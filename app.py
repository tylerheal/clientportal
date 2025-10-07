import base64
import datetime as dt
import json
import secrets
import sqlite3
import urllib.parse
import urllib.request
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
import re
import hashlib
import hmac
import smtplib
from email.message import EmailMessage
import ssl

ROOT = Path(__file__).parent
DB_PATH = ROOT / 'data' / 'portal.sqlite3'
STATIC_PATH = ROOT / 'static'
TEMPLATES_PATH = ROOT / 'templates'
SESSION_COOKIE = 'portal_session'
SESSION_TTL = dt.timedelta(days=7)

DEFAULT_EMAIL_TEMPLATES = [
    {
        'slug': 'order_new_admin',
        'name': 'New order notification (admin)',
        'subject': 'New order from {{client_name}}',
        'body': 'A new order for {{service_name}} has been placed by {{client_name}}. Total: {{currency}}{{total_amount}}.'
    },
    {
        'slug': 'order_confirmation_client',
        'name': 'Order confirmation (client)',
        'subject': 'We received your order for {{service_name}}',
        'body': 'Hi {{client_name}},\n\nThanks for ordering {{service_name}}. We will review it and get back to you shortly.'
    },
    {
        'slug': 'ticket_new_admin',
        'name': 'New ticket (admin)',
        'subject': 'Support request from {{client_name}}',
        'body': 'A new support ticket "{{subject}}" was opened by {{client_name}}.'
    },
    {
        'slug': 'ticket_reply_client',
        'name': 'Ticket reply (client)',
        'subject': 'New reply to "{{subject}}"',
        'body': 'Hi {{client_name}},\n\nWe have responded to your ticket "{{subject}}". Log in to view the message.'
    },
    {
        'slug': 'invite_client',
        'name': 'Client invitation',
        'subject': 'You have been invited to the client portal',
        'body': 'Hi {{client_name}},\n\nWe created an account for you. Use the following password to sign in: {{password}}\n\nPortal URL: {{portal_url}}'
    },
    {
        'slug': 'payment_reminder',
        'name': 'Payment reminder',
        'subject': 'Payment reminder for {{service_name}}',
        'body': 'Hi {{client_name}},\n\nThis is a friendly reminder that payment for {{service_name}} is {{payment_status}}. Please complete the payment.'
    }
]


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def ensure_db():
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = get_db()
    cur = conn.cursor()
    cur.execute('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT UNIQUE,
            password_hash TEXT,
            role TEXT CHECK(role in ('admin','client')) NOT NULL DEFAULT 'client',
            company TEXT,
            created_at TEXT NOT NULL,
            invited_by INTEGER,
            FOREIGN KEY(invited_by) REFERENCES users(id)
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT UNIQUE,
            user_id INTEGER NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            price REAL NOT NULL,
            billing_cycle TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            form_schema TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    ''')
    cur.execute('''\n        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            payment_status TEXT NOT NULL DEFAULT 'pending',
            total_amount REAL NOT NULL,
            currency TEXT NOT NULL DEFAULT '$',
            form_data TEXT,
            payment_provider TEXT,
            external_id TEXT,
            checkout_url TEXT,
            due_date TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(service_id) REFERENCES services(id)
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'open',
            priority TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS ticket_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            user_id INTEGER,
            message TEXT NOT NULL,
            is_staff INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(ticket_id) REFERENCES tickets(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS email_templates (
            slug TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ''')
    cur.execute('''
        CREATE TABLE IF NOT EXISTS form_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            schema TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    ''')
    conn.commit()
    # seed admin user
    cur.execute('SELECT COUNT(*) FROM users WHERE role="admin"')
    if cur.fetchone()[0] == 0:
        password = 'admin123!'
        cur.execute('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?,?,?,?,?)', (
            'Administrator',
            'admin@example.com',
            hash_password(password),
            'admin',
            now()
        ))
        conn.commit()
    # seed templates
    for tpl in DEFAULT_EMAIL_TEMPLATES:
        cur.execute('INSERT OR IGNORE INTO email_templates (slug, name, subject, body) VALUES (?,?,?,?)', (
            tpl['slug'], tpl['name'], tpl['subject'], tpl['body']
        ))
    conn.commit()
    conn.close()


def now():
    return dt.datetime.utcnow().replace(microsecond=0).isoformat() + 'Z'


def hash_password(password: str) -> str:
    salt = secrets.token_bytes(16)
    digest = hashlib.pbkdf2_hmac('sha256', password.encode('utf-8'), salt, 200000)
    return base64.b64encode(salt).decode() + '$' + base64.b64encode(digest).decode()


def verify_password(password: str, stored: str) -> bool:
    try:
        salt_b64, hash_b64 = stored.split('$')
        salt = base64.b64decode(salt_b64.encode())
        expected = base64.b64decode(hash_b64.encode())
        digest = hashlib.pbkdf2_hmac('sha256', password.encode('utf-8'), salt, 200000)
        return hmac.compare_digest(expected, digest)
    except Exception:
        return False


def parse_body(handler: BaseHTTPRequestHandler):
    length = int(handler.headers.get('Content-Length', '0') or 0)
    if length == 0:
        return {}
    data = handler.rfile.read(length)
    content_type = handler.headers.get('Content-Type', '')
    if 'application/json' in content_type:
        try:
            return json.loads(data.decode())
        except json.JSONDecodeError:
            return {}
    elif 'application/x-www-form-urlencoded' in content_type:
        return {k: v[0] if len(v) == 1 else v for k, v in urllib.parse.parse_qs(data.decode()).items()}
    else:
        return {}


def read_static(path: Path):
    if not path.exists() or not path.is_file():
        return None, None
    mime = 'application/octet-stream'
    if path.suffix in {'.css'}:
        mime = 'text/css'
    elif path.suffix in {'.js'}:
        mime = 'text/javascript'
    elif path.suffix in {'.svg'}:
        mime = 'image/svg+xml'
    elif path.suffix in {'.png'}:
        mime = 'image/png'
    elif path.suffix in {'.jpg', '.jpeg'}:
        mime = 'image/jpeg'
    elif path.suffix in {'.ico'}:
        mime = 'image/x-icon'
    return path.read_bytes(), mime


_placeholder_pattern = re.compile(r'{{\s*([\w\.]+)\s*}}')


def render_template(template_name: str, context: dict | None = None) -> str:
    context = context or {}
    template_path = TEMPLATES_PATH / template_name
    html = template_path.read_text(encoding='utf-8')

    def repl(match: re.Match) -> str:
        key = match.group(1)
        return str(context.get(key, ''))

    return _placeholder_pattern.sub(repl, html)


def render_page(
    content_template: str,
    *,
    base_context: dict | None = None,
    layout: str | None = None,
    layout_context: dict | None = None,
    scripts: list[str] | None = None,
    content_context: dict | None = None,
) -> str:
    scripts = scripts or []
    base_context = base_context or {}
    content_context = content_context or {}
    content_html = render_template(content_template, content_context)
    if layout:
        layout_ctx = (layout_context or {}).copy()
        layout_ctx['content'] = content_html
        content_html = render_template(layout, layout_ctx)
    ctx_json = json.dumps(base_context.get('context', {})).replace('</', '<\/')
    base_values = {
        'title': base_context.get('title', 'Client Portal'),
        'context_json': ctx_json,
        'content': content_html,
        'scripts': '\n'.join(f'<script src="{src}" defer></script>' for src in scripts)
    }
    return render_template('base.html', base_values)


def get_session(handler: BaseHTTPRequestHandler):
    cookie_header = handler.headers.get('Cookie', '')
    cookies = {}
    for part in cookie_header.split(';'):
        if '=' in part:
            k, v = part.strip().split('=', 1)
            cookies[k] = v
    token = cookies.get(SESSION_COOKIE)
    if not token:
        return None
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT user_id, expires_at FROM sessions WHERE token = ?', (token,))
    row = cur.fetchone()
    if not row:
        conn.close()
        return None
    expires = dt.datetime.fromisoformat(row['expires_at'].replace('Z', ''))
    if expires < dt.datetime.utcnow():
        cur.execute('DELETE FROM sessions WHERE token = ?', (token,))
        conn.commit()
        conn.close()
        return None
    cur.execute('SELECT * FROM users WHERE id = ?', (row['user_id'],))
    user = cur.fetchone()
    conn.close()
    return user


def create_session(handler: BaseHTTPRequestHandler, user_id: int):
    token = secrets.token_hex(32)
    expires = dt.datetime.utcnow() + SESSION_TTL
    conn = get_db()
    cur = conn.cursor()
    cur.execute('INSERT INTO sessions (token, user_id, expires_at) VALUES (?,?,?)', (token, user_id, expires.isoformat() + 'Z'))
    conn.commit()
    conn.close()
    handler.send_header('Set-Cookie', f'{SESSION_COOKIE}={token}; Path=/; Max-Age={int(SESSION_TTL.total_seconds())}; HttpOnly; SameSite=Lax')


def destroy_session(handler: BaseHTTPRequestHandler):
    cookie_header = handler.headers.get('Cookie', '')
    cookies = {}
    for part in cookie_header.split(';'):
        if '=' in part:
            k, v = part.strip().split('=', 1)
            cookies[k] = v
    token = cookies.get(SESSION_COOKIE)
    if not token:
        return
    conn = get_db()
    cur = conn.cursor()
    cur.execute('DELETE FROM sessions WHERE token = ?', (token,))
    conn.commit()
    conn.close()
    handler.send_header('Set-Cookie', f'{SESSION_COOKIE}=deleted; Path=/; Max-Age=0; HttpOnly; SameSite=Lax')


def get_settings() -> dict:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT key, value FROM settings')
    settings = {row['key']: json.loads(row['value']) if row['value'] else {} for row in cur.fetchall()}
    conn.close()
    return settings


def save_settings(key: str, data: dict):
    conn = get_db()
    cur = conn.cursor()
    cur.execute('REPLACE INTO settings (key, value) VALUES (?, ?)', (key, json.dumps(data)))
    conn.commit()
    conn.close()


def send_email(slug: str, to_addresses: list[str], context: dict):
    recipients = [addr for addr in to_addresses if addr]
    if not recipients:
        return
    settings = get_settings()
    email_settings = settings.get('email', {})
    if not email_settings.get('smtp_host'):
        return
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT subject, body FROM email_templates WHERE slug = ?', (slug,))
    tpl = cur.fetchone()
    conn.close()
    if not tpl:
        return
    subject = interpolate(tpl['subject'], context)
    body = interpolate(tpl['body'], context)
    message = EmailMessage()
    from_email = email_settings.get('from_email') or 'no-reply@example.com'
    from_name = email_settings.get('from_name') or 'Client Portal'
    message['From'] = f"{from_name} <{from_email}>"
    message['To'] = ', '.join(recipients)
    message['Subject'] = subject
    message.set_content(body)
    smtp_host = email_settings.get('smtp_host')
    smtp_port = int(email_settings.get('smtp_port') or 587)
    smtp_username = email_settings.get('smtp_username')
    smtp_password = email_settings.get('smtp_password')
    use_tls = str(email_settings.get('smtp_tls', 'true')).lower() != 'false'
    try:
        if use_tls and smtp_port == 465:
            with smtplib.SMTP_SSL(smtp_host, smtp_port, context=ssl.create_default_context()) as smtp:
                if smtp_username and smtp_password:
                    smtp.login(smtp_username, smtp_password)
                smtp.send_message(message)
        else:
            with smtplib.SMTP(smtp_host, smtp_port) as smtp:
                if use_tls:
                    smtp.starttls(context=ssl.create_default_context())
                if smtp_username and smtp_password:
                    smtp.login(smtp_username, smtp_password)
                smtp.send_message(message)
    except Exception as exc:
        print('Email send failed:', exc)


def interpolate(template: str, context: dict) -> str:
    result = template
    for key, value in context.items():
        result = result.replace(f'{{{{{key}}}}}', str(value))
    return result


def create_checkout_session(order_id: int, provider: str = 'stripe') -> dict:
    settings = get_settings()
    billing = settings.get('billing', {})
    conn = get_db()
    cur = conn.cursor()
    cur.execute('''
        SELECT orders.id, orders.total_amount, orders.currency, users.email, services.name AS service_name
        FROM orders
        JOIN users ON users.id = orders.user_id
        JOIN services ON services.id = orders.service_id
        WHERE orders.id = ?
    ''', (order_id,))
    order = cur.fetchone()
    conn.close()
    if not order:
        raise LookupError('Order not found')
    if provider == 'stripe':
        secret = billing.get('stripe_secret_key')
        if not secret:
            return {'checkout_url': None}
        currency_code = order['currency'] if len(order['currency']) > 1 else 'USD'
        payload = urllib.parse.urlencode({
            'success_url': billing.get('success_url', 'https://example.com/success'),
            'cancel_url': billing.get('cancel_url', 'https://example.com/cancel'),
            'payment_method_types[0]': 'card',
            'line_items[0][price_data][currency]': currency_code.lower(),
            'line_items[0][price_data][product_data][name]': order['service_name'],
            'line_items[0][price_data][unit_amount]': int(order['total_amount'] * 100),
            'line_items[0][quantity]': 1,
            'mode': 'payment'
        })
        req = urllib.request.Request('https://api.stripe.com/v1/checkout/sessions', data=payload.encode(), method='POST')
        req.add_header('Authorization', f'Bearer {secret}')
        req.add_header('Content-Type', 'application/x-www-form-urlencoded')
        try:
            with urllib.request.urlopen(req, timeout=10) as resp:
                data = json.loads(resp.read().decode())
                return {'checkout_url': data.get('url'), 'external_id': data.get('id')}
        except Exception as exc:
            print('Stripe request failed:', exc)
            return {'checkout_url': None}
    elif provider == 'paypal':
        client_id = billing.get('paypal_client_id')
        client_secret = billing.get('paypal_client_secret')
        if not client_id or not client_secret:
            return {'checkout_url': None}
        auth_req = urllib.request.Request('https://api-m.paypal.com/v1/oauth2/token', data=b'grant_type=client_credentials')
        auth = base64.b64encode(f'{client_id}:{client_secret}'.encode()).decode()
        auth_req.add_header('Authorization', f'Basic {auth}')
        auth_req.add_header('Content-Type', 'application/x-www-form-urlencoded')
        try:
            with urllib.request.urlopen(auth_req, timeout=10) as resp:
                token_data = json.loads(resp.read().decode())
        except Exception as exc:
            print('PayPal auth failed:', exc)
            return {'checkout_url': None}
        order_payload = json.dumps({
            'intent': 'CAPTURE',
            'purchase_units': [{
                'amount': {
                    'currency_code': order['currency'] or 'USD',
                    'value': f"{order['total_amount']:.2f}"
                },
                'description': order['service_name']
            }],
            'application_context': {
                'return_url': billing.get('success_url', 'https://example.com/success'),
                'cancel_url': billing.get('cancel_url', 'https://example.com/cancel')
            }
        }).encode()
        order_req = urllib.request.Request('https://api-m.paypal.com/v2/checkout/orders', data=order_payload, method='POST')
        order_req.add_header('Authorization', f"Bearer {token_data.get('access_token')}")
        order_req.add_header('Content-Type', 'application/json')
        try:
            with urllib.request.urlopen(order_req, timeout=10) as resp:
                order_data = json.loads(resp.read().decode())
                approve_link = next((link['href'] for link in order_data.get('links', []) if link.get('rel') == 'approve'), None)
                return {'checkout_url': approve_link, 'external_id': order_data.get('id')}
        except Exception as exc:
            print('PayPal order failed:', exc)
            return {'checkout_url': None}
    return {'checkout_url': None}


class PortalHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.route('GET')

    def do_POST(self):
        self.route('POST')

    def do_PUT(self):
        self.route('PUT')

    def do_DELETE(self):
        self.route('DELETE')

    def route(self, method: str):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path
        user = get_session(self)
        settings = get_settings()
        branding = settings.get('branding', {})
        base_context = {
            'title': branding.get('brand_name', 'Client Portal'),
            'context': {
                'branding': branding
            }
        }
        # static files
        if path.startswith('/static/'):
            relative = Path(path.replace('/static/', ''))
            file_path = (STATIC_PATH / relative).resolve()
            if not str(file_path).startswith(str(STATIC_PATH.resolve())):
                self.send_error(HTTPStatus.FORBIDDEN)
                return
            data, mime = read_static(file_path)
            if data is None:
                self.send_error(HTTPStatus.NOT_FOUND)
                return
            self.send_response(HTTPStatus.OK)
            self.send_header('Content-Type', mime)
            self.send_header('Content-Length', str(len(data)))
            self.end_headers()
            self.wfile.write(data)
            return
        try:
            if path in {'/', '/login'} and method == 'GET':
                if user:
                    if user['role'] == 'admin':
                        self.redirect('/admin')
                    else:
                        self.redirect('/client')
                    return
                html = render_page('login.html', base_context=base_context)
                self.respond_html(html)
                return
            if path == '/signup' and method == 'GET':
                if user:
                    self.redirect('/client')
                    return
                html = render_page('signup.html', base_context=base_context)
                self.respond_html(html)
                return
            if path == '/auth/login' and method == 'POST':
                data = parse_body(self)
                email = data.get('email', '').strip().lower()
                password = data.get('password', '')
                conn = get_db()
                cur = conn.cursor()
                cur.execute('SELECT * FROM users WHERE email = ?', (email,))
                account = cur.fetchone()
                conn.close()
                if not account or not verify_password(password, account['password_hash'] or ''):
                    self.send_error(HTTPStatus.UNAUTHORIZED, 'Invalid credentials')
                    return
                self.send_response(HTTPStatus.SEE_OTHER)
                create_session(self, account['id'])
                self.send_header('Location', '/admin' if account['role'] == 'admin' else '/client')
                self.end_headers()
                return
            if path == '/auth/signup' and method == 'POST':
                data = parse_body(self)
                email = data.get('email', '').strip().lower()
                password = data.get('password', '')
                name = f"{data.get('first_name', '').strip()} {data.get('last_name', '').strip()}".strip()
                company = data.get('company', '').strip()
                if not email or not password or not name:
                    self.send_error(HTTPStatus.BAD_REQUEST, 'Missing fields')
                    return
                conn = get_db()
                cur = conn.cursor()
                try:
                    cur.execute('INSERT INTO users (name, email, password_hash, role, company, created_at) VALUES (?,?,?,?,?,?)', (
                        name,
                        email,
                        hash_password(password),
                        'client',
                        company,
                        now()
                    ))
                    conn.commit()
                    user_id = cur.lastrowid
                except sqlite3.IntegrityError:
                    conn.close()
                    self.send_error(HTTPStatus.CONFLICT, 'Email already exists')
                    return
                conn.close()
                self.send_response(HTTPStatus.SEE_OTHER)
                create_session(self, user_id)
                self.send_header('Location', '/client')
                self.end_headers()
                return
            if path == '/auth/logout' and method == 'POST':
                self.send_response(HTTPStatus.SEE_OTHER)
                destroy_session(self)
                self.send_header('Location', '/login')
                self.end_headers()
                return
            if path.startswith('/admin'):
                if not user or user['role'] != 'admin':
                    self.redirect('/login')
                    return
                if method == 'GET':
                    scripts = ['/static/js/admin.js']
                    sub_path = path.replace('/admin', '') or '/'
                    content_template = 'admin_overview.html'
                    if sub_path == '/services':
                        content_template = 'admin_services.html'
                    elif sub_path == '/orders':
                        content_template = 'admin_orders.html'
                    elif sub_path == '/tickets':
                        content_template = 'admin_tickets.html'
                    elif sub_path == '/clients':
                        content_template = 'admin_clients.html'
                    elif sub_path == '/forms':
                        content_template = 'admin_forms.html'
                    elif sub_path == '/settings':
                        content_template = 'admin_settings.html'
                    layout_context = {
                        'logo_url': branding.get('logo_url', ''),
                        'brand_name': branding.get('brand_name', 'Client Portal')
                    }
                    html = render_page(
                        content_template,
                        base_context=base_context,
                        layout='admin.html',
                        layout_context=layout_context,
                        scripts=scripts,
                        content_context={}
                    )
                    self.respond_html(html)
                    return
            if path.startswith('/client'):
                if not user or user['role'] != 'client':
                    self.redirect('/login')
                    return
                if method == 'GET':
                    scripts = ['/static/js/client.js']
                    sub_path = path.replace('/client', '') or '/'
                    content_template = 'client_dashboard.html'
                    content_context = {
                        'user_name': user['name']
                    }
                    layout_context = {
                        'logo_url': branding.get('logo_url', ''),
                        'brand_name': branding.get('brand_name', 'Client Portal')
                    }
                    if sub_path == '/services':
                        content_template = 'client_services.html'
                    elif sub_path == '/orders':
                        content_template = 'client_orders.html'
                    elif sub_path == '/tickets':
                        content_template = 'client_tickets.html'
                    elif sub_path == '/files':
                        content_template = 'client_files.html'
                    html = render_page(
                        content_template,
                        base_context=base_context,
                        layout='client.html',
                        layout_context=layout_context,
                        scripts=scripts,
                        content_context=content_context
                    )
                    self.respond_html(html)
                    return
            if path.startswith('/api/'):
                self.handle_api(method, path, parsed.query, user)
                return
            if path == '/' and method == 'GET':
                self.redirect('/login')
                return
            self.send_error(HTTPStatus.NOT_FOUND)
        except Exception as exc:
            print('Error handling request', method, path, exc)
            self.send_error(HTTPStatus.INTERNAL_SERVER_ERROR, str(exc))

    def respond_html(self, html: str, status: HTTPStatus = HTTPStatus.OK):
        data = html.encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def respond_json(self, payload: dict | list, status: HTTPStatus = HTTPStatus.OK):
        data = json.dumps(payload).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def redirect(self, location: str):
        self.send_response(HTTPStatus.SEE_OTHER)
        self.send_header('Location', location)
        self.end_headers()

    def handle_api(self, method: str, path: str, query: str, user):
        if not user:
            self.send_error(HTTPStatus.UNAUTHORIZED)
            return
        parsed_query = urllib.parse.parse_qs(query)
        try:
            if path.startswith('/api/admin/'):
                if user['role'] != 'admin':
                    self.send_error(HTTPStatus.FORBIDDEN)
                    return
                if path == '/api/admin/overview' and method == 'GET':
                    self.respond_json(admin_overview())
                    return
                if path == '/api/admin/services':
                    if method == 'GET':
                        self.respond_json(list_services(include_form=True))
                        return
                    elif method == 'POST':
                        data = parse_body(self)
                        service = save_service(data)
                        self.respond_json(service, HTTPStatus.CREATED)
                        return
                if path.startswith('/api/admin/services/'):
                    service_id = int(path.split('/')[-1])
                    if method == 'PUT':
                        data = parse_body(self)
                        service = save_service(data, service_id)
                        self.respond_json(service)
                        return
                    elif method == 'DELETE':
                        delete_service(service_id)
                        self.respond_json({'ok': True})
                        return
                if path == '/api/admin/orders' and method == 'GET':
                    status_filter = parsed_query.get('status', ['all'])[0]
                    self.respond_json(list_orders(status_filter))
                    return
                if path.startswith('/api/admin/orders/') and path.endswith('/payment') and method == 'PUT':
                    order_id = int(path.split('/')[-2])
                    data = parse_body(self)
                    update_payment_status(order_id, data.get('status', 'pending'))
                    self.respond_json({'ok': True})
                    return
                if path == '/api/admin/tickets' and method == 'GET':
                    search = parsed_query.get('q', [''])[0]
                    self.respond_json(list_tickets(search))
                    return
                if path.startswith('/api/admin/tickets/') and method == 'GET':
                    ticket_id = int(path.split('/')[-1])
                    self.respond_json(get_ticket(ticket_id))
                    return
                if path.startswith('/api/admin/tickets/') and path.endswith('/reply') and method == 'POST':
                    ticket_id = int(path.split('/')[-2])
                    data = parse_body(self)
                    add_ticket_message(ticket_id, user['id'], data.get('message', ''), is_staff=True)
                    self.respond_json({'ok': True})
                    return
                if path == '/api/admin/clients' and method == 'GET':
                    self.respond_json(list_clients())
                    return
                if path == '/api/admin/clients/invite' and method == 'POST':
                    data = parse_body(self)
                    invite_client(data, inviter_id=user['id'])
                    self.respond_json({'ok': True})
                    return
                if path == '/api/admin/forms':
                    if method == 'GET':
                        self.respond_json(list_form_templates())
                        return
                    elif method == 'POST':
                        data = parse_body(self)
                        template = save_form_template(data)
                        self.respond_json(template, HTTPStatus.CREATED)
                        return
                if path.startswith('/api/admin/forms/'):
                    template_id = int(path.split('/')[-1])
                    if method == 'PUT':
                        data = parse_body(self)
                        template = save_form_template(data, template_id)
                        self.respond_json(template)
                        return
                    elif method == 'DELETE':
                        delete_form_template(template_id)
                        self.respond_json({'ok': True})
                        return
                if path == '/api/admin/settings' and method == 'GET':
                    self.respond_json(get_settings())
                    return
                if path.startswith('/api/admin/settings/') and method == 'PUT':
                    section = path.split('/')[-1]
                    data = parse_body(self)
                    save_settings(section, data)
                    self.respond_json({'ok': True})
                    return
                if path == '/api/admin/email-templates' and method == 'GET':
                    self.respond_json(list_email_templates())
                    return
                if path.startswith('/api/admin/email-templates/') and method == 'PUT':
                    slug = path.split('/')[-1]
                    data = parse_body(self)
                    update_email_template(slug, data)
                    self.respond_json({'ok': True})
                    return
            if path.startswith('/api/client/'):
                if user['role'] != 'client':
                    self.send_error(HTTPStatus.FORBIDDEN)
                    return
                if path == '/api/client/overview' and method == 'GET':
                    self.respond_json(client_overview(user['id']))
                    return
                if path == '/api/client/services' and method == 'GET':
                    self.respond_json(list_services())
                    return
                if path == '/api/client/orders' and method == 'GET':
                    self.respond_json(client_orders(user['id']))
                    return
                if path == '/api/client/orders' and method == 'POST':
                    data = parse_body(self)
                    order = create_order(user['id'], data)
                    self.respond_json(order, HTTPStatus.CREATED)
                    return
                if path.startswith('/api/client/orders/') and method == 'GET':
                    order_id = int(path.split('/')[-1])
                    self.respond_json(get_order_detail(order_id, user['id']))
                    return
                if path.startswith('/api/client/orders/') and path.endswith('/checkout') and method == 'POST':
                    order_id = int(path.split('/')[-2])
                    session = create_checkout(order_id)
                    self.respond_json(session)
                    return
                if path == '/api/client/tickets' and method == 'GET':
                    search = parsed_query.get('q', [''])[0]
                    self.respond_json(client_tickets(user['id'], search))
                    return
                if path == '/api/client/tickets' and method == 'POST':
                    data = parse_body(self)
                    ticket = create_ticket(user['id'], data)
                    self.respond_json(ticket, HTTPStatus.CREATED)
                    return
                if path.startswith('/api/client/tickets/') and method == 'GET':
                    ticket_id = int(path.split('/')[-1])
                    self.respond_json(get_ticket(ticket_id, user['id']))
                    return
                if path.startswith('/api/client/tickets/') and path.endswith('/reply') and method == 'POST':
                    ticket_id = int(path.split('/')[-2])
                    data = parse_body(self)
                    add_ticket_message(ticket_id, user['id'], data.get('message', ''), is_staff=False)
                    self.respond_json({'ok': True})
                    return
                if path == '/api/client/files' and method == 'GET':
                    self.respond_json(client_files(user['id']))
                    return
            self.send_error(HTTPStatus.NOT_FOUND)
        except LookupError as exc:
            self.send_error(HTTPStatus.NOT_FOUND, str(exc))
        except ValueError as exc:
            self.send_error(HTTPStatus.BAD_REQUEST, str(exc))
        except Exception as exc:
            print('API error', path, exc)
            self.send_error(HTTPStatus.INTERNAL_SERVER_ERROR, str(exc))


def admin_overview() -> dict:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT COUNT(*) FROM users WHERE role = "client"')
    active_clients = cur.fetchone()[0]
    cur.execute('SELECT COUNT(*) FROM services WHERE is_active = 1')
    active_services = cur.fetchone()[0]
    cur.execute('SELECT COUNT(*) FROM tickets WHERE status != "closed"')
    open_tickets = cur.fetchone()[0]
    cur.execute('SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE status = "active"')
    mrr = cur.fetchone()[0]
    cur.execute('''
        SELECT orders.id, users.name as client_name, services.name as service_name, orders.total_amount, orders.payment_status, orders.created_at
        FROM orders
        JOIN users ON users.id = orders.user_id
        JOIN services ON services.id = orders.service_id
        ORDER BY orders.created_at DESC
        LIMIT 5
    ''')
    recent_orders = [dict(row) for row in cur.fetchall()]
    cur.execute('''
        SELECT tickets.id, tickets.subject, users.name as client_name, tickets.status, tickets.updated_at
        FROM tickets
        JOIN users ON users.id = tickets.user_id
        WHERE tickets.status != 'closed'
        ORDER BY tickets.updated_at DESC
        LIMIT 5
    ''')
    tickets = [dict(row) for row in cur.fetchall()]
    settings = get_settings()
    currency = settings.get('billing', {}).get('currency', '$')
    conn.close()
    return {
        'active_clients': active_clients,
        'active_services': active_services,
        'open_tickets': open_tickets,
        'mrr': float(mrr or 0),
        'recent_orders': recent_orders,
        'open_ticket_threads': tickets,
        'currency': currency
    }


def list_services(include_form: bool = False) -> list:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC')
    rows = cur.fetchall()
    conn.close()
    services = []
    settings = get_settings()
    currency = settings.get('billing', {}).get('currency', '$')
    for row in rows:
        schema = json.loads(row['form_schema']) if row['form_schema'] else []
        service = {
            'id': row['id'],
            'name': row['name'],
            'description': row['description'],
            'price': float(row['price']),
            'billing_cycle': row['billing_cycle'],
            'currency': currency,
            'updated_at': row['updated_at']
        }
        if include_form:
            service['form_schema'] = schema
        else:
            service['form_schema'] = schema
        services.append(service)
    return services


def save_service(data: dict, service_id: int | None = None) -> dict:
    name = data.get('name', '').strip()
    description = data.get('description', '').strip()
    price = float(data.get('price') or 0)
    billing_cycle = data.get('billing_cycle', 'one-off')
    form_schema_data = data.get('form_schema') or []
    if isinstance(form_schema_data, str):
        form_schema_data = json.loads(form_schema_data)
    form_schema = json.dumps(form_schema_data)
    timestamp = now()
    conn = get_db()
    cur = conn.cursor()
    if service_id:
        cur.execute('''
            UPDATE services
            SET name=?, description=?, price=?, billing_cycle=?, form_schema=?, updated_at=?
            WHERE id=?
        ''', (name, description, price, billing_cycle, form_schema, timestamp, service_id))
    else:
        cur.execute('''
            INSERT INTO services (name, description, price, billing_cycle, form_schema, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?)
        ''', (name, description, price, billing_cycle, form_schema, timestamp, timestamp))
        service_id = cur.lastrowid
    conn.commit()
    conn.close()
    return {'id': service_id, 'name': name}


def delete_service(service_id: int):
    conn = get_db()
    cur = conn.cursor()
    cur.execute('DELETE FROM services WHERE id = ?', (service_id,))
    conn.commit()
    conn.close()


def list_orders(status_filter: str = 'all') -> list:
    conn = get_db()
    cur = conn.cursor()
    query = '''
        SELECT orders.*, users.name AS client_name, services.name AS service_name
        FROM orders
        JOIN users ON users.id = orders.user_id
        JOIN services ON services.id = orders.service_id
    '''
    params = []
    if status_filter and status_filter != 'all':
        query += ' WHERE orders.payment_status = ?'
        params.append(status_filter)
    query += ' ORDER BY orders.created_at DESC'
    cur.execute(query, params)
    rows = cur.fetchall()
    conn.close()
    return [
        {
            'id': row['id'],
            'client_name': row['client_name'],
            'service_name': row['service_name'],
            'total_amount': float(row['total_amount']),
            'status': row['status'],
            'payment_status': row['payment_status'],
            'created_at': row['created_at'],
            'currency': row['currency']
        }
        for row in rows
    ]


def update_payment_status(order_id: int, status: str):
    conn = get_db()
    cur = conn.cursor()
    cur.execute('UPDATE orders SET payment_status = ?, updated_at = ? WHERE id = ?', (status, now(), order_id))
    conn.commit()
    conn.close()


def list_tickets(search: str) -> list:
    conn = get_db()
    cur = conn.cursor()
    if search:
        cur.execute('''
            SELECT tickets.*, users.name AS client_name
            FROM tickets
            JOIN users ON users.id = tickets.user_id
            WHERE tickets.subject LIKE ?
            ORDER BY tickets.updated_at DESC
        ''', (f'%{search}%',))
    else:
        cur.execute('''
            SELECT tickets.*, users.name AS client_name
            FROM tickets
            JOIN users ON users.id = tickets.user_id
            ORDER BY tickets.updated_at DESC
        ''')
    rows = cur.fetchall()
    conn.close()
    return [
        {
            'id': row['id'],
            'subject': row['subject'],
            'status': row['status'],
            'client_name': row['client_name'],
            'updated_at': row['updated_at']
        }
        for row in rows
    ]


def get_ticket(ticket_id: int, user_id: int | None = None) -> dict:
    conn = get_db()
    cur = conn.cursor()
    if user_id:
        cur.execute('SELECT * FROM tickets WHERE id = ? AND user_id = ?', (ticket_id, user_id))
    else:
        cur.execute('SELECT * FROM tickets WHERE id = ?', (ticket_id,))
    ticket = cur.fetchone()
    if not ticket:
        conn.close()
        raise LookupError('Ticket not found')
    cur.execute('SELECT name FROM users WHERE id = ?', (ticket['user_id'],))
    client_name = cur.fetchone()[0]
    cur.execute('''
        SELECT ticket_messages.*, users.name AS user_name
        FROM ticket_messages
        LEFT JOIN users ON users.id = ticket_messages.user_id
        WHERE ticket_messages.ticket_id = ?
        ORDER BY ticket_messages.created_at ASC
    ''', (ticket_id,))
    messages = []
    for row in cur.fetchall():
        messages.append({
            'id': row['id'],
            'author': row['user_name'] or ('Team' if row['is_staff'] else 'Client'),
            'message': row['message'],
            'is_staff': bool(row['is_staff']),
            'created_at': row['created_at']
        })
    conn.close()
    return {
        'id': ticket['id'],
        'subject': ticket['subject'],
        'status': ticket['status'],
        'client_name': client_name,
        'messages': messages
    }


def add_ticket_message(ticket_id: int, user_id: int | None, message: str, *, is_staff: bool):
    message = (message or '').strip()
    if not message:
        raise ValueError('Message required')
    conn = get_db()
    cur = conn.cursor()
    cur.execute('INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff, created_at) VALUES (?,?,?,?,?)', (
        ticket_id,
        user_id,
        message,
        1 if is_staff else 0,
        now()
    ))
    cur.execute('UPDATE tickets SET updated_at = ?, status = ? WHERE id = ?', (now(), 'open', ticket_id))
    cur.execute('SELECT tickets.subject, tickets.user_id, users.email, users.name FROM tickets JOIN users ON users.id = tickets.user_id WHERE tickets.id = ?', (ticket_id,))
    ticket = cur.fetchone()
    conn.commit()
    conn.close()
    if is_staff:
        send_email('ticket_reply_client', [ticket['email']], {
            'client_name': ticket['name'],
            'subject': ticket['subject']
        })
    else:
        # notify admin
        settings = get_settings()
        admin_email = settings.get('email', {}).get('from_email')
        if admin_email:
            send_email('ticket_new_admin', [admin_email], {
                'client_name': ticket['name'],
                'subject': ticket['subject']
            })


def list_clients() -> list:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT id, name, email, company, created_at FROM users WHERE role = "client" ORDER BY created_at DESC')
    rows = cur.fetchall()
    conn.close()
    return [dict(row) for row in rows]


def invite_client(data: dict, inviter_id: int):
    email = data.get('email', '').strip().lower()
    name = data.get('name', '').strip()
    company = data.get('company', '').strip()
    password = secrets.token_urlsafe(10)
    conn = get_db()
    cur = conn.cursor()
    try:
        cur.execute('INSERT INTO users (name, email, password_hash, role, company, created_at, invited_by) VALUES (?,?,?,?,?,?,?)', (
            name,
            email,
            hash_password(password),
            'client',
            company,
            now(),
            inviter_id
        ))
        conn.commit()
    except sqlite3.IntegrityError as exc:
        conn.close()
        raise ValueError('Email already exists') from exc
    conn.close()
    send_email('invite_client', [email], {
        'client_name': name,
        'password': password,
        'portal_url': 'https://example.com/login'
    })


def list_form_templates() -> list:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT * FROM form_templates ORDER BY created_at DESC')
    rows = cur.fetchall()
    conn.close()
    return [
        {
            'id': row['id'],
            'name': row['name'],
            'description': row['description'],
            'schema': json.loads(row['schema'])
        }
        for row in rows
    ]


def save_form_template(data: dict, template_id: int | None = None) -> dict:
    name = data.get('name', '').strip()
    description = data.get('description', '').strip()
    schema_data = data.get('schema') or []
    if isinstance(schema_data, str):
        schema_data = json.loads(schema_data)
    schema = json.dumps(schema_data)
    timestamp = now()
    conn = get_db()
    cur = conn.cursor()
    if template_id:
        cur.execute('UPDATE form_templates SET name=?, description=?, schema=?, updated_at=? WHERE id=?', (name, description, schema, timestamp, template_id))
    else:
        cur.execute('INSERT INTO form_templates (name, description, schema, created_at, updated_at) VALUES (?,?,?,?,?)', (name, description, schema, timestamp, timestamp))
        template_id = cur.lastrowid
    conn.commit()
    conn.close()
    return {'id': template_id, 'name': name}


def delete_form_template(template_id: int):
    conn = get_db()
    cur = conn.cursor()
    cur.execute('DELETE FROM form_templates WHERE id = ?', (template_id,))
    conn.commit()
    conn.close()


def list_email_templates() -> list:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT slug, name, subject, body FROM email_templates ORDER BY slug ASC')
    rows = cur.fetchall()
    conn.close()
    return [dict(row) for row in rows]


def update_email_template(slug: str, data: dict):
    conn = get_db()
    cur = conn.cursor()
    cur.execute('UPDATE email_templates SET subject = ?, body = ? WHERE slug = ?', (data.get('subject', ''), data.get('body', ''), slug))
    conn.commit()
    conn.close()


def client_overview(user_id: int) -> dict:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT COUNT(*) FROM services WHERE is_active = 1')
    active_services = cur.fetchone()[0]
    cur.execute('SELECT COUNT(*) FROM orders WHERE user_id = ? AND status != "completed"', (user_id,))
    open_orders = cur.fetchone()[0]
    cur.execute('SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE user_id = ? AND payment_status != "paid"', (user_id,))
    outstanding_balance = cur.fetchone()[0]
    cur.execute('SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status != "closed"', (user_id,))
    open_tickets = cur.fetchone()[0]
    cur.execute('''
        SELECT * FROM orders
        WHERE user_id = ? AND payment_status != 'paid'
        ORDER BY COALESCE(due_date, created_at) ASC
        LIMIT 5
    ''', (user_id,))
    upcoming = [dict(row) for row in cur.fetchall()]
    cur.execute('''
        SELECT 'Order placed' AS title, services.name || ' order submitted' AS description, orders.created_at AS ts
        FROM orders
        JOIN services ON services.id = orders.service_id
        WHERE orders.user_id = ?
        UNION ALL
        SELECT 'Ticket created', subject || ' ticket opened', created_at AS ts
        FROM tickets
        WHERE user_id = ?
        ORDER BY ts DESC
        LIMIT 6
    ''', (user_id, user_id))
    activity = [
        {
            'title': row['title'],
            'description': row['description'],
            'timestamp': row['ts']
        }
        for row in cur.fetchall()
    ]
    conn.close()
    currency = get_settings().get('billing', {}).get('currency', '$')
    return {
        'active_services': active_services,
        'open_orders': open_orders,
        'outstanding_balance': float(outstanding_balance or 0),
        'open_tickets': open_tickets,
        'upcoming_payments': [
            {
                'id': item['id'],
                'service_name': get_service_name(item['service_id']),
                'total_amount': float(item['total_amount']),
                'payment_status': item['payment_status'],
                'due_date': item['due_date'] or item['created_at']
            }
            for item in upcoming
        ],
        'activity': activity,
        'currency': currency
    }


def get_service_name(service_id: int) -> str:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT name FROM services WHERE id = ?', (service_id,))
    row = cur.fetchone()
    conn.close()
    return row['name'] if row else 'Service'


def client_orders(user_id: int) -> list:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('''
        SELECT orders.*, services.name AS service_name
        FROM orders
        JOIN services ON services.id = orders.service_id
        WHERE orders.user_id = ?
        ORDER BY orders.created_at DESC
    ''', (user_id,))
    rows = cur.fetchall()
    conn.close()
    return [
        {
            'id': row['id'],
            'service_name': row['service_name'],
            'total_amount': float(row['total_amount']),
            'status': row['status'],
            'payment_status': row['payment_status'],
            'currency': row['currency'],
            'created_at': row['created_at']
        }
        for row in rows
    ]


def get_order_detail(order_id: int, user_id: int) -> dict:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('''
        SELECT orders.*, services.name AS service_name
        FROM orders
        JOIN services ON services.id = orders.service_id
        WHERE orders.id = ? AND orders.user_id = ?
    ''', (order_id, user_id))
    row = cur.fetchone()
    conn.close()
    if not row:
        raise LookupError('Order not found')
    return {
        'id': row['id'],
        'service_name': row['service_name'],
        'total_amount': float(row['total_amount']),
        'status': row['status'],
        'payment_status': row['payment_status'],
        'currency': row['currency'],
        'form_data': json.loads(row['form_data'] or '{}')
    }


def create_order(user_id: int, data: dict) -> dict:
    try:
        service_id = int(data.get('service_id'))
    except (TypeError, ValueError) as exc:
        raise ValueError('Invalid service') from exc
    responses = data.get('responses') or {}
    services = {service['id']: service for service in list_services(include_form=True)}
    if service_id not in services:
        raise ValueError('Service unavailable')
    service = services[service_id]
    conn = get_db()
    cur = conn.cursor()
    settings = get_settings()
    currency = settings.get('billing', {}).get('currency', '$')
    cur.execute('''
        INSERT INTO orders (user_id, service_id, total_amount, currency, form_data, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?)
    ''', (user_id, service_id, service['price'], currency, json.dumps(responses), now(), now()))
    order_id = cur.lastrowid
    conn.commit()
    cur.execute('SELECT email, name FROM users WHERE id = ?', (user_id,))
    client = cur.fetchone()
    conn.close()
    send_email('order_new_admin', [settings.get('email', {}).get('from_email', '')], {
        'client_name': client['name'],
        'service_name': service['name'],
        'total_amount': f"{service['price']:.2f}",
        'currency': currency
    })
    send_email('order_confirmation_client', [client['email']], {
        'client_name': client['name'],
        'service_name': service['name'],
        'currency': currency,
        'total_amount': f"{service['price']:.2f}"
    })
    return {'id': order_id}


def create_checkout(order_id: int) -> dict:
    session = create_checkout_session(order_id)
    conn = get_db()
    cur = conn.cursor()
    cur.execute('UPDATE orders SET checkout_url = ?, external_id = ?, updated_at = ? WHERE id = ?', (
        session.get('checkout_url'),
        session.get('external_id'),
        now(),
        order_id
    ))
    conn.commit()
    conn.close()
    return session


def client_tickets(user_id: int, search: str) -> list:
    conn = get_db()
    cur = conn.cursor()
    if search:
        cur.execute('SELECT * FROM tickets WHERE user_id = ? AND subject LIKE ? ORDER BY updated_at DESC', (user_id, f'%{search}%'))
    else:
        cur.execute('SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC', (user_id,))
    rows = cur.fetchall()
    conn.close()
    return [
        {
            'id': row['id'],
            'subject': row['subject'],
            'status': row['status'],
            'updated_at': row['updated_at']
        }
        for row in rows
    ]


def create_ticket(user_id: int, data: dict) -> dict:
    subject = data.get('subject', '').strip()
    message = data.get('message', '').strip()
    if not subject or not message:
        raise ValueError('Subject and message are required')
    conn = get_db()
    cur = conn.cursor()
    cur.execute('INSERT INTO tickets (user_id, subject, status, created_at, updated_at) VALUES (?,?,?,?,?)', (
        user_id,
        subject,
        'open',
        now(),
        now()
    ))
    ticket_id = cur.lastrowid
    cur.execute('INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff, created_at) VALUES (?,?,?,?,?)', (
        ticket_id,
        user_id,
        message,
        0,
        now()
    ))
    cur.execute('SELECT email, name FROM users WHERE id = ?', (user_id,))
    client = cur.fetchone()
    conn.commit()
    conn.close()
    settings = get_settings()
    admin_email = settings.get('email', {}).get('from_email')
    if admin_email:
        send_email('ticket_new_admin', [admin_email], {
            'client_name': client['name'],
            'subject': subject
        })
    return {'id': ticket_id}


def client_files(user_id: int) -> list:
    conn = get_db()
    cur = conn.cursor()
    cur.execute('SELECT name, url, description, created_at FROM files WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC', (user_id,))
    rows = cur.fetchall()
    conn.close()
    return [dict(row) for row in rows]


def run(host: str = '0.0.0.0', port: int = 8000):
    ensure_db()
    server = HTTPServer((host, port), PortalHandler)
    print(f'Client portal running on http://{host}:{port}')
    server.serve_forever()


if __name__ == '__main__':
    run()
