<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$userId = $userId === false ? null : $userId;
$user = ['name' => '', 'email' => '', 'role' => 'student', 'department_id' => null, 'is_active' => 1];
if ($userId !== null) {
    $find = $pdo->prepare('SELECT name, email, role, department_id, is_active FROM users WHERE user_id = :id');
    $find->execute(['id' => $userId]);
    $existingUser = $find->fetch();
    if (!$existingUser) {
        http_response_code(404);
        exit('User not found.');
    }
    $user = $existingUser;
}
$departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $user['name'] = sanitize($_POST['name'] ?? '');
    $user['email'] = strtolower(sanitize($_POST['email'] ?? ''));
    $user['role'] = sanitize($_POST['role'] ?? '');
    $departmentInput = sanitize($_POST['department_id'] ?? '');
    $user['department_id'] = $departmentInput === '' ? null : filter_var($departmentInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $password = (string) ($_POST['password'] ?? '');

    if ($user['name'] === '' || mb_strlen($user['name']) > 120) {
        $errors[] = 'Enter a name no longer than 120 characters.';
    }
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL) || strlen($user['email']) > 254) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!in_array($user['role'], ['student', 'instructor', 'admin'], true)) {
        $errors[] = 'Choose a valid role.';
    }
    if ($user['role'] !== 'admin' && !is_int($user['department_id'])) {
        $errors[] = 'Students and instructors must have a department.';
    }
    if ($password !== '' && !preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include an uppercase letter and a number.';
    }
    if (!$userId && $password === '') {
        $errors[] = 'A password is required for a new account.';
    }

    if ($errors === []) {
        $emailCheck = $pdo->prepare('SELECT user_id FROM users WHERE email = :email AND (:exclude_id IS NULL OR user_id <> :exclude_user_id)');
        $emailCheck->execute(['email' => $user['email'], 'exclude_id' => $userId, 'exclude_user_id' => $userId ?? 0]);
        if ($emailCheck->fetchColumn()) {
            $errors[] = 'An account with this email already exists.';
        }
        if (is_int($user['department_id'])) {
            $departmentCheck = $pdo->prepare('SELECT 1 FROM departments WHERE department_id = :id');
            $departmentCheck->execute(['id' => $user['department_id']]);
            if (!$departmentCheck->fetchColumn()) {
                $errors[] = 'Select a valid department.';
            }
        }
    }

    if ($errors === []) {
        try {
            if ($userId) {
                $sql = 'UPDATE users SET name = :name, email = :email, role = :role, department_id = :department_id';
                $params = ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'department_id' => $user['department_id'], 'id' => $userId];
                if ($password !== '') {
                    $sql .= ', password = :password';
                    $params['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE user_id = :id';
                $pdo->prepare($sql)->execute($params);
                write_activity($pdo, (int) $_SESSION['user_id'], 'user_edit', 'User #' . $userId);
            } else {
                $insert = $pdo->prepare('INSERT INTO users (name, email, password, role, department_id) VALUES (:name, :email, :password, :role, :department_id)');
                $insert->execute([
                    'name' => $user['name'], 'email' => $user['email'],
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $user['role'], 'department_id' => $user['department_id'],
                ]);
                write_activity($pdo, (int) $_SESSION['user_id'], 'user_create', $user['email']);
            }
            set_flash('User saved.');
            redirect('admin/users.php');
        } catch (PDOException $exception) {
            error_log('User save failed: ' . $exception->getMessage());
            $errors[] = 'The user could not be saved. Check for an email or department conflict.';
        }
    }
}

$pageTitle = $userId ? 'Edit user' : 'Add user';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="form-panel">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" maxlength="120" required value="<?= e($user['name']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="254" required value="<?= e($user['email']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="role">Role</label><select class="form-select" id="role" name="role"><?php foreach (['student', 'instructor', 'admin'] as $role): ?><option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label" for="department_id">Department</label><select class="form-select" id="department_id" name="department_id"><option value="">Not applicable (admin only)</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['department_id'] ?>" <?= (string) $department['department_id'] === (string) ($user['department_id'] ?? '') ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-4"><label class="form-label" for="password"><?= $userId ? 'New password (optional)' : 'Password' ?></label><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" <?= $userId ? '' : 'required' ?>><div class="form-text">At least 8 characters, with one uppercase letter and one number.</div></div>
        <button class="btn btn-primary" type="submit">Save user</button> <a class="btn btn-outline-secondary" href="<?= e(site_url('admin/users.php')) ?>">Cancel</a>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>