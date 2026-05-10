<?php
// views/dashboard/index.php
Auth::requireLogin();
$pageTitle  = 'Dashboard';
$page       = 'dashboard';
$csrfToken  = Security::csrfToken();
require_once __DIR__ . '/../partials/header.php';
?>

<!-- KPI Cards -->
<div class="kpi-row" id="kpiRow">
  <div class="kpi-card kpi-green">
    <div class="kpi-icon"><i class="fas fa-peso-sign"></i></div>
    <div class="kpi-body"><p>Sales Ngayon</p><h2 id="kSales">Loading...</h2><small id="kSalesSub"></small></div>
  </div>
  <div class="kpi-card kpi-blue">
    <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
    <div class="kpi-body"><p>Transactions</p><h2 id="kTxn">—</h2><small id="kAvgTxn"></small></div>
  </div>
  <div class="kpi-card kpi-amber">
    <div class="kpi-icon"><i class="fas fa-coins"></i></div>
    <div class="kpi-body"><p>Gross Profit</p><h2 id="kProfit">—</h2><small id="kProfitSub"></small></div>
  </div>
  <div class="kpi-card kpi-purple">
    <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
    <div class="kpi-body"><p>Cash on Hand</p><h2 id="kCash">—</h2><small>Change fund</small></div>
  </div>
  <div class="kpi-card kpi-teal">
    <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
    <div class="kpi-body"><p>Sales Buwang Ito</p><h2 id="kMonth">—</h2><small id="kMonthSub"></small></div>
  </div>
  <div class="kpi-card kpi-red">
    <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="kpi-body"><p>Low Stock / Expiry</p><h2 id="kAlerts">—</h2><small>Items needing attention</small></div>
  </div>
  <div class="kpi-card kpi-orange">
    <div class="kpi-icon"><i class="fas fa-hand-holding-usd"></i></div>
    <div class="kpi-body"><p>Total Utang</p><h2 id="kUtang">—</h2><small>Customer receivables</small></div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions-grid" style="margin-bottom:16px">
  <a href="/sari-pos/index.php?page=pos" class="qa-btn qa-primary">
    <i class="fas fa-cash-register"></i><span>Bagong Transaction</span>
  </a>
  <button class="qa-btn qa-green" onclick="openModal('restockModal')">
    <i class="fas fa-box-open"></i><span>Dagdag Stock</span>
  </button>
  <button class="qa-btn qa-amber" onclick="openModal('expenseModal')">
    <i class="fas fa-file-invoice-dollar"></i><span>I-record Gastos</span>
  </button>
  <button class="qa-btn qa-teal" onclick="openModal('cashFundModal')">
    <i class="fas fa-piggy-bank"></i><span>Cash Fund</span>
  </button>
  <button class="qa-btn qa-purple" onclick="openZReading()">
    <i class="fas fa-print"></i><span>Z-Reading</span>
  </button>
</div>

<!-- Charts Row -->
<div class="grid-2col" style="margin-bottom:16px">
  <div class="card">
    <div class="card-head">
      <h3><i class="fas fa-chart-bar"></i> Sales — 7 Araw</h3>
      <span class="badge-live"><i class="fas fa-circle"></i> Live</span>
    </div>
    <div class="chart-wrap" style="height:200px">
      <canvas id="weekChart"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-head">
      <h3><i class="fas fa-chart-pie"></i> Payment Breakdown Ngayon</h3>
    </div>
    <div style="display:flex;align-items:center;gap:16px">
      <div class="chart-wrap" style="height:160px;width:160px;flex-shrink:0">
        <canvas id="payChart"></canvas>
      </div>
      <div id="payLegend" style="flex:1;font-size:13px"></div>
    </div>
  </div>
</div>

<!-- Bottom Row -->
<div class="grid-2col" style="margin-bottom:16px">
  <div class="card">
    <div class="card-head">
      <h3><i class="fas fa-trophy"></i> Top Selling Ngayon</h3>
    </div>
    <table class="tbl">
      <thead><tr><th>#</th><th>Produkto</th><th>Qty</th><th>Sales</th></tr></thead>
      <tbody id="topProductsBody"><tr><td colspan="4" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
  <div class="card">
    <div class="card-head"><h3><i class="fas fa-bell"></i> Mga Babala</h3></div>
    <div class="section-label mt-0">⚠ Mababang Stock</div>
    <div id="lowStockList"></div>
    <div class="section-label">🗓 Malapit Mag-expire</div>
    <div id="expiryList"></div>
  </div>
</div>

<!-- Recent Transactions -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h3><i class="fas fa-receipt"></i> Pinakabagong Transactions</h3>
    <a href="/sari-pos/index.php?page=transactions" class="btn btn-sm btn-outline">Tingnan Lahat</a>
  </div>
  <div class="tbl-scroll">
    <table class="tbl">
      <thead><tr><th>Ref #</th><th>Cashier</th><th>Items</th><th>Total</th><th>Bayad</th><th>Status</th><th>Oras</th></tr></thead>
      <tbody id="recentTxnBody"><tr><td colspan="7" class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>

<!-- Hourly Sales -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head"><h3><i class="fas fa-clock"></i> Hourly Sales Ngayon</h3></div>
  <div class="chart-wrap" style="height:160px">
    <canvas id="hourChart"></canvas>
  </div>
</div>

<!-- Restock Modal -->
<div class="modal-bg" id="restockModal">
  <div class="modal-box">
    <div class="modal-title"><span><i class="fas fa-box-open"></i> Dagdag Stock</span><button class="modal-x" onclick="closeModal('restockModal')">×</button></div>
    <form id="restockForm">
      <input type="hidden" name="csrf_token" id="rsCsrf">
      <div class="form-group"><label>Produkto</label>
        <select name="product_id" id="rsProduct" class="form-input"><option value="">Piliin...</option></select>
      </div>
      <div class="form-group"><label>Dami (+)</label><input type="number" name="qty" class="form-input" min="1" value="1" required></div>
      <div class="form-group"><label>Tala</label><input type="text" name="note" class="form-input" maxlength="200"></div>
      <div id="rsMsg" style="display:none;padding:8px 12px;border-radius:6px;font-weight:600;margin-bottom:8px"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('restockModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> I-save</button>
      </div>
    </form>
  </div>
</div>

<!-- Expense Modal -->
<div class="modal-bg" id="expenseModal">
  <div class="modal-box">
    <div class="modal-title"><span><i class="fas fa-file-invoice-dollar"></i> I-record ang Gastos</span><button class="modal-x" onclick="closeModal('expenseModal')">×</button></div>
    <form id="expenseForm">
      <input type="hidden" name="csrf_token" id="expCsrf">
      <input type="hidden" name="id" value="0">
      <div class="form-grid-2">
        <div class="form-group"><label>Kategorya</label>
          <select name="category" class="form-input">
            <option>Electricity</option><option>Water</option><option>Supplies</option>
            <option>Transportation</option><option>Miscellaneous</option><option>General</option>
          </select>
        </div>
        <div class="form-group"><label>Petsa</label><input type="date" name="expense_date" class="form-input" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <div class="form-group"><label>Deskripsyon *</label><input type="text" name="description" class="form-input" required maxlength="255"></div>
      <div class="form-group"><label>Halaga (₱) *</label><input type="number" name="amount" class="form-input" step="0.01" min="0.01" required></div>
      <div class="form-group"><label>Tala</label><input type="text" name="notes" class="form-input" maxlength="255"></div>
      <div id="expMsg" style="display:none;padding:8px 12px;border-radius:6px;font-weight:600;margin-bottom:8px"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('expenseModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> I-save</button>
      </div>
    </form>
  </div>
</div>

<!-- Cash Fund Modal -->
<div class="modal-bg" id="cashFundModal">
  <div class="modal-box modal-sm">
    <div class="modal-title"><span><i class="fas fa-piggy-bank"></i> Cash Fund</span><button class="modal-x" onclick="closeModal('cashFundModal')">×</button></div>
    <div class="info-box" id="cashFundBalance">Loading...</div>
    <form id="cashFundForm">
      <input type="hidden" name="csrf_token" id="cfCsrf">
      <div class="form-group"><label>Uri ng Aksyon</label>
        <select name="type" class="form-input">
          <option value="add">Dagdag (Add)</option>
          <option value="remove">Bawas (Remove)</option>
          <option value="open">Opening Fund</option>
        </select>
      </div>
      <div class="form-group"><label>Halaga (₱)</label><input type="number" name="amount" class="form-input" step="0.01" min="0.01" required></div>
      <div class="form-group"><label>Tala</label><input type="text" name="notes" class="form-input" maxlength="200"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('cashFundModal')">Kanselahin</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> I-save</button>
      </div>
    </form>
  </div>
</div>

<!-- Z-Reading Modal -->
<div class="modal-bg" id="zReadModal">
  <div class="modal-box modal-lg">
    <div class="modal-title"><span><i class="fas fa-print"></i> Z-Reading — End of Day</span><button class="modal-x" onclick="closeModal('zReadModal')">×</button></div>
    <div id="zReadBody" style="padding:16px 20px"><div class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></div></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> I-print</button>
      <button class="btn btn-outline" onclick="closeModal('zReadModal')">Isara</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const CSRF = <?= json_encode($csrfToken) ?>;
let weekChartObj = null, payChartObj = null, hourChartObj = null;

function showModalMsg(id, msg, type = 'success') {
  const el = document.getElementById(id);
  el.style.display = 'block';
  el.style.background = type === 'success' ? '#dcfce7' : '#fee2e2';
  el.style.color      = type === 'success' ? '#15803d' : '#dc2626';
  el.style.border     = type === 'success' ? '1px solid #86efac' : '1px solid #fca5a5';
  el.textContent = msg;
  setTimeout(() => { el.style.display = 'none'; }, 3000);
}

async function loadDashboard() {
  try {
    const r = await fetch('/sari-pos/index.php?page=reports&action=dashboard');
    const d = await r.json();
    if (!d.success) return;

    const fmt = v => '₱' + parseFloat(v||0).toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('kSales').textContent   = fmt(d.today.net_sales);
    document.getElementById('kSalesSub').textContent = \`Avg: ${fmt(d.today.avg_txn)} / txn\`;
    document.getElementById('kTxn').textContent     = d.today.txn_count;
    document.getElementById('kAvgTxn').textContent  = \`Avg: ${fmt(d.today.avg_txn)}\`;
    document.getElementById('kProfit').textContent  = fmt(d.profit_today);
    document.getElementById('kProfitSub').textContent = d.today.net_sales > 0 ? \`${(d.profit_today/d.today.net_sales*100).toFixed(1)}% margin\` : '';
    document.getElementById('kCash').textContent    = fmt(d.cash_fund||0);
    document.getElementById('kMonth').textContent   = fmt(d.month.net_sales);
    document.getElementById('kAlerts').textContent  = parseInt(d.low_stock)+parseInt(d.near_expiry);
    document.getElementById('kUtang').textContent   = fmt(d.total_utang);

    // Weekly chart
    if (weekChartObj) weekChartObj.destroy();
    weekChartObj = new Chart(document.getElementById('weekChart'), {
      type: 'bar',
      data: { labels: d.week_sales.map(x=>x.day.slice(5)), datasets: [{
        label: 'Sales (₱)', data: d.week_sales.map(x=>parseFloat(x.sales)),
        backgroundColor: 'rgba(37,99,235,0.75)', borderRadius: 5
      }]},
      options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
        scales:{ y:{ticks:{callback:v=>'₱'+v.toLocaleString(),font:{size:11}}}, x:{grid:{display:false},ticks:{font:{size:11}}} }}
    });

    // Payment chart
    const payColors = {cash:'#2563eb',gcash:'#16a34a',maya:'#d97706',card:'#7c3aed',utang:'#dc2626'};
    const payBg = d.payments.map(x=>payColors[x.payment_method]||'#6b7280');
    if (payChartObj) payChartObj.destroy();
    payChartObj = new Chart(document.getElementById('payChart'), {
      type: 'doughnut',
      data: { labels: d.payments.map(x=>x.payment_method.toUpperCase()),
              datasets: [{data:d.payments.map(x=>parseFloat(x.total)), backgroundColor:payBg, borderWidth:2}]},
      options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{display:false}} }
    });
    const total = d.payments.reduce((a,x)=>a+parseFloat(x.total),0)||1;
    document.getElementById('payLegend').innerHTML = d.payments.map((p,i)=>
      \`<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
        <span style="width:12px;height:12px;border-radius:2px;background:${payBg[i]};flex-shrink:0"></span>
        <span>${p.payment_method.toUpperCase()}</span>
        <span style="margin-left:auto;font-weight:600">₱${parseFloat(p.total).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
        <span style="color:#6b7280;font-size:12px">${(p.total/total*100).toFixed(1)}%</span>
      </div>\`).join('');

    // Hourly chart
    const hrs = Array.from({length:24},(_,i)=>i);
    const hourVals = hrs.map(h=>{ const rx=d.hourly.find(x=>parseInt(x.hr)===h); return rx?parseFloat(rx.sales):0; });
    if (hourChartObj) hourChartObj.destroy();
    hourChartObj = new Chart(document.getElementById('hourChart'), {
      type: 'bar',
      data: { labels: hrs.map(h=>h===0?'12am':h<12?\`${h}am\`:h===12?'12pm':\`${h-12}pm\`),
              datasets: [{label:'Sales',data:hourVals,backgroundColor:'rgba(6,182,212,0.7)',borderRadius:3}]},
      options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
        scales:{ y:{ticks:{callback:v=>'₱'+v.toLocaleString(),font:{size:10}}}, x:{grid:{display:false},ticks:{font:{size:10},autoSkip:true,maxRotation:0}} }}
    });

    // Top products
    document.getElementById('topProductsBody').innerHTML = d.top_products.length
      ? d.top_products.map((p,i)=>\`<tr>
          <td><span class="rank-badge rank-${i+1}">${i+1}</span></td>
          <td>${esc(p.product_name)}</td><td>${p.qty}</td>
          <td><strong>₱${parseFloat(p.sales).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong></td>
        </tr>\`).join('')
      : '<tr><td colspan="4" class="tbl-empty">Wala pang benta ngayon.</td></tr>';

    // Alerts
    document.getElementById('lowStockList').innerHTML = d.low_stock_items.length
      ? d.low_stock_items.map(p=>\`<div class="alert-item ${p.stock_qty==0?'alert-danger':'alert-warning'}">
          <i class="fas fa-box"></i><span>${esc(p.name)}</span>
          <span class="alert-badge">${p.stock_qty} na lang</span></div>\`).join('')
      : '<div class="text-muted text-sm pl-2">✓ Lahat ay may sapat na stock</div>';

    document.getElementById('expiryList').innerHTML = d.expiry_items.length
      ? d.expiry_items.map(p=>\`<div class="alert-item ${p.days_left<=3?'alert-danger':'alert-warning'}">
          <i class="fas fa-calendar-times"></i><span>${esc(p.name)}</span>
          <span class="alert-badge">${p.days_left} days</span></div>\`).join('')
      : '<div class="text-muted text-sm pl-2">✓ Walang malapit mag-expire</div>';

  } catch(e) { console.error('Dashboard error:', e); }
}

async function loadRecentTransactions() {
  try {
    const today = new Date().toISOString().split('T')[0];
    const r = await fetch(\`/sari-pos/index.php?page=transactions&action=list&date_from=${today}&date_to=${today}&limit=10\`);
    const d = await r.json();
    document.getElementById('recentTxnBody').innerHTML = d.data?.length
      ? d.data.map(t=>\`<tr>
          <td><code>${esc(t.reference_no)}</code></td>
          <td>${esc(t.cashier)}</td><td>${t.item_count}</td>
          <td><strong>₱${parseFloat(t.grand_total).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong></td>
          <td><span class="badge pay-${esc(t.payment_method)}">${esc(t.payment_method).toUpperCase()}</span></td>
          <td><span class="badge status-${esc(t.status)}">${esc(t.status)}</span></td>
          <td class="text-muted">${new Date(t.created_at).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'})}</td>
        </tr>\`).join('')
      : '<tr><td colspan="7" class="tbl-empty">Wala pang transactions ngayon.</td></tr>';
  } catch(e) { console.error(e); }
}

// Restock
document.getElementById('restockForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]'); btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  const fd = new FormData(this);
  const r = await fetch('/sari-pos/index.php?page=products&action=restock', {method:'POST',body:fd});
  const d = await r.json();
  showModalMsg('rsMsg', d.message, d.success?'success':'danger');
  if (d.success) { this.reset(); loadDashboard(); }
  btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> I-save';
});

// Expense
document.getElementById('expenseForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]'); btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Nagse-save...';
  const fd = new FormData(this);
  const r = await fetch('/sari-pos/index.php?page=expenses&action=save', {method:'POST',body:fd});
  const d = await r.json();
  showModalMsg('expMsg', d.message, d.success?'success':'danger');
  if (d.success) { this.reset(); }
  btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> I-save';
});

// Cash fund
document.getElementById('cashFundForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const r = await fetch('/sari-pos/index.php?page=cashfund&action=save', {method:'POST',body:fd});
  const d = await r.json();
  showToast(d.message, d.success?'success':'danger');
  if (d.success) { closeModal('cashFundModal'); loadDashboard(); }
});

async function openModal(id) {
  document.getElementById(id).classList.add('show');
  if (id === 'restockModal') {
    document.getElementById('rsCsrf').value = CSRF;
    const r = await fetch('/sari-pos/index.php?page=products&action=list');
    const d = await r.json();
    document.getElementById('rsProduct').innerHTML = '<option value="">Piliin...</option>' +
      (d.data||[]).map(p=>\`<option value="${p.id}">${esc(p.name)} (${p.stock_qty} na)</option>\`).join('');
  }
  if (id === 'cashFundModal') {
    document.getElementById('cfCsrf').value = CSRF;
    const r = await fetch('/sari-pos/index.php?page=cashfund&action=get');
    const d = await r.json();
    document.getElementById('cashFundBalance').textContent = \`Kasalukuyang balance: ₱${parseFloat(d.data?.balance||0).toFixed(2)}\`;
  }
  if (id === 'expenseModal') document.getElementById('expCsrf').value = CSRF;
}

async function openZReading() {
  document.getElementById('zReadModal').classList.add('show');
  document.getElementById('zReadBody').innerHTML = '<div class="tbl-loading"><i class="fas fa-spinner fa-spin"></i></div>';
  try {
    const today = new Date().toISOString().split('T')[0];
    const r = await fetch(\`/sari-pos/index.php?page=reports&action=zreading&date=${today}\`);
    const d = await r.json();
    if (!d.success) { document.getElementById('zReadBody').innerHTML='<p class="text-danger">Error loading Z-Reading.</p>'; return; }
    const s = d.summary;
    const fmt = v => '₱'+parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2});
    document.getElementById('zReadBody').innerHTML = \`
      <div class="zread-receipt">
        <div class="zread-header"><?= Security::e(EnvLoader::get('APP_NAME','Sari-POS')) ?><br><small>${d.date}</small></div>
        <div class="zread-row"><span>Gross Sales</span><span>${fmt(s.gross_sales)}</span></div>
        <div class="zread-row"><span>Diskwento</span><span class="text-danger">- ${fmt(s.total_discounts)}</span></div>
        <div class="zread-row"><span>Void Transactions</span><span class="text-danger">${s.void_transactions}</span></div>
        <div class="zread-divider"></div>
        <div class="zread-row zread-total"><span>NET SALES</span><span>${fmt(s.net_sales)}</span></div>
        <div class="zread-divider"></div>
        <div class="zread-row"><span>Cash</span><span>${fmt(s.cash_sales)}</span></div>
        <div class="zread-row"><span>GCash</span><span>${fmt(s.gcash_sales)}</span></div>
        <div class="zread-row"><span>Utang</span><span>${fmt(s.utang_sales)}</span></div>
        <div class="zread-divider"></div>
        <div class="zread-row"><span>Gross Profit</span><span class="text-success">${fmt(d.profit)}</span></div>
        <div class="zread-row"><span>Expenses</span><span class="text-danger">- ${fmt(d.expenses)}</span></div>
        <div class="zread-row zread-total"><span>NET PROFIT</span><span class="text-success">${fmt(d.net_profit)}</span></div>
        <div class="zread-divider"></div>
        <div class="zread-row"><span>Transactions</span><span>${s.total_transactions}</span></div>
        <div class="zread-row"><span>Avg Transaction</span><span>${fmt(s.avg_transaction)}</span></div>
        <div class="zread-sub">*** KATAPUSAN NG Z-READING ***</div>
      </div>
      <h4 style="margin:12px 0 6px">Top 10 Items</h4>
      <table class="tbl"><thead><tr><th>#</th><th>Produkto</th><th>Qty</th><th>Sales</th></tr></thead>
      <tbody>${d.top_items.map((x,i)=>\`<tr><td>${i+1}</td><td>${esc(x.product_name)}</td><td>${x.qty}</td><td>₱${parseFloat(x.sales).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>\`).join('')}</tbody></table>\`;
  } catch(e) { document.getElementById('zReadBody').innerHTML='<p class="text-danger">Network error.</p>'; }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', () => {
  loadDashboard();
  loadRecentTransactions();
  if (window.posApp?.pusher) {
    const ch = window.posApp.pusher.subscribe('dashboard');
    ch.bind('sales-updated', () => { loadDashboard(); loadRecentTransactions(); });
    ch.bind('cash-updated',  () => loadDashboard());
    window.posApp.pusher.subscribe('inventory').bind('stock-updated', () => loadDashboard());
  }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>