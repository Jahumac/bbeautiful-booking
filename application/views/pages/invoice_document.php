<?php
/**
 * Printable invoice document.
 *
 * Local variables:
 * @var array $invoice
 * @var array $appointment
 * @var array $customer
 * @var array $line_items
 * @var float $total
 * @var float $vat_rate
 * @var float $vat_amount
 * @var float $net_total
 * @var float $grand_total
 * @var string $company_name
 * @var string $company_email
 * @var string $company_link
 * @var string $company_logo
 * @var string $company_color
 * @var string $date_format
 */
$customer_name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
$customer_email = $customer['email'] ?? '';
$customer_phone = $customer['phone_number'] ?? '';
// Hide internal walk-in placeholder values on the printed invoice.
if (str_contains($customer_email, '@bbeautiful.local') || str_starts_with($customer_email, 'walkin-')) {
    $customer_email = '';
}
if (preg_match('/^0{5,}/', $customer_phone)) {
    $customer_phone = '';
}
$customer_address = $customer['address'] ?? '';
$customer_city = $customer['city'] ?? '';
$customer_zip = $customer['zip_code'] ?? '';
$status = ($invoice['status'] ?? 'unpaid') === 'paid' ? 'PAID' : 'UNPAID';
$accent = !empty($company_color) && $company_color !== '#429A82' ? $company_color : '#429A82';
$logo_src = !empty($company_logo) ? $company_logo : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?= e($invoice['number']) ?> — <?= e($company_name) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 40px 20px;
            background: #f3f4f6;
        }
        .invoice {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e5e7eb; padding-bottom: 24px; margin-bottom: 24px; }
        .brand { text-align: center; flex: 1; }
        .brand h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .brand .tagline { color: #6b7280; font-size: 13px; margin-top: 4px; }
        .brand .logo { display: block; margin: 0 auto 10px; max-height: 84px; max-width: 180px; }
        .meta { text-align: right; }
        .meta .number { font-size: 20px; font-weight: 600; }
        .meta .status { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .status.paid { background: #d1fae5; color: #065f46; }
        .status.unpaid { background: #fee2e2; color: #991b1b; }
        .accent { color: <?= e($accent) ?>; }
        .grid-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .grid-table td { vertical-align: top; padding: 0 24px 0 0; }
        .grid-table td:last-child { padding-right: 0; }
        .block h3 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        .block p { margin: 2px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 2px solid #e5e7eb; padding: 8px 12px; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        td.num, th.num { text-align: right; }
        .totals { margin-left: auto; width: 260px; }
        .totals table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .totals td { font-size: 14px; padding: 6px 0; border: none; background: none; }
        .totals .amt { text-align: right; font-weight: normal; }
        .totals .total-row td { border-top: 2px solid #e5e7eb; padding-top: 8px; font-weight: bold; font-size: 15px; }
        .totals .total-row .amt { font-weight: bold; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px; text-align: center; }
        .footer .thanks { color: #374151; font-size: 14px; margin-bottom: 4px; }
        .footer .records { font-size: 12px; }
        .doc-actions { position: fixed; top: 16px; right: 16px; display: flex; gap: 8px; z-index: 100; }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice { box-shadow: none; border-radius: 0; padding: 0; }
            .doc-actions { display: none; }
        }
    </style>
</head>
<body>
    <?php if (empty($is_pdf)): ?>
        <div class="doc-actions">
            <a class="btn btn-primary" href="<?= e(site_url('invoices/pdf?id=' . (int) $invoice['id'])) ?>">
                <i class="fas fa-file-pdf me-1"></i>Download PDF
            </a>
            <button class="btn btn-outline-secondary" onclick="window.print()">Print</button>
        </div>
    <?php endif; ?>

    <div class="invoice">
        <div class="header">
            <div class="brand">
                <?php if ($logo_src): ?>
                    <img src="<?= e($logo_src) ?>" alt="logo" class="logo">
                <?php endif; ?>
                <h1><?= e($company_name) ?></h1>
                <div class="tagline">Your friendly home beauty &amp; nail salon</div>
                <?php if ($company_email): ?><div class="tagline"><?= e($company_email) ?></div><?php endif; ?>
            </div>
            <div class="meta">
                <div class="number accent"><?= e($invoice['number']) ?></div>
                <div class="status <?= $status === 'PAID' ? 'paid' : 'unpaid' ?>"><?= $status ?></div>
            </div>
        </div>

        <table class="grid-table">
            <tr>
                <td class="block">
                    <h3>Billed to</h3>
                    <p><strong><?= e($customer_name) ?></strong></p>
                    <?php if ($customer_email): ?><p><?= e($customer_email) ?></p><?php endif; ?>
                    <?php if ($customer_phone): ?><p><?= e($customer_phone) ?></p><?php endif; ?>
                    <?php if ($customer_address): ?><p><?= e($customer_address) ?></p><?php endif; ?>
                    <?php if ($customer_city || $customer_zip): ?><p><?= e(trim($customer_city . ' ' . $customer_zip)) ?></p><?php endif; ?>
                </td>
                <td class="block">
                    <h3>Invoice details</h3>
                    <p><strong>Date:</strong> <?= e(format_date($invoice['invoice_date'])) ?></p>
                    <?php if ($appointment): ?>
                        <p><strong>Appointment:</strong> <?= e(format_date($appointment['start_datetime'])) ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Treatment</th>
                    <th class="num">Qty</th>
                    <th class="num">Duration</th>
                    <th class="num">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($line_items as $item): ?>
                    <tr>
                        <td><?= e($item['name'] ?? $item['description'] ?? '') ?></td>
                        <td class="num"><?= (int) ($item['quantity'] ?? 1) ?></td>
                        <td class="num"><?= !empty($item['duration']) ? (int) $item['duration'] . ' min' : '—' ?></td>
                        <td class="num">£<?= number_format((float) ($item['price'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tbody>
                    <?php if ((float) $vat_amount > 0): ?>
                        <tr><td>Subtotal (excl. VAT)</td><td class="amt">£<?= number_format((float) $net_total, 2) ?></td></tr>
                        <tr><td>VAT (<?= rtrim(rtrim(number_format((float) $vat_rate, 2), '0'), '.') ?>%)</td><td class="amt">£<?= number_format((float) $vat_amount, 2) ?></td></tr>
                        <tr class="total-row"><td>Total (incl. VAT)</td><td class="amt">£<?= number_format((float) $grand_total, 2) ?></td></tr>
                    <?php else: ?>
                        <tr><td>Subtotal</td><td class="amt">£<?= number_format((float) $total, 2) ?></td></tr>
                        <tr class="total-row"><td>Total</td><td class="amt">£<?= number_format((float) $grand_total, 2) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <div class="thanks">Thank you for visiting <?= e($company_name) ?> — we hope you enjoyed your treatment.</div>
            <div class="records">This invoice was generated for your records.</div>
        </div>
    </div>
</body>
</html>
