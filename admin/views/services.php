<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="page-columns">
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Create service</h2>
                    <p>Add a new offer to the catalogue.</p>
                </div>
            </header>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                <input type="hidden" name="action" value="create_service">
                <input type="hidden" name="redirect" value="admin/services">
                <label>Name
                    <input type="text" name="name" required>
                </label>
                <label>Description
                    <textarea name="description" rows="3"></textarea>
                </label>
                <label>Price
                    <input type="number" name="price" min="0" step="0.01" required>
                </label>
                <label>Billing interval
                    <select name="billing_interval">
                        <option value="one_time">One-time</option>
                        <option value="monthly">Monthly</option>
                        <option value="annual">Annual</option>
                    </select>
                </label>
                <label>Form builder <span class="hint">Label|name|type|required per line</span>
                    <textarea name="form_builder" rows="4" placeholder="Website URL|website|url|required"></textarea>
                </label>
                <label class="switch">
                    <input type="checkbox" name="active" checked>
                    <span>Active</span>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button button--primary">Save service</button>
                </div>
            </form>
        </article>
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Service library</h2>
                    <p>Manage pricing, availability and forms.</p>
                </div>
            </header>
            <div class="service-list">
                <?php foreach ($services as $service): ?>
                    <?php $fields = parse_form_schema($service['form_schema']); ?>
                    <?php
                        $builderLines = [];
                        foreach ($fields as $field) {
                            $builderLines[] = sprintf('%s|%s|%s|%s', $field['label'], $field['name'], $field['type'], !empty($field['required']) ? 'required' : '');
                        }
                        $builderValue = implode("\n", array_filter($builderLines));
                    ?>
                    <details class="service-item" <?= (int) $service['active'] === 1 ? '' : 'data-inactive'; ?>>
                        <summary>
                            <div>
                                <strong><?= e($service['name']); ?></strong>
                                <span><?= e(ucfirst(str_replace('_', ' ', $service['billing_interval']))); ?> · <?= format_currency((float) $service['price']); ?></span>
                            </div>
                            <span class="badge badge--<?= (int) $service['active'] === 1 ? 'active' : 'disabled'; ?>"><?= (int) $service['active'] === 1 ? 'Active' : 'Hidden'; ?></span>
                        </summary>
                        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                            <input type="hidden" name="action" value="update_service">
                            <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                            <input type="hidden" name="redirect" value="admin/services">
                            <label>Name
                                <input type="text" name="name" value="<?= e($service['name']); ?>" required>
                            </label>
                            <label>Description
                                <textarea name="description" rows="3"><?= e($service['description']); ?></textarea>
                            </label>
                            <label>Price
                                <input type="number" name="price" value="<?= e($service['price']); ?>" min="0" step="0.01" required>
                            </label>
                            <label>Billing interval
                                <select name="billing_interval">
                                    <option value="one_time" <?= $service['billing_interval'] === 'one_time' ? 'selected' : ''; ?>>One-time</option>
                                    <option value="monthly" <?= $service['billing_interval'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="annual" <?= $service['billing_interval'] === 'annual' ? 'selected' : ''; ?>>Annual</option>
                                </select>
                            </label>
                            <label>Form builder
                                <textarea name="form_builder" rows="4"><?= e($builderValue); ?></textarea>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="active" <?= (int) $service['active'] === 1 ? 'checked' : ''; ?>>
                                <span>Active</span>
                            </label>
                            <div class="form-actions">
                                <button type="submit" class="button button--primary">Update</button>
                                <button type="submit" form="delete-service-<?= (int) $service['id']; ?>" class="button button--ghost">Delete</button>
                            </div>
                        </form>
                        <form id="delete-service-<?= (int) $service['id']; ?>" action="<?= e(url_for('dashboard')); ?>" method="post" class="inline-form" onsubmit="return confirm('Delete this service?');">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="service_id" value="<?= (int) $service['id']; ?>">
                            <input type="hidden" name="redirect" value="admin/services">
                        </form>
                    </details>
                <?php endforeach; ?>
                <?php if (!$services): ?>
                    <p class="table-empty">No services yet.</p>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>
