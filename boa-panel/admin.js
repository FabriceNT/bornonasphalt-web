document.addEventListener('DOMContentLoaded', () => {
  const API_AUTH = '../api/admin-auth.php';
  const API_ORDERS = '../api/admin-orders.php';
  const API_REVIEWS = '../api/admin-reviews.php';
  const API_LOGS = '../api/admin-logs.php';
  const API_NEWSLETTER = '../api/admin-newsletter.php';

  // Global state
  let currentTab = 'orders';
  let ordersPage = 1;
  let ordersStatus = '';
  let newsletterPage = 1;

  // DOM Elements
  const loginScreen = document.getElementById('login-screen');
  const dashboardScreen = document.getElementById('dashboard-screen');
  const loginForm = document.getElementById('login-form');
  const loginError = document.getElementById('login-error');
  const passwordInput = document.getElementById('password');
  const logoutBtn = document.getElementById('logout-btn');
  const alertBanner = document.getElementById('alert-banner');
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabPanels = document.querySelectorAll('.tab-panel');

  // Check auth status on load
  checkAuthStatus();

  // --- AUTHENTICATION ---

  async function checkAuthStatus() {
    try {
      const res = await fetch(`${API_AUTH}?action=status`, { credentials: 'include' });
      const data = await res.json();
      if (data.authenticated) {
        showDashboard();
      } else {
        showLogin();
      }
    } catch (err) {
      console.error('Auth check failed:', err);
      showLogin();
    }
  }

  function showLogin() {
    loginScreen.classList.remove('hidden');
    dashboardScreen.classList.add('hidden');
    passwordInput.value = '';
    loginError.textContent = '';
  }

  function showDashboard() {
    loginScreen.classList.add('hidden');
    dashboardScreen.classList.remove('hidden');
    checkRecentErrors();
    switchTab(currentTab);
  }

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    loginError.textContent = '';
    const password = passwordInput.value;

    try {
      const res = await fetch(API_AUTH, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', password }),
        credentials: 'include'
      });

      const data = await res.json();
      if (res.status === 429) {
        loginError.textContent = data.error || 'Too many attempts. Try again later.';
      } else if (res.ok && data.success) {
        showDashboard();
      } else {
        loginError.textContent = data.error || 'Invalid credentials.';
      }
    } catch (err) {
      loginError.textContent = 'Server connection error.';
    }
  });

  logoutBtn.addEventListener('click', async () => {
    try {
      await fetch(API_AUTH, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' }),
        credentials: 'include'
      });
    } catch (err) {
      console.error('Logout failed:', err);
    }
    showLogin();
  });

  // --- RECENT ERRORS BANNER ---

  async function checkRecentErrors() {
    try {
      const res = await fetch(`${API_LOGS}?action=status`, { credentials: 'include' });
      if (res.ok) {
        const data = await res.json();
        if (data.recent_errors > 0) {
          alertBanner.querySelector('.alert-text').innerHTML = `⚠ ${data.recent_errors} error(s) in order-errors.log in the last 24h — Click to inspect`;
          alertBanner.classList.remove('hidden');
        } else {
          alertBanner.classList.add('hidden');
        }
      }
    } catch (err) {
      console.error('Error status check failed:', err);
    }
  }

  alertBanner.addEventListener('click', () => {
    switchTab('logs');
    // Select order-errors.log file
    document.getElementById('log-file-select').value = 'errors';
    loadLogs();
  });

  // --- TABS SWITCHER ---

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      switchTab(tab);
    });
  });

  function switchTab(tab) {
    currentTab = tab;
    tabButtons.forEach(b => {
      if (b.dataset.tab === tab) b.classList.add('active');
      else b.classList.remove('active');
    });

    tabPanels.forEach(panel => {
      if (panel.id === `tab-${tab}`) panel.classList.add('active');
      else panel.classList.remove('active');
    });

    if (tab === 'orders') {
      loadOrders();
    } else if (tab === 'reviews') {
      loadReviews();
    } else if (tab === 'logs') {
      loadLogs();
    } else if (tab === 'newsletter') {
      loadNewsletter();
    }
  }

  // --- ORDERS MODULE ---

  const orderStatusFilter = document.getElementById('order-status-filter');
  orderStatusFilter.addEventListener('change', () => {
    ordersStatus = orderStatusFilter.value;
    ordersPage = 1;
    loadOrders();
  });

  async function loadOrders() {
    const listContainer = document.getElementById('orders-list');
    listContainer.innerHTML = '<tr><td colspan="7" class="text-center">Loading orders...</td></tr>';
    
    try {
      const res = await fetch(`${API_ORDERS}?action=list&page=${ordersPage}&status=${ordersStatus}`, { credentials: 'include' });
      if (res.status === 401) { showLogin(); return; }
      
      const data = await res.json();
      listContainer.innerHTML = '';
      
      if (!data.orders || data.orders.length === 0) {
        listContainer.innerHTML = '<tr><td colspan="7" class="text-center">No orders found.</td></tr>';
        renderOrdersPagination(0);
        return;
      }

      data.orders.forEach(order => {
        const tr = document.createElement('tr');
        if (order.stale_pending) {
          tr.classList.add('stale-pending-row');
        }

        const totalFormatted = (order.total_cents / 100).toFixed(2);
        
        let statusBadge = `<span class="status-badge ${order.status}">${order.status}</span>`;
        if (order.stale_pending) {
          statusBadge += `<span class="stale-warning">⚠ STALE (>30m)</span>`;
        }

        tr.innerHTML = `
          <td><strong>#${order.id}</strong></td>
          <td>${order.created_at}</td>
          <td>${escapeHtml(order.email)}</td>
          <td>$${totalFormatted}</td>
          <td>${statusBadge}</td>
          <td>${order.provider ? escapeHtml(order.provider.toUpperCase()) : 'N/A'}</td>
          <td>
            <button class="btn btn-secondary btn-sm toggle-details-btn" data-id="${order.id}">DETAILS</button>
          </td>
        `;
        listContainer.appendChild(tr);

        // Details Row (initially hidden)
        const detailsTr = document.createElement('tr');
        detailsTr.id = `details-${order.id}`;
        detailsTr.classList.add('order-detail-row', 'hidden');
        detailsTr.innerHTML = `<td colspan="7" id="details-cell-${order.id}">Loading details...</td>`;
        listContainer.appendChild(detailsTr);
      });

      // Bind Details Buttons
      document.querySelectorAll('.toggle-details-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = btn.dataset.id;
          toggleOrderDetails(id);
        });
      });

      renderOrdersPagination(data.total_count);

    } catch (err) {
      listContainer.innerHTML = '<tr><td colspan="7" class="text-center error-msg">Failed to load orders.</td></tr>';
    }
  }

  async function toggleOrderDetails(orderId) {
    const detailsRow = document.getElementById(`details-${orderId}`);
    const detailsCell = document.getElementById(`details-cell-${orderId}`);
    
    if (!detailsRow.classList.contains('hidden')) {
      detailsRow.classList.add('hidden');
      return;
    }

    detailsRow.classList.remove('hidden');
    detailsCell.innerHTML = '<div class="text-center">Loading order details...</div>';

    try {
      const res = await fetch(`${API_ORDERS}?action=detail&id=${orderId}`, { credentials: 'include' });
      if (res.status === 401) { showLogin(); return; }
      
      const data = await res.json();
      const order = data.order;
      
      const subtotal = (order.subtotal_cents / 100).toFixed(2);
      const shipping = (order.shipping_cents / 100).toFixed(2);
      const total = (order.total_cents / 100).toFixed(2);

      let itemsHtml = '';
      if (Array.isArray(order.cart_json)) {
        order.cart_json.forEach(item => {
          itemsHtml += `
            <li class="cart-item-detail">
              <strong>${escapeHtml(item.title || item.id)}</strong><br>
              Color: ${escapeHtml(item.color || 'N/A')}, Size: ${escapeHtml(item.size || 'N/A')}<br>
              Quantity: ${item.qty}
            </li>
          `;
        });
      } else {
        itemsHtml = '<li>Invalid cart data format.</li>';
      }

      detailsCell.innerHTML = `
        <div class="order-details-box">
          <div class="order-details-grid">
            <div class="detail-section">
              <h4>Delivery Information</h4>
              <p><strong>Email:</strong> ${escapeHtml(order.email)}</p>
              <p><strong>Fulfillment Provider:</strong> ${escapeHtml(order.provider.toUpperCase())}</p>
              <p><strong>Provider Order ID:</strong> ${order.provider_order_id ? escapeHtml(order.provider_order_id) : 'Not Fulfilled yet'}</p>
              <p><strong>Stripe/PayPal Session:</strong> ${escapeHtml(order.stripe_session_id)}</p>
            </div>
            <div class="detail-section">
              <h4>Payment Summary</h4>
              <p><strong>Subtotal:</strong> $${subtotal}</p>
              <p><strong>Shipping:</strong> $${shipping}</p>
              <p><strong>Total Charged:</strong> $${total}</p>
            </div>
            <div class="detail-section">
              <h4>Cart Items</h4>
              <ul class="cart-items-list">
                ${itemsHtml}
              </ul>
            </div>
          </div>
        </div>
      `;
    } catch (err) {
      detailsCell.innerHTML = '<div class="error-msg">Failed to load order details.</div>';
    }
  }

  function renderOrdersPagination(totalCount) {
    const paginContainer = document.getElementById('orders-pagination');
    paginContainer.innerHTML = '';
    
    const totalPages = Math.ceil(totalCount / 25);
    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.className = 'pagination-btn';
    prevBtn.textContent = '◄ PREV';
    prevBtn.disabled = ordersPage === 1;
    prevBtn.addEventListener('click', () => {
      ordersPage--;
      loadOrders();
    });

    const nextBtn = document.createElement('button');
    nextBtn.className = 'pagination-btn';
    nextBtn.textContent = 'NEXT ►';
    nextBtn.disabled = ordersPage === totalPages;
    nextBtn.addEventListener('click', () => {
      ordersPage++;
      loadOrders();
    });

    const info = document.createElement('span');
    info.textContent = `Page ${ordersPage} of ${totalPages} (${totalCount} orders)`;

    paginContainer.appendChild(prevBtn);
    paginContainer.appendChild(info);
    paginContainer.appendChild(nextBtn);
  }

  // --- REVIEWS MODULE ---

  const reviewFilter = document.getElementById('review-filter');
  reviewFilter.addEventListener('change', loadReviews);

  async function loadReviews() {
    const listContainer = document.getElementById('reviews-list');
    listContainer.innerHTML = '<tr><td colspan="7" class="text-center">Loading reviews...</td></tr>';
    const filter = reviewFilter.value;

    try {
      const res = await fetch(`${API_REVIEWS}?action=list&filter=${filter}`, { credentials: 'include' });
      if (res.status === 401) { showLogin(); return; }

      const data = await res.json();
      listContainer.innerHTML = '';

      if (!data.reviews || data.reviews.length === 0) {
        listContainer.innerHTML = '<tr><td colspan="7" class="text-center">No reviews found.</td></tr>';
        return;
      }

      data.reviews.forEach(review => {
        const tr = document.createElement('tr');
        
        let photosHtml = '';
        if (Array.isArray(review.photos) && review.photos.length > 0) {
          photosHtml = '<div class="review-photos">';
          review.photos.forEach(photo => {
            photosHtml += `<img src="${photo}" class="review-photo-thumb" onclick="window.open('${photo}', '_blank')" alt="Review photo">`;
          });
          photosHtml += '</div>';
        }

        const verifiedBadge = review.verified ? '<span class="status-badge created">YES</span>' : '<span class="status-badge unsubscribed">NO</span>';
        const statusBadge = review.approved ? '<span class="status-badge approved">Approved</span>' : '<span class="status-badge pending">Pending</span>';

        let actionButtons = '';
        if (!review.approved) {
          actionButtons += `<button class="btn btn-primary btn-sm approve-review-btn" data-id="${review.id}">Approve</button>`;
        } else {
          actionButtons += `<button class="btn btn-secondary btn-sm reject-review-btn" data-id="${review.id}">Reject</button>`;
        }
        actionButtons += `<button class="btn btn-accent btn-sm delete-review-btn" data-id="${review.id}">Delete</button>`;

        tr.innerHTML = `
          <td><strong>${escapeHtml(review.product_id)}</strong></td>
          <td>
            <strong>${escapeHtml(review.display_name)}</strong><br>
            <span style="font-size:0.8rem; color:#888;">${escapeHtml(review.email)}</span>
          </td>
          <td style="color:#d4af37; font-size:1.2rem;">${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</td>
          <td>
            <strong>${escapeHtml(review.title)}</strong><br>
            <p style="max-width:400px; white-space:pre-wrap;">${escapeHtml(review.body)}</p>
            ${photosHtml}
          </td>
          <td>${verifiedBadge}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="action-buttons">${actionButtons}</div>
          </td>
        `;
        listContainer.appendChild(tr);
      });

      // Bind Review Action Buttons
      document.querySelectorAll('.approve-review-btn').forEach(btn => {
        btn.addEventListener('click', () => updateReviewStatus(btn.dataset.id, 'approve'));
      });
      document.querySelectorAll('.reject-review-btn').forEach(btn => {
        btn.addEventListener('click', () => updateReviewStatus(btn.dataset.id, 'reject'));
      });
      document.querySelectorAll('.delete-review-btn').forEach(btn => {
        btn.addEventListener('click', () => deleteReview(btn.dataset.id));
      });

    } catch (err) {
      listContainer.innerHTML = '<tr><td colspan="7" class="text-center error-msg">Failed to load reviews.</td></tr>';
    }
  }

  async function updateReviewStatus(id, action) {
    try {
      const res = await fetch(API_REVIEWS, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, id }),
        credentials: 'include'
      });
      if (res.ok) {
        loadReviews();
        checkRecentErrors(); // Keep error banner updated
      }
    } catch (err) {
      alert('Failed to update review status.');
    }
  }

  async function deleteReview(id) {
    if (!confirm('Are you sure you want to delete this review permanently?')) return;
    try {
      const res = await fetch(API_REVIEWS, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id }),
        credentials: 'include'
      });
      if (res.ok) {
        loadReviews();
      }
    } catch (err) {
      alert('Failed to delete review.');
    }
  }

  // --- LOGS MODULE ---

  const refreshLogsBtn = document.getElementById('refresh-logs-btn');
  const logFileSelect = document.getElementById('log-file-select');
  const logLinesSelect = document.getElementById('log-lines-select');
  const logsConsole = document.getElementById('logs-console');

  refreshLogsBtn.addEventListener('click', loadLogs);

  async function loadLogs() {
    logsConsole.textContent = 'Loading logs...';
    const file = logFileSelect.value;
    const lines = logLinesSelect.value;

    try {
      const res = await fetch(`${API_LOGS}?action=tail&file=${file}&lines=${lines}`, { credentials: 'include' });
      if (res.status === 401) { showLogin(); return; }

      const data = await res.json();
      if (!data.lines || data.lines.length === 0) {
        logsConsole.textContent = 'Log file is empty or missing.';
        return;
      }

      // Display logs, formatting JSON objects nicely if possible
      logsConsole.innerHTML = data.lines.map(line => {
        try {
          const parsed = JSON.parse(line);
          return `<span style="color:#888;">[${parsed.time}]</span> ` + JSON.stringify(parsed, null, 2);
        } catch (e) {
          return escapeHtml(line);
        }
      }).join('\n');

      // Scroll console to the bottom
      setTimeout(() => {
        logsConsole.scrollTop = logsConsole.scrollHeight;
      }, 50);

    } catch (err) {
      logsConsole.textContent = 'Failed to load logs.';
    }
  }

  // --- NEWSLETTER MODULE ---

  const exportCsvBtn = document.getElementById('export-csv-btn');
  exportCsvBtn.addEventListener('click', () => {
    window.location.href = `${API_NEWSLETTER}?action=export`;
  });

  async function loadNewsletter() {
    loadNewsletterStats();
    loadSubscribers();
  }

  async function loadNewsletterStats() {
    try {
      const res = await fetch(`${API_NEWSLETTER}?action=stats`, { credentials: 'include' });
      if (res.status === 401) { showLogin(); return; }

      const data = await res.json();
      document.getElementById('stats-total-subscribers').textContent = data.total_subscribers;
      document.getElementById('stats-active-subscribers').textContent = data.active_subscribers;
      document.getElementById('stats-promos-generated').textContent = data.total_promos_generated;
      document.getElementById('stats-promos-used').textContent = data.promos_used;
      document.getElementById('stats-promos-expired').textContent = data.promos_expired;
    } catch (err) {
      console.error('Failed to load newsletter stats:', err);
    }
  }

  async function loadSubscribers() {
    const listContainer = document.getElementById('subscribers-list');
    listContainer.innerHTML = '<tr><td colspan="3" class="text-center">Loading subscribers...</td></tr>';

    try {
      const res = await fetch(`${API_NEWSLETTER}?action=list&page=${newsletterPage}`, { credentials: 'include' });
      if (res.status === 401) { showLogin(); return; }

      const data = await res.json();
      listContainer.innerHTML = '';

      if (!data.subscribers || data.subscribers.length === 0) {
        listContainer.innerHTML = '<tr><td colspan="3" class="text-center">No subscribers found.</td></tr>';
        renderSubscribersPagination(0);
        return;
      }

      data.subscribers.forEach(sub => {
        const tr = document.createElement('tr');
        const status = sub.unsubscribed_at ? '<span class="status-badge unsubscribed">Unsubscribed</span>' : '<span class="status-badge subscribed">Subscribed</span>';
        
        tr.innerHTML = `
          <td><strong>${escapeHtml(sub.email)}</strong></td>
          <td>${sub.created_at}</td>
          <td>${status}</td>
        `;
        listContainer.appendChild(tr);
      });

      renderSubscribersPagination(data.total_count);

    } catch (err) {
      listContainer.innerHTML = '<tr><td colspan="3" class="text-center error-msg">Failed to load subscribers.</td></tr>';
    }
  }

  function renderSubscribersPagination(totalCount) {
    const paginContainer = document.getElementById('subscribers-pagination');
    paginContainer.innerHTML = '';
    
    const totalPages = Math.ceil(totalCount / 50);
    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.className = 'pagination-btn';
    prevBtn.textContent = '◄ PREV';
    prevBtn.disabled = newsletterPage === 1;
    prevBtn.addEventListener('click', () => {
      newsletterPage--;
      loadSubscribers();
    });

    const nextBtn = document.createElement('button');
    nextBtn.className = 'pagination-btn';
    nextBtn.textContent = 'NEXT ►';
    nextBtn.disabled = newsletterPage === totalPages;
    nextBtn.addEventListener('click', () => {
      newsletterPage++;
      loadSubscribers();
    });

    const info = document.createElement('span');
    info.textContent = `Page ${newsletterPage} of ${totalPages} (${totalCount} total)`;

    paginContainer.appendChild(prevBtn);
    paginContainer.appendChild(info);
    paginContainer.appendChild(nextBtn);
  }

  // --- HELPERS ---

  function escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
});
