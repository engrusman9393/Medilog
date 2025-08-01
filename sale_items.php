<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$error = '';
$success = '';

// Verify sale exists
if ($sale_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            header('Location: sales.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add') {
            $medicine_id = intval($_POST['medicine_id']);
            $quantity = intval($_POST['quantity']);
            $unit_price = floatval($_POST['unit_price']);
            $total_price = $quantity * $unit_price;

            // Check if medicine exists and has enough stock
            $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
            $stmt->execute([$medicine_id]);
            $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$medicine) {
                $error = "Selected medicine not found.";
            } elseif ($medicine['stock_quantity'] < $quantity) {
                $error = "Insufficient stock. Available: " . $medicine['stock_quantity'];
            } else {
                // Add sale item
                $stmt = $pdo->prepare("
                    INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$sale_id, $medicine_id, $quantity, $unit_price, $total_price]);

                // Update medicine stock
                $stmt = $pdo->prepare("
                    UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?
                ");
                $stmt->execute([$quantity, $medicine_id]);

                // Recalculate sale totals
                updateSaleTotals($pdo, $sale_id);
                
                $success = "Item added successfully.";
            }
        } elseif ($action === 'edit' && $item_id > 0) {
            $quantity = intval($_POST['quantity']);
            $unit_price = floatval($_POST['unit_price']);
            $total_price = $quantity * $unit_price;

            // Get current item details to restore stock
            $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $current_item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($current_item) {
                // Restore previous stock
                $stmt = $pdo->prepare("
                    UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id = ?
                ");
                $stmt->execute([$current_item['quantity'], $current_item['medicine_id']]);

                // Check new stock requirement
                $stmt = $pdo->prepare("SELECT stock_quantity FROM medicines WHERE id = ?");
                $stmt->execute([$current_item['medicine_id']]);
                $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($medicine['stock_quantity'] < $quantity) {
                    // Restore original stock
                    $stmt = $pdo->prepare("
                        UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?
                    ");
                    $stmt->execute([$current_item['quantity'], $current_item['medicine_id']]);
                    
                    $error = "Insufficient stock. Available: " . $medicine['stock_quantity'];
                } else {
                    // Update sale item
                    $stmt = $pdo->prepare("
                        UPDATE sale_items SET quantity = ?, unit_price = ?, total_price = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$quantity, $unit_price, $total_price, $item_id]);

                    // Update stock with new quantity
                    $stmt = $pdo->prepare("
                        UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE id = ?
                    ");
                    $stmt->execute([$quantity, $current_item['medicine_id']]);

                    // Recalculate sale totals
                    updateSaleTotals($pdo, $sale_id);
                    
                    $success = "Item updated successfully.";
                    $action = 'list';
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle delete action
if ($action === 'delete' && $item_id > 0) {
    try {
        // Get item details to restore stock
        $stmt = $pdo->prepare("
            SELECT si.*, m.name as medicine_name
            FROM sale_items si
            JOIN medicines m ON si.medicine_id = m.id
            WHERE si.id = ?
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            // Restore stock
            $stmt = $pdo->prepare("
                UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['medicine_id']]);

            // Delete sale item
            $stmt = $pdo->prepare("DELETE FROM sale_items WHERE id = ?");
            $stmt->execute([$item_id]);

            // Recalculate sale totals
            updateSaleTotals($pdo, $sale_id);
            
            $success = "Item deleted successfully.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
    $action = 'list';
}

// Function to update sale totals
function updateSaleTotals($pdo, $sale_id) {
    $stmt = $pdo->prepare("
        SELECT SUM(total_price) as subtotal
        FROM sale_items
        WHERE sale_id = ?
    ");
    $stmt->execute([$sale_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $subtotal = $result['subtotal'] ?? 0;

    // Calculate tax (assuming 8% tax rate)
    $tax_rate = 0.08;
    $tax = $subtotal * $tax_rate;
    $total = $subtotal + $tax;

    $stmt = $pdo->prepare("
        UPDATE sales SET subtotal = ?, tax = ?, total = ?
        WHERE id = ?
    ");
    $stmt->execute([$subtotal, $tax, $total, $sale_id]);
}

// Get sale items
try {
    $stmt = $pdo->prepare("
        SELECT si.*, m.name as medicine_name, m.dosage, m.manufacturer,
               m.stock_quantity as available_stock
        FROM sale_items si
        JOIN medicines m ON si.medicine_id = m.id
        WHERE si.sale_id = ?
        ORDER BY si.id DESC
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get medicines for dropdown
    $stmt = $pdo->prepare("
        SELECT id, name, dosage, manufacturer, selling_price, stock_quantity
        FROM medicines
        WHERE stock_quantity > 0
        ORDER BY name
    ");
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get item for editing
    if ($action === 'edit' && $item_id > 0) {
        $stmt = $pdo->prepare("
            SELECT si.*, m.name as medicine_name, m.dosage
            FROM sale_items si
            JOIN medicines m ON si.medicine_id = m.id
            WHERE si.id = ?
        ");
        $stmt->execute([$item_id]);
        $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Items - Medilog Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .main-content {
            padding: 20px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
        }
        .alert {
            margin-bottom: 20px;
        }
        .form-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <h4 class="text-center text-white mb-4">
                        <i class="fas fa-pills"></i> Medilog
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="medicines.php">
                                <i class="fas fa-pills"></i> Medicines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="sales.php">
                                <i class="fas fa-shopping-cart"></i> Sales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users"></i> Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-list"></i> Sale Items
                        <small class="text-muted">- Sale #<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?></small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="sales.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Sales
                            </a>
                            <a href="print_receipt.php?id=<?php echo $sale_id; ?>" class="btn btn-success" target="_blank">
                                <i class="fas fa-print"></i> Print Receipt
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Sale Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Sale Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Sale Date:</strong><br>
                                        <?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Subtotal:</strong><br>
                                        $<?php echo number_format($sale['subtotal'], 2); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Tax:</strong><br>
                                        $<?php echo number_format($sale['tax'], 2); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Total:</strong><br>
                                        <span class="h5 text-success">$<?php echo number_format($sale['total'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Item Form -->
                <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="form-section">
                    <h5 class="mb-3">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                        <?php echo $action === 'add' ? 'Add Item' : 'Edit Item'; ?>
                    </h5>
                    
                    <form method="POST">
                        <div class="row">
                            <?php if ($action === 'add'): ?>
                            <div class="col-md-4">
                                <label for="medicine_id" class="form-label">Medicine *</label>
                                <select name="medicine_id" id="medicine_id" class="form-select" required>
                                    <option value="">Select Medicine</option>
                                    <?php foreach ($medicines as $medicine): ?>
                                    <option value="<?php echo $medicine['id']; ?>" 
                                            data-price="<?php echo $medicine['selling_price']; ?>"
                                            data-stock="<?php echo $medicine['stock_quantity']; ?>">
                                        <?php echo htmlspecialchars($medicine['name']); ?>
                                        <?php if ($medicine['dosage']): ?>
                                        - <?php echo htmlspecialchars($medicine['dosage']); ?>
                                        <?php endif; ?>
                                        (Stock: <?php echo $medicine['stock_quantity']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="col-md-4">
                                <label class="form-label">Medicine</label>
                                <p class="form-control-plaintext">
                                    <?php echo htmlspecialchars($edit_item['medicine_name']); ?>
                                    <?php if ($edit_item['dosage']): ?>
                                    - <?php echo htmlspecialchars($edit_item['dosage']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <div class="col-md-2">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="number" name="quantity" id="quantity" class="form-control" 
                                       value="<?php echo $action === 'edit' ? $edit_item['quantity'] : ''; ?>"
                                       min="1" required>
                            </div>

                            <div class="col-md-3">
                                <label for="unit_price" class="form-label">Unit Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="unit_price" id="unit_price" class="form-control" 
                                           value="<?php echo $action === 'edit' ? $edit_item['unit_price'] : ''; ?>"
                                           step="0.01" min="0" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Total Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" id="total_display" class="form-control" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $action === 'add' ? 'Add Item' : 'Update Item'; ?>
                                </button>
                                <a href="?sale_id=<?php echo $sale_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Items List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sale Items</h5>
                        <?php if ($action === 'list'): ?>
                        <a href="?sale_id=<?php echo $sale_id; ?>&action=add" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add Item
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sale_items)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No items in this sale yet.</p>
                                <a href="?sale_id=<?php echo $sale_id; ?>&action=add" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add First Item
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Dosage</th>
                                            <th>Manufacturer</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sale_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['medicine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['dosage'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['manufacturer'] ?? 'N/A'); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="?sale_id=<?php echo $sale_id; ?>&action=edit&id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?sale_id=<?php echo $sale_id; ?>&action=delete&id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Are you sure you want to delete this item? This will restore the stock quantity.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-secondary">
                                            <th colspan="5">Subtotal:</th>
                                            <th>$<?php echo number_format($sale['subtotal'], 2); ?></th>
                                            <th></th>
                                        </tr>
                                        <tr class="table-secondary">
                                            <th colspan="5">Tax (8%):</th>
                                            <th>$<?php echo number_format($sale['tax'], 2); ?></th>
                                            <th></th>
                                        </tr>
                                        <tr class="table-dark">
                                            <th colspan="5">TOTAL:</th>
                                            <th>$<?php echo number_format($sale['total'], 2); ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill price when medicine is selected
        document.getElementById('medicine_id')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const stock = selectedOption.getAttribute('data-stock');
            
            if (price) {
                document.getElementById('unit_price').value = price;
                calculateTotal();
            }
            
            // Update quantity max based on stock
            if (stock) {
                document.getElementById('quantity').setAttribute('max', stock);
            }
        });

        // Calculate total price when quantity or price changes
        function calculateTotal() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const unitPrice = parseFloat(document.getElementById('unit_price').value) || 0;
            const total = quantity * unitPrice;
            
            document.getElementById('total_display').value = total.toFixed(2);
        }

        // Add event listeners for calculation
        document.getElementById('quantity')?.addEventListener('input', calculateTotal);
        document.getElementById('unit_price')?.addEventListener('input', calculateTotal);

        // Initial calculation if editing
        <?php if ($action === 'edit'): ?>
        calculateTotal();
        <?php endif; ?>
    </script>
</body>
</html>