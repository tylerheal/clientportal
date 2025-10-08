<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <header class="page-header">
        <div>
            <h2>Saved forms</h2>
            <p>Access the onboarding forms linked to your services.</p>
        </div>
        <div class="page-actions">
            <a class="button button--primary" href="<?= e(url_for('dashboard/services')); ?>">Browse services</a>
        </div>
    </header>
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Form</th>
                        <th>Billing</th>
                        <th>Fields</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <?php $fields = parse_form_schema($service['form_schema']); ?>
                        <tr>
                            <td>
                                <strong><?= e($service['name']); ?></strong>
                                <p class="table-subline"><?= e($service['description']); ?></p>
                            </td>
                            <td><span class="badge badge--neutral"><?= $service['billing_interval'] === 'one_time' ? 'One-off' : ucfirst($service['billing_interval']); ?></span></td>
                            <td><?= count($fields); ?> fields</td>
                            <td><a class="button button--primary" href="<?= e(url_for('dashboard/services')); ?>">Start form</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <tr><td colspan="4" class="table-empty">No forms available yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
