<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Today's Sales
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(final_amount), 0) as total FROM sales_tbl WHERE DATE(sale_date) = ?");
    $stmt->execute([$date]);
    $todaySales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Today's Profit
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM((si.selling_price - si.cost_price) * si.quantity), 0) as profit 
        FROM sale_items_tbl si 
        JOIN sales_tbl s ON si.sale_id = s.id 
        WHERE DATE(s.sale_date) = ?
    ");
    $stmt->execute([$date]);
    $todayProfit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Out of Stock Products
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_tbl WHERE quantity <= 0");
    $stmt->execute();
    $outOfStock = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Expired Medicines
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_tbl WHERE expiryDate <= CURDATE() AND expiryDate != '0000-00-00'");
    $stmt->execute();
    $expiredMedicines = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent Sales
    $stmt = $conn->prepare("
        SELECT s.*, 
               GROUP_CONCAT(CONCAT(si.quantity, 'x ', i.productName) SEPARATOR ', ') as items
        FROM sales_tbl s 
        LEFT JOIN sale_items_tbl si ON s.id = si.sale_id 
        LEFT JOIN inventory_tbl i ON si.product_id = i.id 
        WHERE DATE(s.sale_date) = ?
        GROUP BY s.id 
        ORDER BY s.sale_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$date]);
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low Stock Products
    $stmt = $conn->prepare("SELECT * FROM inventory_tbl WHERE quantity > 0 AND quantity <= 10 ORDER BY quantity ASC LIMIT 5");
    $stmt->execute();
    $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Sales Trend (last 12 months)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(sale_date, '%Y-%m') as month,
            COUNT(*) as sales_count,
            SUM(final_amount) as total_amount
        FROM sales_tbl 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Selling Products (current month)
    $stmt = $conn->prepare("
        SELECT 
            i.productName,
            SUM(si.quantity) as total_sold,
            SUM(si.total_amount) as total_revenue
        FROM sale_items_tbl si
        JOIN inventory_tbl i ON si.product_id = i.id
        JOIN sales_tbl s ON si.sale_id = s.id
        WHERE MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())
        GROUP BY si.product_id, i.productName
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'todaySales' => $todaySales['total'],
        'todaySalesCount' => $todaySales['count'],
        'todayProfit' => $todayProfit['profit'],
        'outOfStock' => $outOfStock['count'],
        'expiredMedicines' => $expiredMedicines['count'],
        'recentSales' => $recentSales,
        'lowStock' => $lowStock,
        'monthlySales' => $monthlySales,
        'topProducts' => $topProducts,
        'date' => $date
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>