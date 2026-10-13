<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$courseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$courseId = $courseId === false ? null : $courseId;
$course = ['course_code' => '', 'course_title' => '', 'department_id' => '', 'year' => '1', 'semester' => '1', 'instructor_id' => ''];
if ($courseId !== null) {
    $find = $pdo->prepare('SELECT course_code, course_title, department_id, year, semester, instructor_id FROM courses WHERE course_id = :id');
    $find->execute(['id' => $courseId]);
    $existing = $find->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Course not found.');
    }
    $course = $existing;
}
$departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $course['course_code'] = strtoupper(sanitize($_POST['course_code'] ?? ''));
    $course['course_title'] = sanitize($_POST['course_title'] ?? '');
    $course['department_id'] = filter_var($_POST['department_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $course['year'] = filter_var($_POST['year'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
    $course['semester'] = filter_var($_POST['semester'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2]]);
    $instructorInput = sanitize($_POST['instructor_id'] ?? '');
    $course['instructor_id'] = $instructorInput === '' ? null : filter_var($instructorInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($course['course_code'] === '' || strlen($course['course_code']) > 30 || $course['course_title'] === '' || mb_strlen($course['course_title']) > 180) {
        $errors[] = 'Enter a course code and title within their length limits.';
    }
    if (!is_int($course['department_id']) || !is_int($course['year']) || !is_int($course['semester'])) {
        $errors[] = 'Choose a valid department, year, and semester.';
    }
    if ($course['instructor_id'] !== null && !is_int($course['instructor_id'])) {
        $errors[] = 'Choose a valid instructor or leave the course unassigned.';
    }
    if ($errors === []) {
        $departmentExists = $pdo->prepare('SELECT 1 FROM departments WHERE department_id = :id');
        $departmentExists->execute(['id' => $course['department_id']]);
        if (!$departmentExists->fetchColumn()) {
            $errors[] = 'Choose an existing department.';
        }
        if ($course['instructor_id'] !== null) {
            $instructorExists = $pdo->prepare('SELECT 1 FROM users WHERE user_id = :id AND role = \'instructor\' AND department_id = :department_id');
            $instructorExists->execute(['id' => $course['instructor_id'], 'department_id' => $course['department_id']]);
            if (!$instructorExists->fetchColumn()) {
                $errors[] = 'The instructor must belong to the selected department.';
            }
        }
    }
    if ($errors === []) {
        try {
            $parameters = [
                'course_code' => $course['course_code'], 'course_title' => $course['course_title'],
                'department_id' => $course['department_id'], 'year' => $course['year'],
                'semester' => $course['semester'], 'instructor_id' => $course['instructor_id'],
            ];
            if ($courseId) {
                $parameters['id'] = $courseId;
                $sql = 'UPDATE courses SET course_code = :course_code, course_title = :course_title, department_id = :department_id, year = :year, semester = :semester, instructor_id = :instructor_id WHERE course_id = :id';
            } else {
                $sql = 'INSERT INTO courses (course_code, course_title, department_id, year, semester, instructor_id) VALUES (:course_code, :course_title, :department_id, :year, :semester, :instructor_id)';
            }
            $pdo->prepare($sql)->execute($parameters);
            write_activity($pdo, (int) $_SESSION['user_id'], $courseId ? 'course_edit' : 'course_create', $course['course_code']);
            set_flash('Course saved.');
            redirect('admin/courses.php');
        } catch (PDOException $exception) {
            error_log('Course save failed: ' . $exception->getMessage());
            $errors[] = 'The course could not be saved. Check for a duplicate course code in the department.';
        }
    }
}
$instructors = $pdo->query(
    "SELECT u.user_id, u.name, d.name AS department_name
     FROM users AS u LEFT JOIN departments AS d ON d.department_id = u.department_id
     WHERE u.role = 'instructor' ORDER BY u.name"
)->fetchAll();
$pageTitle = $courseId ? 'Edit course' : 'Add course';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="form-panel">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="course_code">Course code</label><input class="form-control" id="course_code" name="course_code" maxlength="30" required value="<?= e($course['course_code']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="course_title">Course title</label><input class="form-control" id="course_title" name="course_title" maxlength="180" required value="<?= e($course['course_title']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="department_id">Department</label><select class="form-select" id="department_id" name="department_id" required><option value="">Choose department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['department_id'] ?>" <?= (string) $department['department_id'] === (string) $course['department_id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
        <div class="row"><div class="col mb-3"><label class="form-label" for="year">Year</label><select class="form-select" id="year" name="year"><?php for ($year = 1; $year <= 6; $year++): ?><option value="<?= $year ?>" <?= (int) $course['year'] === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endfor; ?></select></div><div class="col mb-3"><label class="form-label" for="semester">Semester</label><select class="form-select" id="semester" name="semester"><option value="1" <?= (int) $course['semester'] === 1 ? 'selected' : '' ?>>Semester I</option><option value="2" <?= (int) $course['semester'] === 2 ? 'selected' : '' ?>>Semester II</option></select></div></div>
        <div class="mb-4"><label class="form-label" for="instructor_id">Instructor</label><select class="form-select" id="instructor_id" name="instructor_id"><option value="">Unassigned</option><?php foreach ($instructors as $instructor): ?><option value="<?= (int) $instructor['user_id'] ?>" <?= (string) $instructor['user_id'] === (string) $course['instructor_id'] ? 'selected' : '' ?>><?= e($instructor['name'] . ' - ' . ($instructor['department_name'] ?? 'No department')) ?></option><?php endforeach; ?></select><div class="form-text">The selected instructor must belong to this course's department.</div></div>
        <button class="btn btn-primary" type="submit">Save course</button> <a class="btn btn-outline-secondary" href="<?= e(site_url('admin/courses.php')) ?>">Cancel</a>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>