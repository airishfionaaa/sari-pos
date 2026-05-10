<?php
// views/products/index.php
Auth::requireLogin();
$pageTitle  = 'Mga Produkto';
$page       = 'products';
$csrfToken  = Security::csrfToken();
$categories = Database::query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
require_once __DIR__ . '/../partials/header.php';
?>

<div class="page-toolbar">
  <div style="display:flex;gap:8px;flex:1;flex-wrap:wrap">
    <input type="text" id="prodSearch" class="form-input" style="max-width:240px" placeholder="Hanapin...">
    <select id="prodCat" class="form-input" style="width:180px">
      <option value="">Lahat</option>
      <?php foreach($categories as $c): ?>
      <option value="<?= (int)$c['id'] ?>"><?= Security::e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline" id="lowStockBtn" onclick="toggleLow()">
      <i class="fas fa-exclamation-triangle"></i> Mababang Stock
    </button>
  </div>
  <?php if(Auth::isAdmin()): ?>
  <button class="btn btn-primary" onclick="openProdModal()">
    <i class="fas fa-plus"></i> Bagong Produkto
  </button>
  <?php endif; ?>
</div>

<div class="card">
  <div class="tbl-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Pangalan</th>
          <th>Kategorya</th>
          <th>Gastos</th>
          <th>Presyo</th>
          <th>Stock</th>
          <th>Min</th>
          <th>Unit</th>
          <th>Expiry</th>
          <th>Status</th>
          <?php if(Auth::isAdmin()): ?><th>Aksyon</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="prodBody">
        <tr><td colspan="10" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Product Modal -->
<div class="modal-bg" id="prodModal">
  <div class="modal-box modal-lg">
    <div class="modal-title">
      <span><i class="fas fa-box"></i> <span id="prodModalTitle">Bagong Produkto</span></span>
      <button class="modal-x" onclick="closeModal('prodModal')">×</button>
    </div>
    <form id="prodForm">
      <input type="hidden" name="csrf_token" id="pCsrf">
      <input type="hidden" name="id" id="pId" value="0">
      <div class="form-grid-2">
        <div class="form-group" style="grid-column:1/-1">
          <label>Pangalan *</label>
          <input type="text" name="name" id="pName" class="form-input" required maxlength="150">
        </div>
        <div class="form-group">
          <label>Kategorya *</label>
          <select name="category_id" id="pCatId" class="form-input" required>
            <option value="">Piliin...</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= Security::e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Unit</label>
          <input type="text" name="unit" id="pUnit" class="form-input" value="pc" maxlength="30">
        </div>
        <div class="form-group">
          <label>Gastos (₱) *</label>
          <input type="number" name="buy_price" id="pBuy" class="form-input" step="0.01" min="0" required>
        </div>
        <div class="form-group">
          <label>Presyo (₱) *</label>
          <input type="number" name="sell_price" id="pSell" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label>Stock</label>
          <input type="number" name="stock_qty" id="pStock" class="form-input" min="0" value="0">
        </div>
        <div class="form-group">
          <label>Min Stock Alert</label>
          <input type="number" name="low_stock" id="pLow" class="form-input" min="1" value="5">
        </div>
        <div class="form-group">
          <label>Expiry Date</label>
          <input type="date" name="expiry_date" id="pExpiry" class="form-input">
        </div>
        <div class="form-group">
          <label>Barcode (optional)</label>
          <input type="text" name="barcode" id="pBarcode" class="form-input" maxlength="60">
        </div>
      </div>
      <div class="form-group">
        <label>Deskripsyon</label>
        <textarea name="description" id="pDesc" class="form-input" rows="2" maxlength="500"></textarea>
      </div>
      <div id="pErr" class="alert alert-danger" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('prodModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> I-save</button>
      </div>
    </form>
  </div>
</div>

<!-- Restock Modal -->
<div class="modal-bg" id="prodRestockModal">
  <div class="modal-box modal-sm">
    <div class="modal-title">
      <span><i class="fas fa-box-open"></i> Dagdag Stock</span>
      <button class="modal-x" onclick="closeModal('prodRestockModal')">×</button>
    </div>
    <form id="pRestockForm">
      <input type="hidden" name="csrf_token" id="prCsrf">
      <input type="hidden" name="product_id" id="prProdId">
      <div style="padding:0 0 12px">
        <p id="prProdName" class="text-muted mb-2" style="font-weight:600"></p>
      </div>
      <div class="form-group">
        <label>Dami (+)</label>
        <input type="number" name="qty" class="form-input" min="1" value="1" required>
      </div>
      <div class="form-group">
        <label>Tala</label>
        <input type="text" name="note" class="form-input" maxlength="200">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('prodRestockModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> I-save</button>
      </div>
    </form>
  </div>
</div>

<script>
const CSRF = '<?= Security::e($csrfToken) ?>';
let showLowOnly = false;

async function loadProds() {
  const q   = document.getElementById('prodSearch').value;
  const cat = document.getElementById('prodCat').value;
  const low = showLowOnly ? 1 : 0;
  try {
    const r = await fetch(`/sari-pos/index.php?page=products&action=list&q=${encodeURIComponent(q)}&category_id=${cat}&low_only=${low}`);
    const d = await r.json();
    const cols = <?= Auth::isAdmin() ? 10 : 9 ?>;
    if (!d.success || !d.data.length) {
      document.getElementById('prodBody').innerHTML = `<tr><td colspan="${cols}" class="tbl-empty">Walang produkto.</td></tr>`;
      return;
    }
    document.getElementById('prodBody').innerHTML = d.data.map(p => {
      const expiryClass = p.expiry_date && new Date(p.expiry_date) <= new Date(Date.now() + 7*86400000) ? 'text-danger' : '';
      const stockClass  = p.stock_qty == 0 ? 'text-danger' : '';
      const statusBadge = p.stock_qty == 0
        ? '<span class="badge badge-danger">Ubos</span>'
        : p.stock_qty <= p.low_stock
          ? '<span class="badge badge-warning">Mababa</span>'
          : '<span class="badge badge-success">OK</span>';
      <?php if(Auth::isAdmin()): ?>
      const actions = `
        <button class="btn btn-xs btn-outline" onclick='editProd(${JSON.stringify(p)})' title="I-edit"><i class="fas fa-edit"></i></button>
        <button class="btn btn-xs btn-success" onclick="openRestock(${p.id},'${esc(p.name)}')" title="Dagdag stock"><i class="fas fa-plus"></i></button>
        <button class="btn btn-xs btn-danger" onclick="delProd(${p.id},'${esc(p.name)}')" title="I-deactivate"><i class="fas fa-trash"></i></button>`;
      <?php else: ?>
      const actions = '';
      <?php endif; ?>
      return `<tr>
        <td><strong>${esc(p.name)}</strong>${p.description ? `<br><small class="text-muted">${esc(p.description)}</small>` : ''}</td>
        <td>${esc(p.category)}</td>
        <td>₱${parseFloat(p.buy_price).toFixed(2)}</td>
        <td><strong>₱${parseFloat(p.sell_price).toFixed(2)}</strong></td>
        <td class="${stockClass}"><strong>${p.stock_qty}</strong></td>
        <td>${p.low_stock}</td>
        <td>${esc(p.unit || 'pc')}</td>
        <td class="${expiryClass}">${p.expiry_date || '—'}</td>
        <td>${statusBadge}</td>
        <?php if(Auth::isAdmin()): ?><td style="white-space:nowrap">${actions}</td><?php endif; ?>
      </tr>`;
    }).join('');
  } catch(e) {
    document.getElementById('prodBody').innerHTML = '<tr><td colspan="10" class="tbl-empty text-danger">Error loading. I-refresh ang page.</td></tr>';
  }
}

function toggleLow() {
  showLowOnly = !showLowOnly;
  document.getElementById('lowStockBtn').classList.toggle('btn-warning', showLowOnly);
  document.getElementById('lowStockBtn').classList.toggle('btn-outline', !showLowOnly);
  loadProds();
}

function openProdModal(data = null) {
  document.getElementById('prodModalTitle').textContent = data ? 'I-edit ang Produkto' : 'Bagong Produkto';
  document.getElementById('pId').value       = data?.id || 0;
  document.getElementById('pName').value     = data?.name || '';
  document.getElementById('pCatId').value    = data?.category_id || '';
  document.getElementById('pBarcode').value  = data?.barcode || '';
  document.getElementById('pBuy').value      = data?.buy_price || '';
  document.getElementById('pSell').value     = data?.sell_price || '';
  document.getElementById('pStock').value    = data?.stock_qty || 0;
  document.getElementById('pLow').value      = data?.low_stock || 5;
  document.getElementById('pUnit').value     = data?.unit || 'pc';
  document.getElementById('pExpiry').value   = data?.expiry_date || '';
  document.getElementById('pDesc').value     = data?.description || '';
  document.getElementById('pErr').style.display = 'none';
  document.getElementById('pCsrf').value     = CSRF;
  document.getElementById('prodModal').classList.add('show');
}

function editProd(p) { openProdModal(p); }

document.getElementById('prodForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=products&action=save', {method:'POST', body:fd});
    const d  = await r.json();
    if (d.success) {
      closeModal('prodModal');
      loadProds();
      // Show big visible alert
      showAlert(d.message || 'Na-save ang produkto!', 'success');
    } else {
      document.getElementById('pErr').textContent   = d.message || 'May error. Subukan ulit.';
      document.getElementById('pErr').style.display = 'block';
    }
  } catch(e) {
    document.getElementById('pErr').textContent   = 'Network error. I-check ang koneksyon.';
    document.getElementById('pErr').style.display = 'block';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> I-save';
});

async function delProd(id, name) {
  if (!confirm(`I-delete ang "${name}"?\n\nHindi na ito mababalik!`)) return;
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    const r = await fetch('/sari-pos/index.php?page=products&action=delete', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      loadProds();
      showAlert(d.message || 'Na-delete ang produkto!', 'success');
    } else {
      showAlert(d.message || 'Hindi na-delete. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error. I-check ang koneksyon.', 'danger');
  }
}

function openRestock(id, name) {
  document.getElementById('prProdId').value    = id;
  document.getElementById('prProdName').textContent = name;
  document.getElementById('prCsrf').value      = CSRF;
  document.getElementById('prodRestockModal').classList.add('show');
}

document.getElementById('pRestockForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=products&action=restock', {method:'POST', body:fd});
    const d  = await r.json();
    if (d.success) {
      closeModal('prodRestockModal');
      loadProds();
      showAlert(d.message || 'Na-update ang stock!', 'success');
    } else {
      showAlert(d.message || 'Hindi na-update. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error. I-check ang koneksyon.', 'danger');
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-check"></i> I-save';
});

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

let st;
document.getElementById('prodSearch').addEventListener('input', () => { clearTimeout(st); st = setTimeout(loadProds, 300); });
document.getElementById('prodCat').addEventListener('change', loadProds);


// ── Visible Alert Banner ─────────────────────────────────
function showAlert(msg, type='success') {
  // Remove existing alert
  const existing = document.getElementById('pageAlert');
  if (existing) existing.remove();

  const colors = {
    success: { bg: '#dcfce7', border: '#16a34a', color: '#15803d', icon: 'fa-check-circle' },
    danger:  { bg: '#fee2e2', border: '#dc2626', color: '#dc2626', icon: 'fa-times-circle' },
    warning: { bg: '#fef3c7', border: '#d97706', color: '#d97706', icon: 'fa-exclamation-circle' },
  };
  const c = colors[type] || colors.success;

  const div = document.createElement('div');
  div.id = 'pageAlert';
  div.style.cssText = `
    position: fixed; top: 70px; left: 50%; transform: translateX(-50%);
    background: ${c.bg}; border: 2px solid ${c.border}; color: ${c.color};
    padding: 14px 24px; border-radius: 10px; font-size: 15px; font-weight: 700;
    z-index: 9999; display: flex; align-items: center; gap: 10px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15); min-width: 300px; justify-content: center;
  `;
  div.innerHTML = `<i class="fas ${c.icon}" style="font-size:18px"></i><span>${msg}</span>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:${c.color};font-size:18px;margin-left:8px;">×</button>`;
  document.body.appendChild(div);

  // Auto-remove after 4 seconds
  setTimeout(() => { if (div.parentElement) div.remove(); }, 4000);
}

document.addEventListener('DOMContentLoaded', () => {
  loadProds();
  if (window.posApp?.pusher) {
    window.posApp.pusher.subscribe('inventory').bind('stock-updated', () => {
      loadProds();
      showToast('Stock na-update!', 'info');
    });
  }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>