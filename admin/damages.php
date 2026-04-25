<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'Damage Management';
$current_page = 'damages';
$message = $error = '';

$pdo->exec("CREATE TABLE IF NOT EXISTS damages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lens_id     INT NOT NULL,
    sph         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cyl         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    le_sph      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    le_cyl      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    axis        INT DEFAULT 0,
    qty         INT NOT NULL DEFAULT 1,
    eye_side    ENUM('RE','LE','BOTH') NOT NULL DEFAULT 'BOTH',
    damage_type ENUM('BROKEN','SCRATCH','COATING_DEFECT','HANDLING_ERROR','MANUFACTURING_DEFECT','TRANSIT_DAMAGE','OTHER') NOT NULL,
    description TEXT,
    ledger_id   INT,
    order_bill  VARCHAR(50) DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lens_id) REFERENCES lenses(id),
    FOREIGN KEY (ledger_id) REFERENCES inventory_ledger(id)
)");

foreach ([
    "ALTER TABLE damages ADD COLUMN IF NOT EXISTS eye_side ENUM('RE','LE','BOTH') NOT NULL DEFAULT 'BOTH'",
    "ALTER TABLE damages ADD COLUMN IF NOT EXISTS le_sph DECIMAL(5,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE damages ADD COLUMN IF NOT EXISTS le_cyl DECIMAL(5,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE damages ADD COLUMN IF NOT EXISTS order_bill VARCHAR(50) DEFAULT NULL",
] as $mig) {
    try { $pdo->exec($mig); } catch (Exception $e) {}
}

$valid_types = ['BROKEN','SCRATCH','COATING_DEFECT','HANDLING_ERROR','MANUFACTURING_DEFECT','TRANSIT_DAMAGE','OTHER'];
$type_labels = [
    'BROKEN'               => 'Broken',
    'SCRATCH'              => 'Scratch',
    'COATING_DEFECT'       => 'Coating Defect',
    'HANDLING_ERROR'       => 'Handling Error',
    'MANUFACTURING_DEFECT' => 'Manufacturing Defect',
    'TRANSIT_DAMAGE'       => 'Transit Damage',
    'OTHER'                => 'Other',
];

$lenses = $pdo->query(
    "SELECT id, brand, lens_index FROM lenses WHERE is_active = 1 ORDER BY brand, lens_index"
)->fetchAll();

$raw_items = $pdo->query("
    SELECT o.id AS order_id, o.bill_number,
           oi.lens_id, oi.re_sph, oi.re_cyl, oi.le_sph, oi.le_cyl, oi.qty,
           l.brand, l.lens_index
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN lenses l ON l.id = oi.lens_id
    ORDER BY o.created_at DESC
")->fetchAll();

$order_items_map = [];
foreach ($raw_items as $r) {
    $order_items_map[$r['bill_number']][] = [
        'lens_id'    => $r['lens_id'],
        'brand'      => $r['brand'],
        'lens_index' => $r['lens_index'],
        're_sph'     => $r['re_sph'],
        're_cyl'     => $r['re_cyl'],
        'le_sph'     => $r['le_sph'],
        'le_cyl'     => $r['le_cyl'],
        'qty'        => $r['qty'],
    ];
}

// ── HANDLE SUBMISSION ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lens_ids   = (array)($_POST['lens_id']     ?? []);
    $re_sphs    = (array)($_POST['re_sph']      ?? []);
    $re_cyls    = (array)($_POST['re_cyl']      ?? []);
    $le_sphs    = (array)($_POST['le_sph']      ?? []);
    $le_cyls    = (array)($_POST['le_cyl']      ?? []);
    $axes       = (array)($_POST['axis']        ?? []);
    $qtys       = (array)($_POST['qty']         ?? []);
    $eye_sides  = (array)($_POST['eye_side']    ?? []);
    $dtypes     = (array)($_POST['damage_type'] ?? []);
    $descs      = (array)($_POST['description'] ?? []);
    $order_bill = trim($_POST['order_bill'] ?? '');

    $lens_ids = array_filter($lens_ids, fn($v) => (int)$v > 0);

    if (empty($lens_ids)) {
        $error = 'Add at least one damage row.';
    } else {
        $bad = false;
        foreach ($dtypes as $dt) { if (!in_array($dt, $valid_types)) { $bad = true; break; } }
        if ($bad) {
            $error = 'Select damage type for every row.';
        } else {
            $ledger_stmt = $pdo->prepare(
                "INSERT INTO inventory_ledger (lens_id, sph, cyl, axis, change_qty, reason, notes)
                 VALUES (?,?,?,?,?,'DAMAGE',?)"
            );
            $dmg_stmt = $pdo->prepare(
                "INSERT INTO damages (lens_id, sph, cyl, le_sph, le_cyl, axis, qty, eye_side, damage_type, description, ledger_id, order_bill)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );

            $count = 0;
            foreach (array_keys($lens_ids) as $i) {
                $lid      = (int)$lens_ids[$i];
                $re_sph   = (float)($re_sphs[$i] ?? 0);
                $re_cyl   = (float)($re_cyls[$i] ?? 0);
                $le_sph   = (float)($le_sphs[$i] ?? 0);
                $le_cyl   = (float)($le_cyls[$i] ?? 0);
                $axis     = (int)($axes[$i]       ?? 0);
                $qty      = max(1, (int)($qtys[$i] ?? 1));
                $eye_side = in_array($eye_sides[$i] ?? '', ['RE','LE','BOTH'])
                            ? $eye_sides[$i] : 'BOTH';
                $dt       = $dtypes[$i] ?? 'OTHER';
                $desc     = trim($descs[$i] ?? '');
                $base_note = ($type_labels[$dt] ?? $dt)
                           . ($order_bill ? ' [Order: ' . $order_bill . ']' : '')
                           . ($desc ? ': ' . $desc : '');

                if ($eye_side === 'RE' || $eye_side === 'BOTH') {
                    $ledger_stmt->execute([$lid, $re_sph, $re_cyl, $axis, -$qty, $base_note . ' [RE]']);
                    $ledger_id = (int)$pdo->lastInsertId();
                }
                if ($eye_side === 'LE' || $eye_side === 'BOTH') {
                    $ledger_stmt->execute([$lid, $le_sph, $le_cyl, $axis, -$qty, $base_note . ' [LE]']);
                    if ($eye_side === 'LE') $ledger_id = (int)$pdo->lastInsertId();
                }

                $dmg_stmt->execute([
                    $lid, $re_sph, $re_cyl, $le_sph, $le_cyl,
                    $axis, $qty, $eye_side, $dt, $desc, $ledger_id, $order_bill ?: null
                ]);
                $count++;
            }

            $message = $count . ' damage record' . ($count > 1 ? 's' : '') . ' saved. Stock deducted.';
        }
    }
}

// ── ANALYTICS ─────────────────────────────────────────────────
$type_stats = $pdo->query("
    SELECT damage_type, COUNT(*) AS incidents, SUM(qty) AS total_qty
    FROM damages GROUP BY damage_type ORDER BY total_qty DESC
")->fetchAll();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
           COUNT(*) AS incidents, SUM(qty) AS total_qty
    FROM damages GROUP BY month ORDER BY month DESC LIMIT 6
")->fetchAll();

$top_lenses = $pdo->query("
    SELECT l.brand, l.lens_index, COUNT(*) AS incidents, SUM(d.qty) AS total_qty
    FROM damages d JOIN lenses l ON l.id = d.lens_id
    GROUP BY d.lens_id, l.brand, l.lens_index
    ORDER BY total_qty DESC LIMIT 5
")->fetchAll();

$damages = $pdo->query("
    SELECT d.*, l.brand, l.lens_index
    FROM damages d JOIN lenses l ON l.id = d.lens_id
    ORDER BY d.created_at DESC
")->fetchAll();

$damages_json     = json_encode(array_values($damages));
$lenses_json      = json_encode(array_values($lenses));
$order_map_json   = json_encode($order_items_map);
$type_labels_json = json_encode($type_labels);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── TYPE SUMMARY ───────────────────────────────────────── -->
<?php if (!empty($type_stats)): ?>
<div class="damage-summary-grid">
    <?php foreach ($type_stats as $s):
        $cls = 'badge-' . strtolower($s['damage_type']); ?>
    <div class="damage-stat-card">
        <div class="stat-num"><?= $s['total_qty'] ?></div>
        <div><span class="badge <?= $cls ?>"><?= htmlspecialchars($type_labels[$s['damage_type']] ?? $s['damage_type']) ?></span></div>
        <div class="stat-label"><?= $s['incidents'] ?> incident<?= $s['incidents'] != 1 ? 's' : '' ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
<div class="card">
    <div class="card-header"><h3>Most Damaged Lenses</h3></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Lens</th><th>Incidents</th><th>Units Lost</th></tr></thead>
        <tbody>
        <?php if (empty($top_lenses)): ?>
            <tr><td colspan="3" class="empty-state">No records yet.</td></tr>
        <?php else: foreach ($top_lenses as $tl): ?>
            <tr>
                <td><strong><?= htmlspecialchars($tl['brand']) ?></strong> <?= htmlspecialchars($tl['lens_index']) ?></td>
                <td><?= $tl['incidents'] ?></td>
                <td><strong style="color:#e74c3c;"><?= $tl['total_qty'] ?></strong></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
<div class="card">
    <div class="card-header"><h3>Monthly Trend</h3></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Month</th><th>Incidents</th><th>Units Lost</th></tr></thead>
        <tbody>
        <?php if (empty($monthly)): ?>
            <tr><td colspan="3" class="empty-state">No records yet.</td></tr>
        <?php else: foreach ($monthly as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['month']) ?></td>
                <td><?= $m['incidents'] ?></td>
                <td><strong style="color:#e74c3c;"><?= $m['total_qty'] ?></strong></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</div>

<!-- ── RECORD DAMAGE ──────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>&#9888; Record Damage</h3></div>
    <div class="card-body">
        <div style="margin-bottom:18px;">
            <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:4px;">Import from Order Bill #</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" id="bill-lookup" placeholder="e.g. VC-001"
                       list="bill-list" autocomplete="off" style="width:180px;"
                       oninput="onBillInput(this.value)">
                <datalist id="bill-list">
                    <?php foreach (array_keys($order_items_map) as $bn): ?>
                    <option value="<?= htmlspecialchars($bn) ?>">
                    <?php endforeach; ?>
                </datalist>
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="loadFromOrder(document.getElementById('bill-lookup').value)">Load Order</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="clearRows()">Clear</button>
            </div>
            <small id="bill-hint" style="font-size:0.78rem;margin-top:4px;display:block;"></small>
        </div>

        <form method="POST" id="damage-form">
            <input type="hidden" name="order_bill" id="form-order-bill" value="">
            <div class="table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Lens</th>
                            <th>RE SPH</th><th>RE CYL</th>
                            <th>LE SPH</th><th>LE CYL</th>
                            <th>Axis</th><th>Qty</th>
                            <th>Eye Side *</th>
                            <th>Damage Type *</th>
                            <th>Description</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="damage-rows-body"></tbody>
                </table>
            </div>
            <div style="margin-top:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="addDamageRow(null)">&#43; Add Row</button>
            </div>
            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirmSubmit()">&#9888; Submit Damage Report</button>
            </div>
        </form>
    </div>
</div>

<!-- ── FULL HISTORY ───────────────────────────────────────── -->
<div class="card">
    <div class="card-header"><h3>All Damage Records</h3></div>
    <div class="card-body" style="padding-bottom:0;">
        <div class="action-bar" style="margin-bottom:12px;">
            <input type="search" id="dmg-search"
                   placeholder="Search lens, type, order..."
                   oninput="filterDamages(this.value)" autocomplete="off">
            <select id="dmg-type-filter"
                    onchange="filterDamages(document.getElementById('dmg-search').value)">
                <option value="">All Types</option>
                <?php foreach ($type_labels as $val => $label): ?>
                <option value="<?= $val ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select id="dmg-side-filter"
                    onchange="filterDamages(document.getElementById('dmg-search').value)">
                <option value="">All Sides</option>
                <option value="RE">RE Only</option>
                <option value="LE">LE Only</option>
                <option value="BOTH">Full Pair</option>
            </select>
            <span id="dmg-count" style="color:#888;font-size:0.85rem;"></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Order</th><th>Lens</th><th>Eye Side</th><th>RE SPH/CYL</th><th>LE SPH/CYL</th><th>Qty</th><th>Damage Type</th><th>Description</th><th>Date</th></tr>
            </thead>
            <tbody id="damage-table-body"></tbody>
        </table>
    </div>
</div>

<script>
var allDamages     = <?= $damages_json ?>;
var lensData       = <?= $lenses_json ?>;
var orderMap       = <?= $order_map_json ?>;
var typeLabels     = <?= $type_labels_json ?>;
var rowCount = 0;

var eyeSideOptions =
    '<option value="BOTH">Full Pair (RE + LE)</option>'
  + '<option value="RE">RE Only (Right)</option>'
  + '<option value="LE">LE Only (Left)</option>';

var typeOptionsHtml = '<option value="">— Select —</option>';
for (var k in typeLabels) {
    if (typeLabels.hasOwnProperty(k)) {
        typeOptionsHtml += '<option value="' + k + '">' + typeLabels[k] + '</option>';
    }
}

function addDamageRow(prefill) {
    rowCount++;
    var id    = rowCount;
    var lid   = prefill ? prefill.lens_id : '';
    var reSph = prefill ? parseFloat(prefill.re_sph).toFixed(2) : '0.00';
    var reCyl = prefill ? parseFloat(prefill.re_cyl).toFixed(2) : '0.00';
    var leSph = prefill ? parseFloat(prefill.le_sph).toFixed(2) : '0.00';
    var leCyl = prefill ? parseFloat(prefill.le_cyl).toFixed(2) : '0.00';
    var qty   = prefill ? prefill.qty : 1;

    var lensOpts = '<option value="">— Select Lens —</option>';
    for (var i = 0; i < lensData.length; i++) {
        var l = lensData[i];
        var sel = (String(l.id) === String(lid)) ? ' selected' : '';
        lensOpts += '<option value="' + l.id + '"' + sel + '>'
            + escHtml(l.brand) + ' ' + escHtml(l.lens_index) + '</option>';
    }

    var row = '<tr id="drow-' + id + '">'
        + '<td><select name="lens_id[]" required style="min-width:150px;">' + lensOpts + '</select></td>'
        + '<td><input type="number" name="re_sph[]" step="0.25" value="' + reSph + '" id="re-sph-' + id + '" style="width:68px"></td>'
        + '<td><input type="number" name="re_cyl[]" step="0.25" value="' + reCyl + '" id="re-cyl-' + id + '" style="width:68px"></td>'
        + '<td><input type="number" name="le_sph[]" step="0.25" value="' + leSph + '" id="le-sph-' + id + '" style="width:68px"></td>'
        + '<td><input type="number" name="le_cyl[]" step="0.25" value="' + leCyl + '" id="le-cyl-' + id + '" style="width:68px"></td>'
        + '<td><input type="number" name="axis[]" min="0" max="180" value="0" style="width:55px"></td>'
        + '<td><input type="number" name="qty[]" min="1" value="' + qty + '" style="width:55px" required></td>'
        + '<td><select name="eye_side[]" onchange="onEyeSide(this,' + id + ')" style="min-width:130px;">' + eyeSideOptions + '</select></td>'
        + '<td><select name="damage_type[]" required style="min-width:140px;">' + typeOptionsHtml + '</select></td>'
        + '<td><input type="text" name="description[]" placeholder="Optional" style="min-width:140px;"></td>'
        + '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(' + id + ')">&#10005;</button></td>'
        + '</tr>';

    document.getElementById('damage-rows-body').insertAdjacentHTML('beforeend', row);
}

function onEyeSide(select, id) {
    var val   = select.value;
    var reSph = document.getElementById('re-sph-' + id);
    var reCyl = document.getElementById('re-cyl-' + id);
    var leSph = document.getElementById('le-sph-' + id);
    var leCyl = document.getElementById('le-cyl-' + id);
    var reOff = (val === 'LE'), leOff = (val === 'RE');
    reSph.disabled = reOff; reSph.style.opacity = reOff ? '0.35' : '1';
    reCyl.disabled = reOff; reCyl.style.opacity = reOff ? '0.35' : '1';
    leSph.disabled = leOff; leSph.style.opacity = leOff ? '0.35' : '1';
    leCyl.disabled = leOff; leCyl.style.opacity = leOff ? '0.35' : '1';
}

function removeRow(id) {
    var el = document.getElementById('drow-' + id);
    if (el) el.remove();
}

function clearRows() {
    document.getElementById('damage-rows-body').innerHTML = '';
    document.getElementById('bill-lookup').value = '';
    document.getElementById('form-order-bill').value = '';
    document.getElementById('bill-hint').textContent = '';
    rowCount = 0;
}

function onBillInput(val) {
    var hint = document.getElementById('bill-hint');
    if (orderMap.hasOwnProperty(val)) {
        hint.textContent = orderMap[val].length + ' item(s) found — click Load Order';
        hint.style.color = '#27ae60';
    } else if (val.length > 0) {
        hint.textContent = 'Bill number not found';
        hint.style.color = '#e74c3c';
    } else {
        hint.textContent = '';
    }
}

function loadFromOrder(bill) {
    bill = bill.trim();
    var hint = document.getElementById('bill-hint');
    if (!orderMap.hasOwnProperty(bill) || !orderMap[bill].length) {
        hint.textContent = 'Bill number not found.';
        hint.style.color = '#e74c3c';
        return;
    }
    document.getElementById('damage-rows-body').innerHTML = '';
    rowCount = 0;
    document.getElementById('form-order-bill').value = bill;
    var items = orderMap[bill];
    for (var i = 0; i < items.length; i++) addDamageRow(items[i]);
    hint.textContent = items.length + ' row(s) loaded from ' + bill + '. Edit if needed.';
    hint.style.color = '#2980b9';
}

function confirmSubmit() {
    var rows = document.querySelectorAll('#damage-rows-body tr');
    if (rows.length === 0) { alert('Add at least one damage row.'); return false; }
    return true;
}

function filterDamages(query) {
    var typeFilter = document.getElementById('dmg-type-filter').value;
    var sideFilter = document.getElementById('dmg-side-filter').value;
    var q = query.toLowerCase().trim();
    var filtered = [];
    for (var i = 0; i < allDamages.length; i++) {
        var d = allDamages[i];
        if (typeFilter && d.damage_type !== typeFilter) continue;
        if (sideFilter && d.eye_side !== sideFilter) continue;
        if (q) {
            var hay = (d.brand + ' ' + d.lens_index + ' ' + d.damage_type + ' '
                     + (d.description || '') + ' ' + (d.order_bill || '')).toLowerCase();
            if (hay.indexOf(q) === -1) continue;
        }
        filtered.push(d);
    }

    renderTable('damage-table-body', filtered, function(d, idx) {
        var dtCls  = 'badge-' + d.damage_type.toLowerCase();
        var label  = typeLabels[d.damage_type] || d.damage_type;
        var sideBadge = d.eye_side === 'BOTH'
            ? '<span class="badge badge-active">Full Pair</span>'
            : (d.eye_side === 'RE'
                ? '<span class="badge badge-return">RE Only</span>'
                : '<span class="badge badge-adjustment">LE Only</span>');
        var reInfo = parseFloat(d.sph).toFixed(2) + ' / ' + parseFloat(d.cyl).toFixed(2);
        var leInfo = parseFloat(d.le_sph).toFixed(2) + ' / ' + parseFloat(d.le_cyl).toFixed(2);
        return '<tr>'
            + '<td>' + (idx + 1) + '</td>'
            + '<td>' + (d.order_bill ? '<span class="badge badge-draft">' + escHtml(d.order_bill) + '</span>' : '—') + '</td>'
            + '<td><strong>' + escHtml(d.brand) + '</strong> ' + escHtml(d.lens_index) + '</td>'
            + '<td>' + sideBadge + '</td>'
            + '<td>' + (d.eye_side === 'LE' ? '<span style="color:#ccc;">—</span>' : reInfo) + '</td>'
            + '<td>' + (d.eye_side === 'RE' ? '<span style="color:#ccc;">—</span>' : leInfo) + '</td>'
            + '<td><strong style="color:#e74c3c;">-' + d.qty + '</strong></td>'
            + '<td><span class="badge ' + dtCls + '">' + escHtml(label) + '</span></td>'
            + '<td>' + escHtml(d.description || '—') + '</td>'
            + '<td>' + d.created_at.substring(0, 16) + '</td>'
            + '</tr>';
    });

    document.getElementById('dmg-count').textContent =
        filtered.length + ' of ' + allDamages.length + ' records';
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('DOMContentLoaded', function() { filterDamages(''); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
