(function() {
  const ctx = window.__PORTAL_CONTEXT__ || {};
  const toastEl = document.getElementById('toast');

  function showToast(message, variant = 'default') {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.dataset.variant = variant;
    toastEl.classList.add('show');
    setTimeout(() => toastEl.classList.remove('show'), 3000);
  }

  async function api(path, options = {}) {
    const headers = options.headers ? { ...options.headers } : {};
    if (!(options.body instanceof FormData) && options.body && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    const res = await fetch(path, { credentials: 'same-origin', ...options, headers });
    if (!res.ok) {
      const errorText = await res.text();
      throw new Error(errorText || 'Request failed');
    }
    const contentType = res.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      return res.json();
    }
    return res.text();
  }

  function applyBranding() {
    const branding = ctx.branding || {};
    const root = document.documentElement;
    if (branding.primary_color) root.style.setProperty('--primary-color', branding.primary_color);
    if (branding.accent_color) root.style.setProperty('--accent-color', branding.accent_color);
    if (branding.font_family) root.style.setProperty('--font-family', branding.font_family);
    if (branding.background_color) root.style.setProperty('--background-color', branding.background_color);
  }

  applyBranding();

  window.__portal = {
    ctx,
    api,
    showToast
  };
})();
