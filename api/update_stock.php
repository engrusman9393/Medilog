<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$productId = (int)($_POST['productId'] ?? 0);
$stockAction = $_POST['stockAction'] ?? '';
$stockQuantity = (int)($_POST['stockQuantity'] ?? 0);
$stockReason = trim($_POST['stockReason'] ?? '');

if (!$productId || !$stockAction || $stockQuantity < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get current product details
    $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit();
    }
    
    $currentStock = $product['quantity'];
    $newStock = 0;
    
    switch ($stockAction) {
        case 'add':
            $newStock = $currentStock + $stockQuantity;
            break;
        case 'remove':
            $newStock = max(0, $currentStock - $stockQuantity);
            break;
        case 'set':
            $newStock = $stockQuantity;
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid stock action']);
            exit();
    }
    
    $conn->beginTransaction();
    
    // Update product stock
    $stmt = $conn->prepare("UPDATE inventory_tbl SET quantity = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStock, $productId]);
    
    // Log stock movement (create stock_movements_tbl if needed)
    $stmt = $conn->prepare("INSERT INTO stock_movements_tbl (product_id, action_type, quantity_before, quantity_after, quantity_changed, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $quantityChanged = $newStock - $currentStock;
    $user = getUserInfo();
    $stmt->execute([$productId, $stockAction, $currentStock, $newStock, $quantityChanged, $stockReason, $user['id']]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Stock updated successfully',
        'old_stock' => $currentStock,
        'new_stock' => $newStock
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>