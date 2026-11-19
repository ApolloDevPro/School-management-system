<?php
declare(strict_types=1);

/**
 * Base model: small static helpers for the schema.
 *
 * Every model names its table in $table, then uses these helpers instead of
 * opening its own connection. All values are bound parameters.
 */
abstract class Model
{
    /** Table backing this model. Set in each concrete model. */
    protected static string $table = '';

    protected static function db(): PDO
    {
        return Database::conn();
    }

    /**
     * Fetch one row by primary key.
     */
    public static function find(int $id): ?array
    {
        $statement = static::db()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    /**
     * Fetch every row, optionally ordered by a trusted column expression.
     */
    public static function all(string $orderBy = 'id'): array
    {
        return static::db()->query('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy)->fetchAll();
    }

    /**
     * Run a SELECT and return all rows.
     */
    public static function query(string $sql, array $params = []): array
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /**
     * Run a SELECT and return the first row, or null.
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: null;
    }

    /**
     * Run a SELECT and return the first column of the first row, or null.
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Run an INSERT/UPDATE/DELETE and return the affected row count.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    /**
     * Insert an associative array and return the new primary key.
     */
    public static function insert(array $data): int
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . static::$table
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        static::db()->prepare($sql)->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    /**
     * Update one row by primary key. Returns true when a row changed.
     */
    public static function updateRow(int $id, array $data): bool
    {
        $assignments = implode(', ', array_map(fn (string $column): string => $column . ' = ?', array_keys($data)));
        $statement = static::db()->prepare(
            'UPDATE ' . static::$table . ' SET ' . $assignments . ' WHERE id = ?'
        );
        $statement->execute([...array_values($data), $id]);
        return $statement->rowCount() > 0;
    }
}
