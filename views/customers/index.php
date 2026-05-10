<?php
// views/customers/index.php
Auth::requireLogin();
$pageTitle = 'Utang / Customers';
$page      = 'customers';
$csrfToken = Security::csrfToken();
require_once __DIR__ . '/../partials/header.php';
?>

<div class="kpi-row-sm" id="utangKpis" style="margin-bottom:14px"></div>

<div class="page-toolbar">
  <input type="text" id="custSearch" class="form-input" style="max-width:240px" placeholder="Hanapin ang customer...">
  <button class="btn btn-primary" onclick="openCustModal()">
    <i class="fas fa-user-plus"></i> Bagong Customer
  </button>
</div>

<div id="pageAlertWrap" style="margin-bottom:8px"></div>

<div class="card">
  <div class="tbl-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Pangalan</th>
          <th>Phone</th>
          <th>Credit Limit</th>
          <th>Utang Balance</th>
          <th>Status</th>
          <th>Mga Aksyon</th>
        </tr>
      </thead>
      <tbody id="custBody">
        <tr><td colspan="6" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Customer Modal -->
<div class="modal-bg" id="custModal">
  <div class="modal-box">
    <div class="modal-title">
      <span><i class="fas fa-user"></i> <span id="custModalTitle">Bagong Customer</span></span>
      <button class="modal-x" onclick="closeModal('custModal')">×</button>
    </div>
    <form id="custForm">
      <input type="hidden" name="csrf_token" id="cuCsrf">
      <input type="hidden" name="id" id="cuId" value="0">
      <div class="form-grid-2">
        <div class="form-group" style="grid-column:1/-1">
          <label>Pangalan *</label>
          <input type="text" name="name" id="cuName" class="form-input" required maxlength="120">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" id="cuPhone" class="form-input" maxlength="20">
        </div>
        <div class="form-group">
          <label>Credit Limit (₱)</label>
          <input type="number" name="credit_limit" id="cuLimit" class="form-input" step="0.01" min="0" value="500">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Address</label>
          <input type="text" name="address" id="cuAddr" class="form-input" maxlength="255">
        </div>
      </div>
      <div id="cuErr" class="alert alert-danger" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('custModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> I-save</button>
      </div>
    </form>
  </div>
</div>

<!-- Pay Utang Modal -->
<div class="modal-bg" id="payModal">
  <div class="modal-box modal-sm">
    <div class="modal-title">
      <span><i class="fas fa-hand-holding-usd"></i> I-record ang Bayad</span>
      <button class="modal-x" onclick="closeModal('payModal')">×</button>
    </div>
    <form id="payForm">
      <input type="hidden" name="csrf_token" id="payCsrf">
      <input type="hidden" name="customer_id" id="payCustId">
      <div style="padding:0 0 12px">
        <p id="payName" class="info-box"></p>
      </div>
      <div class="form-group">
        <label>Halaga ng Bayad (₱)</label>
        <input type="number" name="amount" class="form-input" id="payAmt" step="0.01" min="0.01" required>
      </div>
      <div class="form-group">
        <label>Tala</label>
        <input type="text" name="notes" class="form-input" maxlength="200">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('payModal')">Kanselahin</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Ibayad</button>
      </div>
    </form>
  </div>
</div>

<!-- Ledger Modal -->
<div class="modal-bg" id="ledgerModal">
  <div class="modal-box modal-lg">
    <div class="modal-title">
      <span><i class="fas fa-list"></i> Credit Ledger</span>
      <button class="modal-x" onclick="closeModal('ledgerModal')">×</button>
    </div>
    <div id="ledgerBody" style="padding:16px 20px"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('ledgerModal')">Isara</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= Security::e($csrfToken) ?>';

function showAlert(msg, type = 'success') {
  const colors = {
    success: { bg: '#dcfce7', border: '#16a34a', color: '#15803d', icon: 'fa-check-circle' },
    danger:  { bg: '#fee2e2', border: '#dc2626', color: '#dc2626', icon: 'fa-times-circle' },
    warning: { bg: '#fef3c7', border: '#d97706', color: '#d97706', icon: 'fa-exclamation-circle' },
  };
  const c = colors[type] || colors.success;
  const wrap = document.getElementById('pageAlertWrap');
  wrap.innerHTML = `
    <div style="background:${c.bg};border:2px solid ${c.border};color:${c.color};
      padding:12px 20px;border-radius:8px;font-size:14px;font-weight:700;
      display:flex;align-items:center;gap:10px;">
      <i class="fas ${c.icon}" style="font-size:18px"></i>
      <span style="flex:1">${msg}</span>
      <button onclick="this.parentElement.parentElement.innerHTML=''"
        style="background:none;border:none;cursor:pointer;color:${c.color};font-size:18px;">×</button>
    </div>`;
  setTimeout(() => { wrap.innerHTML = ''; }, 4000);
}

async function loadCusts() {
  const q = document.getElementById('custSearch').value;
  try {
    const r = await fetch(`/sari-pos/index.php?page=customers&action=list&q=${encodeURIComponent(q)}`);
    const d = await r.json();
    const totUtang = d.data.reduce((s, c) => s + parseFloat(c.balance), 0);
    document.getElementById('utangKpis').innerHTML = `
      <div class="kpi-sm kpi-red"><p>Total Receivables</p><h3>₱${totUtang.toLocaleString('en-PH',{minimumFractionDigits:2})}</h3></div>
      <div class="kpi-sm kpi-blue"><p>Bilang ng Customers</p><h3>${d.data.length}</h3></div>
      <div class="kpi-sm kpi-amber"><p>May Utang</p><h3>${d.data.filter(c=>c.balance>0).length}</h3></div>`;
    document.getElementById('custBody').innerHTML = d.data.length
      ? d.data.map(c => `<tr>
          <td><strong class="clickable" onclick="viewLedger(${c.id},'${esc(c.name)}')">${esc(c.name)}</strong></td>
          <td>${esc(c.phone || '—')}</td>
          <td>₱${parseFloat(c.credit_limit).toFixed(2)}</td>
          <td class="${parseFloat(c.balance) > 0 ? 'text-danger' : 'text-success'}">
            <strong>₱${parseFloat(c.balance).toFixed(2)}</strong>
          </td>
          <td><span class="badge ${parseFloat(c.balance) > 0 ? 'badge-danger' : 'badge-success'}">
            ${parseFloat(c.balance) > 0 ? 'May Utang' : 'OK'}
          </span></td>
          <td style="white-space:nowrap">
            ${parseFloat(c.balance) > 0
              ? `<button class="btn btn-xs btn-success" onclick="openPay(${c.id},'${esc(c.name)}',${c.balance})"><i class="fas fa-hand-holding-usd"></i> Bayad</button>`
              : ''}
            <button class="btn btn-xs btn-outline" onclick='editCust(${JSON.stringify(c)})' title="I-edit"><i class="fas fa-edit"></i></button>
            <button class="btn btn-xs btn-outline" onclick="viewLedger(${c.id},'${esc(c.name)}')" title="Ledger"><i class="fas fa-list"></i></button>
            <button class="btn btn-xs btn-danger" onclick="delCust(${c.id},'${esc(c.name)}')" title="I-delete"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="6" class="tbl-empty">Walang customers.</td></tr>';
  } catch(e) {
    document.getElementById('custBody').innerHTML = '<tr><td colspan="6" class="tbl-empty text-danger">Error loading. I-refresh ang page.</td></tr>';
  }
}

function openCustModal(data = null) {
  document.getElementById('custModalTitle').textContent = data ? 'I-edit ang Customer' : 'Bagong Customer';
  document.getElementById('cuId').value    = data?.id || 0;
  document.getElementById('cuName').value  = data?.name || '';
  document.getElementById('cuPhone').value = data?.phone || '';
  document.getElementById('cuLimit').value = data?.credit_limit || 500;
  document.getElementById('cuAddr').value  = data?.address || '';
  document.getElementById('cuCsrf').value  = CSRF;
  document.getElementById('cuErr').style.display = 'none';
  document.getElementById('custModal').classList.add('show');
}

function editCust(c) { openCustModal(c); }

document.getElementById('custForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=customers&action=save', {method:'POST', body:fd});
    const d  = await r.json();
    if (d.success) {
      closeModal('custModal');
      loadCusts();
      showAlert(d.message || 'Na-save ang customer!', 'success');
    } else {
      document.getElementById('cuErr').textContent   = d.message || 'May error. Subukan ulit.';
      document.getElementById('cuErr').style.display = 'block';
    }
  } catch(e) {
    document.getElementById('cuErr').textContent   = 'Network error.';
    document.getElementById('cuErr').style.display = 'block';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> I-save';
});

async function delCust(id, name) {
  if (!confirm(`I-delete si "${name}"?\n\nHindi na ito mababalik!`)) return;
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    const r = await fetch('/sari-pos/index.php?page=customers&action=delete', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      loadCusts();
      showAlert(d.message || `Na-delete si ${name}!`, 'success');
    } else {
      showAlert(d.message || 'Hindi na-delete. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error.', 'danger');
  }
}

function openPay(id, name, bal) {
  document.getElementById('payCustId').value  = id;
  document.getElementById('payName').textContent = `${name} — Utang: ₱${parseFloat(bal).toFixed(2)}`;
  document.getElementById('payAmt').max        = bal;
  document.getElementById('payCsrf').value     = CSRF;
  document.getElementById('payModal').classList.add('show');
}

document.getElementById('payForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagpo-process...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=customers&action=pay', {method:'POST', body:fd});
    const d  = await r.json();
    if (d.success) {
      closeModal('payModal');
      loadCusts();
      showAlert(d.message || 'Na-record ang bayad!', 'success');
    } else {
      showAlert(d.message || 'Hindi na-process. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error.', 'danger');
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-check"></i> Ibayad';
});

async function viewLedger(id, name) {
  document.getElementById('ledgerModal').classList.add('show');
  document.getElementById('ledgerBody').innerHTML = '<div class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></div>';
  try {
    const r = await fetch(`/sari-pos/index.php?page=customers&action=ledger&id=${id}`);
    const d = await r.json();
    document.getElementById('ledgerBody').innerHTML = `
      <h4 style="margin-bottom:12px;font-size:15px">Ledger ni <strong>${esc(name)}</strong></h4>
      <div class="tbl-scroll">
        <table class="tbl">
          <thead><tr><th>Petsa</th><th>Uri</th><th>Halaga</th><th>Balance</th><th>Tala</th></tr></thead>
          <tbody>
            ${d.data?.length
              ? d.data.map(r => `<tr>
                  <td class="text-sm">${new Date(r.created_at).toLocaleString('en-PH')}</td>
                  <td><span class="badge ${r.type==='charge'?'badge-danger':'badge-success'}">${r.type.toUpperCase()}</span></td>
                  <td>₱${parseFloat(r.amount).toFixed(2)}</td>
                  <td><strong>₱${parseFloat(r.balance_after).toFixed(2)}</strong></td>
                  <td>${esc(r.notes || '—')}</td>
                </tr>`).join('')
              : '<tr><td colspan="5" class="tbl-empty">Walang records.</td></tr>'}
          </tbody>
        </table>
      </div>`;
  } catch(e) {
    document.getElementById('ledgerBody').innerHTML = '<p class="text-danger">Error loading ledger.</p>';
  }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

let st;
document.getElementById('custSearch').addEventListener('input', () => {
  clearTimeout(st); st = setTimeout(loadCusts, 300);
});

document.addEventListener('DOMContentLoaded', () => {
  loadCusts();
  if (window.posApp?.pusher) {
    window.posApp.pusher.subscribe('customers').bind('payment-recorded', loadCusts);
  }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>