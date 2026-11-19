<?php
declare(strict_types=1);

/**
 * Routes, vehicles, drivers and route sheets.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class TransportController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('transport/index', [
            'pageTitle' => 'Transport',
            'activeNav' => 'transport',
            'routes'    => TransportModule::routes($schoolId),
            'canAdd'    => Auth::can('transport', 'add'),
            'canEdit'   => Auth::can('transport', 'edit'),
        ]);
    }

    public function storeRoute(): void
    {
        $name     = trim((string)($_POST['route_name'] ?? ''));
        $code     = trim((string)($_POST['route_code'] ?? ''));
        $distance = (float)($_POST['distance_km'] ?? 0);
        $fare     = (float)($_POST['fare_per_term'] ?? 0);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160) { $errors['route_name'] = 'Enter a route name (max 160 chars).'; }
        if ($distance < 0) { $errors['distance_km']    = 'Distance must be 0 or more.'; }
        if ($fare < 0)     { $errors['fare_per_term']  = 'Fare must be 0 or more.'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'transport', [], $errors);
        }

        $id = TransportModule::createRoute([
            'school_id'   => school_id(),
            'code'        => $code !== '' ? $code : null,
            'name'        => $name,
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'distance'    => $distance,
            'fare'        => $fare,
        ]);

        audit('transport', 'route_created', 'routes', $id, 'Added route: ' . $name);
        $this->respond(true, 'Route "' . $name . '" created.', 'transport');
    }

    public function toggleRoute(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $this->respond(false, 'Invalid status.', 'transport');
        }
        $ok = TransportModule::changeStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Route status updated.' : 'Could not update route status.', 'transport');
    }

    public function transportRouteSheet(): void
    {
        $schoolId = school_id();
        $routeId = (int)($_GET['route_id'] ?? 0);
        $db = Database::conn();
        $route = Database::query(
            'SELECT r.*, v.plate_number, v.seating_capacity,
                    d.full_name AS driver_name
               FROM routes r
               LEFT JOIN vehicles v ON v.id = r.vehicle_id
               LEFT JOIN drivers d ON d.id = r.driver_id
              WHERE r.id = ? AND r.school_id = ?',
            [$routeId, $schoolId]
        )->fetch();
        if (!$route) {
            $this->respond(false, 'Route not found.', 'transport');
            return;
        }
        $students = Database::query(
            'SELECT s.id, s.first_name, s.last_name, s.admission_number,
                    c.name AS class_name,
                    COALESCE(rs.pick_up_time, r.pick_up_time) AS pickup_time,
                    COALESCE(rs.drop_off_time, r.drop_off_time) AS drop_time,
                    COALESCE(rs.stop_name, sra.pickup_point, r.route_name) AS stop_location
               FROM student_route_allocations sra
               JOIN students s ON s.id = sra.student_id
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN routes r ON r.id = sra.route_id
               LEFT JOIN route_stops rs ON rs.id = sra.stop_id
              WHERE sra.route_id = ? AND sra.status = \'active\'
              ORDER BY s.last_name, s.first_name',
            [$routeId]
        );
        $students = array_map(function ($s, $i) {
            $s['no'] = $i + 1;
            return $s;
        }, $students, array_keys($students));
        $school = school_row() ?? [];
        $driverName = trim((string)($route['driver_name'] ?? ''));
        $html = PdfService::transportRouteSheetHtml($school, $route, $students, [
            'plate_number' => $route['plate_number'] ?? '-',
            'driver_name' => $driverName ?: '-',
            'capacity' => $route['seating_capacity'] ?? '-',
        ]);
        PdfService::output($html, 'route_sheet_'.$route['route_code'].'_'.$routeId.'.pdf');
    }

    public function saveVehicle(): void
    {
        $schoolId = school_id();
        $db = Database::conn();
        $id = (int)($_POST['id'] ?? 0);
        $plateNumber = strtoupper(trim((string)($_POST['plate_number'] ?? '')));
        $vehicleType = (string)($_POST['vehicle_type'] ?? 'bus');
        $make = trim((string)($_POST['make'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $modelYear = filter_var($_POST['model_year'] ?? null, FILTER_VALIDATE_INT);
        $colour = trim((string)($_POST['colour'] ?? ''));
        $seatingCapacity = filter_var($_POST['seating_capacity'] ?? '0', FILTER_VALIDATE_INT);
        $fuelType = (string)($_POST['fuel_type'] ?? 'diesel');
        $insuranceNumber = trim((string)($_POST['insurance_number'] ?? ''));
        $insuranceExpiry = $this->optionalDate($_POST['insurance_expiry'] ?? null);
        $insurancePremium = $this->optionalAmount($_POST['insurance_premium'] ?? null);
        $inspectionDue = $this->optionalDate($_POST['inspection_due'] ?? null);
        $registrationExpiry = $this->optionalDate($_POST['registration_expiry'] ?? null);
        $currentOdometer = $this->optionalAmount($_POST['current_odometer'] ?? null);
        $assignedDriverId = $this->optionalInt($_POST['assigned_driver_id'] ?? null);
        $status = (string)($_POST['status'] ?? 'active');

        if ($plateNumber === '' || mb_strlen($plateNumber) > 20
            || !in_array($vehicleType, ['bus','van','pickup','ambulance','motorcycle','trailer'], true)
            || mb_strlen($make) > 60 || mb_strlen($model) > 60
            || ($modelYear !== false && ($modelYear < 1900 || $modelYear > 2100))
            || mb_strlen($colour) > 40
            || !in_array($fuelType, ['petrol','diesel','electric','hybrid'], true)
            || mb_strlen($insuranceNumber) > 60
            || !$this->validDateOpt($insuranceExpiry)
            || !$this->validDateOpt($inspectionDue)
            || !$this->validDateOpt($registrationExpiry)
            || ($seatingCapacity === false || $seatingCapacity < 0 || $seatingCapacity > 1000)
            || !in_array($status, ['active','in_repair','grounded','sold','retired'], true)) {
            $this->respond(false, 'Enter valid vehicle details.', 'transport');
            return;
        }

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = $db->query('SELECT id FROM vehicles WHERE id = ? AND school_id = ?', [$id, $schoolId])->fetch();
                if (!$exists) {
                    throw new DomainException('Vehicle not found.');
                }
                $db->prepare(
                    'UPDATE vehicles SET plate_number = ?, vehicle_type = ?, make = ?, model = ?,
                        model_year = ?, colour = ?, seating_capacity = ?, fuel_type = ?,
                        insurance_number = ?, insurance_expiry = ?, insurance_premium = ?,
                        inspection_due = ?, registration_expiry = ?, current_odometer = ?,
                        assigned_driver_id = ?, status = ?
                      WHERE id = ? AND school_id = ?'
                )->execute([
                    $plateNumber, $vehicleType, $make, $model, $modelYear, $colour,
                    $seatingCapacity, $fuelType, $insuranceNumber, $insuranceExpiry,
                    $insurancePremium, $inspectionDue, $registrationExpiry,
                    $currentOdometer, $assignedDriverId, $status, $id, $schoolId
                ]);
                audit('transport', 'update_vehicle', 'vehicles', $id, 'Updated vehicle: ' . $plateNumber);
                $this->respond(true, 'Vehicle updated.', 'transport');
            } else {
                $db->prepare(
                    'INSERT INTO vehicles
                        (school_id, plate_number, vehicle_type, make, model, model_year, colour,
                         seating_capacity, fuel_type, insurance_number, insurance_expiry,
                         insurance_premium, inspection_due, registration_expiry, current_odometer,
                         assigned_driver_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $schoolId, $plateNumber, $vehicleType, $make, $model, $modelYear, $colour,
                    $seatingCapacity, $fuelType, $insuranceNumber, $insuranceExpiry,
                    $insurancePremium, $inspectionDue, $registrationExpiry, $currentOdometer,
                    $assignedDriverId, $status
                ]);
                $newId = (int)$db->lastInsertId();
                audit('transport', 'create_vehicle', 'vehicles', $newId, 'Registered vehicle: ' . $plateNumber);
                $this->respond(true, 'Vehicle registered.', 'transport');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'transport');
        }
    }

    public function toggleVehicle(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active','in_repair','grounded','sold','retired'], true)) {
            $this->respond(false, 'Invalid status.', 'transport');
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();
        $db->prepare('UPDATE vehicles SET status = ? WHERE id = ? AND school_id = ?')
            ->execute([$status, $id, $schoolId]);
        audit('transport', 'vehicle_status', 'vehicles', $id, 'Vehicle status changed to ' . $status);
        $this->respond(true, 'Vehicle status updated.', 'transport');
    }

    public function saveDriver(): void
    {
        $schoolId = school_id();
        $db = Database::conn();
        $id = (int)($_POST['id'] ?? 0);
        $staffId = $this->optionalInt($_POST['staff_id'] ?? null);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $licenceNumber = trim((string)($_POST['licence_number'] ?? ''));
        $licenceClass = trim((string)($_POST['licence_class'] ?? ''));
        $licenceExpiry = $this->optionalDate($_POST['licence_expiry'] ?? null);
        $dateJoined = $this->optionalDate($_POST['date_joined'] ?? null);
        $isExternal = isset($_POST['is_external']) ? 1 : 0;
        $vehicleId = $this->optionalInt($_POST['vehicle_id'] ?? null);
        $status = (string)($_POST['status'] ?? 'active');

        if ($fullName === '' || mb_strlen($fullName) > 140
            || mb_strlen($phone) > 40
            || $licenceNumber === '' || mb_strlen($licenceNumber) > 60
            || mb_strlen($licenceClass) > 20
            || !$this->validDateOpt($licenceExpiry)
            || !$this->validDateOpt($dateJoined)
            || !in_array($status, ['active','on_leave','suspended','former'], true)) {
            $this->respond(false, 'Enter valid driver details.', 'transport');
            return;
        }

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = $db->query('SELECT id FROM drivers WHERE id = ? AND school_id = ?', [$id, $schoolId])->fetch();
                if (!$exists) {
                    throw new DomainException('Driver not found.');
                }
                $db->prepare(
                    'UPDATE drivers SET staff_id = ?, full_name = ?, phone = ?, licence_number = ?,
                        licence_class = ?, licence_expiry = ?, date_joined = ?, is_external = ?,
                        vehicle_id = ?, status = ?
                      WHERE id = ? AND school_id = ?'
                )->execute([
                    $staffId, $fullName, $phone, $licenceNumber, $licenceClass,
                    $licenceExpiry, $dateJoined, $isExternal, $vehicleId, $status, $id, $schoolId
                ]);
                audit('transport', 'update_driver', 'drivers', $id, 'Updated driver: ' . $fullName);
                $this->respond(true, 'Driver updated.', 'transport');
            } else {
                $db->prepare(
                    'INSERT INTO drivers
                        (school_id, staff_id, full_name, phone, licence_number, licence_class,
                         licence_expiry, date_joined, is_external, vehicle_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $schoolId, $staffId, $fullName, $phone, $licenceNumber, $licenceClass,
                    $licenceExpiry, $dateJoined, $isExternal, $vehicleId, $status
                ]);
                $newId = (int)$db->lastInsertId();
                audit('transport', 'create_driver', 'drivers', $newId, 'Registered driver: ' . $fullName);
                $this->respond(true, 'Driver registered.', 'transport');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'transport');
        }
    }

    public function toggleDriver(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active','on_leave','suspended','former'], true)) {
            $this->respond(false, 'Invalid status.', 'transport');
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();
        $db->prepare('UPDATE drivers SET status = ? WHERE id = ? AND school_id = ?')
            ->execute([$status, $id, $schoolId]);
        audit('transport', 'driver_status', 'drivers', $id, 'Driver status changed to ' . $status);
        $this->respond(true, 'Driver status updated.', 'transport');
    }

    private function optionalDate(mixed $value, string $label = ''): ?string
    {
        return $value === null || trim((string)$value) === '' ? null : $this->date($value, $label);
    }

    private function date(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new DomainException(($label ?: 'Date') . ' must be a valid date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException(($label ?: 'Date') . ' must be a valid date.');
        }
        return $value;
    }

    private function optionalAmount(mixed $value, string $label): ?float
    {
        return $value === null || trim((string)$value) === '' ? null : $this->amount($value, $label, true);
    }

    private function amount(mixed $value, string $label, bool $allowZero = false): float
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string)$value)) {
            throw new DomainException($label . ' must be a valid amount.');
        }
        $amount = (float)$value;
        if ((!$allowZero && $amount <= 0) || $amount > 99999999999.99) {
            throw new DomainException($label . ' is outside the permitted range.');
        }
        return round($amount, 2);
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '' || (string)$value === '0') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            return null;
        }
        return $parsed;
    }

    private function validDateOpt(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
