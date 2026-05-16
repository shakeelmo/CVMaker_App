# CV Maker App

CV Maker is a PHP/MySQL web app for creating professional resumes online. It includes a public resume builder, template gallery, blog, admin dashboard, email templates, PayPal-ready settings, and optional AI-assisted writing tools.

## Features

- User registration, email verification, login, password reset, and dashboard
- Resume builder with saved resumes, photo uploads, templates, and PDF export
- Admin dashboard for users, resumes, templates, blog posts, CMS pages, settings, branding, tracking, email templates, and contact messages
- Public SEO pages for resume templates, resume examples, blog articles, FAQ, help, privacy, terms, and about
- SMTP email support through PHPMailer
- Optional Gemini API and PayPal settings managed through the admin dashboard

## Requirements

- PHP 8.0 or newer
- MySQL 5.7+ or MariaDB 10.3+
- PHP extensions: PDO MySQL, cURL, JSON, OpenSSL, mbstring
- Composer for PHP dependencies
- Apache/OpenLiteSpeed rewrite support

## Setup

1. Install Composer dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. Create a `.env` file one level above the web root, or use hosting environment variables. Start from `.env.example`.

3. Import `database.sql` into a new database, or run `install.php` if you want to use the web installer.

4. Make these directories writable by the web server:

   ```text
   config/
   uploads/
   uploads/branding/
   uploads/blog/
   uploads/photos/
   exports/
   logs/
   ```

5. Configure SMTP, branding, PayPal, AI, and tracking from the admin dashboard after installation.

## Environment Variables

```env
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_user
DB_PASSWORD=your_password
JWT_SECRET=change-this-to-a-long-random-secret

PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
GEMINI_API_KEY=

SMTP_HOST=
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=

LICENSE_API_URL=https://resume.muawia.com/api/validate
LICENSE_API_SECRET=
LICENSE_KEY=
```

## Repository Hygiene

This repository intentionally excludes production-only files:

- `.env`
- `vendor/`
- `logs/`
- `exports/`
- `uploads/photos/`
- server backup files
- exported seed-data dumps that may contain private settings

The committed `database.sql` keeps public seed data and clears sensitive settings.

## Important Paths

```text
api/                  Backend API entry points
routes/               Application route handlers
middleware/           JWT authentication middleware
config/               Database, mailer, payment/settings helpers
templates/emails/     HTML email templates
uploads/branding/     Logo and favicon assets
uploads/blog/         Blog featured images
uploads/templates/    Resume template thumbnails
examples/             Public resume example landing pages
```

## Security Notes

- Change `JWT_SECRET` before production use.
- Do not commit `.env`, SMTP passwords, API keys, PayPal secrets, or production database exports.
- Delete or lock `install.php` after installation.
- Keep Composer dependencies updated.
