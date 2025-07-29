<?php
// Include the database connection file
require_once 'config/database.php';
require_once 'includes/auth.php';

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to prevent stray output
ob_start();

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check authentication
if (!isLoggedIn()) {
    ob_end_clean();
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Initialize response array
$response = [];

// Handle POST requests for file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if file is uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error occurred.');
        }

        // Get file details
        $file = $_FILES['file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uploadMode = $_POST['uploadMode'] ?? 'add';

        // Validate file size (5MB limit)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size too large. Maximum allowed size is 5MB.');
        }

        // Initialize data and headers
        $data = [];
        $headers = [];

        // Handle CSV files
        if ($fileExt === 'csv') {
            // Read file content and normalize line endings
            $fileContent = file_get_contents($fileTmpName);
            if ($fileContent === false) {
                throw new Exception('Failed to read uploaded file.');
            }
            
            $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent); // Normalize line endings
            $fileContent = trim($fileContent); // Remove leading/trailing whitespace

            // Remove BOM if present
            if (substr($fileContent, 0, 3) === "\xEF\xBB\xBF") {
                $fileContent = substr($fileContent, 3);
            }

            // Split into lines and filter out empty or invalid lines
            $lines = array_filter(explode("\n", $fileContent), function($line) {
                $line = trim($line);
                // Skip empty lines or lines with only commas
                return !empty($line) && !preg_match('/^,*$/', $line);
            });

            // Parse CSV lines
            $data = array_map('str_getcsv', $lines);
            if (empty($data)) {
                throw new Exception('No valid data found in CSV file.');
            }

            // Extract headers (first row)
            $headers = array_shift($data);
            $headers = array_map('trim', $headers);

            // Validate required headers
            $requiredHeaders = ['productName', 'quantity', 'costPrice'];
            $missingHeaders = [];
            foreach ($requiredHeaders as $required) {
                if (!in_array($required, $headers)) {
                    $missingHeaders[] = $required;
                }
            }
            
            if (!empty($missingHeaders)) {
                throw new Exception('Missing required columns: ' . implode(', ', $missingHeaders));
            }

            // Filter out invalid data rows
            $data = array_filter($data, function($row, $index) use ($headers) {
                // Skip empty arrays or rows with insufficient columns
                if (!is_array($row) || count($row) < count($headers)) {
                    return false;
                }

                // Check if all elements are null or empty
                $nonEmptyValues = array_filter($row, function($value) {
                    return !is_null($value) && trim($value) !== '';
                });
                if (empty($nonEmptyValues)) {
                    return false;
                }

                return true;
            }, ARRAY_FILTER_USE_BOTH);
            $data = array_values($data); // Reindex array

            // Check if any valid data remains
            if (empty($data)) {
                throw new Exception('No valid data rows found after filtering.');
            }
        } else {
            throw new Exception('Unsupported file type: ' . $fileExt . '. Please upload a CSV file.');
        }

        // Validate data rows
        foreach ($data as $index => $row) {
            if (empty(trim($row[0]))) {
                throw new Exception("Product name cannot be empty in row " . ($index + 2));
            }
        }

        // Get database connection
        $db = new Database();
        $conn = $db->getConnection();

        // Begin transaction
        $conn->beginTransaction();

        $insertedRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $errorRows = 0;
        $errors = [];

        // Prepare SQL statements
        $insertStmt = $conn->prepare("INSERT INTO inventory_tbl (productName, quantity, costPrice, selling_price, shelfLocation, expiryDate, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $updateStmt = $conn->prepare("UPDATE inventory_tbl SET quantity = ?, costPrice = ?, selling_price = ?, shelfLocation = ?, expiryDate = ?, updated_at = NOW() WHERE productName = ?");
        $checkStmt = $conn->prepare("SELECT id FROM inventory_tbl WHERE productName = ?");

        // Process and insert/update data
        foreach ($data as $index => $row) {
            try {
                $productName = trim($row[0]);
                $quantity = (int)($row[1] ?? 0);
                $costPrice = (float)($row[2] ?? 0);
                $sellingPrice = isset($row[3]) && is_numeric($row[3]) ? (float)$row[3] : $costPrice;
                $shelfLocation = trim($row[4] ?? '');
                $expiryDate = trim($row[5] ?? '');

                // Validation
                if (empty($productName)) {
                    throw new Exception("Product name cannot be empty");
                }
                if ($quantity < 0) {
                    throw new Exception("Quantity cannot be negative");
                }
                if ($costPrice < 0) {
                    throw new Exception("Cost price cannot be negative");
                }

                // Validate expiry date format if provided
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
                        $expiryDate = null; // Set to null if invalid format
                    }
                }

                // Check if product exists
                $checkStmt->execute([$productName]);
                $existingProduct = $checkStmt->fetch();

                if ($existingProduct) {
                    // Product exists
                    if ($uploadMode === 'add') {
                        $skippedRows++;
                        continue; // Skip existing products
                    } elseif ($uploadMode === 'update') {
                        // Update existing product
                        if ($updateStmt->execute([$quantity, $costPrice, $sellingPrice, $shelfLocation, $expiryDate, $productName])) {
                            $updatedRows++;
                        } else {
                            throw new Exception("Failed to update product");
                        }
                    }
                } else {
                    // New product, insert it
                    if ($insertStmt->execute([$productName, $quantity, $costPrice, $sellingPrice, $shelfLocation, $expiryDate])) {
                        $insertedRows++;
                    } else {
                        throw new Exception("Failed to insert product");
                    }
                }

            } catch (Exception $e) {
                $errorRows++;
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                
                // If too many errors, stop processing
                if (count($errors) > 10) {
                    $errors[] = "Too many errors. Processing stopped.";
                    break;
                }
            }
        }

        // Commit transaction
        $conn->commit();

        // Prepare success response
        $totalProcessed = $insertedRows + $updatedRows + $skippedRows + $errorRows;
        $message = "Upload completed successfully!";
        
        if ($insertedRows > 0) {
            $message .= " {$insertedRows} products added.";
        }
        if ($updatedRows > 0) {
            $message .= " {$updatedRows} products updated.";
        }
        if ($skippedRows > 0) {
            $message .= " {$skippedRows} products skipped (already exist).";
        }
        if ($errorRows > 0) {
            $message .= " {$errorRows} products had errors.";
        }

        $response = [
            'success' => true,
            'message' => $message,
            'processed' => $totalProcessed,
            'inserted' => $insertedRows,
            'updated' => $updatedRows,
            'skipped' => $skippedRows,
            'errors' => $errorRows,
            'errorDetails' => $errors
        ];

    } catch (PDOException $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $response = ['error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $response = ['error' => $e->getMessage()];
    }

    // Clean buffer and output JSON
    ob_end_clean();
    echo json_encode($response);
    exit;

} else {
    // Invalid request method
    ob_end_clean();
    echo json_encode(['error' => 'Invalid request method. Only POST is allowed.']);
    exit;
}
?>