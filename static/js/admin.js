(function(){
  const { api, showToast } = window.__portal;

  function detectPage() {
    if (document.getElementById('admin-overview-cards')) {
      loadOverview();
    }
    if (document.getElementById('services-table')) {
      initServices();
    }
    if (document.getElementById('orders-table')) {
      initOrders();
    }
    if (document.getElementById('ticket-list')) {
      initTickets();
    }
    if (document.getElementById('clients-table')) {
      initClients();
    }
    if (document.getElementById('form-templates')) {
      initFormTemplates();
    }
    if (document.getElementById('settings-content')) {
      initSettings();
    }
  }

  async function loadOverview() {
    try {
      const data = await api('/api/admin/overview');
      const cards = document.getElementById('admin-overview-cards');
      cards.innerHTML = '';
      const items = [
        { label: 'Active clients', value: data.active_clients },
        { label: 'Active services', value: data.active_services },
        { label: 'Open tickets', value: data.open_tickets },
        { label: 'Monthly recurring revenue', value: `${data.currency}${data.mrr.toFixed(2)}` }
      ];
      for (const item of items) {
        const div = document.createElement('div');
        div.className = 'card';
        div.innerHTML = `<div class="badge">${item.label}</div><h2 style="margin:0.75rem 0 0;">${item.value}</h2>`;
        cards.appendChild(div);
      }
      renderTable('admin-overview-orders', ['Client', 'Service', 'Total', 'Payment', 'Created'], data.recent_orders.map(order => [
        order.client_name,
        order.service_name,
        `${data.currency}${order.total_amount.toFixed(2)}`,
        `<span class="status-pill" data-status="${order.payment_status}">${order.payment_status}</span>`,
        new Date(order.created_at).toLocaleString()
      ]), 'No recent orders.');
      renderTable('admin-overview-tickets', ['Subject', 'Client', 'Status', 'Updated'], data.open_ticket_threads.map(ticket => [
        ticket.subject,
        ticket.client_name,
        `<span class="status-pill" data-status="${ticket.status}">${ticket.status}</span>`,
        new Date(ticket.updated_at).toLocaleString()
      ]), 'All caught up!');
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

  function initServices() {
    const modal = document.getElementById('service-modal');
    const form = document.getElementById('service-form');
    const addBtn = document.getElementById('add-service-btn');
    const table = document.getElementById('services-table');
    const fieldContainer = document.getElementById('form-fields');

    const closeButtons = modal ? modal.querySelectorAll('[data-close]') : [];
    closeButtons.forEach(btn => btn.addEventListener('click', () => modal.close()));

    async function loadServices() {
      const services = await api('/api/admin/services');
      if (!services.length) {
        table.innerHTML = '<p style="color:var(--muted-text);">No services yet.</p>';
        return;
      }
      table.innerHTML = renderServiceTable(services);
      table.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => openModal(services.find(s => s.id === Number(btn.dataset.edit))));
      });
      table.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
          if (!confirm('Delete this service?')) return;
          await api(`/api/admin/services/${btn.dataset.delete}`, { method: 'DELETE' });
          showToast('Service removed');
          loadServices();
        });
      });
    }

    function renderServiceTable(services) {
      let html = '<table><thead><tr><th>Name</th><th>Price</th><th>Billing</th><th>Updated</th><th></th></tr></thead><tbody>';
      html += services.map(service => `
        <tr>
          <td>
            <strong>${service.name}</strong>
            <div style="color:var(--muted-text);font-size:0.85rem;">${service.description}</div>
          </td>
          <td>${service.currency}${service.price.toFixed(2)}</td>
          <td>${service.billing_cycle}</td>
          <td>${new Date(service.updated_at).toLocaleString()}</td>
          <td style="text-align:right;display:flex;gap:0.5rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-edit="${service.id}" type="button">Edit</button>
            <button class="btn btn-secondary" data-delete="${service.id}" type="button">Delete</button>
          </td>
        </tr>`).join('');
      html += '</tbody></table>';
      return html;
    }

    function serializeFormFields() {
      const fields = Array.from(fieldContainer.querySelectorAll('.form-field')).map(el => {
        return {
          id: el.dataset.id,
          label: el.querySelector('input[name="label"]').value,
          type: el.dataset.type,
          required: el.querySelector('input[name="required"]').checked,
          options: el.dataset.type === 'select' ? el.querySelector('textarea[name="options"]').value.split('\n').filter(Boolean) : [],
        };
      });
      return fields;
    }

    function renderFormFields(schema) {
      fieldContainer.innerHTML = '';
      schema.forEach(field => addField(field.type, field));
    }

    function addField(type, data = {}) {
      const id = data.id || `${type}-${Date.now()}`;
      const wrapper = document.createElement('div');
      wrapper.className = 'form-field';
      wrapper.dataset.id = id;
      wrapper.dataset.type = type;
      wrapper.innerHTML = `
        <div class="card" style="padding:1rem;box-shadow:none;border:1px solid #e5e7eb;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
            <div class="badge">${type.toUpperCase()}</div>
            <button type="button" class="btn btn-secondary" data-remove>Remove</button>
          </div>
          <div style="margin-top:1rem;">
            <label>Label</label>
            <input name="label" value="${data.label || ''}" />
          </div>
          <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.75rem;">
            <input type="checkbox" name="required" ${data.required ? 'checked' : ''} /> Required
          </label>
          ${type === 'select' ? `<div style="margin-top:0.75rem;"><label>Options (one per line)</label><textarea name="options">${(data.options || []).join('\n')}</textarea></div>` : ''}
        </div>`;
      wrapper.querySelector('[data-remove]').addEventListener('click', () => wrapper.remove());
      fieldContainer.appendChild(wrapper);
    }

    document.querySelectorAll('.form-field-add').forEach(btn => {
      btn.addEventListener('click', () => addField(btn.dataset.type));
    });

    function openModal(service) {
      form.reset();
      fieldContainer.innerHTML = '';
      form.querySelector('input[name="id"]').value = service ? service.id : '';
      form.querySelector('input[name="name"]').value = service ? service.name : '';
      form.querySelector('textarea[name="description"]').value = service ? service.description : '';
      form.querySelector('input[name="price"]').value = service ? service.price : '';
      form.querySelector('select[name="billing_cycle"]').value = service ? service.billing_cycle : 'one-off';
      document.getElementById('service-modal-title').textContent = service ? 'Edit service' : 'New service';
      if (service && service.form_schema && service.form_schema.length) {
        renderFormFields(service.form_schema);
      }
      modal.showModal();
    }

    addBtn && addBtn.addEventListener('click', () => openModal());

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.price = Number(payload.price);
      payload.form_schema = serializeFormFields();
      const id = payload.id;
      delete payload.id;
      await api(id ? `/api/admin/services/${id}` : '/api/admin/services', {
        method: id ? 'PUT' : 'POST',
        body: payload
      });
      modal.close();
      showToast('Service saved');
      loadServices();
    });

    loadServices();
  }

  function initOrders() {
    const table = document.getElementById('orders-table');
    const tabs = document.querySelectorAll('.tab-bar button');

    tabs.forEach(tab => tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      loadOrders(tab.dataset.filter);
    }));

    async function loadOrders(filter = 'all') {
      const params = new URLSearchParams();
      if (filter && filter !== 'all') params.append('status', filter);
      const orders = await api(`/api/admin/orders?${params.toString()}`);
      if (!orders.length) {
        table.innerHTML = '<p style="color:var(--muted-text);">No orders yet.</p>';
        return;
      }
      let html = '<table><thead><tr><th>Client</th><th>Service</th><th>Total</th><th>Status</th><th>Payment</th><th>Created</th><th></th></tr></thead><tbody>';
      html += orders.map(order => `
        <tr>
          <td>${order.client_name}</td>
          <td>${order.service_name}</td>
          <td>${order.currency}${order.total_amount.toFixed(2)}</td>
          <td><span class="status-pill" data-status="${order.status}">${order.status}</span></td>
          <td><span class="status-pill" data-status="${order.payment_status}">${order.payment_status}</span></td>
          <td>${new Date(order.created_at).toLocaleString()}</td>
          <td style="text-align:right;">
            <button class="btn btn-secondary" data-action="mark-paid" data-id="${order.id}" type="button">Mark paid</button>
          </td>
        </tr>`).join('');
      html += '</tbody></table>';
      table.innerHTML = html;
      table.querySelectorAll('[data-action="mark-paid"]').forEach(btn => {
        btn.addEventListener('click', async () => {
          await api(`/api/admin/orders/${btn.dataset.id}/payment`, { method: 'PUT', body: { status: 'paid' } });
          showToast('Payment updated');
          loadOrders(filter);
        });
      });
    }

    loadOrders();
  }

  function initTickets() {
    const list = document.getElementById('ticket-list');
    const thread = document.getElementById('ticket-thread');
    const search = document.getElementById('ticket-search');

    async function loadTickets(query = '') {
      const params = new URLSearchParams();
      if (query) params.append('q', query);
      const tickets = await api(`/api/admin/tickets?${params.toString()}`);
      renderTicketList(tickets);
      if (tickets.length) {
        loadThread(tickets[0].id);
      } else {
        thread.innerHTML = '<p style="color:var(--muted-text);">No tickets match your filters.</p>';
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
          <div style="color:var(--muted-text);font-size:0.85rem;">${ticket.client_name}</div>
          <div style="color:var(--muted-text);font-size:0.8rem;">Updated ${new Date(ticket.updated_at).toLocaleString()}</div>`;
        item.addEventListener('click', () => loadThread(ticket.id));
        list.appendChild(item);
      });
    }

    async function loadThread(ticketId) {
      const ticket = await api(`/api/admin/tickets/${ticketId}`);
      thread.innerHTML = '';
      const header = document.createElement('div');
      header.innerHTML = `<h2 style="margin:0;">${ticket.subject}</h2><p style="color:var(--muted-text);">${ticket.client_name}</p>`;
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
        await api(`/api/admin/tickets/${ticketId}/reply`, { method: 'POST', body });
        form.reset();
        loadThread(ticketId);
      });
      thread.appendChild(header);
      thread.appendChild(messages);
      thread.appendChild(form);
    }

    search.addEventListener('input', () => loadTickets(search.value));

    loadTickets();
  }

  function initClients() {
    const table = document.getElementById('clients-table');
    const inviteBtn = document.getElementById('invite-client-btn');
    const modal = document.getElementById('invite-modal');
    const form = document.getElementById('invite-form');
    modal?.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => modal.close()));

    async function loadClients() {
      const clients = await api('/api/admin/clients');
      if (!clients.length) {
        table.innerHTML = '<p style="color:var(--muted-text);">No clients yet.</p>';
        return;
      }
      let html = '<table><thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Joined</th></tr></thead><tbody>';
      html += clients.map(client => `
        <tr>
          <td>${client.name}</td>
          <td>${client.email}</td>
          <td>${client.company || ''}</td>
          <td>${new Date(client.created_at).toLocaleDateString()}</td>
        </tr>`).join('');
      html += '</tbody></table>';
      table.innerHTML = html;
    }

    inviteBtn?.addEventListener('click', () => {
      form.reset();
      modal.showModal();
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      await api('/api/admin/clients/invite', { method: 'POST', body: payload });
      modal.close();
      showToast('Invite sent');
      loadClients();
    });

    loadClients();
  }

  function initFormTemplates() {
    const list = document.getElementById('form-templates');
    const modal = document.getElementById('form-template-modal');
    const form = document.getElementById('form-template-form');
    const createBtn = document.getElementById('create-form-template');
    modal?.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => modal.close()));

    async function loadTemplates() {
      const templates = await api('/api/admin/forms');
      if (!templates.length) {
        list.innerHTML = '<p style="color:var(--muted-text);">No templates yet.</p>';
        return;
      }
      list.innerHTML = templates.map(template => `
        <div class="card" style="box-shadow:none;border:1px solid #e5e7eb;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <strong>${template.name}</strong>
              <div style="color:var(--muted-text);font-size:0.85rem;">${template.description || ''}</div>
            </div>
            <div style="display:flex;gap:0.5rem;">
              <button class="btn btn-secondary" data-edit="${template.id}" type="button">Edit</button>
              <button class="btn btn-secondary" data-delete="${template.id}" type="button">Delete</button>
            </div>
          </div>
        </div>`).join('');
      list.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => openModal(templates.find(t => t.id === Number(btn.dataset.edit))));
      });
      list.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
          if (!confirm('Delete this template?')) return;
          await api(`/api/admin/forms/${btn.dataset.delete}`, { method: 'DELETE' });
          showToast('Template removed');
          loadTemplates();
        });
      });
    }

    function openModal(template) {
      form.reset();
      form.querySelector('input[name="id"]').value = template ? template.id : '';
      form.querySelector('input[name="name"]').value = template ? template.name : '';
      form.querySelector('textarea[name="description"]').value = template ? (template.description || '') : '';
      form.querySelector('textarea[name="schema"]').value = template ? JSON.stringify(template.schema, null, 2) : '';
      modal.showModal();
    }

    createBtn?.addEventListener('click', () => openModal());

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.schema = payload.schema ? JSON.parse(payload.schema) : [];
      const id = payload.id;
      delete payload.id;
      await api(id ? `/api/admin/forms/${id}` : '/api/admin/forms', { method: id ? 'PUT' : 'POST', body: payload });
      modal.close();
      showToast('Template saved');
      loadTemplates();
    });

    loadTemplates();
  }

  function initSettings() {
    const container = document.getElementById('settings-content');
    const tabs = document.querySelectorAll('[data-target="settings"] button');
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        renderTab(tab.dataset.tab);
      });
    });

    async function renderTab(tab) {
      const data = await api('/api/admin/settings');
      const templates = await api('/api/admin/email-templates');
      const branding = data.branding || {};
      const billing = data.billing || {};
      const email = data.email || {};
      const integrations = data.integrations || {};

      if (tab === 'branding') {
        container.innerHTML = `
          <form id="branding-form" class="grid two">
            <div>
              <label>Brand name</label>
              <input name="brand_name" value="${branding.brand_name || ''}" />
            </div>
            <div>
              <label>Logo URL</label>
              <input name="logo_url" value="${branding.logo_url || ''}" />
            </div>
            <div>
              <label>Primary colour</label>
              <input name="primary_color" value="${branding.primary_color || '#4f46e5'}" />
            </div>
            <div>
              <label>Accent colour</label>
              <input name="accent_color" value="${branding.accent_color || '#ec4899'}" />
            </div>
            <div>
              <label>Background colour</label>
              <input name="background_color" value="${branding.background_color || '#f9fafb'}" />
            </div>
            <div>
              <label>Font family</label>
              <input name="font_family" value="${branding.font_family || 'Inter, sans-serif'}" />
            </div>
            <div style="grid-column:1/-1;text-align:right;">
              <button class="btn btn-primary" type="submit">Save branding</button>
            </div>
          </form>`;
        container.querySelector('#branding-form').addEventListener('submit', handleSave('branding'));
      }

      if (tab === 'billing') {
        container.innerHTML = `
          <form id="billing-form" class="grid two">
            <div>
              <label>Default currency</label>
              <input name="currency" value="${billing.currency || '$'}" />
            </div>
            <div>
              <label>Stripe secret key</label>
              <input name="stripe_secret_key" value="${billing.stripe_secret_key || ''}" />
            </div>
            <div>
              <label>Stripe publishable key</label>
              <input name="stripe_publishable_key" value="${billing.stripe_publishable_key || ''}" />
            </div>
            <div>
              <label>PayPal client id</label>
              <input name="paypal_client_id" value="${billing.paypal_client_id || ''}" />
            </div>
            <div>
              <label>PayPal client secret</label>
              <input name="paypal_client_secret" value="${billing.paypal_client_secret || ''}" />
            </div>
            <div>
              <label>Payment reminder days</label>
              <input type="number" name="reminder_days" min="1" value="${billing.reminder_days || 3}" />
            </div>
            <div style="grid-column:1/-1;text-align:right;">
              <button class="btn btn-primary" type="submit">Save payment settings</button>
            </div>
          </form>`;
        container.querySelector('#billing-form').addEventListener('submit', handleSave('billing'));
      }

      if (tab === 'email') {
        container.innerHTML = `
          <form id="email-form" class="grid two">
            <div>
              <label>From name</label>
              <input name="from_name" value="${email.from_name || ''}" />
            </div>
            <div>
              <label>From email</label>
              <input name="from_email" value="${email.from_email || ''}" />
            </div>
            <div>
              <label>SMTP host</label>
              <input name="smtp_host" value="${email.smtp_host || ''}" />
            </div>
            <div>
              <label>SMTP port</label>
              <input name="smtp_port" value="${email.smtp_port || ''}" />
            </div>
            <div>
              <label>SMTP username</label>
              <input name="smtp_username" value="${email.smtp_username || ''}" />
            </div>
            <div>
              <label>SMTP password</label>
              <input name="smtp_password" type="password" value="${email.smtp_password || ''}" />
            </div>
            <div>
              <label>Use TLS</label>
              <select name="smtp_tls">
                <option value="true" ${email.smtp_tls !== 'false' ? 'selected' : ''}>Yes</option>
                <option value="false" ${email.smtp_tls === 'false' ? 'selected' : ''}>No</option>
              </select>
            </div>
            <div style="grid-column:1/-1;text-align:right;">
              <button class="btn btn-primary" type="submit">Save email settings</button>
            </div>
          </form>`;
        container.querySelector('#email-form').addEventListener('submit', handleSave('email'));
      }

      if (tab === 'templates') {
        container.innerHTML = templates.map(template => `
          <details class="card" style="box-shadow:none;border:1px solid #e5e7eb;">
            <summary style="cursor:pointer;display:flex;justify-content:space-between;align-items:center;">
              <div>
                <strong>${template.name}</strong>
                <div style="color:var(--muted-text);font-size:0.85rem;">${template.slug}</div>
              </div>
              <span class="badge">${template.subject}</span>
            </summary>
            <form data-slug="${template.slug}" style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1rem;">
              <div>
                <label>Subject</label>
                <input name="subject" value="${template.subject}" />
              </div>
              <div>
                <label>Body</label>
                <textarea name="body" rows="6">${template.body}</textarea>
              </div>
              <div style="text-align:right;">
                <button class="btn btn-primary" type="submit">Save template</button>
              </div>
            </form>
          </details>`).join('');
        container.querySelectorAll('form[data-slug]').forEach(form => {
          form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const slug = form.dataset.slug;
            const payload = Object.fromEntries(new FormData(form).entries());
            await api(`/api/admin/email-templates/${slug}`, { method: 'PUT', body: payload });
            showToast('Template updated');
          });
        });
      }

      if (tab === 'integrations') {
        container.innerHTML = `
          <form id="integrations-form" class="grid two">
            <div>
              <label>Microsoft 365 tenant ID</label>
              <input name="m365_tenant_id" value="${integrations.m365_tenant_id || ''}" />
            </div>
            <div>
              <label>Microsoft 365 client ID</label>
              <input name="m365_client_id" value="${integrations.m365_client_id || ''}" />
            </div>
            <div>
              <label>Microsoft 365 client secret</label>
              <input name="m365_client_secret" value="${integrations.m365_client_secret || ''}" />
            </div>
            <div>
              <label>SharePoint site</label>
              <input name="sharepoint_site" value="${integrations.sharepoint_site || ''}" />
            </div>
            <div style="grid-column:1/-1;text-align:right;">
              <button class="btn btn-primary" type="submit">Save integrations</button>
            </div>
          </form>`;
        container.querySelector('#integrations-form').addEventListener('submit', handleSave('integrations'));
      }
    }

    function handleSave(section) {
      return async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(event.target).entries());
        await api(`/api/admin/settings/${section}`, { method: 'PUT', body: payload });
        showToast('Settings saved');
      };
    }

    renderTab('branding');
  }

  document.addEventListener('DOMContentLoaded', detectPage);
})();
