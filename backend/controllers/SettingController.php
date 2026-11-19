<?php
declare(strict_types=1);

/**
 * Per-school settings registry.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class SettingController extends Controller
{
    public function index(): void
    {
        $this->render('settings/index', [
            'pageTitle' => 'Settings',
            'activeNav' => 'settings',
            'groups'    => Setting::grouped(school_id()),
            'canEdit'   => Auth::can('administration', 'edit'),
        ]);
    }

    public function save(): void
    {
        $posted = $_POST['value'] ?? [];
        $ids    = array_map('intval', array_keys($posted));
        $rows   = Setting::rowsForUpdate(school_id(), $ids);

        if (!$rows) {
            $this->respond(false, 'No editable settings were received.', 'settings');
        }

        $errors  = [];
        $updates = [];

        foreach ($rows as $row) {
            $value = $posted[$row['id']] ?? ($row['value_type'] === 'bool' ? '0' : '');
            [$valid, $clean, $error] = Setting::validateValue($row['value_type'], $value);

            if (!$valid) {
                $errors['value_' . $row['id']] = $error;
                continue;
            }

            $updates[(int)$row['id']] = [
                'key'  => $row['setting_key'],
                'old'  => $row['setting_value'],
                'new'  => $clean,
            ];
        }

        if ($errors) {
            $this->respond(false, 'Some settings have invalid values.', 'settings', [], $errors);
        }

        $count   = 0;
        $changed = [];

        foreach ($updates as $id => $item) {
            Setting::execute(
                'UPDATE settings SET setting_value = ?, updated_at = NOW()
                  WHERE id = ? AND school_id = ? AND is_editable = 1',
                [$item['new'], $id, school_id()]
            );

            if ((string)$item['old'] !== (string)$item['new']) {
                $changed[] = ['key' => $item['key'], 'old' => $item['old'], 'new' => $item['new']];
                $count++;
            }
        }

        if ($changed) {
            audit('administration', 'update', 'settings', null, 'Updated ' . $count . ' setting(s)', ['settings' => $changed]);
        }

        $this->respond(
            true,
            $count ? 'Saved ' . $count . ' changed setting(s).' : 'No changes to save.',
            'settings'
        );
    }
}
