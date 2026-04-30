# NadiPlayer — PHP / cPanel Edition

This is a **PHP** port of the original FastAPI + React `Landing-2` project, ready to upload to a regular shared-hosting account (cPanel / Plesk / DirectAdmin).

The React landing pages, admin login and admin dashboard are kept exactly as-is (built once into static HTML/CSS/JS — same look, same animations). The whole backend is rewritten in plain PHP 7.4+ / 8.x with PDO. MongoDB is replaced by **MySQL** (or SQLite if you prefer zero-setup).

---

## 🚀 Quick start (cPanel)

### 1. Create a database

In cPanel → **MySQL® Databases**
1. Create a new database (e.g. `cpaneluser_nadiplayer`).
2. Create a new MySQL user with a strong password.
3. Add that user to the database with **ALL PRIVILEGES**.

### 2. Upload the files

1. In cPanel → **File Manager**, navigate to `public_html/` (or a subfolder like `public_html/nadiplayer/`).
2. Upload **everything inside this folder** (use a ZIP and Extract for speed).
3. Make sure the `uploads/` directory exists and is writable (chmod **755** or **775**).

### 3. Configure the environment

Copy `.env.example` to `.env` in the same folder, then edit it:

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpaneluser_nadiplayer
DB_USER=cpaneluser_nadi
DB_PASS=your-strong-password
DB_CHARSET=utf8mb4

JWT_SECRET=<paste a long random string here>
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123

CORS_ORIGINS=*
```

> 🔐 **Change `JWT_SECRET`** to a long random string — anything 40+ characters works. Once tokens have been issued, do not change it again unless you want everyone signed out.

### 4. Run the installer

Open `https://your-domain.tld/install.php` once.
It will:
* test the DB connection,
* create the `admins`, `site_config` and `pages` tables,
* seed your admin user (using `ADMIN_USERNAME` / `ADMIN_PASSWORD` from `.env`),
* check the `uploads/` folder is writable.

When all rows are green, **delete `install.php`** from the server.

### 5. Done!

* Public site: `https://your-domain.tld/`
* Admin panel: `https://your-domain.tld/admin` (sign in with the credentials from `.env`).

---

## 🧩 What's inside

```
.
├── .env.example         ← copy to .env and edit
├── .htaccess            ← routing + security (Apache)
├── index.html           ← React SPA entry (built static)
├── install.php          ← first-run installer
├── router.php           ← fallback for `php -S` testing
├── static/              ← built React JS/CSS bundles
├── asset-manifest.json
├── includes/
│   ├── bootstrap.php    ← loads .env, sets CORS, includes the rest
│   ├── db.php           ← PDO singleton, MySQL or SQLite
│   ├── jwt.php          ← minimal HS256 JWT (compatible with PyJWT)
│   ├── auth.php         ← bcrypt + admin lookup
│   ├── helpers.php      ← JSON helpers, slug helpers, UUID
│   ├── config_store.php ← site_config defaults + load/save
│   ├── env.php          ← lightweight .env loader
│   └── .htaccess        ← deny direct access
├── api/
│   ├── index.php
│   ├── site-config.php             ← GET
│   ├── pages.php                   ← GET (list / by slug)
│   ├── uploads_serve.php           ← fallback file server
│   ├── auth/
│   │   ├── login.php
│   │   └── me.php
│   └── admin/
│       ├── site-config.php         ← GET, PUT
│       ├── pages.php               ← GET, POST, PUT, DELETE
│       └── uploads.php             ← POST upload, GET list, DELETE
├── sql/
│   ├── schema.sql       ← manual phpMyAdmin import (optional)
│   └── .htaccess        ← deny
└── uploads/             ← admin-uploaded images live here
    └── .htaccess        ← disable PHP execution inside
```

## 🔌 API endpoints

Identical to the original FastAPI surface so the React frontend works without modification.

| Method | Path                                | Auth   | Description                  |
|--------|-------------------------------------|--------|------------------------------|
| GET    | `/api/`                             | —      | Health check                 |
| GET    | `/api/site-config`                  | —      | Public site configuration    |
| GET    | `/api/pages`                        | —      | List published pages         |
| GET    | `/api/pages/{slug}`                 | —      | One published page           |
| POST   | `/api/auth/login`                   | —      | Admin login → JWT             |
| GET    | `/api/auth/me`                      | Bearer | Current admin                |
| GET    | `/api/admin/site-config`            | Bearer | Full config                  |
| PUT    | `/api/admin/site-config`            | Bearer | Save config                  |
| GET    | `/api/admin/pages`                  | Bearer | All pages                    |
| POST   | `/api/admin/pages`                  | Bearer | Create page                  |
| PUT    | `/api/admin/pages/{id}`             | Bearer | Update page                  |
| DELETE | `/api/admin/pages/{id}`             | Bearer | Delete page                  |
| POST   | `/api/admin/upload`                 | Bearer | Upload image (multipart)     |
| GET    | `/api/admin/uploads`                | Bearer | List images                  |
| DELETE | `/api/admin/uploads/{filename}`     | Bearer | Delete image                 |
| GET    | `/api/uploads/{filename}`           | —      | Serve uploaded file (static) |

JWT payload is `{ sub, role, iat, exp }` and tokens are valid for 7 days — same as the FastAPI version.

---

## 🧰 Local testing (without cPanel)

You don't need Apache to test locally. Use PHP's built-in server:

```bash
cd nadiplayer-php
cp .env.example .env
# tweak .env as needed (DB_DRIVER=sqlite is the easiest)
php -S 127.0.0.1:8080 router.php
```

Then open <http://127.0.0.1:8080/install.php> once, and the site is up at <http://127.0.0.1:8080/>.

---

## 🔐 Security checklist before going live

1. **Delete `install.php`** after a successful install.
2. **Change `JWT_SECRET`** in `.env` to a long random value.
3. **Change `ADMIN_PASSWORD`** to a strong password and re-run `install.php` (or simply update it in the DB).
4. Ensure `.env` is **not** browsable: it's blocked by `.htaccess`, but double-check with `https://your-domain/.env` (you should get 403).
5. Set folder permissions: `755` for directories, `644` for files, `775` (or `755`) for `uploads/`.
6. Make sure `uploads/` does **not** execute PHP — the included `uploads/.htaccess` blocks that.

---

## 🔄 Updating the React frontend

The React source still lives in the original repo (`frontend/`). To regenerate the static bundle:

```bash
cd frontend
yarn install
REACT_APP_BACKEND_URL='' yarn build      # use empty string so paths are same-origin
```

Then copy `frontend/build/*` to the project root, replacing `index.html`, `static/`, `asset-manifest.json`. The PHP backend stays the same.

---

## 📜 Original project

Based on the FastAPI + React + MongoDB project at <https://github.com/youtnad1-droid/Landing-2>. This PHP port keeps **all features**:

* 3 themed landing pages: **Cosmic** (gold + dark teal), **Yellow** (Molotov), **Premium**.
* Bilingual EN/AR with RTL support.
* Admin dashboard — edit hero copy, section titles, store URLs, prices, branding.
* Custom pages (legal / about / contact) authored in EN+AR with publish toggle.
* Image uploads (PNG/JPG/WEBP/GIF/AVIF/SVG, 8 MB max).
* JWT-protected admin endpoints, bcrypt-hashed passwords.
