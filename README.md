# Client Portal

This project provides a self-hosted client portal with an administrative dashboard.

## Features

- Email/password authentication for admins and clients.
- Admin console for managing services, custom intake forms, orders, support tickets, clients, and settings.
- Client area for viewing dashboards, requesting services, tracking orders, managing tickets, and downloading shared files.
- Visual form builder for services and reusable form templates.
- Stripe Checkout and PayPal order creation (requires API keys and internet access) for collecting payments.
- SMTP-based email notifications with editable templates and Microsoft 365 credential storage fields.
- Branding controls for logo, colours, and typography.
- SQLite-backed persistence with automatic migration.

## Requirements

- Python 3.11 or later.
- Network access is required for third-party integrations (Stripe, PayPal, Microsoft 365 SMTP).

All dependencies rely on the Python standard library so no additional packages need to be installed.

## Running the Portal

```bash
python app.py
```

The server listens on `http://0.0.0.0:8000` by default. Log in with the seeded admin account:

- **Email:** `admin@example.com`
- **Password:** `admin123!`

## Configuration

Most configuration options are available inside the admin dashboard under **Settings**. SMTP credentials must be configured before email notifications will send. Stripe and PayPal credentials are optional but required for payment links.

## Database

The application stores data in `data/portal.sqlite3`. The schema is created automatically on first run.

## Email Templates

Email templates can be customised in the admin dashboard. Templates support placeholder variables like `{{client_name}}`, `{{service_name}}`, etc.

## Limitations

- File uploads are disabled until storage integrations are added; clients see a placeholder message.
- Payment status tracking relies on manual updates or future webhook integration.
- Microsoft 365 integration fields are stored but further integration (e.g., Graph API) must be implemented separately.
