<?php
declare(strict_types=1);

/**
 * Shared PDO connection to the application database.
 *
 * One connection is reused for the whole request. Credentials come from the
 * constants defined in backend/config/config.php (which reads .env).
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Return the single PDO connection, creating it on first use.
     */
    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /**
     * Prepare, bind and execute a statement, returning the executed statement.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::conn()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }
}
