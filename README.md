# Client Portal (PHP)

This project delivers a PHP-powered client portal inspired by SPP with distinct admin and client areas. It supports service management, order capture, ticketing, branding controls, and email notifications out of the box.

## Features

- Email/password authentication with role-based access for admins and clients.
- Admin dashboard to manage services (including dynamic intake forms), review orders, respond to support tickets, and configure branding/payment/email settings.
- Client dashboard to submit orders, review order/payment status, and create or reply to support tickets.
- Simple visual form builder using `Label|name|type|required` lines that are converted into dynamic service forms.
- Editable email templates plus configurable Stripe and PayPal credentials.
- Automatic SQLite persistence with seeded admin account and lightweight email logging fallback.

## Requirements

- PHP 8.1+ with the `pdo_sqlite` extension enabled.
- A web server capable of running PHP (Apache, Nginx with PHP-FPM, etc.).

## Getting Started

1. Copy the repository contents to your PHP-enabled web root (or configure a virtual host pointing to this directory).
2. Ensure the PHP process can write to the `data/` directory (it will be created automatically on first run).
3. Visit `/signup.php` to create a client account or log in with the seeded admin account:
   - **Email:** `admin@example.com`
   - **Password:** `Admin123!`
4. Update branding, payment credentials, and email templates from the **Brand & account settings**, **Email templates**, and **Payment integrations** sections of the admin dashboard.

## Email Notifications

The portal uses PHP's native `mail()` function. If delivery fails (common on local environments), the email payload is appended to `data/mail.log` so you can review what would have been sent. Configure your server's mail transport or connect a transactional provider for production use.

## Payments

Stripe and PayPal credentials are stored in settings for convenience. Orders are created immediately; update payment statuses manually or extend the integration with webhooks as required for your workflow.

## Customisation

Adjust the brand colour, logo URL, and font family directly from the admin dashboard. Additional styling hooks are exposed via `static/css/style.css`.

## Development Notes

- Database schema lives in `database.php` and is created automatically when the site first loads.
- Application routes are handled through standalone PHP files (`login.php`, `signup.php`, `dashboard.php`, etc.).
- Tailwind CSS is consumed via CDN for rapid styling; supplement with `static/css/style.css` for bespoke tweaks.

## Testing Locally

If you have PHP installed locally you can use the built-in development server:

```bash
php -S localhost:8000
```

Then browse to `http://localhost:8000/login.php`.

## Security Considerations

- Passwords are hashed using PHP's `password_hash` / `password_verify` helpers.
- Sessions are regenerated on login/logout.
- Further hardening (CSRF tokens, rate limiting, audit logs) can be added as needed for production deployments.
