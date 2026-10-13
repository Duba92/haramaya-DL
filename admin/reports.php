<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = sanitize($_POST['status'] ?? '');
    if ($reportId !== false && $reportId !== null && in_array($status, ['resolved', 'dismissed', 'open'], true)) {
        $update = $pdo->prepare('UPDATE material_reports SET status = :status WHERE report_id = :id');
        $update->execute(['status' => $status, 'id' => $reportId]);
        write_activity($pdo, (int) $_SESSION['user_id'], 'report_' . $status, 'Report #' . $reportId);
        set_flash('Report status updated.');
    }
    redirect('admin/reports.php');
}

$reports = $pdo->query(
    'SELECT r.report_id, r.reason, r.status, r.created_at, u.name AS reporter,
            m.material_id, m.title AS material_title
     FROM material_reports AS r JOIN users AS u ON u.user_id = r.user_id
     JOIN materials AS m ON m.material_id = r.material_id
     ORDER BY (r.status = \'open\') DESC, r.created_at DESC LIMIT 200'
)->fetchAll();
$pageTitle = 'Material reports';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <h1 class="page-title">Material reports</h1>
    <div class="table-responsive content-panel p-0"><table class="table align-middle mb-0"><thead><tr><th>Material</th><th>Reason</th><th>Reported by</th><th>Date</th><th>Status</th><th>Update</th></tr></thead><tbody>
        <?php foreach ($reports as $report): ?><tr><td><a href="<?= e(site_url('material.php?id=' . (int) $report['material_id'])) ?>"><?= e($report['material_title']) ?></a></td><td><?= e($report['reason']) ?></td><td><?= e($report['reporter']) ?></td><td><?= e(date('M j, Y', strtotime($report['created_at']))) ?></td><td><?= e(ucfirst($report['status'])) ?></td>
            <td><form method="post" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>"><select class="form-select form-select-sm" name="status" aria-label="Report status"><option value="open" <?= $report['status'] === 'open' ? 'selected' : '' ?>>Open</option><option value="resolved" <?= $report['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option><option value="dismissed" <?= $report['status'] === 'dismissed' ? 'selected' : '' ?>>Dismissed</option></select><button class="btn btn-sm btn-primary" type="submit">Save</button></form></td></tr><?php endforeach; ?>
        <?php if ($reports === []): ?><tr><td colspan="6" class="text-center text-secondary py-5">No reports have been submitted.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>