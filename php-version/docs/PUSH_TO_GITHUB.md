# Pushing this PHP port to GitHub

The folder you have in front of you is ready to be pushed back to your `Landing-2` repository (or to a new one). Pick **one** of the workflows below.

---

## A. Push to a new branch on the same repo (recommended)

This keeps the original FastAPI/React code in `main` and the PHP version in `php`.

```bash
cd /path/to/this/folder
git init
git checkout -b php
git add .
git commit -m "PHP / cPanel port of Landing-2"
git remote add origin https://github.com/youtnad1-droid/Landing-2.git
git push -u origin php
```

Then on cPanel → Git Version Control → clone the `php` branch into `public_html/`.

---

## B. Replace the contents of `main`

> ⚠️ This rewrites history. Only do this if you want the PHP port to be the new project.

```bash
cd /path/to/this/folder
git init
git add .
git commit -m "Convert project to PHP for cPanel"
git branch -M main
git remote add origin https://github.com/youtnad1-droid/Landing-2.git
git push -f origin main
```

---

## C. New repository

1. Create a brand-new empty repo on GitHub, e.g. `Landing-2-php`.
2. Then:

```bash
cd /path/to/this/folder
git init
git add .
git commit -m "Initial PHP version"
git branch -M main
git remote add origin https://github.com/youtnad1-droid/Landing-2-php.git
git push -u origin main
```

---

## What's already configured for Git

* `.gitignore` excludes `.env`, the `storage/` SQLite folder, and uploaded media.
* `uploads/.gitkeep` keeps the empty uploads folder in version control.
* `install.php` ships in the repo — remember to delete it on the server after install.
