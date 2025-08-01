<?php
// Basic Sale Items listing page for Medilog Admin Panel
// Assumes there is an include file that provides a $pdo (PHP Data Objects) database connection.

require_once __DIR__ . '/inc/config.php'; // adjust path if needed

// Sanitize & validate incoming sale ID
$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($saleId <= 0) {
    die('Invalid sale ID.');
}

try {
    // Fetch sale info
    $saleStmt = $pdo->prepare('SELECT * FROM sales WHERE id = :id');
    $saleStmt->execute(['id' => $saleId]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        die('Sale not found.');
    }

    // Fetch sale items + product details
    $itemsStmt = $pdo->prepare('SELECT si.*, p.name AS product_name, p.code AS product_code
                                 FROM sale_items si
                                 JOIN products p ON p.id = si.product_id
                                 WHERE si.sale_id = :sale_id');
    $itemsStmt->execute(['sale_id' => $saleId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sale #<?php echo htmlspecialchars($sale['invoice_no']); ?> - Items</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css"><!-- adjust to your asset path -->
</head>
<body>
<div class="container my-4">
    <h1 class="mb-4">Sale #<?php echo htmlspecialchars($sale['invoice_no']); ?> - Items</h1>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Product Code</th>
            <th>Product Name</th>
            <th class="text-end">Quantity</th>
            <th class="text-end">Unit Price (<?php echo htmlspecialchars($sale['currency'] ?? '$'); ?>)</th>
            <th class="text-end">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr>
                <td colspan="6" class="text-center">No items found for this sale.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-end"><?php echo number_format($item['quantity'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-between mt-4">
        <a href="sales.php" class="btn btn-secondary">&laquo; Back to Sales</a>
        <a href="print_receipt.php?id=<?php echo $saleId; ?>" target="_blank" class="btn btn-primary">Print Receipt</a>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script> <!-- adjust path as needed -->
</body>
</html>