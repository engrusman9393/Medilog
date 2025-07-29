<?php
// Prevent any output before JSON
ob_start();

// Set error reporting but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include required files
require_once 'config/database.php';
require_once 'includes/auth.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to send JSON response and exit
function sendJsonResponse($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Function to send error response
function sendErrorResponse($message) {
    sendJsonResponse(['error' => $message]);
}

// Check if user is logged in
if (!isLoggedIn()) {
    sendErrorResponse('Authentication required. Please log in.');
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method. Only POST is allowed.');
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMessage = $uploadErrors[$errorCode] ?? 'Unknown upload error';
    sendErrorResponse($errorMessage);
}

try {
    // Get uploaded file details
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Get upload mode
    $uploadMode = $_POST['uploadMode'] ?? 'add';
    
    // Validate file extension
    if (!in_array($fileExt, ['csv', 'txt'])) {
        sendErrorResponse('Invalid file type. Please upload a CSV file.');
    }
    
    // Validate file size (5MB limit)
    if ($fileSize > 5 * 1024 * 1024) {
        sendErrorResponse('File size too large. Maximum allowed size is 5MB.');
    }
    
    // Read and parse CSV file
    $fileContent = file_get_contents($fileTmpName);
    if ($fileContent === false) {
        sendErrorResponse('Failed to read the uploaded file.');
    }
    
    // Handle different line endings
    $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent);
    $fileContent = trim($fileContent);
    
    // Remove BOM if present
    if (substr($fileContent, 0, 3) === "\xEF\xBB\xBF") {
        $fileContent = substr($fileContent, 3);
    }
    
    // Split into lines
    $lines = explode("\n", $fileContent);
    $lines = array_filter($lines, function($line) {
        $line = trim($line);
        return !empty($line) && !preg_match('/^,*$/', $line);
    });
    
    if (empty($lines)) {
        sendErrorResponse('The CSV file is empty or contains no valid data.');
    }
    
    // Parse CSV data
    $csvData = [];
    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (!empty($row)) {
            $csvData[] = $row;
        }
    }
    
    if (count($csvData) < 2) {
        sendErrorResponse('CSV file must contain at least a header row and one data row.');
    }
    
    // Extract header and data
    $headers = array_map('trim', array_shift($csvData));
    $headers = array_map('strtolower', $headers);
    
    // Validate required columns
    $requiredColumns = ['productname', 'quantity', 'costprice'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $required) {
        if (!in_array($required, $headers)) {
            $missingColumns[] = $required;
        }
    }
    
    if (!empty($missingColumns)) {
        sendErrorResponse('Missing required columns: ' . implode(', ', $missingColumns));
    }
    
    // Get column indexes
    $productNameIndex = array_search('productname', $headers);
    $quantityIndex = array_search('quantity', $headers);
    $costPriceIndex = array_search('costprice', $headers);
    $shelfLocationIndex = array_search('shelflocation', $headers);
    $expiryDateIndex = array_search('expirydate', $headers);
    
    // Initialize database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Initialize counters
    $totalProcessed = 0;
    $successfulInserts = 0;
    $successfulUpdates = 0;
    $skippedRows = 0;
    $errorCount = 0;
    $errors = [];
    
    // Prepare SQL statements
    $checkExistingStmt = $conn->prepare("SELECT id FROM inventory_tbl WHERE productName = ?");
    $insertStmt = $conn->prepare("INSERT INTO inventory_tbl (productName, quantity, costPrice, shelfLocation, expiryDate, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $updateStmt = $conn->prepare("UPDATE inventory_tbl SET quantity = ?, costPrice = ?, shelfLocation = ?, expiryDate = ?, updated_at = NOW() WHERE productName = ?");
    
    // Process each data row
    foreach ($csvData as $rowIndex => $row) {
        $totalProcessed++;
        $actualRowNumber = $rowIndex + 2; // +2 because we removed header and arrays are 0-indexed
        
        try {
            // Extract data from row
            $productName = isset($row[$productNameIndex]) ? trim($row[$productNameIndex]) : '';
            $quantity = isset($row[$quantityIndex]) ? trim($row[$quantityIndex]) : '0';
            $costPrice = isset($row[$costPriceIndex]) ? trim($row[$costPriceIndex]) : '0';
            $shelfLocation = isset($row[$shelfLocationIndex]) ? trim($row[$shelfLocationIndex]) : '';
            $expiryDate = isset($row[$expiryDateIndex]) ? trim($row[$expiryDateIndex]) : '';
            
            // Validate product name
            if (empty($productName)) {
                throw new Exception("Product name cannot be empty");
            }
            
            // Validate and convert quantity
            if (!is_numeric($quantity) || $quantity < 0) {
                throw new Exception("Quantity must be a non-negative number");
            }
            $quantity = (int)$quantity;
            
            // Validate and convert cost price
            if (!is_numeric($costPrice) || $costPrice < 0) {
                throw new Exception("Cost price must be a non-negative number");
            }
            $costPrice = (float)$costPrice;
            
            // Validate expiry date if provided
            if (!empty($expiryDate)) {
                $dateFormats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];
                $validDate = false;
                
                foreach ($dateFormats as $format) {
                    $dateObj = DateTime::createFromFormat($format, $expiryDate);
                    if ($dateObj && $dateObj->format($format) === $expiryDate) {
                        $expiryDate = $dateObj->format('Y-m-d');
                        $validDate = true;
                        break;
                    }
                }
                
                if (!$validDate) {
                    // If date format is invalid, set to null instead of throwing error
                    $expiryDate = null;
                }
            } else {
                $expiryDate = null;
            }
            
            // Check if product already exists
            $checkExistingStmt->execute([$productName]);
            $existingProduct = $checkExistingStmt->fetch();
            
            if ($existingProduct) {
                // Product exists
                if ($uploadMode === 'add') {
                    // Skip existing products in add mode
                    $skippedRows++;
                } elseif ($uploadMode === 'update') {
                    // Update existing product
                    if ($updateStmt->execute([$quantity, $costPrice, $shelfLocation, $expiryDate, $productName])) {
                        $successfulUpdates++;
                    } else {
                        throw new Exception("Failed to update existing product");
                    }
                }
            } else {
                // New product - insert it
                if ($insertStmt->execute([$productName, $quantity, $costPrice, $shelfLocation, $expiryDate])) {
                    $successfulInserts++;
                } else {
                    throw new Exception("Failed to insert new product");
                }
            }
            
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Row {$actualRowNumber}: " . $e->getMessage();
            
            // Stop processing if too many errors
            if ($errorCount > 10) {
                $errors[] = "Too many errors encountered. Processing stopped.";
                break;
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Prepare success message
    $messages = [];
    if ($successfulInserts > 0) {
        $messages[] = "{$successfulInserts} new products added";
    }
    if ($successfulUpdates > 0) {
        $messages[] = "{$successfulUpdates} products updated";
    }
    if ($skippedRows > 0) {
        $messages[] = "{$skippedRows} products skipped (already exist)";
    }
    if ($errorCount > 0) {
        $messages[] = "{$errorCount} products had errors";
    }
    
    $successMessage = "Upload completed successfully! " . implode(', ', $messages) . ".";
    
    // Send success response
    sendJsonResponse([
        'success' => true,
        'message' => $successMessage,
        'processed' => $totalProcessed,
        'inserted' => $successfulInserts,
        'updated' => $successfulUpdates,
        'skipped' => $skippedRows,
        'errors' => $errorCount,
        'errorDetails' => $errors
    ]);
    
} catch (PDOException $e) {
    // Database error - rollback transaction
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    sendErrorResponse('Database error: ' . $e->getMessage());
    
} catch (Exception $e) {
    // General error - rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    sendErrorResponse($e->getMessage());
}
?>