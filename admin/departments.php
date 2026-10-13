<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = sanitize($_POST['action'] ?? '');
    try {
        if ($action === 'save_college') {
            $collegeId = filter_var($_POST['college_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $name = sanitize($_POST['college_name'] ?? '');
            if ($name === '' || mb_strlen($name) > 150) {
                throw new InvalidArgumentException('College name is required and must be at most 150 characters.');
            }
            if ($collegeId === false || $collegeId === null || $collegeId === '') {
                $statement = $pdo->prepare('INSERT INTO colleges (name) VALUES (:name)');
                $statement->execute(['name' => $name]);
            } else {
                $statement = $pdo->prepare('UPDATE colleges SET name = :name WHERE college_id = :id');
                $statement->execute(['name' => $name, 'id' => $collegeId]);
            }
        } elseif ($action === 'delete_college') {
            $collegeId = filter_input(INPUT_POST, 'college_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($collegeId === false || $collegeId === null) {
                throw new InvalidArgumentException('Choose a valid college.');
            }
            $pdo->prepare('DELETE FROM colleges WHERE college_id = :id')->execute(['id' => $collegeId]);
        } elseif ($action === 'save_department') {
            $departmentId = filter_var($_POST['department_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $collegeId = filter_var($_POST['department_college_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $name = sanitize($_POST['department_name'] ?? '');
            if ($name === '' || mb_strlen($name) > 150 || !is_int($collegeId)) {
                throw new InvalidArgumentException('Enter a department name and choose a college.');
            }
            if ($departmentId === false || $departmentId === null || $departmentId === '') {
                $statement = $pdo->prepare('INSERT INTO departments (name, college_id) VALUES (:name, :college_id)');
                $statement->execute(['name' => $name, 'college_id' => $collegeId]);
            } else {
                $statement = $pdo->prepare('UPDATE departments SET name = :name, college_id = :college_id WHERE department_id = :id');
                $statement->execute(['name' => $name, 'college_id' => $collegeId, 'id' => $departmentId]);
            }
        } elseif ($action === 'delete_department') {
            $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($departmentId === false || $departmentId === null) {
                throw new InvalidArgumentException('Choose a valid department.');
            }
            $pdo->prepare('DELETE FROM departments WHERE department_id = :id')->execute(['id' => $departmentId]);
        } else {
            throw new InvalidArgumentException('Choose a valid action.');
        }
        write_activity($pdo, (int) $_SESSION['user_id'], 'curriculum_' . $action);
        set_flash('College or department saved.');
    } catch (InvalidArgumentException $exception) {
        set_flash($exception->getMessage(), 'warning');
    } catch (PDOException $exception) {
        error_log('College/department management failed: ' . $exception->getMessage());
        set_flash('This record could not be changed. It may still be used by a user or course.', 'danger');
    }
    redirect('admin/departments.php');
}

$colleges = $pdo->query(
    'SELECT c.college_id, c.name, COUNT(d.department_id) AS department_count
     FROM colleges AS c LEFT JOIN departments AS d ON d.college_id = c.college_id
     GROUP BY c.college_id, c.name ORDER BY c.name'
)->fetchAll();
$departments = $pdo->query(
    'SELECT d.department_id, d.name, d.college_id, c.name AS college_name, COUNT(co.course_id) AS course_count
     FROM departments AS d JOIN colleges AS c ON c.college_id = d.college_id
     LEFT JOIN courses AS co ON co.department_id = d.department_id
     GROUP BY d.department_id, d.name, d.college_id, c.name ORDER BY c.name, d.name'
)->fetchAll();
$pageTitle = 'Manage departments';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell">
    <h1 class="page-title">Colleges and departments</h1>
    <div class="row g-4 mt-1">
        <div class="col-12 col-lg-5"><section class="content-panel"><h2 class="section-heading">Add college</h2>
            <form method="post" class="mb-4"><?= csrf_field() ?><input type="hidden" name="action" value="save_college"><label class="form-label" for="college_name">College name</label><div class="input-group"><input class="form-control" id="college_name" name="college_name" maxlength="150" required><button class="btn btn-primary" type="submit">Add</button></div></form>
            <h3 class="h6">Existing colleges</h3>
            <?php foreach ($colleges as $college): ?>
                <div class="border-top py-2">
                    <form method="post" class="d-flex gap-2 align-items-center mb-2"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_college"><input type="hidden" name="college_id" value="<?= (int) $college['college_id'] ?>">
                        <label class="visually-hidden" for="college-<?= (int) $college['college_id'] ?>">College name</label>
                        <input class="form-control form-control-sm" id="college-<?= (int) $college['college_id'] ?>" name="college_name" maxlength="150" required value="<?= e($college['name']) ?>">
                        <span class="small text-secondary text-nowrap"><?= (int) $college['department_count'] ?> departments</span>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                    </form>
                    <form method="post" data-confirm="Delete this college? It must not contain departments."><?= csrf_field() ?><input type="hidden" name="action" value="delete_college"><input type="hidden" name="college_id" value="<?= (int) $college['college_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete college</button></form>
                </div>
            <?php endforeach; ?>
        </section></div>
        <div class="col-12 col-lg-7"><section class="content-panel"><h2 class="section-heading">Add department</h2>
            <form method="post" class="row g-2 mb-4"><?= csrf_field() ?><input type="hidden" name="action" value="save_department"><div class="col-12 col-md-5"><label class="visually-hidden" for="department_name">Department name</label><input class="form-control" id="department_name" name="department_name" maxlength="150" required placeholder="Department name"></div><div class="col-8 col-md-5"><label class="visually-hidden" for="department_college_id">College</label><select class="form-select" id="department_college_id" name="department_college_id" required><option value="">Choose college</option><?php foreach ($colleges as $college): ?><option value="<?= (int) $college['college_id'] ?>"><?= e($college['name']) ?></option><?php endforeach; ?></select></div><div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Add</button></div></form>
            <h3 class="h6">Existing departments</h3>
            <?php foreach ($departments as $department): ?>
                <div class="border-top py-2">
                    <form method="post" class="row g-2 align-items-center mb-2"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_department"><input type="hidden" name="department_id" value="<?= (int) $department['department_id'] ?>">
                        <div class="col-12 col-md-4"><label class="visually-hidden" for="department-<?= (int) $department['department_id'] ?>">Department name</label><input class="form-control form-control-sm" id="department-<?= (int) $department['department_id'] ?>" name="department_name" maxlength="150" required value="<?= e($department['name']) ?>"></div>
                        <div class="col-7 col-md-4"><label class="visually-hidden" for="college-select-<?= (int) $department['department_id'] ?>">College</label><select class="form-select form-select-sm" id="college-select-<?= (int) $department['department_id'] ?>" name="department_college_id"><?php foreach ($colleges as $college): ?><option value="<?= (int) $college['college_id'] ?>" <?= (int) $college['college_id'] === (int) $department['college_id'] ? 'selected' : '' ?>><?= e($college['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-5 col-md-4 d-flex gap-2"><button class="btn btn-sm btn-outline-primary" type="submit">Save</button><span class="small text-secondary align-self-center"><?= (int) $department['course_count'] ?> courses</span></div>
                    </form>
                    <form method="post" data-confirm="Delete this department? It must not contain courses."><?= csrf_field() ?><input type="hidden" name="action" value="delete_department"><input type="hidden" name="department_id" value="<?= (int) $department['department_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete department</button></form>
                </div>
            <?php endforeach; ?>
        </section></div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>