<section class="page-section">
    <header class="page-header">
        <div>
            <h2>Client forms</h2>
            <p>Preview the onboarding forms attached to each service.</p>
        </div>
        <div class="page-actions">
            <a class="button button--primary" href="<?= e(url_for('admin/services')); ?>">Manage services</a>
        </div>
    </header>
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Form</th>
                        <th>Slug</th>
                        <th>Fields</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <?php $fields = parse_form_schema($service['form_schema']); ?>
                        <tr>
                            <td>
                                <strong><?= e($service['name']); ?></strong>
                                <p><?= e($service['description']); ?></p>
                            </td>
                            <td><?= e(strtolower(str_replace(' ', '-', $service['name']))); ?></td>
                            <td><?= count($fields); ?> fields</td>
                            <td><span class="badge badge--<?= (int) $service['active'] === 1 ? 'active' : 'disabled'; ?>"><?= (int) $service['active'] === 1 ? 'Active' : 'Hidden'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <tr><td colspan="4" class="table-empty">No forms configured.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
