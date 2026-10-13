<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('instructor');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $materialId = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($materialId !== false && $materialId !== null) {
        $find = $pdo->prepare('SELECT title, file_path FROM materials WHERE material_id = :id AND uploader_id = :uploader_id');
        $find->execute(['id' => $materialId, 'uploader_id' => $_SESSION['user_id']]);
        $material = $find->fetch();
        if ($material) {
            $delete = $pdo->prepare('DELETE FROM materials WHERE material_id = :id AND uploader_id = :uploader_id');
            $delete->execute(['id' => $materialId, 'uploader_id' => $_SESSION['user_id']]);
            if ($delete->rowCount() === 1) {
                delete_stored_file($material['file_path']);
                write_activity($pdo, (int) $_SESSION['user_id'], 'material_delete', $material['title']);
                set_flash('Material deleted.');
            }
        }
    }
    redirect('instructor/my_uploads.php');
}

$statement = $pdo->prepare(
    'SELECT m.material_id, m.title, m.file_type, m.upload_date, m.approved, m.download_count,
            c.course_code, c.course_title
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     WHERE m.uploader_id = :uploader_id ORDER BY m.upload_date DESC'
);
$statement->execute(['uploader_id' => $_SESSION['user_id']]);
$materials = $statement->fetchAll();
$pageTitle = 'My uploads';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <div class="page-toolbar"><div><h1 class="page-title">My uploads</h1><p class="text-secondary mb-0">Track review status and material downloads.</p></div>
        <a class="btn btn-primary" href="<?= e(site_url('instructor/upload.php')) ?>"><i class="bi bi-upload" aria-hidden="true"></i> Upload material</a></div>
    <div class="table-responsive content-panel p-0"><table class="table align-middle mb-0"><thead><tr><th>Material</th><th>Course</th><th>Uploaded</th><th>Status</th><th>Downloads</th><th><span class="visually-hidden">Actions</span></th></tr></thead><tbody>
        <?php foreach ($materials as $material): ?>
            <?php $status = $material['approved'] === null ? 'pending' : ((int) $material['approved'] === 1 ? 'approved' : 'rejected'); ?>
            <tr><td><i class="bi <?= e(get_file_icon($material['file_type'])) ?> material-icon me-2" aria-hidden="true"></i><?= e($material['title']) ?></td>
                <td><?= e($material['course_code'] . ' - ' . $material['course_title']) ?></td><td><?= e(date('M j, Y', strtotime($material['upload_date']))) ?></td>
                <td><span class="status-badge status-<?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td><td><?= (int) $material['download_count'] ?></td>
                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(site_url('instructor/edit_upload.php?id=' . (int) $material['material_id'])) ?>" aria-label="Edit <?= e($material['title']) ?>">Edit</a>
                    <form method="post" class="d-inline" data-confirm="Delete this upload permanently?" action="<?= e(site_url('instructor/my_uploads.php')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form></td></tr>
        <?php endforeach; ?>
        <?php if ($materials === []): ?><tr><td colspan="6" class="text-center text-secondary py-5">You have not uploaded any materials yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>