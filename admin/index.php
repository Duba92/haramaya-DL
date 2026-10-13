<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$stats = $pdo->query(
    'SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM materials) AS materials,
            (SELECT COUNT(*) FROM downloads) AS downloads,
            (SELECT COUNT(*) FROM materials WHERE approved IS NULL) AS pending'
)->fetch();
$uploadsByMonth = $pdo->query(
    "SELECT DATE_FORMAT(upload_date, '%Y-%m') AS month, COUNT(*) AS total
     FROM materials WHERE upload_date >= DATE_SUB(CURRENT_DATE, INTERVAL 11 MONTH)
     GROUP BY DATE_FORMAT(upload_date, '%Y-%m') ORDER BY month"
)->fetchAll();
$downloadsByDepartment = $pdo->query(
    'SELECT d.name AS department, COUNT(dl.download_id) AS total
     FROM departments AS d
     LEFT JOIN courses AS c ON c.department_id = d.department_id
     LEFT JOIN materials AS m ON m.course_id = c.course_id
     LEFT JOIN downloads AS dl ON dl.material_id = m.material_id
     GROUP BY d.department_id, d.name ORDER BY total DESC LIMIT 8'
)->fetchAll();
$activity = $pdo->query(
    'SELECT a.action, a.details, a.created_at, u.name AS actor_name
     FROM activity_log AS a LEFT JOIN users AS u ON u.user_id = a.actor_id
     ORDER BY a.created_at DESC LIMIT 10'
)->fetchAll();
$pageTitle = 'Administrator dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <div class="page-toolbar"><div><h1 class="page-title">Administration</h1><p class="text-secondary mb-0">Library activity and review queue.</p></div>
        <a class="btn btn-primary" href="<?= e(site_url('admin/materials.php?status=pending')) ?>">Review pending materials</a></div>
    <div class="row g-3 mb-4">
        <?php foreach ([['Users', $stats['users']], ['Materials', $stats['materials']], ['Downloads', $stats['downloads']], ['Pending', $stats['pending']]] as [$label, $value]): ?>
            <div class="col-6 col-lg-3"><section class="content-panel h-100"><div class="stat-value"><?= (int) $value ?></div><div class="stat-label mt-1"><?= e($label) ?></div></section></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-4">
        <div class="col-12 col-lg-7"><section class="content-panel"><h2 class="section-heading">Uploads over time</h2><canvas id="uploads-chart" aria-label="Monthly material uploads chart" role="img"></canvas></section></div>
        <div class="col-12 col-lg-5"><section class="content-panel"><h2 class="section-heading">Downloads by department</h2><canvas id="downloads-chart" aria-label="Downloads by department chart" role="img"></canvas></section></div>
    </div>
    <section class="content-panel mt-4"><h2 class="section-heading">Recent activity</h2>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Action</th><th>Details</th><th>By</th><th>Date</th></tr></thead><tbody>
            <?php foreach ($activity as $event): ?><tr><td><?= e(str_replace('_', ' ', ucfirst($event['action']))) ?></td><td><?= e($event['details']) ?></td><td><?= e($event['actor_name'] ?? 'Deleted account') ?></td><td><?= e(date('M j, Y H:i', strtotime($event['created_at']))) ?></td></tr><?php endforeach; ?>
            <?php if ($activity === []): ?><tr><td colspan="4" class="text-secondary">No activity recorded yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <nav class="d-flex flex-wrap gap-2 mt-4" aria-label="Administration pages">
        <a class="btn btn-outline-primary" href="<?= e(site_url('admin/users.php')) ?>">Users</a>
        <a class="btn btn-outline-primary" href="<?= e(site_url('admin/courses.php')) ?>">Courses</a>
        <a class="btn btn-outline-primary" href="<?= e(site_url('admin/departments.php')) ?>">Departments and colleges</a>
        <a class="btn btn-outline-primary" href="<?= e(site_url('admin/reports.php')) ?>">Material reports</a>
        <a class="btn btn-outline-primary" href="<?= e(site_url('admin/settings.php')) ?>">Settings</a>
    </nav>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
<script>
window.libraryCharts = {
    uploads: <?= json_encode($uploadsByMonth, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    downloads: <?= json_encode($downloadsByDepartment, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>