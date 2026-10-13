<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function require_login(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['last_activity'])) {
        set_flash('Please sign in to continue.', 'warning');
        redirect('login.php');
    }

    if (time() - (int) $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('Your session expired. Please sign in again.', 'warning');
        redirect('login.php');
    }

    global $pdo;
    $statement = $pdo->prepare('SELECT is_active FROM users WHERE user_id = :user_id');
    $statement->execute(['user_id' => $_SESSION['user_id']]);
    $isActive = $statement->fetchColumn();
    if ($isActive === false || (int) $isActive !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('This account is unavailable. Contact the library administrator.', 'danger');
        redirect('login.php');
    }

    $_SESSION['last_activity'] = time();
}