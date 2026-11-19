<?php
declare(strict_types=1);

/**
 * Books, borrowings and fines.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class LibraryModule extends Model
{
    public static function books(int $schoolId): array
    {
        return static::query(
            'SELECT b.id,b.title,b.author,b.isbn,b.total_copies,b.available_copies,b.status,b.category_id,
                    c.name AS category_name
               FROM books b LEFT JOIN book_categories c ON c.id=b.category_id
              WHERE b.school_id=? ORDER BY b.title LIMIT 500',
            [$schoolId]
        );
    }

    public static function createBook(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO books (school_id,title,author,isbn,category_id,total_copies,available_copies,created_by)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $data['school_id'], $data['title'], $data['author'], $data['isbn'],
            $data['category_id'] ?? null, $data['copies'], $data['copies'], $data['user_id'],
        ]);
        $bookId = (int)static::db()->lastInsertId();

        $copy = static::db()->prepare('INSERT INTO book_copies (book_id,accession_number,status) VALUES (?,?,?)');
        for ($i = 1; $i <= $data['copies']; $i++) {
            $copy->execute([$bookId, 'ACC-' . $bookId . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT), 'available']);
        }

        return $bookId;
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE books SET status=? WHERE id=? AND school_id=?', [$status,$id,$schoolId]) > 0;
    }

    public static function categories(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,code,description,is_active FROM book_categories WHERE school_id=? ORDER BY name LIMIT 200',
            [$schoolId]
        );
    }

    public static function createCategory(int $schoolId, string $name, ?string $code, ?string $description): int
    {
        static::db()->prepare(
            'INSERT INTO book_categories (school_id,name,code,description) VALUES (?,?,?,?)'
        )->execute([$schoolId, $name, $code, $description]);
        return (int)static::db()->lastInsertId();
    }

    public static function toggleCategory(int $id, int $schoolId, int $active): bool
    {
        return static::execute('UPDATE book_categories SET is_active=? WHERE id=? AND school_id=?', [$active,$id,$schoolId]) > 0;
    }

    public static function categoryNameTaken(int $schoolId, string $name): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM book_categories WHERE school_id=? AND name=?', [$schoolId,$name]);
    }

    public static function validCategory(int $id, int $schoolId): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM book_categories WHERE id=? AND school_id=?', [$id,$schoolId]);
    }

    public static function students(int $schoolId): array
    {
        return static::query(
            "SELECT id,admission_number,first_name,last_name FROM students WHERE school_id=? AND status='active' ORDER BY last_name,first_name LIMIT 500",
            [$schoolId]
        );
    }

    public static function staff(int $schoolId): array
    {
        return static::query(
            "SELECT id,staff_number,first_name,last_name FROM staff
              WHERE school_id=? AND employment_status IN ('active','probation','contract')
              ORDER BY last_name,first_name LIMIT 300",
            [$schoolId]
        );
    }

    public static function activeBorrowings(int $schoolId): array
    {
        return static::query(
            "SELECT b.id,b.book_id,b.copy_id,b.borrower_type,b.issue_date,b.due_date,b.status,
                    bk.title,bc.accession_number,
                    DATEDIFF(b.due_date,CURDATE()) AS days_until_due,
                    COALESCE(CONCAT(s.last_name,' ',s.first_name),CONCAT(st.last_name,' ',st.first_name),'Unknown') AS borrower_name,
                    COALESCE(s.admission_number,st.staff_number,'') AS borrower_ref
               FROM borrowings b
               JOIN books bk ON bk.id=b.book_id
               JOIN book_copies bc ON bc.id=b.copy_id
               LEFT JOIN students s ON s.id=b.student_id
               LEFT JOIN staff st ON st.id=b.staff_id
              WHERE b.school_id=? AND b.status IN ('borrowed','overdue')
              ORDER BY b.due_date ASC,b.id ASC LIMIT 300",
            [$schoolId]
        );
    }

    public static function overdueBorrowings(int $schoolId): array
    {
        return static::query(
            "SELECT b.id,b.book_id,b.copy_id,b.borrower_type,b.issue_date,b.due_date,b.status,
                    bk.title,bc.accession_number,
                    DATEDIFF(CURDATE(),b.due_date) AS days_overdue,
                    COALESCE(CONCAT(s.last_name,' ',s.first_name),CONCAT(st.last_name,' ',st.first_name),'Unknown') AS borrower_name,
                    COALESCE(s.admission_number,st.staff_number,'') AS borrower_ref
               FROM borrowings b
               JOIN books bk ON bk.id=b.book_id
               JOIN book_copies bc ON bc.id=b.copy_id
               LEFT JOIN students s ON s.id=b.student_id
               LEFT JOIN staff st ON st.id=b.staff_id
              WHERE b.school_id=? AND b.status IN ('borrowed','overdue') AND b.due_date<CURDATE()
              ORDER BY b.due_date ASC,b.id ASC LIMIT 300",
            [$schoolId]
        );
    }

    public static function borrowingOptions(int $schoolId): array
    {
        return static::query(
            "SELECT b.id,b.due_date,b.status,bk.title,
                    COALESCE(CONCAT(s.last_name,' ',s.first_name),CONCAT(st.last_name,' ',st.first_name),'Unknown') AS borrower_name,
                    COALESCE(s.admission_number,st.staff_number,'') AS borrower_ref
               FROM borrowings b
               JOIN books bk ON bk.id=b.book_id
               LEFT JOIN students s ON s.id=b.student_id
               LEFT JOIN staff st ON st.id=b.staff_id
              WHERE b.school_id=?
              ORDER BY b.created_at DESC,b.id DESC LIMIT 100",
            [$schoolId]
        );
    }

    public static function issueBorrowing(array $data): int
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                'SELECT id,total_copies,available_copies,status FROM books WHERE id=? AND school_id=? FOR UPDATE'
            );
            $statement->execute([$data['book_id'], $data['school_id']]);
            $book = $statement->fetch();
            if (!$book || $book['status'] !== 'active') {
                throw new InvalidArgumentException('The selected book is not available for issue.');
            }
            if ((int)$book['available_copies'] < $data['copies']) {
                throw new InvalidArgumentException(
                    'Only ' . (int)$book['available_copies'] . ' copy/copies of this book are available to issue.'
                );
            }

            $insert = $db->prepare(
                'INSERT INTO borrowings (school_id,copy_id,book_id,borrower_type,student_id,staff_id,issue_date,due_date,status,issued_by,notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $firstId = 0;
            for ($i = 0; $i < $data['copies']; $i++) {
                $copyId = self::takeCopy($db, (int)$book['id'], (int)$book['total_copies']);
                if ($copyId === null) {
                    throw new InvalidArgumentException('No physical copies of this book are on the shelf to issue.');
                }
                $insert->execute([
                    $data['school_id'], $copyId, (int)$book['id'], $data['borrower_type'],
                    $data['student_id'], $data['staff_id'], $data['issue_date'], $data['due_date'],
                    'borrowed', $data['user_id'], $data['notes'],
                ]);
                $firstId = $firstId ?: (int)$db->lastInsertId();
            }

            $db->prepare('UPDATE books SET available_copies = available_copies - ? WHERE id=?')
               ->execute([$data['copies'], (int)$book['id']]);

            $db->commit();
            return $firstId;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function returnBorrowing(int $id, int $schoolId, string $condition, int $userId): bool
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $statement = $db->prepare('SELECT * FROM borrowings WHERE id=? AND school_id=? FOR UPDATE');
            $statement->execute([$id, $schoolId]);
            $borrowing = $statement->fetch();
            if (!$borrowing || !in_array($borrowing['status'], ['borrowed', 'overdue'], true)) {
                $db->rollBack();
                return false;
            }

            $db->prepare(
                'UPDATE borrowings SET status=?,return_date=CURRENT_DATE,condition_on_return=?,returned_to=? WHERE id=?'
            )->execute(['returned', $condition, $userId, $id]);

            $copyStatus = in_array($condition, ['good', 'worn'], true) ? 'available' : 'damaged';
            $db->prepare('UPDATE book_copies SET status=?,book_condition=? WHERE id=?')
               ->execute([$copyStatus, $condition, (int)$borrowing['copy_id']]);

            $db->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id=?')
               ->execute([(int)$borrowing['book_id']]);

            $db->commit();
            return true;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function fines(int $schoolId): array
    {
        return static::query(
            "SELECT f.id,f.borrowing_id,f.student_id,f.staff_id,f.fine_type,f.days_charged,f.amount,f.amount_paid,
                    f.reason,f.status,f.created_at,bk.title AS book_title,
                    COALESCE(CONCAT(s.last_name,' ',s.first_name),CONCAT(st.last_name,' ',st.first_name),'Unknown') AS borrower_name,
                    COALESCE(s.admission_number,st.staff_number,'') AS borrower_ref
               FROM library_fines f
               LEFT JOIN borrowings b ON b.id=f.borrowing_id
               LEFT JOIN books bk ON bk.id=b.book_id
               LEFT JOIN students s ON s.id=f.student_id
               LEFT JOIN staff st ON st.id=f.staff_id
              WHERE f.school_id=? ORDER BY f.created_at DESC,f.id DESC LIMIT 300",
            [$schoolId]
        );
    }

    public static function createFine(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO library_fines (school_id,borrowing_id,student_id,staff_id,fine_type,days_charged,amount,reason,status,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $data['school_id'], $data['borrowing_id'], $data['student_id'], $data['staff_id'],
            $data['fine_type'], $data['days_charged'], $data['amount'], $data['reason'],
            'unpaid', $data['user_id'],
        ]);
        return (int)static::db()->lastInsertId();
    }

    public static function markFinePaid(int $id, int $schoolId): bool
    {
        return static::execute(
            "UPDATE library_fines SET status='paid',amount_paid=amount WHERE id=? AND school_id=? AND status IN ('unpaid','part_paid')",
            [$id,$schoolId]
        ) > 0;
    }

    public static function borrowingFor(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT id,student_id,staff_id,borrower_type FROM borrowings WHERE id=? AND school_id=?',
            [$id,$schoolId]
        );
    }

    public static function validBook(int $bookId, int $schoolId): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM books WHERE id=? AND school_id=?', [$bookId,$schoolId]);
    }

    public static function validStudent(int $studentId, int $schoolId): bool
    {
        return (bool)static::scalar(
            "SELECT COUNT(*) FROM students WHERE id=? AND school_id=? AND status='active'",
            [$studentId,$schoolId]
        );
    }

    public static function validStaff(int $staffId, int $schoolId): bool
    {
        return (bool)static::scalar(
            "SELECT COUNT(*) FROM staff WHERE id=? AND school_id=? AND employment_status IN ('active','probation','contract')",
            [$staffId,$schoolId]
        );
    }

    private static function takeCopy(PDO $db, int $bookId, int $totalCopies): ?int
    {
        $statement = $db->prepare("SELECT id FROM book_copies WHERE book_id=? AND status='available' ORDER BY id LIMIT 1 FOR UPDATE");
        $statement->execute([$bookId]);
        $copy = $statement->fetch();
        if ($copy) {
            $db->prepare("UPDATE book_copies SET status='borrowed' WHERE id=?")->execute([(int)$copy['id']]);
            return (int)$copy['id'];
        }

        $countStatement = $db->prepare('SELECT COUNT(*) FROM book_copies WHERE book_id=?');
        $countStatement->execute([$bookId]);
        $count = (int)$countStatement->fetchColumn();
        if ($count >= $totalCopies) {
            return null;
        }

        $accession = 'ACC-' . $bookId . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT)
            . '-' . strtoupper(bin2hex(random_bytes(2)));
        $insert = $db->prepare('INSERT INTO book_copies (book_id,accession_number,status) VALUES (?,?,?)');
        $insert->execute([$bookId, $accession, 'borrowed']);
        return (int)$db->lastInsertId();
    }
}
