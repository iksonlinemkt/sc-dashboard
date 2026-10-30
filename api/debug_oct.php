<?php
// debug_oct.php — ตรวจสอบข้อมูล October ใน database
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=sc_dashboard;charset=utf8mb4',
        'sc_user',
        'Sc@ikssa2026!',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $branch = $_GET['branch'] ?? 'SR';
    $sc_page = $_GET['sc_page'] ?? '%';

    // นับ post ต่อเดือน ต่อคน
    $stmt = $pdo->prepare("
        SELECT sc_page, month, COUNT(*) as cnt
        FROM sc_posts
        WHERE branch = ?
        AND sc_page LIKE ?
        GROUP BY sc_page, month
        ORDER BY sc_page, month DESC
    ");
    $stmt->execute([$branch, $sc_page]);
    $by_month = $stmt->fetchAll();

    // ดู date range จริงใน Oct
    $stmt2 = $pdo->prepare("
        SELECT sc_page, date, COUNT(*) as cnt
        FROM sc_posts
        WHERE branch = ? AND month = '2026-10'
        AND sc_page LIKE ?
        GROUP BY sc_page, date
        ORDER BY sc_page, date
    ");
    $stmt2->execute([$branch, $sc_page]);
    $oct_dates = $stmt2->fetchAll();

    // ดู date ผิด (ไม่ใช่ 2026-10-xx แต่อาจเป็น 2026-09 หรือ 2026-01)
    $stmt3 = $pdo->prepare("
        SELECT sc_page, month, MIN(date) as min_date, MAX(date) as max_date, COUNT(*) as cnt
        FROM sc_posts
        WHERE branch = ?
        AND sc_page LIKE ?
        AND month BETWEEN '2026-01' AND '2026-09'
        GROUP BY sc_page, month
        ORDER BY sc_page, month
    ");
    $stmt3->execute([$branch, $sc_page]);
    $wrong_months = $stmt3->fetchAll();

    echo json_encode([
        'branch' => $branch,
        'sc_page_filter' => $sc_page,
        'posts_by_month' => $by_month,
        'oct_2026_by_date' => $oct_dates,
        'possible_wrong_dates_jan_sep' => $wrong_months,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
