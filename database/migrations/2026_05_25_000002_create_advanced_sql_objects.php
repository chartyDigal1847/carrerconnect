<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS v_faculty_activity_summary');
        DB::unprepared('DROP VIEW IF EXISTS v_department_announcement_analytics');
        DB::unprepared('DROP VIEW IF EXISTS v_communication_engagement');
        DB::unprepared('DROP VIEW IF EXISTS v_announcement_delivery_stats');
        DB::unprepared('DROP VIEW IF EXISTS v_resource_usage_analytics');

        DB::unprepared(<<<'SQL'
CREATE VIEW v_faculty_activity_summary AS
SELECT
    fu.id AS faculty_user_id,
    fu.name,
    fu.role,
    fu.department,
    COUNT(DISTINCT al.id) AS total_actions,
    COUNT(DISTINCT CASE WHEN al.action = 'created' THEN al.id END) AS creations,
    COUNT(DISTINCT bp.id) AS board_posts,
    COUNT(DISTINCT bc.id) AS board_comments,
    MAX(al.created_at) AS last_activity_at
FROM faculty_users fu
LEFT JOIN activity_logs al ON al.user_id = fu.id
LEFT JOIN board_posts bp ON bp.author_id = fu.id AND bp.deleted_at IS NULL
LEFT JOIN board_comments bc ON bc.author_id = fu.id AND bc.deleted_at IS NULL
WHERE fu.deleted_at IS NULL AND fu.role != 'student'
GROUP BY fu.id, fu.name, fu.role, fu.department;
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW v_department_announcement_analytics AS
SELECT
    d.id AS department_id,
    d.code AS department_code,
    d.name AS department_name,
    COUNT(a.id) AS total_announcements,
    SUM(CASE WHEN a.is_active = 1 THEN 1 ELSE 0 END) AS active_announcements,
    SUM(a.views_count) AS total_views,
    AVG(a.views_count) AS avg_views_per_announcement
FROM departments d
LEFT JOIN announcements a ON a.department_id = d.id AND a.deleted_at IS NULL
WHERE d.deleted_at IS NULL
GROUP BY d.id, d.code, d.name;
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW v_communication_engagement AS
SELECT
    cb.id AS board_id,
    cb.name AS board_name,
    cb.visibility,
    COUNT(DISTINCT bp.id) AS total_posts,
    COUNT(DISTINCT bc.id) AS total_comments,
    COALESCE(SUM(bp.views_count), 0) AS total_post_views,
    MAX(GREATEST(COALESCE(bp.created_at, '1970-01-01'), COALESCE(bc.created_at, '1970-01-01'))) AS last_activity_at
FROM communication_boards cb
LEFT JOIN board_posts bp ON bp.board_id = cb.id AND bp.deleted_at IS NULL
LEFT JOIN board_comments bc ON bc.post_id = bp.id AND bc.deleted_at IS NULL
WHERE cb.deleted_at IS NULL
GROUP BY cb.id, cb.name, cb.visibility;
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW v_announcement_delivery_stats AS
SELECT
    a.id AS announcement_id,
    a.title,
    a.priority,
    a.visibility,
    a.published_at,
    a.views_count,
    fu.name AS author_name,
    COUNT(DISTINCT n.id) AS notifications_sent,
    COUNT(DISTINCT CASE WHEN n.is_read = 1 THEN n.id END) AS notifications_read
FROM announcements a
INNER JOIN faculty_users fu ON fu.id = a.author_id
LEFT JOIN notifications n ON n.type = 'announcement'
    AND n.message LIKE CONCAT('%', a.title, '%')
WHERE a.deleted_at IS NULL
GROUP BY a.id, a.title, a.priority, a.visibility, a.published_at, a.views_count, fu.name;
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW v_resource_usage_analytics AS
SELECT
    rc.id AS category_id,
    rc.name AS category_name,
    COUNT(cr.id) AS total_resources,
    SUM(CASE WHEN cr.is_approved = 1 THEN 1 ELSE 0 END) AS approved_resources,
    COALESCE(SUM(cr.downloads_count), 0) AS total_downloads,
    COALESCE(SUM(cr.views_count), 0) AS total_views
FROM resource_categories rc
LEFT JOIN career_resources cr ON cr.category_id = rc.id AND cr.deleted_at IS NULL
GROUP BY rc.id, rc.name;
SQL);

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_faculty_activity_report');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE sp_faculty_activity_report(IN p_department VARCHAR(20), IN p_days INT)
BEGIN
    SELECT * FROM v_faculty_activity_summary
    WHERE (p_department IS NULL OR department = p_department)
      AND (last_activity_at IS NULL OR last_activity_at >= DATE_SUB(NOW(), INTERVAL p_days DAY))
    ORDER BY total_actions DESC;
END
SQL);

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_announcement_delivery_stats');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE sp_announcement_delivery_stats(IN p_announcement_id BIGINT)
BEGIN
    IF p_announcement_id IS NULL THEN
        SELECT * FROM v_announcement_delivery_stats ORDER BY published_at DESC LIMIT 50;
    ELSE
        SELECT * FROM v_announcement_delivery_stats WHERE announcement_id = p_announcement_id;
    END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_board_post_increment_board_count');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_board_post_increment_board_count
AFTER INSERT ON board_posts
FOR EACH ROW
BEGIN
    UPDATE communication_boards
    SET posts_count = posts_count + 1
    WHERE id = NEW.board_id;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_board_comment_increment_post_count');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_board_comment_increment_post_count
AFTER INSERT ON board_comments
FOR EACH ROW
BEGIN
    UPDATE board_posts
    SET comments_count = comments_count + 1
    WHERE id = NEW.post_id;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_board_comment_increment_post_count');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_board_post_increment_board_count');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_announcement_delivery_stats');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_faculty_activity_report');
        DB::unprepared('DROP VIEW IF EXISTS v_resource_usage_analytics');
        DB::unprepared('DROP VIEW IF EXISTS v_announcement_delivery_stats');
        DB::unprepared('DROP VIEW IF EXISTS v_communication_engagement');
        DB::unprepared('DROP VIEW IF EXISTS v_department_announcement_analytics');
        DB::unprepared('DROP VIEW IF EXISTS v_faculty_activity_summary');
    }
};
