-- Run this file after importing schema.sql into MySQL/phpMyAdmin.
-- These examples use MySQL session variables so they run as written. In the
-- PHP application, replace them with PDO prepared-statement parameters.

-- Search approved materials by title, description, course code, or course title.
SET @keyword = '%database%';

SELECT m.material_id, m.title, m.description, c.course_code, c.course_title,
       m.file_type, m.file_size, m.download_count, m.upload_date
FROM materials AS m
JOIN courses AS c ON c.course_id = m.course_id
WHERE m.approved = 1
  AND (m.title LIKE @keyword
       OR m.description LIKE @keyword
       OR c.course_code LIKE @keyword
       OR c.course_title LIKE @keyword)
ORDER BY m.upload_date DESC;

-- Filter approved materials. Leave a variable NULL to skip that filter.
SET @college_id = NULL;
SET @department_id = 1;
SET @course_year = NULL;
SET @semester = NULL;
SET @course_id = NULL;
SET @file_type = NULL;

SELECT m.material_id, m.title, c.course_code, c.course_title,
       d.name AS department_name, cl.name AS college_name,
       c.year, c.semester, m.file_type, m.file_size, m.download_count,
       m.upload_date
FROM materials AS m
JOIN courses AS c ON c.course_id = m.course_id
JOIN departments AS d ON d.department_id = c.department_id
JOIN colleges AS cl ON cl.college_id = d.college_id
WHERE m.approved = 1
  AND (@college_id IS NULL OR cl.college_id = @college_id)
  AND (@department_id IS NULL OR d.department_id = @department_id)
  AND (@course_year IS NULL OR c.year = @course_year)
  AND (@semester IS NULL OR c.semester = @semester)
  AND (@course_id IS NULL OR c.course_id = @course_id)
  AND (@file_type IS NULL OR m.file_type = @file_type)
ORDER BY m.upload_date DESC;

-- List approved materials for one course; change the course ID as needed.
SET @course_id = 1;

SELECT m.material_id, m.title, m.description, m.file_type, m.file_size,
       m.upload_date, m.download_count, u.name AS instructor_name
FROM materials AS m
JOIN courses AS c ON c.course_id = m.course_id
JOIN users AS u ON u.user_id = m.uploader_id
WHERE m.course_id = @course_id
  AND m.approved = 1
ORDER BY m.upload_date DESC;

-- Count download events for one material; change the material ID as needed.
SET @material_id = 1;

SELECT COUNT(*) AS total_downloads
FROM downloads
WHERE material_id = @material_id;

-- Show the 10 most recent approved uploads.
SELECT m.material_id, m.title, c.course_code, c.course_title,
       m.file_type, m.file_size, m.upload_date
FROM materials AS m
JOIN courses AS c ON c.course_id = m.course_id
WHERE m.approved = 1
ORDER BY m.upload_date DESC
LIMIT 10;