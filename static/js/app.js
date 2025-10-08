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

    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileMenu();
            menus.forEach((menu) => menu.classList.remove('open'));
            closeInvoiceModal();
        }
    });

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
        const container = invoiceModal.querySelector('[data-paypal-container]');
        const closeBtn = invoiceModal.querySelector('[data-invoice-close]');
        let paypalButtons = null;
        let activeOrderId = null;
        let activeOptions = {};
        let activeDetails = null;
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
                orderId: activeOrderId,
                service: serviceName || (activeDetails && activeDetails.service) || 'Invoice',
            };
            doc.dispatchEvent(new CustomEvent('portal:invoice-paid', { detail }));
            if (activeOptions && typeof activeOptions.onPaid === 'function') {
                try {
                    activeOptions.onPaid(detail);
                } catch (error) {
                    console.error('portal:invoice-paid callback failed', error);
                }
            }
        };

        const renderButtons = (details) => {
            if (!container) {
                return;
            }
            container.innerHTML = '';
            if (paypalButtons && typeof paypalButtons.close === 'function') {
                paypalButtons.close();
            }
            waitForPayPal()
                .then((paypal) => {
                    paypalButtons = paypal.Buttons({
                        style: { layout: 'vertical' },
                        createOrder: () => {
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
                        },
                        onApprove: (data) => {
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
                        },
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
                    });
                    if (paypalButtons && paypalButtons.isEligible()) {
                        return paypalButtons.render(container);
                    }
                    throw new Error('PayPal checkout is not available.');
                })
                .catch((error) => {
                    if (feedbackEl) {
                        feedbackEl.textContent = error.message || 'Unable to load PayPal checkout.';
                    }
                });
        };

        const openInvoiceModal = (details, options = {}) => {
            activeDetails = details;
            activeOptions = options || {};
            activeOrderId = details.orderId || null;
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
            renderButtons(details);
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
            if (container) {
                container.innerHTML = '';
            }
            if (paypalButtons && typeof paypalButtons.close === 'function') {
                paypalButtons.close();
            }
            activeOptions = {};
            activeOrderId = null;
            activeDetails = null;
        };

        const paymentsAPI = {
            open(details, options = {}) {
                if (!details || !details.id) {
                    return;
                }
                openInvoiceModal(details, options);
            },
            close: () => {
                closeModal();
            },
        };

        window.PortalPayments = paymentsAPI;
        closeInvoiceModal = paymentsAPI.close;

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
                if (!invoiceId || !amount) {
                    return;
                }
                paymentsAPI.open({ id: invoiceId, amount, service, currency });
            });
        });
    }

    const serviceForms = Array.from(doc.querySelectorAll('[data-service-order-form]'));
    if (serviceForms.length) {
        const clearFeedback = (card) => {
            if (!card) {
                return;
            }
            const feedback = card.querySelector('[data-service-feedback]');
            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.className = 'service-feedback';
            }
        };

        const showFeedback = (card, message, tone = 'info') => {
            if (!card) {
                return;
            }
            const feedback = card.querySelector('[data-service-feedback]');
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
            form.addEventListener('submit', (event) => {
                const methodField = form.querySelector('[name="payment_method"]');
                const paymentMethod = methodField ? methodField.value : 'manual';
                if (paymentMethod !== 'paypal') {
                    return;
                }
                if (!window.PortalPayments || typeof window.PortalPayments.open !== 'function') {
                    return;
                }

                event.preventDefault();

                const card = form.closest('[data-service-card]');
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
                formData.set('payment_method', 'paypal');
                if (!formData.has('redirect')) {
                    formData.append('redirect', 'dashboard/services');
                }

                const endpoint = form.getAttribute('action') || 'dashboard.php';
                sendForm(endpoint, formData)
                    .then((data) => {
                        const invoiceId = Number(data.invoice_id || data.id);
                        if (!invoiceId) {
                            throw new Error('Unable to prepare the PayPal checkout.');
                        }
                        const amount = Number(data.amount ?? form.dataset.servicePrice ?? 0);
                        const currency = data.currency || form.dataset.serviceCurrency || 'GBP';
                        const service = data.service || form.dataset.serviceName || 'Invoice';

                        showFeedback(card, 'Order saved. Complete the PayPal payment to confirm.', 'info');

                        window.PortalPayments.open({
                            id: invoiceId,
                            orderId: data.order_id || null,
                            amount: amount || 0,
                            currency,
                            service,
                        }, {
                            onPaid: () => {
                                showFeedback(card, 'Payment complete! We’ll be in touch shortly.', 'success');
                                form.reset();
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
