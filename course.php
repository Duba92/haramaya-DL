<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$courseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($courseId === false || $courseId === null) {
    http_response_code(404);
    exit('Course not found.');
}
$courseQuery = $pdo->prepare(
    'SELECT c.course_id, c.course_code, c.course_title, c.year, c.semester,
            d.name AS department_name, cl.name AS college_name, u.name AS instructor_name
     FROM courses AS c JOIN departments AS d ON d.department_id = c.department_id
     JOIN colleges AS cl ON cl.college_id = d.college_id
     LEFT JOIN users AS u ON u.user_id = c.instructor_id WHERE c.course_id = :id'
);
$courseQuery->execute(['id' => $courseId]);
$course = $courseQuery->fetch();
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}
$query = mb_substr(sanitize($_GET['q'] ?? ''), 0, 100);
$sql = 'SELECT m.material_id, m.title, m.description, m.file_type, m.file_size,
               m.upload_date, m.download_count, u.name AS uploader_name
        FROM materials AS m JOIN users AS u ON u.user_id = m.uploader_id
        WHERE m.course_id = :course_id AND m.approved = 1';
$parameters = ['course_id' => $courseId];
if ($query !== '') {
    $sql .= ' AND (m.title LIKE :title_term OR m.description LIKE :description_term)';
    $parameters['title_term'] = '%' . $query . '%';
    $parameters['description_term'] = '%' . $query . '%';
}
$sql .= ' ORDER BY m.upload_date DESC LIMIT 100';
$materialsQuery = $pdo->prepare($sql);
$materialsQuery->execute($parameters);
$materials = $materialsQuery->fetchAll();
$pageTitle = $course['course_code'] . ' ' . $course['course_title'];
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell">
    <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(site_url('browse.php')) ?>">Browse</a></li><li class="breadcrumb-item"><?= e($course['college_name']) ?></li><li class="breadcrumb-item active" aria-current="page"><?= e($course['course_code']) ?></li></ol></nav>
    <section class="content-panel mb-4"><p class="eyebrow mb-2"><?= e($course['department_name']) ?></p><h1 class="page-title"><?= e($course['course_code']) ?>: <?= e($course['course_title']) ?></h1><p class="text-secondary mb-0">Year <?= (int) $course['year'] ?> · Semester <?= (int) $course['semester'] === 1 ? 'I' : 'II' ?> · Instructor: <?= e($course['instructor_name'] ?? 'Not assigned') ?></p></section>
    <div class="section-topline"><h2 class="section-heading">Approved course materials</h2><span class="text-secondary"><?= count($materials) ?> shown</span></div>
    <form class="mb-3" method="get"><input type="hidden" name="id" value="<?= (int) $course['course_id'] ?>"><label class="visually-hidden" for="course-query">Search course materials</label><div class="input-group"><input class="form-control" id="course-query" name="q" value="<?= e($query) ?>" placeholder="Search within this course"><button class="btn btn-outline-primary" type="submit">Search</button></div></form>
    <div class="d-grid gap-3"><?php foreach ($materials as $material): ?><article class="content-panel material-result"><div class="d-flex gap-3"><i class="bi <?= e(get_file_icon($material['file_type'])) ?> material-icon" aria-hidden="true"></i><div class="flex-grow-1"><h3 class="h5"><a href="<?= e(site_url('material.php?id=' . (int) $material['material_id'])) ?>"><?= e($material['title']) ?></a></h3><p class="text-secondary"><?= e($material['description'] ?? '') ?></p><div class="small text-secondary">By <?= e($material['uploader_name']) ?> · <?= e(strtoupper($material['file_type'])) ?> · <?= e(format_file_size($material['file_size'])) ?> · <?= (int) $material['download_count'] ?> downloads</div></div><?= download_form((int) $material['material_id'], 'Download', 'btn btn-sm btn-primary align-self-start') ?></div></article><?php endforeach; ?>
        <?php if ($materials === []): ?><div class="content-panel text-secondary">No approved materials match this course search yet.</div><?php endif; ?></div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>