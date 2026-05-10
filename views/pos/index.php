<?php
// views/pos/index.php
Auth::requireLogin();
$pageTitle  = 'Point of Sale';
$page       = 'pos';
$csrfToken  = Security::csrfToken();
$categories = Database::query("SELECT id, name, color FROM categories ORDER BY name")->fetchAll();
$customers  = Database::query("SELECT id, name, balance, credit_limit FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();
require_once __DIR__ . '/../partials/header.php';
?>

<style>
.pos-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 16px;
  height: calc(100vh - var(--topbar-h) - 40px);
}
.pos-left { display: flex; flex-direction: column; min-height: 0; }

/* Search fix */
.pos-search { display: flex; gap: 8px; margin-bottom: 10px; align-items: center; }
.search-icon-wrap { position: relative; flex: 1; }
.search-icon-wrap i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  font-size: 14px;
  pointer-events: none;
  z-index: 1;
}
.search-icon-wrap input {
  padding-left: 36px !important;
  width: 100%;
}

/* Category pills — wrap, no scroll */
.cat-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.cat-pill {
  padding: 5px 12px;
  border: 1.5px solid var(--gray-200);
  border-radius: 99px;
  background: #fff;
  font-size: 12px;
  cursor: pointer;
  font-weight: 600;
  transition: all var(--transition);
  color: var(--gray-600);
  white-space: nowrap;
}
.cat-pill:hover { border-color: var(--primary); color: var(--primary); }
.cat-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }

/* Product grid — vertical scroll only */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px;
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  align-content: start;
  padding-right: 4px;
}
.product-grid::-webkit-scrollbar { width: 6px; }
.product-grid::-webkit-scrollbar-track { background: transparent; }
.product-grid::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 99px; }

.prod-card {
  background: #fff;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius);
  padding: 12px;
  cursor: pointer;
  transition: all var(--transition);
}
.prod-card:hover:not(.prod-oos) {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(37,99,235,.1);
  transform: translateY(-2px);
}
.prod-card.prod-oos { opacity: .5; cursor: not-allowed; }
.prod-card.prod-low { border-color: var(--warning); }
.pc-cat  { font-size: 10px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
.pc-name { font-size: 13px; font-weight: 600; line-height: 1.3; margin-bottom: 6px; color: var(--gray-800); }
.pc-price { font-size: 17px; font-weight: 700; color: var(--primary); }
.pc-stock { font-size: 11px; margin-top: 3px; }
.pc-stock.ok  { color: var(--gray-400); }
.pc-stock.low { color: var(--warning); }
.pc-stock.oos { color: var(--danger); font-weight: 700; }

/* Cart */
.pos-right {
  background: #fff;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.cart-body {
  flex: 1;
  overflow-y: auto;
  padding: 8px 12px;
  min-height: 80px;
  max-height: 200px;
}
.cart-body::-webkit-scrollbar { width: 4px; }
.cart-body::-webkit-scrollbar-thumb { background: var(--gray-200); border-radius: 99px; }

.payment-area {
  overflow-y: auto;
  max-height: 320px;
  padding: 12px 16px;
}
.payment-area::-webkit-scrollbar { width: 4px; }
.payment-area::-webkit-scrollbar-thumb { background: var(--gray-200); border-radius: 99px; }
.pay-methods { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 10px; }
</style>

<div class="pos-layout">
  <!-- LEFT: Products -->
  <div class="pos-left">
    <div class="pos-search">
      <div class="search-icon-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="posSearch" placeholder="Hanapin ang produkto..." autocomplete="off">
      </div>
      <button class="btn btn-sm btn-outline" onclick="loadProducts()" title="I-refresh">
        <i class="fas fa-sync"></i>
      </button>
    </div>

    <!-- Category pills (wrap) - from DB -->
    <div class="cat-pills">
      <button class="cat-pill active" onclick="filterCat('',this)">🏪 Lahat</button>
      <?php
      $emojiMap = [
        'Beverages'          => '🥤',
        'Snacks'             => '🍪',
        'Frozen Goods'       => '🧊',
        'Household Supplies' => '🏠',
        'Personal Care'      => '🧴',
        'School Supplies'    => '📚',
        'E-Loading Services' => '📱',
        'Cigarettes & Vape'  => '🚬',
        'Cooking Essentials' => '🍳',
        'Baby Products'      => '👶',
        'Medicines'          => '💊',
        'Pet Supplies'       => '🐾',
        'Canned Goods'       => '🥫',
        'Condiments'         => '🧂',
        'Dairy'              => '🥛',
        'Inumin'             => '🧃',
        'Meryenda'           => '🍿',
        'Others'             => '📦',
        'Tobacco'            => '🚬',
      ];
      foreach($categories as $c):
        $emoji = $emojiMap[$c['name']] ?? '📦';
      ?>
      <button class="cat-pill" onclick="filterCat(<?= (int)$c['id'] ?>,this)">
        <?= $emoji ?> <?= Security::e($c['name']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div id="productGrid" class="product-grid">
      <div class="grid-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-right">
    <div class="cart-head">
      <h3><i class="fas fa-shopping-basket"></i> Cart</h3>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm btn-outline" onclick="clearCart()" title="I-clear ang cart">
          <i class="fas fa-trash"></i>
        </button>
        <?php if(Auth::isAdmin()): ?>
        <button class="btn btn-sm btn-outline" onclick="applyDiscount()" title="Diskwento">
          <i class="fas fa-percent"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <div id="cartBody" class="cart-body">
      <div id="cartEmpty" class="cart-empty">
        <i class="fas fa-shopping-cart"></i>
        <p>Cart ay walang laman</p>
        <small>I-click ang produkto para idagdag</small>
      </div>
    </div>

    <!-- Totals -->
    <div class="cart-totals">
      <div class="total-line"><span>Subtotal</span><span id="cartSub">₱0.00</span></div>
      <div class="total-line">
        <span>Diskwento</span>
        <div style="display:flex;align-items:center;gap:4px">
          <span id="discLabel">₱0.00</span>
          <input type="hidden" id="discountAmt" value="0">
        </div>
      </div>
      <div class="total-line total-grand"><span>TOTAL</span><span id="cartTotal">₱0.00</span></div>
    </div>

    <!-- Payment -->
    <div class="payment-area">
      <div class="pay-methods">
        <label class="pay-method active" data-m="cash">
          <input type="radio" name="paymethod" value="cash" checked>
          <i class="fas fa-money-bill-wave"></i> Cash
        </label>
        <label class="pay-method" data-m="gcash">
          <input type="radio" name="paymethod" value="gcash">
          <i class="fas fa-mobile-alt"></i> GCash
        </label>
        <label class="pay-method pay-utang" data-m="utang">
          <input type="radio" name="paymethod" value="utang">
          <i class="fas fa-user-clock"></i> Utang
        </label>
      </div>

      <div id="cashSection">
        <label class="form-label">Ibinigay na Pera</label>
        <input type="number" id="tenderAmt" class="tender-input" min="0" step="0.01" placeholder="0.00" oninput="calcChange()">
        <div id="quickBills" class="quick-bills"></div>
        <div class="change-line"><span>Sukli</span><strong id="changeDue">₱0.00</strong></div>
      </div>

      <div id="gcashSection" style="display:none">
        <div class="info-box" style="text-align:center;padding:12px;">
          <i class="fas fa-mobile-alt" style="font-size:24px;color:var(--success)"></i>
          <div style="font-weight:700;margin-top:4px;">GCash Payment</div>
          <div style="font-size:13px;color:var(--gray-500)">I-confirm ang bayad sa GCash</div>
        </div>
      </div>

      <div id="utangSection" style="display:none">
        <label class="form-label">Customer (may utang)</label>
        <select id="utangCustomer" class="form-input">
          <option value="">-- Piliin ang Customer --</option>
          <?php foreach($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
                  data-limit="<?= Security::e($c['credit_limit']) ?>"
                  data-balance="<?= Security::e($c['balance']) ?>">
            <?= Security::e($c['name']) ?> (Utang: ₱<?= number_format($c['balance'],2) ?> / Limit: ₱<?= number_format($c['credit_limit'],2) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-top:8px">
        <input type="text" id="posNotes" class="form-input" placeholder="Tala (optional)" maxlength="200">
      </div>

      <button class="btn-charge" id="chargeBtn" onclick="processCheckout()" disabled>
        <i class="fas fa-check-circle"></i> CHARGE &nbsp;₱<span id="chargeBtnAmt">0.00</span>
      </button>
    </div>
  </div>
</div>

<!-- Discount Modal -->
<div class="modal-bg" id="discountModal">
  <div class="modal-box modal-sm">
    <div class="modal-title">
      <span><i class="fas fa-percent"></i> I-apply ang Diskwento</span>
      <button class="modal-x" onclick="closeModal('discountModal')">×</button>
    </div>
    <div style="padding:16px 20px">
      <div class="form-group">
        <label>Halaga ng Diskwento (₱)</label>
        <input type="number" id="discInput" class="form-input" min="0" step="0.01" placeholder="0.00">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('discountModal')">Kanselahin</button>
      <button class="btn btn-primary" onclick="saveDiscount()"><i class="fas fa-check"></i> I-apply</button>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal-bg" id="receiptModal">
  <div class="modal-box modal-receipt">
    <div class="receipt-check"><i class="fas fa-check-circle"></i></div>
    <h2 class="receipt-title">Bayad Na!</h2>
    <div id="receiptContent" class="receipt-body"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> I-print</button>
      <button class="btn btn-primary" onclick="newTransaction()"><i class="fas fa-plus"></i> Bagong Txn</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
let cart = [], discountAmt = 0, selectedCat = '';

async function loadProducts(search='', cat='') {
  const grid = document.getElementById('productGrid');
  grid.innerHTML = '<div class="grid-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  try {
    const p = new URLSearchParams({q: search, category_id: cat});
    const r = await fetch(`/sari-pos/index.php?page=products&action=list&${p}`);
    const d = await r.json();
    if (!d.success || !d.data.length) {
      grid.innerHTML = '<div class="grid-empty">Walang produktong nahanap.</div>';
      return;
    }
    grid.innerHTML = d.data.map(p => `
      <div class="prod-card ${p.stock_qty==0?'prod-oos':p.stock_qty<=p.low_stock?'prod-low':''}"
           onclick="${p.stock_qty>0?`addItem(${p.id},'${esc(p.name)}',${p.sell_price},${p.stock_qty})`:''}">
        <div class="pc-cat" style="color:${p.cat_color||'#6b7280'}">${esc(p.category||'')}</div>
        <div class="pc-name">${esc(p.name)}</div>
        <div class="pc-price">₱${parseFloat(p.sell_price).toFixed(2)}</div>
        <div class="pc-stock ${p.stock_qty==0?'oos':p.stock_qty<=p.low_stock?'low':'ok'}">
          ${p.stock_qty==0?'OUT OF STOCK':p.stock_qty<=p.low_stock?`⚠ ${p.stock_qty} nalang`:`${p.stock_qty} ${esc(p.unit||'pcs')}`}
        </div>
      </div>`).join('');
  } catch(e) {
    grid.innerHTML = '<div class="grid-empty text-danger">Error loading products.</div>';
  }
}

function filterCat(cat, el) {
  selectedCat = cat;
  document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  loadProducts(document.getElementById('posSearch').value, cat);
}

function addItem(id, name, price, stock) {
  const ex = cart.find(c => c.id === id);
  if (ex) {
    if (ex.qty >= stock) { showToast(`Max stock (${stock}) na!`, 'warning'); return; }
    ex.qty++;
  } else {
    cart.push({id, name, price: parseFloat(price), qty: 1, max: stock});
  }
  renderCart();
  showToast(`${name} na-add`, 'success');
}

function setQty(id, v) {
  const i = cart.find(c => c.id === id); if (!i) return;
  v = parseInt(v);
  if (isNaN(v) || v < 1) { removeItem(id); return; }
  if (v > i.max) { showToast('Higit pa sa available na stock!', 'warning'); v = i.max; }
  i.qty = v; renderCart();
}

function removeItem(id) { cart = cart.filter(c => c.id !== id); renderCart(); }

function clearCart() {
  cart = []; discountAmt = 0;
  document.getElementById('discountAmt').value = 0;
  renderCart();
}

function renderCart() {
  const body = document.getElementById('cartBody');
  const empty = document.getElementById('cartEmpty');
  body.querySelectorAll('.cart-row').forEach(e => e.remove());
  if (!cart.length) { empty.style.display = 'flex'; recalc(); return; }
  empty.style.display = 'none';
  cart.forEach(item => {
    const line = item.price * item.qty;
    const row = document.createElement('div'); row.className = 'cart-row';
    row.innerHTML = `
      <div class="cr-info">
        <span class="cr-name">${esc(item.name)}</span>
        <span class="cr-unitprice">₱${item.price.toFixed(2)}/pc</span>
      </div>
      <div class="cr-controls">
        <button class="cr-btn" onclick="setQty(${item.id},${item.qty-1})"><i class="fas fa-minus"></i></button>
        <input type="number" class="cr-qty" value="${item.qty}" min="1" max="${item.max}" onchange="setQty(${item.id},this.value)">
        <button class="cr-btn" onclick="setQty(${item.id},${item.qty+1})"><i class="fas fa-plus"></i></button>
      </div>
      <div class="cr-line">₱${line.toFixed(2)}</div>
      <button class="cr-del" onclick="removeItem(${item.id})"><i class="fas fa-times"></i></button>`;
    body.appendChild(row);
  });
  recalc();
}

function recalc() {
  const sub = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const disc = parseFloat(document.getElementById('discountAmt').value) || 0;
  discountAmt = disc;
  const total = Math.max(0, sub - disc);
  document.getElementById('cartSub').textContent    = `₱${sub.toFixed(2)}`;
  document.getElementById('discLabel').textContent  = `- ₱${disc.toFixed(2)}`;
  document.getElementById('cartTotal').textContent  = `₱${total.toFixed(2)}`;
  document.getElementById('chargeBtnAmt').textContent = total.toFixed(2);
  document.getElementById('chargeBtn').disabled = cart.length === 0;
  buildQuickBills(total);
  calcChange();
}

function buildQuickBills(total) {
  const amts = [...new Set([
    Math.ceil(total),
    Math.ceil(total/10)*10,
    Math.ceil(total/50)*50,
    Math.ceil(total/100)*100,
    Math.ceil(total/500)*500
  ].filter(a => a > 0 && a >= total))].slice(0, 4);
  document.getElementById('quickBills').innerHTML =
    amts.map(a => `<button class="quick-bill" onclick="setTender(${a})">₱${a.toFixed(0)}</button>`).join('');
}

function setTender(v) { document.getElementById('tenderAmt').value = v; calcChange(); }

function calcChange() {
  const total  = parseFloat(document.getElementById('cartTotal').textContent.replace('₱','')) || 0;
  const tender = parseFloat(document.getElementById('tenderAmt').value) || 0;
  document.getElementById('changeDue').textContent = `₱${Math.max(0, tender - total).toFixed(2)}`;
}

document.querySelectorAll('.pay-method').forEach(el => {
  el.addEventListener('click', function() {
    document.querySelectorAll('.pay-method').forEach(m => m.classList.remove('active'));
    this.classList.add('active');
    const m = this.dataset.m;
    document.getElementById('cashSection').style.display  = m === 'cash'  ? 'block' : 'none';
    document.getElementById('gcashSection').style.display = m === 'gcash' ? 'block' : 'none';
    document.getElementById('utangSection').style.display = m === 'utang' ? 'block' : 'none';
  });
});

function applyDiscount() {
  document.getElementById('discInput').value = document.getElementById('discountAmt').value || 0;
  document.getElementById('discountModal').classList.add('show');
}
function saveDiscount() {
  document.getElementById('discountAmt').value = parseFloat(document.getElementById('discInput').value) || 0;
  recalc();
  closeModal('discountModal');
}

async function processCheckout() {
  if (!cart.length) { showToast('Cart ay walang laman!', 'warning'); return; }
  const total  = parseFloat(document.getElementById('cartTotal').textContent.replace('₱','')) || 0;
  const method = document.querySelector('input[name=paymethod]:checked').value;
  const tender = (method === 'cash') ? (parseFloat(document.getElementById('tenderAmt').value) || 0) : total;

  if (method === 'cash' && tender < total) { showToast('Hindi sapat ang bayad!', 'danger'); return; }
  if (method === 'utang' && !document.getElementById('utangCustomer').value) {
    showToast('Piliin ang customer para sa utang!', 'danger'); return;
  }

  const btn = document.getElementById('chargeBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

  const fd = new FormData();
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('items', JSON.stringify(cart.map(i => ({product_id:i.id, quantity:i.qty, discount_pct:0}))));
  fd.append('payment_method', method);
  fd.append('amount_tendered', tender);
  fd.append('discount_amount', discountAmt);
  fd.append('customer_id', method === 'utang' ? document.getElementById('utangCustomer').value : 0);
  fd.append('notes', document.getElementById('posNotes').value);

  try {
    const r = await fetch('/sari-pos/index.php?page=transactions&action=create', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      showReceipt(d.transaction, total, tender, method);
      clearCart();
      loadProducts(document.getElementById('posSearch').value, selectedCat);
    } else {
      showToast(d.message, 'danger');
      btn.disabled = false;
      btn.innerHTML = `<i class="fas fa-check-circle"></i> CHARGE &nbsp;₱<span id="chargeBtnAmt">${total.toFixed(2)}</span>`;
    }
  } catch(e) {
    showToast('Network error. Subukan ulit.', 'danger');
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-check-circle"></i> CHARGE &nbsp;₱<span id="chargeBtnAmt">${total.toFixed(2)}</span>`;
  }
}

function showReceipt(txn, total, tender, method) {
  const change = Math.max(0, tender - total);
  document.getElementById('receiptContent').innerHTML = `
    <div class="receipt-ref">Ref: <strong>${esc(txn.reference_no)}</strong></div>
    <div class="receipt-date">${new Date().toLocaleString('en-PH')}</div>
    <div class="receipt-divider"></div>
    ${(txn.items||[]).map(i=>`<div class="receipt-item"><span>${esc(i.product_name)} x${i.quantity}</span><span>₱${parseFloat(i.line_total).toFixed(2)}</span></div>`).join('')}
    <div class="receipt-divider"></div>
    <div class="receipt-line"><span>Total</span><strong>₱${parseFloat(txn.grand_total).toFixed(2)}</strong></div>
    <div class="receipt-line"><span>Bayad</span><span>₱${parseFloat(tender).toFixed(2)}</span></div>
    <div class="receipt-line text-success"><span>Sukli</span><strong>₱${change.toFixed(2)}</strong></div>
    <div class="receipt-line"><span>Paraan</span><span>${esc(method).toUpperCase()}</span></div>
    <div class="receipt-thanks">Salamat sa inyong pagbili! 😊</div>`;
  document.getElementById('receiptModal').classList.add('show');
  document.getElementById('chargeBtn').innerHTML = `<i class="fas fa-check-circle"></i> CHARGE &nbsp;₱<span id="chargeBtnAmt">0.00</span>`;
}

function newTransaction() {
  closeModal('receiptModal');
  clearCart();
  document.getElementById('tenderAmt').value = '';
  document.getElementById('posNotes').value  = '';
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

let searchTimer;
document.getElementById('posSearch').addEventListener('input', function() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadProducts(this.value, selectedCat), 250);
});

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', () => {
  loadProducts();
  if (window.posApp?.pusher) {
    const inv = window.posApp.pusher.subscribe('inventory');
    inv.bind('stock-updated', () => {
      loadProducts(document.getElementById('posSearch').value, selectedCat);
      showToast('Stock na-update!', 'info');
    });
  }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>