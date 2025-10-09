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
2. Under **Two-factor authentication**, choose **Enable 2FA**. A modal will display the QR code and secret for your authenticator app.
3. Scan the secret into your preferred authenticator app (e.g., Microsoft Authenticator, Google Authenticator) and enter the 6-digit code to confirm.
4. Store the generated recovery codes in a secure location. You can regenerate them at any time.
5. Once enabled, future logins require both password and TOTP code (with `verify-otp` as the intermediate step). Recovery codes can be used when the authenticator device is unavailable.

## Recurring billing automation

The `bin/process_billing.php` script processes subscriptions, creates new invoices when the `next_billing_at` date is reached, and marks invoices as overdue (sending both email and in-app reminders). Schedule it via cron, for example:

```cron
*/30 * * * * php /path/to/portal/bin/process_billing.php
```

Mark invoices as paid from the admin **Orders** table; doing so triggers the payment success email and clears outstanding notifications.

> **Stripe subscription API note:** the portal charges recurring services by generating a new [PaymentIntent](https://docs.stripe.com/api/payment_intents) for each cycle using the saved customer and payment method. Because we do not create Stripe `subscription` or `subscription_item` objects, the requirements in [Stripe’s subscription item update docs](https://docs.stripe.com/api/subscription_items/update#update_subscription_item-price_data-recurring) do not affect the integration. If you later extend the portal to manage Stripe subscriptions directly, ensure each `price_data` payload includes the `recurring` block (`interval`, `interval_count`, etc.) as described in the documentation.

### PayPal subscription flow

Recurring services that use PayPal already implement the recommended API sequence end to end:

1. **Product creation** – `prepare_paypal_subscription_plan()` calls `ensure_paypal_product_for_service()` which reuses or issues `POST /v1/catalogs/products` through `create_paypal_product()` so every recurring service has a PayPal catalog product.【F:helpers.php†L613-L666】
2. **Plan creation & activation** – the helper immediately invokes `create_paypal_plan()`, sending `POST /v1/billing/plans`, activating the plan, and polling `GET /v1/billing/plans/{id}` until the status is `ACTIVE` before returning the identifier.【F:helpers.php†L668-L720】
3. **Client checkout** – recurring services enqueue the PayPal JS SDK with `vault=true`/`intent=subscription`, and the payment modal renders subscription buttons that call `actions.subscription.create` with the plan ID so clients subscribe directly from the portal.【F:templates/client/pages/service_detail.php†L8-L15】【F:templates/client/partials/payments_modal.php†L11-L28】

Once credentials are saved, no manual product/plan setup is required—each checkout dynamically provisions the product and plan, records the identifiers, and PayPal handles future renewals.

## Notifications & email

- Outbound email can now use PHP's `mail()` transport, authenticated SMTP, or the SendGrid API. Configure the sender name, address, and delivery method from **Admin → Settings → Email delivery**. When delivery fails (for example on a local machine without an SMTP relay) the payload is logged to `data/mail.log` so nothing is lost.
- To connect Microsoft 365, create an app password under **My Account → Security → Additional security verification**, then enter:
  - **Transport:** SMTP
  - **Host:** `smtp.office365.com`
  - **Port:** `587`
  - **Encryption:** TLS
  - **Username:** Your Microsoft 365 mailbox address
  - **Password:** The generated app password (leave the field blank later to keep it stored, or tick “Reset stored password” to clear it).
- In-app notifications surface via the bell icon in the top bar. Clicking **Clear** (or the bell button) marks alerts as read.
- Email templates live in the **Automations** section. You can add new templates or remove the defaults (order confirmation, ticket reply, payment success, invoice overdue).
- To verify email delivery end-to-end: submit a new service order from the client portal, capture payment (or mark it paid from the admin orders table), reply to a support ticket, and confirm that each stage emails both the client and administrators while logging the corresponding invoices.

### SendGrid email delivery

The project ships with the official `sendgrid/sendgrid` SDK. Install dependencies after cloning by running:

```bash
composer install
```

Set your API key as an environment variable so it can be rotated without editing the database:

```bash
export SENDGRID_API_KEY="<your key>"
```

If you are using the EU data residency endpoint, also export:

```bash
export SENDGRID_REGION="eu"
```

The admin settings screen lets you persist a fallback API key/region in the database, but any environment variable will take precedence so you can swap credentials during deployments without touching production data.

## Styling & assets

All UI styling is consolidated in `static/css/app.css`. The layout respects the configurable brand colour and font stored in settings. Feel free to extend or replace this stylesheet—no external Tailwind/Bootstrap dependencies are required.

To display your own logo in the sidebar header, upload the asset anywhere web-accessible on your server and save the absolute URL under **Branding → Logo URL** in the admin dashboard. When no logo is configured the interface automatically renders branded initials based on your company name, so you will still have a polished placeholder while preparing custom artwork.

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
