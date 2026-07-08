/* ===================== CONFIG ===================== */
// PHP backend lives at /api/ on the same domain — no separate host, no CORS.
const CHECKOUT_ENDPOINT = '/api/checkout-stripe.php';
const AUTH_SIGNUP_ENDPOINT = '/api/auth-signup.php';
const AUTH_LOGIN_ENDPOINT = '/api/auth-login.php';
const AUTH_LOGOUT_ENDPOINT = '/api/auth-logout.php';
const AUTH_ME_ENDPOINT = '/api/auth-me.php';
const ORDERS_LIST_ENDPOINT = '/api/orders-list.php';
const ADDRESSES_LIST_ENDPOINT = '/api/addresses-list.php';
const ADDRESSES_SAVE_ENDPOINT = '/api/addresses-save.php';
const ADDRESSES_DELETE_ENDPOINT = '/api/addresses-delete.php';
const PAYPAL_CREATE_ORDER_ENDPOINT = '/api/paypal-create-order.php';
const PAYPAL_CAPTURE_ORDER_ENDPOINT = '/api/paypal-capture-order.php';

/* ===================== STATE ===================== */
let cart = JSON.parse(localStorage.getItem('boa_cart') || '[]');

// Migration: cart entries saved before color/size selection existed don't
// have a lineKey, which breaks "Remove". Drop anything that isn't in the
// current shape rather than leaving broken rows a customer can't clear.
cart = cart.filter(c => c && typeof c.lineKey === 'string' && c.lineKey.length > 0);

// Migration: cart entries saved before per-color images existed are
// missing `image` and fall back to the text swatch — backfill it now that
// the catalog has real photos, using the product/color it was added with.
cart = cart.map(c => {
  if(!c.image){
    const p = PRODUCTS.find(x => x.id === c.id);
    if(p) c.image = p.images?.[c.color] || p.image || null;
  }
  return c;
});

localStorage.setItem('boa_cart', JSON.stringify(cart));

let activeTribe = 'all';
let currentUser = null;
let currentPage = 1;
const PRODUCTS_PER_PAGE = 20;

/* ===================== INIT ===================== */
document.addEventListener('DOMContentLoaded', () => {
  renderProducts();
  renderCart();
  initFilters();
  initNavToggle();
  initModals();
  initWelcomePopup();
  initCheckoutButtons();
  initProductPage();
  initAuth();
  if (typeof boaLoadFeaturedReviews === 'function') {
    boaLoadFeaturedReviews();
  }
});

/* ===================== PRODUCT GRID ===================== */
function renderProducts(){
  const grid = document.getElementById('productGrid');
  if(!grid) return;
  const items = activeTribe === 'all' ? PRODUCTS : PRODUCTS.filter(p => p.tribe === activeTribe);

  const totalPages = Math.max(1, Math.ceil(items.length / PRODUCTS_PER_PAGE));
  if (currentPage > totalPages) {
    currentPage = totalPages;
  }

  const startIndex = (currentPage - 1) * PRODUCTS_PER_PAGE;
  const paginatedItems = items.slice(startIndex, startIndex + PRODUCTS_PER_PAGE);

  grid.innerHTML = paginatedItems.map(p => {
    const cardImg = p.images?.[p.colors?.[0]] || p.image;
    return `
    <div class="product-card" data-id="${p.id}" onclick="location.href='product.html?id=${p.id}'">
      <div class="product-swatch">${cardImg ? `<img src="${cardImg}" alt="${p.title}" loading="lazy" />` : `<span>${p.swatch}</span>`}</div>
      <div class="product-body">
        <div class="pcode">${p.series}</div>
        <h3>${p.title}</h3>
        <div class="tribe-tag">${p.tribeLabel}</div>
        <div class="price-row">
          <span class="price">From $${BASE_PRICE.toFixed(2)}</span>
          ${p.comingSoon
            ? `<button class="add-btn" disabled>Coming soon</button>`
            : `<button class="add-btn" onclick="event.stopPropagation(); location.href='product.html?id=${p.id}'">Select</button>`}
        </div>
      </div>
    </div>
  `;
  }).join('');

  renderPaginationControls(items.length);
}

function renderPaginationControls(totalItems) {
  const grid = document.getElementById('productGrid');
  if(!grid) return;

  let paginationContainer = document.getElementById('paginationControls');
  const totalPages = Math.ceil(totalItems / PRODUCTS_PER_PAGE);

  if (totalPages <= 1) {
    if (paginationContainer) {
      paginationContainer.remove();
    }
    return;
  }

  if (!paginationContainer) {
    paginationContainer = document.createElement('div');
    paginationContainer.id = 'paginationControls';
    paginationContainer.className = 'pagination-controls';
    grid.after(paginationContainer);
  }

  paginationContainer.innerHTML = `
    <button class="pagination-btn" id="prevPageBtn" ${currentPage === 1 ? 'disabled' : ''}>← Previous</button>
    <span class="pagination-info">Page ${currentPage} / ${totalPages}</span>
    <button class="pagination-btn" id="nextPageBtn" ${currentPage === totalPages ? 'disabled' : ''}>Next →</button>
  `;

  paginationContainer.querySelector('#prevPageBtn').addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      renderProducts();
      scrollToShop();
    }
  });

  paginationContainer.querySelector('#nextPageBtn').addEventListener('click', () => {
    if (currentPage < totalPages) {
      currentPage++;
      renderProducts();
      scrollToShop();
    }
  });
}

function scrollToShop() {
  const shopSection = document.getElementById('shop');
  if (shopSection) {
    shopSection.scrollIntoView({ behavior: 'smooth' });
  }
}

function initFilters(){
  const buttons = document.querySelectorAll('.filter-btn');
  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      buttons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeTribe = btn.dataset.tribe;
      currentPage = 1;
      renderProducts();
    });
  });
}

/* ===================== PRODUCT DETAIL (full page) ===================== */
let pdState = { id: null, color: null, size: null, qty: 1 };

// Called on product.html — reads ?id= from the URL and renders the page.
function initProductPage(){
  const container = document.getElementById('productDetailContainer');
  if(!container) return;
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');
  const p = PRODUCTS.find(x => x.id === id);
  if(!p){
    container.innerHTML = `<div class="legal-block"><h2>Design not found</h2><p>This one might have moved. <a href="index.html#shop">Back to the shop.</a></p></div>`;
    return;
  }
  pdState = { id, color: p.comingSoon ? null : p.colors[0], size: null, qty: 1 };
  document.title = `${p.title} — Born on Asphalt`;
  renderProductDetail(p);
  if (typeof boaLoadProductReviews === 'function') {
    boaLoadProductReviews(id, pdState.color, pdState.size);
  }
}

function renderProductDetail(p){
  const container = document.getElementById('productDetailContainer');
  if(!container) return;

  const comingSoonHtml = p.comingSoon
    ? `<div class="coming-soon-badge">Not available yet</div>`
    : '';

  const colorRowHtml = p.comingSoon ? '' : `
    <div class="variant-block">
      <div class="variant-label">Color: <span class="selected-value">${pdState.color || ''}</span></div>
      <div class="color-row">
        ${p.colors.map(c => `
          <button class="color-swatch ${c === pdState.color ? 'selected' : ''}"
            style="background:${COLOR_HEX[c] || '#333'};"
            title="${c}"
            aria-label="${c}"
            onclick="selectColor('${c.replace(/'/g, "\\'")}')"></button>
        `).join('')}
      </div>
    </div>
  `;

  const sizeRowHtml = p.comingSoon ? '' : `
    <div class="variant-block">
      <div class="variant-label">Size: <span class="selected-value">${pdState.size || 'select a size'}</span></div>
      <div class="size-row">
        ${p.sizes.map(s => `
          <button class="size-btn ${s === pdState.size ? 'selected' : ''}" onclick="selectSize('${s}')">${s}</button>
        `).join('')}
      </div>
    </div>
  `;

  const canAdd = !p.comingSoon && pdState.color && pdState.size;
  const pdImg = p.images?.[pdState.color] || p.image;
  const pdPrice = pdState.size ? SIZE_PRICES[pdState.size] : BASE_PRICE;
  const pdPriceLabel = pdState.size ? `$${pdPrice.toFixed(2)}` : `From $${BASE_PRICE.toFixed(2)}`;

  container.innerHTML = `
    <div class="section-label">// Product detail — ${p.series}</div>
    <div class="product-page-grid">
      <div class="pd-swatch">${pdImg ? `<img src="${pdImg}" alt="${p.title} — ${pdState.color || ''}" />` : `<span>${p.swatch}</span>`}</div>
      <div>
        <div class="pcode">${p.series} · ${p.tribeLabel}</div>
        <h1 class="pd-title">${p.title}</h1>
        <p class="pd-desc"><strong>"${p.slogan}"</strong>${p.sub ? '<br/>' + p.sub : ''}</p>
        ${comingSoonHtml}
        ${colorRowHtml}
        ${sizeRowHtml}
        ${p.comingSoon ? '' : `
        <div class="qty-row">
          <button onclick="changeQty(-1)">-</button>
          <span id="pdQty">${pdState.qty}</span>
          <button onclick="changeQty(1)">+</button>
        </div>`}
        <div class="price-row" style="border-top:none; padding-top:0;">
          <span class="price">${pdPriceLabel}</span>
          <button class="add-btn" ${canAdd ? '' : 'disabled'} onclick="confirmAddToCart()">+ Add to sheet</button>
        </div>
        <p class="modal-note" style="margin-top:6px;">2XL $36.99 · 3XL $39.99 · 4XL $40.99 — sizes S–XL $34.99. Shipping $${SHIPPING_FLAT.toFixed(2)}, free over $${FREE_SHIPPING_THRESHOLD}.</p>

        <div class="pd-accordion-group">
          <details class="pd-accordion-item" open>
            <summary>Description</summary>
            <p>${p.desc}</p>
          </details>
          <details class="pd-accordion-item">
            <summary>Washing instructions</summary>
            <p>Machine wash cold, inside out, with like colors. Do not bleach. Tumble dry low. Do not iron directly on the print. Do not dry clean. Comfort Colors 1717 is garment-dyed — slight color variation between washes is normal and part of the fabric's character.</p>
          </details>
          <details class="pd-accordion-item">
            <summary>Shipping &amp; returns</summary>
            <p>Free shipping on every order, US domestic only. Printed on demand — production takes 2–5 business days, plus 3–5 business days in transit. Damaged or misprinted items are replaced at no cost within 14 days of delivery. Full policy on the <a href="shipping.html">Shipping &amp; Returns</a> page.</p>
          </details>
        </div>

        <p class="modal-note">${p.comingSoon
          ? 'This design is being synced with our print partners — check back soon.'
          : 'Printed on demand via Printful/Printify · Comfort Colors 1717 · Ships from the US'}</p>
      </div>
    </div>
  `;
}

window.selectColor = function(color){
  pdState.color = color;
  renderProductDetail(PRODUCTS.find(x => x.id === pdState.id));
}
window.selectSize = function(size){
  pdState.size = size;
  renderProductDetail(PRODUCTS.find(x => x.id === pdState.id));
}
function changeQty(delta){
  pdState.qty = Math.max(1, pdState.qty + delta);
  renderProductDetail(PRODUCTS.find(x => x.id === pdState.id));
}
window.confirmAddToCart = function(){
  if(!pdState.color || !pdState.size) return;
  addToCart(pdState.id, pdState.color, pdState.size, pdState.qty);
}

/* ===================== CART ===================== */
window.addToCart = function(id, color, size, qty){
  qty = qty || 1;
  const lineKey = `${id}__${color}__${size}`;
  const existing = cart.find(c => c.lineKey === lineKey);
  if(existing){ existing.qty += qty; } else {
    const p = PRODUCTS.find(x => x.id === id);
    const price = SIZE_PRICES[size] ?? BASE_PRICE;
    const image = p.images?.[color] || p.image || null;
    cart.push({ lineKey, id: p.id, title: p.title, price, swatch: p.swatch, image, color, size, qty });
  }
  saveCart();
  renderCart();
  openDrawer();
}
window.removeFromCart = function(lineKey){
  cart = cart.filter(c => c.lineKey !== lineKey);
  saveCart();
  renderCart();
}
function saveCart(){
  localStorage.setItem('boa_cart', JSON.stringify(cart));
}
function cartTotals(){
  const subtotal = cart.reduce((s,c) => s + c.price * c.qty, 0);
  const shipping = subtotal === 0 ? 0 : (subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FLAT);
  return { subtotal, shipping, total: subtotal + shipping };
}

function renderCart(){
  const wrap = document.getElementById('drawerItems');
  const countEl = document.getElementById('cartCount');
  const totalCount = cart.reduce((s,c) => s + c.qty, 0);
  if(countEl) countEl.textContent = totalCount;
  if(!wrap) return;
  if(cart.length === 0){
    wrap.innerHTML = '<div class="empty-cart">Your build sheet is empty.</div>';
  } else {
    wrap.innerHTML = cart.map(c => `
      <div class="cart-line">
        <div class="sw">${c.image ? `<img src="${c.image}" alt="${c.title} — ${c.color}" />` : `<span>${c.swatch}</span>`}</div>
        <div class="cart-line-info">
          <h4>${c.title}</h4>
          <div class="meta">${c.color} · ${c.size} · $${c.price.toFixed(2)} each</div>
          <div class="cart-line-actions">
            <div class="cart-qty-row">
              <button onclick="changeCartQty('${c.lineKey}', -1)">-</button>
              <span>${c.qty}</span>
              <button onclick="changeCartQty('${c.lineKey}', 1)">+</button>
            </div>
            <span>$${(c.price * c.qty).toFixed(2)}</span>
          </div>
          <button class="remove-btn" onclick="removeFromCart('${c.lineKey}')">Remove</button>
        </div>
      </div>
    `).join('');
  }
  const { subtotal, shipping, total } = cartTotals();
  const subtotalEl = document.getElementById('subtotalAmount');
  if(subtotalEl) subtotalEl.textContent = '$' + subtotal.toFixed(2);
  const shippingEl = document.getElementById('shippingAmount');
  if(shippingEl) shippingEl.textContent = shipping === 0 ? 'Free' : '$' + shipping.toFixed(2);
  const totalEl = document.getElementById('cartTotalAmount');
  if(totalEl) totalEl.textContent = '$' + total.toFixed(2);
  const noteEl = document.getElementById('freeShippingNote');
  if(noteEl){
    const remaining = FREE_SHIPPING_THRESHOLD - subtotal;
    noteEl.textContent = (subtotal > 0 && remaining > 0)
      ? `Add $${remaining.toFixed(2)} more for free shipping.`
      : '';
  }
  const checkoutBtn = document.getElementById('checkoutBtn');
  if(checkoutBtn) checkoutBtn.style.display = cart.length === 0 ? 'none' : 'block';
}

window.changeCartQty = function(lineKey, delta){
  const item = cart.find(c => c.lineKey === lineKey);
  if(!item) return;
  item.qty += delta;
  if(item.qty < 1){
    cart = cart.filter(c => c.lineKey !== lineKey);
  }
  saveCart();
  renderCart();
}
function openDrawer(){
  document.getElementById('cartDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}
function closeDrawer(){
  document.getElementById('cartDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}

/* ===================== MODALS (generic) ===================== */
function initModals(){
  // Delegated so this keeps working even when a modal's inner content is
  // rebuilt dynamically later (e.g. the account modal switching between
  // logged-out/logged-in views) — a direct listener on the button would be
  // lost the moment its DOM node gets replaced.
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-close-modal]');
    if(closeBtn) closeModal(closeBtn.dataset.closeModal);
  });
  document.getElementById('cartIconBtn')?.addEventListener('click', openDrawer);
  document.getElementById('drawerCloseBtn')?.addEventListener('click', closeDrawer);
  document.getElementById('drawerOverlay')?.addEventListener('click', closeDrawer);
  document.getElementById('accountIconBtn')?.addEventListener('click', () => {
    location.href = 'account.html';
  });
  document.getElementById('checkoutBtn')?.addEventListener('click', () => {
    closeDrawer();
    openModal('checkoutModal');
    renderCheckoutSummary();
    if(currentUser){
      const emailInput = document.getElementById('checkoutEmail');
      const nameInput = document.getElementById('checkoutFullName');
      if(emailInput && !emailInput.value) emailInput.value = currentUser.email;
      if(nameInput && !nameInput.value) nameInput.value = currentUser.name;
      prefillCheckoutAddress();
    }
    initPaymentElement();
    initPayPalButton();
  });
}

async function prefillCheckoutAddress(){
  try{
    const res = await fetch(ADDRESSES_LIST_ENDPOINT, { credentials: 'include' });
    const data = await res.json();
    const addresses = data.addresses || [];
    if(addresses.length === 0) return;
    const addr = addresses.find(a => a.is_default) || addresses[0];
    const nameInput = document.getElementById('checkoutFullName');
    const addr1 = document.getElementById('checkoutAddress1');
    const city = document.getElementById('checkoutCity');
    const state = document.getElementById('checkoutState');
    const zip = document.getElementById('checkoutZip');
    if(nameInput && !nameInput.value) nameInput.value = addr.full_name;
    if(addr1 && !addr1.value) addr1.value = addr.address1;
    if(city && !city.value) city.value = addr.city;
    if(state && !state.value) state.value = addr.state_code;
    if(zip && !zip.value) zip.value = addr.zip;
  } catch(err){
    console.error('Could not prefill saved address:', err);
  }
}
function openModal(id){ document.getElementById(id).classList.add('open'); }
window.closeModal = function(id){ document.getElementById(id).classList.remove('open'); }

/* ===================== AUTH ===================== */
async function initAuth(){
  try{
    const res = await fetch(AUTH_ME_ENDPOINT, { credentials: 'include' });
    const data = await res.json();
    currentUser = data.user || null;
    window._boaUser = currentUser;
  } catch(err){
    console.error('Session check failed:', err);
    currentUser = null;
  }
  updateAccountNavLabel();
  const pageContainer = document.getElementById('accountPageContainer');
  if(pageContainer) renderAccountPanel(pageContainer);
}

function updateAccountNavLabel(){
  const btn = document.getElementById('accountIconBtn');
  if(!btn) return;
  btn.innerHTML = currentUser ? `☰ ${currentUser.name.split(' ')[0].toUpperCase()}` : '☰ ACCOUNT';
}

function renderAccountPanel(container){
  if(!container) return;

  if(currentUser){
    container.innerHTML = `
    <div class="legal-block" style="max-width:600px;">
      <div class="section-label">// Driver record</div>
      <h1 class="pd-title" style="font-size:28px; color:var(--ink);">Your account</h1>
      <p style="font-size:13px; color:var(--ink-faded); margin-bottom:4px;">Signed in as</p>
      <p style="font-family:'Oswald', sans-serif; font-size:18px; margin:0 0 4px; color:var(--ink);">${currentUser.name}</p>
      <p style="font-size:12.5px; color:var(--ink-faded); margin:0 0 20px;">${currentUser.email}</p>
      <button class="btn ghost" id="logoutBtn" style="width:100%; max-width:320px; margin-bottom:22px;">Sign out</button>

      <div class="pd-accordion-group">
        <details class="pd-accordion-item" open>
          <summary>Order history</summary>
          <div id="orderHistoryBody"><p>Loading...</p></div>
        </details>
        <details class="pd-accordion-item">
          <summary>Saved addresses</summary>
          <div id="addressListBody"><p>Loading...</p></div>
          <form id="addAddressForm" style="margin-top:14px; max-width:400px;">
            <div class="form-field"><label>Label</label><input type="text" name="label" placeholder="Home, Work..." /></div>
            <div class="form-field"><label>Full name</label><input type="text" name="full_name" required /></div>
            <div class="form-field"><label>Address</label><input type="text" name="address1" required /></div>
            <div style="display:flex; gap:10px;">
              <div class="form-field" style="flex:1;"><label>City</label><input type="text" name="city" required /></div>
              <div class="form-field" style="flex:1;"><label>State</label><input type="text" name="state_code" maxlength="2" required /></div>
              <div class="form-field" style="flex:1;"><label>ZIP</label><input type="text" name="zip" required /></div>
            </div>
            <label style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--ink-faded); margin-bottom:12px;">
              <input type="checkbox" name="is_default" style="width:auto;" /> Set as default
            </label>
            <button type="submit" class="btn" style="width:100%;">Save address</button>
          </form>
        </details>
      </div>
    </div>
    `;
    container.querySelector('#logoutBtn').addEventListener('click', handleLogout);
    container.querySelector('#addAddressForm').addEventListener('submit', handleAddAddress);
    loadOrderHistory();
    loadAddresses();
  } else {
    container.innerHTML = `
    <div class="legal-block" style="max-width:440px;">
      <div class="section-label">// Driver record</div>
      <h1 class="pd-title" style="font-size:28px; color:var(--ink);">Your account</h1>
      <div class="tab-row">
        <button class="tab-btn active" data-tab="signInPanel">Sign in</button>
        <button class="tab-btn" data-tab="signUpPanel">Create account</button>
      </div>
      <div class="tab-panel active" id="signInPanel">
        <form id="signInForm">
          <p class="form-error" id="signInError" style="display:none; color:var(--stamp-red); font-size:12px; margin-bottom:10px;"></p>
          <div class="form-field"><label>Email</label><input type="email" name="email" required placeholder="you@email.com" /></div>
          <div class="form-field"><label>Password</label><input type="password" name="password" required placeholder="••••••••" /></div>
          <button type="submit" class="btn" style="width:100%;">Sign in</button>
        </form>
      </div>
      <div class="tab-panel" id="signUpPanel">
        <form id="signUpForm">
          <p class="form-error" id="signUpError" style="display:none; color:var(--stamp-red); font-size:12px; margin-bottom:10px;"></p>
          <div class="form-field"><label>Name</label><input type="text" name="name" required placeholder="Full name" /></div>
          <div class="form-field"><label>Email</label><input type="email" name="email" required placeholder="you@email.com" /></div>
          <div class="form-field"><label>Password</label><input type="password" name="password" required placeholder="Minimum 8 characters" /></div>
          <button type="submit" class="btn" style="width:100%;">Create account</button>
        </form>
      </div>
    </div>
    `;
    container.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        container.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        container.querySelector('#' + btn.dataset.tab).classList.add('active');
      });
    });
    container.querySelector('#signInForm').addEventListener('submit', handleSignIn);
    container.querySelector('#signUpForm').addEventListener('submit', handleSignUp);
  }
}

async function handleSignIn(e){
  e.preventDefault();
  const form = e.target;
  const errorEl = form.querySelector('#signInError');
  const btn = form.querySelector('button[type="submit"]');
  errorEl.style.display = 'none';
  btn.disabled = true;
  try{
    const res = await fetch(AUTH_LOGIN_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: form.email.value, password: form.password.value })
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error || 'Could not sign in.');
    currentUser = data.user;
    updateAccountNavLabel();
    renderAccountPanel(document.getElementById('accountPageContainer'));
  } catch(err){
    errorEl.textContent = err.message;
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

async function handleSignUp(e){
  e.preventDefault();
  const form = e.target;
  const errorEl = form.querySelector('#signUpError');
  const btn = form.querySelector('button[type="submit"]');
  errorEl.style.display = 'none';
  btn.disabled = true;
  try{
    const res = await fetch(AUTH_SIGNUP_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: form.name.value, email: form.email.value, password: form.password.value })
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error || 'Could not create account.');
    currentUser = data.user;
    updateAccountNavLabel();
    renderAccountPanel(document.getElementById('accountPageContainer'));
  } catch(err){
    errorEl.textContent = err.message;
    errorEl.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}

async function handleLogout(){
  try{
    await fetch(AUTH_LOGOUT_ENDPOINT, { method: 'POST', credentials: 'include' });
  } catch(err){
    console.error('Logout request failed:', err);
  }
  currentUser = null;
  updateAccountNavLabel();
  renderAccountPanel(document.getElementById('accountPageContainer'));
}

const ORDER_STATUS_LABELS = {
  created: 'Processing',
  shipped: 'Shipped',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
};

async function loadOrderHistory(){
  const el = document.getElementById('orderHistoryBody');
  if(!el) return;
  try{
    const res = await fetch(ORDERS_LIST_ENDPOINT, { credentials: 'include' });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error || 'Could not load orders.');
    const orders = data.orders || [];
    if(orders.length === 0){
      el.innerHTML = `<p style="font-size:12.5px; color:var(--ink-faded);">No orders yet.</p>`;
      return;
    }
    el.innerHTML = orders.map(o => {
      const date = new Date(o.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
      const itemsSummary = (o.cart || []).map(c => {
        const product = PRODUCTS.find(p => p.id === c.id);
        const name = product ? product.title : c.id;
        return `${name} (${c.color}, ${c.size}) ×${c.qty}`;
      }).join(', ');
      const statusLabel = ORDER_STATUS_LABELS[o.status] || 'Processing';
      return `
        <div class="order-row">
          <div class="order-row-top">
            <span>${date}</span>
            <span>$${(o.total_cents/100).toFixed(2)}</span>
          </div>
          <div class="order-row-items">${itemsSummary}</div>
          <div class="order-row-status">${statusLabel}</div>
        </div>
      `;
    }).join('');
  } catch(err){
    el.innerHTML = `<p style="font-size:12.5px; color:var(--stamp-red);">${err.message}</p>`;
  }
}

let loadedAddresses = [];

async function loadAddresses(){
  const el = document.getElementById('addressListBody');
  if(!el) return;
  try{
    const res = await fetch(ADDRESSES_LIST_ENDPOINT, { credentials: 'include' });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error || 'Could not load addresses.');
    loadedAddresses = data.addresses || [];
    if(loadedAddresses.length === 0){
      el.innerHTML = `<p style="font-size:12.5px; color:var(--ink-faded);">No saved addresses yet.</p>`;
      return;
    }
    el.innerHTML = loadedAddresses.map(a => `
      <div class="address-row">
        <div>
          <strong>${a.label}${a.is_default ? ' · Default' : ''}</strong><br/>
          <span style="font-size:12px; color:var(--ink-faded);">${a.full_name} · ${a.address1}, ${a.city}, ${a.state_code} ${a.zip}</span>
        </div>
        <div style="display:flex; gap:10px; flex-shrink:0;">
          <button class="remove-btn" style="color:var(--pencil-blue);" onclick="editAddress(${a.id})">Edit</button>
          <button class="remove-btn" onclick="deleteAddress(${a.id})">Remove</button>
        </div>
      </div>
    `).join('');
  } catch(err){
    el.innerHTML = `<p style="font-size:12.5px; color:var(--stamp-red);">${err.message}</p>`;
  }
}

let editingAddressId = null;

window.editAddress = function(id){
  const a = loadedAddresses.find(x => x.id === id);
  if(!a) return;
  editingAddressId = id;
  const form = document.getElementById('addAddressForm');
  if(!form) return;
  form.label.value = a.label;
  form.full_name.value = a.full_name;
  form.address1.value = a.address1;
  form.city.value = a.city;
  form.state_code.value = a.state_code;
  form.zip.value = a.zip;
  form.is_default.checked = !!a.is_default;
  form.querySelector('button[type="submit"]').textContent = 'Update address';
  let cancelBtn = form.querySelector('#cancelEditBtn');
  if(!cancelBtn){
    cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.id = 'cancelEditBtn';
    cancelBtn.className = 'btn ghost';
    cancelBtn.style.width = '100%';
    cancelBtn.style.marginTop = '8px';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('click', resetAddressForm);
    form.appendChild(cancelBtn);
  }
  form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function resetAddressForm(){
  editingAddressId = null;
  const form = document.getElementById('addAddressForm');
  if(!form) return;
  form.reset();
  form.querySelector('button[type="submit"]').textContent = 'Save address';
  form.querySelector('#cancelEditBtn')?.remove();
}

async function handleAddAddress(e){
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  try{
    const res = await fetch(ADDRESSES_SAVE_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: editingAddressId || undefined,
        label: form.label.value,
        full_name: form.full_name.value,
        address1: form.address1.value,
        city: form.city.value,
        state_code: form.state_code.value,
        zip: form.zip.value,
        is_default: form.is_default.checked
      })
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error || 'Could not save address.');
    resetAddressForm();
    loadAddresses();
  } catch(err){
    alert(err.message);
  } finally {
    btn.disabled = false;
  }
}

window.deleteAddress = async function(id){
  try{
    await fetch(ADDRESSES_DELETE_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    loadAddresses();
  } catch(err){
    console.error('Could not delete address:', err);
  }
}
function initNavToggle(){
  const burger = document.getElementById('burgerBtn');
  const links = document.getElementById('navLinks');
  burger?.addEventListener('click', () => {
    links.style.display = links.style.display === 'flex' ? 'none' : 'flex';
  });
}

/* ===================== WELCOME / DISCOUNT POPUP ===================== */
function initWelcomePopup(){
  const seen = localStorage.getItem('boa_welcome_seen');
  if(!seen){
    setTimeout(() => {
      document.getElementById('welcomeOverlay')?.classList.add('open');
    }, 3500);
  }
  document.getElementById('welcomeCloseBtn')?.addEventListener('click', dismissWelcome);
  document.getElementById('welcomeOverlay')?.addEventListener('click', (e) => {
    if(e.target.id === 'welcomeOverlay') dismissWelcome();
  });
  document.getElementById('welcomeForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const email = document.getElementById('welcomeEmail').value;
    // NOTE: this only stores the email locally for demo purposes.
    // In production this must POST to a backend endpoint that saves
    // the email to your ESP/CRM (Klaviyo, Mailchimp, etc). See
    // README-integrations.md for the exact endpoint to build.
    console.log('Captured email (demo only, not sent anywhere):', email);
    document.getElementById('welcomeForm').style.display = 'none';
    document.getElementById('welcomeCode').classList.add('show');
    localStorage.setItem('boa_welcome_seen', '1');
  });
}
function dismissWelcome(){
  document.getElementById('welcomeOverlay')?.classList.remove('open');
  localStorage.setItem('boa_welcome_seen', '1');
}

/* ===================== CHECKOUT (Stripe Payment Element / PayPal stub) ===================== */
let stripeInstance = null;
let stripeElements = null;
let stripePublishableKey = null;

function renderCheckoutSummary(){
  const el = document.getElementById('checkoutSummary');
  if(!el) return;
  const { subtotal, shipping, total } = cartTotals();
  el.innerHTML = cart.map(c => `
    <div class="option-line"><span class="desc">${c.title} (${c.color}, ${c.size}) × ${c.qty}</span><span>$${(c.price*c.qty).toFixed(2)}</span></div>
  `).join('') + `
    <div class="option-line"><span class="desc">Subtotal</span><span>$${subtotal.toFixed(2)}</span></div>
    <div class="option-line"><span class="desc">Shipping (US)</span><span>${shipping === 0 ? 'Free' : '$' + shipping.toFixed(2)}</span></div>
    <div class="option-line" style="font-weight:600;"><span class="desc">Total</span><span>$${total.toFixed(2)}</span></div>
  `;
  const amountEl = document.getElementById('payBtnAmount');
  if(amountEl) amountEl.textContent = `$${total.toFixed(2)}`;
}

async function getStripePublishableKey(){
  if(stripePublishableKey) return stripePublishableKey;
  const res = await fetch('/api/stripe-config.php', { credentials: 'include' });
  const data = await res.json();
  stripePublishableKey = data.publishable_key;
  return stripePublishableKey;
}

// Creates a fresh PaymentIntent (+ Customer Session if signed in) for the
// current cart, and mounts Stripe's embedded Payment Element in the
// checkout modal. Runs every time the checkout modal opens, since the
// cart total could have changed.
async function initPaymentElement(){
  const container = document.getElementById('paymentElementContainer');
  const payBtn = document.getElementById('stripePayBtn');
  const errorEl = document.getElementById('checkoutPayError');
  if(!container) return;
  if(errorEl) errorEl.style.display = 'none';
  if(payBtn) payBtn.disabled = true;
  container.innerHTML = `<p style="font-size:12px; color:var(--ink-faded); margin:0;">Loading payment form...</p>`;

  if(cart.length === 0) return;

  try{
    const pk = await getStripePublishableKey();
    if(!stripeInstance) stripeInstance = Stripe(pk);

    const emailInput = document.getElementById('checkoutEmail');
    const res = await fetch(CHECKOUT_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cart: cart.map(c => ({ id: c.id, color: c.color, size: c.size, qty: c.qty })),
        email: emailInput?.value || undefined
      })
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error || 'Could not start checkout.');

    const elementsOptions = { clientSecret: data.client_secret, appearance: { theme: 'stripe' } };
    if(data.customer_session_client_secret){
      elementsOptions.customerSessionClientSecret = data.customer_session_client_secret;
    }
    stripeElements = stripeInstance.elements(elementsOptions);
    const paymentElement = stripeElements.create('payment');
    container.innerHTML = '';
    paymentElement.mount('#paymentElementContainer');
    if(payBtn) payBtn.disabled = false;
  } catch(err){
    console.error('Could not initialize payment form:', err);
    container.innerHTML = `<p style="font-size:12px; color:var(--stamp-red); margin:0;">${err.message}</p>`;
  }
}

function initCheckoutButtons(){
  document.getElementById('stripePayBtn')?.addEventListener('click', async (e) => {
    if(cart.length === 0){ alert('Your build sheet is empty.'); return; }
    if(!stripeInstance || !stripeElements){ alert('Payment form is still loading — try again in a moment.'); return; }

    const btn = e.currentTarget;
    const errorEl = document.getElementById('checkoutPayError');
    const originalLabel = btn.innerHTML;
    errorEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const fullName = document.getElementById('checkoutFullName')?.value || '';
    const address1 = document.getElementById('checkoutAddress1')?.value || '';
    const city = document.getElementById('checkoutCity')?.value || '';
    const stateCode = document.getElementById('checkoutState')?.value || '';
    const zip = document.getElementById('checkoutZip')?.value || '';

    try{
      const { error, paymentIntent } = await stripeInstance.confirmPayment({
        elements: stripeElements,
        confirmParams: {
          return_url: window.location.origin + '/checkout-success.html',
          shipping: {
            name: fullName || 'Customer',
            address: { line1: address1, city: city, state: stateCode, postal_code: zip, country: 'US' }
          }
        },
        redirect: 'if_required'
      });

      if(error){
        throw new Error(error.message || 'Payment failed.');
      }

      // Most test cards resolve here without ever leaving the page —
      // redirect ourselves to the same success page Stripe would have
      // used, so the experience is identical either way.
      localStorage.removeItem('boa_cart');
      window.location.href = `checkout-success.html?payment_intent=${paymentIntent.id}`;
    } catch(err){
      errorEl.textContent = err.message;
      errorEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = originalLabel;
    }
  });
}

/* ===================== PAYPAL ===================== */
let paypalSdkLoaded = false;
let paypalClientId = null;

function loadPayPalSdk(){
  return new Promise(async (resolve, reject) => {
    if(paypalSdkLoaded && window.paypal) return resolve();
    try{
      if(!paypalClientId){
        const res = await fetch('/api/paypal-config.php', { credentials: 'include' });
        const data = await res.json();
        paypalClientId = data.client_id;
      }
      const script = document.createElement('script');
      script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(paypalClientId)}&currency=USD`;
      script.onload = () => { paypalSdkLoaded = true; resolve(); };
      script.onerror = () => reject(new Error('Could not load PayPal SDK.'));
      document.body.appendChild(script);
    } catch(err){
      reject(err);
    }
  });
}

async function initPayPalButton(){
  const container = document.getElementById('paypalButtonContainer');
  if(!container || cart.length === 0) return;
  container.innerHTML = `<p style="font-size:11.5px; color:var(--ink-faded); margin:0;">Loading PayPal...</p>`;

  try{
    await loadPayPalSdk();
    container.innerHTML = '';

    window.paypal.Buttons({
      style: { layout: 'horizontal', color: 'gold', label: 'paypal', height: 45 },

      createOrder: async () => {
        const res = await fetch(PAYPAL_CREATE_ORDER_ENDPOINT, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            cart: cart.map(c => ({ id: c.id, color: c.color, size: c.size, qty: c.qty })),
            full_name: document.getElementById('checkoutFullName')?.value || '',
            address1: document.getElementById('checkoutAddress1')?.value || '',
            city: document.getElementById('checkoutCity')?.value || '',
            state_code: document.getElementById('checkoutState')?.value || '',
            zip: document.getElementById('checkoutZip')?.value || '',
            email: document.getElementById('checkoutEmail')?.value || ''
          })
        });
        const data = await res.json();
        if(!res.ok) throw new Error(data.error || 'Could not start PayPal checkout.');
        return data.id;
      },

      onApprove: async (data) => {
        const res = await fetch(PAYPAL_CAPTURE_ORDER_ENDPOINT, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ orderID: data.orderID })
        });
        const result = await res.json();
        if(!res.ok) throw new Error(result.error || 'Payment could not be completed.');
        localStorage.removeItem('boa_cart');
        window.location.href = `checkout-success.html?payment_intent=paypal_${data.orderID}`;
      },

      onError: (err) => {
        console.error('PayPal error:', err);
        const errorEl = document.getElementById('checkoutPayError');
        if(errorEl){
          errorEl.textContent = 'PayPal checkout failed. Please try again or use a card instead.';
          errorEl.style.display = 'block';
        }
      }
    }).render('#paypalButtonContainer');
  } catch(err){
    console.error('Could not initialize PayPal:', err);
    container.innerHTML = `<p style="font-size:11.5px; color:var(--stamp-red); margin:0;">${err.message}</p>`;
  }
}
