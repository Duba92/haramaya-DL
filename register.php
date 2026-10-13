<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$form = ['name' => '', 'email' => '', 'role' => 'student', 'department_id' => ''];
$departments = $pdo->query(
    'SELECT d.department_id, d.name AS department_name, c.name AS college_name
     FROM departments AS d JOIN colleges AS c ON c.college_id = d.college_id
     ORDER BY c.name, d.name'
)->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $form['name'] = sanitize($_POST['name'] ?? '');
    $form['email'] = strtolower(sanitize($_POST['email'] ?? ''));
    $form['role'] = sanitize($_POST['role'] ?? '');
    $form['department_id'] = sanitize($_POST['department_id'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($form['name'] === '' || mb_strlen($form['name']) > 120) {
        $errors[] = 'Enter a name no longer than 120 characters.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 254) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!in_array($form['role'], ['student', 'instructor'], true)) {
        $errors[] = 'Choose student or instructor as the account type.';
    }
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include an uppercase letter and a number.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'The passwords do not match.';
    }
    $departmentId = filter_var($form['department_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($departmentId === false) {
        $errors[] = 'Select a department.';
    }

    if ($errors === []) {
        $departmentCheck = $pdo->prepare('SELECT 1 FROM departments WHERE department_id = :id');
        $departmentCheck->execute(['id' => $departmentId]);
        $emailCheck = $pdo->prepare('SELECT 1 FROM users WHERE email = :email');
        $emailCheck->execute(['email' => $form['email']]);
        if (!$departmentCheck->fetchColumn()) {
            $errors[] = 'Select a valid department.';
        }
        if ($emailCheck->fetchColumn()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if ($errors === []) {
        try {
            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password, role, department_id)
                 VALUES (:name, :email, :password, :role, :department_id)'
            );
            $insert->execute([
                'name' => $form['name'],
                'email' => $form['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $form['role'],
                'department_id' => $departmentId,
            ]);
            set_flash('Registration complete. You can now sign in.');
            redirect('login.php');
        } catch (PDOException $exception) {
            error_log('Registration failed: ' . $exception->getMessage());
            $errors[] = 'Registration could not be completed. Please try again.';
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <h1 class="page-title">Create your library account</h1>
    <p class="text-secondary">Use your university email address where available.</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form method="post" action="<?= e(site_url('register.php')) ?>" class="form-panel">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="name">Full name</label>
            <input class="form-control" id="name" name="name" maxlength="120" required autocomplete="name" value="<?= e($form['name']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" maxlength="254" required autocomplete="email" value="<?= e($form['email']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="role">Account type</label>
            <select class="form-select" id="role" name="role" required>
                <option value="student" <?= $form['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="instructor" <?= $form['role'] === 'instructor' ? 'selected' : '' ?>>Instructor</option>
            </select></div>
        <div class="mb-3"><label class="form-label" for="department_id">Department</label>
            <select class="form-select" id="department_id" name="department_id" required>
                <option value="">Choose a department</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= (int) $department['department_id'] ?>" <?= (string) $department['department_id'] === $form['department_id'] ? 'selected' : '' ?>><?= e($department['college_name'] . ' / ' . $department['department_name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" minlength="8" required autocomplete="new-password" aria-describedby="password-help">
            <div id="password-help" class="form-text">At least 8 characters, including one uppercase letter and one number.</div></div>
        <div class="mb-4"><label class="form-label" for="confirm_password">Confirm password</label>
            <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-primary w-100" type="submit">Create account</button>
    </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>