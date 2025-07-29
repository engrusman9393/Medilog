<?php
$pageTitle = 'Inventory Management';
require_once 'includes/header.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Build WHERE clause
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND productName LIKE ?";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $whereClause .= " AND category = ?";
    $params[] = $category;
}

if ($status === 'out_of_stock') {
    $whereClause .= " AND quantity <= 0";
} elseif ($status === 'low_stock') {
    $whereClause .= " AND quantity > 0 AND quantity <= 10";
} elseif ($status === 'expired') {
    $whereClause .= " AND expiryDate <= CURDATE() AND expiryDate != '0000-00-00'";
} elseif ($status === 'expiring_soon') {
    $whereClause .= " AND expiryDate > CURDATE() AND expiryDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

// Get products
$stmt = $conn->prepare("SELECT * FROM inventory_tbl $whereClause ORDER BY productName ASC");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $conn->prepare("SELECT DISTINCT category FROM inventory_tbl WHERE category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get inventory statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_products,
    SUM(quantity) as total_quantity,
    COUNT(CASE WHEN quantity <= 0 THEN 1 END) as out_of_stock,
    COUNT(CASE WHEN quantity > 0 AND quantity <= 10 THEN 1 END) as low_stock,
    COUNT(CASE WHEN expiryDate <= CURDATE() AND expiryDate != '0000-00-00' THEN 1 END) as expired,
    SUM(quantity * costPrice) as total_value
    FROM inventory_tbl");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Inventory Statistics -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
        <div class="stat-label">Total Products</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-value"><?php echo number_format($stats['total_quantity']); ?></div>
        <div class="stat-label">Total Stock Units</div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-value"><?php echo number_format($stats['low_stock']); ?></div>
        <div class="stat-label">Low Stock Items</div>
    </div>
    
    <div class="stat-card danger">
        <div class="stat-value"><?php echo number_format($stats['out_of_stock']); ?></div>
        <div class="stat-label">Out of Stock</div>
    </div>
</div>

<div class="d-flex justify-between align-center mb-4">
    <div>
        <h2>Inventory Overview</h2>
        <p class="text-secondary">Total inventory value: ₹<?php echo number_format($stats['total_value'], 2); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="upload.php" class="btn btn-warning">
            <i class="fas fa-upload"></i>
            Upload CSV
        </a>
        <a href="products.php?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Product
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="form-row">
            <div class="form-group">
                <input type="text" name="search" class="form-control" placeholder="Search products..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    <option value="low_stock" <?php echo $status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="expiring_soon" <?php echo $status === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon (30 days)</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i>
                    Filter
                </button>
                <a href="inventory.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-between align-center">
            <h3 class="card-title">Inventory Items (<?php echo count($products); ?>)</h3>
            <div class="d-flex gap-2">
                <button onclick="exportToCSV('inventory')" class="btn btn-sm btn-secondary">
                    <i class="fas fa-download"></i>
                    Export CSV
                </button>
                <button onclick="exportToPDF('inventory')" class="btn btn-sm btn-secondary">
                    <i class="fas fa-file-pdf"></i>
                    Export PDF
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="inventoryTable">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Total Value</th>
                        <th>Location</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-secondary">No products found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $isExpired = $product['expiryDate'] && $product['expiryDate'] !== '0000-00-00' && strtotime($product['expiryDate']) <= time();
                            $isExpiringSoon = $product['expiryDate'] && $product['expiryDate'] !== '0000-00-00' && 
                                            strtotime($product['expiryDate']) > time() && 
                                            strtotime($product['expiryDate']) <= strtotime('+30 days');
                            $totalValue = $product['quantity'] * $product['costPrice'];
                            ?>
                            <tr class="<?php echo $isExpired ? 'table-danger' : ($product['quantity'] <= 0 ? 'table-warning' : ''); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($product['productName']); ?></strong>
                                    <?php if (!empty($product['description'])): ?>
                                        <br><small class="text-secondary"><?php echo htmlspecialchars($product['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($product['quantity'] <= 0): ?>
                                        <span class="badge badge-danger">0 (Out of Stock)</span>
                                    <?php elseif ($product['quantity'] <= 10): ?>
                                        <span class="badge badge-warning"><?php echo $product['quantity']; ?> (Low Stock)</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?php echo $product['quantity']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?php echo number_format($product['costPrice'], 2); ?></td>
                                <td>₹<?php echo number_format($product['selling_price'] ?? $product['costPrice'], 2); ?></td>
                                <td>₹<?php echo number_format($totalValue, 2); ?></td>
                                <td><?php echo htmlspecialchars($product['shelfLocation']); ?></td>
                                <td>
                                    <?php if ($product['expiryDate'] && $product['expiryDate'] !== '0000-00-00'): ?>
                                        <?php if ($isExpired): ?>
                                            <span class="badge badge-danger">
                                                <?php echo date('M j, Y', strtotime($product['expiryDate'])); ?> (Expired)
                                            </span>
                                        <?php elseif ($isExpiringSoon): ?>
                                            <span class="badge badge-warning">
                                                <?php echo date('M j, Y', strtotime($product['expiryDate'])); ?> (Soon)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-success">
                                                <?php echo date('M j, Y', strtotime($product['expiryDate'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Expired</span>
                                    <?php elseif ($product['quantity'] <= 0): ?>
                                        <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                    <?php elseif ($product['quantity'] <= 10): ?>
                                        <span class="badge badge-warning"><i class="fas fa-exclamation-circle"></i> Low Stock</span>
                                    <?php elseif ($isExpiringSoon): ?>
                                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Expiring Soon</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="showStockModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['productName']); ?>', <?php echo $product['quantity']; ?>)" 
                                                class="btn btn-sm btn-success" title="Update Stock">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Stock Update Modal -->
<div id="stockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title">Update Stock</h4>
            <button class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <form id="stockForm">
                <input type="hidden" id="productId" name="productId">
                <div class="form-group">
                    <label class="form-label">Product: <span id="productName"></span></label>
                </div>
                <div class="form-group">
                    <label class="form-label">Current Stock: <span id="currentStock"></span></label>
                </div>
                <div class="form-group">
                    <label for="stockAction" class="form-label">Action</label>
                    <select id="stockAction" name="stockAction" class="form-control">
                        <option value="add">Add Stock</option>
                        <option value="remove">Remove Stock</option>
                        <option value="set">Set Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stockQuantity" class="form-label">Quantity</label>
                    <input type="number" id="stockQuantity" name="stockQuantity" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label for="stockReason" class="form-label">Reason</label>
                    <input type="text" id="stockReason" name="stockReason" class="form-control" 
                           placeholder="e.g., New stock arrival, Sale, Damage">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close">Cancel</button>
            <button type="button" onclick="updateStock()" class="btn btn-primary">Update Stock</button>
        </div>
    </div>
</div>

<script>
function showStockModal(productId, productName, currentStock) {
    document.getElementById('productId').value = productId;
    document.getElementById('productName').textContent = productName;
    document.getElementById('currentStock').textContent = currentStock;
    document.getElementById('stockModal').style.display = 'block';
}

async function updateStock() {
    const formData = new FormData(document.getElementById('stockForm'));
    
    try {
        const response = await fetch('api/update_stock.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', result.message);
            document.getElementById('stockModal').style.display = 'none';
            location.reload();
        } else {
            showAlert('error', result.error);
        }
    } catch (error) {
        showAlert('error', 'Failed to update stock');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>