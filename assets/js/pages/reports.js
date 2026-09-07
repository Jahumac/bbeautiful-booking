/**
 * Reports page behaviour.
 *
 * Loads the date-range aggregation from the Reports controller and renders the
 * summary cards plus the per-treatment and by-month breakdowns. Uses the same
 * UI conventions (Bootstrap cards, fw-light headings) as the rest of the backend.
 */
App.Pages.Reports = (function () {
    const $message = $('#reports-message');

    function showMessage(text, type) {
        $message.text(text)
            .removeClass('alert-success alert-danger d-none')
            .addClass(type === 'error' ? 'alert-danger' : 'alert-success')
            .show();
    }

    function hideMessage() {
        $message.hide();
    }

    function money(value) {
        const num = Number(value || 0);
        return '£' + num.toLocaleString('en-GB', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function renderSummary(data) {
        $('#report-total-appointments').text(data.total_appointments);
        $('#report-total-revenue').text(money(data.total_revenue));
        $('#report-total-cancelled').text(data.total_cancelled);
    }

    function renderPerService(rows) {
        const $container = $('#report-per-service');
        $container.empty();

        if (!rows || !rows.length) {
            $container.html('<div class="text-muted small">No treatments in this range.</div>');
            return;
        }

        rows.forEach((row) => {
            const $item = $(
                '<div class="d-flex justify-content-between align-items-center border-bottom py-2">' +
                '  <div class="me-3">' +
                '    <div class="fw-semibold">' + $('<div>').text(row.name).html() + '</div>' +
                '    <div class="text-muted small">' + row.count + ' appointment' + (row.count === 1 ? '' : 's') + ' &middot; ' + row.duration + ' min</div>' +
                '  </div>' +
                '  <div class="fw-semibold text-nowrap">' + money(row.revenue) + '</div>' +
                '</div>'
            );
            $container.append($item);
        });
    }

    function renderByMonth(rows) {
        const $container = $('#report-by-month');
        $container.empty();

        if (!rows || !rows.length) {
            $container.html('<div class="text-muted small">No appointments in this range.</div>');
            return;
        }

        rows.forEach((row) => {
            const label = row.month + '-01';
            const $item = $(
                '<div class="d-flex justify-content-between align-items-center border-bottom py-2">' +
                '  <div class="me-3">' +
                '    <div class="fw-semibold">' + label + '</div>' +
                '    <div class="text-muted small">' + row.count + ' appointment' + (row.count === 1 ? '' : 's') + '</div>' +
                '  </div>' +
                '  <div class="fw-semibold text-nowrap">' + money(row.revenue) + '</div>' +
                '</div>'
            );
            $container.append($item);
        });
    }

    function loadReport(from, to) {
        $.ajax({
            url: App.Utils.Url.siteUrl('reports/data'),
            method: 'GET',
            data: { from: from, to: to },
            dataType: 'json',
            beforeSend: () => {
                hideMessage();
                $('#reports-filter button[type=submit]').prop('disabled', true);
            },
        })
            .done((response) => {
                if (response && response.success) {
                    renderSummary(response);
                    renderPerService(response.per_service);
                    renderByMonth(response.by_month);
                } else {
                    showMessage((response && response.message) ? response.message : 'Could not load the report.', 'error');
                }
            })
            .fail(() => {
                showMessage('Could not load the report. Please try again.', 'error');
            })
            .always(() => {
                $('#reports-filter button[type=submit]').prop('disabled', false);
            });
    }

    function addEventListeners() {
        $('#reports-filter').on('submit', (event) => {
            event.preventDefault();

            const from = $('#report-from').val();
            const to = $('#report-to').val();

            if (!from || !to) {
                showMessage('Please pick a "from" and "to" date.', 'error');
                return;
            }

            loadReport(from, to);
        });

        // Export the current date range as a CSV download.
        $('#reports-export').on('click', () => {
            const from = $('#report-from').val();
            const to = $('#report-to').val();

            if (!from || !to) {
                showMessage('Please pick a "from" and "to" date before exporting.', 'error');
                return;
            }

            window.location.href = App.Utils.Url.siteUrl(
                'reports/csv?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to),
            );
        });

        // Load the current period by default.
        loadReport($('#report-from').val(), $('#report-to').val());
    }

    document.addEventListener('DOMContentLoaded', addEventListeners);

    return {
        loadReport,
    };
})();
