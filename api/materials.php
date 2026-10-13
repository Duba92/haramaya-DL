<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$rawIds = sanitize($_GET['ids'] ?? '');
$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)), static fn (int $id): bool => $id > 0))), 0, 20);
if ($ids === []) {
    echo json_encode(['items' => []]);
    exit;
}
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$statement = $pdo->prepare(
    'SELECT m.material_id, m.title, c.course_code
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     WHERE m.approved = 1 AND m.material_id IN (' . $placeholders . ')'
);
$statement->execute($ids);
echo json_encode(['items' => $statement->fetchAll()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);