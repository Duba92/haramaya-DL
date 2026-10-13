<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$term = sanitize($_GET['q'] ?? '');
if (mb_strlen($term) < 2 || mb_strlen($term) > 100) {
    echo json_encode(['items' => []]);
    exit;
}
$statement = $pdo->prepare(
    'SELECT m.material_id, m.title, c.course_code
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     WHERE m.approved = 1 AND (m.title LIKE :title_term OR c.course_code LIKE :code_term)
     ORDER BY m.download_count DESC, m.upload_date DESC LIMIT 8'
);
$pattern = '%' . $term . '%';
$statement->execute(['title_term' => $pattern, 'code_term' => $pattern]);
echo json_encode(['items' => $statement->fetchAll()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);