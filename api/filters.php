<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$type = sanitize($_GET['type'] ?? '');
$items = [];
try {
    if ($type === 'departments') {
        $collegeId = filter_input(INPUT_GET, 'college_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($collegeId) {
            $statement = $pdo->prepare('SELECT department_id AS id, name AS label FROM departments WHERE college_id = :id ORDER BY name');
            $statement->execute(['id' => $collegeId]);
            $items = $statement->fetchAll();
        }
    } elseif (in_array($type, ['years', 'semesters', 'courses'], true)) {
        $departmentId = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
        $semester = filter_input(INPUT_GET, 'semester', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2]]);
        if ($departmentId) {
            if ($type === 'years') {
                $statement = $pdo->prepare("SELECT DISTINCT year AS id, CONCAT('Year ', year) AS label FROM courses WHERE department_id = :department_id ORDER BY year");
                $statement->execute(['department_id' => $departmentId]);
                $items = $statement->fetchAll();
            } elseif ($type === 'semesters' && $year) {
                $statement = $pdo->prepare("SELECT DISTINCT semester AS id, CONCAT('Semester ', semester) AS label FROM courses WHERE department_id = :department_id AND year = :year ORDER BY semester");
                $statement->execute(['department_id' => $departmentId, 'year' => $year]);
                $items = $statement->fetchAll();
            } elseif ($type === 'courses' && $year && $semester) {
                $statement = $pdo->prepare("SELECT course_id AS id, CONCAT(course_code, ' - ', course_title) AS label FROM courses WHERE department_id = :department_id AND year = :year AND semester = :semester ORDER BY course_code");
                $statement->execute(['department_id' => $departmentId, 'year' => $year, 'semester' => $semester]);
                $items = $statement->fetchAll();
            }
        }
    }
    echo json_encode(['items' => $items], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Filter lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Filters are temporarily unavailable.']);
}