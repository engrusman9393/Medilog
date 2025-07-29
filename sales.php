<?php
$pageTitle = 'Sales Management';
require_once 'includes/header.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Handle new sale creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'new') {
    $products = $_POST['products'] ?? [];
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $discount = (float)($_POST['discount'] ?? 0);
    
    if (empty($products)) {
        $error = 'Please add at least one product to the sale';
    } else {
        try {
            $conn->beginTransaction();
            
            $total_amount = 0;
            $sale_items = [];
            
            // Validate products and calculate total
            foreach ($products as $item) {
                $product_id = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $selling_price = (float)$item['selling_price'];
                
                if ($quantity <= 0) continue;
                
                // Check product availability
                $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("Product not found");
                }
                
                if ($product['quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for {$product['productName']}. Available: {$product['quantity']}");
                }
                
                $item_total = $quantity * $selling_price;
                $total_amount += $item_total;
                
                $sale_items[] = [
                    'product_id' => $product_id,
                    'product_name' => $product['productName'],
                    'quantity' => $quantity,
                    'selling_price' => $selling_price,
                    'cost_price' => $product['costPrice'],
                    'total' => $item_total
                ];
            }
            
            if (empty($sale_items)) {
                throw new Exception("No valid products in the sale");
            }
            
            // Apply discount
            $discount_amount = ($total_amount * $discount) / 100;
            $final_amount = $total_amount - $discount_amount;
            
            // Insert sale record
            $stmt = $conn->prepare("INSERT INTO sales_tbl (customer_name, customer_phone, total_amount, discount_amount, final_amount, payment_method, sale_date, created_by) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$customer_name, $customer_phone, $total_amount, $discount_amount, $final_amount, $payment_method, $user['id']]);
            $sale_id = $conn->lastInsertId();
            
            // Insert sale items and update inventory
            foreach ($sale_items as $item) {
                // Insert sale item
                $stmt = $conn->prepare("INSERT INTO sale_items_tbl (sale_id, product_id, quantity, selling_price, cost_price, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sale_id, $item['product_id'], $item['quantity'], $item['selling_price'], $item['cost_price'], $item['total']]);
                
                // Update inventory
                $stmt = $conn->prepare("UPDATE inventory_tbl SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $conn->commit();
            $message = "Sale completed successfully! Sale ID: #$sale_id";
            $action = 'list';
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get sales list
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (customer_name LIKE ? OR customer_phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($date_from)) {
        $whereClause .= " AND DATE(sale_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $whereClause .= " AND DATE(sale_date) <= ?";
        $params[] = $date_to;
    }
    
    $stmt = $conn->prepare("SELECT * FROM sales_tbl $whereClause ORDER BY sale_date DESC");
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sales statistics
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(final_amount), 0) as total_revenue,
        COALESCE(AVG(final_amount), 0) as avg_sale_amount,
        COUNT(CASE WHEN DATE(sale_date) = CURDATE() THEN 1 END) as today_sales
        FROM sales_tbl");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get products for new sale
if ($action === 'new') {
    $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE quantity > 0 ORDER BY productName ASC");
    $stmt->execute();
    $available_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Sales Statistics -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['total_sales']); ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-value">₹<?php echo number_format($stats['total_revenue']); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-value">₹<?php echo number_format($stats['avg_sale_amount']); ?></div>
            <div class="stat-label">Average Sale</div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-value"><?php echo number_format($stats['today_sales']); ?></div>
            <div class="stat-label">Today's Sales</div>
        </div>
    </div>

    <div class="d-flex justify-between align-center mb-4">
        <div>
            <h2>Sales Management</h2>
            <p class="text-secondary">Track and manage all your sales transactions</p>
        </div>
        <a href="?action=new" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Sale
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="form-row">
                <input type="hidden" name="action" value="list">
                <div class="form-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by customer name or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <input type="date" name="date_from" class="form-control" placeholder="From Date"
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group">
                    <input type="date" name="date_to" class="form-control" placeholder="To Date"
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <a href="sales.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Sales Table -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-between align-center">
                <h3 class="card-title">Sales History (<?php echo count($sales); ?>)</h3>
                <div class="d-flex gap-2">
                    <button onclick="exportToCSV('sales')" class="btn btn-sm btn-secondary">
                        <i class="fas fa-download"></i>
                        Export CSV
                    </button>
                    <button onclick="exportToPDF('sales')" class="btn btn-sm btn-secondary">
                        <i class="fas fa-file-pdf"></i>
                        Export PDF
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Discount</th>
                            <th>Final Amount</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-secondary">No sales found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><strong>#<?php echo $sale['id']; ?></strong></td>
                                    <td>
                                        <?php if (!empty($sale['customer_name'])): ?>
                                            <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong><br>
                                            <small class="text-secondary"><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                        <?php else: ?>
                                            <span class="text-secondary">Walk-in Customer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($sale['sale_date'])); ?></td>
                                    <td>
                                        <button onclick="showSaleItems(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-list"></i>
                                            View Items
                                        </button>
                                    </td>
                                    <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($sale['discount_amount'] > 0): ?>
                                            ₹<?php echo number_format($sale['discount_amount'], 2); ?>
                                        <?php else: ?>
                                            <span class="text-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>₹<?php echo number_format($sale['final_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $sale['payment_method'] === 'cash' ? 'success' : 'primary'; ?>">
                                            <?php echo ucfirst($sale['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button onclick="printReceipt(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-primary" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button onclick="showSaleDetails(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-secondary" title="View Details">
                                                <i class="fas fa-eye"></i>
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

<?php elseif ($action === 'new'): ?>
    <!-- New Sale Form -->
    <div class="d-flex justify-between align-center mb-4">
        <div>
            <h2>Create New Sale</h2>
            <p class="text-secondary">Add products and complete the sale</p>
        </div>
        <a href="sales.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Sales
        </a>
    </div>
    
    <form method="POST" id="saleForm">
        <div class="row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Products Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Products</h3>
                </div>
                <div class="card-body">
                    <!-- Product Search -->
                    <div class="form-group">
                        <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
                    </div>
                    
                    <!-- Selected Products -->
                    <div id="selectedProducts">
                        <p class="text-secondary text-center">No products selected</p>
                    </div>
                    
                    <!-- Available Products -->
                    <div class="mt-4">
                        <h5>Available Products</h5>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Stock</th>
                                        <th>Price</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="productsList">
                                    <?php foreach ($available_products as $product): ?>
                                        <tr data-product-name="<?php echo strtolower($product['productName']); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['productName']); ?></strong>
                                                <?php if (!empty($product['category'])): ?>
                                                    <br><small class="text-secondary"><?php echo htmlspecialchars($product['category']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $product['quantity'] <= 10 ? 'warning' : 'success'; ?>">
                                                    <?php echo $product['quantity']; ?>
                                                </span>
                                            </td>
                                            <td>₹<?php echo number_format($product['selling_price'] ?? $product['costPrice'], 2); ?></td>
                                            <td>
                                                <button type="button" onclick="addProductToSale(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['productName']); ?>', <?php echo $product['quantity']; ?>, <?php echo $product['selling_price'] ?? $product['costPrice']; ?>)" 
                                                        class="btn btn-sm btn-primary">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sale Summary -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Customer Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="customer_name" class="form-label">Customer Name</label>
                            <input type="text" id="customer_name" name="customer_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="customer_phone" class="form-label">Phone Number</label>
                            <input type="tel" id="customer_phone" name="customer_phone" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Sale Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal">₹0.00</span>
                        </div>
                        <div class="form-group">
                            <label for="discount" class="form-label">Discount (%)</label>
                            <input type="number" id="discount" name="discount" class="form-control" min="0" max="100" value="0" onchange="calculateTotal()">
                        </div>
                        <div class="d-flex justify-between mb-2">
                            <span>Discount Amount:</span>
                            <span id="discountAmount">₹0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-between mb-3">
                            <strong>Total:</strong>
                            <strong id="total">₹0.00</strong>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="form-control">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="upi">UPI</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-full" id="completeSaleBtn" disabled>
                            <i class="fas fa-shopping-cart"></i>
                            Complete Sale
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
let selectedProducts = [];
let productCounter = 0;

// Product search functionality
document.getElementById('productSearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const productRows = document.querySelectorAll('#productsList tr');
    
    productRows.forEach(row => {
        const productName = row.getAttribute('data-product-name');
        if (productName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

function addProductToSale(productId, productName, availableStock, sellingPrice) {
    // Check if product already added
    const existingProduct = selectedProducts.find(p => p.id === productId);
    if (existingProduct) {
        showAlert('warning', 'Product already added to sale');
        return;
    }
    
    const product = {
        id: productId,
        name: productName,
        availableStock: availableStock,
        sellingPrice: sellingPrice,
        quantity: 1,
        total: sellingPrice
    };
    
    selectedProducts.push(product);
    updateSelectedProductsDisplay();
    calculateTotal();
}

function updateSelectedProductsDisplay() {
    const container = document.getElementById('selectedProducts');
    
    if (selectedProducts.length === 0) {
        container.innerHTML = '<p class="text-secondary text-center">No products selected</p>';
        document.getElementById('completeSaleBtn').disabled = true;
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th><th>Action</th></tr></thead><tbody>';
    
    selectedProducts.forEach((product, index) => {
        html += `
            <tr>
                <td>${product.name}</td>
                <td>
                    <input type="number" class="form-control form-control-sm" min="1" max="${product.availableStock}" 
                           value="${product.quantity}" onchange="updateProductQuantity(${index}, this.value)"
                           style="width: 80px;">
                    <input type="hidden" name="products[${index}][product_id]" value="${product.id}">
                    <input type="hidden" name="products[${index}][quantity]" value="${product.quantity}">
                    <input type="hidden" name="products[${index}][selling_price]" value="${product.sellingPrice}">
                </td>
                <td>₹${product.sellingPrice.toFixed(2)}</td>
                <td>₹${product.total.toFixed(2)}</td>
                <td>
                    <button type="button" onclick="removeProductFromSale(${index})" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    document.getElementById('completeSaleBtn').disabled = false;
}

function updateProductQuantity(index, newQuantity) {
    const quantity = parseInt(newQuantity);
    const product = selectedProducts[index];
    
    if (quantity > product.availableStock) {
        showAlert('warning', `Only ${product.availableStock} units available`);
        return;
    }
    
    if (quantity <= 0) {
        removeProductFromSale(index);
        return;
    }
    
    selectedProducts[index].quantity = quantity;
    selectedProducts[index].total = quantity * product.sellingPrice;
    
    updateSelectedProductsDisplay();
    calculateTotal();
}

function removeProductFromSale(index) {
    selectedProducts.splice(index, 1);
    updateSelectedProductsDisplay();
    calculateTotal();
}

function calculateTotal() {
    const subtotal = selectedProducts.reduce((sum, product) => sum + product.total, 0);
    const discountPercent = parseFloat(document.getElementById('discount')?.value || 0);
    const discountAmount = (subtotal * discountPercent) / 100;
    const total = subtotal - discountAmount;
    
    document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
    document.getElementById('discountAmount').textContent = `₹${discountAmount.toFixed(2)}`;
    document.getElementById('total').textContent = `₹${total.toFixed(2)}`;
}

function showSaleItems(saleId) {
    // This would show sale items in a modal
    window.open(`api/sale_items.php?id=${saleId}`, '_blank', 'width=600,height=400');
}

function showSaleDetails(saleId) {
    // This would show full sale details in a modal
    window.open(`api/sale_details.php?id=${saleId}`, '_blank', 'width=800,height=600');
}

function printReceipt(saleId) {
    window.open(`api/print_receipt.php?id=${saleId}`, '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>