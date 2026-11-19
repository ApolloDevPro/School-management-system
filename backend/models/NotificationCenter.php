<?php
declare(strict_types=1);

/**
 * Message templates, bulk notification campaigns and PTA meetings.
 *
 * A campaign snapshots its recipients when it is created so the list stays
 * stable, even if students or guardians change later.
 */
final class NotificationCenter extends Model
{
    private const TABLES = ['notification_templates', 'notifications', 'pta_meetings', 'pta_attendance'];

    private static function insertRow(string $table, array $data): int
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException('Unsupported notification table.');
        }
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($data)) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    private static function transaction(callable $callback): mixed
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function templates(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM notification_templates WHERE school_id = ? ORDER BY channel, name',
            [$schoolId]
        );
    }

    public static function saveTemplate(int $schoolId, array $data, ?int $actorId): int
    {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            $exists = static::one('SELECT id FROM notification_templates WHERE id = ? AND school_id = ?', [$id, $schoolId]);
            if (!$exists) {
                throw new DomainException('Template not found.');
            }
            static::execute(
                'UPDATE notification_templates SET template_code = ?, name = ?, channel = ?, subject = ?,
                        body = ?, placeholders = ?, related_module = ?, is_active = ?
                  WHERE id = ? AND school_id = ?',
                [
                    $data['template_code'], $data['name'], $data['channel'], $data['subject'],
                    $data['body'], $data['placeholders'], $data['related_module'], $data['is_active'],
                    $id, $schoolId,
                ]
            );
            return $id;
        }
        $data['school_id'] = $schoolId;
        $data['created_by'] = $actorId;
        return static::insertRow('notification_templates', $data);
    }

    public static function campaigns(int $schoolId, int $limit = 60): array
    {
        return static::query(
            'SELECT n.*, t.name AS template_name
               FROM notifications n
               LEFT JOIN notification_templates t ON t.id = n.template_id
              WHERE n.school_id = ?
              ORDER BY n.id DESC
              LIMIT ' . max(1, min(200, $limit)),
            [$schoolId]
        );
    }

    public static function campaign(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM notifications WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function recipients(int $notificationId): array
    {
        return static::query(
            'SELECT * FROM notification_recipients WHERE notification_id = ? ORDER BY name',
            [$notificationId]
        );
    }

    /**
     * Build the recipient list for an audience. Portal and email need a portal
     * or email account; SMS needs a phone number, so the two groups differ.
     */
    public static function audience(int $schoolId, string $audience, ?int $classId): array
    {
        $rows = [];
        if (in_array($audience, ['guardians', 'all'], true)) {
            $sql = 'SELECT g.id, g.full_name, g.first_name, g.last_name, g.phone, g.email, g.user_id,
                           NULL AS student_id, NULL AS staff_id
                      FROM guardians g
                     WHERE g.school_id = ?';
            $params = [$schoolId];
            if ($audience === 'guardians' && $classId !== null) {
                $sql = 'SELECT g.id, g.full_name, g.first_name, g.last_name, g.phone, g.email, g.user_id,
                               NULL AS student_id, NULL AS staff_id
                          FROM guardians g
                          JOIN student_guardians sg ON sg.guardian_id = g.id
                          JOIN students st ON st.id = sg.student_id
                         WHERE g.school_id = ? AND st.current_class_id = ?';
                $params[] = $classId;
            }
            $sql .= ' ORDER BY g.last_name, g.first_name';
            foreach (static::query($sql, $params) as $guardian) {
                $rows[] = [
                    'recipient_type' => 'guardian',
                    'guardian_id' => (int)$guardian['id'],
                    'student_id' => null,
                    'staff_id' => null,
                    'user_id' => $guardian['user_id'] ? (int)$guardian['user_id'] : null,
                    'name' => $guardian['full_name'] ?: trim($guardian['first_name'] . ' ' . $guardian['last_name']),
                    'phone' => $guardian['phone'],
                    'email' => $guardian['email'],
                ];
            }
        }
        if ($audience === 'students') {
            $sql = 'SELECT s.id, s.user_id, s.first_name, s.middle_name, s.last_name, g.phone, g.email
                      FROM students s
                      LEFT JOIN student_guardians sg ON sg.student_id = s.id AND sg.is_primary = 1
                      LEFT JOIN guardians g ON g.id = sg.guardian_id
                     WHERE s.school_id = ? AND s.status = ?';
            $params = [$schoolId, 'active'];
            if ($classId !== null) {
                $sql .= ' AND s.current_class_id = ?';
                $params[] = $classId;
            }
            $sql .= ' ORDER BY s.last_name, s.first_name';
            foreach (static::query($sql, $params) as $student) {
                $rows[] = [
                    'recipient_type' => 'student',
                    'guardian_id' => null,
                    'student_id' => (int)$student['id'],
                    'staff_id' => null,
                    'user_id' => $student['user_id'] ? (int)$student['user_id'] : null,
                    'name' => trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name']),
                    'phone' => $student['phone'],
                    'email' => $student['email'],
                ];
            }
        }
        if (in_array($audience, ['staff', 'all'], true)) {
            $staffRows = static::query(
                'SELECT id, first_name, last_name, phone, email, user_id
                   FROM staff WHERE school_id = ? AND employment_status = ? ORDER BY last_name',
                [$schoolId, 'active']
            );
            foreach ($staffRows as $staff) {
                $rows[] = [
                    'recipient_type' => 'staff',
                    'guardian_id' => null,
                    'student_id' => null,
                    'staff_id' => (int)$staff['id'],
                    'user_id' => $staff['user_id'] ? (int)$staff['user_id'] : null,
                    'name' => trim($staff['first_name'] . ' ' . $staff['last_name']),
                    'phone' => $staff['phone'],
                    'email' => $staff['email'],
                ];
            }
        }
        return $rows;
    }

    /**
     * Create a campaign and one recipient row per destination. SMS and email
     * stay queued until a gateway confirms delivery; a portal campaign is
     * treated as delivered straight away.
     */
    public static function sendCampaign(int $schoolId, array $data, int $actorId): array
    {
        $audience = self::audience($schoolId, $data['audience'], $data['class_id']);
        if (!$audience) {
            throw new DomainException('No recipients match that audience.');
        }
        $channel = $data['channel'];
        $recipients = [];
        foreach ($audience as $person) {
            $destination = match ($channel) {
                'sms' => $person['phone'],
                'email' => $person['email'],
                default => $person['user_id'] ? 'portal:' . $person['user_id'] : null,
            };
            if ($destination === null || $destination === '') {
                continue;
            }
            $recipients[] = $person + ['destination' => $destination];
        }
        if (!$recipients) {
            throw new DomainException('The chosen audience has no usable phone numbers, emails or portal accounts for this channel.');
        }

        return self::transaction(static function (PDO $db) use ($schoolId, $data, $actorId, $recipients): array {
            $status = $data['channel'] === 'portal' ? 'sent' : ($data['scheduled_for'] ? 'queued' : 'queued');
            $sentAt = $data['channel'] === 'portal' ? date('Y-m-d H:i:s') : null;
            $db->prepare(
                'INSERT INTO notifications
                    (school_id, template_id, channel, category, audience, subject, message,
                     recipient_count, delivered_count, failed_count, is_emergency, priority,
                     status, scheduled_for, sent_at, sent_by, provider)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $schoolId, $data['template_id'] ?: null, $data['channel'], $data['category'], $data['audience'],
                $data['subject'], $data['message'], count($recipients),
                $data['channel'] === 'portal' ? count($recipients) : 0,
                $data['is_emergency'], $data['priority'], $status, $data['scheduled_for'] ?: null,
                $sentAt, $actorId, $data['channel'] === 'portal' ? 'portal' : 'pending_gateway',
            ]);
            $notificationId = (int)$db->lastInsertId();
            $insert = $db->prepare(
                'INSERT INTO notification_recipients
                    (notification_id, recipient_type, guardian_id, student_id, staff_id, user_id,
                     name, destination, delivery_status, sent_at, delivered_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($recipients as $person) {
                $delivered = $data['channel'] === 'portal';
                $insert->execute([
                    $notificationId, $person['recipient_type'], $person['guardian_id'], $person['student_id'],
                    $person['staff_id'], $person['user_id'], $person['name'], $person['destination'],
                    $delivered ? 'delivered' : 'pending',
                    $delivered ? date('Y-m-d H:i:s') : null,
                    $delivered ? date('Y-m-d H:i:s') : null,
                ]);
            }
            return ['id' => $notificationId, 'recipients' => count($recipients)];
        });
    }

    /**
     * Record that an external gateway accepted the whole batch.
     */
    public static function markCampaignSent(int $id, int $schoolId, int $actorId): void
    {
        $campaign = static::campaign($id, $schoolId);
        if (!$campaign || in_array($campaign['status'], ['sent', 'cancelled'], true)) {
            throw new DomainException('Only an open campaign can be marked sent.');
        }
        self::transaction(static function (PDO $db) use ($id, $schoolId, $actorId): void {
            $now = date('Y-m-d H:i:s');
            $db->prepare(
                "UPDATE notification_recipients SET delivery_status = 'sent', sent_at = ?
                  WHERE notification_id = ? AND delivery_status = 'pending'"
            )->execute([$now, $id]);
            $db->prepare(
                "UPDATE notifications SET status = 'sent', sent_at = ?, sent_by = ?, delivered_count = recipient_count
                  WHERE id = ? AND school_id = ?"
            )->execute([$now, $actorId, $id, $schoolId]);
        });
    }

    public static function cancelCampaign(int $id, int $schoolId): void
    {
        $campaign = static::campaign($id, $schoolId);
        if (!$campaign || in_array($campaign['status'], ['sent', 'cancelled'], true)) {
            throw new DomainException('Only an unsent campaign can be cancelled.');
        }
        static::execute("UPDATE notifications SET status = 'cancelled' WHERE id = ? AND school_id = ?", [$id, $schoolId]);
    }

    public static function meetings(int $schoolId): array
    {
        return static::query(
            'SELECT p.*, y.name AS year_name, t.name AS term_name,
                    (SELECT COUNT(*) FROM pta_attendance a WHERE a.pta_meeting_id = p.id) AS sign_in_count
               FROM pta_meetings p
               JOIN academic_years y ON y.id = p.academic_year_id
               LEFT JOIN terms t ON t.id = p.term_id
              WHERE p.school_id = ?
              ORDER BY p.meeting_date DESC, p.id DESC',
            [$schoolId]
        );
    }

    public static function meeting(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM pta_meetings WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function saveMeeting(int $schoolId, array $data, ?int $actorId): int
    {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            if (!static::meeting($id, $schoolId)) {
                throw new DomainException('PTA meeting not found.');
            }
            unset($data['id']);
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            static::execute('UPDATE pta_meetings SET ' . $assignments . ' WHERE id = ? AND school_id = ?', [...array_values($data), $id, $schoolId]);
            return $id;
        }
        $data['school_id'] = $schoolId;
        $data['created_by'] = $actorId;
        return static::insertRow('pta_meetings', $data);
    }

    public static function meetingAttendance(int $meetingId, int $schoolId): array
    {
        return static::query(
            'SELECT a.*, g.full_name, g.first_name, g.last_name, g.phone,
                    s.admission_number, s.first_name AS student_first, s.last_name AS student_last
               FROM pta_attendance a
               JOIN pta_meetings p ON p.id = a.pta_meeting_id
               JOIN guardians g ON g.id = a.guardian_id
               LEFT JOIN students s ON s.id = a.student_id
              WHERE a.pta_meeting_id = ? AND p.school_id = ?
              ORDER BY g.last_name, g.first_name',
            [$meetingId, $schoolId]
        );
    }

    public static function signIn(int $meetingId, int $schoolId, array $data): int
    {
        $meeting = static::meeting($meetingId, $schoolId);
        if (!$meeting) {
            throw new DomainException('PTA meeting not found.');
        }
        $guardian = static::one('SELECT id FROM guardians WHERE id = ? AND school_id = ?', [$data['guardian_id'], $schoolId]);
        if (!$guardian) {
            throw new DomainException('Choose a guardian from this school.');
        }
        if (!empty($data['student_id'])) {
            $student = static::one('SELECT id FROM students WHERE id = ? AND school_id = ?', [$data['student_id'], $schoolId]);
            if (!$student) {
                throw new DomainException('Choose a student from this school.');
            }
        }
        $existing = static::one(
            'SELECT id FROM pta_attendance WHERE pta_meeting_id = ? AND guardian_id = ? AND student_id <=> ?',
            [$meetingId, $data['guardian_id'], $data['student_id'] ?: null]
        );
        if ($existing) {
            throw new DomainException('That guardian is already signed in for this meeting.');
        }
        $id = static::insertRow('pta_attendance', [
            'pta_meeting_id' => $meetingId,
            'guardian_id' => $data['guardian_id'],
            'student_id' => $data['student_id'] ?: null,
            'is_proxy' => $data['is_proxy'] ? 1 : 0,
            'proxy_name' => $data['proxy_name'] ?: null,
            'arrived_at' => date('Y-m-d H:i:s'),
            'signed' => 1,
        ]);
        static::execute(
            'UPDATE pta_meetings SET attendance_count = (SELECT COUNT(*) FROM pta_attendance WHERE pta_meeting_id = ?)
              WHERE id = ? AND school_id = ?',
            [$meetingId, $meetingId, $schoolId]
        );
        return $id;
    }
}
