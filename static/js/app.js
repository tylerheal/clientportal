(function () {
    if (typeof window === 'undefined') {
        return;
    }

    const doc = document;
    const menuBtn = doc.getElementById('menuBtn');
    const mobileMenu = doc.getElementById('mobileMenu');
    const state = {
        chart: null,
        data: { labels: [], series: [] },
        currency: { prefix: '', suffix: '' },
    };
    let closeInvoiceModal = () => {};
    const searchModal = doc.querySelector('[data-search-modal]');
    const searchOpeners = Array.from(doc.querySelectorAll('[data-search-open]'));
    const searchDismissEls = Array.from(doc.querySelectorAll('[data-search-dismiss]'));
    const searchInput = searchModal ? searchModal.querySelector('[data-search-input]') : null;
    const searchScopeInput = searchModal ? searchModal.querySelector('[data-search-scope]') : null;
    const searchChips = searchModal ? Array.from(searchModal.querySelectorAll('[data-search-chip]')) : [];

    const sendForm = (endpoint, formData) => fetch(endpoint, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then(async (response) => {
        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('Unexpected response from the server.');
        }
        if (!response.ok || (data && data.error)) {
            throw new Error((data && data.error) || 'Request failed.');
        }
        return data;
    });

    const sendAction = (endpoint, action, payload = {}) => {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('redirect', '');
        Object.entries(payload).forEach(([key, value]) => {
            if (value != null) {
                formData.append(key, value);
            }
        });
        return sendForm(endpoint, formData);
    };

    const canvas = doc.getElementById('ordersChart');
    if (canvas) {
        state.currency.prefix = canvas.dataset.prefix || '';
        state.currency.suffix = canvas.dataset.suffix || '';
    }

    const closeMobileMenu = () => {
        if (mobileMenu) {
            mobileMenu.classList.remove('open');
        }
    };

    const openMobileMenu = () => {
        if (mobileMenu) {
            mobileMenu.classList.add('open');
        }
    };

    if (menuBtn) {
        menuBtn.addEventListener('click', (event) => {
            event.preventDefault();
            if (!mobileMenu) {
                return;
            }
            if (mobileMenu.classList.contains('open')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });
    }

    if (mobileMenu) {
        mobileMenu.addEventListener('click', (event) => {
            if (event.target === mobileMenu) {
                closeMobileMenu();
            }
        });
    }

    const closeSearchModal = () => {
        if (!searchModal) {
            return;
        }
        searchModal.classList.remove('is-open');
        searchModal.setAttribute('aria-hidden', 'true');
        doc.body.classList.remove('has-search-open');
    };

    const openSearchModal = () => {
        if (!searchModal) {
            return;
        }
        searchModal.classList.add('is-open');
        searchModal.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('has-search-open');
        if (searchInput) {
            window.requestAnimationFrame(() => {
                searchInput.focus();
                searchInput.select();
            });
        }
    };

    const setSearchScope = (value) => {
        if (searchScopeInput) {
            searchScopeInput.value = value || '';
        }
        searchChips.forEach((chip) => {
            if (chip.dataset.value === value) {
                chip.classList.add('search-chip--active');
            } else {
                chip.classList.remove('search-chip--active');
            }
        });
    };

    searchOpeners.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            openSearchModal();
        });
    });

    searchDismissEls.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            closeSearchModal();
        });
    });

    if (searchModal) {
        searchModal.addEventListener('click', (event) => {
            if (event.target === searchModal) {
                closeSearchModal();
            }
        });
    }

    if (searchChips.length) {
        searchChips.forEach((chip) => {
            chip.addEventListener('click', (event) => {
                event.preventDefault();
                const value = chip.dataset.value || '';
                setSearchScope(value);
            });
        });
    }

    if (searchScopeInput) {
        setSearchScope(searchScopeInput.value || '');
    }

    // Dropdown menus
    const menus = Array.from(doc.querySelectorAll('[data-menu]'));
    menus.forEach((menu) => {
        const toggle = menu.querySelector('[data-menu-toggle]');
        if (!toggle) {
            return;
        }
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            menu.classList.toggle('open');
        });
    });

    doc.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.classList.remove('open');
            }
        });
    });

    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileMenu();
            menus.forEach((menu) => menu.classList.remove('open'));
            closeInvoiceModal();
            closeSearchModal();
        }
    });

    const ensureChart = () => {
        if (!canvas || typeof Chart === 'undefined') {
            return null;
        }
        if (state.chart) {
            return state.chart;
        }
        const existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }
        const ctx = canvas.getContext('2d');
        state.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Orders',
                        data: [],
                        backgroundColor: '#2f6bff',
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: 18,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                animation: false,
                transitions: { active: { animation: { duration: 0 } } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#aab3c2' },
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.06)' },
                        ticks: {
                            color: '#aab3c2',
                            callback: (value) => formatCurrency(value),
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => formatCurrency(context.parsed.y),
                        },
                    },
                },
            },
        });
        return state.chart;
    };

    const formatCurrency = (value, options) => {
        if (value == null || value === '' || Number.isNaN(Number(value))) {
            return '—';
        }
        const number = typeof value === 'number' ? value : Number(value);
        const formatter = new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
            ...options,
        });
        return `${state.currency.prefix}${formatter.format(number)}${state.currency.suffix}`;
    };

    const updateKpis = (payload) => {
        const revenueEl = doc.getElementById('kpiRevenue');
        if (revenueEl) {
            revenueEl.textContent = payload.revenue != null
                ? formatCurrency(payload.revenue, { maximumFractionDigits: 0 })
                : '—';
        }
        const clientsEl = doc.getElementById('kpiClients');
        if (clientsEl) {
            clientsEl.textContent = payload.newClients != null ? String(payload.newClients) : '—';
        }
        const aovEl = doc.getElementById('kpiAOV');
        if (aovEl) {
            aovEl.textContent = payload.averageOrder != null
                ? formatCurrency(payload.averageOrder)
                : '—';
        }
        const rangeInput = doc.getElementById('dateRangeText');
        if (rangeInput && payload.rangeText) {
            rangeInput.value = payload.rangeText;
        }
    };

    const setDashboardData = (payload = {}) => {
        const labels = Array.isArray(payload.labels) ? payload.labels : [];
        const series = Array.isArray(payload.series) ? payload.series : [];
        if (typeof payload.currencyPrefix === 'string') {
            state.currency.prefix = payload.currencyPrefix;
        }
        if (typeof payload.currencySuffix === 'string') {
            state.currency.suffix = payload.currencySuffix;
        }

        if (canvas && typeof Chart !== 'undefined') {
            const chart = ensureChart();
            if (chart) {
                chart.data.labels = labels;
                chart.data.datasets[0].data = series;
                chart.update();
            }
        }
        state.data = { labels, series };
        if (canvas) {
            canvas.dataset.prefix = state.currency.prefix;
            canvas.dataset.suffix = state.currency.suffix;
            try {
                const rangeInput = doc.getElementById('dateRangeText');
                const stored = {
                    labels,
                    series,
                    currencyPrefix: state.currency.prefix,
                    currencySuffix: state.currency.suffix,
                    revenue: payload.revenue ?? null,
                    newClients: payload.newClients ?? null,
                    averageOrder: payload.averageOrder ?? null,
                    rangeText: payload.rangeText ?? (rangeInput ? rangeInput.value : ''),
                };
                canvas.dataset.dashboard = JSON.stringify(stored);
            } catch (error) {
                // ignore serialization issues
            }
        }
        updateKpis(payload);
    };

    const downloadFile = (content, type, filename) => {
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const link = doc.createElement('a');
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        doc.body.appendChild(link);
        link.click();
        doc.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    const exportLinks = Array.from(doc.querySelectorAll('[data-export]'));
    exportLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const type = link.dataset.export;
            if (!type || !state.data.labels.length || !state.data.series.length) {
                return;
            }
            if (type === 'csv') {
                const rows = [['Date', 'Amount']];
                state.data.labels.forEach((label, index) => {
                    rows.push([label, state.data.series[index] ?? '']);
                });
                const csv = rows.map((row) => row.join(',')).join('\n');
                downloadFile(csv, 'text/csv', 'orders.csv');
            } else {
                const json = JSON.stringify({
                    labels: state.data.labels,
                    series: state.data.series,
                }, null, 2);
                downloadFile(json, 'application/json', 'orders.json');
            }
            const parentMenu = link.closest('[data-menu]');
            if (parentMenu) {
                parentMenu.classList.remove('open');
            }
        });
    });

    const todayBtn = doc.getElementById('todayBtn');
    if (todayBtn) {
        todayBtn.addEventListener('click', (event) => {
            event.preventDefault();
            const today = new Date();
            const rangeInput = doc.getElementById('dateRangeText');
            if (rangeInput) {
                rangeInput.value = formatRange(today, today);
            }
        });
    }

    const formatRange = (from, to) => {
        const format = (date) => date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
        return `${format(from)} to ${format(to)}`;
    };

    const lastNDays = (n) => {
        const labels = [];
        const dates = [];
        const now = new Date();
        for (let i = n - 1; i >= 0; i -= 1) {
            const date = new Date(now);
            date.setDate(now.getDate() - i);
            labels.push(date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
            dates.push(new Date(date.getFullYear(), date.getMonth(), date.getDate()));
        }
        return { labels, dates };
    };

    const bootstrapChart = () => {
        if (!canvas) {
            return;
        }
        const raw = canvas.dataset.dashboard;
        if (raw) {
            try {
                const payload = JSON.parse(raw);
                payload.currencyPrefix = payload.currencyPrefix ?? state.currency.prefix;
                payload.currencySuffix = payload.currencySuffix ?? state.currency.suffix;
                setDashboardData(payload);
                return;
            } catch (error) {
                console.warn('Failed to parse dashboard dataset', error);
            }
        }
        if (typeof Chart === 'undefined') {
            return;
        }
        const sample = (() => {
            const labels = [];
            const end = new Date();
            const start = new Date();
            start.setDate(end.getDate() - 29);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                labels.push(d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
            }
            const series = [
                440, 420, 430, 280, 360, 350, 370, 470, 490, 430,
                400, 420, 460, 310, 480, 430, 410, 460, 420, 450,
                330, 470, 430, 450, 420, 310, 480, 500, 430, 440,
            ];
            const revenue = series.reduce((total, value) => total + value, 0);
            const averageOrder = revenue / series.length;
            return { labels, series, revenue, averageOrder, newClients: 18 };
        })();
        setDashboardData(sample);
    };

    const invoiceModal = doc.querySelector('[data-invoice-modal]');
    if (invoiceModal) {
        const actionEndpoint = invoiceModal.getAttribute('data-action') || 'dashboard.php';
        const numberEl = invoiceModal.querySelector('[data-invoice-number]');
        const summaryEl = invoiceModal.querySelector('[data-invoice-summary]');
        const feedbackEl = invoiceModal.querySelector('[data-invoice-feedback]');
        const providerEl = invoiceModal.querySelector('[data-payment-provider]');
        const paymentIntro = invoiceModal.querySelector('[data-payment-intro]');
        const paypalContainer = invoiceModal.querySelector('[data-paypal-container]');
        const stripePanel = invoiceModal.querySelector('[data-stripe-card]');
        const stripeMount = stripePanel ? stripePanel.querySelector('[data-stripe-card-element]') : null;
        const stripeSubmit = invoiceModal.querySelector('[data-stripe-submit]');
        const stripeFeedback = invoiceModal.querySelector('[data-stripe-feedback]');
        const paymentRequestPanel = invoiceModal.querySelector('[data-payment-request]');
        const paymentRequestMount = paymentRequestPanel ? paymentRequestPanel.querySelector('[data-payment-request-button]') : null;
        const closeBtn = invoiceModal.querySelector('[data-invoice-close]');
        let paypalButtons = null;

        const state = {
            details: null,
            options: {},
            orderId: null,
            provider: 'paypal',
        };

        const stripeState = {
            instance: null,
            key: null,
            elements: null,
            card: null,
            request: null,
            requestButton: null,
            clientSecret: null,
            intentId: null,
            subscriptionId: null,
        };

        const formatMoney = (amount, currency) => {
            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency,
                    minimumFractionDigits: 2,
                }).format(amount);
            } catch (error) {
                return `${currency} ${amount.toFixed(2)}`;
            }
        };

        const waitForPayPal = () => new Promise((resolve, reject) => {
            if (window.paypal && typeof window.paypal.Buttons === 'function') {
                resolve(window.paypal);
                return;
            }
            let attempts = 0;
            const timer = window.setInterval(() => {
                attempts += 1;
                if (window.paypal && typeof window.paypal.Buttons === 'function') {
                    window.clearInterval(timer);
                    resolve(window.paypal);
                } else if (attempts > 40) {
                    window.clearInterval(timer);
                    reject(new Error('PayPal SDK did not load.'));
                }
            }, 150);
        });

        const hidePanels = () => {
            if (paypalContainer) {
                paypalContainer.hidden = true;
                paypalContainer.innerHTML = '';
            }
            if (stripePanel) {
                stripePanel.hidden = true;
            }
            if (paymentRequestPanel) {
                paymentRequestPanel.hidden = true;
            }
            if (providerEl) {
                providerEl.hidden = true;
                providerEl.textContent = '';
            }
        };

        const resetStripe = () => {
            if (stripeState.card) {
                if (typeof stripeState.card.unmount === 'function') {
                    stripeState.card.unmount();
                }
                if (typeof stripeState.card.destroy === 'function') {
                    stripeState.card.destroy();
                }
            }
            if (stripeState.requestButton && typeof stripeState.requestButton.unmount === 'function') {
                stripeState.requestButton.unmount();
            }
            stripeState.card = null;
            stripeState.requestButton = null;
            stripeState.request = null;
            stripeState.clientSecret = null;
            stripeState.intentId = null;
            stripeState.subscriptionId = null;
            stripeState.elements = null;
            stripeState.provider = null;
            if (stripeFeedback) {
                stripeFeedback.textContent = '';
            }
            if (stripeSubmit) {
                stripeSubmit.disabled = false;
                stripeSubmit.textContent = 'Pay now';
            }
        };

        const ensureStripe = (publishableKey) => new Promise((resolve, reject) => {
            if (!window.Stripe) {
                reject(new Error('Stripe SDK not loaded.'));
                return;
            }
            if (stripeState.instance && stripeState.key === publishableKey) {
                resolve(stripeState.instance);
                return;
            }
            stripeState.instance = window.Stripe(publishableKey);
            stripeState.key = publishableKey;
            stripeState.elements = null;
            resolve(stripeState.instance);
        });

        const mountCardElement = () => {
            if (!stripeState.instance || !stripeMount) {
                return null;
            }
            if (!stripeState.elements) {
                stripeState.elements = stripeState.instance.elements();
            }
            if (!stripeState.card) {
                stripeState.card = stripeState.elements.create('card');
                stripeState.card.mount(stripeMount);
            }
            return stripeState.card;
        };

        const markInvoicePaid = (invoiceId) => {
            const status = doc.querySelector(`[data-invoice-status="${invoiceId}"]`);
            if (status) {
                status.textContent = 'Paid';
                status.className = 'badge badge--paid';
            }
            const trigger = doc.querySelector(`[data-invoice-pay][data-invoice-id="${invoiceId}"]`);
            if (trigger) {
                const badge = doc.createElement('span');
                badge.className = 'badge badge--paid';
                badge.textContent = 'Paid';
                trigger.replaceWith(badge);
            }
        };

        const emitPaid = (invoiceId, serviceName) => {
            const detail = {
                invoiceId,
                orderId: state.orderId,
                service: serviceName || (state.details && state.details.service) || 'Invoice',
            };
            doc.dispatchEvent(new CustomEvent('portal:invoice-paid', { detail }));
            if (state.options && typeof state.options.onPaid === 'function') {
                try {
                    state.options.onPaid(detail);
                } catch (error) {
                    console.error('portal:invoice-paid callback failed', error);
                }
            }
        };

        const finalizeStripePayment = () => {
            if (!state.details || !stripeState.intentId) {
                return Promise.resolve();
            }
            if (feedbackEl) {
                feedbackEl.textContent = 'Finalising payment…';
            }
            return sendAction(actionEndpoint, 'finalize_stripe_payment', {
                invoice_id: state.details.id,
                payment_intent: stripeState.intentId,
            })
                .then(() => {
                    markInvoicePaid(state.details.id);
                    if (feedbackEl) {
                        feedbackEl.textContent = 'Payment complete. Thank you!';
                    }
                    emitPaid(state.details.id, state.details.service);
                    window.setTimeout(() => {
                        closeInvoiceModal();
                    }, 1500);
                })
                .catch((error) => {
                    if (feedbackEl) {
                        feedbackEl.textContent = error.message || 'Unable to finalise the payment.';
                    }
                    throw error;
                });
        };

        const setupPaymentRequest = (stripe, details) => {
            if (!paymentRequestPanel || !paymentRequestMount) {
                return;
            }
            const currency = (details.currency || 'GBP').toLowerCase();
            const country = currency === 'gbp' ? 'GB' : 'US';
            const amountMinor = Math.round((details.amount || 0) * 100);
            const request = stripe.paymentRequest({
                country,
                currency,
                total: {
                    label: details.service || 'Invoice',
                    amount: amountMinor,
                },
                requestPayerName: true,
                requestPayerEmail: true,
            });
            stripeState.request = request;
            request.canMakePayment().then((result) => {
                if (!result) {
                    paymentRequestPanel.hidden = true;
                    return;
                }
                paymentRequestPanel.hidden = false;
                if (stripeState.requestButton && typeof stripeState.requestButton.unmount === 'function') {
                    stripeState.requestButton.unmount();
                }
                stripeState.requestButton = stripe.elements().create('paymentRequestButton', {
                    paymentRequest: request,
                    style: { paymentRequestButton: { type: 'buy', theme: 'dark' } },
                });
                stripeState.requestButton.mount(paymentRequestMount);
            });
            request.on('paymentmethod', async (event) => {
                try {
                    const { error } = await stripe.confirmCardPayment(stripeState.clientSecret, {
                        payment_method: event.paymentMethod.id,
                    }, { handleActions: false });
                    if (error) {
                        event.complete('fail');
                        if (stripeFeedback) {
                            stripeFeedback.textContent = error.message || 'Payment failed.';
                        }
                        return;
                    }
                    event.complete('success');
                    const result = await stripe.confirmCardPayment(stripeState.clientSecret);
                    if (result.error) {
                        if (stripeFeedback) {
                            stripeFeedback.textContent = result.error.message || 'Payment failed.';
                        }
                        return;
                    }
                    finalizeStripePayment();
                } catch (error) {
                    event.complete('fail');
                    if (stripeFeedback) {
                        stripeFeedback.textContent = error.message || 'Payment failed.';
                    }
                }
            });
        };

        const openPayPal = (details) => {
            hidePanels();
            if (providerEl) {
                providerEl.hidden = false;
                providerEl.textContent = 'PayPal';
            }
            if (paymentIntro) {
                paymentIntro.textContent = details.isSubscription
                    ? 'Approve the subscription in PayPal to start recurring billing.'
                    : 'We’ll redirect you after PayPal confirms the payment.';
            }
            if (!paypalContainer) {
                return;
            }
            paypalContainer.hidden = false;
            paypalContainer.innerHTML = '';
            if (paypalButtons && typeof paypalButtons.close === 'function') {
                paypalButtons.close();
            }
            waitForPayPal()
                .then((paypal) => {
                    const buttonConfig = {
                        style: { layout: 'vertical' },
                        onCancel: () => {
                            if (feedbackEl) {
                                feedbackEl.textContent = 'Payment was cancelled before completion.';
                            }
                        },
                        onError: (error) => {
                            if (feedbackEl) {
                                feedbackEl.textContent = error.message || 'PayPal encountered an issue.';
                            }
                        },
                    };

                    if (details.isSubscription) {
                        buttonConfig.createSubscription = (data, actions) => {
                            if (feedbackEl) {
                                feedbackEl.textContent = 'Preparing subscription…';
                            }
                            return sendAction(actionEndpoint, 'create_paypal_order', { invoice_id: details.id })
                                .then((payload) => {
                                    if (!payload.planID) {
                                        const message = payload.error || 'Unable to prepare the PayPal subscription.';
                                        throw new Error(message);
                                    }
                                    const createPayload = { plan_id: payload.planID };
                                    if (payload.customID) {
                                        createPayload.custom_id = payload.customID;
                                    }
                                    return actions.subscription.create(createPayload);
                                })
                                .catch((error) => {
                                    if (feedbackEl) {
                                        feedbackEl.textContent = error.message || String(error);
                                    }
                                    throw error;
                                });
                        };
                        buttonConfig.onApprove = (data) => {
                            if (feedbackEl) {
                                feedbackEl.textContent = 'Activating subscription…';
                            }
                            return sendAction(actionEndpoint, 'capture_paypal_subscription', {
                                invoice_id: details.id,
                                paypal_subscription_id: data.subscriptionID,
                            })
                                .then(() => {
                                    markInvoicePaid(details.id);
                                    if (feedbackEl) {
                                        feedbackEl.textContent = 'Subscription started. Thank you!';
                                    }
                                    emitPaid(details.id, details.service);
                                    window.setTimeout(() => {
                                        closeInvoiceModal();
                                    }, 1500);
                                })
                                .catch((error) => {
                                    if (feedbackEl) {
                                        feedbackEl.textContent = error.message || String(error);
                                    }
                                });
                        };
                    } else {
                        buttonConfig.createOrder = () => {
                            if (feedbackEl) {
                                feedbackEl.textContent = 'Creating PayPal order…';
                            }
                            return sendAction(actionEndpoint, 'create_paypal_order', { invoice_id: details.id })
                                .then((data) => data.orderID)
                                .catch((error) => {
                                    if (feedbackEl) {
                                        feedbackEl.textContent = error.message || String(error);
                                    }
                                    throw error;
                                });
                        };
                        buttonConfig.onApprove = (data) => {
                            if (feedbackEl) {
                                feedbackEl.textContent = 'Capturing payment…';
                            }
                            return sendAction(actionEndpoint, 'capture_paypal_order', {
                                invoice_id: details.id,
                                paypal_order_id: data.orderID,
                            })
                                .then(() => {
                                    markInvoicePaid(details.id);
                                    if (feedbackEl) {
                                        feedbackEl.textContent = 'Payment complete. Thank you!';
                                    }
                                    emitPaid(details.id, details.service);
                                    window.setTimeout(() => {
                                        closeInvoiceModal();
                                    }, 1500);
                                })
                                .catch((error) => {
                                    if (feedbackEl) {
                                        feedbackEl.textContent = error.message || String(error);
                                    }
                                });
                        };
                    }

                    paypalButtons = paypal.Buttons(buttonConfig);
                    if (paypalButtons && paypalButtons.isEligible()) {
                        return paypalButtons.render(paypalContainer);
                    }
                    throw new Error('PayPal checkout is not available.');
                })
                .catch((error) => {
                    if (feedbackEl) {
                        feedbackEl.textContent = error.message || 'Unable to load PayPal checkout.';
                    }
                });
        };

        const prepareStripe = (details, provider) => {
            hidePanels();
            resetStripe();
            if (feedbackEl) {
                feedbackEl.textContent = details.isSubscription ? 'Preparing subscription payment…' : 'Preparing payment…';
            }
            sendAction(actionEndpoint, 'create_stripe_intent', {
                invoice_id: details.id,
                mode: provider === 'google_pay' ? 'google_pay' : 'card',
            })
                .then((payload) => ensureStripe(payload.publishable_key)
                    .then((stripe) => {
                        stripeState.instance = stripe;
                        stripeState.clientSecret = payload.client_secret;
                        stripeState.intentId = payload.intent;
                        stripeState.provider = provider;
                        if (payload.subscription) {
                            stripeState.subscriptionId = payload.subscription;
                        } else {
                            stripeState.subscriptionId = null;
                        }
                        if (stripeSubmit) {
                            stripeSubmit.textContent = `Pay ${formatMoney(details.amount, details.currency || 'GBP')}`;
                            stripeSubmit.disabled = false;
                        }
                        if (providerEl) {
                            providerEl.hidden = false;
                            if (details.isSubscription) {
                                providerEl.textContent = 'Card subscription';
                            } else {
                                providerEl.textContent = provider === 'google_pay' ? 'Google Pay' : 'Card payment';
                            }
                        }
                        if (paymentIntro) {
                            if (details.isSubscription) {
                                paymentIntro.textContent = 'Your card will be saved for future renewals after this payment.';
                            } else {
                                paymentIntro.textContent = provider === 'google_pay'
                                    ? 'Use Google Pay on supported devices or enter your card details below.'
                                    : 'Enter your card details to complete payment.';
                            }
                        }
                        if (stripePanel) {
                            stripePanel.hidden = false;
                            mountCardElement();
                        }
                        if (provider === 'google_pay') {
                            setupPaymentRequest(stripe, details);
                        } else if (paymentRequestPanel) {
                            paymentRequestPanel.hidden = true;
                        }
                        if (feedbackEl) {
                            feedbackEl.textContent = '';
                        }
                    }))
                .catch((error) => {
                    hidePanels();
                    if (feedbackEl) {
                        feedbackEl.textContent = error.message || 'Unable to prepare the payment.';
                    }
                });
        };

        const openInvoiceModal = (details, options = {}) => {
            state.details = details;
            state.options = options || {};
            state.orderId = details.orderId || null;
            state.provider = (details.paymentMethod || 'paypal').toLowerCase();
            invoiceModal.hidden = false;
            invoiceModal.classList.add('open');
            if (numberEl) {
                numberEl.textContent = `#${details.id}`;
            }
            if (summaryEl) {
                const money = formatMoney(details.amount, details.currency || 'GBP');
                summaryEl.textContent = `${details.service} · ${money}`;
            }
            if (feedbackEl) {
                feedbackEl.textContent = options.message || '';
            }
            hidePanels();
            resetStripe();
            const provider = state.provider;
            if (provider === 'stripe' || provider === 'google_pay') {
                prepareStripe(details, provider);
            } else if (provider === 'paypal') {
                openPayPal(details);
            } else if (feedbackEl) {
                feedbackEl.textContent = 'This invoice cannot be paid online.';
            }
        };

        const closeModal = () => {
            if (!invoiceModal || invoiceModal.hidden) {
                return;
            }
            invoiceModal.hidden = true;
            invoiceModal.classList.remove('open');
            if (feedbackEl) {
                feedbackEl.textContent = '';
            }
            hidePanels();
            resetStripe();
            if (paypalButtons && typeof paypalButtons.close === 'function') {
                paypalButtons.close();
            }
            paypalButtons = null;
            state.details = null;
            state.options = {};
            state.orderId = null;
        };

        const paymentsAPI = {
            open(details, options = {}) {
                if (!details || !details.id) {
                    return;
                }
                const merged = { ...details };
                merged.paymentMethod = merged.paymentMethod || 'paypal';
                if (typeof merged.subscriptionId === 'number') {
                    merged.isSubscription = merged.subscriptionId > 0;
                }
                openInvoiceModal(merged, options);
            },
            close: () => {
                closeModal();
            },
        };

        window.PortalPayments = paymentsAPI;
        closeInvoiceModal = paymentsAPI.close;

        if (stripeSubmit) {
            stripeSubmit.addEventListener('click', () => {
                if (!stripeState.instance || !stripeState.clientSecret || !stripeState.card) {
                    return;
                }
                stripeSubmit.disabled = true;
                if (stripeFeedback) {
                    stripeFeedback.textContent = 'Confirming payment…';
                }
                stripeState.instance.confirmCardPayment(stripeState.clientSecret, {
                    payment_method: {
                        card: stripeState.card,
                    },
                })
                    .then((result) => {
                        if (result.error) {
                            if (stripeFeedback) {
                                stripeFeedback.textContent = result.error.message || 'Payment failed.';
                            }
                            stripeSubmit.disabled = false;
                            return;
                        }
                        finalizeStripePayment();
                    })
                    .catch((error) => {
                        if (stripeFeedback) {
                            stripeFeedback.textContent = error.message || 'Payment failed.';
                        }
                        stripeSubmit.disabled = false;
                    });
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                paymentsAPI.close();
            });
        }
        invoiceModal.addEventListener('click', (event) => {
            if (event.target === invoiceModal) {
                paymentsAPI.close();
            }
        });

        const triggers = Array.from(doc.querySelectorAll('[data-invoice-pay]'));
        triggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                const invoiceId = Number(trigger.getAttribute('data-invoice-id'));
                const amount = Number(trigger.getAttribute('data-invoice-amount'));
                const currency = trigger.getAttribute('data-invoice-currency') || 'GBP';
                const service = trigger.getAttribute('data-invoice-service') || 'Invoice';
                const paymentMethod = (trigger.getAttribute('data-invoice-method') || 'paypal').toLowerCase();
                const subscriptionId = Number(trigger.getAttribute('data-invoice-subscription') || '0');
                const subscriptionInterval = trigger.getAttribute('data-invoice-interval') || '';
                if (!invoiceId || !amount) {
                    return;
                }
                paymentsAPI.open({
                    id: invoiceId,
                    amount,
                    service,
                    currency,
                    paymentMethod,
                    subscriptionId,
                    subscriptionInterval,
                    isSubscription: subscriptionId > 0,
                });
            });
        });
    }

    const serviceForms = Array.from(doc.querySelectorAll('[data-service-order-form]'));
    if (serviceForms.length) {
        const formatMoney = (amount, currency) => {
            try {
                return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);
            } catch (error) {
                return `${currency} ${amount.toFixed(2)}`;
            }
        };

        const clearFeedback = (wrapper) => {
            if (!wrapper) {
                return;
            }
            const feedback = wrapper.querySelector('[data-service-feedback]');
            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.className = 'service-feedback';
            }
        };

        const showFeedback = (wrapper, message, tone = 'info') => {
            if (!wrapper) {
                return;
            }
            const feedback = wrapper.querySelector('[data-service-feedback]');
            if (!feedback) {
                return;
            }
            feedback.hidden = false;
            feedback.textContent = message;
            feedback.className = 'service-feedback';
            if (tone) {
                feedback.classList.add(`service-feedback--${tone}`);
            }
        };

        serviceForms.forEach((form) => {
            const card = form.closest('.service-checkout');
            const summaryTotal = card ? card.querySelector('[data-summary-total]') : null;
            const planSummary = card ? card.querySelector('[data-plan-summary-label] dd') : null;
            const hourSummary = card ? card.querySelector('[data-hour-summary] dd') : null;
            const currency = form.dataset.serviceCurrency || 'GBP';
            const orderField = form.querySelector('[data-order-total]');
            const paymentField = form.querySelector('[name="payment_method"]');
            const paymentOptions = Array.from(form.querySelectorAll('[data-payment-option]'));
            const planCards = Array.from(form.querySelectorAll('[data-plan-choice]'));
            const hourInput = form.querySelector('[data-hour-input]');
            const serviceName = form.dataset.serviceName || 'Invoice';
            const baseRate = Number(form.dataset.serviceRate || form.dataset.servicePrice || 0);

            const updateTotal = (value) => {
                const amount = Math.max(0, Number(value || 0));
                if (orderField) {
                    orderField.value = amount.toFixed(2);
                }
                if (summaryTotal) {
                    summaryTotal.textContent = formatMoney(amount, currency);
                }
                return amount;
            };

            const setPaymentActive = (value) => {
                paymentOptions.forEach((option, index) => {
                    const optionValue = option.dataset.value || 'manual';
                    const selected = optionValue === value || (!value && index === 0);
                    option.classList.toggle('payment-option--active', selected);
                    const input = option.querySelector('input');
                    if (input) {
                        input.checked = selected;
                    }
                });
                if (paymentField) {
                    paymentField.value = value;
                }
            };

            const setPlanActive = (choice) => {
                planCards.forEach((option) => option.classList.toggle('plan-card--active', option === choice));
                if (!choice) {
                    return;
                }
                const price = Number(choice.dataset.price || form.dataset.servicePrice || 0);
                const labelNode = choice.querySelector('.plan-card__label');
                const label = labelNode ? labelNode.textContent.trim() : choice.dataset.value || '';
                form.dataset.servicePrice = Number.isFinite(price) ? price.toFixed(2) : form.dataset.servicePrice;
                updateTotal(price);
                if (planSummary && label) {
                    planSummary.textContent = label;
                }
            };

            const applyInitialState = () => {
                if (planCards.length) {
                    const defaultPlan = planCards.find((option) => {
                        const input = option.querySelector('input');
                        return input && input.checked;
                    }) || planCards[0];
                    if (defaultPlan) {
                        const input = defaultPlan.querySelector('input');
                        if (input) {
                            input.checked = true;
                        }
                        setPlanActive(defaultPlan);
                    }
                } else {
                    updateTotal(Number(form.dataset.servicePrice || 0));
                }

                if (hourInput) {
                    const updateHours = () => {
                        let hours = Number(hourInput.value || 1);
                        if (!Number.isFinite(hours) || hours < 1) {
                            hours = 1;
                            hourInput.value = String(hours);
                        }
                        if (hourSummary) {
                            hourSummary.textContent = `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
                        }
                        const total = (Number(form.dataset.serviceRate || baseRate) || 0) * hours;
                        updateTotal(total);
                    };
                    hourInput.addEventListener('input', updateHours);
                    hourInput.addEventListener('change', updateHours);
                    updateHours();
                }

                const initialPayment = paymentField ? paymentField.value || (paymentOptions[0] && paymentOptions[0].dataset.value) || 'manual' : (paymentOptions[0] && paymentOptions[0].dataset.value) || 'manual';
                setPaymentActive(initialPayment);
            };

            planCards.forEach((choice) => {
                const input = choice.querySelector('input');
                if (!input) {
                    return;
                }
                if (input.checked) {
                    choice.classList.add('plan-card--active');
                }
                input.addEventListener('change', () => {
                    if (input.checked) {
                        setPlanActive(choice);
                    }
                });
            });

            paymentOptions.forEach((option) => {
                const value = option.dataset.value || 'manual';
                option.addEventListener('click', (event) => {
                    event.preventDefault();
                    setPaymentActive(value);
                });
                const input = option.querySelector('input');
                if (input) {
                    input.addEventListener('change', () => {
                        if (input.checked) {
                            setPaymentActive(value);
                        }
                    });
                }
            });

            applyInitialState();

            form.addEventListener('submit', (event) => {
                const paymentMethod = (paymentField ? paymentField.value : null) || 'manual';
                if (!['paypal', 'stripe', 'google_pay'].includes(paymentMethod)) {
                    return;
                }
                if (!window.PortalPayments || typeof window.PortalPayments.open !== 'function') {
                    return;
                }
                event.preventDefault();
                clearFeedback(card);

                const submitBtn = form.querySelector('[type="submit"]');
                const setLoading = (loading) => {
                    if (!submitBtn) {
                        return;
                    }
                    if (loading) {
                        submitBtn.dataset.originalLabel = submitBtn.textContent;
                        submitBtn.textContent = 'Preparing…';
                        submitBtn.disabled = true;
                    } else {
                        submitBtn.disabled = false;
                        if (submitBtn.dataset.originalLabel) {
                            submitBtn.textContent = submitBtn.dataset.originalLabel;
                            delete submitBtn.dataset.originalLabel;
                        }
                    }
                };

                setLoading(true);
                const formData = new FormData(form);
                formData.set('payment_method', paymentMethod);
                if (!formData.has('redirect')) {
                    formData.append('redirect', 'dashboard/services');
                }
                const endpoint = form.getAttribute('action') || 'dashboard.php';
                sendForm(endpoint, formData)
                    .then((data) => {
                        const invoiceId = Number(data.invoice_id || data.id);
                        if (!invoiceId) {
                            throw new Error('Unable to prepare the checkout.');
                        }
                        const amount = Number(data.amount ?? orderField?.value ?? form.dataset.servicePrice ?? 0);
                        const currencyCode = data.currency || form.dataset.serviceCurrency || 'GBP';
                        const service = data.service || serviceName;

                        showFeedback(card, 'Order saved. Complete the payment to confirm.', 'info');

                        const subscriptionId = Number(data.subscription_id || 0);
                        const subscriptionInterval = data.subscription_interval || '';
                        window.PortalPayments.open({
                            id: invoiceId,
                            orderId: data.order_id || null,
                            amount,
                            currency: currencyCode,
                            service,
                            paymentMethod,
                            subscriptionId: Number.isFinite(subscriptionId) && subscriptionId > 0 ? subscriptionId : 0,
                            subscriptionInterval,
                        }, {
                            onPaid: () => {
                                showFeedback(card, 'Payment complete! We’ll be in touch shortly.', 'success');
                                form.reset();
                                applyInitialState();
                            },
                        });
                    })
                    .catch((error) => {
                        showFeedback(card, error.message || 'Unable to create the order.', 'error');
                    })
                    .finally(() => {
                        setLoading(false);
                    });
            });
        });
    }

    bootstrapChart();

    window.PortalDashboard = {
        setDashboardData,
        formatRange,
        lastNDays,
    };
})();
