<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $email = strtolower(sanitize($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $statement = $pdo->prepare(
        'SELECT user_id, name, email, password, role, department_id, is_active
         FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department_id'] = $user['department_id'] === null ? null : (int) $user['department_id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf_token']);
        csrf_token();

        $dashboard = match ($user['role']) {
            'admin' => 'admin/index.php',
            'instructor' => 'instructor/dashboard.php',
            default => 'student/dashboard.php',
        };
        redirect($dashboard);
    }
    $error = 'Email or password is incorrect, or the account is inactive.';
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <h1 class="page-title">Sign in</h1>
    <?php if ($message = get_flash()): ?><div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(site_url('login.php')) ?>" class="form-panel">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>"></div>
        <div class="mb-4"><label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="btn btn-primary w-100" type="submit">Sign in</button>
    </form>
    <p class="mt-3">New to the library? <a href="<?= e(site_url('register.php')) ?>">Create an account</a></p>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>