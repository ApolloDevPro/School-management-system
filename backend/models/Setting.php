<?php
declare(strict_types=1);

/**
 * Settings registry.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Setting extends Model
{
    protected static string $table = 'settings';

    public static function get(int $schoolId, string $key, mixed $default = null): mixed
    {
        $value = static::scalar(
            'SELECT setting_value FROM settings WHERE school_id = ? AND setting_key = ?',
            [$schoolId, $key]
        );
        return $value === null ? $default : $value;
    }

    public static function grouped(int $schoolId): array
    {
        $rows = static::query(
            'SELECT * FROM settings WHERE school_id = ? ORDER BY setting_group, setting_key',
            [$schoolId]
        );

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['setting_group']][] = $row;
        }
        return $groups;
    }

    public static function rowsForUpdate(int $schoolId, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        return static::query(
            'SELECT * FROM settings WHERE school_id = ? AND id IN (' . $placeholders . ') AND is_editable = 1',
            [$schoolId, ...$ids]
        );
    }

    public static function validateValue(string $type, mixed $value): array
    {
        switch ($type) {
            case 'int':
                if (!preg_match('/^-?\d+$/', (string)$value)) {
                    return [false, '', 'Enter a whole number.'];
                }
                return [true, (string)(int)$value, ''];

            case 'decimal':
                if (!is_numeric($value)) {
                    return [false, '', 'Enter a number.'];
                }
                return [true, (string)(float)$value, ''];

            case 'bool':
                $bool = in_array($value, [1, '1', true, 'true', 'on'], true) ? '1' : '0';
                return [true, $bool, ''];

            case 'json':
                json_decode((string)$value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [false, '', 'Enter valid JSON.'];
                }
                return [true, (string)$value, ''];

            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value) || !strtotime((string)$value)) {
                    return [false, '', 'Enter a valid date (YYYY-MM-DD).'];
                }
                return [true, (string)$value, ''];

            default:
                return [true, (string)$value, ''];
        }
    }
}
