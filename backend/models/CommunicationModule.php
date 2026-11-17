<?php
declare(strict_types=1);

final class CommunicationModule extends Model
{
    public static function announcements(int $schoolId, bool $publicOnly = false): array
    {
        $sql = 'SELECT id,title,body,category,audience,status,publish_from,publish_until,created_at FROM announcements WHERE school_id=?';
        $params = [$schoolId];
        if ($publicOnly) {
            $sql .= " AND status='published' AND show_on_portal=1 AND (publish_from IS NULL OR publish_from<=NOW()) AND (publish_until IS NULL OR publish_until>=NOW())";
        }
        return static::query($sql . ' ORDER BY created_at DESC LIMIT 300',$params);
    }

    public static function createAnnouncement(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO announcements (school_id,title,body,category,audience,status,created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$data['school_id'],$data['title'],$data['body'],$data['category'],$data['audience'],'draft',$data['user_id']]);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        $published = $status === 'published' ? ', published_at=NOW()' : '';
        return static::execute("UPDATE announcements SET status=?{$published} WHERE id=? AND school_id=?",[$status,$id,$schoolId]) > 0;
    }
}
