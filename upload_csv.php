<?php
// Prevent any output before JSON response
ob_start();

// Disable error display to prevent HTML interference
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include required files
require_once 'config/database.php';
require_once 'includes/auth.php';

// Set JSON headers early
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to safely send JSON response
function sendJsonResponse($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check authentication
if (!isLoggedIn()) {
    sendJsonResponse(['error' => 'Authentication required. Please log in.']);
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method. Only POST is allowed.']);
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)', 
        UPLOAD_ERR_PARTIAL => 'File partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
    ];
    
    $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMessage = $uploadErrors[$errorCode] ?? 'Unknown upload error';
    sendJsonResponse(['error' => $errorMessage]);
}

try {
    // Get file details
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $uploadMode = $_POST['uploadMode'] ?? 'add';

    // Validate file type
    if (!in_array($fileExt, ['csv', 'txt'])) {
        sendJsonResponse(['error' => 'Invalid file type. Please upload a CSV file.']);
    }

    // Validate file size (5MB limit)
    if ($fileSize > 5 * 1024 * 1024) {
        sendJsonResponse(['error' => 'File size too large. Maximum allowed size is 5MB.']);
    }

    // Read file content
    $fileContent = file_get_contents($fileTmpName);
    if ($fileContent === false) {
        sendJsonResponse(['error' => 'Failed to read the uploaded file.']);
    }

    // Normalize line endings and remove BOM
    $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent);
    $fileContent = trim($fileContent);
    
    if (substr($fileContent, 0, 3) === "\xEF\xBB\xBF") {
        $fileContent = substr($fileContent, 3);
    }

    // Split into lines and filter empty ones
    $lines = explode("\n", $fileContent);
    $lines = array_filter($lines, function($line) {
        $line = trim($line);
        return !empty($line) && !preg_match('/^,*$/', $line);
    });

    if (empty($lines)) {
        sendJsonResponse(['error' => 'The CSV file is empty or contains no valid data.']);
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
        sendJsonResponse(['error' => 'CSV file must contain at least a header row and one data row.']);
    }

    // Extract and validate headers
    $headers = array_map('trim', array_shift($csvData));
    $headers = array_map('strtolower', $headers);

    // Check required columns
    $requiredColumns = ['productname', 'quantity', 'costprice'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $required) {
        if (!in_array($required, $headers)) {
            $missingColumns[] = $required;
        }
    }

    if (!empty($missingColumns)) {
        sendJsonResponse(['error' => 'Missing required columns: ' . implode(', ', $missingColumns)]);
    }

    // Get column indexes
    $productNameIndex = array_search('productname', $headers);
    $quantityIndex = array_search('quantity', $headers);
    $costPriceIndex = array_search('costprice', $headers);
    $shelfLocationIndex = array_search('shelflocation', $headers);
    $expiryDateIndex = array_search('expirydate', $headers);

    // Initialize database
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // Initialize counters
    $totalProcessed = 0;
    $successfulInserts = 0;
    $successfulUpdates = 0;
    $skippedRows = 0;
    $errorCount = 0;
    $errors = [];

    // Prepare statements
    $checkStmt = $conn->prepare("SELECT id FROM inventory_tbl WHERE productName = ?");
    $insertStmt = $conn->prepare("INSERT INTO inventory_tbl (productName, quantity, costPrice, shelfLocation, expiryDate, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $updateStmt = $conn->prepare("UPDATE inventory_tbl SET quantity = ?, costPrice = ?, shelfLocation = ?, expiryDate = ?, updated_at = NOW() WHERE productName = ?");

    // Process each row
    foreach ($csvData as $rowIndex => $row) {
        $totalProcessed++;
        $actualRowNumber = $rowIndex + 2;

        try {
            // Extract values
            $productName = isset($row[$productNameIndex]) ? trim($row[$productNameIndex]) : '';
            $quantity = isset($row[$quantityIndex]) ? trim($row[$quantityIndex]) : '0';
            $costPrice = isset($row[$costPriceIndex]) ? trim($row[$costPriceIndex]) : '0';
            $shelfLocation = isset($row[$shelfLocationIndex]) ? trim($row[$shelfLocationIndex]) : '';
            $expiryDate = isset($row[$expiryDateIndex]) ? trim($row[$expiryDateIndex]) : '';

            // Validate product name
            if (empty($productName)) {
                throw new Exception("Product name cannot be empty");
            }

            // Validate quantity
            if (!is_numeric($quantity) || $quantity < 0) {
                throw new Exception("Quantity must be a non-negative number");
            }
            $quantity = (int)$quantity;

            // Validate cost price
            if (!is_numeric($costPrice) || $costPrice < 0) {
                throw new Exception("Cost price must be a non-negative number");
            }
            $costPrice = (float)$costPrice;

            // Validate expiry date
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
                    $expiryDate = null;
                }
            } else {
                $expiryDate = null;
            }

            // Check if product exists
            $checkStmt->execute([$productName]);
            $existingProduct = $checkStmt->fetch();

            if ($existingProduct) {
                if ($uploadMode === 'add') {
                    $skippedRows++;
                } elseif ($uploadMode === 'update') {
                    if ($updateStmt->execute([$quantity, $costPrice, $shelfLocation, $expiryDate, $productName])) {
                        $successfulUpdates++;
                    } else {
                        throw new Exception("Failed to update existing product");
                    }
                }
            } else {
                if ($insertStmt->execute([$productName, $quantity, $costPrice, $shelfLocation, $expiryDate])) {
                    $successfulInserts++;
                } else {
                    throw new Exception("Failed to insert new product");
                }
            }

        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Row {$actualRowNumber}: " . $e->getMessage();

            if ($errorCount > 10) {
                $errors[] = "Too many errors encountered. Processing stopped.";
                break;
            }
        }
    }

    // Commit transaction
    $conn->commit();

    // Build success message
    $messages = [];
    if ($successfulInserts > 0) $messages[] = "{$successfulInserts} new products added";
    if ($successfulUpdates > 0) $messages[] = "{$successfulUpdates} products updated";
    if ($skippedRows > 0) $messages[] = "{$skippedRows} products skipped (already exist)";
    if ($errorCount > 0) $messages[] = "{$errorCount} products had errors";

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
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    sendJsonResponse(['error' => $e->getMessage()]);
}
?>