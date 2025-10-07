(function(){
  const { api, showToast, ctx } = window.__portal;

  function detectPage() {
    if (document.getElementById('client-summary')) {
      loadDashboard();
    }
    if (document.getElementById('client-services')) {
      initServices();
    }
    if (document.getElementById('client-orders')) {
      loadOrders();
    }
    if (document.getElementById('client-ticket-list')) {
      initTickets();
    }
    if (document.getElementById('client-files')) {
      loadFiles();
    }
  }

  async function loadDashboard() {
    try {
      const data = await api('/api/client/overview');
      const summary = document.getElementById('client-summary');
      summary.innerHTML = '';
      const items = [
        { label: 'Active services', value: data.active_services },
        { label: 'Open orders', value: data.open_orders },
        { label: 'Outstanding balance', value: `${data.currency}${data.outstanding_balance.toFixed(2)}` },
        { label: 'Open tickets', value: data.open_tickets }
      ];
      for (const item of items) {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `<div class="badge">${item.label}</div><h2 style="margin:0.75rem 0 0;">${item.value}</h2>`;
        summary.appendChild(card);
      }
      renderTable('client-upcoming', ['Service', 'Due', 'Amount', 'Status'], data.upcoming_payments.map(payment => [
        payment.service_name,
        new Date(payment.due_date).toLocaleDateString(),
        `${data.currency}${payment.total_amount.toFixed(2)}`,
        `<span class="status-pill" data-status="${payment.payment_status}">${payment.payment_status}</span>`
      ]), 'No upcoming invoices.');
      const activity = document.getElementById('client-activity');
      if (!data.activity.length) {
        activity.innerHTML = '<p style="color:var(--muted-text);">No activity yet.</p>';
      } else {
        activity.innerHTML = data.activity.map(item => `
          <div class="list-item">
            <div>
              <strong>${item.title}</strong>
              <div style="color:var(--muted-text);font-size:0.85rem;">${item.description}</div>
            </div>
            <div style="color:var(--muted-text);font-size:0.8rem;">${new Date(item.timestamp).toLocaleString()}</div>
          </div>`).join('');
      }
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  function renderTable(targetId, headers, rows, emptyText) {
    const container = document.getElementById(targetId);
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = `<p style="color:var(--muted-text);">${emptyText}</p>`;
      return;
    }
    let html = '<table><thead><tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr></thead><tbody>';
    html += rows.map(row => '<tr>' + row.map(cell => `<td>${cell}</td>`).join('') + '</tr>').join('');
    html += '</tbody></table>';
    container.innerHTML = html;
  }

  async function initServices() {
    const grid = document.getElementById('client-services');
    const modal = document.getElementById('order-modal');
    const form = document.getElementById('order-form');
    const fields = document.getElementById('order-form-fields');
    modal?.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => modal.close()));

    const services = await api('/api/client/services');
    if (!services.length) {
      grid.innerHTML = '<p style="color:var(--muted-text);">No services shared with you yet.</p>';
      return;
    }
    grid.innerHTML = services.map(service => `
      <div class="card">
        <div class="badge">${service.billing_cycle}</div>
        <h2 style="margin:0.75rem 0 0;">${service.name}</h2>
        <p style="color:var(--muted-text);">${service.description}</p>
        <div style="font-size:1.25rem;font-weight:600;margin:1rem 0;">${service.currency}${service.price.toFixed(2)}</div>
        <button class="btn btn-primary" data-order="${service.id}" type="button">Order service</button>
      </div>`).join('');
    grid.querySelectorAll('[data-order]').forEach(btn => {
      btn.addEventListener('click', () => openModal(services.find(s => s.id === Number(btn.dataset.order))));
    });

    function openModal(service) {
      form.reset();
      fields.innerHTML = '';
      document.getElementById('order-modal-title').textContent = `Order ${service.name}`;
      form.querySelector('input[name="service_id"]').value = service.id;
      service.form_schema.forEach(field => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = renderFormField(field);
        fields.appendChild(wrapper);
      });
      modal.showModal();
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.responses = {};
      Array.from(form.elements).forEach(el => {
        if (!el.name || ['service_id'].includes(el.name)) return;
        payload.responses[el.name] = el.type === 'checkbox' ? el.checked : el.value;
      });
      await api('/api/client/orders', { method: 'POST', body: payload });
      modal.close();
      showToast('Order submitted');
      loadOrders();
    });
  }

  function renderFormField(field) {
    const name = `field_${field.id}`;
    if (field.type === 'textarea') {
      return `<div><label>${field.label}</label><textarea name="${name}" rows="4" ${field.required ? 'required' : ''}></textarea></div>`;
    }
    if (field.type === 'select') {
      return `<div><label>${field.label}</label><select name="${name}" ${field.required ? 'required' : ''}>${field.options.map(opt => `<option value="${opt}">${opt}</option>`).join('')}</select></div>`;
    }
    if (field.type === 'checkbox') {
      return `<label style="display:flex;align-items:center;gap:0.75rem;"><input type="checkbox" name="${name}" ${field.required ? 'required' : ''}/> ${field.label}</label>`;
    }
    if (field.type === 'file') {
      return `<div><label>${field.label}</label><input type="file" name="${name}" ${field.required ? 'required' : ''} disabled title="File uploads will be enabled once storage integration is configured."/></div>`;
    }
    return `<div><label>${field.label}</label><input name="${name}" ${field.required ? 'required' : ''}/></div>`;
  }

  async function loadOrders() {
    const container = document.getElementById('client-orders');
    const orders = await api('/api/client/orders');
    if (!orders.length) {
      container.innerHTML = '<p style="color:var(--muted-text);">No orders yet.</p>';
      return;
    }
    container.innerHTML = '<div class="list">' + orders.map(order => `
      <div class="card" style="box-shadow:none;border:1px solid #e5e7eb;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
          <div>
            <strong>${order.service_name}</strong>
            <div style="color:var(--muted-text);font-size:0.85rem;">${new Date(order.created_at).toLocaleString()}</div>
          </div>
          <div style="text-align:right;">
            <div>${order.currency}${order.total_amount.toFixed(2)}</div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
              <span class="status-pill" data-status="${order.status}">${order.status}</span>
              <span class="status-pill" data-status="${order.payment_status}">${order.payment_status}</span>
            </div>
          </div>
        </div>
        <div style="margin-top:1rem;display:flex;gap:0.75rem;">
          <button class="btn btn-secondary" data-pay="${order.id}" type="button">Pay now</button>
          <button class="btn btn-secondary" data-detail="${order.id}" type="button">View details</button>
        </div>
      </div>`).join('') + '</div>';
    container.querySelectorAll('[data-pay]').forEach(btn => {
      btn.addEventListener('click', () => initiatePayment(btn.dataset.pay));
    });
    container.querySelectorAll('[data-detail]').forEach(btn => {
      btn.addEventListener('click', () => viewOrder(btn.dataset.detail));
    });
  }

  async function initiatePayment(orderId) {
    try {
      const session = await api(`/api/client/orders/${orderId}/checkout`, { method: 'POST' });
      if (session.checkout_url) {
        window.location.href = session.checkout_url;
      } else {
        showToast('Payment session created. Please follow the instructions sent via email.');
      }
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  async function viewOrder(orderId) {
    const order = await api(`/api/client/orders/${orderId}`);
    const detail = document.createElement('dialog');
    detail.style.border = 'none';
    detail.style.borderRadius = '18px';
    detail.style.padding = '0';
    detail.style.maxWidth = '640px';
    detail.style.width = '90vw';
    detail.innerHTML = `
      <article style="padding:2rem;display:flex;flex-direction:column;gap:1rem;">
        <h2 style="margin:0;">${order.service_name}</h2>
        <div style="display:flex;gap:0.75rem;">
          <span class="status-pill" data-status="${order.status}">${order.status}</span>
          <span class="status-pill" data-status="${order.payment_status}">${order.payment_status}</span>
        </div>
        <div>
          <strong>Responses</strong>
          <div style="margin-top:0.5rem;display:flex;flex-direction:column;gap:0.5rem;">
            ${Object.entries(order.form_data || {}).map(([key, value]) => `<div><div style="color:var(--muted-text);font-size:0.8rem;">${key}</div><div>${value}</div></div>`).join('')}
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;">
          <button class="btn btn-secondary" type="button">Close</button>
        </div>
      </article>`;
    detail.querySelector('button').addEventListener('click', () => detail.close());
    document.body.appendChild(detail);
    detail.addEventListener('close', () => detail.remove());
    detail.showModal();
  }

  function initTickets() {
    const list = document.getElementById('client-ticket-list');
    const thread = document.getElementById('client-ticket-thread');
    const search = document.getElementById('client-ticket-search');
    const newTicketBtn = document.getElementById('new-ticket-btn');
    const modal = document.getElementById('ticket-modal');
    const form = document.getElementById('ticket-form');
    modal?.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => modal.close()));

    async function loadTickets(query = '') {
      const params = new URLSearchParams();
      if (query) params.append('q', query);
      const tickets = await api(`/api/client/tickets?${params.toString()}`);
      renderTicketList(tickets);
      if (tickets.length) {
        loadThread(tickets[0].id);
      } else {
        thread.innerHTML = '<p style="color:var(--muted-text);">No tickets yet.</p>';
      }
    }

    function renderTicketList(tickets) {
      list.innerHTML = '';
      tickets.forEach(ticket => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'card';
        item.style.textAlign = 'left';
        item.style.boxShadow = 'none';
        item.dataset.id = ticket.id;
        item.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong>${ticket.subject}</strong>
            <span class="status-pill" data-status="${ticket.status}">${ticket.status}</span>
          </div>
          <div style="color:var(--muted-text);font-size:0.85rem;">Updated ${new Date(ticket.updated_at).toLocaleString()}</div>`;
        item.addEventListener('click', () => loadThread(ticket.id));
        list.appendChild(item);
      });
    }

    async function loadThread(ticketId) {
      const ticket = await api(`/api/client/tickets/${ticketId}`);
      thread.innerHTML = '';
      const header = document.createElement('div');
      header.innerHTML = `<h2 style="margin:0;">${ticket.subject}</h2>`;
      const messages = document.createElement('div');
      messages.style.flex = '1';
      messages.style.display = 'flex';
      messages.style.flexDirection = 'column';
      messages.style.gap = '1rem';
      messages.style.margin = '1rem 0';
      ticket.messages.forEach(msg => {
        const bubble = document.createElement('div');
        bubble.className = 'card';
        bubble.style.background = msg.is_staff ? 'rgba(79, 70, 229, 0.08)' : 'rgba(15, 23, 42, 0.04)';
        bubble.style.boxShadow = 'none';
        bubble.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;">
          <strong>${msg.author}</strong>
          <span style="color:var(--muted-text);font-size:0.85rem;">${new Date(msg.created_at).toLocaleString()}</span>
        </div><div style="margin-top:0.75rem;white-space:pre-wrap;">${msg.message}</div>`;
        messages.appendChild(bubble);
      });
      const form = document.createElement('form');
      form.innerHTML = `
        <textarea name="message" rows="4" placeholder="Type your reply" required></textarea>
        <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:0.75rem;">
          <button type="submit" class="btn btn-primary">Send reply</button>
        </div>`;
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = Object.fromEntries(new FormData(form).entries());
        await api(`/api/client/tickets/${ticketId}/reply`, { method: 'POST', body });
        form.reset();
        loadThread(ticketId);
      });
      thread.appendChild(header);
      thread.appendChild(messages);
      thread.appendChild(form);
    }

    newTicketBtn?.addEventListener('click', () => {
      form.reset();
      modal.showModal();
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      await api('/api/client/tickets', { method: 'POST', body: payload });
      modal.close();
      showToast('Ticket created');
      loadTickets();
    });

    search.addEventListener('input', () => loadTickets(search.value));

    loadTickets();
  }

  async function loadFiles() {
    const list = document.getElementById('client-files');
    const files = await api('/api/client/files');
    if (!files.length) {
      list.innerHTML = '<p style="color:var(--muted-text);">No files shared yet.</p>';
      return;
    }
    list.innerHTML = files.map(file => `
      <a class="card" href="${file.url}" download style="display:flex;justify-content:space-between;align-items:center;box-shadow:none;border:1px solid #e5e7eb;">
        <div>
          <strong>${file.name}</strong>
          <div style="color:var(--muted-text);font-size:0.85rem;">${file.description || ''}</div>
        </div>
        <span style="color:var(--muted-text);font-size:0.85rem;">${new Date(file.created_at).toLocaleDateString()}</span>
      </a>`).join('');
  }

  document.addEventListener('DOMContentLoaded', detectPage);
})();
