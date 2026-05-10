<?php
// views/dashboard/users.php
Auth::requireAdmin();
$pageTitle = 'User Management';
$page      = 'users';
$csrfToken = Security::csrfToken();
require_once __DIR__ . '/../partials/header.php';
?>

<div class="page-toolbar">
  <button class="btn btn-primary" onclick="openUserModal()">
    <i class="fas fa-user-plus"></i> Bagong User
  </button>
</div>

<div id="pageAlertWrap" style="margin-bottom:8px"></div>

<div class="card">
  <div class="tbl-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Username</th>
          <th>Pangalan</th>
          <th>Email</th>
          <th>Role</th>
          <th>Last Login</th>
          <th>Status</th>
          <th>Aksyon</th>
        </tr>
      </thead>
      <tbody id="uBody">
        <tr><td colspan="7" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal-bg" id="userModal">
  <div class="modal-box">
    <div class="modal-title">
      <span><i class="fas fa-user-cog"></i> <span id="userModalTitle">Bagong User</span></span>
      <button class="modal-x" onclick="closeModal('userModal')">×</button>
    </div>
    <form id="userForm">
      <input type="hidden" name="csrf_token" id="uCsrf">
      <input type="hidden" name="id" id="uId" value="0">
      <div class="form-grid-2">
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" id="uUser" class="form-input" required maxlength="60">
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role" id="uRole" class="form-input">
            <option value="cashier">Cashier</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Buong Pangalan</label>
          <input type="text" name="full_name" id="uFull" class="form-input" maxlength="120">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Email *</label>
          <input type="email" name="email" id="uEmail" class="form-input" required maxlength="120">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Password <small class="text-muted">(mag-iwan ng blangko para hindi baguhin)</small></label>
          <input type="password" name="password" id="uPass" class="form-input" minlength="8" maxlength="100" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="is_active" id="uActive" class="form-input">
            <option value="1">Aktibo</option>
            <option value="0">Di-aktibo</option>
          </select>
        </div>
      </div>
      <div id="uErr" class="alert alert-danger" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Kanselahin</button>
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

async function loadUsers() {
  try {
    const r = await fetch('/sari-pos/index.php?page=users&action=list');
    const d = await r.json();
    document.getElementById('uBody').innerHTML = d.data?.length
      ? d.data.map(u => `<tr>
          <td><strong>${esc(u.username)}</strong></td>
          <td>${esc(u.full_name || '—')}</td>
          <td>${esc(u.email)}</td>
          <td><span class="badge badge-${u.role === 'admin' ? 'danger' : 'blue'}">${esc(u.role)}</span></td>
          <td class="text-muted text-sm">${u.last_login ? new Date(u.last_login).toLocaleString('en-PH') : '—'}</td>
          <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-warning'}">${u.is_active ? 'Aktibo' : 'Di-aktibo'}</span></td>
          <td style="white-space:nowrap">
            <button class="btn btn-xs btn-outline" onclick='editUser(${JSON.stringify(u)})' title="I-edit"><i class="fas fa-edit"></i></button>
            <button class="btn btn-xs btn-danger" onclick="delUser(${u.id},'${esc(u.username)}')" title="I-delete"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="7" class="tbl-empty">Walang users.</td></tr>';
  } catch(e) {
    document.getElementById('uBody').innerHTML = '<tr><td colspan="7" class="tbl-empty text-danger">Error loading. I-refresh ang page.</td></tr>';
  }
}

function openUserModal(data = null) {
  document.getElementById('userModalTitle').textContent = data ? 'I-edit ang User' : 'Bagong User';
  document.getElementById('uId').value     = data?.id || 0;
  document.getElementById('uUser').value   = data?.username || '';
  document.getElementById('uFull').value   = data?.full_name || '';
  document.getElementById('uEmail').value  = data?.email || '';
  document.getElementById('uRole').value   = data?.role || 'cashier';
  document.getElementById('uActive').value = data?.is_active ?? 1;
  document.getElementById('uPass').value   = '';
  document.getElementById('uErr').style.display = 'none';
  document.getElementById('uCsrf').value   = CSRF;
  document.getElementById('userModal').classList.add('show');
}

function editUser(u) { openUserModal(u); }

document.getElementById('userForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=users&action=save', {method:'POST', body:fd});
    const d  = await r.json();
    if (d.success) {
      closeModal('userModal');
      loadUsers();
      showAlert(d.message || 'Na-save ang user!', 'success');
    } else {
      document.getElementById('uErr').textContent   = d.message || 'May error. Subukan ulit.';
      document.getElementById('uErr').style.display = 'block';
    }
  } catch(e) {
    document.getElementById('uErr').textContent   = 'Network error.';
    document.getElementById('uErr').style.display = 'block';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> I-save';
});

async function delUser(id, uname) {
  if (!confirm(`I-delete si "${uname}"?\n\nHindi na ito mababalik!`)) return;
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    const r = await fetch('/sari-pos/index.php?page=users&action=delete', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
      loadUsers();
      showAlert(d.message || `Na-delete si ${uname}!`, 'success');
    } else {
      showAlert(d.message || 'Hindi na-delete. Subukan ulit.', 'danger');
    }
  } catch(e) {
    showAlert('Network error.', 'danger');
  }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', loadUsers);
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>