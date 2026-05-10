<?php
// views/reports/index.php
Auth::requireAdmin();
$pageTitle  = 'Ad-hoc Reports';
$page       = 'reports';
$csrfToken  = Security::csrfToken();
$categories = Database::query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$users      = Database::query("SELECT id, username, full_name FROM users WHERE is_active=1 ORDER BY username")->fetchAll();
require_once __DIR__ . '/../partials/header.php';
?>

<div class="report-layout">
  <!-- Filter Panel -->
  <div class="card report-filters">
    <div class="card-head"><h3><i class="fas fa-sliders-h"></i> Report Parameters</h3></div>
    <div class="card-body">
      <form id="reportForm">
        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
        <div class="form-group">
          <label><i class="fas fa-calendar"></i> Mula sa</label>
          <input type="date" name="date_from" id="rFrom" class="form-input" value="<?= date('Y-m-01') ?>">
        </div>
        <div class="form-group">
          <label><i class="fas fa-calendar"></i> Hanggang</label>
          <input type="date" name="date_to" id="rTo" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label><i class="fas fa-layer-group"></i> I-group By</label>
          <select name="group_by" id="rGroup" class="form-input">
            <option value="day">Araw-araw (Daily)</option>
            <option value="week">Linggu-linggo (Weekly)</option>
            <option value="month">Buwanang (Monthly)</option>
            <option value="product">Per Produkto</option>
            <option value="category">Per Kategorya</option>
            <option value="cashier">Per Cashier</option>
            <option value="payment">Per Payment Method</option>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-tag"></i> Kategorya</label>
          <select name="category_id" class="form-input">
            <option value="0">Lahat ng Kategorya</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= Security::e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-user"></i> Cashier</label>
          <select name="user_id" class="form-input">
            <option value="0">Lahat ng Cashier</option>
            <?php foreach($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= Security::e($u['username']) ?> — <?= Security::e($u['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="preset-btns">
          <p style="font-size:12px;color:#6b7280;margin-bottom:6px">Quick Presets:</p>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('today')">Ngayon</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('week')">Linggong Ito</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('month')">Buwang Ito</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('year')">Taong Ito</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('last7')">Huling 7 Araw</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('last30')">Huling 30 Araw</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="preset('last90')">Huling 90 Araw</button>
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:12px" id="runBtn">
          <i class="fas fa-play"></i> Patakbuhin
        </button>
      </form>
    </div>
  </div>

  <!-- Results Panel -->
  <div class="report-results" id="reportResults">
    <div class="report-placeholder" id="reportPlaceholder">
      <i class="fas fa-chart-bar"></i>
      <h3>Ad-hoc Reporting</h3>
      <p>I-configure ang parameters at i-click ang <strong>Patakbuhin</strong></p>
      <ul>
        <li>Custom date range filter</li>
        <li>Filter by product/category</li>
        <li>Group by day/week/month/product/category/cashier</li>
        <li>Export to CSV</li>
        <li>Profit at expenses summary</li>
      </ul>
    </div>

    <div id="reportKpis" style="display:none;margin-bottom:12px">
      <div class="kpi-row-sm">
        <div class="kpi-sm kpi-green"><p>Net Sales</p><h3 id="rSales">—</h3></div>
        <div class="kpi-sm kpi-blue"><p>Transactions</p><h3 id="rTxn">—</h3></div>
        <div class="kpi-sm kpi-amber"><p>Gross Profit</p><h3 id="rProfit">—</h3></div>
        <div class="kpi-sm kpi-teal"><p>Expenses</p><h3 id="rExp">—</h3></div>
        <div class="kpi-sm kpi-purple"><p>Net Profit</p><h3 id="rNet">—</h3></div>
        <div class="kpi-sm kpi-red"><p>Avg Transaction</p><h3 id="rAvg">—</h3></div>
      </div>
    </div>

    <div class="card" id="reportChartCard" style="display:none;margin-bottom:12px">
      <div class="card-head">
        <h3 id="chartCardTitle"><i class="fas fa-chart-bar"></i> Chart</h3>
        <div style="display:flex;gap:6px">
          <button class="btn btn-sm btn-outline" onclick="exportCSV()"><i class="fas fa-file-csv"></i> CSV</button>
          <button class="btn btn-sm btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
      </div>
      <div class="chart-wrap" style="height:240px">
        <canvas id="reportChart"></canvas>
      </div>
    </div>

    <div class="card" id="reportTableCard" style="display:none">
      <div class="card-head">
        <h3><i class="fas fa-table"></i> Detalyeng Datos</h3>
        <span id="reportMeta" style="font-size:12px;color:#6b7280"></span>
      </div>
      <div class="tbl-scroll">
        <table class="tbl" id="reportTable">
          <thead id="rThead"></thead>
          <tbody id="rTbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Alert -->
<div id="pageAlertWrap" style="position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:9999;min-width:300px"></div>

<!-- Hidden export form -->
<form id="csvExportForm" method="POST" action="/sari-pos/index.php?page=reports&action=export-csv" style="display:none">
  <input type="hidden" name="csrf_token" id="csvCsrf">
  <input type="hidden" name="rows" id="csvRows">
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
let reportData = null, reportChartObj = null;

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
      padding:12px 24px;border-radius:8px;font-size:14px;font-weight:700;
      display:flex;align-items:center;gap:10px;box-shadow:0 4px 16px rgba(0,0,0,.15)">
      <i class="fas ${c.icon}" style="font-size:18px"></i>
      <span style="flex:1">${msg}</span>
      <button onclick="this.parentElement.parentElement.innerHTML=''"
        style="background:none;border:none;cursor:pointer;color:${c.color};font-size:18px;">×</button>
    </div>`;
  setTimeout(() => { wrap.innerHTML = ''; }, 4000);
}

function preset(p) {
  const now = new Date(), fmt = d => d.toISOString().split('T')[0];
  let from, to = fmt(now);
  if      (p==='today')  { from = to; }
  else if (p==='week')   { const d=new Date(now); d.setDate(d.getDate()-d.getDay()+1); from=fmt(d); }
  else if (p==='month')  { from=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-01`; }
  else if (p==='year')   { from=`${now.getFullYear()}-01-01`; }
  else if (p==='last7')  { const d=new Date(now); d.setDate(d.getDate()-7);  from=fmt(d); }
  else if (p==='last30') { const d=new Date(now); d.setDate(d.getDate()-30); from=fmt(d); }
  else if (p==='last90') { const d=new Date(now); d.setDate(d.getDate()-90); from=fmt(d); }
  document.getElementById('rFrom').value = from;
  document.getElementById('rTo').value   = to;
}

document.getElementById('reportForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('runBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
  try {
    const fd = new FormData(this);
    const r  = await fetch('/sari-pos/index.php?page=reports&action=run', {method:'POST', body:fd});
    const d  = await r.json();
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play"></i> Patakbuhin';
    if (!d.success) { showAlert(d.message || 'Error sa pag-generate ng report.', 'danger'); return; }
    reportData = d;
    renderReport(d);
  } catch(err) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play"></i> Patakbuhin';
    showAlert('Network error. I-check ang koneksyon.', 'danger');
  }
});

function renderReport(d) {
  document.getElementById('reportPlaceholder').style.display = 'none';
  const fmt = v => '₱' + parseFloat(v||0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  document.getElementById('rSales').textContent  = fmt(d.summary.net_sales);
  document.getElementById('rTxn').textContent    = d.summary.transactions;
  document.getElementById('rProfit').textContent = fmt(d.profit);
  document.getElementById('rExp').textContent    = fmt(d.expenses);
  document.getElementById('rNet').textContent    = fmt(d.net_profit);
  document.getElementById('rAvg').textContent    = fmt(d.summary.avg_txn);
  document.getElementById('reportKpis').style.display = 'block';

  const g = d.meta.group_by;
  document.getElementById('reportMeta').textContent = `${d.meta.date_from} → ${d.meta.date_to} | Na-generate: ${d.meta.generated_at}`;
  document.getElementById('chartCardTitle').innerHTML = `<i class="fas fa-chart-bar"></i> ${{
    day:'Araw-araw',week:'Linggu-linggo',month:'Buwanang',
    product:'Per Produkto',category:'Per Kategorya',cashier:'Per Cashier',payment:'Per Payment'
  }[g]} na Sales`;

  const colDefs = {
    day:     ['Petsa','Transactions','Units','Gross Sales','Gross Profit','Diskwento','Net Sales'],
    week:    ['Linggo','Simula','Transactions','Units','Net Sales','Profit'],
    month:   ['Buwan','Transactions','Units','Net Sales','Profit'],
    product: ['Produkto','Kategorya','Units','Gross Sales','Profit'],
    category:['Kategorya','Units','Gross Sales','Profit'],
    cashier: ['Cashier','Pangalan','Transactions','Net Sales'],
    payment: ['Paraan ng Bayad','Transactions','Total'],
  };
  const cols = colDefs[g] || colDefs.day;
  document.getElementById('rThead').innerHTML = `<tr>${cols.map(c=>`<th>${c}</th>`).join('')}</tr>`;

  const rowMap = {
    day:     r=>[r.period,r.transactions,r.units_sold,fmt(r.gross_sales),fmt(r.gross_profit),fmt(r.discounts),fmt(r.net_sales)],
    week:    r=>[r.period,r.week_start,r.transactions,r.units_sold,fmt(r.net_sales),fmt(r.gross_profit)],
    month:   r=>[r.period,r.transactions,r.units_sold,fmt(r.net_sales),fmt(r.gross_profit)],
    product: r=>[esc(r.product_name),esc(r.category),r.units_sold,fmt(r.gross_sales),fmt(r.gross_profit)],
    category:r=>[esc(r.category),r.units_sold,fmt(r.gross_sales),fmt(r.gross_profit)],
    cashier: r=>[esc(r.cashier),esc(r.full_name||''),r.transactions,fmt(r.net_sales)],
    payment: r=>[esc(r.payment_method).toUpperCase(),r.transactions,fmt(r.total_amount)],
  };
  const mapper = rowMap[g] || rowMap.day;
  document.getElementById('rTbody').innerHTML = d.rows.length
    ? d.rows.map(r=>`<tr>${mapper(r).map(c=>`<td>${c}</td>`).join('')}</tr>`).join('')
    : `<tr><td colspan="${cols.length}" class="tbl-empty">Walang datos para sa napiling range.</td></tr>`;

  let labels = [], values = [];
  if      (g==='product')  { labels=d.rows.map(r=>r.product_name); values=d.rows.map(r=>parseFloat(r.gross_sales)); }
  else if (g==='category') { labels=d.rows.map(r=>r.category);     values=d.rows.map(r=>parseFloat(r.gross_sales)); }
  else if (g==='cashier')  { labels=d.rows.map(r=>r.cashier);      values=d.rows.map(r=>parseFloat(r.net_sales));   }
  else if (g==='payment')  { labels=d.rows.map(r=>r.payment_method.toUpperCase()); values=d.rows.map(r=>parseFloat(r.total_amount)); }
  else { labels=d.rows.map(r=>r.period||r.week_start); values=d.rows.map(r=>parseFloat(r.net_sales)); }

  const isBar = ['product','category','cashier','payment'].includes(g);
  if (reportChartObj) reportChartObj.destroy();
  reportChartObj = new Chart(document.getElementById('reportChart'), {
    type: isBar ? 'bar' : 'line',
    data: { labels, datasets: [{
      label: 'Sales (₱)', data: values,
      backgroundColor: isBar ? 'rgba(37,99,235,.7)' : 'rgba(37,99,235,.15)',
      borderColor: '#2563eb', borderWidth: 2, tension: .4, fill: !isBar, pointBackgroundColor: '#2563eb'
    }]},
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 }, autoSkip: true, maxRotation: 45 } }
      }
    }
  });

  document.getElementById('reportChartCard').style.display = 'block';
  document.getElementById('reportTableCard').style.display = 'block';
  showAlert('Na-generate ang report!', 'success');
}

async function exportCSV() {
  if (!reportData?.rows?.length) { showAlert('Walang datos para i-export.', 'warning'); return; }
  document.getElementById('csvCsrf').value = '<?= Security::e($csrfToken) ?>';
  document.getElementById('csvRows').value = JSON.stringify(reportData.rows);
  document.getElementById('csvExportForm').submit();
  showAlert('Na-export ang CSV!', 'success');
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>