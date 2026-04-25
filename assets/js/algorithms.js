/**
 * ============================================================
 * OPTICAL LEDGER — ALGORITHMS
 * ============================================================
 *
 * Algorithm 1: LINEAR SEARCH
 *   - Time Complexity:  O(n)
 *   - Space Complexity: O(k) where k = number of matches
 *   - Use: Search lenses/inventory by brand, index, or power
 *
 * Algorithm 2: BUBBLE SORT
 *   - Time Complexity:  O(n²) worst case, O(n) best case (optimized)
 *   - Space Complexity: O(1) in-place
 *   - Use: Sort orders by amount, date, or status
 * ============================================================
 */

// ── ALGORITHM 1: LINEAR SEARCH ────────────────────────────────
/**
 * Searches an array of objects linearly, field by field.
 * Returns all items where any searchField contains the query.
 *
 * @param {Array}  dataArray    - Array of objects to search
 * @param {string} searchQuery  - Search keyword
 * @param {Array}  searchFields - Object keys to search in
 * @returns {Array} Matching items
 */
function linearSearch(dataArray, searchQuery, searchFields) {
    var results = [];
    var query = searchQuery.toLowerCase().trim();

    if (query === '') return dataArray; // empty query returns all

    for (var i = 0; i < dataArray.length; i++) {
        var item = dataArray[i];
        var matched = false;

        for (var j = 0; j < searchFields.length; j++) {
            var fieldValue = String(item[searchFields[j]] || '').toLowerCase();

            if (fieldValue.indexOf(query) !== -1) {
                matched = true;
                break; // found in this item, no need to check more fields
            }
        }

        if (matched) {
            results.push(item);
        }
    }

    return results;
}

// ── ALGORITHM 2: BUBBLE SORT ──────────────────────────────────
/**
 * Sorts an array of objects by a given field using Bubble Sort.
 * Includes early-exit optimization: stops if no swaps in a pass.
 *
 * @param {Array}  dataArray - Array of objects to sort
 * @param {string} sortField - Object key to sort by
 * @param {string} sortOrder - 'asc' or 'desc'
 * @returns {Array} Sorted copy of the array
 */
function bubbleSort(dataArray, sortField, sortOrder) {
    var arr = dataArray.slice(); // work on a copy
    var n = arr.length;
    var swapped;

    for (var i = 0; i < n - 1; i++) {
        swapped = false;

        for (var j = 0; j < n - i - 1; j++) {
            var valA = arr[j][sortField];
            var valB = arr[j + 1][sortField];

            // Numeric comparison if values are numbers
            if (!isNaN(parseFloat(valA)) && !isNaN(parseFloat(valB))) {
                valA = parseFloat(valA);
                valB = parseFloat(valB);
            } else {
                // String comparison (lowercase)
                valA = String(valA).toLowerCase();
                valB = String(valB).toLowerCase();
            }

            var shouldSwap = (sortOrder === 'desc') ? (valA < valB) : (valA > valB);

            if (shouldSwap) {
                // Swap adjacent elements
                var temp = arr[j];
                arr[j]     = arr[j + 1];
                arr[j + 1] = temp;
                swapped    = true;
            }
        }

        // Optimization: if no swap happened, array is already sorted
        if (!swapped) break;
    }

    return arr;
}

// ── RENDER HELPERS ────────────────────────────────────────────

/**
 * Re-renders a table body from a data array.
 * @param {string} tbodyId   - ID of the <tbody> element
 * @param {Array}  data      - Data rows
 * @param {Function} rowFn   - Function(item, index) returning a <tr> HTML string
 */
function renderTable(tbodyId, data, rowFn) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="99" style="text-align:center;padding:30px;color:#999;">No results found.</td></tr>';
        return;
    }

    var html = '';
    for (var i = 0; i < data.length; i++) {
        html += rowFn(data[i], i);
    }
    tbody.innerHTML = html;
}
