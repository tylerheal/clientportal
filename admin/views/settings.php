<?php
$companyName = get_setting('company_name', 'Service Portal');
$primaryColor = get_setting('brand_primary_color', '#2563eb');
$fontFamily = get_setting('brand_font_family', 'Inter, sans-serif');
$surfaceColor = get_setting('brand_surface_color', '#f4f6fb');
$cardColor = get_setting('brand_card_color', '#ffffff');
$controlColor = get_setting('brand_control_color', '#eef2ff');
$borderColor = get_setting('brand_border_color', '#dce1eb');
$textColor = get_setting('brand_text_color', '#111827');
$mutedColor = get_setting('brand_muted_color', '#6b7280');
$supportEmail = get_setting('support_email', '');
$logoSetting = get_setting('brand_logo_url', '');
$logoUrl = $logoSetting !== '' ? asset_url($logoSetting) : '';
$brandInitials = brand_initials($companyName);
$mailFromName = get_setting('mail_from_name', $companyName);
$mailFromAddress = get_setting('mail_from_address', $supportEmail ?: 'no-reply@example.com');
$mailTransport = get_setting('mail_transport', 'mail');
$smtpHost = get_setting('smtp_host', '');
$smtpPort = get_setting('smtp_port', '587');
$smtpUsername = get_setting('smtp_username', '');
$smtpEncryption = get_setting('smtp_encryption', 'tls');
$smtpPasswordStored = get_setting('smtp_password', '') !== '';
$turnstileEnabled = turnstile_enabled();
$turnstileSiteKey = get_setting('turnstile_site_key', '');
$turnstileSecretStored = get_setting('turnstile_secret_key', '') !== '';
?>
<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="card">
        <header class="card-header">
            <div>
                <h2>Brand identity</h2>
                <p>Upload your logo and fine-tune colours, fonts, and messaging shown across the portal.</p>
            </div>
        </header>
        <form action="<?= e(url_for('dashboard')); ?>" method="post" enctype="multipart/form-data" class="settings-grid">
            <input type="hidden" name="action" value="update_settings">
            <input type="hidden" name="redirect" value="admin/settings">
            <div class="settings-brand-panel">
                <div class="brand-preview">
                    <div class="brand-preview__mark">
                        <?php if ($logoUrl !== ''): ?>
                            <img src="<?= e($logoUrl); ?>" alt="<?= e($companyName); ?> logo">
                        <?php else: ?>
                            <span class="brand-placeholder" aria-hidden="true"><?= e($brandInitials); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="brand-preview__meta">
                        <strong><?= e($companyName); ?></strong>
                        <span>Sidebar preview</span>
                    </div>
                </div>
                <label class="file-field">Upload logo
                    <input type="file" name="brand_logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                    <span class="hint">PNG, JPG, SVG or WebP are supported. Uploading replaces the current logo.</span>
                </label>
                <div class="button-group">
                    <button type="submit" class="button button--ghost" name="clear_logo" value="1"<?= $logoUrl === '' ? ' disabled' : ''; ?> formnovalidate>Remove logo</button>
                </div>
            </div>
            <div class="settings-fields">
                <label>Company name
                    <input type="text" name="company_name" value="<?= e($companyName); ?>" required>
                </label>
                <label>Support email
                    <input type="email" name="support_email" value="<?= e($supportEmail); ?>" placeholder="support@example.com">
                </label>
                <label>Brand colour
                    <input type="text" name="brand_primary_color" value="<?= e($primaryColor); ?>" placeholder="#2563eb">
                </label>
                <label>Font family
                    <input type="text" name="brand_font_family" value="<?= e($fontFamily); ?>" placeholder="Inter, sans-serif">
                </label>
                <label>Dashboard surface
                    <input type="text" name="brand_surface_color" value="<?= e($surfaceColor); ?>" placeholder="#f4f6fb">
                </label>
                <label>Card background
                    <input type="text" name="brand_card_color" value="<?= e($cardColor); ?>" placeholder="#ffffff">
                </label>
                <label>Control background
                    <input type="text" name="brand_control_color" value="<?= e($controlColor); ?>" placeholder="#eef2ff">
                </label>
                <label>Border colour
                    <input type="text" name="brand_border_color" value="<?= e($borderColor); ?>" placeholder="#dce1eb">
                </label>
                <label>Primary text colour
                    <input type="text" name="brand_text_color" value="<?= e($textColor); ?>" placeholder="#111827">
                </label>
                <label>Muted text colour
                    <input type="text" name="brand_muted_color" value="<?= e($mutedColor); ?>" placeholder="#6b7280">
                </label>
                <label>Logo URL
                    <input type="url" name="brand_logo_url" value="<?= e($logoSetting); ?>" placeholder="https://cdn.example.com/logo.svg">
                    <span class="hint">Used when no uploaded file is present.</span>
                </label>
                <hr class="settings-divider">
                <h3 class="settings-subheading">Email delivery</h3>
                <p class="hint">Send portal notifications via Microsoft 365 (SMTP) or keep the built-in PHP mail transport.</p>
                <label>From name
                    <input type="text" name="mail_from_name" value="<?= e($mailFromName); ?>" placeholder="<?= e($companyName); ?>">
                </label>
                <label>From email address
                    <input type="email" name="mail_from_address" value="<?= e($mailFromAddress); ?>" placeholder="hello@yourdomain.com" required>
                </label>
                <label>Email transport
                    <select name="mail_transport">
                        <option value="mail"<?= $mailTransport === 'mail' ? ' selected' : ''; ?>>Built-in PHP mail</option>
                        <option value="smtp"<?= $mailTransport === 'smtp' ? ' selected' : ''; ?>>SMTP (Microsoft 365, Gmail, etc.)</option>
                    </select>
                </label>
                <label>SMTP host
                    <input type="text" name="smtp_host" value="<?= e($smtpHost); ?>" placeholder="smtp.office365.com">
                    <span class="hint">For Microsoft 365 use <code>smtp.office365.com</code>.</span>
                </label>
                <label>SMTP port
                    <input type="text" name="smtp_port" value="<?= e($smtpPort); ?>" placeholder="587">
                </label>
                <label>SMTP username
                    <input type="text" name="smtp_username" value="<?= e($smtpUsername); ?>" placeholder="you@yourdomain.com">
                </label>
                <label>SMTP encryption
                    <select name="smtp_encryption">
                        <option value="tls"<?= $smtpEncryption === 'tls' ? ' selected' : ''; ?>>TLS</option>
                        <option value="ssl"<?= $smtpEncryption === 'ssl' ? ' selected' : ''; ?>>SSL</option>
                        <option value="none"<?= $smtpEncryption === 'none' ? ' selected' : ''; ?>>None</option>
                    </select>
                </label>
                <label>SMTP password / app password
                    <input type="password" name="smtp_password" placeholder="Microsoft 365 app password" autocomplete="new-password">
                    <span class="hint">Generate an app password from Microsoft 365 security settings. Leave blank to keep the current password.</span>
                </label>
                <?php if ($smtpPasswordStored): ?>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="clear_smtp_password" value="1"> Reset stored password
                    </label>
                <?php endif; ?>
                <hr class="settings-divider">
                <h3 class="settings-subheading">Bot protection</h3>
                <p class="hint">Enable <a href="https://www.cloudflare.com/products/turnstile/" target="_blank" rel="noopener">Cloudflare Turnstile</a> on login and sign-up forms to reduce automated sign-ups.</p>
                <label class="checkbox-inline">
                    <input type="checkbox" name="turnstile_enabled" value="1"<?= $turnstileEnabled ? ' checked' : ''; ?>> Require Turnstile verification on auth forms
                </label>
                <label>Turnstile site key
                    <input type="text" name="turnstile_site_key" value="<?= e($turnstileSiteKey); ?>" placeholder="0x4AAAA...">
                </label>
                <label>Turnstile secret key
                    <input type="password" name="turnstile_secret_key" placeholder="Secret key" autocomplete="new-password">
                    <span class="hint">Paste the secret key from your Cloudflare Turnstile dashboard. Leave blank to keep the stored key.</span>
                </label>
                <?php if ($turnstileSecretStored): ?>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="clear_turnstile_secret" value="1"> Reset stored secret
                    </label>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="button button--primary">Save settings</button>
            </div>
        </form>
    </div>
    <div class="card">
        <header class="card-header">
            <div>
                <h2>Send test email</h2>
                <p>Confirm your mail settings by sending a sample template to any address.</p>
            </div>
        </header>
        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="settings-grid">
            <input type="hidden" name="action" value="send_test_email">
            <input type="hidden" name="redirect" value="admin/settings">
            <div class="settings-fields">
                <label>Send to email address
                    <input type="email" name="test_email" placeholder="you@example.com" required>
                </label>
                <label>Email template
                    <select name="template">
                        <option value="">Generic delivery test</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= e($template['slug']); ?>"><?= e($template['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Choose a template to preview real placeholders or send a simple delivery ping.</span>
                </label>
                <?php if (!$templates): ?>
                    <p class="hint">Create templates under Automations to test personalised messages.</p>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="button button--primary">Send test email</button>
            </div>
        </form>
    </div>
</section>
