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
        .brand h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .brand .tagline { color: #6b7280; font-size: 13px; margin-top: 4px; }
        .brand .logo { max-height: 56px; margin-bottom: 8px; }
        .meta { text-align: right; }
        .meta .number { font-size: 20px; font-weight: 600; }
        .meta .status { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .status.paid { background: #d1fae5; color: #065f46; }
        .status.unpaid { background: #fee2e2; color: #991b1b; }
        .accent { color: <?= e($accent) ?>; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .block h3 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        .block p { margin: 2px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 2px solid #e5e7eb; padding: 8px 12px; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        td.num, th.num { text-align: right; }
        .totals { margin-left: auto; width: 260px; }
        .totals .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .totals .grand { border-top: 2px solid #e5e7eb; margin-top: 6px; padding-top: 10px; font-weight: 600; font-size: 16px; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; text-align: center; }
        .print-btn { position: fixed; top: 16px; right: 16px; padding: 10px 18px; background: #111827; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice { box-shadow: none; border-radius: 0; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>

    <div class="invoice">
        <div class="header">
            <div class="brand">
                <?php if ($logo_src): ?>
                    <img src="<?= e($logo_src) ?>" alt="logo" class="logo">
                <?php endif; ?>
                <h1><?= e($company_name) ?></h1>
                <div class="tagline">Beauty, nails &amp; more</div>
                <?php if ($company_email): ?><div class="tagline"><?= e($company_email) ?></div><?php endif; ?>
            </div>
            <div class="meta">
                <div class="number accent"><?= e($invoice['number']) ?></div>
                <div class="status <?= $status === 'PAID' ? 'paid' : 'unpaid' ?>"><?= $status ?></div>
            </div>
        </div>

        <div class="grid">
            <div class="block">
                <h3>Billed to</h3>
                <p><strong><?= e($customer_name) ?></strong></p>
                <?php if ($customer_email): ?><p><?= e($customer_email) ?></p><?php endif; ?>
                <?php if ($customer_phone): ?><p><?= e($customer_phone) ?></p><?php endif; ?>
                <?php if ($customer_address): ?><p><?= e($customer_address) ?></p><?php endif; ?>
                <?php if ($customer_city || $customer_zip): ?><p><?= e(trim($customer_city . ' ' . $customer_zip)) ?></p><?php endif; ?>
            </div>
            <div class="block">
                <h3>Invoice details</h3>
                <p><strong>Date:</strong> <?= e(date($date_format, strtotime($invoice['invoice_date']))) ?></p>
                <p><strong>Appointment:</strong> <?= e(date($date_format, strtotime($appointment['start_datetime']))) ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Treatment</th>
                    <th class="num">Duration</th>
                    <th class="num">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($line_items as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td class="num"><?= (int) $item['duration'] ?> min</td>
                        <td class="num">£<?= number_format((float) $item['price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal</span><span>£<?= number_format($total, 2) ?></span></div>
            <div class="row grand"><span>Total</span><span>£<?= number_format($total, 2) ?></span></div>
        </div>

        <div class="footer">
            Thank you for visiting <?= e($company_name) ?>. This invoice was generated for your records.
        </div>
    </div>
</body>
</html>
