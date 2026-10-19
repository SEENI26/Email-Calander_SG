# Email Inward Count Tracker

A small PHP and SQLite app based on the supplied reference table. It records the number of emails received by **Design Center**, **QC**, and **Account** for two daily shifts, stores the entries, and calculates day and Monday–Saturday totals automatically.

## Schedule and reporting

- Morning: 7:30 AM–12:00 PM
- Afternoon: 12:00 PM–5:00 PM
- Choose a date and department, enter both counts, and save.
- Choose any date in a week to see that week's Monday–Saturday report. Saturday is the weekly closing day; the last column calculates each shift, department, and overall total.
- Existing date/department values can be loaded and edited. Zero-count days can be saved as `0`; dates with no saved record also display as 0.

## Requirements

- PHP 8.1 or later with PDO SQLite enabled (`pdo_sqlite` and `sqlite3` extensions).
- The PHP process needs write permission to the `storage/` folder.

## Run locally

From this project folder:

```sh
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000`. The database is created on first launch at `storage/email_counts.sqlite` and remains there between visits.

## Deploy

Upload the project folder to the server and point the web server's document root to its `public/` directory. Keep `storage/` outside the public document root and make it writable by PHP. The included `storage/.htaccess` is an extra protection when using Apache, but configuring the public document root as `public/` is the main protection. Back up `storage/email_counts.sqlite` regularly to retain the saved counts.

The app uses the `Asia/Singapore` timezone for its server-side date default. Update `date_default_timezone_set` near the top of `public/index.php` if needed.
