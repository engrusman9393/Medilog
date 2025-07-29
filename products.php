<?php
$pageTitle = 'Product Management';
require_once 'includes/header.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$productId = $_GET['id'] ?? $_GET['edit'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = trim($_POST['productName'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $costPrice = (float)($_POST['costPrice'] ?? 0);
    $sellingPrice = (float)($_POST['sellingPrice'] ?? 0);
    $shelfLocation = trim($_POST['shelfLocation'] ?? '');
    $expiryDate = $_POST['expiryDate'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($productName) || $quantity < 0 || $costPrice < 0) {
        $error = 'Please fill in all required fields with valid values';
    } else {
        try {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO inventory_tbl (productName, quantity, costPrice, selling_price, shelfLocation, expiryDate, category, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if ($stmt->execute([$productName, $quantity, $costPrice, $sellingPrice, $shelfLocation, $expiryDate, $category, $description])) {
                    $message = 'Product added successfully!';
                    $action = 'list';
                } else {
                    $error = 'Failed to add product';
                }
            } elseif ($action === 'edit' && $productId) {
                $stmt = $conn->prepare("UPDATE inventory_tbl SET productName = ?, quantity = ?, costPrice = ?, selling_price = ?, shelfLocation = ?, expiryDate = ?, category = ?, description = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$productName, $quantity, $costPrice, $sellingPrice, $shelfLocation, $expiryDate, $category, $description, $productId])) {
                    $message = 'Product updated successfully!';
                    $action = 'list';
                } else {
                    $error = 'Failed to update product';
                }
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle delete action
if ($action === 'delete' && $productId) {
    try {
        $stmt = $conn->prepare("DELETE FROM inventory_tbl WHERE id = ?");
        if ($stmt->execute([$productId])) {
            $message = 'Product deleted successfully!';
        } else {
            $error = 'Failed to delete product';
        }
        $action = 'list';
    } catch (Exception $e) {
        $error = 'Cannot delete product: ' . $e->getMessage();
        $action = 'list';
    }
}

// Get product for editing
$editProduct = null;
if (($action === 'edit' || isset($_GET['edit'])) && $productId) {
    $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE id = ?");
    $stmt->execute([$productId]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editProduct) {
        $error = 'Product not found';
        $action = 'list';
    } else {
        $action = 'edit';
    }
}

// Get all products for listing
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
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
    
    $stmt = $conn->prepare("SELECT * FROM inventory_tbl $whereClause ORDER BY productName ASC");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $stmt = $conn->prepare("SELECT DISTINCT category FROM inventory_tbl WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Product List View -->
    <div class="d-flex justify-between align-center mb-4">
        <div>
            <h2>Product Management</h2>
            <p class="text-secondary">Manage your inventory products</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="deleteMultipleProducts()" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Delete Selected
            </button>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add Product
            </a>
        </div>
    </div>
    
    <!-- Search and Filters -->
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
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Products (<?php echo count($products); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
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
                                <tr>
                                    <td><input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>"></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['productName']); ?></strong>
                                        <?php if (!empty($product['description'])): ?>
                                            <br><small class="text-secondary"><?php echo htmlspecialchars($product['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($product['quantity'] <= 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($product['quantity'] <= 10): ?>
                                            <span class="badge badge-warning"><?php echo $product['quantity']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?php echo $product['quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>₹<?php echo number_format($product['costPrice'], 2); ?></td>
                                    <td>₹<?php echo number_format($product['selling_price'] ?? $product['costPrice'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['shelfLocation']); ?></td>
                                    <td>
                                        <?php 
                                        $expiryDate = $product['expiryDate'];
                                        if ($expiryDate && $expiryDate !== '0000-00-00'):
                                            $isExpired = strtotime($expiryDate) <= time();
                                            $class = $isExpired ? 'badge-danger' : 'badge-success';
                                        ?>
                                            <span class="badge <?php echo $class; ?>">
                                                <?php echo date('M j, Y', strtotime($expiryDate)); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (strtotime($product['expiryDate']) <= time() && $product['expiryDate'] !== '0000-00-00'): ?>
                                            <span class="badge badge-danger">Expired</span>
                                        <?php elseif ($product['quantity'] <= 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($product['quantity'] <= 10): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Are you sure you want to delete this product?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Product Form -->
    <div class="d-flex justify-between align-center mb-4">
        <div>
            <h2><?php echo $action === 'add' ? 'Add New Product' : 'Edit Product'; ?></h2>
            <p class="text-secondary">Fill in the product details</p>
        </div>
        <a href="products.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to List
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" data-validate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="productName" class="form-label">Product Name *</label>
                        <input type="text" id="productName" name="productName" class="form-control" 
                               value="<?php echo htmlspecialchars($editProduct['productName'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" id="category" name="category" class="form-control" 
                               value="<?php echo htmlspecialchars($editProduct['category'] ?? ''); ?>"
                               placeholder="e.g., Tablet, Syrup, Injection">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity" class="form-label">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="0"
                               value="<?php echo $editProduct['quantity'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="shelfLocation" class="form-label">Shelf Location</label>
                        <input type="text" id="shelfLocation" name="shelfLocation" class="form-control"
                               value="<?php echo htmlspecialchars($editProduct['shelfLocation'] ?? ''); ?>"
                               placeholder="e.g., A1, B2, C3">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="costPrice" class="form-label">Cost Price *</label>
                        <input type="number" id="costPrice" name="costPrice" class="form-control" min="0" step="0.01"
                               value="<?php echo $editProduct['costPrice'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sellingPrice" class="form-label">Selling Price</label>
                        <input type="number" id="sellingPrice" name="sellingPrice" class="form-control" min="0" step="0.01"
                               value="<?php echo $editProduct['selling_price'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="expiryDate" class="form-label">Expiry Date</label>
                        <input type="date" id="expiryDate" name="expiryDate" class="form-control"
                               value="<?php echo $editProduct['expiryDate'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Additional product details..."><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?php echo $action === 'add' ? 'Add Product' : 'Update Product'; ?>
                    </button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}
</script>

<?php require_once 'includes/footer.php'; ?>