<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('instructor');

$statement = $pdo->prepare(
    'SELECT COUNT(*) AS uploads, COALESCE(SUM(download_count), 0) AS downloads,
            COALESCE(SUM(approved IS NULL), 0) AS pending
     FROM materials WHERE uploader_id = :uploader_id'
);
$statement->execute(['uploader_id' => $_SESSION['user_id']]);
$stats = $statement->fetch();
$pageTitle = 'Instructor dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <h1 class="page-title">Welcome, <?= e($_SESSION['name']) ?></h1>
    <p class="text-secondary">Manage course materials and monitor their review and use.</p>
    <div class="row g-3 my-4">
        <?php foreach ([['Uploads', $stats['uploads']], ['Downloads', $stats['downloads']], ['Pending review', $stats['pending']]] as [$label, $value]): ?>
            <div class="col-12 col-md-4"><section class="content-panel h-100"><div class="stat-value"><?= (int) $value ?></div><div class="stat-label mt-1"><?= e($label) ?></div></section></div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="<?= e(site_url('instructor/upload.php')) ?>"><i class="bi bi-upload" aria-hidden="true"></i> Upload material</a>
        <a class="btn btn-outline-primary" href="<?= e(site_url('instructor/my_uploads.php')) ?>">View my uploads</a></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>