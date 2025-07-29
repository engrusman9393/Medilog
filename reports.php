<?php
$pageTitle = 'Reports & Analytics';
require_once 'includes/header.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'sales';

// Generate reports based on type
$reportData = [];
$reportTitle = '';

try {
    switch ($report_type) {
        case 'sales':
            $reportTitle = 'Sales Report';
            $stmt = $conn->prepare("
                SELECT 
                    s.*,
                    COUNT(si.id) as item_count,
                    GROUP_CONCAT(CONCAT(si.quantity, 'x ', i.productName) SEPARATOR ', ') as items
                FROM sales_tbl s 
                LEFT JOIN sale_items_tbl si ON s.id = si.sale_id 
                LEFT JOIN inventory_tbl i ON si.product_id = i.id 
                WHERE DATE(s.sale_date) BETWEEN ? AND ?
                GROUP BY s.id 
                ORDER BY s.sale_date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'inventory':
            $reportTitle = 'Inventory Report';
            $stmt = $conn->prepare("
                SELECT *,
                    CASE 
                        WHEN quantity <= 0 THEN 'Out of Stock'
                        WHEN quantity <= 10 THEN 'Low Stock'
                        WHEN expiryDate <= CURDATE() AND expiryDate != '0000-00-00' THEN 'Expired'
                        WHEN expiryDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiryDate != '0000-00-00' THEN 'Expiring Soon'
                        ELSE 'In Stock'
                    END as status,
                    (quantity * costPrice) as total_value
                FROM inventory_tbl 
                ORDER BY productName ASC
            ");
            $stmt->execute();
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'profit':
            $reportTitle = 'Profit Analysis Report';
            $stmt = $conn->prepare("
                SELECT 
                    DATE(s.sale_date) as sale_date,
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(s.final_amount) as total_revenue,
                    SUM(si.cost_price * si.quantity) as total_cost,
                    SUM((si.selling_price - si.cost_price) * si.quantity) as total_profit,
                    ROUND(AVG((si.selling_price - si.cost_price) / si.cost_price * 100), 2) as avg_margin_percent
                FROM sales_tbl s
                JOIN sale_items_tbl si ON s.id = si.sale_id
                WHERE DATE(s.sale_date) BETWEEN ? AND ?
                GROUP BY DATE(s.sale_date)
                ORDER BY sale_date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'products':
            $reportTitle = 'Product Performance Report';
            $stmt = $conn->prepare("
                SELECT 
                    i.productName,
                    i.category,
                    i.quantity as current_stock,
                    COALESCE(SUM(si.quantity), 0) as total_sold,
                    COALESCE(SUM(si.total_amount), 0) as total_revenue,
                    COALESCE(SUM((si.selling_price - si.cost_price) * si.quantity), 0) as total_profit,
                    COUNT(DISTINCT s.id) as sales_count
                FROM inventory_tbl i
                LEFT JOIN sale_items_tbl si ON i.id = si.product_id
                LEFT JOIN sales_tbl s ON si.sale_id = s.id AND DATE(s.sale_date) BETWEEN ? AND ?
                GROUP BY i.id, i.productName, i.category, i.quantity
                ORDER BY total_sold DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
    
    // Get summary statistics
    $stats = [];
    if ($report_type === 'sales') {
        $totalSales = count($reportData);
        $totalRevenue = array_sum(array_column($reportData, 'final_amount'));
        $avgSale = $totalSales > 0 ? $totalRevenue / $totalSales : 0;
        
        $stats = [
            'Total Sales' => $totalSales,
            'Total Revenue' => '₹' . number_format($totalRevenue, 2),
            'Average Sale' => '₹' . number_format($avgSale, 2)
        ];
    } elseif ($report_type === 'inventory') {
        $totalProducts = count($reportData);
        $totalValue = array_sum(array_column($reportData, 'total_value'));
        $outOfStock = count(array_filter($reportData, fn($item) => $item['quantity'] <= 0));
        
        $stats = [
            'Total Products' => $totalProducts,
            'Total Value' => '₹' . number_format($totalValue, 2),
            'Out of Stock' => $outOfStock
        ];
    } elseif ($report_type === 'profit') {
        $totalProfit = array_sum(array_column($reportData, 'total_profit'));
        $totalRevenue = array_sum(array_column($reportData, 'total_revenue'));
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
        
        $stats = [
            'Total Profit' => '₹' . number_format($totalProfit, 2),
            'Total Revenue' => '₹' . number_format($totalRevenue, 2),
            'Profit Margin' => number_format($profitMargin, 2) . '%'
        ];
    }
    
} catch (Exception $e) {
    $error = 'Failed to generate report: ' . $e->getMessage();
}
?>

<div class="d-flex justify-between align-center mb-4">
    <div>
        <h2>Reports & Analytics</h2>
        <p class="text-secondary">Generate detailed reports for your business</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="exportToCSV('<?php echo $report_type; ?>')" class="btn btn-success">
            <i class="fas fa-file-csv"></i>
            Export CSV
        </button>
        <button onclick="exportToPDF('<?php echo $report_type; ?>')" class="btn btn-danger">
            <i class="fas fa-file-pdf"></i>
            Export PDF
        </button>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">Report Configuration</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="form-row">
            <div class="form-group">
                <label for="report_type" class="form-label">Report Type</label>
                <select name="report_type" id="report_type" class="form-control">
                    <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                    <option value="inventory" <?php echo $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory Report</option>
                    <option value="profit" <?php echo $report_type === 'profit' ? 'selected' : ''; ?>>Profit Analysis</option>
                    <option value="products" <?php echo $report_type === 'products' ? 'selected' : ''; ?>>Product Performance</option>
                </select>
            </div>
            
            <?php if ($report_type !== 'inventory'): ?>
            <div class="form-group">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" name="date_from" id="date_from" class="form-control" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            
            <div class="form-group">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" name="date_to" id="date_to" class="form-control" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i>
                    Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Statistics -->
<?php if (!empty($stats)): ?>
<div class="stats-grid mb-4">
    <?php foreach ($stats as $label => $value): ?>
    <div class="stat-card">
        <div class="stat-value"><?php echo $value; ?></div>
        <div class="stat-label"><?php echo $label; ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Report Data -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-between align-center">
            <h3 class="card-title">
                <?php echo $reportTitle; ?>
                <?php if ($report_type !== 'inventory'): ?>
                    (<?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?>)
                <?php endif; ?>
            </h3>
            <span class="badge badge-primary"><?php echo count($reportData); ?> records</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (empty($reportData)): ?>
            <p class="text-center text-secondary">No data found for the selected criteria.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="reportTable">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'sales'): ?>
                                <th>Sale ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Discount</th>
                                <th>Final Amount</th>
                                <th>Payment Method</th>
                            <?php elseif ($report_type === 'inventory'): ?>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Total Value</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            <?php elseif ($report_type === 'profit'): ?>
                                <th>Date</th>
                                <th>Total Sales</th>
                                <th>Revenue</th>
                                <th>Cost</th>
                                <th>Profit</th>
                                <th>Margin %</th>
                            <?php elseif ($report_type === 'products'): ?>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Total Sold</th>
                                <th>Revenue</th>
                                <th>Profit</th>
                                <th>Sales Count</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php if ($report_type === 'sales'): ?>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($row['sale_date'])); ?></td>
                                    <td>
                                        <?php if (!empty($row['customer_name'])): ?>
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                            <?php if (!empty($row['customer_phone'])): ?>
                                                <br><small><?php echo htmlspecialchars($row['customer_phone']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em>Walk-in Customer</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($row['items']); ?></small>
                                        <br><span class="badge badge-secondary"><?php echo $row['item_count']; ?> items</span>
                                    </td>
                                    <td>₹<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['discount_amount'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($row['final_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['payment_method'] === 'cash' ? 'success' : 'primary'; ?>">
                                            <?php echo ucfirst($row['payment_method']); ?>
                                        </span>
                                    </td>
                                <?php elseif ($report_type === 'inventory'): ?>
                                    <td><?php echo htmlspecialchars($row['productName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['quantity'] <= 0 ? 'danger' : ($row['quantity'] <= 10 ? 'warning' : 'success'); ?>">
                                            <?php echo $row['quantity']; ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($row['costPrice'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['selling_price'] ?? $row['costPrice'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['total_value'], 2); ?></td>
                                    <td>
                                        <?php if ($row['expiryDate'] && $row['expiryDate'] !== '0000-00-00'): ?>
                                            <?php echo date('M j, Y', strtotime($row['expiryDate'])); ?>
                                        <?php else: ?>
                                            <em>N/A</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $row['status'] === 'In Stock' ? 'success' : 
                                                ($row['status'] === 'Low Stock' || $row['status'] === 'Expiring Soon' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                <?php elseif ($report_type === 'profit'): ?>
                                    <td><?php echo date('M j, Y', strtotime($row['sale_date'])); ?></td>
                                    <td><?php echo $row['total_sales']; ?></td>
                                    <td>₹<?php echo number_format($row['total_revenue'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['total_cost'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($row['total_profit'], 2); ?></strong></td>
                                    <td><?php echo $row['avg_margin_percent']; ?>%</td>
                                <?php elseif ($report_type === 'products'): ?>
                                    <td><?php echo htmlspecialchars($row['productName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['current_stock'] <= 0 ? 'danger' : ($row['current_stock'] <= 10 ? 'warning' : 'success'); ?>">
                                            <?php echo $row['current_stock']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['total_sold']; ?></td>
                                    <td>₹<?php echo number_format($row['total_revenue'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['total_profit'], 2); ?></td>
                                    <td><?php echo $row['sales_count']; ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-submit form when report type changes
document.getElementById('report_type').addEventListener('change', function() {
    // Hide date fields for inventory report
    const dateFields = document.querySelectorAll('#date_from, #date_to');
    const dateLabels = document.querySelectorAll('label[for="date_from"], label[for="date_to"]');
    
    if (this.value === 'inventory') {
        dateFields.forEach(field => field.parentElement.style.display = 'none');
    } else {
        dateFields.forEach(field => field.parentElement.style.display = 'block');
    }
});

// Initialize date field visibility
document.getElementById('report_type').dispatchEvent(new Event('change'));
</script>

<?php require_once 'includes/footer.php'; ?>