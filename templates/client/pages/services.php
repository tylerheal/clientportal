<?php
$serviceSlug = $serviceSlug ?? null;
$availability = payments_available();
$paymentLabels = [];
if (!empty($availability['stripe'])) {
    $paymentLabels[] = 'Stripe';
}
if (!empty($availability['google_pay'])) {
    $paymentLabels[] = 'Google Pay';
}
if (!empty($availability['paypal'])) {
    $paymentLabels[] = 'PayPal';
}
if (empty($paymentLabels)) {
    $paymentLabels[] = 'Manual invoice';
}

$cards = [
    [
        'slug' => 'malware-removal',
        'title' => 'WordPress Malware Removal',
        'lead' => 'Emergency cleanup, hardening and forensic scans to restore your site fast.',
        'features' => [
            'Guaranteed response within 2 hours',
            'Malware removal and blacklist delisting',
            'Post-clean security hardening and monitoring',
        ],
    ],
    [
        'slug' => 'care-plans',
        'title' => 'WordPress Care Plans',
        'lead' => 'Proactive maintenance, updates, and uptime monitoring tailored to your site.',
        'features' => [
            'Daily backups with 30-day retention',
            'Weekly core, theme, and plugin updates',
            'Performance insights and uptime alerts',
        ],
    ],
    [
        'slug' => 'support',
        'title' => 'WordPress Support',
        'lead' => 'Flexible support blocks for tweaks, fixes, and enhancements.',
        'features' => [
            'Dedicated WordPress specialists',
            'Track progress in the client portal',
            'Unused hours roll forward for 60 days',
        ],
    ],
];
?>
<section class="page-section">
    <header class="page-header">
        <div>
            <h2>Services catalogue</h2>
            <p>Choose the service you need and complete the tailored intake form to get started.</p>
        </div>
    </header>

    <div class="service-list">
        <?php foreach ($cards as $card): ?>
            <article class="service-card">
                <div class="service-card__body">
                    <h3><?= e($card['title']); ?></h3>
                    <p><?= e($card['lead']); ?></p>
                    <ul>
                        <?php foreach ($card['features'] as $feature): ?>
                            <li><?= e($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <footer class="service-card__footer">
                    <span class="service-card__payments">Payments: <?= e(implode(' • ', $paymentLabels)); ?></span>
                    <a class="btn primary" href="<?= e(url_for('dashboard/services/' . $card['slug'])); ?>">View &amp; order</a>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
</section>
