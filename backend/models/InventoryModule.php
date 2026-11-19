<?php
declare(strict_types=1);

/**
 * Stock items, stock movements, suppliers and purchase requests.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class InventoryModule extends Model
{
    public static function items(int $schoolId): array
    {
        return static::query(
            'SELECT i.id,i.item_code,i.name,i.unit,i.current_stock,i.reorder_level,i.unit_cost,
                    i.status,cat.name AS category_name
               FROM items i
               LEFT JOIN item_categories cat ON cat.id=i.category_id
              WHERE i.school_id=?
              ORDER BY i.name LIMIT 500',
            [$schoolId]
        );
    }

    public static function categories(int $schoolId): array
    {
        return static::query(
            'SELECT id,name FROM item_categories WHERE school_id=? AND is_active=1 ORDER BY name LIMIT 300',
            [$schoolId]
        );
    }

    public static function createItem(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO items
                (school_id,item_code,name,category_id,unit,description,reorder_level,current_stock,
                 unit_cost,status,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $data['school_id'],$data['item_code'],$data['name'],$data['category_id'],$data['unit'],
            $data['description'],$data['reorder_level'],$data['opening_stock'],$data['unit_cost'],
            'active',$data['created_by'],
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function itemCodeExists(int $schoolId, string $code): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM items WHERE school_id=? AND item_code=?',[$schoolId,$code]);
    }

    public static function requests(int $schoolId): array
    {
        return static::query(
            'SELECT id,request_number,request_date,needed_by,estimated_total,status,justification FROM purchase_requests WHERE school_id=? ORDER BY request_date DESC,id DESC LIMIT 300',
            [$schoolId]
        );
    }

    public static function createRequest(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO purchase_requests (request_number,school_id,requested_by,request_date,needed_by,priority,justification,estimated_total,status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$data['number'],$data['school_id'],$data['user_id'],date('Y-m-d'),$data['needed_by'],$data['priority'],$data['justification'],$data['amount'],'draft']);
        return (int)static::db()->lastInsertId();
    }

    public static function transition(int $id, int $schoolId, string $from, string $to, ?int $userId = null): bool
    {
        $approved = $to === 'approved' ? ', approved_by=?, approved_at=NOW()' : '';
        $params = $userId === null ? [$to,$id,$schoolId,$from] : [$to,$userId,$id,$schoolId,$from];
        return static::execute(
            "UPDATE purchase_requests SET status=?{$approved} WHERE id=? AND school_id=? AND status=?",
            $params
        ) > 0;
    }

    public static function ownsDraft(int $id, int $schoolId, int $userId): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM purchase_requests WHERE id=? AND school_id=? AND requested_by=? AND status=?',[$id,$schoolId,$userId,'draft']);
    }

    public static function suppliers(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,contact_person,phone,alternate_phone,email,address,tin,items_supplied,
                    payment_terms,status,total_orders,total_value
               FROM suppliers WHERE school_id=? ORDER BY name LIMIT 500',
            [$schoolId]
        );
    }

    public static function saveSupplier(array $data): int
    {
        if ($data['id'] > 0) {
            $exists = static::scalar('SELECT COUNT(*) FROM suppliers WHERE id=? AND school_id=?',[$data['id'],$data['school_id']]);
            if (!$exists) {
                throw new DomainException('Supplier not found for this school.');
            }
            static::execute(
                'UPDATE suppliers SET name=?,contact_person=?,phone=?,alternate_phone=?,email=?,address=?,
                    tin=?,items_supplied=?,payment_terms=?,status=?,notes=? WHERE id=? AND school_id=?',
                [
                    $data['name'],$data['contact_person'],$data['phone'],$data['alternate_phone'],
                    $data['email'],$data['address'],$data['tin'],$data['items_supplied'],
                    $data['payment_terms'],$data['status'],$data['notes'],$data['id'],$data['school_id'],
                ]
            );
            return $data['id'];
        }
        static::db()->prepare(
            'INSERT INTO suppliers
                (school_id,name,contact_person,phone,alternate_phone,email,address,tin,items_supplied,
                 payment_terms,status,notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $data['school_id'],$data['name'],$data['contact_person'],$data['phone'],$data['alternate_phone'],
            $data['email'],$data['address'],$data['tin'],$data['items_supplied'],$data['payment_terms'],
            $data['status'],$data['notes'],
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function setSupplierStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE suppliers SET status=? WHERE id=? AND school_id=?',[$status,$id,$schoolId]) > 0;
    }

    /**
     * Apply a stock movement and keep items.current_stock in step.
     *
     * Receipts, returns and opening balances add stock; issues, losses,
     * expirations and transfers remove stock. An adjustment carries a signed
     * quantity so a count can increase or reduce the balance.
     */
    public static function recordMovement(int $schoolId, array $data, int $actorId): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $statement = $db->prepare('SELECT * FROM items WHERE id=? AND school_id=? FOR UPDATE');
            $statement->execute([$data['item_id'],$schoolId]);
            $item = $statement->fetch();
            if (!$item) {
                throw new DomainException('Stock item not found.');
            }

            $quantity = round((float)$data['quantity'], 2);
            $type = $data['movement_type'];
            if ($type === 'adjustment') {
                if ($quantity === 0.0) {
                    throw new DomainException('An adjustment needs a non-zero quantity. Use a negative number to reduce stock.');
                }
                $delta = $quantity;
                $storedQuantity = $quantity;
            } else {
                if ($quantity <= 0) {
                    throw new DomainException('Quantity must be above zero.');
                }
                $adds = in_array($type, ['opening', 'receipt', 'return'], true);
                $delta = $adds ? $quantity : -$quantity;
                $storedQuantity = $quantity;
            }

            $after = round((float)$item['current_stock'] + $delta, 2);
            if ($after < 0) {
                throw new DomainException('This movement would take stock below zero. Available: ' . number_format((float)$item['current_stock'], 2));
            }

            $unitCost = $data['unit_cost'] !== null ? round((float)$data['unit_cost'], 2) : (float)($item['unit_cost'] ?? 0);
            $totalCost = round(abs($delta) * $unitCost, 2);

            $db->prepare('UPDATE items SET current_stock=? WHERE id=? AND school_id=?')
                ->execute([$after,$data['item_id'],$schoolId]);
            $db->prepare(
                'INSERT INTO stock_movements
                    (school_id,item_id,movement_type,quantity,unit_cost,total_cost,quantity_after,
                     movement_date,issued_to,reference_type,stock_location,reason,performed_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $schoolId,$data['item_id'],$type,$storedQuantity,$unitCost,$totalCost,$after,
                $data['movement_date'],$data['issued_to'],
                $type === 'adjustment' ? 'count' : 'manual',
                $data['stock_location'],$data['reason'],$actorId,
            ]);

            audit('inventory', 'stock_movement', 'items', $data['item_id'],
                'Stock ' . $type . ' of ' . $storedQuantity . ' for ' . $item['name'] . '; balance now ' . $after);

            $db->commit();
            return ['quantity_after' => $after, 'item_name' => $item['name'], 'unit' => $item['unit']];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function movements(int $schoolId, int $limit = 120): array
    {
        return static::query(
            'SELECT sm.id,sm.movement_type,sm.quantity,sm.unit_cost,sm.total_cost,sm.quantity_after,
                    sm.movement_date,sm.issued_to,sm.reason,sm.created_at,
                    i.item_code,i.name AS item_name,i.unit
               FROM stock_movements sm
               JOIN items i ON i.id=sm.item_id
              WHERE sm.school_id=?
              ORDER BY sm.movement_date DESC,sm.id DESC
              LIMIT ' . (int)$limit,
            [$schoolId]
        );
    }
}
