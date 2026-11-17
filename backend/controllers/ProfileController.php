<?php
declare(strict_types=1);

final class ProfileController extends Controller
{
    public function index(): void
    {
        $userId = (int)Auth::user()['id'];
        $profile = User::find($userId);
        if (!$profile) {
            redirect('dashboard');
        }

        $schoolId = school_id();
        $school   = school_row() ?? [];
        $userType = $profile['user_type'] ?? 'staff';

        $profile['school_name'] = $school['name'] ?? '-';
        $profile['school_address'] = $school['physical_address'] ?? '-';
        $profile['school_phone'] = $school['phone'] ?? '-';
        $profile['school_email'] = $school['email'] ?? '-';

        // Role-specific details linked to this user
        switch ($userType) {
            case 'student':
                $profile['student_details'] = $this->studentDetails($profile['student_id'] ?? null, $schoolId);
                break;
            case 'staff':
                $profile['staff_details'] = $this->staffDetails($profile['staff_id'] ?? null, $schoolId);
                break;
            case 'parent':
            case 'guardian':
                $profile['guardian_details'] = $this->guardianDetails($profile['guardian_id'] ?? null, $schoolId);
                $profile['linked_students'] = $this->linkedStudents($profile['guardian_id'] ?? null, $schoolId);
                break;
        }

        $this->render('profile/index', [
            'pageTitle' => 'My account',
            'activeNav' => 'account',
            'profile' => $profile,
        ]);
    }

    public function update(): void
    {
        $id = (int)Auth::user()['id'];
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $errors = [];

        if ($fullName === '' || mb_strlen($fullName) > 140) {
            $errors['full_name'] = 'Enter your full name (up to 140 characters).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($email !== '' && User::emailTaken($email, school_id(), $id)) {
            $errors['email'] = 'This email is already used by another account.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{6,40}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'profile', [], $errors);
        }

        User::updateProfile($id, ['full_name' => $fullName, 'email' => $email, 'phone' => $phone]);
        $_SESSION['user']['full_name'] = $fullName;
        audit('security', 'profile_updated', 'users', $id, 'Updated own profile');
        $this->respond(true, 'Profile updated.', 'profile');
    }

    private function studentDetails(?int $studentId, int $schoolId): ?array
    {
        if (!$studentId) {
            return null;
        }
        return Database::conn()->prepare(
            'SELECT s.admission_number, s.registration_number, s.first_name, s.last_name,
                    s.middle_name, s.gender, s.date_of_birth, s.nationality, s.religion,
                    s.student_type, s.status, s.admission_date, s.previous_school,
                    s.previous_result, s.photo_path,
                    c.name AS class_name, c.level AS class_level,
                    st.name AS stream_name, h.name AS house_name,
                    y.name AS academic_year
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
               LEFT JOIN houses h ON h.id = s.house_id
               LEFT JOIN academic_years y ON y.id = s.academic_year_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        )->fetch();
    }

    private function staffDetails(?int $staffId, int $schoolId): ?array
    {
        if (!$staffId) {
            return null;
        }
        return Database::conn()->prepare(
            'SELECT sf.staff_number, sf.first_name, sf.last_name, sf.middle_name,
                    sf.gender, sf.date_of_birth, sf.nationality, sf.marital_status,
                    sf.phone, sf.phone_alt, sf.email, sf.address,
                    sf.highest_qualification, sf.teacher_registration_number,
                    sf.employment_type, sf.employment_status, sf.position,
                    sf.date_of_appointment, sf.date_of_confirmation,
                    sf.nssf_number, sf.ura_tin, sf.emergency_contact_name,
                    sf.emergency_contact_phone, sf.photo_path,
                    d.name AS department_name
               FROM staff sf
               LEFT JOIN departments d ON d.id = sf.department_id
              WHERE sf.id = ? AND sf.school_id = ?',
            [$staffId, $schoolId]
        )->fetch();
    }

    private function guardianDetails(?int $guardianId, int $schoolId): ?array
    {
        if (!$guardianId) {
            return null;
        }
        return Database::conn()->prepare(
            'SELECT g.full_name, g.first_name, g.last_name, g.relationship,
                    g.phone, g.phone_alt, g.email, g.occupation, g.address,
                    g.district, g.nin, g.is_primary, g.receive_sms, g.receive_email
               FROM guardians g
              WHERE g.id = ? AND g.school_id = ?',
            [$guardianId, $schoolId]
        )->fetch();
    }

    private function linkedStudents(?int $guardianId, int $schoolId): array
    {
        if (!$guardianId) {
            return [];
        }
        return Database::conn()->prepare(
            'SELECT s.admission_number, s.first_name, s.last_name,
                    s.student_type, s.status,
                    c.name AS class_name, st.name AS stream_name,
                    sg.relationship AS link_relationship, sg.is_primary, sg.lives_with, sg.is_fee_payer
               FROM student_guardians sg
               JOIN students s ON s.id = sg.student_id
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
              WHERE sg.guardian_id = ? AND s.school_id = ?
              ORDER BY sg.is_primary DESC, s.last_name',
            [$guardianId, $schoolId]
        )->execute()->fetchAll();
    }
}
