<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="invoices-page">
    <div class="row" id="invoices">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                Invoices
                <span class="text-muted small fw-normal">— generated invoices for customer visits</span>
            </h4>

            <div class="mb-4">
                <button id="add-invoice" class="btn btn-primary add-record-btn">
                    <i class="fas fa-plus-square me-2"></i>
                    Add invoice
                </button>
            </div>

            <div id="invoices-message" class="alert" style="display:none;"></div>

            <div class="card border">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Number</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th class="text-end">Total</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-table-body">
                                <tr>
                                    <td colspan="6" class="text-muted text-center py-4">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<!-- Manual Invoice Modal -->
<div id="manual-invoice-modal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-light">Add manual invoice</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="manual-invoice-message" class="alert" style="display:none;"></div>

                <div class="mb-3">
                    <label for="invoice-customer-name" class="form-label">Customer name <span class="text-danger">*</span></label>
                    <input type="text" id="invoice-customer-name" class="form-control" maxlength="120">
                </div>
                <div class="mb-3">
                    <label for="invoice-customer-email" class="form-label">Customer email</label>
                    <input type="email" id="invoice-customer-email" class="form-control" maxlength="120">
                </div>
                <div class="mb-3">
                    <label for="invoice-customer-phone" class="form-label">Customer phone</label>
                    <input type="text" id="invoice-customer-phone" class="form-control" maxlength="60">
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-light mb-0">Line items</h5>
                    <button type="button" id="invoice-add-item" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Add item
                    </button>
                </div>

                <div id="invoice-items">
                    <!-- Line item rows injected by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="invoice-save" class="btn btn-primary">
                    <i class="fas fa-check me-2"></i>Create invoice
                </button>
            </div>
        </div>
    </div>
</div>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/pages/invoices.js') ?>"></script>
<?php end_section('scripts'); ?>
