<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title   = 'View Inventory';
$current_page = 'inventory';

// Fetch all lenses with current stock for JS
$lenses_raw = $pdo->query("
    SELECT
        l.id, l.brand, l.lens_index, l.material,
        l.wholesale_price, l.mrp, l.fitting_fee,
        COALESCE(SUM(il.change_qty), 0) AS net_stock
    FROM lenses l
    LEFT JOIN inventory_ledger il ON l.id = il.lens_id
    WHERE l.is_active = 1
    GROUP BY l.id, l.brand, l.lens_index, l.material, l.wholesale_price, l.mrp, l.fitting_fee
    ORDER BY l.brand, l.lens_index
")->fetchAll();

// Encode for JS
$lenses_json = json_encode(array_values($lenses_raw));

// Fetch per-power stock (only positive net stock)
$powers_raw = $pdo->query("
    SELECT
        l.id AS lens_id, l.brand, l.lens_index,
        il.sph, il.cyl, il.axis,
        SUM(il.change_qty) AS net_qty
    FROM inventory_ledger il
    JOIN lenses l ON l.id = il.lens_id
    WHERE l.is_active = 1
    GROUP BY il.lens_id, l.brand, l.lens_index, il.sph, il.cyl, il.axis
    HAVING SUM(il.change_qty) > 0
    ORDER BY l.brand, l.lens_index, il.sph, il.cyl
")->fetchAll();

$powers_json = json_encode(array_values($powers_raw));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── SEARCH BAR ───────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>&#128269; Search Lenses</h3></div>
    <div class="card-body">
        <div class="action-bar">
            <input
                type="search"
                id="search-input"
                placeholder="Type brand, index, or material..."
                oninput="doSearch(this.value)"
                autocomplete="off"
            >
            <select id="filter-stock" onchange="doSearch(document.getElementById('search-input').value)">
                <option value="all">All Stock</option>
                <option value="in">In Stock Only</option>
                <option value="low">Low Stock (&lt;5)</option>
                <option value="out">Out of Stock</option>
            </select>
            <span id="result-count" style="color:#888;font-size:0.85rem;white-space:nowrap;"></span>
        </div>
    </div>
</div>

<!-- ── LENS TABLE ───────────────────────────────────────────── -->
<div class="card">
    <div class="card-header"><h3>Lens Catalog & Stock</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Brand</th>
                    <th>Index</th>
                    <th>Material</th>
                    <th>Wholesale Price</th>
                    <th>MRP</th>
                    <th>Fitting Fee</th>
                    <th>Net Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="lens-table-body">
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- ── POWER STOCK SECTION ────────────────────────────────── -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Power Stock Breakdown</h3>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <div class="action-bar" style="margin-bottom:12px;">
            <input
                type="search"
                id="power-search"
                placeholder="Filter by brand, index, SPH, CYL..."
                oninput="doPowerFilter(this.value)"
                autocomplete="off"
            >
            <select id="power-stock-filter" onchange="doPowerFilter(document.getElementById('power-search').value)">
                <option value="all">All In-Stock Powers</option>
                <option value="low">Low Stock (&lt;5)</option>
                <option value="ok">Good Stock (&ge;5)</option>
            </select>
            <span id="power-count" style="color:#888;font-size:0.85rem;white-space:nowrap;"></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Brand</th>
                    <th>Index</th>
                    <th>SPH</th>
                    <th>CYL</th>
                    <th>Axis</th>
                    <th>Qty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="power-table-body">
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- ── INJECT DATA & RUN ALGORITHM ──────────────────────────── -->
<script>
// All lens data from server
var allLenses = <?= $lenses_json ?>;
var allPowers = <?= $powers_json ?>;

// Run LINEAR SEARCH on keypress
function doSearch(query) {
    var stockFilter = document.getElementById('filter-stock').value;

    // Step 1: Linear Search by text query
    var searched = linearSearch(allLenses, query, ['brand', 'lens_index', 'material']);

    // Step 2: Filter by stock level
    var filtered = [];
    for (var i = 0; i < searched.length; i++) {
        var stock = parseInt(searched[i].net_stock);
        if      (stockFilter === 'in'  && stock > 4) filtered.push(searched[i]);
        else if (stockFilter === 'low' && stock > 0 && stock <= 4) filtered.push(searched[i]);
        else if (stockFilter === 'out' && stock <= 0) filtered.push(searched[i]);
        else if (stockFilter === 'all') filtered.push(searched[i]);
    }

    // Step 3: Render results
    renderTable('lens-table-body', filtered, function(item, idx) {
        var stock    = parseInt(item.net_stock);
        var stockCls = stock <= 0 ? 'stock-zero' : (stock < 5 ? 'stock-low' : 'stock-ok');
        var badge    = stock <= 0
            ? '<span class="badge badge-closed">Out of Stock</span>'
            : (stock < 5
                ? '<span class="badge badge-damage">Low Stock</span>'
                : '<span class="badge badge-active">In Stock</span>');

        return '<tr>'
            + '<td>' + (idx + 1) + '</td>'
            + '<td><strong>' + escHtml(item.brand) + '</strong></td>'
            + '<td>' + escHtml(item.lens_index) + '</td>'
            + '<td>' + escHtml(item.material || '—') + '</td>'
            + '<td>Rs. ' + parseFloat(item.wholesale_price).toFixed(2) + '</td>'
            + '<td>Rs. ' + parseFloat(item.mrp).toFixed(2) + '</td>'
            + '<td>Rs. ' + parseFloat(item.fitting_fee).toFixed(2) + '</td>'
            + '<td><span class="' + stockCls + '">' + stock + '</span></td>'
            + '<td>' + badge + '</td>'
            + '</tr>';
    });

    document.getElementById('result-count').textContent =
        filtered.length + ' of ' + allLenses.length + ' lenses';
}

function doPowerFilter(query) {
    var stockFilter = document.getElementById('power-stock-filter').value;
    var q = query.toLowerCase().trim();

    var filtered = [];
    for (var i = 0; i < allPowers.length; i++) {
        var p = allPowers[i];
        var qty = parseInt(p.net_qty);

        // text filter
        if (q !== '') {
            var haystack = (p.brand + ' ' + p.lens_index + ' ' + p.sph + ' ' + p.cyl).toLowerCase();
            if (haystack.indexOf(q) === -1) continue;
        }

        // stock level filter
        if (stockFilter === 'low' && qty >= 5) continue;
        if (stockFilter === 'ok'  && qty < 5)  continue;

        filtered.push(p);
    }

    renderTable('power-table-body', filtered, function(p, idx) {
        var qty = parseInt(p.net_qty);
        var cls = qty < 5 ? 'stock-low' : 'stock-ok';
        var badge = qty < 5
            ? '<span class="badge badge-damage">Low Stock</span>'
            : '<span class="badge badge-active">In Stock</span>';

        return '<tr>'
            + '<td>' + (idx + 1) + '</td>'
            + '<td><strong>' + escHtml(p.brand) + '</strong></td>'
            + '<td>' + escHtml(p.lens_index) + '</td>'
            + '<td>' + parseFloat(p.sph).toFixed(2) + '</td>'
            + '<td>' + parseFloat(p.cyl).toFixed(2) + '</td>'
            + '<td>' + parseInt(p.axis) + '&deg;</td>'
            + '<td><span class="' + cls + '">' + qty + '</span></td>'
            + '<td>' + badge + '</td>'
            + '</tr>';
    });

    document.getElementById('power-count').textContent =
        filtered.length + ' of ' + allPowers.length + ' powers';
}

// Simple HTML escape
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;');
}

document.addEventListener('DOMContentLoaded', function() {
    doSearch('');
    doPowerFilter('');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
