<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$query = mb_substr(sanitize($_GET['q'] ?? ''), 0, 100);
$sort = sanitize($_GET['sort'] ?? 'relevance');
$page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
$perPage = 10;
$readId = static function (string $key, int $maximum): ?int {
    $value = filter_var($_GET[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);
    return $value === false ? null : $value;
};
$collegeId = $readId('college_id', PHP_INT_MAX);
$departmentId = $readId('department_id', PHP_INT_MAX);
$year = $readId('year', 6);
$semester = $readId('semester', 2);
$courseId = $readId('course_id', PHP_INT_MAX);
$fileType = sanitize($_GET['file_type'] ?? '');
if (!array_key_exists($fileType, ALLOWED_TYPES)) {
    $fileType = '';
}

$where = ['m.approved = 1'];
$parameters = [];
if ($query !== '') {
    $where[] = '(m.title LIKE :title_term OR m.description LIKE :description_term OR c.course_code LIKE :code_term OR c.course_title LIKE :course_term)';
    $pattern = '%' . $query . '%';
    $parameters += ['title_term' => $pattern, 'description_term' => $pattern, 'code_term' => $pattern, 'course_term' => $pattern];
}
foreach ([
    'college_id' => ['cl.college_id', $collegeId],
    'department_id' => ['d.department_id', $departmentId],
    'year' => ['c.year', $year],
    'semester' => ['c.semester', $semester],
    'course_id' => ['c.course_id', $courseId],
] as $key => [$column, $value]) {
    if ($value !== null) {
        $where[] = $column . ' = :' . $key;
        $parameters[$key] = $value;
    }
}
if ($fileType !== '') {
    $where[] = 'm.file_type = :file_type';
    $parameters['file_type'] = $fileType;
}
$whereSql = implode(' AND ', $where);
$joins = ' FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
           JOIN departments AS d ON d.department_id = c.department_id
           JOIN colleges AS cl ON cl.college_id = d.college_id
           JOIN users AS u ON u.user_id = m.uploader_id';
$count = $pdo->prepare('SELECT COUNT(*)' . $joins . ' WHERE ' . $whereSql);
$count->execute($parameters);
$totalResults = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$orderBy = match ($sort) {
    'date' => 'm.upload_date DESC, m.material_id DESC',
    'downloads' => 'm.download_count DESC, m.upload_date DESC',
    'title' => 'm.title ASC',
    default => $query === '' ? 'm.upload_date DESC' : 'CASE WHEN m.title LIKE :relevance_title THEN 0 WHEN c.course_code LIKE :relevance_code THEN 1 ELSE 2 END, m.download_count DESC, m.upload_date DESC',
};
if ($sort === 'relevance' && $query !== '') {
    $parameters['relevance_title'] = '%' . $query . '%';
    $parameters['relevance_code'] = '%' . $query . '%';
}
$results = $pdo->prepare(
    'SELECT m.material_id, m.title, m.description, m.file_type, m.file_size,
            m.upload_date, m.download_count, c.course_code, c.course_title,
            d.name AS department_name, u.name AS instructor_name' . $joins . '
     WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $results->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$results->bindValue(':limit', $perPage, PDO::PARAM_INT);
$results->bindValue(':offset', $offset, PDO::PARAM_INT);
$results->execute();
$materials = $results->fetchAll();

$colleges = $pdo->query('SELECT college_id, name FROM colleges ORDER BY name')->fetchAll();
$departments = $collegeId ? $pdo->prepare('SELECT department_id, name FROM departments WHERE college_id = :id ORDER BY name') : null;
if ($departments) {
    $departments->execute(['id' => $collegeId]);
    $departments = $departments->fetchAll();
} else {
    $departments = [];
}
$years = [];
$semesters = [];
$courses = [];
if ($departmentId) {
    $optionQuery = $pdo->prepare('SELECT DISTINCT year FROM courses WHERE department_id = :id ORDER BY year');
    $optionQuery->execute(['id' => $departmentId]);
    $years = $optionQuery->fetchAll(PDO::FETCH_COLUMN);
    if ($year) {
        $optionQuery = $pdo->prepare('SELECT DISTINCT semester FROM courses WHERE department_id = :id AND year = :year ORDER BY semester');
        $optionQuery->execute(['id' => $departmentId, 'year' => $year]);
        $semesters = $optionQuery->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($year && $semester) {
        $optionQuery = $pdo->prepare('SELECT course_id, course_code, course_title FROM courses WHERE department_id = :id AND year = :year AND semester = :semester ORDER BY course_code');
        $optionQuery->execute(['id' => $departmentId, 'year' => $year, 'semester' => $semester]);
        $courses = $optionQuery->fetchAll();
    }
}
$pageTitle = 'Search materials';
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell">
    <h1 class="page-title">Search the library</h1>
    <form class="content-panel mb-4" method="get" action="<?= e(site_url('search.php')) ?>" data-filter-form data-api-base="<?= e(site_url('api/filters.php')) ?>">
        <div class="row g-3"><div class="col-12"><label class="form-label" for="q">Keyword</label><input class="form-control" id="q" name="q" type="search" value="<?= e($query) ?>" placeholder="Title, course code, or description"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="filter-college">College</label><select class="form-select" id="filter-college" name="college_id" data-filter-select="college"><option value="">All colleges</option><?php foreach ($colleges as $college): ?><option value="<?= (int) $college['college_id'] ?>" <?= $collegeId === (int) $college['college_id'] ? 'selected' : '' ?>><?= e($college['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="filter-department">Department</label><select class="form-select" id="filter-department" name="department_id" data-filter-select="department"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['department_id'] ?>" <?= $departmentId === (int) $department['department_id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label" for="filter-year">Year</label><select class="form-select" id="filter-year" name="year" data-filter-select="year"><option value="">All years</option><?php foreach ($years as $option): ?><option value="<?= (int) $option ?>" <?= $year === (int) $option ? 'selected' : '' ?>>Year <?= (int) $option ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label" for="filter-semester">Semester</label><select class="form-select" id="filter-semester" name="semester" data-filter-select="semester"><option value="">All semesters</option><?php foreach ($semesters as $option): ?><option value="<?= (int) $option ?>" <?= $semester === (int) $option ? 'selected' : '' ?>>Semester <?= (int) $option ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="filter-course">Course</label><select class="form-select" id="filter-course" name="course_id" data-filter-select="course"><option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>" <?= $courseId === (int) $course['course_id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' - ' . $course['course_title']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="file_type">File type</label><select class="form-select" id="file_type" name="file_type"><option value="">Any type</option><?php foreach (array_keys(ALLOWED_TYPES) as $type): ?><option value="<?= e($type) ?>" <?= $fileType === $type ? 'selected' : '' ?>><?= e(strtoupper($type)) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="sort">Sort by</label><select class="form-select" id="sort" name="sort"><option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Relevance</option><option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Upload date</option><option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Downloads</option><option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option></select></div>
        </div><button class="btn btn-primary mt-3" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
    </form>
    <div class="d-flex justify-content-between align-items-center mb-3"><p class="mb-0 text-secondary" aria-live="polite"><?= number_format($totalResults) ?> materials found</p></div>
    <div class="search-results d-grid gap-3">
        <?php foreach ($materials as $material): ?><article class="material-result content-panel"><div class="d-flex gap-3"><i class="bi <?= e(get_file_icon($material['file_type'])) ?> material-icon" aria-hidden="true"></i><div class="flex-grow-1 min-w-0"><h2 class="h5 mb-1"><a href="<?= e(site_url('material.php?id=' . (int) $material['material_id'])) ?>"><?= e($material['title']) ?></a></h2><p class="mb-2 text-secondary"><?= e($material['course_code'] . ' - ' . $material['course_title']) ?> · <?= e($material['department_name']) ?></p><p class="result-description mb-2"><?= e($material['description'] ?? '') ?></p><div class="small text-secondary">Uploaded by <?= e($material['instructor_name']) ?> · <?= e(strtoupper($material['file_type'])) ?> · <?= e(format_file_size($material['file_size'])) ?> · <?= (int) $material['download_count'] ?> downloads · <?= e(date('M j, Y', strtotime($material['upload_date']))) ?></div></div><div class="d-flex flex-column gap-2 align-items-end"><button class="bookmark-button" type="button" data-bookmark="<?= (int) $material['material_id'] ?>" aria-label="Save <?= e($material['title']) ?>" aria-pressed="false"><i class="bi bi-bookmark" aria-hidden="true"></i></button><?= download_form((int) $material['material_id'], 'Download', 'btn btn-sm btn-primary') ?></div></div></article><?php endforeach; ?>
        <?php if ($materials === []): ?><section class="content-panel text-center py-5"><h2 class="h5">No matching materials</h2><p class="text-secondary">Try a broader keyword, clear one or more filters, or browse by college.</p><a class="btn btn-outline-primary" href="<?= e(site_url('browse.php')) ?>">Browse curriculum</a></section><?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?><nav class="mt-4" aria-label="Search result pages"><ul class="pagination justify-content-center"><?php for ($number = 1; $number <= $totalPages; $number++): $pageQuery = $_GET; $pageQuery['page'] = $number; ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(site_url('search.php?' . http_build_query($pageQuery))) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>