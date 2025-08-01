<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $product_id = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                $unit_price = floatval($_POST['unit_price']);
                $receipt_id = intval($_POST['receipt_id']);
                
                if ($product_id > 0 && $quantity > 0 && $unit_price > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO receipt_items (receipt_id, product_id, quantity, unit_price, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    if ($stmt->execute([$receipt_id, $product_id, $quantity, $unit_price])) {
                        $message = "Sale item added successfully!";
                    } else {
                        $error = "Error adding sale item.";
                    }
                } else {
                    $error = "Please fill all required fields correctly.";
                }
                break;
                
            case 'edit':
                $item_id = intval($_POST['item_id']);
                $quantity = intval($_POST['quantity']);
                $unit_price = floatval($_POST['unit_price']);
                
                if ($item_id > 0 && $quantity > 0 && $unit_price > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE receipt_items 
                        SET quantity = ?, unit_price = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($stmt->execute([$quantity, $unit_price, $item_id])) {
                        $message = "Sale item updated successfully!";
                    } else {
                        $error = "Error updating sale item.";
                    }
                } else {
                    $error = "Please fill all required fields correctly.";
                }
                break;
                
            case 'delete':
                $item_id = intval($_POST['item_id']);
                
                if ($item_id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM receipt_items WHERE id = ?");
                    if ($stmt->execute([$item_id])) {
                        $message = "Sale item deleted successfully!";
                    } else {
                        $error = "Error deleting sale item.";
                    }
                } else {
                    $error = "Invalid item ID.";
                }
                break;
        }
    }
}

// Get receipt ID from URL parameter
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;

if ($receipt_id <= 0) {
    echo "Invalid receipt ID";
    exit();
}

// Fetch receipt information
$stmt = $pdo->prepare("
    SELECT r.*, c.name as customer_name
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

// Fetch sale items for this receipt
$stmt = $pdo->prepare("
    SELECT ri.*, p.name as product_name, p.code as product_code, p.stock_quantity
    FROM receipt_items ri
    LEFT JOIN products p ON ri.product_id = p.id
    WHERE ri.receipt_id = ?
    ORDER BY ri.created_at ASC
");
$stmt->execute([$receipt_id]);
$sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available products for dropdown
$stmt = $pdo->prepare("
    SELECT id, name, code, selling_price, stock_quantity
    FROM products 
    WHERE stock_quantity > 0
    ORDER BY name ASC
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($sale_items as $item) {
    $subtotal += $item['quantity'] * $item['unit_price'];
}
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
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .table th {
            background-color: #f8f9fa;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shopping-cart"></i> Sale Items</h2>
                    <div>
                        <a href="sales.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Sales
                        </a>
                        <a href="print_receipt.php?id=<?php echo $receipt_id; ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-print"></i> Print Receipt
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Receipt Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-receipt"></i> Receipt Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Receipt #:</strong> <?php echo str_pad($receipt['id'], 6, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($receipt['created_at'])); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($receipt['customer_name']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?php echo $receipt['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($receipt['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add New Item -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Item</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="receipt_id" value="<?php echo $receipt_id; ?>">
                            
                            <div class="col-md-4">
                                <label for="product_id" class="form-label">Product</label>
                                <select name="product_id" id="product_id" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" 
                                                data-price="<?php echo $product['selling_price']; ?>"
                                                data-stock="<?php echo $product['stock_quantity']; ?>">
                                            <?php echo htmlspecialchars($product['name'] . ' (' . $product['code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="unit_price" class="form-label">Unit Price</label>
                                <input type="number" name="unit_price" id="unit_price" class="form-control" step="0.01" min="0" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Total</label>
                                <input type="text" id="item_total" class="form-control" readonly>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus"></i> Add Item
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sale Items Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Sale Items</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sale_items)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                <p>No items added to this sale yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Code</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sale_items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td>$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>', <?php echo $item['quantity']; ?>, <?php echo $item['unit_price']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-info">
                                            <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                            <td><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Sale Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" id="edit_product_name" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_quantity" class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_unit_price" class="form-label">Unit Price</label>
                            <input type="number" name="unit_price" id="edit_unit_price" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Sale Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item?</p>
                    <p><strong id="delete_item_name"></strong></p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" id="delete_item_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate item total when quantity or price changes
        function calculateTotal() {
            const quantity = document.getElementById('quantity').value;
            const unitPrice = document.getElementById('unit_price').value;
            const total = quantity * unitPrice;
            document.getElementById('item_total').value = '$' + total.toFixed(2);
        }

        // Update unit price when product is selected
        document.getElementById('product_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const stock = selectedOption.getAttribute('data-stock');
            
            if (price) {
                document.getElementById('unit_price').value = price;
                document.getElementById('quantity').max = stock;
                calculateTotal();
            }
        });

        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('unit_price').addEventListener('input', calculateTotal);

        // Edit item functions
        function editItem(itemId, productName, quantity, unitPrice) {
            document.getElementById('edit_item_id').value = itemId;
            document.getElementById('edit_product_name').value = productName;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_unit_price').value = unitPrice;
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        function deleteItem(itemId, productName) {
            document.getElementById('delete_item_id').value = itemId;
            document.getElementById('delete_item_name').textContent = productName;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>