<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single product
                $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    echo json_encode($product);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Product not found']);
                }
            } else {
                // Get all products with optional filters
                $search = $_GET['search'] ?? '';
                $category = $_GET['category'] ?? '';
                $limit = (int)($_GET['limit'] ?? 100);
                $offset = (int)($_GET['offset'] ?? 0);
                
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
                
                $stmt = $conn->prepare("SELECT * FROM inventory_tbl $whereClause ORDER BY productName ASC LIMIT ? OFFSET ?");
                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($products);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $productName = trim($input['productName'] ?? '');
            $quantity = (int)($input['quantity'] ?? 0);
            $costPrice = (float)($input['costPrice'] ?? 0);
            $sellingPrice = (float)($input['sellingPrice'] ?? 0);
            $shelfLocation = trim($input['shelfLocation'] ?? '');
            $expiryDate = $input['expiryDate'] ?? null;
            $category = trim($input['category'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (empty($productName) || $quantity < 0 || $costPrice < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input data']);
                break;
            }
            
            $stmt = $conn->prepare("INSERT INTO inventory_tbl (productName, quantity, costPrice, selling_price, shelfLocation, expiryDate, category, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$productName, $quantity, $costPrice, $sellingPrice, $shelfLocation, $expiryDate, $category, $description])) {
                $productId = $conn->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Product created successfully', 'id' => $productId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create product']);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $_GET['id'] ?? null;
            
            if (!$productId) {
                http_response_code(400);
                echo json_encode(['error' => 'Product ID required']);
                break;
            }
            
            $productName = trim($input['productName'] ?? '');
            $quantity = (int)($input['quantity'] ?? 0);
            $costPrice = (float)($input['costPrice'] ?? 0);
            $sellingPrice = (float)($input['sellingPrice'] ?? 0);
            $shelfLocation = trim($input['shelfLocation'] ?? '');
            $expiryDate = $input['expiryDate'] ?? null;
            $category = trim($input['category'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (empty($productName) || $quantity < 0 || $costPrice < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input data']);
                break;
            }
            
            $stmt = $conn->prepare("UPDATE inventory_tbl SET productName = ?, quantity = ?, costPrice = ?, selling_price = ?, shelfLocation = ?, expiryDate = ?, category = ?, description = ?, updated_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$productName, $quantity, $costPrice, $sellingPrice, $shelfLocation, $expiryDate, $category, $description, $productId])) {
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update product']);
            }
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                // Delete single product
                $stmt = $conn->prepare("DELETE FROM inventory_tbl WHERE id = ?");
                if ($stmt->execute([$_GET['id']])) {
                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete product']);
                }
            } else {
                // Delete multiple products
                $input = json_decode(file_get_contents('php://input'), true);
                $ids = $input['ids'] ?? [];
                
                if (empty($ids)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No product IDs provided']);
                    break;
                }
                
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $conn->prepare("DELETE FROM inventory_tbl WHERE id IN ($placeholders)");
                
                if ($stmt->execute($ids)) {
                    $deletedCount = $stmt->rowCount();
                    echo json_encode(['success' => true, 'message' => "$deletedCount products deleted successfully"]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete products']);
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>