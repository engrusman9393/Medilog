<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get today's date
    $today = date('Y-m-d');
    $dateFilter = $_GET['date'] ?? $today;
    
    // Today's Sales
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM sales_tbl WHERE DATE(sale_date) = ?");
    $stmt->execute([$dateFilter]);
    $todaySales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Today's Profit (assuming profit = selling_price - cost_price)
    $stmt = $conn->prepare("SELECT COALESCE(SUM((selling_price - cost_price) * quantity), 0) as profit FROM sales_tbl s JOIN inventory_tbl i ON s.product_id = i.id WHERE DATE(s.sale_date) = ?");
    $stmt->execute([$dateFilter]);
    $todayProfit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Out of Stock Products
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_tbl WHERE quantity <= 0");
    $stmt->execute();
    $outOfStock = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Expired Medicines
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_tbl WHERE expiryDate <= CURDATE()");
    $stmt->execute();
    $expiredMedicines = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent Sales
    $stmt = $conn->prepare("SELECT s.*, i.productName FROM sales_tbl s JOIN inventory_tbl i ON s.product_id = i.id ORDER BY s.sale_date DESC LIMIT 10");
    $stmt->execute();
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low Stock Products (quantity <= 10)
    $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE quantity > 0 AND quantity <= 10 ORDER BY quantity ASC LIMIT 10");
    $stmt->execute();
    $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $todaySales = ['count' => 0, 'total' => 0];
    $todayProfit = ['profit' => 0];
    $outOfStock = ['count' => 0];
    $expiredMedicines = ['count' => 0];
    $recentSales = [];
    $lowStock = [];
}
?>

<!-- Dashboard Content -->
<div class="d-flex justify-between align-center mb-4">
    <div>
        <h2>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
        <p class="text-secondary">Here's what's happening with your pharmacy today.</p>
    </div>
    <div class="d-flex gap-2 align-center">
        <span class="text-secondary">Currency: <strong><?php echo getCurrencySymbol($user['id']) . ' ' . getUserCurrency($user['id']); ?></strong></span>
        <input type="date" id="dateFilter" class="form-control" value="<?php echo $dateFilter; ?>" onchange="updateDashboard()">
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" id="todaySales"><?php echo formatCurrency($todaySales['total'], $user['id']); ?></div>
        <div class="stat-label">Today's Sales (<?php echo $todaySales['count']; ?> orders)</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-value" id="todayProfit"><?php echo formatCurrency($todayProfit['profit'], $user['id']); ?></div>
        <div class="stat-label">Today's Profit</div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-value" id="outOfStock"><?php echo $outOfStock['count']; ?></div>
        <div class="stat-label">Out of Stock</div>
    </div>
    
    <div class="stat-card danger">
        <div class="stat-value" id="expiredMedicines"><?php echo $expiredMedicines['count']; ?></div>
        <div class="stat-label">Expired Medicines</div>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Sales</h3>
        </div>
        <div class="card-body">
            <?php if (empty($recentSales)): ?>
                <p class="text-secondary text-center">No recent sales found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sale['productName']); ?></td>
                                    <td><?php echo $sale['quantity']; ?></td>
                                    <td><?php echo formatCurrency($sale['total_amount'], $user['id']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($sale['sale_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Low Stock Alert</h3>
        </div>
        <div class="card-body">
            <?php if (empty($lowStock)): ?>
                <p class="text-secondary text-center">All products are well stocked!</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Cost Price</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStock as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['productName']); ?></td>
                                    <td>
                                        <span class="badge badge-warning"><?php echo $product['quantity']; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-secondary"><?php echo formatCurrency($product['costPrice'], $user['id']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['shelfLocation']); ?></td>
                                    <td>
                                        <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Additional Stats Row -->
<div class="row mt-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
    <!-- Monthly Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">This Month Summary</h3>
        </div>
        <div class="card-body">
            <?php
            try {
                // Monthly sales
                $stmt = $conn->prepare("SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                    FROM sales_tbl 
                    WHERE MONTH(sale_date) = MONTH(CURDATE()) 
                    AND YEAR(sale_date) = YEAR(CURDATE())");
                $stmt->execute();
                $monthlyStats = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $monthlyStats = ['total_orders' => 0, 'total_sales' => 0, 'avg_order_value' => 0];
            }
            ?>
            <div class="stats-list">
                <div class="stat-item">
                    <span class="stat-label">Total Orders:</span>
                    <span class="stat-value"><?php echo number_format($monthlyStats['total_orders']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Total Sales:</span>
                    <span class="stat-value"><?php echo formatCurrency($monthlyStats['total_sales'], $user['id']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Avg. Order Value:</span>
                    <span class="stat-value"><?php echo formatCurrency($monthlyStats['avg_order_value'], $user['id']); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expiring Soon -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Expiring Soon</h3>
        </div>
        <div class="card-body">
            <?php
            try {
                // Get user's expiry alert setting
                $expiryDays = $db->getUserSetting($user['id'], 'expiry_alert_days', 30);
                
                $stmt = $conn->prepare("SELECT productName, expiryDate, quantity 
                    FROM inventory_tbl 
                    WHERE expiryDate IS NOT NULL 
                    AND expiryDate > CURDATE() 
                    AND expiryDate <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY expiryDate ASC LIMIT 5");
                $stmt->execute([$expiryDays]);
                $expiringSoon = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $expiringSoon = [];
            }
            ?>
            
            <?php if (empty($expiringSoon)): ?>
                <p class="text-secondary text-center">
                    <i class="fas fa-check-circle text-success"></i><br>
                    No products expiring soon!
                </p>
            <?php else: ?>
                <div class="expiring-list">
                    <?php foreach ($expiringSoon as $product): ?>
                        <div class="expiring-item">
                            <div class="product-info">
                                <strong><?php echo htmlspecialchars($product['productName']); ?></strong>
                                <small class="text-secondary"><?php echo $product['quantity']; ?> units</small>
                            </div>
                            <div class="expiry-date">
                                <span class="badge badge-warning">
                                    <?php echo date('M j', strtotime($product['expiryDate'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="d-flex gap-3 flex-wrap">
            <a href="products.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add New Product
            </a>
            <a href="sales.php?action=new" class="btn btn-success">
                <i class="fas fa-shopping-cart"></i>
                New Sale
            </a>
            <a href="upload.php" class="btn btn-warning">
                <i class="fas fa-upload"></i>
                Upload CSV
            </a>
            <a href="reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i>
                View Reports
            </a>
            <a href="inventory.php" class="btn btn-info">
                <i class="fas fa-boxes"></i>
                Manage Inventory
            </a>
            <a href="settings.php" class="btn btn-dark">
                <i class="fas fa-cog"></i>
                Settings
            </a>
        </div>
    </div>
</div>

<style>
.stats-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: rgba(0,0,0,0.02);
    border-radius: 4px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.stat-value {
    font-weight: 600;
    color: var(--text-primary);
}

.expiring-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.expiring-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: rgba(255, 193, 7, 0.1);
    border-radius: 4px;
    border-left: 3px solid var(--warning-color);
}

.product-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.product-info strong {
    font-size: 0.875rem;
}

.product-info small {
    font-size: 0.75rem;
}

.flex-wrap {
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .d-flex.gap-3.flex-wrap {
        flex-direction: column;
    }
    
    .d-flex.gap-3.flex-wrap .btn {
        justify-content: flex-start;
    }
}
</style>

<script>
function updateDashboard() {
    const dateFilter = document.getElementById('dateFilter').value;
    window.location.href = `dashboard.php?date=${dateFilter}`;
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000); // 5 minutes

// Show loading state when navigating
function showLoading() {
    document.getElementById('todaySales').innerHTML = '<span class="loading"></span>';
    document.getElementById('todayProfit').innerHTML = '<span class="loading"></span>';
}
</script>

<?php require_once 'includes/footer.php'; ?>