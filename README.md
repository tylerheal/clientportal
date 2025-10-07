# Client Portal (PHP)

A self-hosted client/admin portal built with PHP + SQLite featuring modern dashboards, service ordering, ticketing, recurring billing, and two-factor authentication.

## Highlights

- Clean, responsive admin and client dashboards with a persistent sidebar, top search, notification bell, and profile menu.
- Email/password authentication plus optional TOTP-based two-factor security with recovery codes for both admins and clients.
- Service catalogue with dynamic form builder, one-off/recurring pricing, and inline management tools for admins.
- Orders, invoices, and subscriptions tracked in SQLite with automated invoice generation and overdue reminders (via `bin/process_billing.php`).
- Shared notification system that pushes email + in-app alerts for orders, ticket replies, payment updates, and overdue invoices.
- Self-hosted CSS/JS (no external CDNs) compiled into `static/css/app.css` for a consistent look that respects brand colours/fonts.
- Extensionless URLs via `index.php` front controller and `.htaccess` rewrite rules for cleaner routing.

## Requirements

- PHP 8.1+ with `pdo_sqlite` enabled.
- Ability for PHP to write to the `data/` directory (for the SQLite database, email log, and session artifacts).
- Apache or Nginx configured to pass unmatched requests to `index.php` (sample `.htaccess` included).

## First-time setup

1. Upload the repository contents to your web root (or clone directly onto the server).
2. Ensure the `data/` directory is writable by the web server user (`www-data`, `apache`, etc.). It will be created automatically on first run if missing.
3. Browse to `/signup` to create your first client account or sign in with the seeded admin credentials:
   - **Email:** `admin@example.com`
   - **Password:** `Admin123!`
4. Head to `/dashboard` (as an admin) to customise branding colours, fonts, and logo, and to connect Stripe/PayPal credentials under **Payments**.
5. Create services (one-off, monthly, or annual) and test placing orders from a client account. New subscriptions will receive invoices automatically.

## Creating additional admins

From the admin dashboard, scroll to **Administrators** and submit the form with a name, email, and secure password. The new account will be created immediately with admin privileges. Alternatively, you can seed additional admins by inserting directly into the `users` table (role `admin`).

## Enabling two-factor authentication

1. Visit `/profile` while signed in.
2. Under **Two-factor authentication**, choose **Start setup**. A secret key (and otpauth URI) will be displayed.
3. Scan the secret into your preferred authenticator app (e.g., Microsoft Authenticator, Google Authenticator) and enter the 6-digit code to confirm.
4. Store the generated recovery codes in a secure location. You can regenerate them at any time.
5. Once enabled, future logins require both password and TOTP code (with `verify-otp` as the intermediate step). Recovery codes can be used when the authenticator device is unavailable.

## Recurring billing automation

The `bin/process_billing.php` script processes subscriptions, creates new invoices when the `next_billing_at` date is reached, and marks invoices as overdue (sending both email and in-app reminders). Schedule it via cron, for example:

```cron
*/30 * * * * php /path/to/portal/bin/process_billing.php
```

Mark invoices as paid from the admin **Orders** table; doing so triggers the payment success email and clears outstanding notifications.

## Notifications & email

- Outbound emails use PHP's `mail()` function. When delivery fails (e.g., on local machines), the payload is logged to `data/mail.log` so nothing is lost.
- In-app notifications surface via the bell icon in the top bar. Clicking **Clear** (or the bell button) marks alerts as read.
- Email templates live in the **Automations** section. You can add new templates or remove the defaults (order confirmation, ticket reply, payment success, invoice overdue).

## Styling & assets

All UI styling is consolidated in `static/css/app.css`. The layout respects the configurable brand colour and font stored in settings. Feel free to extend or replace this stylesheet—no external Tailwind/Bootstrap dependencies are required.

## Routing & pretty URLs

The included `.htaccess` rewrites non-file requests to `index.php`, allowing you to serve `/dashboard`, `/login`, etc., without the `.php` extension. For Nginx, add a similar rule to pass unmatched requests to `index.php`.

## Local development

```bash
php -S localhost:8000
```

Then browse to `http://localhost:8000/` (which will route to the login screen). The SQLite database is created on demand under `data/portal.sqlite3`.

## Testing checklist

- ✅ Client signup/login and admin login (with 2FA if enabled).
- ✅ Service creation, editing, deletion, and form builder parsing.
- ✅ Client order placement (one-off/monthly/annual) with invoice + notification creation.
- ✅ Admin payment status updates triggering payment-success emails and notifications.
- ✅ Support ticket submission and replies sending both emails and bell notifications.
- ✅ Billing cron script generating new invoices and overdue alerts.
- ✅ Profile updates, password changes, and TOTP enable/disable flows.

## Security notes

- Passwords use PHP's password hashing APIs.
- Sessions regenerate on login/logout and store minimal user context.
- TOTP secrets and recovery codes are persisted per user; disable 2FA from `/profile` if a device is lost.
- For production, consider adding HTTPS enforcement, CSRF tokens, and rate limiting around authentication endpoints.
