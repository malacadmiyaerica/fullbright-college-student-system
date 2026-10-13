# Fullbright College Student Management System — Documentation

This folder holds project documentation. See the root
[`README.md`](../README.md) for the feature overview, setup, and key files.

## Repository organization

The application is a **server-rendered PHP monolith**, so there is no physical
`frontend/` vs `backend/` split. Instead, the logical layers map onto the
existing folders as follows:

| Logical layer | Folder(s)                                             | Contents                                                             |
| ------------- | ----------------------------------------------------- | -------------------------------------------------------------------- |
| **backend**   | repository root `*.php`, `includes/`                  | Request handlers/endpoints and shared libraries (DB, mail, exam, ID) |
| **frontend**  | `css/`, `js/`, and the HTML emitted by the PHP pages  | Styles, client scripts, and markup                                   |
| **assets**    | `assets/`, `uploads/`                                 | `logo.png`, `background.jpg`, and user-uploaded documents            |
| **docs**      | `docs/`                                               | This documentation                                                   |

### Why not a physical `/frontend` + `/backend` split?

The pages are rendered on the server and share a single configuration,
database layer, and session. Moving files into new top-level folders would
require rewriting every `require`/`include` path and every asset/redirect URL,
with no functional benefit, while risking the working application. The mapping
above keeps the repository understandable without breaking it.

## Directory reference

```
.
├── assets/                  # Static brand assets (logo.png, background.jpg)
├── css/
│   └── style.css            # All application styles
├── js/
│   ├── script.js            # Enrollment wizard, profile, ID card export
│   └── qrcode.js            # Offline QR-code generator
├── includes/
│   ├── config.php           # Constants, DB config (env vars), URL helpers
│   ├── db.php               # PDO connection + shared helpers
│   ├── content.php          # Shared page content/helpers
│   ├── exam.php             # Question bank, generation, scoring, fallback
│   ├── idcard.php           # Server-side Digital ID markup
│   ├── mailer.php           # Email HTML + SMTP/mail() send helpers
│   ├── verification.php     # Email-verification tokens & helpers
│   ├── session_handler.php  # MySQL-backed session handler (production)
│   └── session_bootstrap.php# auto_prepend bootstrap for DB sessions
├── docker/                  # Container config (php.ini, Apache, entrypoint)
├── docs/                    # Documentation (this folder)
├── uploads/                 # Student documents (git-ignored)
├── digital_id/              # Generated ID PNGs (git-ignored)
├── Dockerfile               # Production PHP 8.2 (Apache) image
├── docker-compose.yml       # Local/self-hosted stack (app + MySQL)
├── railway.json             # Railway deploy config
├── render.yaml              # Render Blueprint
├── .env.example             # Environment variable template
├── schema.sql               # Full schema
├── upgrade_*.sql            # Incremental migrations
├── index.php                # Landing page
├── enroll.php               # 5-step enrollment wizard
├── submit_enrollment.php    # Enrollment handler
├── send_verification.php    # Issue verification token
├── check_verification.php   # Poll verification status
├── verify_email.php         # Verification landing page
├── exam.php                 # Entrance exam
├── submit_exam.php          # Exam scoring
├── generate_exam.php        # Create an exam code
├── get_exam_status.php      # Exam status endpoint
├── receipt.php              # One-time credential display
├── login.php                # Login (username = Student ID)
├── logout.php               # Logout
├── dashboard.php            # Student dashboard
├── profile.php              # View / edit profile
├── update_profile.php       # Profile update endpoint
├── change_password.php      # Change password
├── forgot_password.php      # Password recovery
├── digital_id.php           # QR-target: downloadable ID card
├── save_id_card.php         # Persist rendered ID PNG
├── photo.php                # Serve/validate student photos
└── setup.php                # One-click schema setup
```

## Database

- Database name: `fullbright_college`
- Full schema: [`../schema.sql`](../schema.sql)
- Incremental changes: `upgrade_*.sql` in the repository root.

Tables: `students`, `exams`, `requirements`, `email_verifications`.

## Feature notes

- **Login credentials** — username is the Student ID; the password is a random
  value generated at enrollment (hashed with `password_hash`).
- **Email verification** — tokens are random 256-bit values; only their
  SHA-256 hash is stored; 24-hour expiry; single-use.
- **Same as Permanent Address** — stored as `students.same_address`; when set,
  the Current Address is mirrored server-side (the posted value is ignored).
- **Digital ID** — rendered client-side on a `<canvas>`; the QR links to
  `digital_id.php?sid=...`, which serves the saved PNG.
