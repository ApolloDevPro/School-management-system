<?php
declare(strict_types=1);

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
        $route = $db->query(
            'SELECT r.*, v.plate_number, v.seating_capacity,
                    st.first_name AS driver_first, st.last_name AS driver_last
               FROM routes r
               LEFT JOIN vehicles v ON v.id = r.vehicle_id
               LEFT JOIN staff st ON st.id = r.driver_staff_id
              WHERE r.id = ? AND r.school_id = ?',
            [$routeId, $schoolId]
        )->fetch();
        if (!$route) {
            $this->respond(false, 'Route not found.', 'transport');
            return;
        }
        $students = $db->query(
            'SELECT s.id, s.first_name, s.last_name, s.admission_number,
                    c.name AS class_name, sra.pickup_time, sra.drop_time, sra.stop_location
               FROM student_route_allocations sra
               JOIN students s ON s.id = sra.student_id
               LEFT JOIN classes c ON c.id = s.current_class_id
              WHERE sra.route_id = ? AND sra.status = \'active\'
              ORDER BY s.last_name, s.first_name',
            [$routeId]
        );
        $students = array_map(function ($s, $i) {
            $s['no'] = $i + 1;
            return $s;
        }, $students, array_keys($students));
        $school = school_row() ?? [];
        $driverName = trim(($route['driver_first'] ?? '').' '.($route['driver_last'] ?? ''));
        $html = PdfService::transportRouteSheetHtml($school, $route, $students, [
            'plate_number' => $route['plate_number'] ?? '-',
            'driver_name' => $driverName ?: '-',
            'capacity' => $route['seating_capacity'] ?? '-',
        ]);
        PdfService::output($html, 'route_sheet_'.$route['route_code'].'_'.$routeId.'.pdf');
    }
}
