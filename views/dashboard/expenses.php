<?php
// views/dashboard/expenses.php
Auth::requireLogin();
$pageTitle = 'Gastos / Expenses';
$page      = 'expenses';
$csrfToken = Security::csrfToken();
require_once __DIR__ . '/../partials/header.php';
?>

<div class="page-toolbar">
  <input type="date" id="expFrom" class="form-input" style="width:150px" value="<?= date('Y-m-01') ?>">
  <input type="date" id="expTo"   class="form-input" style="width:150px" value="<?= date('Y-m-d') ?>">
  <button class="btn btn-outline" onclick="loadExp()"><i class="fas fa-search"></i></button>
  <button class="btn btn-primary" onclick="openExpModal()"><i class="fas fa-plus"></i> Bagong Gastos</button>
  <span id="expTotal" class="text-danger text-sm" style="margin-left:auto;font-weight:700"></span>
</div>

<div id="pageAlertWrap" style="margin-bottom:8px"></div>

<div class="card">
  <div class="tbl-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Petsa</th>
          <th>Kategorya</th>
          <th>Deskripsyon</th>
          <th>Halaga</th>
          <th>Cashier</th>
          <th>Tala</th>
          <th>Aksyon</th>
        </tr>
      </thead>
      <tbody id="expBody">
        <tr><td colspan="7" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Expense Modal -->
<div class="modal-bg" id="expModal">
  <div class="modal-box">
    <div class="modal-title">
      <span><i class="fas fa-file-invoice-dollar"></i> <span id="expModalTitle">Bagong Gastos</span></span>
      <button class="modal-x" onclick="closeModal('expModal')">×</button>
    </div>
    <form id="expForm">
      <input type="hidden" name="csrf_token" id="exCsrf">
      <input type="hidden" name="id" id="exId" value="0">
      <div class="form-grid-2">
        <div class="form-group">
          <label>Kategorya</label>
          <select name="category" id="exCat" class="form-input">
            <option>Electricity</option>
            <option>Water</option>
            <option>Supplies</option>
            <option>Transportation</option>
            <option>Miscellaneous</option>
            <option>General</option>
          </select>
        </div>
        <div class="form-group">
          <label>Petsa</label>
          <input type="date" name="expense_date" id="exDate" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Deskripsyon *</label>
          <input type="text" name="description" id="exDesc" class="form-input" required maxlength="255">
        </div>
        <div class="form-group">
          <label>Halaga (₱) *</label>
          <input type="number" name="amount" id="exAmt" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label>Tala</label>
          <input type="text" name="notes" id="exNotes" class="form-input" maxlength="255">
        </div>
      </div>
      <div id="exErr" class="alert alert-danger" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('expModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> I-save</button>
      </div>
    </form>
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

async function loadExp() {
  document.getElementById('expBody').innerHTML = '<tr><td colspan="7" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try {
    const p = new URLSearchParams({
      date_from: document.getElementById('expFrom').value,
      date_to:   document.getElementById('expTo').value
    });
    const r = await fetch(`/sari-pos/index.php?page=expenses&action=list&${p}`);
    const d = await r.json();
    const total = (d.data || []).reduce((s, r) => s + parseFloat(r.amount), 0);
    document.getElementById('expTotal').textContent = `Total: ₱${total.toLocaleString('en-PH', {minimumFractionDigits:2})}`;
    document.getElementById('expBody').innerHTML = d.data?.length
      ? d.data.map(r => `<tr>
          <td>${r.expense_date}</td>
          <td><span class="badge badge-blue">${esc(r.category)}</span></td>
          <td><strong>${esc(r.description)}</strong></td>
          <td class="text-danger"><strong>₱${parseFloat(r.amount).toFixed(2)}</strong></td>
          <td>${esc(r.username)}</td>
          <td class="text-muted">${esc(r.notes || '—')}</td>
          <td style="white-space:nowrap">
            <button class="btn btn-xs btn-outline" onclick='editExp(${JSON.stringify(r)})' title="I-edit"><i class="fas fa-edit"></i></button>
            <button class="btn btn-xs btn-danger" onclick="delExp(${r.id},'${esc(r.description)}')" title="I-delete"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="7" class="tbl-empty">Walang gastos sa napiling petsa.</td></tr>';
  } catch(e) {
    document.getElementById('expBody').innerHTML = '<tr><td colspan="7" class="tbl-empty text-danger">Error loading. I-refresh ang page.</td></tr>';
  }
}

function openExpModal(data = null) {
  document.getElementById('expModalTitle').textContent = data ? 'I-edit ang Gastos' : 'Bagong Gastos';
  document.getElementById('exId').value   = data?.id || 0;
  document.getElementById('exCat').value  = data?.category || 'General';
  document.getElementById('exDate').value = data?.expense_date || '<?= date('Y-m-d') ?>';
  document.getElementById('exDesc').value = data?.description || '';
  document.getElementById('exAmt').value  = data?.amount || '';
  document.getElementById('exNotes').value = data?.notes || '';
  document.getElementById('exCsrf').value  = CSRF;
  document.getElementById('exErr').style.display = 'none';
  document.getElementById('expModal').classList.add('show');
}

function editExp(r) { openExpModal(r); }

document.getElementById('expForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=expenses&action=save', {method:'POST', body:fd});
    const d  = await r.json();
    if (d.success) {
      closeModal('expModal');
      loadExp();
      showAlert(d.message || 'Na-save ang gastos!', 'success');
    } else {
      document.getElementById('exErr').textContent   = d.message || 'May error. Subukan ulit.';
      document.getElementById('exErr').style.display = 'block';
    }
  } catch(e) {
    document.getElementById('exErr').textContent   = 'Network error.';
    document.getElementById('exErr').style.display = 'block';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> I-save';
});

async function delExp(id, desc) {
  if (!confirm(`I-delete ang gastos na "${desc}"?\n\nHindi na ito mababalik!`)) return;
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    const r = await fetch('/sari-pos/index.php?page=expenses&action=delete', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      loadExp();
      showAlert(d.message || 'Na-delete ang gastos!', 'success');
    } else {
      showAlert(d.message || 'Hindi na-delete. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error.', 'danger');
  }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', loadExp);
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>