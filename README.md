# Fullbright College Student Management System

A web-based **Student Management System** for Fullbright College, built with
**PHP + MySQL** and vanilla JavaScript. It covers the full student journey:
online enrollment, email verification, an automated entrance examination,
profile management, secure login, and a downloadable Digital ID with QR code.

> Department module: **Engineering & Communication Technology (ECT)**

---

## Features

- **Online Enrollment Wizard** — a 5-step guided form (Email Verification,
  Personal Information, Required Documents, Entrance Examination, Review).
- **Email Verification** — enrollment requires a verified email. A secure,
  single-use token (SHA-256 hashed, 24-hour expiry) is emailed; the student
  must click the link before they can proceed. Duplicate/resend attempts are
  throttled and already-registered emails are rejected.
- **Entrance Examination** — 15 auto-generated questions (Math + English),
  accessible by code or QR scan, auto-scored on submit with per-question
  review. A regeneration fallback rebuilds the exam if its stored questions
  are ever missing.
- **Secure Login Credentials** — on successful enrollment each student
  receives a **username (their Student ID)** and a **randomly generated
  password** (hashed with `password_verify` / `password_hash`). Credentials
  are shown once on the receipt.
- **Student Dashboard & Profile** — view personal details and edit permitted
  fields (email, contact number, current address, profile photo).
- **"Same as Permanent Address"** — a checkbox mirrors and locks the Current
  Address to the Permanent Address on both the enrollment and profile forms,
  and collapses the Digital ID to a single address line.
- **Digital ID Card** — a landscape ID rendered on a `<canvas>` with the
  student's photo, details, and a **QR code** that links to a downloadable
  copy of the full ID (`digital_id.php`).
- **Forgot / Change Password** — self-service recovery by Student ID or email,
  plus a change-password form enforcing a strong password policy.

### Password policy

Minimum 8 characters, with at least one uppercase letter, one lowercase
letter, one digit, and one special character (`!@#$%^&*`).

---

## Tech Stack

| Layer     | Technology                                   |
| --------- | -------------------------------------------- |
| Backend   | PHP 8.2 (PDO / prepared statements)          |
| Database  | MySQL / MariaDB (via XAMPP)                  |
| Frontend  | HTML5, CSS3, vanilla JavaScript              |
| Imaging   | PHP GD (`imagepng`)                          |
| QR codes  | Bundled `js/qrcode.js` (works offline)       |

No build step or Node.js dependency is required to run the app.

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (or any PHP 8.1+ with the `pdo_mysql`
  and `gd` extensions) and MySQL/MariaDB.
- A web browser.

---

## Setup (XAMPP)

1. **Place the project** in your web root, e.g.
   `C:\xampp\htdocs\SYSTEM FINALS` (or `/Applications/XAMPP/htdocs/...` on macOS).

2. **Create the database** (default name `fullbright_college`):

   ```sql
   CREATE DATABASE fullbright_college
     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import the schema**: open `setup.php` in the browser, or import
   `schema.sql` via phpMyAdmin:
   `mysql -u root fullbright_college < schema.sql`

4. **Configure the connection** in `includes/config.php`
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Defaults match a stock XAMPP
   install (`root` with an empty password).

5. **Run**: browse to `http://localhost/SYSTEM FINALS/` (or start the built-in
   server with `php -S localhost:8080`).

### Applying updates to an existing database

Incremental migrations are kept as `upgrade_*.sql` files. Import any that
post-date your database, e.g.:

```
upgrade_email_verification.sql
upgrade_profile.sql
upgrade_same_address.sql
```

---

## Project Structure

The application is a classic server-rendered PHP app. To keep the repository
clear, the logical layers map onto the existing folders as follows (see
[`docs/README.md`](docs/README.md) for details):

| Logical layer | Location in this repo                                  |
| ------------- | ------------------------------------------------------ |
| **backend**   | root `*.php` endpoints + `includes/`                    |
| **frontend**  | `css/`, `js/`, and the HTML rendered by the PHP pages   |
| **assets**    | `assets/` (logo, background) and `uploads/` (documents) |
| **docs**      | `docs/`                                                 |

```
.
├── assets/                 # logo.png, background.jpg
├── css/                    # style.css
├── js/                     # script.js, qrcode.js
├── includes/               # config, db, mailer, exam, verification, idcard
├── docs/                   # documentation
├── uploads/                # student-uploaded documents (git-ignored)
├── digital_id/             # generated ID-card PNGs (git-ignored)
├── schema.sql              # full database schema
├── upgrade_*.sql           # incremental migrations
└── *.php                   # pages & JSON endpoints
```

### Key files

| File                        | Purpose                                          |
| --------------------------- | ------------------------------------------------ |
| `index.php`                 | Landing page                                     |
| `enroll.php`                | 5-step enrollment wizard                         |
| `submit_enrollment.php`     | Validates and stores an application              |
| `send_verification.php`     | Issues an email-verification token               |
| `verify_email.php`          | Verification landing page                        |
| `exam.php` / `submit_exam.php` | Entrance exam page / scoring                  |
| `receipt.php`               | One-time display of generated credentials        |
| `login.php` / `logout.php`  | Authentication                                   |
| `dashboard.php`             | Student dashboard                                |
| `profile.php`               | View / edit profile                              |
| `update_profile.php`        | Profile update endpoint                          |
| `change_password.php`       | Change password                                  |
| `forgot_password.php`       | Password recovery                                |
| `digital_id.php`            | Serves the downloadable ID card via QR           |
| `save_id_card.php`          | Saves the rendered ID card PNG                   |

---

## Testing the enrollment flow locally

XAMPP has no outgoing mail server, so verification emails are not delivered.
For local testing, `SHOW_VERIFICATION_LINK_ONSCREEN` (in
`includes/config.php`) returns the verification link to the enrollment form
and displays it in a "Local demo" box. Set it to `false` once real SMTP is
configured. The same applies to `SHOW_CREDENTIALS_ONSCREEN` for password
recovery.

---

## Deployment

The app ships with a `Dockerfile` and deploys to any container host with a
managed MySQL database (Railway, Render, Fly.io, or a VPS). Configuration is
read from environment variables, so no credentials live in the code.

> **Not supported on Vercel.** Vercel does not run PHP and provides no MySQL,
> persistent filesystem, or shared sessions — all of which this app uses.

Quick start (Docker Compose, self-hosted):

```bash
cp .env.example .env      # then edit it
docker compose up -d --build
# open http://localhost:8080
```

Full instructions (Railway, Render, Fly.io, VPS), the environment-variable
reference, and a post-deploy verification checklist live in
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

---

## Contributing

1. Fork / clone the repository, then create a feature branch:
   `git checkout -b feature/my-change`
2. Commit your work: `git commit -m "Update: describe change"`
3. Push and open a **Pull Request** against `main`.

Please use **GitHub Issues** for bug reports and **GitHub Projects** for task
tracking. See [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md).

---

## License

Provided for educational use as part of the Fullbright College student project.
