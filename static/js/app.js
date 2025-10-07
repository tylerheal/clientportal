(function(){
  const qs = (sel, ctx=document) => ctx.querySelector(sel);
  const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  /* ===== Mobile menu ===== */
  const menuBtn = qs('#menuBtn');
  const mobileMenu = qs('#mobileMenu');
  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
      if (mobileMenu.classList.contains('open')) {
        mobileMenu.removeAttribute('hidden');
      } else {
        mobileMenu.setAttribute('hidden', '');
      }
    });
    mobileMenu.addEventListener('click', (evt) => {
      if (evt.target === mobileMenu) {
        mobileMenu.classList.remove('open');
        mobileMenu.setAttribute('hidden', '');
      }
    });
  }

  /* ===== Dropdown menus ===== */
  qsa('.menu').forEach((menu) => {
    const trigger = menu.querySelector('button');
    if (!trigger) return;
    trigger.addEventListener('click', (evt) => {
      evt.preventDefault();
      menu.classList.toggle('open');
    });
  });
  document.addEventListener('click', (evt) => {
    qsa('.menu.open').forEach((menu) => {
      if (!menu.contains(evt.target)) {
        menu.classList.remove('open');
      }
    });
  });

  /* ===== Notification drawer support ===== */
  const notificationToggle = qs('[data-notification-toggle]');
  const notificationPanel = qs('[data-notification-panel]');
  if (notificationToggle && notificationPanel) {
    notificationToggle.addEventListener('click', () => {
      notificationPanel.toggleAttribute('hidden');
    });
  }

  /* ===== Date helpers ===== */
  const rangeInput = qs('#dateRangeText');
  const todayBtn = qs('#todayBtn');
  function formatRange(from, to) {
    const fmt = (d) => d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    return `${fmt(from)} to ${fmt(to)}`;
  }
  function lastNDays(n) {
    const labels = [];
    const now = new Date();
    for (let i = n - 1; i >= 0; i -= 1) {
      const d = new Date(now);
      d.setDate(now.getDate() - i);
      labels.push(d);
    }
    return labels;
  }
  if (rangeInput) {
    const days = lastNDays(30);
    rangeInput.value = formatRange(days[0], days[days.length - 1]);
  }
  if (todayBtn && rangeInput) {
    todayBtn.addEventListener('click', () => {
      const today = new Date();
      rangeInput.value = formatRange(today, today);
      document.dispatchEvent(new CustomEvent('dashboard:today'));
    });
  }

  /* ===== Chart.js setup ===== */
  function initialiseChart() {
    const canvas = qs('#ordersChart');
    if (!canvas || typeof Chart === 'undefined') {
      return null;
    }
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    return new Chart(canvas, {
      type: 'bar',
      data: {
        labels: [],
        datasets: [{
          label: 'Orders',
          data: [],
          backgroundColor: '#2f6bff',
          borderRadius: 6,
          borderSkipped: false,
          barThickness: 18
        }]
      },
      options: {
        maintainAspectRatio: false,
        animation: false,
        transitions: { active: { animation: { duration: 0 } } },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#aab3c2' } },
          y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#aab3c2', callback: (v) => '$' + v } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (ctx) => `$${ctx.parsed.y}` } }
        }
      }
    });
  }

  const chart = initialiseChart();
  const KPI_IDS = {
    revenue: 'kpiRevenue',
    clients: 'kpiClients',
    aov: 'kpiAOV'
  };

  const FIXED_DEMO = (() => {
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
      330, 470, 430, 450, 420, 310, 480, 500, 430, 440
    ];
    const revenue = series.reduce((acc, value) => acc + value, 0);
    const averageOrder = revenue / series.length;
    const newClients = 18;
    return { labels, series, revenue, newClients, averageOrder };
  })();

  function formatCurrency(value) {
    return value != null
      ? value.toLocaleString(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
      : '—';
  }

  function updateKpi(id, value) {
    const el = qs(`#${id}`);
    if (!el) return;
    el.textContent = value;
  }

  function setDashboardData(data) {
    if (chart) {
      chart.data.labels = data.labels;
      chart.data.datasets[0].data = data.series;
      chart.update();
    }
    updateKpi(KPI_IDS.revenue, formatCurrency(data.revenue));
    updateKpi(KPI_IDS.clients, data.newClients ?? '—');
    updateKpi(KPI_IDS.aov, data.averageOrder != null
      ? data.averageOrder.toLocaleString(undefined, { style: 'currency', currency: 'USD' })
      : '—');
  }

  window.dashboard = Object.assign(window.dashboard || {}, {
    setDashboardData
  });

  setDashboardData(FIXED_DEMO);
})();
