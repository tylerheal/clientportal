<?php
$availability = payments_available();
$currency = currency_code();
$pageScripts = $pageScripts ?? [];
$inlineScripts = $inlineScripts ?? [];

if (!empty($availability['paypal'])) {
    $paypalScript = sprintf(
        'https://www.paypal.com/sdk/js?client-id=%s&currency=%s&components=buttons&intent=capture',
        rawurlencode(get_setting('paypal_client_id', '')),
        rawurlencode($currency)
    );
    $pageScripts[] = ['src' => $paypalScript];
}

if (!empty($availability['stripe'])) {
    $pageScripts[] = ['src' => 'https://js.stripe.com/v3/'];
}

$serviceFocus = $serviceFocus ?? strtolower((string) ($_GET['focus'] ?? ''));
$validFocuses = ['malware-removal', 'care-plans', 'support'];
if (!in_array($serviceFocus, $validFocuses, true)) {
    $serviceFocus = 'malware-removal';
}

if (isset($_GET['focus']) && in_array($serviceFocus, $validFocuses, true)) {
    $focusTarget = json_encode($serviceFocus, JSON_THROW_ON_ERROR);
    $inlineScripts[] = "(function(){var target=document.querySelector('[data-service-section=" . $focusTarget . "]');if(target){target.classList.add('service-checkout--active');target.scrollIntoView({behavior:'smooth',block:'start'});}})();";
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

$malwareService = $findService($serviceLookup, ['wordpress malware removal', 'malware removal']);
$carePlanService = $findService($serviceLookup, ['wordpress care plan', 'wordpress care plans', 'website care plan']);
$supportService = $findService($serviceLookup, ['wordpress support', 'support hours', 'additional support']);

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

$defaultName = $user['name'] ?? '';
$defaultEmail = $user['email'] ?? '';
$defaultPhone = $user['phone'] ?? '';
$defaultCompany = $user['company'] ?? '';

$formatPrice = static fn(float $amount): string => format_currency($amount);
?>
<section class="page-section">
    <header class="page-header">
        <div>
            <h2>Services catalogue</h2>
            <p>Choose the service you need and complete the tailored intake form to get started.</p>
        </div>
    </header>

    <div class="service-stack">
        <section class="service-checkout<?= $serviceFocus === 'malware-removal' ? ' service-checkout--active' : ''; ?>" id="service-malware" data-service-section="malware-removal">
            <header class="service-checkout__header">
                <h3>WordPress Malware Removal</h3>
                <p>Urgent cleanup, hardening and forensic scans to get your site back online quickly.</p>
            </header>
            <div class="service-checkout__body">
                <?php if ($malwareService): ?>
                    <?php $malwarePrice = (float) $malwareService['price']; ?>
                    <form
                        action="<?= e(url_for('dashboard')); ?>"
                        method="post"
                        class="service-checkout__form"
                        data-service-order-form
                        data-service-name="<?= e($malwareService['name']); ?>"
                        data-service-price="<?= number_format($malwarePrice, 2, '.', ''); ?>"
                        data-service-currency="<?= e($currency); ?>"
                    >
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $malwareService['id']; ?>">
                        <input type="hidden" name="redirect" value="dashboard/services">
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
                                    <dd><?= e($malwareService['name']); ?></dd>
                                </div>
                                <div class="summary-line">
                                    <dt>Includes</dt>
                                    <dd>Comprehensive cleanup, security hardening &amp; 30-day monitoring.</dd>
                                </div>
                            </dl>
                            <div class="summary-total" data-summary-total><?= e($formatPrice($malwarePrice)); ?></div>
                        </div>
                    </aside>
                <?php else: ?>
                    <div class="service-empty">
                        <p class="subtle">Add a “WordPress Malware Removal” service in the admin area to enable this form.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="service-checkout<?= $serviceFocus === 'care-plans' ? ' service-checkout--active' : ''; ?>" id="service-care" data-service-section="care-plans">
            <header class="service-checkout__header">
                <h3>WordPress Care Plans</h3>
                <p>Ongoing maintenance, monitoring and support to keep your site healthy every month.</p>
            </header>
            <div class="service-checkout__body">
                <?php if ($carePlanService): ?>
                    <?php
                    $basePrice = (float) $carePlanService['price'];
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
                        data-service-name="<?= e($carePlanService['name']); ?>"
                        data-service-price="<?= number_format($carePlans[$selectedPlanKey]['price'], 2, '.', ''); ?>"
                        data-service-currency="<?= e($currency); ?>"
                        data-plan-summary
                    >
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $carePlanService['id']; ?>">
                        <input type="hidden" name="redirect" value="dashboard/services?focus=care-plans">
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
                                    <dd><?= e($carePlanService['name']); ?></dd>
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
                    <div class="service-empty">
                        <p class="subtle">Add a “WordPress Care Plan” service in the admin area to enable this form.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="service-checkout<?= $serviceFocus === 'support' ? ' service-checkout--active' : ''; ?>" id="service-support" data-service-section="support">
            <header class="service-checkout__header">
                <h3>WordPress Support</h3>
                <p>Book hourly support for enhancements, troubleshooting or hands-on assistance.</p>
            </header>
            <div class="service-checkout__body">
                <?php if ($supportService): ?>
                    <?php $supportRate = max(1, (float) $supportService['price']); ?>
                    <form
                        action="<?= e(url_for('dashboard')); ?>"
                        method="post"
                        class="service-checkout__form"
                        data-service-order-form
                        data-service-name="<?= e($supportService['name']); ?>"
                        data-service-price="<?= number_format($supportRate, 2, '.', ''); ?>"
                        data-service-rate="<?= number_format($supportRate, 2, '.', ''); ?>"
                        data-service-currency="<?= e($currency); ?>"
                    >
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="service_id" value="<?= (int) $supportService['id']; ?>">
                        <input type="hidden" name="redirect" value="dashboard/services?focus=support">
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
                                    <dd><?= e($supportService['name']); ?></dd>
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
                <?php else: ?>
                    <div class="service-empty">
                        <p class="subtle">Add a “WordPress Support” service in the admin area to enable this form.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>

<?php if (!empty($availability['paypal']) || !empty($availability['stripe'])): ?>
    <?php include __DIR__ . '/../partials/payments_modal.php'; ?>
<?php endif; ?>
