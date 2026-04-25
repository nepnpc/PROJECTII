// ── GENERAL UTILITIES ─────────────────────────────────────────

// Auto-dismiss alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });
});

// Confirm before any delete link
document.addEventListener('DOMContentLoaded', function () {
    var deleteLinks = document.querySelectorAll('a[data-confirm]');
    deleteLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});

// ── POWER STOCK MAP ───────────────────────────────────────────
// Built from powerStock injected by orders.php
var powerStockMap = {};
document.addEventListener('DOMContentLoaded', function () {
    if (typeof powerStock === 'undefined') return;
    for (var i = 0; i < powerStock.length; i++) {
        var p = powerStock[i];
        var key = p.lens_id + '|' + parseFloat(p.sph).toFixed(2) + '|' + parseFloat(p.cyl).toFixed(2);
        powerStockMap[key] = parseInt(p.net_qty);
    }
});

function checkStock(row) {
    var hint = row.querySelector('.stock-hint');
    if (!hint) return;
    var lensSelect = row.querySelector('select[name="lens_id[]"]');
    var lid = lensSelect ? lensSelect.value : '';
    if (!lid) { hint.textContent = ''; return; }

    var sph = parseFloat(row.querySelector('input[name="re_sph[]"]').value || 0).toFixed(2);
    var cyl = parseFloat(row.querySelector('input[name="re_cyl[]"]').value || 0).toFixed(2);
    var qty = parseInt(row.querySelector('input[name="qty[]"]').value || 1);
    var key = lid + '|' + sph + '|' + cyl;

    if (powerStockMap.hasOwnProperty(key)) {
        var avail = powerStockMap[key];
        if (avail < qty) {
            hint.textContent = '⚠ Only ' + avail + ' in stock';
            hint.style.color = '#e74c3c';
        } else {
            hint.textContent = '✓ ' + avail + ' available';
            hint.style.color = '#27ae60';
        }
    } else {
        hint.textContent = '⚠ No stock for this power';
        hint.style.color = '#e74c3c';
    }
}

// ── ORDER FORM: DYNAMIC ITEM ROWS ─────────────────────────────
var itemCount = 0;

function addOrderItem() {
    itemCount++;
    var tbody = document.getElementById('order-items-body');
    if (!tbody) return;

    // lensData is injected by staff/orders.php as a JS variable
    var lensOptions = '<option value="">— Select Lens —</option>';
    if (typeof lensData !== 'undefined') {
        for (var i = 0; i < lensData.length; i++) {
            var l = lensData[i];
            lensOptions += '<option value="' + l.id + '" data-price="' + l.wholesale_price + '" data-fitting="' + l.fitting_fee + '">'
                + l.brand + ' ' + l.lens_index + ' (Rs.' + parseFloat(l.wholesale_price).toFixed(2) + ')'
                + '</option>';
        }
    }

    var row = '<tr id="item-row-' + itemCount + '">'
        + '<td><select name="lens_id[]" class="lens-select" onchange="fillPrice(this);checkStock(this.closest(\'tr\'))" required>' + lensOptions + '</select></td>'
        + '<td><input type="number" step="0.01" name="re_sph[]" placeholder="0.00" style="width:70px" oninput="checkStock(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" step="0.01" name="re_cyl[]" placeholder="0.00" style="width:70px" oninput="checkStock(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" step="0.01" name="le_sph[]" placeholder="0.00" style="width:70px"></td>'
        + '<td><input type="number" step="0.01" name="le_cyl[]" placeholder="0.00" style="width:70px"></td>'
        + '<td><input type="number" name="qty[]" value="1" min="1" style="width:60px" oninput="calcTotal();checkStock(this.closest(\'tr\'))">'
        +     '<br><small class="stock-hint" style="font-size:0.72rem;white-space:nowrap;"></small></td>'
        + '<td><input type="number" step="0.01" name="price_at_sale[]" placeholder="0.00" style="width:90px" onchange="calcTotal()"></td>'
        + '<td><input type="number" step="0.01" name="fitting_at_sale[]" placeholder="0.00" style="width:90px" onchange="calcTotal()"></td>'
        + '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(' + itemCount + ')">Remove</button></td>'
        + '</tr>';

    tbody.insertAdjacentHTML('beforeend', row);
    calcTotal();
}

function removeRow(id) {
    var row = document.getElementById('item-row-' + id);
    if (row) row.remove();
    calcTotal();
}

function fillPrice(select) {
    var opt = select.options[select.selectedIndex];
    var price   = opt.getAttribute('data-price')   || '0';
    var fitting = opt.getAttribute('data-fitting') || '0';
    var row = select.closest('tr');
    row.querySelector('input[name="price_at_sale[]"]').value   = parseFloat(price).toFixed(2);
    row.querySelector('input[name="fitting_at_sale[]"]').value = parseFloat(fitting).toFixed(2);
    calcTotal();
}

function calcTotal() {
    var rows   = document.querySelectorAll('#order-items-body tr');
    var total  = 0;
    rows.forEach(function (row) {
        var qty     = parseFloat(row.querySelector('input[name="qty[]"]')?.value || 0);
        var price   = parseFloat(row.querySelector('input[name="price_at_sale[]"]')?.value || 0);
        var fitting = parseFloat(row.querySelector('input[name="fitting_at_sale[]"]')?.value || 0);
        total += qty * (price + fitting);
    });
    var el = document.getElementById('order-total');
    if (el) el.textContent = 'Rs. ' + total.toFixed(2);
}
