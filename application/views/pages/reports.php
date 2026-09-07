<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="reports-page">
    <div class="row" id="reports">
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-light">
                Reports
                <span class="text-muted small fw-normal">— appointments &amp; revenue over a date range</span>
            </h4>

            <form id="reports-filter" class="mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-sm-4 col-md-3">
                        <label for="report-from" class="form-label">From</label>
                        <input type="date" id="report-from" class="form-control" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-12 col-sm-4 col-md-3">
                        <label for="report-to" class="form-label">To</label>
                        <input type="date" id="report-to" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-sm-4 col-md-3">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-chart-line me-2"></i>
                            Generate report
                        </button>
                    </div>
                    <div class="col-12 col-sm-4 col-md-3">
                        <button class="btn btn-outline-secondary" type="button" id="reports-export">
                            <i class="fas fa-file-csv me-2"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
            </form>

            <div id="reports-message" class="alert" style="display:none;"></div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card border">
                        <div class="card-body py-3">
                            <div class="text-muted small">Appointments</div>
                            <div class="fs-4 fw-light" id="report-total-appointments">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border">
                        <div class="card-body py-3">
                            <div class="text-muted small">Revenue</div>
                            <div class="fs-4 fw-light" id="report-total-revenue">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border">
                        <div class="card-body py-3">
                            <div class="text-muted small">Cancelled</div>
                            <div class="fs-4 fw-light" id="report-total-cancelled">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <h5 class="mb-3 fw-light">By treatment</h5>
                    <div class="card border">
                        <div class="card-body p-0">
                            <div id="report-per-service" class="p-3 overflow-auto" style="max-height: 420px;">
                                <!-- JS -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <h5 class="mb-3 fw-light">By month</h5>
                    <div class="card border">
                        <div class="card-body p-0">
                            <div id="report-by-month" class="p-3 overflow-auto" style="max-height: 420px;">
                                <!-- JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/pages/reports.js') ?>"></script>
<?php end_section('scripts'); ?>
