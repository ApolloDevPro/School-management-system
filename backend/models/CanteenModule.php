<?php
declare(strict_types=1);

/**
 * Canteen products, point-of-sale, stock and student wallets.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class CanteenModule extends Model
{
    public const CATEGORIES = ['uniform', 'scholastic', 'textbook', 'stationery', 'snack', 'beverage', 'supplies', 'other'];

    public static function products(int $schoolId): array
    {
        return static::query(
            'SELECT id,product_code,name,category,unit,cost_price,selling_price,current_stock,reorder_level,track_stock,status
               FROM products WHERE school_id=? ORDER BY name LIMIT 500',
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

    /**
     * Active students with their wallet balance, used by the sale screen.
     */
    public static function studentsWithWallet(int $schoolId): array
    {
        return static::query(
            "SELECT s.id,s.admission_number,s.first_name,s.last_name,c.name AS class_name,
                    w.id AS wallet_id,w.balance,w.status AS wallet_status,w.daily_limit
               FROM students s
               LEFT JOIN classes c ON c.id=s.current_class_id
               LEFT JOIN student_wallets w ON w.student_id=s.id
              WHERE s.school_id=? AND s.status='active'
              ORDER BY s.last_name,s.first_name LIMIT 2000",
            [$schoolId]
        );
    }

    public static function sales(int $schoolId, int $limit = 80): array
    {
        return static::query(
            "SELECT sa.id,sa.sale_number,sa.sale_date,sa.sale_time,sa.buyer_type,sa.student_id,
                    sa.subtotal,sa.discount,sa.total_amount,sa.amount_paid,sa.amount_change,
                    sa.payment_method,sa.status,sa.void_reason,
                    s.admission_number,s.first_name,s.last_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id=sa.id) AS line_count
               FROM sales sa
               LEFT JOIN students s ON s.id=sa.student_id
              WHERE sa.school_id=?
              ORDER BY sa.sale_date DESC,sa.sale_time DESC,sa.id DESC
              LIMIT " . (int)$limit,
            [$schoolId]
        );
    }

    /**
     * Record a cash or wallet sale, deduct product stock and write the
     * matching wallet and stock movements in one transaction.
     */
    public static function recordSale(int $schoolId, array $data, int $actorId): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $quantities = [];
            foreach ($data['lines'] as $line) {
                $productId = (int)$line['product_id'];
                $quantity = round((float)$line['quantity'], 2);
                if ($productId < 1 || $quantity <= 0) {
                    throw new DomainException('Each sale line needs a product and a quantity above zero.');
                }
                $quantities[$productId] = round(($quantities[$productId] ?? 0) + $quantity, 2);
            }
            if (!$quantities) {
                throw new DomainException('Add at least one product to the sale.');
            }
            if (count($quantities) > 50) {
                throw new DomainException('A single sale cannot contain more than 50 different products.');
            }

            $placeholders = implode(',', array_fill(0, count($quantities), '?'));
            $statement = $db->prepare(
                "SELECT id,name,unit,cost_price,selling_price,current_stock,track_stock,status
                   FROM products
                  WHERE school_id=? AND id IN ($placeholders)
                  FOR UPDATE"
            );
            $statement->execute(array_merge([$schoolId], array_keys($quantities)));
            $products = [];
            foreach ($statement->fetchAll() as $product) {
                $products[(int)$product['id']] = $product;
            }
            if (count($products) !== count($quantities)) {
                throw new DomainException('One of the selected products is no longer available.');
            }

            $subtotal = 0.0;
            $lines = [];
            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId];
                if ($product['status'] !== 'active') {
                    throw new DomainException('Product "' . $product['name'] . '" is not active.');
                }
                if ((int)$product['track_stock'] === 1 && $quantity > (float)$product['current_stock']) {
                    throw new DomainException('Only ' . rtrim(rtrim(number_format((float)$product['current_stock'], 2, '.', ''), '0'), '.') . ' ' . $product['unit'] . ' of ' . $product['name'] . ' left in stock.');
                }
                $unitPrice = round((float)$product['selling_price'], 2);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ];
            }
            $subtotal = round($subtotal, 2);
            $discount = round(min((float)($data['discount'] ?? 0), $subtotal), 2);
            $total = round($subtotal - $discount, 2);

            $studentId = null;
            $wallet = null;
            $walletBefore = 0.0;
            $walletAfter = 0.0;
            if ($data['buyer_type'] === 'student') {
                $studentCheck = $db->prepare("SELECT id FROM students WHERE id=? AND school_id=? AND status='active'");
                $studentCheck->execute([(int)$data['student_id'], $schoolId]);
                if (!$studentCheck->fetch()) {
                    throw new DomainException('Choose an active student for this sale.');
                }
                $studentId = (int)$data['student_id'];
            } elseif ($data['buyer_type'] === 'staff' || $data['buyer_type'] === 'visitor') {
                $studentId = null;
            } else {
                $studentId = null;
            }

            if ($data['payment_method'] === 'wallet') {
                if ($studentId === null) {
                    throw new DomainException('A wallet sale needs a student buyer.');
                }
                $walletStmt = $db->prepare("SELECT * FROM student_wallets WHERE student_id=? AND status='active' FOR UPDATE");
                $walletStmt->execute([$studentId]);
                $wallet = $walletStmt->fetch();
                if (!$wallet) {
                    throw new DomainException('This student has no active wallet. Record a deposit first.');
                }
                $walletBefore = (float)$wallet['balance'];
                if ($walletBefore < $total) {
                    throw new DomainException('Wallet balance is too low. Available: ' . number_format($walletBefore, 2));
                }
                if ($wallet['daily_limit'] !== null && (float)$wallet['daily_limit'] > 0) {
                    $spent = $db->prepare(
                        "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions
                          WHERE wallet_id=? AND transaction_type='purchase' AND DATE(created_at)=CURDATE()"
                    );
                    $spent->execute([(int)$wallet['id']]);
                    if ((float)$spent->fetchColumn() + $total > (float)$wallet['daily_limit']) {
                        throw new DomainException('This sale would exceed the wallet daily spending limit.');
                    }
                }
                $walletAfter = round($walletBefore - $total, 2);
            } else {
                $amountPaid = round((float)($data['amount_paid'] ?? 0), 2);
                if ($total > 0 && $amountPaid < $total) {
                    throw new DomainException('Cash received must cover the sale total.');
                }
            }

            $saleNumber = 'SAL-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $saleStmt = $db->prepare(
                "INSERT INTO sales
                    (sale_number,school_id,buyer_type,student_id,sale_date,sale_time,subtotal,discount,
                     total_amount,amount_paid,amount_change,payment_method,status,notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'completed',?)"
            );
            $amountPaid = $data['payment_method'] === 'wallet' ? $total : round((float)($data['amount_paid'] ?? 0), 2);
            $saleStmt->execute([
                $saleNumber, $schoolId, $data['buyer_type'], $studentId,
                date('Y-m-d'), date('H:i:s'), $subtotal, $discount, $total,
                $amountPaid, $data['payment_method'] === 'wallet' ? 0 : round($amountPaid - $total, 2),
                $data['payment_method'], $data['notes'] ?? null,
            ]);
            $saleId = (int)$db->lastInsertId();

            $insertItem = $db->prepare(
                'INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,unit_cost,discount,total_price,profit)
                 VALUES (?,?,?,?,?,0,?,?)'
            );
            $stockUpdate = $db->prepare('UPDATE products SET current_stock=?, status=? WHERE id=? AND school_id=?');
            $movement = $db->prepare(
                "INSERT INTO product_stock_movements
                    (school_id,product_id,movement_type,quantity,unit_cost,quantity_after,
                     reference_type,reference_id,movement_date,reason,performed_by)
                 VALUES (?,?,'sale',?,?,?,'sale',?,NOW(),?,?)"
            );
            foreach ($lines as $line) {
                $product = $line['product'];
                $cost = round((float)$product['cost_price'], 2);
                $insertItem->execute([
                    $saleId, (int)$product['id'], $line['quantity'], $line['unit_price'], $cost,
                    $line['total'], round(($line['unit_price'] - $cost) * $line['quantity'], 2),
                ]);
                if ((int)$product['track_stock'] === 1) {
                    $after = round((float)$product['current_stock'] - $line['quantity'], 2);
                    $newStatus = $after <= 0 ? 'out_of_stock' : 'active';
                    $stockUpdate->execute([$after, $newStatus, (int)$product['id'], $schoolId]);
                    $movement->execute([
                        $schoolId, (int)$product['id'], $line['quantity'], $cost, $after,
                        $saleId, 'Canteen sale ' . $saleNumber, $actorId,
                    ]);
                }
            }

            $walletTxnId = null;
            if ($data['payment_method'] === 'wallet' && $wallet !== null) {
                $db->prepare('UPDATE student_wallets SET balance=? WHERE id=?')->execute([$walletAfter, (int)$wallet['id']]);
                $db->prepare(
                    "INSERT INTO wallet_transactions
                        (wallet_id,transaction_type,amount,balance_before,balance_after,reference,sale_id,performed_by,notes)
                     VALUES (?,'purchase',?,?,?,?,?,?,?)"
                )->execute([
                    (int)$wallet['id'], $total, $walletBefore, $walletAfter,
                    $saleNumber, $saleId, $actorId, 'Canteen purchase',
                ]);
                $walletTxnId = (int)$db->lastInsertId();
                $db->prepare('UPDATE sales SET wallet_transaction_id=? WHERE id=?')->execute([$walletTxnId, $saleId]);
            }

            audit('canteen', 'sale', 'sales', $saleId, 'Recorded sale ' . $saleNumber . ' totalling ' . number_format($total, 2));
            $db->commit();
            return [
                'id' => $saleId,
                'sale_number' => $saleNumber,
                'total' => $total,
                'change' => $data['payment_method'] === 'wallet' ? 0.0 : round($amountPaid - $total, 2),
                'wallet_balance' => $data['payment_method'] === 'wallet' ? $walletAfter : null,
            ];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Void a completed sale, restore stock and refund a wallet payment.
     */
    public static function voidSale(int $saleId, int $schoolId, string $reason, int $actorId): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $statement = $db->prepare('SELECT * FROM sales WHERE id=? AND school_id=? FOR UPDATE');
            $statement->execute([$saleId, $schoolId]);
            $sale = $statement->fetch();
            if (!$sale) {
                throw new DomainException('Sale not found.');
            }
            if ($sale['status'] !== 'completed') {
                throw new DomainException('Only a completed sale can be voided.');
            }

            $items = $db->prepare('SELECT * FROM sale_items WHERE sale_id=?');
            $items->execute([$saleId]);
            $productStmt = $db->prepare('SELECT id,name,current_stock,track_stock FROM products WHERE id=? AND school_id=? FOR UPDATE');
            $restore = $db->prepare('UPDATE products SET current_stock=?, status=? WHERE id=?');
            $movement = $db->prepare(
                "INSERT INTO product_stock_movements
                    (school_id,product_id,movement_type,quantity,quantity_after,reference_type,reference_id,movement_date,reason,performed_by)
                 VALUES (?,?,'return',?,?,'sale',?,NOW(),?,?)"
            );
            foreach ($items->fetchAll() as $item) {
                $productStmt->execute([(int)$item['product_id'], $schoolId]);
                $product = $productStmt->fetch();
                if (!$product) {
                    continue;
                }
                if ((int)$product['track_stock'] === 1) {
                    $after = round((float)$product['current_stock'] + (float)$item['quantity'], 2);
                    $restore->execute([$after, 'active', (int)$product['id']]);
                    $movement->execute([
                        $schoolId, (int)$product['id'], (float)$item['quantity'], $after,
                        $saleId, 'Void sale ' . $sale['sale_number'], $actorId,
                    ]);
                }
            }

            if ($sale['payment_method'] === 'wallet' && $sale['student_id'] !== null) {
                $walletStmt = $db->prepare("SELECT * FROM student_wallets WHERE student_id=? AND status='active' FOR UPDATE");
                $walletStmt->execute([(int)$sale['student_id']]);
                $wallet = $walletStmt->fetch();
                if ($wallet) {
                    $before = (float)$wallet['balance'];
                    $after = round($before + (float)$sale['total_amount'], 2);
                    $db->prepare('UPDATE student_wallets SET balance=? WHERE id=?')->execute([$after, (int)$wallet['id']]);
                    $db->prepare(
                        "INSERT INTO wallet_transactions
                            (wallet_id,transaction_type,amount,balance_before,balance_after,reference,sale_id,performed_by,notes)
                         VALUES (?,'refund',?,?,?,?,?,?,?)"
                    )->execute([
                        (int)$wallet['id'], (float)$sale['total_amount'], $before, $after,
                        $sale['sale_number'], $saleId, $actorId, 'Voided canteen sale',
                    ]);
                }
            }

            $db->prepare('UPDATE sales SET status=\'void\',void_reason=?,voided_by=? WHERE id=?')
                ->execute([$reason, $actorId, $saleId]);
            audit('canteen', 'void_sale', 'sales', $saleId, 'Voided sale ' . $sale['sale_number'] . ': ' . $reason);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function todaySummary(int $schoolId): array
    {
        $row = static::one(
            "SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount),0) AS total
               FROM sales WHERE school_id=? AND sale_date=CURDATE() AND status='completed'",
            [$schoolId]
        );
        return ['sale_count' => (int)($row['sale_count'] ?? 0), 'total' => (float)($row['total'] ?? 0)];
    }

    public static function sellers(int $schoolId): array
    {
        return static::query(
            'SELECT id, staff_number, first_name, last_name
               FROM staff WHERE school_id = ? AND employment_status = ?
              ORDER BY last_name, first_name',
            [$schoolId, 'active']
        );
    }

    public static function reconciliations(int $schoolId, int $limit = 60): array
    {
        return static::query(
            'SELECT r.*, s.first_name, s.last_name, s.staff_number
               FROM shop_till_reconciliations r
               LEFT JOIN staff s ON s.id = r.seller_staff_id
              WHERE r.school_id = ?
              ORDER BY r.recon_date DESC, r.id DESC
              LIMIT ' . max(1, min(200, $limit)),
            [$schoolId]
        );
    }

    /**
     * Cash and digital totals for one day, optionally for one seller.
     */
    public static function tillExpected(int $schoolId, string $date, ?int $sellerId): array
    {
        $sql = "SELECT
                    COUNT(*) AS sale_count,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) AS expected_cash,
                    COALESCE(SUM(CASE WHEN payment_method IN ('wallet','mobile_money','card','account') THEN total_amount ELSE 0 END), 0) AS digital_total
                  FROM sales
                 WHERE school_id = ? AND sale_date = ? AND status = 'completed'";
        $params = [$schoolId, $date];
        if ($sellerId !== null) {
            $sql .= ' AND seller_staff_id = ?';
            $params[] = $sellerId;
        }
        $row = static::one($sql, $params) ?? [];
        return [
            'sale_count' => (int)($row['sale_count'] ?? 0),
            'expected_cash' => (float)($row['expected_cash'] ?? 0),
            'digital_total' => (float)($row['digital_total'] ?? 0),
        ];
    }

    /**
     * Record or update the cash count for a day and seller. The unique key
     * treats null sellers as different rows, so the lookup is null-safe.
     */
    public static function saveReconciliation(
        int $schoolId,
        string $date,
        ?int $sellerId,
        float $countedCash,
        ?string $remarks,
        int $actorId
    ): array {
        if ($sellerId !== null && !static::one('SELECT id FROM staff WHERE id = ? AND school_id = ?', [$sellerId, $schoolId])) {
            throw new DomainException('Choose a seller from this school.');
        }
        $totals = static::tillExpected($schoolId, $date, $sellerId);
        $difference = round($countedCash - $totals['expected_cash'], 2);
        $status = abs($difference) < 0.01 ? 'matched' : 'variance';
        $existing = static::one(
            'SELECT id FROM shop_till_reconciliations WHERE school_id = ? AND recon_date = ? AND seller_staff_id <=> ?',
            [$schoolId, $date, $sellerId]
        );
        if ($existing) {
            static::execute(
                'UPDATE shop_till_reconciliations
                    SET expected_cash = ?, counted_cash = ?, difference = ?, digital_total = ?,
                        sales_count = ?, reconciled_by = ?, reconciled_at = NOW(), status = ?, remarks = ?
                  WHERE id = ?',
                [
                    $totals['expected_cash'], $countedCash, $difference, $totals['digital_total'],
                    $totals['sale_count'], $actorId, $status, $remarks, (int)$existing['id'],
                ]
            );
            $id = (int)$existing['id'];
        } else {
            static::execute(
                'INSERT INTO shop_till_reconciliations
                    (school_id, recon_date, seller_staff_id, expected_cash, counted_cash, difference,
                     digital_total, sales_count, reconciled_by, status, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $schoolId, $date, $sellerId, $totals['expected_cash'], $countedCash, $difference,
                    $totals['digital_total'], $totals['sale_count'], $actorId, $status, $remarks,
                ]
            );
            $id = (int)static::db()->lastInsertId();
        }
        return ['id' => $id, 'status' => $status, 'difference' => $difference] + $totals;
    }

    public static function approveReconciliation(int $id, int $schoolId, int $actorId): void
    {
        $row = static::one('SELECT * FROM shop_till_reconciliations WHERE id = ? AND school_id = ?', [$id, $schoolId]);
        if (!$row || !in_array($row['status'], ['matched', 'variance'], true)) {
            throw new DomainException('Only a counted till can be approved.');
        }
        static::execute(
            'UPDATE shop_till_reconciliations SET status = ?, reconciled_by = ?, reconciled_at = NOW() WHERE id = ?',
            ['approved', $actorId, $id]
        );
    }
}
