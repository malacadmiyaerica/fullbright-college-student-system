# Deployment Guide

> **Vercel cannot host this application.** Vercel runs static sites, Node.js,
> and edge functions — not PHP — and it has no MySQL, no persistent filesystem,
> and no shared session storage. This is a PHP + MySQL monolith that writes
> uploaded documents and generated ID-card images to disk, and uses server-side
> sessions, so it must run on a **container / PHP host** with a **managed
> MySQL** database.

The repository ships a `Dockerfile`, so it deploys to any container platform.
Recommended targets:

| Host            | MySQL included | Persistent disk (free) | Notes                                   |
| --------------- | -------------- | ---------------------- | --------------------------------------- |
| **Railway**     | Yes            | Volumes available      | Easiest — web + MySQL in one project    |
| Render          | No             | Paid plans only        | Use an external MySQL                   |
| Fly.io          | Unmanaged (or external) | Volumes     | More manual                             |
| Any VPS        | Install MySQL  | Yes                    | Use `docker compose`                    |

---

## Environment variables

All configuration is read from the environment (`includes/config.php`). Set these
in your host's dashboard — never in code.

| Variable | Purpose | Example |
| --- | --- | --- |
| `DATABASE_URL` | Single MySQL URL (preferred if the host provides one) | `mysql://user:pass@host:3306/db` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Individual DB settings (used when `DATABASE_URL` is absent) | `host`, `3306`, `fullbright_college`, ... |
| `DB_SSL` | Require TLS for managed MySQL public endpoints | `true` |
| `DB_SSL_CA` | Path to a CA bundle (optional) | `/etc/ssl/ca.pem` |
| `APP_ENV` | `production` disables on-screen credential/link fallbacks by default | `production` |
| `SESSION_DRIVER` | `database` stores sessions in MySQL (recommended) | `database` |
| `AUTO_IMPORT_SCHEMA` | Import `schema.sql` once into an empty DB | `true` (first deploy only) |
| `FULLBRIGHT_PUBLIC_URL` | Force the public URL used in Digital ID QR codes | `https://your.domain` |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_ENCRYPTION` | SMTP delivery for verification/recovery email | `smtp.gmail.com`, `587`, ... |
| `SHOW_CREDENTIALS_ONSCREEN`, `SHOW_VERIFICATION_LINK_ONSCREEN` | On-screen fallbacks (auto-off in production) | `false` |

If `DATABASE_URL` is set, it takes precedence over the individual `DB_*` values.

---

## Option A — Railway (recommended)

Railway provides both a Docker host and managed MySQL.

1. Push this repo to GitHub (already done).
2. Go to <https://railway.app> → **New Project** → **Deploy from GitHub repo**
   → select `fullbright-college-student-system`.
3. In the project, click **New** → **Database** → **Add MySQL**.
4. Open the **web service** → **Variables** and add:
   - `DATABASE_URL` = `${{MySQL.DATABASE_URL}}` (reference the MySQL plugin)
   - `APP_ENV` = `production`
   - `SESSION_DRIVER` = `database`
   - `AUTO_IMPORT_SCHEMA` = `true`  *(first deploy only)*
   - optionally `SMTP_*` for real email delivery
5. **Deploy**. Watch the build/deploy logs for
   `[entrypoint] Schema imported.`
6. After the first successful deploy, set `AUTO_IMPORT_SCHEMA` = `false`.
7. **Settings → Networking → Generate Domain**. Open it — the site is live.
8. (Persistence) **Add a Volume** to the web service and mount it at
   `/var/www/html/uploads`; add a second at `/var/www/html/digital_id`.
   Without volumes these files are lost on redeploy.

The Digital ID QR code uses `public_base_url()`, which auto-detects the domain
the site is served from — so the QR automatically points at your Railway URL
(or set `FULLBRIGHT_PUBLIC_URL` to force it).

---

## Option B — Render

1. Provision a managed MySQL externally (Aiven, Railway, Clever Cloud, ...) and
   note the host, port, database, user, password.
2. In Render: **New +** → **Blueprint** → select this repo. Render reads
   `render.yaml` and builds the Dockerfile.
3. In the service **Environment**, set `DB_HOST`, `DB_NAME`, `DB_USER`,
   `DB_PASS` (and `DB_SSL=true` if the MySQL requires TLS).
4. Deploy. On a paid plan, attach a **Disk** at `/var/www/html/uploads` and
   another at `/var/www/html/digital_id` for persistence.

> Render's free web services sleep after inactivity and have no persistent disk,
> so uploads/ID cards will not survive. Use a paid instance or a VPS for
> production data.

---

## Option C — Fly.io

```bash
fly launch --dockerfile Dockerfile        # create the app
fly mysql create                          # or use an external MySQL
fly secrets set DATABASE_URL="mysql://..." APP_ENV=production SESSION_DRIVER=database
fly volumes create fullbright_data --size 1
# mount the volume at /var/www/html/uploads and /var/www/html/digital_id in fly.toml
fly deploy
```

---

## Option D — Any VPS with Docker

The included `docker-compose.yml` runs the app plus MySQL with persistent
volumes:

```bash
cp .env.example .env      # edit DB_PASS, MYSQL_ROOT_PASSWORD, etc.
docker compose up -d --build
# app on http://<server-ip>:8080
```

`AUTO_IMPORT_SCHEMA=true` imports the schema on the first boot (only when the
database is empty). Set it to `false` afterwards.

---

## Post-deployment verification

After the site is live, confirm each feature works **against the live URL**:

1. **Enrollment + email verification** — open the enrollment page, enter an
   email, and either receive the email (with `SMTP_*` configured) or use the
   on-screen link (when `SHOW_VERIFICATION_LINK_ONSCREEN=true`). Verify the
   link opens `verify_email.php` on the live domain.
2. **Entrance exam** — generate/open an exam, submit, and confirm scoring.
3. **Login credentials** — finish enrollment, note the Student ID + generated
   password on the receipt, log in with **username = Student ID**.
4. **Address checkbox** — tick *Same as Permanent Address*; confirm the Current
   Address mirrors/locks and saves.
5. **Digital ID QR** — open the profile, render the ID, and scan the QR with a
   phone. It must open `digital_id.php?sid=...` on the **live domain** (not
   `localhost`) and download the ID PNG. If it points at localhost, set
   `FULLBRIGHT_PUBLIC_URL` to your live URL.

## Production hardening checklist

- [ ] `APP_ENV=production` (turns off on-screen credential/link fallbacks).
- [ ] `SESSION_DRIVER=database` (survives restarts and multiple instances).
- [ ] Configure `SMTP_*` so verification/recovery emails are actually delivered.
- [ ] Attach persistent volumes for `uploads/` and `digital_id/`, or accept
      that these files reset on redeploy.
- [ ] Use TLS for the database (`DB_SSL=true`) when it is internet-facing.
- [ ] Rotate the database password; never commit `.env`.
- [ ] Change the default MySQL root/db password (the compose file uses
      placeholders).
