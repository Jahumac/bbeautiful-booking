<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="invoices-page">
    <div class="row" id="invoices">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                Invoices
                <span class="text-muted small fw-normal">— generated invoices for customer visits</span>
            </h4>

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

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/pages/invoices.js') ?>"></script>
<?php end_section('scripts'); ?>
