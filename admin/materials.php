<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $singleAction = sanitize($_POST['single_action'] ?? '');
    $action = sanitize($_POST['bulk_action'] ?? '');
    $materialIds = $_POST['material_ids'] ?? [];
    if ($singleAction !== '' && preg_match('/^(approve|reject|delete):(\d+)$/', $singleAction, $matches)) {
        $action = $matches[1];
        $materialIds = [$matches[2]];
    }
    $materialIds = array_values(array_unique(array_filter(array_map('intval', is_array($materialIds) ? $materialIds : []), static fn (int $id): bool => $id > 0)));
    if (!in_array($action, ['approve', 'reject', 'delete'], true) || $materialIds === []) {
        set_flash('Choose a valid action and at least one material.', 'warning');
        redirect('admin/materials.php');
    }

    $filesToDelete = [];
    try {
        $pdo->beginTransaction();
        foreach ($materialIds as $id) {
            if ($action === 'delete') {
                $find = $pdo->prepare('SELECT file_path, title FROM materials WHERE material_id = :id');
                $find->execute(['id' => $id]);
                $material = $find->fetch();
                if ($material) {
                    $filesToDelete[] = $material['file_path'];
                    $delete = $pdo->prepare('DELETE FROM materials WHERE material_id = :id');
                    $delete->execute(['id' => $id]);
                }
            } else {
                $decision = $action === 'approve' ? 1 : 0;
                $update = $pdo->prepare('UPDATE materials SET approved = :approved WHERE material_id = :id');
                $update->execute(['approved' => $decision, 'id' => $id]);
            }
        }
        $pdo->commit();
        foreach ($filesToDelete as $file) {
            delete_stored_file($file);
        }
        write_activity($pdo, (int) $_SESSION['user_id'], 'material_' . $action, count($materialIds) . ' record(s)');
        set_flash('Material action completed.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Material moderation failed: ' . $exception->getMessage());
        set_flash('The material action could not be completed.', 'danger');
    }
    redirect('admin/materials.php');
}

$status = sanitize($_GET['status'] ?? 'pending');
$conditions = match ($status) {
    'approved' => 'WHERE m.approved = 1',
    'rejected' => 'WHERE m.approved = 0',
    'all' => '',
    default => 'WHERE m.approved IS NULL',
};
$materials = $pdo->query(
    'SELECT m.material_id, m.title, m.file_type, m.file_size, m.upload_date,
            m.approved, m.download_count, c.course_code, c.course_title,
            u.name AS uploader_name
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     JOIN users AS u ON u.user_id = m.uploader_id ' . $conditions . '
     ORDER BY m.upload_date DESC LIMIT 200'
)->fetchAll();
$pageTitle = 'Manage materials';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <div class="page-toolbar"><div><h1 class="page-title">Material review</h1><p class="text-secondary mb-0">Approve, reject, or remove submitted materials.</p></div>
        <a class="btn btn-outline-secondary" href="<?= e(site_url('admin/index.php')) ?>">Dashboard</a></div>
    <nav class="nav nav-tabs mb-3" aria-label="Material status filter">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?>
            <a class="nav-link <?= $status === $key ? 'active' : '' ?>" href="<?= e(site_url('admin/materials.php?status=' . $key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= e(site_url('admin/materials.php?status=' . rawurlencode($status))) ?>">
        <?= csrf_field() ?>
        <div class="d-flex gap-2 mb-3"><select class="form-select" name="bulk_action" aria-label="Bulk action" style="max-width: 220px"><option value="">Bulk action</option><option value="approve">Approve selected</option><option value="reject">Reject selected</option><option value="delete">Delete selected</option></select><button class="btn btn-outline-primary" type="submit">Apply</button></div>
        <div class="table-responsive content-panel p-0"><table class="table align-middle mb-0"><thead><tr><th><input class="form-check-input" type="checkbox" data-select-all aria-label="Select all materials"></th><th>Material</th><th>Course</th><th>Uploader</th><th>Uploaded</th><th>Status</th><th>Downloads</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($materials as $material): ?>
            <?php $materialStatus = $material['approved'] === null ? 'pending' : ((int) $material['approved'] === 1 ? 'approved' : 'rejected'); ?>
            <tr><td><input class="form-check-input" type="checkbox" name="material_ids[]" value="<?= (int) $material['material_id'] ?>" aria-label="Select <?= e($material['title']) ?>"></td>
                <td><i class="bi <?= e(get_file_icon($material['file_type'])) ?> material-icon me-1" aria-hidden="true"></i><a href="<?= e(site_url('material.php?id=' . (int) $material['material_id'])) ?>"><?= e($material['title']) ?></a><div class="small text-secondary"><?= e(strtoupper($material['file_type']) . ' / ' . format_file_size($material['file_size'])) ?></div></td>
                <td><?= e($material['course_code'] . ' - ' . $material['course_title']) ?></td><td><?= e($material['uploader_name']) ?></td><td><?= e(date('M j, Y', strtotime($material['upload_date']))) ?></td>
                <td><span class="status-badge status-<?= e($materialStatus) ?>"><?= e(ucfirst($materialStatus)) ?></span></td><td><?= (int) $material['download_count'] ?></td>
                <td class="text-nowrap"><button class="btn btn-sm btn-success" name="single_action" value="approve:<?= (int) $material['material_id'] ?>" type="submit">Approve</button>
                    <button class="btn btn-sm btn-outline-warning" name="single_action" value="reject:<?= (int) $material['material_id'] ?>" type="submit">Reject</button>
                    <button class="btn btn-sm btn-outline-danger" name="single_action" value="delete:<?= (int) $material['material_id'] ?>" type="submit" data-confirm="Delete this material and its file?">Delete</button></td></tr>
        <?php endforeach; ?>
        <?php if ($materials === []): ?><tr><td colspan="8" class="text-center text-secondary py-5">No materials in this view.</td></tr><?php endif; ?>
        </tbody></table></div>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>