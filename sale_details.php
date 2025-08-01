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
            case 'update_status':
                $receipt_id = intval($_POST['receipt_id']);
                $status = $_POST['status'];
                $payment_method = $_POST['payment_method'];
                $paid_amount = floatval($_POST['paid_amount']);
                
                if ($receipt_id > 0 && in_array($status, ['pending', 'completed', 'cancelled'])) {
                    $stmt = $pdo->prepare("
                        UPDATE receipts 
                        SET status = ?, payment_method = ?, paid_amount = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($stmt->execute([$status, $payment_method, $paid_amount, $receipt_id])) {
                        $message = "Sale status updated successfully!";
                    } else {
                        $error = "Error updating sale status.";
                    }
                } else {
                    $error = "Invalid status or receipt ID.";
                }
                break;
                
            case 'add_note':
                $receipt_id = intval($_POST['receipt_id']);
                $note = trim($_POST['note']);
                
                if ($receipt_id > 0 && !empty($note)) {
                    $stmt = $pdo->prepare("
                        UPDATE receipts 
                        SET notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($stmt->execute([date('Y-m-d H:i:s') . ' - ' . $note, $receipt_id])) {
                        $message = "Note added successfully!";
                    } else {
                        $error = "Error adding note.";
                    }
                } else {
                    $error = "Please enter a valid note.";
                }
                break;
        }
    }
}

// Get receipt ID from URL parameter
$receipt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receipt_id <= 0) {
    echo "Invalid receipt ID";
    exit();
}

// Fetch receipt data with customer information
$stmt = $pdo->prepare("
    SELECT r.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, 
           c.address as customer_address, u.username as created_by_user
    FROM receipts r
    LEFT JOIN customers c ON r.customer_id = c.id
    LEFT JOIN users u ON r.created_by = u.id
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
    SELECT ri.*, p.name as product_name, p.code as product_code, p.category_id,
           c.name as category_name
    FROM receipt_items ri
    LEFT JOIN products p ON ri.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE ri.receipt_id = ?
    ORDER BY ri.created_at ASC
");
$stmt->execute([$receipt_id]);
$receipt_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($receipt_items as $item) {
    $subtotal += $item['quantity'] * $item['unit_price'];
    $total_items += $item['quantity'];
}

$tax_amount = $receipt['tax_amount'] ?? 0;
$discount_amount = $receipt['discount_amount'] ?? 0;
$total_amount = $receipt['total_amount'] ?? $subtotal + $tax_amount - $discount_amount;
$paid_amount = $receipt['paid_amount'] ?? 0;
$change_amount = $paid_amount - $total_amount;

// Get payment methods for dropdown
$payment_methods = ['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'Mobile Payment', 'Check'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Details - Medilog</title>
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
        .status-badge {
            font-size: 0.875rem;
        }
        .info-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .total-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .item-card {
            border-left: 4px solid #007bff;
        }
        .notes-section {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-receipt"></i> Sale Details</h2>
                    <div>
                        <a href="sales.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Sales
                        </a>
                        <a href="sale_items.php?receipt_id=<?php echo $receipt_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Items
                        </a>
                        <a href="print_receipt.php?id=<?php echo $receipt_id; ?>" class="btn btn-success" target="_blank">
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

                <div class="row">
                    <!-- Sale Information -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Sale Information</h5>
                                <span class="badge bg-<?php 
                                    echo $receipt['status'] == 'completed' ? 'success' : 
                                        ($receipt['status'] == 'cancelled' ? 'danger' : 'warning'); 
                                ?> status-badge">
                                    <?php echo ucfirst($receipt['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <strong>Receipt #:</strong> <?php echo str_pad($receipt['id'], 6, '0', STR_PAD_LEFT); ?>
                                        </div>
                                        <div class="info-row">
                                            <strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($receipt['created_at'])); ?>
                                        </div>
                                        <div class="info-row">
                                            <strong>Created By:</strong> <?php echo htmlspecialchars($receipt['created_by_user']); ?>
                                        </div>
                                        <div class="info-row">
                                            <strong>Payment Method:</strong> <?php echo htmlspecialchars($receipt['payment_method'] ?? 'Not specified'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-row">
                                            <strong>Customer:</strong> <?php echo htmlspecialchars($receipt['customer_name']); ?>
                                        </div>
                                        <div class="info-row">
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($receipt['customer_phone']); ?>
                                        </div>
                                        <div class="info-row">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($receipt['customer_email']); ?>
                                        </div>
                                        <div class="info-row">
                                            <strong>Address:</strong> <?php echo htmlspecialchars($receipt['customer_address']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sale Items -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Sale Items (<?php echo count($receipt_items); ?> items)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($receipt_items)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                        <p>No items in this sale.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Category</th>
                                                    <th>Code</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Price</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($receipt_items as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                                                        <td><?php echo $item['quantity']; ?></td>
                                                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                                        <td><strong>$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <?php if (!empty($receipt['notes'])): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Notes</h5>
                                </div>
                                <div class="card-body">
                                    <div class="notes-section">
                                        <?php echo nl2br(htmlspecialchars($receipt['notes'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Summary -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calculator"></i> Payment Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="total-section">
                                    <div class="row mb-2">
                                        <div class="col-6">Subtotal:</div>
                                        <div class="col-6 text-end">$<?php echo number_format($subtotal, 2); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Tax (<?php echo $receipt['tax_rate'] ?? 0; ?>%):</div>
                                        <div class="col-6 text-end">$<?php echo number_format($tax_amount, 2); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Discount:</div>
                                        <div class="col-6 text-end">-$<?php echo number_format($discount_amount, 2); ?></div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Total:</strong></div>
                                        <div class="col-6 text-end"><strong>$<?php echo number_format($total_amount, 2); ?></strong></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Paid:</div>
                                        <div class="col-6 text-end">$<?php echo number_format($paid_amount, 2); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>Change:</strong></div>
                                        <div class="col-6 text-end"><strong>$<?php echo number_format($change_amount, 2); ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-cogs"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                    <i class="fas fa-edit"></i> Update Status
                                </button>
                                <button class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                    <i class="fas fa-plus"></i> Add Note
                                </button>
                                <a href="sale_items.php?receipt_id=<?php echo $receipt_id; ?>" class="btn btn-warning w-100 mb-2">
                                    <i class="fas fa-edit"></i> Edit Items
                                </a>
                                <a href="print_receipt.php?id=<?php echo $receipt_id; ?>" class="btn btn-success w-100" target="_blank">
                                    <i class="fas fa-print"></i> Print Receipt
                                </a>
                            </div>
                        </div>

                        <!-- Sale Statistics -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <h4 class="text-primary mb-0"><?php echo count($receipt_items); ?></h4>
                                            <small class="text-muted">Items</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <h4 class="text-success mb-0"><?php echo $total_items; ?></h4>
                                            <small class="text-muted">Quantity</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Sale Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="receipt_id" value="<?php echo $receipt_id; ?>">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="pending" <?php echo $receipt['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $receipt['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $receipt['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="form-select">
                                <option value="">Select Payment Method</option>
                                <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo $method; ?>" <?php echo $receipt['payment_method'] == $method ? 'selected' : ''; ?>>
                                        <?php echo $method; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="paid_amount" class="form-label">Amount Paid</label>
                            <input type="number" name="paid_amount" id="paid_amount" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $paid_amount; ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div class="modal fade" id="addNoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_note">
                        <input type="hidden" name="receipt_id" value="<?php echo $receipt_id; ?>">
                        
                        <div class="mb-3">
                            <label for="note" class="form-label">Note</label>
                            <textarea name="note" id="note" class="form-control" rows="4" 
                                      placeholder="Enter your note here..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate change when paid amount changes
        document.getElementById('paid_amount').addEventListener('input', function() {
            const paidAmount = parseFloat(this.value) || 0;
            const totalAmount = <?php echo $total_amount; ?>;
            const change = paidAmount - totalAmount;
            
            // You can display the change amount somewhere if needed
            console.log('Change: $' + change.toFixed(2));
        });
    </script>
</body>
</html>