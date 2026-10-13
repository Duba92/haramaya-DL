<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($materialId === false || $materialId === null) {
    http_response_code(404);
    exit('Material not found.');
}
$find = $pdo->prepare(
    'SELECT m.material_id, m.title, m.description, m.file_type, m.file_size,
            m.upload_date, m.download_count, m.approved, m.uploader_id,
            c.course_id, c.course_code, c.course_title, c.year, c.semester,
            d.department_id, d.name AS department_name, cl.name AS college_name,
            u.name AS uploader_name
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     JOIN departments AS d ON d.department_id = c.department_id
     JOIN colleges AS cl ON cl.college_id = d.college_id
     JOIN users AS u ON u.user_id = m.uploader_id
     WHERE m.material_id = :id'
);
$find->execute(['id' => $materialId]);
$material = $find->fetch();
if (!$material) {
    http_response_code(404);
    exit('Material not found.');
}
$canManage = false;
if ((int) $material['approved'] !== 1) {
    require_once __DIR__ . '/includes/auth.php';
    require_login();
    $canManage = check_role('admin') || (int) $_SESSION['user_id'] === (int) $material['uploader_id'];
    if (!$canManage) {
        http_response_code(404);
        exit('Material not found.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_once __DIR__ . '/includes/auth.php';
    require_login();
    require_csrf();
    $reason = sanitize($_POST['reason'] ?? '');
    if ($reason === '' || mb_strlen($reason) > 1000) {
        set_flash('Enter a report reason of up to 1,000 characters.', 'warning');
    } else {
        $report = $pdo->prepare('INSERT INTO material_reports (user_id, material_id, reason) VALUES (:user_id, :material_id, :reason)');
        $report->execute(['user_id' => $_SESSION['user_id'], 'material_id' => $materialId, 'reason' => $reason]);
        write_activity($pdo, (int) $_SESSION['user_id'], 'material_report', 'Material #' . $materialId);
        set_flash('Thank you. Your report was sent to the library administrator.');
    }
    redirect('material.php?id=' . $materialId);
}

$sameCourse = $pdo->prepare(
    'SELECT m.material_id, m.title, m.file_type, m.file_size, m.download_count, c.course_code, c.course_title
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     WHERE m.course_id = :course_id AND m.material_id <> :material_id AND m.approved = 1
     ORDER BY m.download_count DESC, m.upload_date DESC LIMIT 3'
);
$sameCourse->execute(['course_id' => $material['course_id'], 'material_id' => $materialId]);
$related = $sameCourse->fetchAll();
if (count($related) < 3) {
    $sameDepartment = $pdo->prepare(
        'SELECT m.material_id, m.title, m.file_type, m.file_size, m.download_count, c.course_code, c.course_title
         FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
         WHERE c.department_id = :department_id AND m.material_id <> :material_id
           AND c.course_id <> :course_id AND m.approved = 1
         ORDER BY m.download_count DESC, m.upload_date DESC LIMIT :remaining'
    );
    $sameDepartment->bindValue(':department_id', (int) $material['department_id'], PDO::PARAM_INT);
    $sameDepartment->bindValue(':material_id', (int) $materialId, PDO::PARAM_INT);
    $sameDepartment->bindValue(':course_id', (int) $material['course_id'], PDO::PARAM_INT);
    $sameDepartment->bindValue(':remaining', 3 - count($related), PDO::PARAM_INT);
    $sameDepartment->execute();
    $related = array_merge($related, $sameDepartment->fetchAll());
}
$pageTitle = $material['title'];
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell">
    <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(site_url('browse.php')) ?>">Browse</a></li><li class="breadcrumb-item"><a href="<?= e(site_url('course.php?id=' . (int) $material['course_id'])) ?>"><?= e($material['course_code']) ?></a></li><li class="breadcrumb-item active" aria-current="page">Material</li></ol></nav>
    <?php if ((int) $material['approved'] !== 1): ?><div class="alert alert-warning" role="status">This material is not public. It is visible only to its uploader and administrators.</div><?php endif; ?>
    <article class="content-panel material-detail">
        <div class="d-flex flex-wrap gap-3 align-items-start"><i class="bi <?= e(get_file_icon($material['file_type'])) ?> detail-file-icon" aria-hidden="true"></i><div class="flex-grow-1"><p class="eyebrow mb-2"><?= e($material['course_code'] . ' · ' . $material['department_name']) ?></p><h1 class="page-title"><?= e($material['title']) ?></h1><p class="text-secondary">Uploaded by <?= e($material['uploader_name']) ?> on <?= e(date('F j, Y', strtotime($material['upload_date']))) ?></p></div>
            <button class="bookmark-button" type="button" data-bookmark="<?= (int) $material['material_id'] ?>" aria-label="Save <?= e($material['title']) ?>" aria-pressed="false" title="Save material"><i class="bi bi-bookmark" aria-hidden="true"></i></button></div>
        <?php if (!empty($material['description'])): ?><div class="material-description my-4"><h2 class="h6">Description</h2><p class="mb-0"><?= nl2br(e($material['description'])) ?></p></div><?php endif; ?>
        <dl class="metadata-grid"><div><dt>Course</dt><dd><?= e($material['course_code'] . ' - ' . $material['course_title']) ?></dd></div><div><dt>College</dt><dd><?= e($material['college_name']) ?></dd></div><div><dt>Department</dt><dd><?= e($material['department_name']) ?></dd></div><div><dt>Year / semester</dt><dd><?= (int) $material['year'] ?> / <?= (int) $material['semester'] === 1 ? 'I' : 'II' ?></dd></div><div><dt>File</dt><dd><?= e(strtoupper($material['file_type']) . ' · ' . format_file_size($material['file_size'])) ?></dd></div><div><dt>Downloads</dt><dd><?= (int) $material['download_count'] ?></dd></div></dl>
        <?php if ((int) $material['approved'] === 1): ?><?= download_form((int) $material['material_id'], 'Download material') ?><?php endif; ?>
    </article>
    <?php if ($related !== []): ?><section class="mt-5"><h2 class="section-heading mb-3">Related materials</h2><div class="row g-3"><?php foreach ($related as $materialCard): ?><div class="col-12 col-md-6 col-lg-4"><?php require __DIR__ . '/includes/material_card.php'; ?></div><?php endforeach; ?></div></section><?php endif; ?>
    <?php if (isset($_SESSION['user_id']) && (int) $material['approved'] === 1): ?><details class="content-panel mt-5"><summary class="fw-semibold">Report this material</summary><form method="post" class="mt-3" data-confirm="Send this report to the administrator?"><?= csrf_field() ?><label class="form-label" for="reason">Reason</label><textarea class="form-control mb-3" id="reason" name="reason" rows="3" maxlength="1000" required></textarea><button class="btn btn-outline-danger" type="submit">Submit report</button></form></details><?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>