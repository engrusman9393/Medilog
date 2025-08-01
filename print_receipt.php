<?php
// Printable receipt page for Medilog Admin Panel (58-80mm thermal friendly)
// Assumes a PDO connection is available via inc/config.php providing $pdo

require_once __DIR__ . '/inc/config.php'; // adjust path if different

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($saleId <= 0) {
    die('Invalid sale ID.');
}

try {
    // Fetch sale record
    $saleStmt = $pdo->prepare('SELECT s.*, c.name AS customer_name, c.phone, c.email
                                FROM sales s
                                LEFT JOIN customers c ON c.id = s.customer_id
                                WHERE s.id = :id');
    $saleStmt->execute(['id' => $saleId]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) {
        die('Sale not found.');
    }

    // Fetch sale items
    $itemsStmt = $pdo->prepare('SELECT si.*, p.name AS product_name
                                 FROM sale_items si
                                 JOIN products p ON p.id = si.product_id
                                 WHERE si.sale_id = :sale_id');
    $itemsStmt->execute(['sale_id' => $saleId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Company settings (hard-code or pull from settings table)
$company = [
    'name'    => 'Medilog Pharmacy',
    'address' => '123 Health St, Wellness City',
    'phone'   => '+1 555-123-4567',
];

// Helper to format money
function money($amount) {
    return number_format((float)$amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo htmlspecialchars($sale['invoice_no']); ?></title>
    <style>
        /* 58-80mm thermal styling */
        @media print {
            @page { size: auto; margin: 0; }
            body { margin: 0; }
        }
        body {
            font-family: "Courier New", monospace;
            font-size: 12px;
            padding: 10px;
            max-width: 80mm;
        }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; }
        .items th {
            border-bottom: 1px dashed #000;
        }
        .items td {
            vertical-align: top;
        }
        .totals td { padding-top: 4px; }
        .totals .label { text-align: right; }
    </style>
</head>
<body onload="window.print(); setTimeout(()=>window.close(), 500);">
<div class="text-center bold">
    <?php echo htmlspecialchars($company['name']); ?>
</div>
<div class="text-center">
    <?php echo htmlspecialchars($company['address']); ?><br>
    Phone: <?php echo htmlspecialchars($company['phone']); ?>
</div>
<hr>
<table>
    <tr>
        <td>Invoice:</td><td>#<?php echo htmlspecialchars($sale['invoice_no']); ?></td>
    </tr>
    <tr>
        <td>Date:</td><td><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></td>
    </tr>
    <?php if ($sale['customer_name']): ?>
    <tr>
        <td>Customer:</td><td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
    </tr>
    <?php endif; ?>
</table>
<hr>
<table class="items">
    <thead>
    <tr>
        <th>Qty</th>
        <th>Description</th>
        <th style="text-align:right;">Total</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
        <?php $lineTotal = $item['price'] * $item['quantity']; ?>
        <tr>
            <td><?php echo $item['quantity']; ?></td>
            <td>
                <?php echo htmlspecialchars($item['product_name']); ?><br>
                <small>@ <?php echo money($item['price']); ?></small>
            </td>
            <td style="text-align:right;">
                <?php echo money($lineTotal); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<hr>
<?php
$subTotal = array_reduce($items, fn($c,$i)=>$c + ($i['price'] * $i['quantity']), 0);
$tax = $sale['tax_amount'] ?? 0;
$discount = $sale['discount'] ?? 0;
$total = $subTotal + $tax - $discount;
?>
<table class="totals">
    <tr>
        <td class="label">Subtotal:</td>
        <td style="text-align:right;"><?php echo money($subTotal); ?></td>
    </tr>
    <?php if ($discount > 0): ?>
    <tr>
        <td class="label">Discount:</td>
        <td style="text-align:right;">-<?php echo money($discount); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($tax > 0): ?>
    <tr>
        <td class="label">Tax:</td>
        <td style="text-align:right;"><?php echo money($tax); ?></td>
    </tr>
    <?php endif; ?>
    <tr class="bold">
        <td class="label">TOTAL:</td>
        <td style="text-align:right;"><?php echo money($total); ?></td>
    </tr>
</table>
<hr>
<div class="text-center">
    Thank you for your purchase!<br>
    Powered by Medilog Admin Panel
</div>
</body>
</html>