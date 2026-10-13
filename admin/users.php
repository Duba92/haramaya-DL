<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = sanitize($_POST['action'] ?? '');
    if ($userId === false || $userId === null || $userId === (int) $_SESSION['user_id']) {
        set_flash('That user action is not allowed.', 'warning');
        redirect('admin/users.php');
    }
    try {
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $update = $pdo->prepare('UPDATE users SET is_active = :active WHERE user_id = :id');
            $update->execute(['active' => $action === 'activate' ? 1 : 0, 'id' => $userId]);
        } elseif ($action === 'reset_password') {
            $temporaryPassword = bin2hex(random_bytes(8));
            $update = $pdo->prepare('UPDATE users SET password = :password WHERE user_id = :id');
            $update->execute(['password' => password_hash($temporaryPassword, PASSWORD_DEFAULT), 'id' => $userId]);
            set_flash('Temporary password (shown once): ' . $temporaryPassword, 'warning');
        } elseif ($action === 'delete') {
            $delete = $pdo->prepare('DELETE FROM users WHERE user_id = :id');
            $delete->execute(['id' => $userId]);
        } else {
            set_flash('Choose a valid user action.', 'warning');
            redirect('admin/users.php');
        }
        write_activity($pdo, (int) $_SESSION['user_id'], 'user_' . $action, 'User #' . $userId);
        if ($action !== 'reset_password') {
            set_flash('User action completed.');
        }
    } catch (PDOException $exception) {
        error_log('User management failed: ' . $exception->getMessage());
        set_flash('The user could not be changed. Remove or reassign dependent records first.', 'danger');
    }
    redirect('admin/users.php');
}

$search = sanitize($_GET['q'] ?? '');
$role = sanitize($_GET['role'] ?? '');
$active = sanitize($_GET['active'] ?? 'all');
$statement = $pdo->prepare(
    'SELECT u.user_id, u.name, u.email, u.role, u.is_active, u.created_at, d.name AS department
     FROM users AS u LEFT JOIN departments AS d ON d.department_id = u.department_id
     WHERE (:search_blank = \'\' OR u.name LIKE :name_search OR u.email LIKE :email_search)
       AND (:role_blank = \'\' OR u.role = :role_filter)
       AND (:active_all = 1 OR u.is_active = :active_filter)
     ORDER BY u.created_at DESC LIMIT 200'
);
$term = '%' . $search . '%';
$statement->execute([
    'search_blank' => $search,
    'name_search' => $term,
    'email_search' => $term,
    'role_blank' => in_array($role, ['student', 'instructor', 'admin'], true) ? 'set' : '',
    'role_filter' => $role,
    'active_all' => in_array($active, ['0', '1'], true) ? 0 : 1,
    'active_filter' => in_array($active, ['0', '1'], true) ? (int) $active : 0,
]);
$users = $statement->fetchAll();
$pageTitle = 'Manage users';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <div class="page-toolbar"><div><h1 class="page-title">Users</h1><p class="text-secondary mb-0">Search, edit, activate, or deactivate accounts.</p></div><a class="btn btn-primary" href="<?= e(site_url('admin/user_edit.php')) ?>">Add user</a></div>
    <form class="row g-2 mb-3" method="get"><div class="col-12 col-md-5"><label class="visually-hidden" for="q">Search users</label><input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Name or email"></div>
        <div class="col-6 col-md-3"><label class="visually-hidden" for="role">Role</label><select class="form-select" id="role" name="role"><option value="">All roles</option><?php foreach (['student', 'instructor', 'admin'] as $option): ?><option value="<?= e($option) ?>" <?= $role === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="visually-hidden" for="active">Account status</label><select class="form-select" id="active" name="active"><option value="all">All status</option><option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive</option></select></div><div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div></form>
    <div class="table-responsive content-panel p-0"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($users as $user): ?><tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e(ucfirst($user['role'])) ?></td><td><?= e($user['department'] ?? '—') ?></td><td><?= (int) $user['is_active'] === 1 ? 'Active' : 'Inactive' ?></td><td><?= e(date('M j, Y', strtotime($user['created_at']))) ?></td>
            <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(site_url('admin/user_edit.php?id=' . (int) $user['user_id'])) ?>">Edit</a>
                <?php if ((int) $user['user_id'] !== (int) $_SESSION['user_id']): ?>
                    <?php foreach ([(int) $user['is_active'] === 1 ? 'deactivate' : 'activate', 'reset_password', 'delete'] as $action): ?>
                        <form class="d-inline" method="post" data-confirm="<?= $action === 'delete' ? 'Delete this account? Existing uploads may prevent deletion.' : ($action === 'reset_password' ? 'Reset password and show a temporary password once?' : 'Change account activation?') ?>">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><button class="btn btn-sm <?= $action === 'delete' ? 'btn-outline-danger' : 'btn-outline-secondary' ?>" type="submit"><?= e(ucwords(str_replace('_', ' ', $action))) ?></button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td></tr><?php endforeach; ?>
        <?php if ($users === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">No users found.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>