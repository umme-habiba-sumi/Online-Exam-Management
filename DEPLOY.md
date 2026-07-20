# ExamPortal — Deploy (no separate database)

This project uses **SQLite** by default. The database file is created automatically
on first API request (with demo admin/student accounts). You do **not** need to
upload or create MySQL.

## After fixing login (redeploy)

1. Push latest code to GitHub (must include `backend/storage/exam_portal.sqlite`).
2. Vercel → **Redeploy** (Deployments → … → Redeploy).
3. Test API: open `https://YOUR-APP.vercel.app/api/health.php` — should show JSON `ok: true`.
4. Login: Admin `shamim.hossain@iu.ac.bd` / `password123` or Student `20-A-114` / `password123`.

If you see **404 DEPLOYMENT_NOT_FOUND** (Vercel platform page, not the app):

- Use your **production** URL (`https://YOUR-APP.vercel.app`), not an old preview link.
- Redeploy after the latest `vercel.json` change (single PHP router at `api/index.php`).
- In Vercel → Deployments, confirm the latest deploy status is **Ready**, not **Error**.

If login still fails, check Vercel → Project → **Functions** logs for PHP errors.


| Role | Login | Password |
|------|--------|----------|
| Admin | `shamim.hossain@iu.ac.bd` | `password123` |
| Student | Student ID `20-A-114` | `password123` |

---

## Option A — Vercel (GitHub upload only)

1. Push this whole project to **GitHub** (do not commit `backend/.env` — already gitignored).
2. [vercel.com](https://vercel.com) → **Add New Project** → import the repo.
3. Framework: **Other**. Leave Build Command empty.
4. Optional Environment Variables (only if you want real email OTP):

```
MAIL_DRIVER=smtp
MAIL_FROM=your@gmail.com
MAIL_FROM_NAME=ExamPortal
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your@gmail.com
SMTP_PASS=your-gmail-app-password
SMTP_SECURE=tls
SESSION_SECURE=1
SESSION_DRIVER=database
DB_DRIVER=sqlite
APP_TIMEZONE=Asia/Dhaka
```

If you skip mail vars, OTP codes still work in **dev** via `backend/storage/mail.log` style logging when `MAIL_DRIVER=log`.

5. Deploy → open `https://YOUR-APP.vercel.app/` → login page.

**Note:** On Vercel the SQLite file lives in temporary storage. Data can reset after idle/cold starts. Fine for demos; for permanent school data use Option B.

---

## Option B — Any PHP host (best for permanent data)

Upload the project folder by FTP/cPanel (Hostinger, InfinityFree, etc.) where PHP + SQLite are enabled. No MySQL setup. First visit creates `backend/storage/exam_portal.sqlite`.

---

## Local (XAMPP)

Already works. With `DB_DRIVER=sqlite` in `backend/.env`, open:

`http://localhost/Exam%20Management/login.html`

To use old MySQL again, set in `.env`:

```
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=exam_portal
DB_USER=root
DB_PASS=
```
