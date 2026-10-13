<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$collegeId = filter_input(INPUT_GET, 'college_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$sql =
    'SELECT cl.college_id, cl.name AS college_name,
            d.department_id, d.name AS department_name,
            c.course_id, c.course_code, c.course_title, c.year, c.semester,
            COUNT(m.material_id) AS material_count
     FROM colleges AS cl LEFT JOIN departments AS d ON d.college_id = cl.college_id
     LEFT JOIN courses AS c ON c.department_id = d.department_id
     LEFT JOIN materials AS m ON m.course_id = c.course_id AND m.approved = 1';
if ($collegeId && $collegeId !== false) {
    $sql .= ' WHERE cl.college_id = :college_id';
}
$sql .= ' GROUP BY cl.college_id, cl.name, d.department_id, d.name, c.course_id,
          c.course_code, c.course_title, c.year, c.semester
          ORDER BY cl.name, d.name, c.year, c.semester, c.course_code';
$statement = $pdo->prepare($sql);
$statement->execute($collegeId && $collegeId !== false ? ['college_id' => $collegeId] : []);
$rows = $statement->fetchAll();
$hierarchy = [];
foreach ($rows as $row) {
    $collegeKey = (int) $row['college_id'];
    $hierarchy[$collegeKey]['name'] = $row['college_name'];
    if ($row['department_id'] === null) {
        continue;
    }
    $departmentKey = (int) $row['department_id'];
    $hierarchy[$collegeKey]['departments'][$departmentKey]['name'] = $row['department_name'];
    if ($row['course_id'] !== null) {
        $year = (int) $row['year'];
        $semester = (int) $row['semester'];
        $hierarchy[$collegeKey]['departments'][$departmentKey]['courses'][$year][$semester][] = $row;
    }
}
$pageTitle = 'Browse curriculum';
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell">
    <h1 class="page-title">Browse the curriculum</h1>
    <p class="text-secondary">Explore learning materials by college, department, year, semester, and course.</p>
    <div class="curriculum-tree mt-4">
        <?php foreach ($hierarchy as $collegeKey => $college): ?><details class="tree-level college-level" <?= $collegeId && (int) $collegeId === $collegeKey ? 'open' : '' ?>><summary><span><?= e($college['name']) ?></span><span class="tree-count"><?= count($college['departments'] ?? []) ?> departments</span></summary>
            <?php foreach (($college['departments'] ?? []) as $departmentKey => $department): ?><details class="tree-level department-level"><summary><span><?= e($department['name']) ?></span><span class="tree-count"><?= count($department['courses'] ?? []) ?> curriculum years</span></summary>
                <?php foreach (($department['courses'] ?? []) as $year => $semesters): ?><details class="tree-level year-level"><summary>Year <?= (int) $year ?></summary>
                    <?php foreach ($semesters as $semester => $courses): ?><details class="tree-level semester-level"><summary>Semester <?= $semester === 1 ? 'I' : 'II' ?></summary>
                        <ul class="course-list"><?php foreach ($courses as $course): ?><li><a href="<?= e(site_url('course.php?id=' . (int) $course['course_id'])) ?>"><span><strong><?= e($course['course_code']) ?></strong> <?= e($course['course_title']) ?></span><span class="tree-count"><?= (int) $course['material_count'] ?> materials</span></a></li><?php endforeach; ?></ul>
                    </details><?php endforeach; ?>
                </details><?php endforeach; ?>
            </details><?php endforeach; ?>
        </details><?php endforeach; ?>
        <?php if ($hierarchy === []): ?><p class="text-secondary">No colleges or departments have been added yet.</p><?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>