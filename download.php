<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_csrf();

$materialId = filter_var($_POST['material_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($materialId === false || $materialId === null) {
    http_response_code(400);
    exit('Invalid material.');
}

$pdo->beginTransaction();
try {
    $statement = $pdo->prepare(
        'SELECT material_id, title, file_path, file_type, file_size
         FROM materials WHERE material_id = :id AND approved = 1 FOR UPDATE'
    );
    $statement->execute(['id' => $materialId]);
    $material = $statement->fetch();
    if (!$material) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Approved material not found.');
    }

    $root = realpath(UPLOAD_DIR);
    $path = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($material['file_path']));
    if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
        $pdo->rollBack();
        http_response_code(404);
        exit('The material file is unavailable.');
    }

    $log = $pdo->prepare('INSERT INTO downloads (user_id, material_id) VALUES (:user_id, :material_id)');
    $log->execute(['user_id' => $_SESSION['user_id'], 'material_id' => $materialId]);
    $increment = $pdo->prepare('UPDATE materials SET download_count = download_count + 1 WHERE material_id = :id');
    $increment->execute(['id' => $materialId]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Material download failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The material could not be downloaded.');
}

$extensions = array_keys(ALLOWED_TYPES);
$extension = in_array($material['file_type'], $extensions, true) ? $material['file_type'] : 'bin';
$mimeTypes = ['pdf' => 'application/pdf', 'docx' => ALLOWED_TYPES['docx'][0], 'pptx' => ALLOWED_TYPES['pptx'][0], 'mp4' => 'video/mp4'];
$downloadName = preg_replace('/[\r\n"\\\\]/', '_', $material['title']) . '.' . $extension;
header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="material.' . $extension . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;