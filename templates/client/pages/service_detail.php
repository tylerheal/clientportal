<?php
$serviceSlug = $serviceSlug ?? 'malware-removal';
$availability = payments_available();
$currency = currency_code();
$pageScripts = $pageScripts ?? [];
$inlineScripts = $inlineScripts ?? [];

if (!empty($availability['stripe'])) {
    $pageScripts[] = ['src' => 'https://js.stripe.com/v3/'];
}

$serviceLookup = [];
foreach ($services as $service) {
    $serviceLookup[strtolower($service['name'])] = $service;
}

$findService = static function (array $lookup, array $candidates): ?array {
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($lookup[$key])) {
            return $lookup[$key];
        }
    }
    return null;
};

$definitions = [
    'malware-removal' => [
        'title' => 'WordPress Malware Removal',
        'intro' => 'Urgent cleanup, hardening and forensic scans to get your site back online quickly.',
        'candidates' => ['wordpress malware removal', 'malware removal'],
        'summary' => 'Comprehensive cleanup, security hardening &amp; 30-day monitoring.',
    ],
    'care-plans' => [
        'title' => 'WordPress Care Plans',
        'intro' => 'Ongoing maintenance, monitoring and support to keep your site healthy every month.',
        'candidates' => ['wordpress care plan', 'wordpress care plans', 'website care plan'],
    ],
    'support' => [
        'title' => 'WordPress Support',
        'intro' => 'Book hourly support for enhancements, troubleshooting or hands-on assistance.',
        'candidates' => ['wordpress support', 'support hours', 'additional support'],
    ],
];

if (!isset($definitions[$serviceSlug])) {
    $serviceSlug = 'malware-removal';
}

$currentDefinition = $definitions[$serviceSlug];
$serviceRecord = $findService($serviceLookup, $currentDefinition['candidates']);

if ($serviceRecord && $serviceSlug === 'care-plans') {
    $interval = strtolower((string) ($serviceRecord['billing_interval'] ?? 'one_time'));
    if ($interval !== 'monthly') {
        $pdo = get_db();
        $pdo->prepare('UPDATE services SET billing_interval = :interval, updated_at = :updated WHERE id = :id')
            ->execute([
                'interval' => 'monthly',
                'updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'id' => $serviceRecord['id'],
            ]);
        $serviceRecord['billing_interval'] = 'monthly';
    }
}

$orderIntervalValue = strtolower((string) ($serviceRecord['billing_interval'] ?? 'one_time'));
if (!in_array($orderIntervalValue, ['monthly', 'annual'], true)) {
    $orderIntervalValue = 'one_time';
}

if (!empty($availability['paypal'])) {
    $paypalIntent = $orderIntervalValue === 'one_time' ? 'capture' : 'subscription';
    $params = [
        'client-id' => get_setting('paypal_client_id', ''),
        'currency' => $currency,
        'components' => 'buttons',
        'intent' => $paypalIntent,
        'vault' => 'true',
    ];
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === '') {
            continue;
        }
        $query[] = $key . '=' . rawurlencode($value);
    }
    $pageScripts[] = ['src' => 'https://www.paypal.com/sdk/js?' . implode('&', $query)];
}

if (isset($currentDefinition['title'])) {
    $pageTitle = $currentDefinition['title'];
}

$paymentOptions = [];
if (!empty($availability['stripe'])) {
    $paymentOptions['stripe'] = [
        'label' => 'Card payment',
        'description' => 'Secure checkout via Stripe.',
        'icon' => '💳',
    ];
}
if (!empty($availability['google_pay'])) {
    $paymentOptions['google_pay'] = [
        'label' => 'Google Pay',
        'description' => 'Fast checkout on supported devices.',
        'icon' => '🅖',
    ];
}
if (!empty($availability['paypal'])) {
    $paymentOptions['paypal'] = [
        'label' => 'PayPal',
        'description' => 'Pay with your PayPal balance or saved cards.',
        'icon' => 'Ⓟ',
    ];
}
$paymentOptions['manual'] = [
    'label' => 'Manual invoice',
    'description' => 'We’ll email an invoice if you prefer to pay later.',
    'icon' => '📄',
];

if ($serviceSlug === 'care-plans' && isset($paymentOptions['google_pay'])) {
    unset($paymentOptions['google_pay']);
}

$defaultName = $user['name'] ?? '';
$defaultEmail = $user['email'] ?? '';
$defaultPhone = $user['phone'] ?? '';
$defaultCompany = $user['company'] ?? '';

$formatPrice = static fn(float $amount): string => format_currency($amount);
$detailPath = 'dashboard/services/' . $serviceSlug;
?>
<section class="page-section">
    <header class="page-header">
        <div>
            <a class="breadcrumb-link" href="<?= e(url_for('dashboard/services')); ?>">← Back to services</a>
            <h2><?= e($currentDefinition['title']); ?></h2>
            <p><?= e($currentDefinition['intro']); ?></p>
        </div>
    </header>

    <div class="service-checkout service-checkout--active" data-service-section="<?= e($serviceSlug); ?>">
        <div class="service-checkout__body">
            <?php if ($serviceRecord): ?>
                <?php if ($serviceSlug === 'malware-removal'): ?>
                    <?php $malwarePrice = (float) $serviceRecord['price']; ?>
                    <form
                        action="<?= e(url_for('dashboard')); ?>"
                        method="post"
                        class="service-checkout__form"
                        data-service-order-form
                        data-service-name="<?= e($serviceRecord['name']); ?>"
                        data-service-price="<?= number_format($malwarePrice, 2, '.', ''); ?>"
                        data-service-currency="<?= e($currency); ?>"
                    >
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $serviceRecord['id']; ?>">
                        <input type="hidden" name="redirect" value="<?= e($detailPath); ?>">
                        <input type="hidden" name="order_interval" value="<?= e($orderIntervalValue); ?>">
                        <input type="hidden" name="order_total" value="<?= number_format($malwarePrice, 2, '.', ''); ?>" data-order-total>
                        <input type="hidden" name="payment_method" value="<?= e(array_key_first($paymentOptions)); ?>" data-payment-method>
                        <fieldset class="fieldset">
                            <legend>Site details</legend>
                            <label>Describe the issue
                                <textarea name="custom[issue_description]" rows="4" placeholder="Tell us when the infection started, any symptoms you’ve noticed, and recent changes." required></textarea>
                            </label>
                            <label>Website URL
                                <input type="url" name="custom[website_url]" placeholder="https://example.com" required>
                            </label>
                            <label>Admin login URL
                                <input type="url" name="custom[admin_url]" placeholder="https://example.com/wp-admin">
                            </label>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Primary contact</legend>
                            <div class="field-grid">
                                <label>Full name
                                    <input type="text" name="custom[contact_name]" value="<?= e($defaultName); ?>" required>
                                </label>
                                <label>Email address
                                    <input type="email" name="custom[contact_email]" value="<?= e($defaultEmail); ?>" required>
                                </label>
                                <label>Phone
                                    <input type="tel" name="custom[contact_phone]" value="<?= e($defaultPhone); ?>">
                                </label>
                                <label>Company
                                    <input type="text" name="custom[company]" value="<?= e($defaultCompany); ?>" placeholder="Optional">
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Billing address</legend>
                            <div class="field-grid">
                                <label>Address line 1
                                    <input type="text" name="custom[address_line1]" required>
                                </label>
                                <label>Address line 2
                                    <input type="text" name="custom[address_line2]" placeholder="Optional">
                                </label>
                                <label>City
                                    <input type="text" name="custom[address_city]" required>
                                </label>
                                <label>Region
                                    <input type="text" name="custom[address_region]" placeholder="County / State">
                                </label>
                                <label>Postcode
                                    <input type="text" name="custom[address_postcode]" required>
                                </label>
                                <label>Country
                                    <input type="text" name="custom[address_country]" value="United Kingdom" required>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Payment</legend>
                            <div class="payment-options" data-payment-options>
                                <?php $firstOption = true; ?>
                                <?php foreach ($paymentOptions as $method => $option): ?>
                                    <label class="payment-option" data-payment-option data-value="<?= e($method); ?>">
                                        <input type="radio" name="payment_selector" value="<?= e($method); ?>"<?= $firstOption ? ' checked' : ''; ?>>
                                        <span class="payment-option__icon" aria-hidden="true"><?= e($option['icon']); ?></span>
                                        <span class="payment-option__copy">
                                            <span class="payment-option__label"><?= e($option['label']); ?></span>
                                            <span class="payment-option__meta"><?= e($option['description']); ?></span>
                                        </span>
                                    </label>
                                    <?php $firstOption = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="service-feedback" data-service-feedback hidden></div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary">Complete purchase</button>
                        </div>
                    </form>
                    <aside class="service-summary">
                        <div class="summary-card">
                            <h4>Summary</h4>
                            <dl>
                                <div class="summary-line">
                                    <dt>Service</dt>
                                    <dd><?= e($serviceRecord['name']); ?></dd>
                                </div>
                                <div class="summary-line">
                                    <dt>Includes</dt>
                                    <dd><?= $currentDefinition['summary']; ?></dd>
                                </div>
                            </dl>
                            <div class="summary-total" data-summary-total><?= e($formatPrice($malwarePrice)); ?></div>
                        </div>
                    </aside>
                <?php elseif ($serviceSlug === 'care-plans'): ?>
                    <?php
                    $basePrice = (float) $serviceRecord['price'];
                    $carePlans = [
                        'essential' => [
                            'label' => 'Care Plan Essential',
                            'description' => 'Core updates, backups & uptime monitoring.',
                            'price' => $basePrice,
                        ],
                        'standard' => [
                            'label' => 'Care Plan Standard',
                            'description' => 'Everything in Essential plus performance tuning & quarterly reviews.',
                            'price' => $basePrice + 40,
                        ],
                        'premium' => [
                            'label' => 'Care Plan Premium',
                            'description' => 'Priority support, staging deployments & weekly strategy calls.',
                            'price' => $basePrice + 90,
                        ],
                    ];
                    $selectedPlanKey = array_key_first($carePlans);
                    ?>
                    <form
                        action="<?= e(url_for('dashboard')); ?>"
                        method="post"
                        class="service-checkout__form"
                        data-service-order-form
                        data-service-name="<?= e($serviceRecord['name']); ?>"
                        data-service-price="<?= number_format($carePlans[$selectedPlanKey]['price'], 2, '.', ''); ?>"
                        data-service-currency="<?= e($currency); ?>"
                        data-plan-summary
                    >
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $serviceRecord['id']; ?>">
                        <input type="hidden" name="redirect" value="<?= e($detailPath); ?>">
                        <input type="hidden" name="order_interval" value="<?= e($orderIntervalValue); ?>">
                        <input type="hidden" name="order_total" value="<?= number_format($carePlans[$selectedPlanKey]['price'], 2, '.', ''); ?>" data-order-total>
                        <input type="hidden" name="payment_method" value="<?= e(array_key_first($paymentOptions)); ?>" data-payment-method>
                        <fieldset class="fieldset">
                            <legend>Choose your plan</legend>
                            <div class="plan-grid" data-plan-options>
                                <?php foreach ($carePlans as $planKey => $plan): ?>
                                    <label class="plan-card" data-plan-choice data-price="<?= number_format($plan['price'], 2, '.', ''); ?>">
                                        <input type="radio" name="custom[plan]" value="<?= e($planKey); ?>"<?= $planKey === $selectedPlanKey ? ' checked' : ''; ?>>
                                        <span class="plan-card__label"><?= e($plan['label']); ?></span>
                                        <span class="plan-card__price"><?= e($formatPrice($plan['price'])); ?>/month</span>
                                        <span class="plan-card__meta"><?= e($plan['description']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Site information</legend>
                            <div class="field-grid">
                                <label>Primary website URL
                                    <input type="url" name="custom[website_url]" placeholder="https://example.com" required>
                                </label>
                                <label>Staging site URL
                                    <input type="url" name="custom[staging_url]" placeholder="https://staging.example.com">
                                </label>
                                <label>Monthly traffic band
                                    <select name="custom[traffic_band]">
                                        <option value="under_10k">Under 10k visits</option>
                                        <option value="10k_50k">10k – 50k visits</option>
                                        <option value="50k_plus">50k+ visits</option>
                                    </select>
                                </label>
                                <label>Focus areas
                                    <textarea name="custom[focus]" rows="3" placeholder="e.g. Accessibility, WooCommerce, membership flows"></textarea>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Primary contact</legend>
                            <div class="field-grid">
                                <label>Full name
                                    <input type="text" name="custom[contact_name]" value="<?= e($defaultName); ?>" required>
                                </label>
                                <label>Email address
                                    <input type="email" name="custom[contact_email]" value="<?= e($defaultEmail); ?>" required>
                                </label>
                                <label>Phone
                                    <input type="tel" name="custom[contact_phone]" value="<?= e($defaultPhone); ?>">
                                </label>
                                <label>Company
                                    <input type="text" name="custom[company]" value="<?= e($defaultCompany); ?>" placeholder="Optional">
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Payment</legend>
                            <div class="payment-options" data-payment-options>
                                <?php $firstOption = true; ?>
                                <?php foreach ($paymentOptions as $method => $option): ?>
                                    <label class="payment-option" data-payment-option data-value="<?= e($method); ?>">
                                        <input type="radio" name="payment_selector" value="<?= e($method); ?>"<?= $firstOption ? ' checked' : ''; ?>>
                                        <span class="payment-option__icon" aria-hidden="true"><?= e($option['icon']); ?></span>
                                        <span class="payment-option__copy">
                                            <span class="payment-option__label"><?= e($option['label']); ?></span>
                                            <span class="payment-option__meta"><?= e($option['description']); ?></span>
                                        </span>
                                    </label>
                                    <?php $firstOption = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="service-feedback" data-service-feedback hidden></div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary">Start plan</button>
                        </div>
                    </form>
                    <aside class="service-summary">
                        <div class="summary-card">
                            <h4>Summary</h4>
                            <dl>
                                <div class="summary-line">
                                    <dt>Service</dt>
                                    <dd><?= e($serviceRecord['name']); ?></dd>
                                </div>
                                <div class="summary-line" data-plan-summary-label>
                                    <dt>Plan selected</dt>
                                    <dd><?= e($carePlans[$selectedPlanKey]['label']); ?></dd>
                                </div>
                                <div class="summary-line">
                                    <dt>Billing</dt>
                                    <dd>Charged monthly, cancel anytime.</dd>
                                </div>
                            </dl>
                            <div class="summary-total" data-summary-total><?= e($formatPrice($carePlans[$selectedPlanKey]['price'])); ?>/month</div>
                        </div>
                    </aside>
                <?php else: ?>
                    <?php $supportRate = max(1, (float) $serviceRecord['price']); ?>
                    <form
                        action="<?= e(url_for('dashboard')); ?>"
                        method="post"
                        class="service-checkout__form"
                        data-service-order-form
                        data-service-name="<?= e($serviceRecord['name']); ?>"
                        data-service-price="<?= number_format($supportRate, 2, '.', ''); ?>"
                        data-service-rate="<?= number_format($supportRate, 2, '.', ''); ?>"
                        data-service-currency="<?= e($currency); ?>"
                    >
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $serviceRecord['id']; ?>">
                        <input type="hidden" name="redirect" value="<?= e($detailPath); ?>">
                        <input type="hidden" name="order_interval" value="<?= e($orderIntervalValue); ?>">
                        <input type="hidden" name="order_total" value="<?= number_format($supportRate, 2, '.', ''); ?>" data-order-total>
                        <input type="hidden" name="payment_method" value="<?= e(array_key_first($paymentOptions)); ?>" data-payment-method>
                        <fieldset class="fieldset">
                            <legend>Support request</legend>
                            <label>What do you need help with?
                                <textarea name="custom[support_summary]" rows="4" placeholder="Describe the task, issue or improvements you’d like us to work on." required></textarea>
                            </label>
                            <div class="field-grid">
                                <label>Preferred start date
                                    <input type="date" name="custom[start_date]">
                                </label>
                                <label>Hours required
                                    <input type="number" name="custom[hours]" value="1" min="1" max="40" step="1" data-hour-input required>
                                </label>
                                <label>Priority level
                                    <select name="custom[priority]">
                                        <option value="normal">Standard response (within 2 business days)</option>
                                        <option value="rush">Rush (next business day)</option>
                                        <option value="urgent">Urgent (same day)</option>
                                    </select>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Primary contact</legend>
                            <div class="field-grid">
                                <label>Full name
                                    <input type="text" name="custom[contact_name]" value="<?= e($defaultName); ?>" required>
                                </label>
                                <label>Email address
                                    <input type="email" name="custom[contact_email]" value="<?= e($defaultEmail); ?>" required>
                                </label>
                                <label>Phone
                                    <input type="tel" name="custom[contact_phone]" value="<?= e($defaultPhone); ?>">
                                </label>
                                <label>Company
                                    <input type="text" name="custom[company]" value="<?= e($defaultCompany); ?>" placeholder="Optional">
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Payment</legend>
                            <div class="payment-options" data-payment-options>
                                <?php $firstOption = true; ?>
                                <?php foreach ($paymentOptions as $method => $option): ?>
                                    <label class="payment-option" data-payment-option data-value="<?= e($method); ?>">
                                        <input type="radio" name="payment_selector" value="<?= e($method); ?>"<?= $firstOption ? ' checked' : ''; ?>>
                                        <span class="payment-option__icon" aria-hidden="true"><?= e($option['icon']); ?></span>
                                        <span class="payment-option__copy">
                                            <span class="payment-option__label"><?= e($option['label']); ?></span>
                                            <span class="payment-option__meta"><?= e($option['description']); ?></span>
                                        </span>
                                    </label>
                                    <?php $firstOption = false; ?>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="service-feedback" data-service-feedback hidden></div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary">Book support</button>
                        </div>
                    </form>
                    <aside class="service-summary">
                        <div class="summary-card">
                            <h4>Summary</h4>
                            <dl>
                                <div class="summary-line">
                                    <dt>Service</dt>
                                    <dd><?= e($serviceRecord['name']); ?></dd>
                                </div>
                                <div class="summary-line">
                                    <dt>Hourly rate</dt>
                                    <dd><?= e($formatPrice($supportRate)); ?></dd>
                                </div>
                                <div class="summary-line" data-hour-summary>
                                    <dt>Hours booked</dt>
                                    <dd>1 hour</dd>
                                </div>
                            </dl>
                            <div class="summary-total" data-summary-total><?= e($formatPrice($supportRate)); ?></div>
                        </div>
                    </aside>
                <?php endif; ?>
            <?php else: ?>
                <div class="service-empty">
                    <p class="subtle">This service isn’t active yet. Enable it from the admin services catalogue.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!empty($availability['paypal']) || !empty($availability['stripe'])): ?>
    <?php include __DIR__ . '/../partials/payments_modal.php'; ?>
<?php endif; ?>
