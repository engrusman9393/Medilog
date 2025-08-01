<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get receipt ID from URL parameter
$receipt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receipt_id <= 0) {
    echo "Invalid receipt ID";
    exit();
}

// Fetch receipt data
$stmt = $pdo->prepare("
    SELECT r.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address
    FROM receipts r
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE r.id = ?
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    echo "Receipt not found";
    exit();
}

// Fetch receipt items
$stmt = $pdo->prepare("
    SELECT ri.*, p.name as product_name, p.code as product_code
    FROM receipt_items ri
    LEFT JOIN products p ON ri.product_id = p.id
    WHERE ri.receipt_id = ?
");
$stmt->execute([$receipt_id]);
$receipt_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - Medilog</title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .receipt-header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        
        .receipt-info {
            margin-bottom: 20px;
        }
        
        .receipt-info table {
            width: 100%;
        }
        
        .receipt-info td {
            padding: 5px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .total-section {
            border-top: 2px solid #333;
            padding-top: 10px;
            text-align: right;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 18px;
        }
        
        .print-btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .print-btn:hover {
            background-color: #0056b3;
        }
        
        .back-btn {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .back-btn:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
        <a href="sales.php" class="back-btn">Back to Sales</a>
    </div>
    
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>MEDILOG</h1>
            <p>Medical Supplies & Equipment</p>
            <p>Receipt #<?php echo str_pad($receipt['id'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        
        <div class="receipt-info">
            <table>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($receipt['created_at'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Customer:</strong></td>
                    <td><?php echo htmlspecialchars($receipt['customer_name'] ?? 'Walk-in Customer'); ?></td>
                </tr>
                <?php if (!empty($receipt['customer_phone'])): ?>
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td><?php echo htmlspecialchars($receipt['customer_phone']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($receipt['customer_address'])): ?>
                <tr>
                    <td><strong>Address:</strong></td>
                    <td><?php echo htmlspecialchars($receipt['customer_address']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipt_items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-section">
            <div class="total-row">
                <strong>Subtotal: $<?php echo number_format($receipt['subtotal'], 2); ?></strong>
            </div>
            <?php if ($receipt['tax_amount'] > 0): ?>
            <div>
                <strong>Tax: $<?php echo number_format($receipt['tax_amount'], 2); ?></strong>
            </div>
            <?php endif; ?>
            <?php if ($receipt['discount_amount'] > 0): ?>
            <div>
                <strong>Discount: -$<?php echo number_format($receipt['discount_amount'], 2); ?></strong>
            </div>
            <?php endif; ?>
            <div class="total-row">
                <strong>Total: $<?php echo number_format($receipt['total_amount'], 2); ?></strong>
            </div>
            <div>
                <strong>Payment Method: <?php echo htmlspecialchars($receipt['payment_method']); ?></strong>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
            <p>Thank you for your purchase!</p>
            <p>For any queries, please contact us.</p>
            <p>This is a computer generated receipt.</p>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>