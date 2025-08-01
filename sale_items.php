<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $category_id = intval($_POST['category_id']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $cost_price = floatval($_POST['cost_price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $min_stock = intval($_POST['min_stock']);
                $unit = trim($_POST['unit']);
                
                if (empty($name) || empty($code)) {
                    $error = "Name and Code are required fields.";
                } else {
                    // Check if code already exists
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
                    $stmt->execute([$code]);
                    if ($stmt->fetch()) {
                        $error = "Product code already exists.";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO products (name, code, category_id, description, price, cost_price, stock_quantity, min_stock, unit, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        if ($stmt->execute([$name, $code, $category_id, $description, $price, $cost_price, $stock_quantity, $min_stock, $unit])) {
                            $success = "Product added successfully.";
                        } else {
                            $error = "Error adding product.";
                        }
                    }
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $category_id = intval($_POST['category_id']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $cost_price = floatval($_POST['cost_price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $min_stock = intval($_POST['min_stock']);
                $unit = trim($_POST['unit']);
                
                if (empty($name) || empty($code)) {
                    $error = "Name and Code are required fields.";
                } else {
                    // Check if code already exists for other products
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ? AND id != ?");
                    $stmt->execute([$code, $id]);
                    if ($stmt->fetch()) {
                        $error = "Product code already exists.";
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET name = ?, code = ?, category_id = ?, description = ?, price = ?, cost_price = ?, 
                                stock_quantity = ?, min_stock = ?, unit = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$name, $code, $category_id, $description, $price, $cost_price, $stock_quantity, $min_stock, $unit, $id])) {
                            $success = "Product updated successfully.";
                        } else {
                            $error = "Error updating product.";
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = "Product deleted successfully.";
                } else {
                    $error = "Error deleting product.";
                }
                break;
        }
    }
}

// Get categories for dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause 
    ORDER BY p.name 
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Items - Medilog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stock-low { color: #dc3545; font-weight: bold; }
        .stock-ok { color: #28a745; }
        .stock-warning { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sale Items</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Add New Product
                        </button>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-secondary">Search</button>
                        </form>
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="sale_items.php" class="btn btn-outline-secondary">Clear Filters</a>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['code']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $stock_class = '';
                                        if ($product['stock_quantity'] <= $product['min_stock']) {
                                            $stock_class = 'stock-low';
                                        } elseif ($product['stock_quantity'] <= ($product['min_stock'] * 2)) {
                                            $stock_class = 'stock-warning';
                                        } else {
                                            $stock_class = 'stock-ok';
                                        }
                                        ?>
                                        <span class="<?php echo $stock_class; ?>">
                                            <?php echo $product['stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                    <td>
                                        <?php if ($product['stock_quantity'] <= $product['min_stock']): ?>
                                            <span class="badge bg-danger">Low Stock</span>
                                        <?php elseif ($product['stock_quantity'] <= ($product['min_stock'] * 2)): ?>
                                            <span class="badge bg-warning">Warning</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Code *</label>
                                    <input type="text" name="code" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" name="unit" class="form-control" placeholder="e.g., pieces, boxes">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Selling Price *</label>
                                    <input type="number" name="price" class="form-control" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cost Price</label>
                                    <input type="number" name="cost_price" class="form-control" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" name="stock_quantity" class="form-control" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Minimum Stock Level</label>
                                    <input type="number" name="min_stock" class="form-control" value="10">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name *</label>
                                    <input type="text" name="name" id="edit_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Code *</label>
                                    <input type="text" name="code" id="edit_code" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" id="edit_category_id" class="form-select">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" name="unit" id="edit_unit" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Selling Price *</label>
                                    <input type="number" name="price" id="edit_price" class="form-control" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cost Price</label>
                                    <input type="number" name="cost_price" id="edit_cost_price" class="form-control" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" name="stock_quantity" id="edit_stock_quantity" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Minimum Stock Level</label>
                                    <input type="number" name="min_stock" id="edit_min_stock" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the product "<span id="deleteProductName"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteProductId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(id) {
            // Fetch product data via AJAX and populate the edit modal
            fetch('ajax/get_product.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_code').value = data.code;
                    document.getElementById('edit_category_id').value = data.category_id;
                    document.getElementById('edit_price').value = data.price;
                    document.getElementById('edit_cost_price').value = data.cost_price;
                    document.getElementById('edit_stock_quantity').value = data.stock_quantity;
                    document.getElementById('edit_min_stock').value = data.min_stock;
                    document.getElementById('edit_unit').value = data.unit;
                    document.getElementById('edit_description').value = data.description;
                    
                    new bootstrap.Modal(document.getElementById('editProductModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product data');
                });
        }
        
        function deleteProduct(id, name) {
            document.getElementById('deleteProductId').value = id;
            document.getElementById('deleteProductName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteProductModal')).show();
        }
    </script>
</body>
</html>