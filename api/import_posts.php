<?php
// import_posts.php — SC Dashboard: รับ JSON จาก browser แล้ว import ลง MySQL
header('Content-Type: application/json; charset=utf-8');

// ── CORS ─────────────────────────────────────────────────────────────────────
$allowed_origins = [
    'https://iksonlinemkt.github.io',
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:5500',
    'http://127.0.0.1:5500',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── AUTH ─────────────────────────────────────────────────────────────────────
define('IMPORT_PASSWORD', 'iks1234');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON ไม่ถูกต้อง']);
    exit;
}

if (($input['password'] ?? '') !== IMPORT_PASSWORD) {
    http_response_code(401);
    echo json_encode(['error' => 'รหัสผ่านไม่ถูกต้อง']);
    exit;
}

// ── VALIDATE ─────────────────────────────────────────────────────────────────
$branch = trim($input['branch'] ?? '');
$rows   = $input['rows'] ?? [];

if ($branch === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่ระบุ branch']);
    exit;
}
if (!is_array($rows) || count($rows) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่มีข้อมูลใน rows']);
    exit;
}

// ── DB ───────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=sc_dashboard;charset=utf8mb4',
        'sc_user',
        'Sc@ikssa2026!',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // ลบข้อมูลเก่า branch นี้ก่อน
    $del = $pdo->prepare("DELETE FROM sc_posts WHERE branch = ?");
    $del->execute([$branch]);
    $deleted = $del->rowCount();

    // เตรียม INSERT statement
    $ins = $pdo->prepare("
        INSERT INTO sc_posts
          (branch, sc_page, date, month, views, reach, engagement,
           reactions, comments, shares, clicks, view_organic, view_paid,
           post_type, title, link)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $inserted = 0;
    $skipped  = 0;

    foreach ($rows as $row) {
        $date = trim($row['date'] ?? '');
        // ตรวจ format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $skipped++;
            continue;
        }
        $month = substr($date, 0, 7); // YYYY-MM

        $ins->execute([
            $branch,
            substr(trim($row['sc_page']   ?? ''), 0, 100),
            $date,
            $month,
            (int)($row['views']        ?? 0),
            (int)($row['reach']        ?? 0),
            (int)($row['engagement']   ?? 0),
            (int)($row['reactions']    ?? 0),
            (int)($row['comments']     ?? 0),
            (int)($row['shares']       ?? 0),
            (int)($row['clicks']       ?? 0),
            (int)($row['view_organic'] ?? 0),
            (int)($row['view_paid']    ?? 0),
            substr(trim($row['post_type'] ?? ''), 0, 100),
            substr(trim($row['title']     ?? ''), 0, 500),
            trim($row['link']             ?? ''),
        ]);
        $inserted++;
    }

    echo json_encode([
        'success'  => true,
        'branch'   => $branch,
        'deleted'  => $deleted,
        'inserted' => $inserted,
        'skipped'  => $skipped,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
