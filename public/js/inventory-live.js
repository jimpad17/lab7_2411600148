/*
 * JimTrack Fitness - Lab 6 real-time dashboard updates.
 * polls the JSON API every 30 seconds and animates changed KPI values.
 */
(function () {
    'use strict';

    var POLL_MS = 30000;
    var scriptUrl = document.currentScript ? document.currentScript.src : '';
    var basePath = scriptUrl.replace(/\/js\/inventory-live\.js.*$/, '');
    var endpoints = {
        stats: basePath + '/api/dashboard/stats',
        lowStock: basePath + '/api/products/low-stock',
        recent: basePath + '/api/transactions/recent'
    };

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function getJson(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed: ' + response.status);
            }
            return response.json();
        });
    }

    function animateValue(id, value) {
        var element = document.getElementById(id);
        if (!element) {
            return;
        }
        var text = String(value);
        if (element.textContent.trim() === text) {
            return;
        }
        element.textContent = text;
        element.classList.remove('number-flash');
        void element.offsetWidth;
        element.classList.add('number-flash');
    }

    function badgeForStatus(color) {
        if (color === 'danger') { return 'badge-out'; }
        if (color === 'warning') { return 'badge-low'; }
        return 'badge-in';
    }

    function formatTimestamp(iso) {
        try {
            return new Date(iso).toLocaleString('en-US', {
                month: 'short', day: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (error) {
            return 'just now';
        }
    }

    function renderLowStock(items) {
        var container = document.getElementById('live-low-stock-list');
        if (!container) {
            return;
        }

        if (!items.length) {
            container.innerHTML = '<p class="text-muted small mb-0">All products are above reorder level. Nice!</p>';
            return;
        }

        container.innerHTML = items.map(function (item) {
            return '<div class="d-flex justify-content-between align-items-center border-bottom py-2 small">' +
                '<div><div class="fw-bold">' + escapeHtml(item.name) + '</div>' +
                '<div class="text-muted">' + escapeHtml(item.quantity) + ' pcs left · min ' + escapeHtml(item.reorder_level) + '</div></div>' +
                '<span class="badge ' + badgeForStatus(item.status_color) + '">' + escapeHtml(item.status) + '</span>' +
                '</div>';
        }).join('');
    }

    function renderRecentTransactions(items) {
        var tbody = document.getElementById('live-recent-transactions');
        if (!tbody) {
            return;
        }

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No stock movements recorded.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(function (item) {
            var product = item.product || {};
            var negative = Number(item.signed_quantity) < 0;
            return '<tr>' +
                '<td class="ps-3">' + escapeHtml(product.name || 'Deleted product') + '</td>' +
                '<td><span class="badge ' + escapeHtml(item.badge_class) + '">' + escapeHtml(item.type_label) + '</span></td>' +
                '<td class="fw-bold ' + (negative ? 'text-danger' : 'text-success') + '">' +
                    escapeHtml(negative ? '−' + item.quantity : '+' + item.quantity) + '</td>' +
                '<td><code>' + escapeHtml(item.reference_number || '—') + '</code></td>' +
                '<td>' + escapeHtml(item.user || 'System') + '</td>' +
                '<td>' + escapeHtml(item.created_at_display || formatTimestamp(item.created_at)) + '</td>' +
                '</tr>';
        }).join('');
    }

    function refresh() {
        return getJson(endpoints.stats)
            .then(function (payload) {
                var stats = payload.data;
                animateValue('live-total-products', stats.total_products);
                animateValue('live-low-stock', stats.low_stock);
                animateValue('live-out-of-stock', stats.out_of_stock);
                animateValue('live-inventory-value', stats.formatted_inventory_value);

                var lastUpdated = document.getElementById('last-updated');
                if (lastUpdated) {
                    lastUpdated.textContent = formatTimestamp(stats.generated_at);
                }

                return Promise.all([getJson(endpoints.lowStock), getJson(endpoints.recent)]);
            })
            .then(function (results) {
                renderLowStock(results[0].data);
                renderRecentTransactions(results[1].data);
            })
            .catch(function (error) {
                var lastUpdated = document.getElementById('last-updated');
                if (lastUpdated) {
                    lastUpdated.textContent = 'Update failed — retrying in 30s';
                    lastUpdated.className = 'text-danger';
                }
                if (window.console) {
                    console.warn('[JimTrack] Live inventory refresh failed:', error);
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('live-total-products')) {
            return;
        }

        refresh();
        setInterval(refresh, POLL_MS);

        // Refresh immediately when the user returns to the tab.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refresh();
            }
        });
    });
})();
