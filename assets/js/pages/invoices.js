/**
 * Invoices page behaviour.
 *
 * Loads the invoice list and provides view / mark-paid / delete actions.
 * Uses the same UI conventions as the rest of the backend.
 */
App.Pages.Invoices = (function () {
    const $message = $('#invoices-message');

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
        return '£' + Number(value || 0).toLocaleString('en-GB', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatDate(value) {
        if (!value) return '—';
        const d = new Date(value.replace(' ', 'T'));
        return d.toLocaleDateString('en-GB');
    }

    function renderInvoices(invoices) {
        const $tbody = $('#invoices-table-body');
        $tbody.empty();

        if (!invoices || !invoices.length) {
            $tbody.html('<tr><td colspan="6" class="text-muted text-center py-4">No invoices yet. Generate one from an appointment in the calendar.</td></tr>');
            return;
        }

        invoices.forEach((invoice) => {
            const status = invoice.status === 'paid' ? 'paid' : 'unpaid';
            const $row = $(
                '<tr>' +
                '  <td class="fw-semibold">' + $('<div>').text(invoice.number).html() + '</td>' +
                '  <td>' + $('<div>').text(invoice.customer_name || '—').html() + '</td>' +
                '  <td>' + formatDate(invoice.appointment_date) + '</td>' +
                '  <td class="text-end fw-semibold">' + money(invoice.total) + '</td>' +
                '  <td><span class="badge ' + (status === 'paid' ? 'text-bg-success' : 'text-bg-danger') + '">' + status + '</span></td>' +
                '  <td class="text-end">' +
                '    <button class="btn btn-sm btn-outline-secondary invoice-view" data-id="' + invoice.id + '" title="View / print">' +
                '      <i class="fas fa-file-invoice me-1"></i>View' +
                '    </button> ' +
                '    <button class="btn btn-sm btn-outline-primary invoice-toggle" data-id="' + invoice.id + '" data-status="' + status + '" title="Toggle paid">' +
                '      <i class="fas fa-check me-1"></i>' + (status === 'paid' ? 'Unmark' : 'Mark paid') +
                '    </button> ' +
                '    <button class="btn btn-sm btn-outline-danger invoice-delete" data-id="' + invoice.id + '" title="Delete">' +
                '      <i class="fas fa-trash-alt"></i>' +
                '    </button>' +
                '  </td>' +
                '</tr>'
            );
            $tbody.append($row);
        });
    }

    function loadInvoices() {
        $.ajax({
            url: App.Utils.Url.siteUrl('invoices/list'),
            method: 'GET',
            dataType: 'json',
        })
            .done((response) => {
                if (response && response.success) {
                    renderInvoices(response.invoices);
                } else {
                    showMessage('Could not load invoices.', 'error');
                }
            })
            .fail(() => {
                showMessage('Could not load invoices.', 'error');
            });
    }

    function addEventListeners() {
        // View / print invoice.
        $('#invoices-table-body').on('click', '.invoice-view', function () {
            const id = $(this).data('id');
            window.open(App.Utils.Url.siteUrl('invoices/view?id=' + id), '_blank');
        });

        // Toggle paid / unpaid.
        $('#invoices-table-body').on('click', '.invoice-toggle', function () {
            const id = $(this).data('id');
            const current = $(this).data('status');
            const next = current === 'paid' ? 'unpaid' : 'paid';

            $.ajax({
                url: App.Utils.Url.siteUrl('invoices/mark_paid'),
                method: 'POST',
                data: { id: id, status: next },
                dataType: 'json',
            })
                .done((response) => {
                    if (response && response.success) {
                        loadInvoices();
                    } else {
                        showMessage('Could not update the invoice.', 'error');
                    }
                })
                .fail(() => {
                    showMessage('Could not update the invoice.', 'error');
                });
        });

        // Delete invoice.
        $('#invoices-table-body').on('click', '.invoice-delete', function () {
            const id = $(this).data('id');

            if (!window.confirm('Delete this invoice?')) {
                return;
            }

            $.ajax({
                url: App.Utils.Url.siteUrl('invoices/destroy'),
                method: 'POST',
                data: { id: id },
                dataType: 'json',
            })
                .done((response) => {
                    if (response && response.success) {
                        loadInvoices();
                    } else {
                        showMessage('Could not delete the invoice.', 'error');
                    }
                })
                .fail(() => {
                    showMessage('Could not delete the invoice.', 'error');
                });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        addEventListeners();
        loadInvoices();
    });

    return {
        loadInvoices,
    };
})();
