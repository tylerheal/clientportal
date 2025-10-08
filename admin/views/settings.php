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
            </div>
            <div class="form-actions">
                <button type="submit" class="button button--primary">Save settings</button>
            </div>
        </form>
    </div>
</section>
