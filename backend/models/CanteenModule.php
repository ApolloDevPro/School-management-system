<?php
declare(strict_types=1);

/**
 * Canteen products and student wallets.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class CanteenModule extends Model
{
    public static function products(int $schoolId): array
    {
        return static::query(
            'SELECT id,product_code,name,category,unit,selling_price,current_stock,status FROM products WHERE school_id=? ORDER BY name LIMIT 500',
            [$schoolId]
        );
    }

    public static function createProduct(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO products (school_id,product_code,name,category,unit,cost_price,selling_price,current_stock,reorder_level,status)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$data['school_id'],$data['code'],$data['name'],$data['category'],$data['unit'],$data['cost'],$data['price'],$data['stock'],$data['reorder'],'active']);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE products SET status=? WHERE id=? AND school_id=?',[$status,$id,$schoolId]) > 0;
    }
}
