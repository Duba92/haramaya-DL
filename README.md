# Haramaya University Digital Library

A curriculum-aligned digital library for organizing and sharing course materials
at Haramaya University.

## Project structure

```text
haramaya-DL/
|-- admin/                    Admin dashboard and management pages
|-- api/                      JSON endpoints for filters and suggestions
|-- assets/
|   |-- css/                  Application stylesheets
|   |-- images/               Logos and other static images
|   `-- js/                   Browser-side JavaScript
|-- database/                 SQL schema and sample data
|-- docker/                   PHP runtime and upload settings for Codespaces
|-- docs/                     Deployment, user, architecture, and test guides
|-- includes/                 Shared PHP configuration, helpers, and layout
|-- instructor/               Instructor dashboard and upload pages
|-- index.php                 Public homepage
|-- about.php                 About page
|-- browse.php                Curriculum hierarchy browser
|-- contact.php               Library contact information
|-- compose.yaml              Codespaces PHP/MySQL development stack
|-- course.php                Course details and materials
|-- download.php              Authenticated material download handler
|-- help.php                  Student and instructor help
|-- login.php                 User sign-in
|-- logout.php                CSRF-protected sign-out
|-- logs/                     Application logs; never commit generated logs
|-- material.php              Material detail and reporting page
|-- search.php                Search and filter approved materials
|-- register.php              Student and instructor registration
|-- tests/                    Lightweight helper tests
|-- student/                  Student dashboard and library pages
|-- uploads/
|   `-- materials/            Uploaded course files; direct web access denied
|-- .gitignore                Local configuration and generated-file exclusions
`-- README.md                 Setup notes and project documentation
```

### Folder responsibilities

- `admin/` contains administrator dashboards and management pages for users, courses, curriculum, settings, materials, and reports.
- `api/` contains JSON endpoints for dependent filters, search suggestions, and saved-material metadata.
- `assets/css/`, `assets/js/`, and `assets/images/` hold browser styles, scripts, and local images.
- `database/` holds the MySQL schema, sample data, and separate query examples.
- `docs/` contains deployment instructions, role manuals, architecture notes, and test plans.
- `docker/` contains the Codespaces PHP image and upload limits.
- `includes/` holds shared PHP configuration, authentication, helpers, and page templates.
- `instructor/` contains instructor dashboards and material upload/edit/list pages.
- `logs/` is reserved for private application logs; generated logs are excluded from Git.
- `student/` contains the student dashboard, recommendations, recent downloads, and saved materials.
- `tests/` contains database-independent helper tests; MySQL integration cases are listed in `docs/testing.md`.
- `uploads/materials/` stores uploaded files. Apache denies direct requests; downloads go through the authenticated PHP handler.

## Development environment

The project targets PHP 8+, MySQL, and Apache, and can be developed with XAMPP.
Place the project directory under XAMPP's `htdocs` directory.

For this Codespaces workspace, start the included PHP/MySQL development stack
with `docker compose up --build -d`, then open the forwarded port 8000. The
database is initialized from `database/schema.sql` on its first start. Stop the
services with `docker compose down`; `docker compose down -v` also deletes the
development database, uploaded files, and logs.

## Database setup

Import [`database/schema.sql`](database/schema.sql) using phpMyAdmin or the MySQL
command-line client. The script creates the `digital_library` database, core
tables plus moderation, report, settings, and activity support, indexes, and
foreign keys, then inserts sample colleges, departments, courses, accounts,
materials, and download events. Run
[`database/queries.sql`](database/queries.sql) separately to try the keyword
search, filters, course lookup, download count, and recent-upload queries.

In XAMPP, start MySQL, open phpMyAdmin, select **Import**, choose `schema.sql`,
and run the import. Alternatively, from a terminal with the MySQL client, run
`mysql -u root -p < database/schema.sql`. To check the seeded rows, run
`SELECT 'colleges' AS table_name, COUNT(*) AS total FROM digital_library.colleges;`
and repeat for the other tables; expected counts are 2 colleges, 4 departments,
3 users, 8 courses, 5 materials, and 3 download events. Then import/run
`queries.sql` separately to exercise the example retrieval queries.

If import fails, confirm the MySQL service is running and that the SQL file was
imported into the MySQL server (the script selects `digital_library` itself).
When testing application downloads later, remember that the sample material
paths are metadata only; matching files are not included.

The sample accounts use `student@example.test` / `Student123!`,
`instructor@example.test` / `Instructor123!`, and `admin@example.test` /
`Admin123!`. These are development-only credentials. Change or remove them
before deployment. The five sample material paths are metadata only; matching
files are not included, so downloads of those records will not work until test
files are added.

For production, keep secrets outside version control, store uploaded files
outside the public document root where possible, and serve the site over HTTPS.

## Setup and documentation

See [docs/deployment.md](docs/deployment.md) for XAMPP installation, local
configuration, PHP extensions, upload permissions, and production HTTPS notes.
Role workflows are in [docs/user-manual.md](docs/user-manual.md), system design
and API details are in [docs/architecture.md](docs/architecture.md), and
[docs/testing.md](docs/testing.md) contains functional/security checks and a
student usability-study plan. Run the database-independent helper checks with
`php tests/run.php`.
