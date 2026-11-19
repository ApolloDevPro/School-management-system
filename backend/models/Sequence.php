<?php
declare(strict_types=1);

/**
 * Human-readable document number sequences.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Sequence extends Model
{
    protected static string $table = 'sequences';

    public static function next(int $schoolId, string $key): string
    {
        $db = static::db();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $statement = $db->prepare(
                'SELECT prefix, suffix, padding, next_value
                   FROM sequences
                  WHERE school_id = ? AND seq_key = ?
                   FOR UPDATE'
            );
            $statement->execute([$schoolId, $key]);
            $row = $statement->fetch();

            if (!$row) {
                if ($ownsTransaction) {
                    $db->rollBack();
                }
                return '';
            }

            $value   = (int)$row['next_value'];
            $padded  = str_pad((string)$value, max(1, (int)$row['padding']), '0', STR_PAD_LEFT);
            $number  = $row['prefix'] . $padded . $row['suffix'];

            $db->prepare(
                'UPDATE sequences SET next_value = next_value + 1, updated_at = NOW()
                  WHERE school_id = ? AND seq_key = ?'
            )->execute([$schoolId, $key]);

            if ($ownsTransaction) {
                $db->commit();
            }
            return $number;
        } catch (Throwable $error) {
            if ($ownsTransaction) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
