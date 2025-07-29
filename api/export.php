<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$type = $_GET['type'] ?? 'sales';
$format = $_GET['format'] ?? 'csv';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$db = new Database();
$conn = $db->getConnection();

try {
    $data = [];
    $filename = '';
    $headers = [];
    
    switch ($type) {
        case 'sales':
            $filename = "sales_report_" . date('Y-m-d');
            $headers = ['Sale ID', 'Date', 'Customer Name', 'Customer Phone', 'Items', 'Total Amount', 'Discount', 'Final Amount', 'Payment Method'];
            
            $stmt = $conn->prepare("
                SELECT 
                    s.*,
                    GROUP_CONCAT(CONCAT(si.quantity, 'x ', i.productName) SEPARATOR ', ') as items
                FROM sales_tbl s 
                LEFT JOIN sale_items_tbl si ON s.id = si.sale_id 
                LEFT JOIN inventory_tbl i ON si.product_id = i.id 
                WHERE DATE(s.sale_date) BETWEEN ? AND ?
                GROUP BY s.id 
                ORDER BY s.sale_date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $data[] = [
                    '#' . $row['id'],
                    date('M j, Y g:i A', strtotime($row['sale_date'])),
                    $row['customer_name'] ?: 'Walk-in Customer',
                    $row['customer_phone'] ?: '-',
                    $row['items'],
                    '₹' . number_format($row['total_amount'], 2),
                    '₹' . number_format($row['discount_amount'], 2),
                    '₹' . number_format($row['final_amount'], 2),
                    ucfirst($row['payment_method'])
                ];
            }
            break;
            
        case 'inventory':
            $filename = "inventory_report_" . date('Y-m-d');
            $headers = ['Product Name', 'Category', 'Current Stock', 'Cost Price', 'Selling Price', 'Total Value', 'Shelf Location', 'Expiry Date', 'Status'];
            
            $stmt = $conn->prepare("
                SELECT *,
                    CASE 
                        WHEN quantity <= 0 THEN 'Out of Stock'
                        WHEN quantity <= 10 THEN 'Low Stock'
                        WHEN expiryDate <= CURDATE() AND expiryDate != '0000-00-00' THEN 'Expired'
                        WHEN expiryDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiryDate != '0000-00-00' THEN 'Expiring Soon'
                        ELSE 'In Stock'
                    END as status,
                    (quantity * costPrice) as total_value
                FROM inventory_tbl 
                ORDER BY productName ASC
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $data[] = [
                    $row['productName'],
                    $row['category'] ?: 'N/A',
                    $row['quantity'],
                    '₹' . number_format($row['costPrice'], 2),
                    '₹' . number_format($row['selling_price'] ?? $row['costPrice'], 2),
                    '₹' . number_format($row['total_value'], 2),
                    $row['shelfLocation'],
                    ($row['expiryDate'] && $row['expiryDate'] !== '0000-00-00') ? date('M j, Y', strtotime($row['expiryDate'])) : 'N/A',
                    $row['status']
                ];
            }
            break;
            
        case 'profit':
            $filename = "profit_report_" . date('Y-m-d');
            $headers = ['Date', 'Total Sales', 'Revenue', 'Cost', 'Profit', 'Margin %'];
            
            $stmt = $conn->prepare("
                SELECT 
                    DATE(s.sale_date) as sale_date,
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(s.final_amount) as total_revenue,
                    SUM(si.cost_price * si.quantity) as total_cost,
                    SUM((si.selling_price - si.cost_price) * si.quantity) as total_profit,
                    ROUND(AVG((si.selling_price - si.cost_price) / si.cost_price * 100), 2) as avg_margin_percent
                FROM sales_tbl s
                JOIN sale_items_tbl si ON s.id = si.sale_id
                WHERE DATE(s.sale_date) BETWEEN ? AND ?
                GROUP BY DATE(s.sale_date)
                ORDER BY sale_date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $data[] = [
                    date('M j, Y', strtotime($row['sale_date'])),
                    $row['total_sales'],
                    '₹' . number_format($row['total_revenue'], 2),
                    '₹' . number_format($row['total_cost'], 2),
                    '₹' . number_format($row['total_profit'], 2),
                    $row['avg_margin_percent'] . '%'
                ];
            }
            break;
            
        case 'products':
            $filename = "products_report_" . date('Y-m-d');
            $headers = ['Product Name', 'Category', 'Current Stock', 'Total Sold', 'Revenue', 'Profit', 'Sales Count'];
            
            $stmt = $conn->prepare("
                SELECT 
                    i.productName,
                    i.category,
                    i.quantity as current_stock,
                    COALESCE(SUM(si.quantity), 0) as total_sold,
                    COALESCE(SUM(si.total_amount), 0) as total_revenue,
                    COALESCE(SUM((si.selling_price - si.cost_price) * si.quantity), 0) as total_profit,
                    COUNT(DISTINCT s.id) as sales_count
                FROM inventory_tbl i
                LEFT JOIN sale_items_tbl si ON i.id = si.product_id
                LEFT JOIN sales_tbl s ON si.sale_id = s.id AND DATE(s.sale_date) BETWEEN ? AND ?
                GROUP BY i.id, i.productName, i.category, i.quantity
                ORDER BY total_sold DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $data[] = [
                    $row['productName'],
                    $row['category'] ?: 'N/A',
                    $row['current_stock'],
                    $row['total_sold'],
                    '₹' . number_format($row['total_revenue'], 2),
                    '₹' . number_format($row['total_profit'], 2),
                    $row['sales_count']
                ];
            }
            break;
    }
    
    if ($format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        
    } elseif ($format === 'pdf') {
        // PDF Export (basic HTML to PDF)
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        
        // Simple HTML to PDF conversion (you might want to use a proper PDF library like TCPDF or FPDF)
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>' . ucfirst($type) . ' Report</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                h1 { color: #333; text-align: center; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>MediLog - ' . ucfirst($type) . ' Report</h1>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>';
                
        if ($type !== 'inventory') {
            $html .= '<p>Period: ' . date('M j, Y', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to)) . '</p>';
        }
        
        $html .= '</div>
            <table>
                <thead>
                    <tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
            </table>
        </body>
        </html>';
        
        // For a basic implementation, we'll just output HTML
        // In a production environment, you'd want to use a proper PDF library
        echo $html;
    }
    
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo 'Error generating report: ' . $e->getMessage();
}
?>