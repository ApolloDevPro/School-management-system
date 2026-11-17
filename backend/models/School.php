<?php
declare(strict_types=1);

final class School extends Model
{
    protected static string $table = 'schools';

    public static function first(): ?array
    {
        return static::one('SELECT * FROM schools ORDER BY id LIMIT 1');
    }
}
