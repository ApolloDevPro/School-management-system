<?php
declare(strict_types=1);

/**
 * School profile.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class School extends Model
{
    protected static string $table = 'schools';

    public static function first(): ?array
    {
        return static::one('SELECT * FROM schools ORDER BY id LIMIT 1');
    }
}
