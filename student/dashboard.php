<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$departmentId = $_SESSION['department_id'] ?? null;
$recommended = [];
if ($departmentId !== null) {
    $recommendations = $pdo->prepare(
        'SELECT m.material_id, m.title, m.file_type, m.file_size, m.download_count,
                c.course_code, c.course_title
         FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
         WHERE c.department_id = :department_id AND m.approved = 1
         ORDER BY m.download_count DESC, m.upload_date DESC LIMIT 6'
    );
    $recommendations->execute(['department_id' => $departmentId]);
    $recommended = $recommendations->fetchAll();
}
$downloads = $pdo->prepare(
    'SELECT m.material_id, m.title, m.file_type, m.file_size, c.course_code,
            c.course_title, MAX(dl.download_date) AS last_download
     FROM downloads AS dl JOIN materials AS m ON m.material_id = dl.material_id
     JOIN courses AS c ON c.course_id = m.course_id
     WHERE dl.user_id = :user_id AND m.approved = 1
     GROUP BY m.material_id, m.title, m.file_type, m.file_size, c.course_code, c.course_title
     ORDER BY last_download DESC LIMIT 8'
);
$downloads->execute(['user_id' => $_SESSION['user_id']]);
$recentDownloads = $downloads->fetchAll();
$pageTitle = 'Student dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <h1 class="page-title">Welcome, <?= e($_SESSION['name']) ?></h1>
    <p class="text-secondary">Find course-aligned study materials and return to your recent downloads.</p>
    <form class="home-search mb-5" action="<?= e(site_url('search.php')) ?>" method="get" role="search"><label class="visually-hidden" for="dashboard-query">Search materials</label><input class="form-control form-control-lg" id="dashboard-query" name="q" type="search" placeholder="Search by course code or keyword"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button></form>
    <section class="mb-5"><div class="section-topline"><h2 class="section-heading">Recommended for your department</h2><a href="<?= e(site_url('search.php')) ?>">Search all</a></div><div class="row g-3">
        <?php foreach ($recommended as $materialCard): ?><div class="col-12 col-md-6 col-lg-4"><?php require __DIR__ . '/../includes/material_card.php'; ?></div><?php endforeach; ?>
        <?php if ($recommended === []): ?><p class="text-secondary">No approved materials are available for your department yet.</p><?php endif; ?></div></section>
    <section class="mb-5"><h2 class="section-heading mb-3">Recent downloads</h2><div class="table-responsive content-panel p-0"><table class="table align-middle mb-0"><thead><tr><th>Material</th><th>Course</th><th>Last downloaded</th><th></th></tr></thead><tbody>
        <?php foreach ($recentDownloads as $material): ?><tr><td><a href="<?= e(site_url('material.php?id=' . (int) $material['material_id'])) ?>"><?= e($material['title']) ?></a></td><td><?= e($material['course_code'] . ' - ' . $material['course_title']) ?></td><td><?= e(date('M j, Y', strtotime($material['last_download']))) ?></td><td><?= download_form((int) $material['material_id'], 'Download again', 'btn btn-sm btn-outline-primary') ?></td></tr><?php endforeach; ?>
        <?php if ($recentDownloads === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">Your downloaded materials will appear here.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <section><div class="section-topline"><h2 class="section-heading">Saved materials</h2><span class="small text-secondary">Saved on this device</span></div><div class="row g-3" data-bookmarks data-api="<?= e(site_url('api/materials.php')) ?>"><p class="text-secondary">Loading saved materials…</p></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>