<?php
declare(strict_types=1);

abstract class Model
{
    protected static string $table = '';

    protected static function db(): PDO
    {
        return Database::conn();
    }

    public static function find(int $id): ?array
    {
        $statement = static::db()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public static function all(string $orderBy = 'id'): array
    {
        return static::db()->query('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy)->fetchAll();
    }

    public static function query(string $sql, array $params = []): array
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: null;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public static function insert(array $data): int
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . static::$table
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        static::db()->prepare($sql)->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

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
