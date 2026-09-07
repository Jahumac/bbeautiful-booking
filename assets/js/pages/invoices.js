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
                '    <button class="btn btn-sm btn-outline-secondary invoice-pdf" data-id="' + invoice.id + '" title="Download PDF">' +
                '      <i class="fas fa-file-pdf me-1"></i>Download PDF' +
                '    </button> ' +
                '    <button class="btn btn-sm btn-outline-primary invoice-email" data-id="' + invoice.id + '" title="Email invoice to customer">' +
                '      <i class="fas fa-envelope me-1"></i>Email' +
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

    function addItemRow(description, quantity, duration, price) {
        const $row = $(
            '<div class="row g-2 mb-2 invoice-item-row">' +
            '  <div class="col-12 col-md-5">' +
            '    <input type="text" class="form-control item-description" placeholder="Description" value="' + (description || '') + '">' +
            '  </div>' +
            '  <div class="col-4 col-md-2">' +
            '    <input type="number" class="form-control item-quantity" placeholder="Qty" min="1" value="' + (quantity || 1) + '">' +
            '  </div>' +
            '  <div class="col-4 col-md-2">' +
            '    <input type="number" class="form-control item-duration" placeholder="Min" min="0" value="' + (duration || '') + '">' +
            '  </div>' +
            '  <div class="col-4 col-md-2">' +
            '    <input type="number" step="0.01" class="form-control item-price" placeholder="£" min="0" value="' + (price || '') + '">' +
            '  </div>' +
            '  <div class="col-12 col-md-1 d-flex align-items-center">' +
            '    <button type="button" class="btn btn-link text-danger p-0 ms-auto remove-item" title="Remove"><i class="fas fa-times-circle"></i></button>' +
            '  </div>' +
            '</div>'
        );
        $row.find('.remove-item').on('click', () => $row.remove());
        $('#invoice-items').append($row);
    }

    function resetManualInvoiceModal() {
        $('#invoice-customer-name').val('');
        $('#invoice-customer-email').val('');
        $('#invoice-customer-phone').val('');
        $('#invoice-items').empty();
        addItemRow();
        $('#manual-invoice-message').hide();
    }

    function showManualMessage(text, type) {
        $('#manual-invoice-message')
            .text(text)
            .removeClass('alert-success alert-danger d-none')
            .addClass(type === 'error' ? 'alert-danger' : 'alert-success')
            .show();
    }

    function collectManualInvoice() {
        const items = [];

        $('#invoice-items .invoice-item-row').each(function () {
            const $row = $(this);
            const description = $row.find('.item-description').val().trim();
            const quantity = $row.find('.item-quantity').val();
            const duration = $row.find('.item-duration').val();
            const price = $row.find('.item-price').val();

            if (!description) return;

            items.push({
                description: description,
                quantity: quantity ? parseInt(quantity, 10) : 1,
                duration: duration ? parseInt(duration, 10) : null,
                price: price ? parseFloat(price) : 0,
            });
        });

        return items;
    }

    function saveManualInvoice() {
        const customerName = $('#invoice-customer-name').val().trim();
        const customerEmail = $('#invoice-customer-email').val().trim();
        const customerPhone = $('#invoice-customer-phone').val().trim();
        const items = collectManualInvoice();

        if (!customerName) {
            showManualMessage('Please enter the customer name.', 'error');
            return;
        }

        if (!items.length) {
            showManualMessage('Please add at least one line item with a description.', 'error');
            return;
        }

        $.ajax({
            url: App.Utils.Url.siteUrl('invoices/store_manual'),
            method: 'POST',
            data: {
                customer_name: customerName,
                customer_email: customerEmail,
                customer_phone: customerPhone,
                items: JSON.stringify(items),
                csrf_token: vars('csrf_token'),
            },
            dataType: 'json',
        })
            .done((response) => {
                if (response && response.success) {
                    $('#manual-invoice-modal').modal('hide');
                    loadInvoices();
                    window.location.href = App.Utils.Url.siteUrl('invoices/pdf?id=' + response.invoice_id);
                } else {
                    showManualMessage((response && response.message) ? response.message : 'Could not create the invoice.', 'error');
                }
            })
            .fail(() => {
                showManualMessage('Could not create the invoice.', 'error');
            });
    }

    function addEventListeners() {
        // Open the manual invoice modal.
        $('#add-invoice').on('click', () => {
            resetManualInvoiceModal();
            $('#manual-invoice-modal').modal('show');
        });

        // Add a line item row.
        $('#invoice-add-item').on('click', () => {
            addItemRow();
        });

        // Save the manual invoice.
        $('#invoice-save').on('click', saveManualInvoice);

        // Download invoice PDF.
        $('#invoices-table-body').on('click', '.invoice-pdf', function () {
            const id = $(this).data('id');
            // Open the pdf endpoint directly so the browser downloads the file.
            window.location.href = App.Utils.Url.siteUrl('invoices/pdf?id=' + id);
        });

        // Email the invoice PDF to the customer.
        $('#invoices-table-body').on('click', '.invoice-email', function () {
            const id = $(this).data('id');
            const $btn = $(this);
            const original = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Sending');

            $.ajax({
                url: App.Utils.Url.siteUrl('invoices/email'),
                method: 'POST',
                data: { id: id, csrf_token: vars('csrf_token') },
                dataType: 'json',
            })
                .done((response) => {
                    showMessage(response && response.message ? response.message : 'Invoice emailed.', response && response.success ? 'success' : 'error');
                })
                .fail(() => {
                    showMessage('Could not email the invoice.', 'error');
                })
                .always(() => {
                    $btn.prop('disabled', false).html(original);
                });
        });

        // Toggle paid / unpaid.
        $('#invoices-table-body').on('click', '.invoice-toggle', function () {
            const id = $(this).data('id');
            const current = $(this).data('status');
            const next = current === 'paid' ? 'unpaid' : 'paid';

            $.ajax({
                url: App.Utils.Url.siteUrl('invoices/mark_paid'),
                method: 'POST',
                data: { id: id, status: next, csrf_token: vars('csrf_token') },
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
                data: { id: id, csrf_token: vars('csrf_token') },
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
