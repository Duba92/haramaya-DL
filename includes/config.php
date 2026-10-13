<?php
declare(strict_types=1);

const SITE_NAME = 'Haramaya University Digital Library';
const MAX_FILE_SIZE = 52428800;
const ALLOWED_TYPES = [
    'pdf' => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'mp4' => ['video/mp4', 'application/mp4'],
];
const SESSION_TIMEOUT = 1800;

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'materials');

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = preg_replace('#/(admin|instructor|student|api)(/.*)?$#', '', $scriptDirectory) ?? '';
define('BASE_URL', rtrim($basePath, '/'));

$localConfiguration = [];
$localConfigurationFile = __DIR__ . '/config.local.php';
if (is_file($localConfigurationFile)) {
    $loadedConfiguration = require $localConfigurationFile;
    $localConfiguration = is_array($loadedConfiguration) ? $loadedConfiguration : [];
}

$appEnvironment = getenv('APP_ENV') ?: ($localConfiguration['APP_ENV'] ?? 'development');
ini_set('display_errors', $appEnvironment === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/logs/app.log');
error_reporting(E_ALL);

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => BASE_URL !== '' ? BASE_URL . '/' : '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$databaseHost = getenv('DB_HOST') ?: ($localConfiguration['DB_HOST'] ?? '127.0.0.1');
$databasePort = getenv('DB_PORT') ?: ($localConfiguration['DB_PORT'] ?? '3306');
$databaseName = getenv('DB_NAME') ?: ($localConfiguration['DB_NAME'] ?? 'digital_library');
$databaseUser = getenv('DB_USER') ?: ($localConfiguration['DB_USER'] ?? 'root');
$databasePassword = getenv('DB_PASSWORD') ?: ($localConfiguration['DB_PASSWORD'] ?? '');
$databaseDsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $databaseHost,
    $databasePort,
    $databaseName
);

try {
    $pdo = new PDO($databaseDsn, $databaseUser, $databasePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The library is temporarily unavailable. Please try again later.');
}