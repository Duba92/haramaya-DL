# Deployment Guide

## Requirements

- Apache 2.4 with `.htaccess` overrides enabled (`AllowOverride All` for this app).
- PHP 8.0 or newer with `pdo_mysql`, `fileinfo`, `mbstring`, and `zip` enabled.
- MySQL 8 or a compatible current MariaDB release, using InnoDB and `utf8mb4`.
- Apache write permission for `uploads/materials/` and `logs/`; application PHP and asset files should be read-only to the web-server account.
- A supported modern browser. Bootstrap and Bootstrap Icons are loaded from jsDelivr, so the browser needs network access unless those assets are served locally.

## Install with XAMPP

1. Install XAMPP and start Apache and MySQL from the control panel.
2. Place the project directory under `xampp/htdocs/haramaya-DL`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, choose **Import**, and import `database/schema.sql`. This creates the database and sample records. The SQL is intended for a fresh install; `CREATE TABLE IF NOT EXISTS` does not alter a database created from an earlier schema revision.
4. Create `includes/config.local.php` and return the local settings array. This file is ignored by Git:

   ```php
   <?php
   return [
       'APP_ENV' => 'development',
       'DB_HOST' => '127.0.0.1',
       'DB_PORT' => '3306',
       'DB_NAME' => 'digital_library',
       'DB_USER' => 'root',
       'DB_PASSWORD' => '',
   ];
   ```

5. Visit `http://localhost/haramaya-DL/` and sign in with one of the development accounts in the root README.
6. Run `database/queries.sql` separately in phpMyAdmin's SQL tab to try the retrieval examples.

If you already created a database from an earlier version of `schema.sql`, back up its records before recreating it. The current script includes account activation, reports, activity, and settings tables that older schemas do not contain.

## PHP upload limits

The application caps uploads at 50 MB. In `php.ini`, set values that allow the multipart request envelope as well as the file itself:

```ini
file_uploads = On
upload_max_filesize = 50M
post_max_size = 52M
max_file_uploads = 10
max_execution_time = 120
```

Restart Apache after changing `php.ini`. The administrator setting may reduce the upload cap; it cannot raise the hard 50 MB application limit.

## Production configuration

- Set `APP_ENV=production` and database settings through the server environment or a non-versioned `includes/config.local.php`.
- Use a dedicated MySQL user with only the privileges this application needs. Do not use the XAMPP root account.
- Change or remove the sample accounts and sample credentials before exposing the site publicly.
- Serve the site exclusively over HTTPS. Configure Apache's TLS certificate and redirect HTTP to HTTPS so secure session cookies are enabled.
- Verify Apache honors `uploads/materials/.htaccess` and `logs/.htaccess`. Requests to either directory must not expose files. Prefer moving uploads and logs outside the public document root for production, then update `UPLOAD_DIR` and the error-log destination.
- Grant the Apache account write access only to the upload and log directories. Do not make the whole project tree writable.
- Configure `upload_max_filesize`, `post_max_size`, and request timeouts to match the application's limits.
- Back up the MySQL database and `uploads/materials/` together. Test restoring both before relying on the system.
- Keep PHP error display disabled in production. Logs contain operational details and must remain private.

## Deployment checks

- Confirm the homepage, login, registration, and all role dashboards load over HTTPS.
- Sign in with a test account for each role; verify an inactive account is rejected.
- Upload a valid PDF, approve it as an administrator, then download it as a student.
- Confirm pending/rejected files cannot be downloaded and direct HTTP requests to the uploads folder return 403 or 404.
- Check that an upload and its database record survive an application restart.
- Verify search results, charts, reports, settings, and navigation at mobile and desktop widths.

## Troubleshooting

- **Database unavailable:** Start MySQL, check host/port/database/user/password in the local config, and verify the PHP `pdo_mysql` extension is enabled.
- **Access denied:** Grant the dedicated account access to `digital_library`; avoid changing the database to a root login in production.
- **Import reports missing table/column:** Back up existing data and install the current schema on a clean database; `IF NOT EXISTS` does not migrate older tables.
- **Uploads always fail:** Check PHP's `upload_max_filesize` and `post_max_size`, Apache write permissions on `uploads/materials/`, and that `fileinfo` and `zip` are enabled.
- **Direct upload path works:** Enable Apache `AllowOverride All`, restart Apache, and retest `.htaccess` access restrictions before storing real content.
- **Uploaded file not found:** Confirm the file remains in `uploads/materials/` and its relative database path begins with `uploads/materials/`.
- **Styles or charts missing:** Check browser network access to the CDN, or download and serve Bootstrap, Bootstrap Icons, and Chart.js locally.
- **Blank page after deployment:** Keep display errors disabled; inspect the private PHP/Apache error log and application `logs/app.log`.