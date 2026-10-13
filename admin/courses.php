<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($courseId !== false && $courseId !== null) {
        try {
            $paths = $pdo->prepare('SELECT file_path FROM materials WHERE course_id = :id');
            $paths->execute(['id' => $courseId]);
            $filePaths = $paths->fetchAll(PDO::FETCH_COLUMN);
            $delete = $pdo->prepare('DELETE FROM courses WHERE course_id = :id');
            $delete->execute(['id' => $courseId]);
            foreach ($filePaths as $filePath) {
                delete_stored_file($filePath);
            }
            write_activity($pdo, (int) $_SESSION['user_id'], 'course_delete', 'Course #' . $courseId);
            set_flash('Course and its materials deleted.');
        } catch (PDOException $exception) {
            error_log('Course deletion failed: ' . $exception->getMessage());
            set_flash('The course could not be deleted.', 'danger');
        }
    }
    redirect('admin/courses.php');
}

$search = sanitize($_GET['q'] ?? '');
$statement = $pdo->prepare(
    'SELECT c.course_id, c.course_code, c.course_title, c.year, c.semester,
            d.name AS department, u.name AS instructor
     FROM courses AS c JOIN departments AS d ON d.department_id = c.department_id
     LEFT JOIN users AS u ON u.user_id = c.instructor_id
     WHERE (:search_blank = \'\' OR c.course_code LIKE :code_search OR c.course_title LIKE :title_search)
     ORDER BY d.name, c.year, c.semester, c.course_code LIMIT 300'
);
$term = '%' . $search . '%';
$statement->execute(['search_blank' => $search, 'code_search' => $term, 'title_search' => $term]);
$courses = $statement->fetchAll();
$pageTitle = 'Manage courses';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <div class="page-toolbar"><div><h1 class="page-title">Courses</h1><p class="text-secondary mb-0">Maintain curriculum courses and instructors.</p></div><a class="btn btn-primary" href="<?= e(site_url('admin/course_edit.php')) ?>">Add course</a></div>
    <form class="mb-3" method="get"><label class="visually-hidden" for="q">Search courses</label><div class="input-group"><input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Course code or title"><button class="btn btn-outline-primary" type="submit">Search</button></div></form>
    <div class="table-responsive content-panel p-0"><table class="table align-middle mb-0"><thead><tr><th>Code</th><th>Title</th><th>Department</th><th>Year</th><th>Semester</th><th>Instructor</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($courses as $course): ?><tr><td><?= e($course['course_code']) ?></td><td><?= e($course['course_title']) ?></td><td><?= e($course['department']) ?></td><td><?= (int) $course['year'] ?></td><td><?= (int) $course['semester'] ?></td><td><?= e($course['instructor'] ?? 'Unassigned') ?></td>
            <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(site_url('admin/course_edit.php?id=' . (int) $course['course_id'])) ?>">Edit</a><form method="post" class="d-inline" data-confirm="Delete this course and all of its materials?"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int) $course['course_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
        <?php if ($courses === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">No courses found.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>