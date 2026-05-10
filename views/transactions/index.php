<?php
// views/transactions/index.php
Auth::requireLogin();
$pageTitle = 'Transaction History';
$page      = 'transactions';
$csrfToken = Security::csrfToken();
require_once __DIR__ . '/../partials/header.php';
?>

<div class="page-toolbar">
  <input type="date" id="txFrom" class="form-input" style="width:150px" value="<?= date('Y-m-01') ?>">
  <input type="date" id="txTo"   class="form-input" style="width:150px" value="<?= date('Y-m-d') ?>">
  <select id="txStatus" class="form-input" style="width:160px">
    <option value="">Lahat ng Status</option>
    <option value="completed">Completed</option>
    <option value="pending">Pending</option>
    <option value="void">Void</option>
  </select>
  <button class="btn btn-primary" onclick="loadTxn()"><i class="fas fa-search"></i> Filter</button>
  <span id="txCount" class="text-muted text-sm" style="margin-left:auto"></span>
</div>

<div id="pageAlertWrap"></div>

<div class="card">
  <div class="tbl-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Ref #</th>
          <th>Cashier</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Diskwento</th>
          <th>Bayad</th>
          <th>Status</th>
          <th>Petsa/Oras</th>
          <?php if(Auth::isAdmin()): ?><th>Aksyon</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="txBody">
        <tr><td colspan="10" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Transaction Detail Modal -->
<div class="modal-bg" id="txDetailModal">
  <div class="modal-box modal-lg">
    <div class="modal-title">
      <span><i class="fas fa-receipt"></i> Transaction Detail</span>
      <button class="modal-x" onclick="closeModal('txDetailModal')">×</button>
    </div>
    <div id="txDetailBody" style="padding:16px 20px"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('txDetailModal')">Isara</button>
      <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> I-print</button>
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
      display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <i class="fas ${c.icon}" style="font-size:18px"></i>
      <span style="flex:1">${msg}</span>
      <button onclick="this.parentElement.parentElement.innerHTML=''" 
        style="background:none;border:none;cursor:pointer;color:${c.color};font-size:18px;">×</button>
    </div>`;
  setTimeout(() => { wrap.innerHTML = ''; }, 4000);
}

async function loadTxn() {
  document.getElementById('txBody').innerHTML = '<tr><td colspan="10" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try {
    const p = new URLSearchParams({
      date_from: document.getElementById('txFrom').value,
      date_to:   document.getElementById('txTo').value,
      status:    document.getElementById('txStatus').value,
      limit:     200
    });
    const r = await fetch(`/sari-pos/index.php?page=transactions&action=list&${p}`);
    const d = await r.json();
    document.getElementById('txCount').textContent = `${d.data?.length || 0} transactions`;
    if (!d.data?.length) {
      document.getElementById('txBody').innerHTML = '<tr><td colspan="10" class="tbl-empty">Walang transactions sa napiling petsa.</td></tr>';
      return;
    }
    document.getElementById('txBody').innerHTML = d.data.map(t => `<tr>
      <td><code class="clickable" onclick="viewTxn(${t.id})">${esc(t.reference_no)}</code></td>
      <td>${esc(t.cashier)}</td>
      <td>${esc(t.customer_name || '—')}</td>
      <td>${t.item_count} (${t.total_qty} pcs)</td>
      <td><strong>₱${parseFloat(t.grand_total).toFixed(2)}</strong></td>
      <td class="text-danger">- ₱${parseFloat(t.discount_amount).toFixed(2)}</td>
      <td><span class="badge pay-${esc(t.payment_method)}">${esc(t.payment_method).toUpperCase()}</span></td>
      <td><span class="badge status-${esc(t.status)}">${esc(t.status)}</span></td>
      <td class="text-muted text-sm">${new Date(t.created_at).toLocaleString('en-PH')}</td>
      <?php if(Auth::isAdmin()): ?>
      <td>${t.status === 'completed'
        ? `<button class="btn btn-xs btn-danger" onclick="voidTxn(${t.id},'${esc(t.reference_no)}')"><i class="fas fa-ban"></i> Void</button>`
        : `<span class="text-muted">—</span>`}
      </td>
      <?php endif; ?>
    </tr>`).join('');
  } catch(e) {
    document.getElementById('txBody').innerHTML = '<tr><td colspan="10" class="tbl-empty text-danger">Error loading. I-refresh ang page.</td></tr>';
  }
}

async function viewTxn(id) {
  document.getElementById('txDetailModal').classList.add('show');
  document.getElementById('txDetailBody').innerHTML = '<div class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></div>';
  try {
    const r = await fetch(`/sari-pos/index.php?page=transactions&action=detail&id=${id}`);
    const d = await r.json();
    if (!d.success) {
      document.getElementById('txDetailBody').innerHTML = '<p class="text-danger">Error loading transaction.</p>';
      return;
    }
    const t   = d.transaction;
    const fmt = v => '₱' + parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('txDetailBody').innerHTML = `
      <div class="info-grid" style="margin-bottom:16px">
        <span>Ref #</span><strong>${esc(t.reference_no)}</strong>
        <span>Cashier</span><span>${esc(t.cashier)}</span>
        <span>Customer</span><span>${esc(t.customer_name || '—')}</span>
        <span>Payment</span><span><span class="badge pay-${esc(t.payment_method)}">${esc(t.payment_method).toUpperCase()}</span></span>
        <span>Status</span><span><span class="badge status-${esc(t.status)}">${esc(t.status)}</span></span>
        <span>Petsa</span><span>${new Date(t.created_at).toLocaleString('en-PH')}</span>
        ${t.notes ? `<span>Tala</span><span>${esc(t.notes)}</span>` : ''}
      </div>
      <table class="tbl">
        <thead><tr><th>Produkto</th><th>Presyo</th><th>Qty</th><th>Total</th></tr></thead>
        <tbody>
          ${d.items.map(i => `<tr>
            <td>${esc(i.product_name)}</td>
            <td>₱${parseFloat(i.unit_price).toFixed(2)}</td>
            <td>${i.quantity}</td>
            <td><strong>₱${parseFloat(i.line_total).toFixed(2)}</strong></td>
          </tr>`).join('')}
        </tbody>
      </table>
      <div style="margin-top:14px;text-align:right;font-size:14px">
        <div>Subtotal: <strong>${fmt(t.subtotal || t.grand_total)}</strong></div>
        <div>Diskwento: <span class="text-danger">- ${fmt(t.discount_amount)}</span></div>
        <div style="font-size:17px;font-weight:700;margin-top:4px">Grand Total: ${fmt(t.grand_total)}</div>
        <div class="text-muted">Tendered: ${fmt(t.amount_tendered)} | Sukli: ${fmt(t.change_due)}</div>
      </div>`;
  } catch(e) {
    document.getElementById('txDetailBody').innerHTML = '<p class="text-danger">Network error.</p>';
  }
}

async function voidTxn(id, ref) {
  if (!confirm(`I-void ang transaction ${ref}?\n\nMaibabalik ang stock ng mga produkto.`)) return;
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('transaction_id', id);
    const r = await fetch('/sari-pos/index.php?page=transactions&action=void', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      showAlert(d.message || `Na-void ang transaction ${ref}!`, 'success');
      loadTxn();
    } else {
      showAlert(d.message || 'Hindi na-void. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error. I-check ang koneksyon.', 'danger');
  }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', () => {
  loadTxn();
  if (window.posApp?.pusher) {
    const ch = window.posApp.pusher.subscribe('pos');
    ch.bind('transaction-completed', () => { loadTxn(); showAlert('Bagong transaction na-record!', 'success'); });
    ch.bind('transaction-voided',    () => loadTxn());
  }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>