<?php
// sc_posts.php — SC Dashboard API  (v2, fixed CORS)
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
    // ไม่จำกัด origin (เพื่อรองรับ local dev และ preview)
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── PARAMS ───────────────────────────────────────────────────────────────────
$branch = trim($_GET['branch'] ?? '');
$order  = strtoupper(trim($_GET['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$limit  = (int)($_GET['limit'] ?? 0);

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

    $sql    = 'SELECT sc_page, date, month, views, reach, engagement,
                      reactions, comments, shares, clicks,
                      view_organic, view_paid, post_type, title, link
               FROM sc_posts';
    $params = [];

    if ($branch !== '') {
        $sql    .= ' WHERE branch = ?';
        $params[] = $branch;
    }
    $sql .= " ORDER BY date $order";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Cast ตัวเลขให้เป็น int จริง ๆ (PDO คืนค่าเป็น string ทั้งหมด)
    $int_fields = ['views','reach','engagement','reactions','comments',
                   'shares','clicks','view_organic','view_paid'];
    foreach ($rows as &$row) {
        foreach ($int_fields as $f) {
            $row[$f] = (int)$row[$f];
        }
    }
    unset($row);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
