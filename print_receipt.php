<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sale_id <= 0) {
    header('Location: sales.php');
    exit();
}

try {
    // Get sale details
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name, c.phone, c.address,
               u.name as cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        header('Location: sales.php');
        exit();
    }

    // Get sale items
    $stmt = $pdo->prepare("
        SELECT si.*, m.name as medicine_name, m.dosage, m.manufacturer
        FROM sale_items si
        JOIN medicines m ON si.medicine_id = m.id
        WHERE si.sale_id = ?
        ORDER BY si.id
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - Medilog</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            font-size: 12px;
        }
        
        .receipt {
            max-width: 300px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 10px;
            line-height: 1.2;
        }
        
        .receipt-details {
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th,
        .items-table td {
            text-align: left;
            padding: 2px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .items-table th {
            font-weight: bold;
            border-bottom: 1px solid #000;
        }
        
        .total-section {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dotted #ccc;
            font-size: 10px;
        }
        
        .print-btn {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="print-btn no-print">
        <button onclick="window.print()" class="btn">Print Receipt</button>
        <a href="sales.php" class="btn btn-secondary">Back to Sales</a>
    </div>

    <div class="receipt">
        <div class="header">
            <div class="company-name">MEDILOG PHARMACY</div>
            <div class="company-details">
                Address: 123 Medical Street<br>
                City, State 12345<br>
                Phone: (555) 123-4567<br>
                License: PH-2024-001
            </div>
        </div>

        <div class="receipt-details">
            <div class="detail-row">
                <span><strong>Receipt #:</strong></span>
                <span><?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Date:</strong></span>
                <span><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Cashier:</strong></span>
                <span><?php echo htmlspecialchars($sale['cashier_name'] ?? 'N/A'); ?></span>
            </div>
            <?php if ($sale['customer_name']): ?>
            <div class="detail-row">
                <span><strong>Customer:</strong></span>
                <span><?php echo htmlspecialchars($sale['customer_name']); ?></span>
            </div>
            <?php if ($sale['phone']): ?>
            <div class="detail-row">
                <span><strong>Phone:</strong></span>
                <span><?php echo htmlspecialchars($sale['phone']); ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
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
                <?php foreach ($sale_items as $item): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($item['medicine_name']); ?>
                        <?php if ($item['dosage']): ?>
                        <br><small><?php echo htmlspecialchars($item['dosage']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($sale['subtotal'], 2); ?></span>
            </div>
            <?php if ($sale['discount'] > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-$<?php echo number_format($sale['discount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row">
                <span>Tax:</span>
                <span>$<?php echo number_format($sale['tax'], 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($sale['total'], 2); ?></span>
            </div>
            <div class="total-row">
                <span>Payment Method:</span>
                <span><?php echo strtoupper(htmlspecialchars($sale['payment_method'] ?? 'CASH')); ?></span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Keep this receipt for your records</p>
            <p>For returns, please present this receipt within 30 days</p>
            <hr style="border: 1px dotted #ccc;">
            <p>Receipt generated on <?php echo date('M d, Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Auto-focus print dialog when page loads
        window.onload = function() {
            // Optional: Auto-print when page loads
            // window.print();
        };

        // Print function
        function printReceipt() {
            window.print();
        }

        // Keyboard shortcut for printing (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReceipt();
            }
        });
    </script>
</body>
</html>