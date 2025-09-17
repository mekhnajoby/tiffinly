<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php'; // defines $conn

$sql = "SELECT meal_id, meal_name FROM meals WHERE is_active = 1 AND meal_name LIKE ? ORDER BY meal_name ASC LIMIT 20";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([]);
    exit;
}
$like = '%' . $q . '%';
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = [
        'meal_id' => (int)$row['meal_id'],
        'meal_name' => $row['meal_name'],
    ];
}
$stmt->close();

echo json_encode($out);
