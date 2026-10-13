<?php
declare(strict_types=1);

function e(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }
    return trim(str_replace("\0", '', (string) $value));
}

function site_url(string $path = ''): string
{
    $base = BASE_URL;
    $path = ltrim($path, '/');
    return $base . ($path === '' ? '/' : '/' . $path);
}

function redirect(string $path): void
{
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) {
        $path = 'index.php';
    }
    header('Location: ' . site_url($path), true, 303);
    exit;
}

function set_flash(string $message, string $type = 'success'): void
{
    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => in_array($type, $allowedTypes, true) ? $type : 'info',
    ];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function check_role(string ...$allowedRoles): bool
{
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $allowedRoles, true);
}

function require_role(string ...$allowedRoles): void
{
    require_login();
    if (!check_role(...$allowedRoles)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function format_file_size(int|string $bytes): string
{
    $size = max(0, (int) $bytes);
    if ($size < 1024) {
        return $size . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $size / 1024;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'TB') {
            return number_format($value, 1) . ' ' . $unit;
        }
        $value /= 1024;
    }
    return number_format($value, 1) . ' TB';
}

function get_file_icon(string $fileType): string
{
    return match (strtolower($fileType)) {
        'pdf' => 'bi-file-earmark-pdf',
        'doc', 'docx' => 'bi-file-earmark-word',
        'ppt', 'pptx' => 'bi-file-earmark-ppt',
        'mp4' => 'bi-file-earmark-play',
        default => 'bi-file-earmark',
    };
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function download_form(int $materialId, string $label = 'Download', string $buttonClass = 'btn btn-primary'): string
{
    return '<form class="d-inline" method="post" action="' . e(site_url('download.php')) . '">'
        . csrf_field()
        . '<input type="hidden" name="material_id" value="' . $materialId . '">'
        . '<button class="' . e($buttonClass) . '" type="submit"><i class="bi bi-download" aria-hidden="true"></i> '
        . e($label) . '</button></form>';
}

function require_csrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
        || !is_string($submittedToken)
        || !hash_equals(csrf_token(), $submittedToken)
    ) {
        http_response_code(419);
        exit('This form expired. Reload the page and try again.');
    }
}

function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $settings = $pdo->query('SELECT setting_key, setting_value FROM site_settings')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return (string) ($settings[$key] ?? $default);
}

function write_activity(PDO $pdo, int $actorId, string $action, string $details = ''): void
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO activity_log (actor_id, action, details) VALUES (:actor_id, :action, :details)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'details' => mb_substr($details, 0, 500),
        ]);
    } catch (PDOException $exception) {
        error_log('Activity log write failed: ' . $exception->getMessage());
    }
}

function delete_stored_file(string $relativePath): void
{
    if (!str_starts_with($relativePath, 'uploads/materials/')) {
        return;
    }
    $uploadRoot = realpath(UPLOAD_DIR);
    $filePath = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($relativePath));
    if (
        $uploadRoot !== false
        && $filePath !== false
        && str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR)
        && is_file($filePath)
    ) {
        unlink($filePath);
    }
}