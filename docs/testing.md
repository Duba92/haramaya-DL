# Test Plan and Usability Study

## Run the local checks

From the project root, run:

```sh
php tests/run.php
find . -type f -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

The helper test does not require MySQL. To test database-backed behavior, import `database/schema.sql` into a disposable MySQL/XAMPP database and use the sample accounts in the root README. Run `database/queries.sql` separately.

## Functional test cases

| Area | Test | Expected result |
| --- | --- | --- |
| Registration | Register a student and an instructor with valid values | Account is created with a password hash and selected department; success redirects to login |
| Registration | Invalid email, weak/mismatched password, missing department, duplicate email, or submitted `admin` role | Request is rejected with field-level feedback; no account is added |
| Login | Sign in as student, instructor, and admin | Each role reaches only its own dashboard; session ID changes after login |
| Login | Wrong password, inactive user, or inactive session past timeout | Generic sign-in denial or redirect to login; protected content is not returned |
| Upload | Upload valid PDF, DOCX, PPTX, and MP4 samples within the limit | Randomized file is saved; metadata is inserted with pending status |
| Upload | Rename an executable/text file to `.pdf`, submit an array-shaped file field, or exceed the configured limit | Upload is rejected and no material row or stored file remains |
| Moderation | Approve, reject, and bulk-process materials | State changes persist; pending/rejected materials stay out of student search |
| Material edit | Instructor edits title/description after approval | Record returns to pending and is hidden until approved again |
| Search | Search title, description, code; combine each curriculum/type filter; sort and move pages | Only approved matching rows appear; filters persist across pages; no SQL error |
| Download | Download approved content while signed in | File streams as an attachment; `downloads` row and counter increment exactly once |
| Download | Request pending/rejected/unknown ID or request while signed out | No file is returned and no download is recorded |
| Course/browse | Expand college/dept/year/semester and open course | Correct hierarchy, metadata, and approved course materials appear |
| Bookmark | Save/remove a material and open dashboard on same browser | Bookmark state updates and only approved saved materials are shown |
| Admin | Search/filter users, edit, reset password, deactivate/reactivate, delete | Changes persist; reset secret displays once; active sessions for deactivated users are denied on next request |
| Admin | Add/edit/delete course, college, department; change site settings | Valid changes persist; referenced records cannot be deleted; maintenance mode blocks non-admin pages |
| Reports | Submit a material report and resolve/dismiss it as admin | Report is stored, visible to admin, and status persists |

## Security checks

Perform these checks only against a local test database with disposable accounts and files:

- Submit SQL metacharacters in search, email, title, and filter fields; verify they are treated as data and do not change query structure.
- Submit HTML/script text in names, descriptions, and titles; verify it is displayed as text, not executed.
- Submit missing, stale, and altered CSRF tokens to every state-changing form; expect rejection without a write.
- Request another instructor's upload edit/delete URL and admin-only URLs as a student; expect denial and no state change.
- Try direct HTTP access to `uploads/materials/` and `logs/`; expect 403/404, not a directory listing or file content.
- Try `download.php` with an unapproved ID and path-like values; no file should be exposed.
- Inspect cookies for `HttpOnly`, `SameSite=Lax`, and `Secure` under HTTPS. Confirm production responses do not display exception details.

## Responsive and compatibility checks

Test narrow mobile (320-390 px), tablet (768 px), and desktop (1280 px or wider). Check navigation, filters, tables, long titles, buttons, and forms for clipping/overlap. Test current Chrome, Firefox, and Edge plus Android Chrome and iOS Safari when available. Verify keyboard-only navigation, visible focus, skip link, form labels, and reduced-motion behavior.

## Performance checks

Use a representative test dataset and record PHP/MySQL versions, device, and network conditions. Suggested project targets are first useful page content under 2 seconds on the university network, search response under 1 second for 10,000 metadata rows, and successful upload/download of a 50 MB file within the server's configured timeout. Measure before claiming these targets are met; they are goals, not benchmark results.

## Usability study plan

Recruit 6-10 participants with informed consent: students from more than one department, at least one instructor, and one library/admin representative. Use a test database and do not collect real passwords or private course files. Ask participants to think aloud; observe without teaching unless they are blocked.

### Participant tasks

1. Find an approved material by a known course code.
2. Browse to a course using the college/department/year/semester hierarchy.
3. Filter results to a file type and sort by recent upload.
4. Open a material, save it, download it, and find it again on the dashboard.
5. As an instructor, upload a test PDF and locate its moderation status.
6. As an administrator, approve a pending material and deactivate a test account.

### Observation checklist

- Task success/failure and completion time.
- Search terms and navigation path chosen.
- Misclicks, backtracking, repeated filters, or abandoned actions.
- Whether labels/statuses are understood without explanation.
- Mobile keyboard, page loading, text wrapping, and touch-target difficulties.
- Participant confidence and any privacy/accessibility concern.

### Feedback questionnaire

Rate each from 1 (strongly disagree) to 5 (strongly agree):

- I could find materials that matched the course I needed.
- The college/department/course structure made sense to me.
- Search and filters behaved as I expected.
- Material status and download information were clear.
- The interface was readable and usable on my device.
- I would use this library for course study materials.

Open questions: What was hardest to find? Which label or status was unclear? What did you expect but could not do? What should be available in Afaan Oromo or Amharic? What is the most important change before deployment?