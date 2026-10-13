-- Haramaya University Digital Library database
-- Compatible with MySQL 8+ and current XAMPP MySQL/MariaDB releases.

CREATE DATABASE IF NOT EXISTS digital_library
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE digital_library;

-- Colleges contain departments; departments organize courses and users.
CREATE TABLE IF NOT EXISTS colleges (
    college_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    PRIMARY KEY (college_id),
    UNIQUE KEY uq_colleges_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    department_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    college_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (department_id),
    UNIQUE KEY uq_departments_college_name (college_id, name),
    CONSTRAINT fk_departments_college
        FOREIGN KEY (college_id) REFERENCES colleges (college_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
    department_id SMALLINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_department (role, department_id),
    KEY idx_users_active_role (is_active, role),
    CONSTRAINT chk_users_is_active CHECK (is_active IN (0, 1)),
    CONSTRAINT fk_users_department
        FOREIGN KEY (department_id) REFERENCES departments (department_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
    course_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_code VARCHAR(30) NOT NULL,
    course_title VARCHAR(180) NOT NULL,
    department_id SMALLINT UNSIGNED NOT NULL,
    year TINYINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    instructor_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (course_id),
    UNIQUE KEY uq_courses_department_code (department_id, course_code),
    KEY idx_courses_curriculum (department_id, year, semester),
    KEY idx_courses_instructor (instructor_id),
    CONSTRAINT chk_courses_year CHECK (year BETWEEN 1 AND 6),
    CONSTRAINT chk_courses_semester CHECK (semester IN (1, 2)),
    CONSTRAINT fk_courses_department
        FOREIGN KEY (department_id) REFERENCES departments (department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_courses_instructor
        FOREIGN KEY (instructor_id) REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materials (
    material_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(10) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    uploader_id BIGINT UNSIGNED NOT NULL,
    upload_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- NULL = pending review, 1 = approved, 0 = rejected.
    approved TINYINT(1) NULL DEFAULT NULL,
    download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (material_id),
    UNIQUE KEY uq_materials_file_path (file_path),
    KEY idx_materials_course_approval_date (course_id, approved, upload_date),
    KEY idx_materials_approval_date (approved, upload_date),
    KEY idx_materials_download_count (download_count),
    KEY idx_materials_uploader (uploader_id),
    CONSTRAINT chk_materials_approved CHECK (approved IS NULL OR approved IN (0, 1)),
    CONSTRAINT fk_materials_course
        FOREIGN KEY (course_id) REFERENCES courses (course_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_materials_uploader
        FOREIGN KEY (uploader_id) REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS downloads (
    download_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    download_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (download_id),
    KEY idx_downloads_user_date (user_id, download_date),
    KEY idx_downloads_material_date (material_id, download_date),
    CONSTRAINT fk_downloads_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_downloads_material
        FOREIGN KEY (material_id) REFERENCES materials (material_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS material_reports (
    report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('open', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    KEY idx_material_reports_status_date (status, created_at),
    KEY idx_material_reports_material (material_id),
    CONSTRAINT fk_material_reports_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_material_reports_material
        FOREIGN KEY (material_id) REFERENCES materials (material_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_id),
    KEY idx_activity_created (created_at),
    CONSTRAINT fk_activity_actor
        FOREIGN KEY (actor_id) REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_title', 'Haramaya University Digital Library'),
    ('site_description', 'Curriculum-aligned learning materials for Haramaya University.'),
    ('site_logo', ''),
    ('maintenance_mode', '0'),
    ('allowed_file_types', 'pdf,docx,pptx,mp4'),
    ('max_file_size', '52428800'),
    ('support_email', '')
ON DUPLICATE KEY UPDATE setting_value = site_settings.setting_value;

-- Sample colleges: 2 rows.
INSERT INTO colleges (college_id, name) VALUES
    (1, 'College of Computing and Informatics'),
    (2, 'College of Agriculture and Environmental Sciences')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Sample departments: 4 rows, 2 in each college.
INSERT INTO departments (department_id, name, college_id) VALUES
    (1, 'Information Science', 1),
    (2, 'Computer Science', 1),
    (3, 'Plant Sciences', 2),
    (4, 'Agricultural Economics', 2)
ON DUPLICATE KEY UPDATE name = VALUES(name), college_id = VALUES(college_id);

-- Test accounts use the listed passwords; change them before any deployment.
-- Student123! / Instructor123! / Admin123!
INSERT INTO users (user_id, name, email, password, role, department_id) VALUES
    (1, 'Sample Student', 'student@example.test',
     '$2y$12$jioh2SGlycLLEjDg5WfWKuEUagW5fPcr3OylZrugD3WLuUBBAr0qG', 'student', 1),
    (2, 'Sample Instructor', 'instructor@example.test',
     '$2y$12$SXdsydCbI/9iFgHJESwvle0R8NYPXlwk3mhb.sxv10W3eJEXFkSue', 'instructor', 1),
    (3, 'Sample Administrator', 'admin@example.test',
     '$2y$12$1T2kMUl25x67llnrrNcFSO.KU7h33a9fJR8x7C58Ea1TkizxHZk3O', 'admin', NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), role = VALUES(role), department_id = VALUES(department_id);

-- Sample courses: 8 rows spanning both colleges and both semesters.
INSERT INTO courses (course_id, course_code, course_title, department_id, year, semester, instructor_id) VALUES
    (1, 'IS101', 'Introduction to Information Science', 1, 1, 1, 2),
    (2, 'IS102', 'Fundamentals of Information Systems', 1, 1, 2, 2),
    (3, 'CS101', 'Introduction to Computer Science', 2, 1, 1, NULL),
    (4, 'CS202', 'Database Systems', 2, 2, 2, NULL),
    (5, 'PS101', 'Principles of Crop Production', 3, 1, 1, NULL),
    (6, 'PS202', 'Soil and Water Management', 3, 2, 2, NULL),
    (7, 'AE101', 'Introduction to Agricultural Economics', 4, 1, 1, NULL),
    (8, 'AE202', 'Agricultural Marketing', 4, 2, 2, NULL)
ON DUPLICATE KEY UPDATE
    course_title = VALUES(course_title),
    department_id = VALUES(department_id),
    year = VALUES(year),
    semester = VALUES(semester),
    instructor_id = VALUES(instructor_id);

-- These are metadata examples; the referenced files are not included.
-- approved: NULL = pending, 1 = approved, 0 = rejected.
INSERT INTO materials
    (material_id, title, description, file_path, file_type, file_size, course_id, uploader_id, approved, download_count)
VALUES
    (1, 'Information Science Lecture Notes', 'Introductory lecture notes for IS101.',
     'uploads/materials/sample-is101-notes.pdf', 'pdf', 1048576, 1, 2, 1, 2),
    (2, 'IS101 Course Presentation', 'Slides covering information organization.',
     'uploads/materials/sample-is101-slides.pptx', 'pptx', 2097152, 1, 2, NULL, 0),
    (3, 'IS102 Reading Guide', 'A reading guide awaiting a new review decision.',
     'uploads/materials/sample-is102-guide.docx', 'docx', 524288, 2, 2, 0, 0),
    (4, 'Introduction to Information Systems', 'Approved course reference document.',
     'uploads/materials/sample-is102-reference.pdf', 'pdf', 1572864, 2, 2, 1, 1),
    (5, 'IS101 Tutorial Recording', 'Short recorded tutorial for the sample course.',
     'uploads/materials/sample-is101-tutorial.mp4', 'mp4', 10485760, 1, 2, 1, 0)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    file_path = VALUES(file_path),
    file_type = VALUES(file_type),
    file_size = VALUES(file_size),
    course_id = VALUES(course_id),
    uploader_id = VALUES(uploader_id),
    approved = VALUES(approved),
    download_count = VALUES(download_count);

-- Sample download events keep the event history aligned with download_count.
INSERT INTO downloads (download_id, user_id, material_id, download_date) VALUES
    (1, 1, 1, '2026-09-01 10:00:00'),
    (2, 1, 1, '2026-09-02 11:00:00'),
    (3, 1, 4, '2026-09-03 12:00:00')
ON DUPLICATE KEY UPDATE
        user_id = VALUES(user_id), material_id = VALUES(material_id), download_date = VALUES(download_date);