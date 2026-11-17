<?php
declare(strict_types=1);

final class CocurricularController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('cocurricular/index', [
            'pageTitle' => 'Co-curricular activities',
            'activeNav' => 'cocurricular',
            'clubs'     => CocurricularModule::clubs($schoolId),
            'canAdd'    => Auth::can('cocurricular', 'add'),
            'canEdit'   => Auth::can('cocurricular', 'edit'),
        ]);
    }

    public function storeClub(): void
    {
        $name        = trim((string)($_POST['name'] ?? ''));
        $category    = (string)($_POST['club_category'] ?? 'general');
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160) { $errors['name'] = 'Enter a club name (max 160 chars).'; }
        if (!in_array($category, ['academic','sports','arts','debate','science','religious','community','general'], true)) {
            $category = 'general';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'cocurricular', [], $errors);
        }

        $id = CocurricularModule::createClub([
            'school_id'   => school_id(),
            'name'        => $name,
            'category'    => $category,
            'description' => $description ?: null,
        ]);

        audit('cocurricular', 'club_created', 'clubs', $id, 'Created club: ' . $name);
        $this->respond(true, 'Club "' . $name . '" created.', 'cocurricular');
    }

    public function toggleClub(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $this->respond(false, 'Invalid status.', 'cocurricular');
        }
        $ok = CocurricularModule::changeStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Club status updated.' : 'Could not update club.', 'cocurricular');
    }
}
