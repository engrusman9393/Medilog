<?php
$pageTitle = 'Upload CSV';
require_once 'includes/header.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get recent upload history
$uploadHistory = [];
try {
    // Get recent uploads from database (you might want to create an uploads_log table)
    // For now, we'll show recent products added as a proxy for uploads
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as products_count,
            DATE(created_at) as upload_date,
            MIN(created_at) as first_upload,
            MAX(created_at) as last_upload
        FROM inventory_tbl 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY upload_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $uploadHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors for upload history
}

// Get total products count
$totalProducts = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventory_tbl");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $result['total'];
} catch (Exception $e) {
    // Ignore errors
}
?>

<div class="d-flex justify-between align-center mb-4">
    <div>
        <h2>Upload Products via CSV</h2>
        <p class="text-secondary">Upload multiple products to your inventory using CSV files</p>
    </div>
    <div class="d-flex gap-2">
        <a href="inventory.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Inventory
        </a>
        <a href="products.php?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Single Product
        </a>
    </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
        <div class="stat-label">Total Products in Inventory</div>
    </div>
    <div class="stat-card success">
        <div class="stat-value"><?php echo count($uploadHistory); ?></div>
        <div class="stat-label">Upload Sessions (30 days)</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-value">CSV, Excel</div>
        <div class="stat-label">Supported Formats</div>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <!-- Upload Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Upload CSV File</h3>
        </div>
        <div class="card-body">
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csvFile" class="form-label">Select CSV File</label>
                    <input type="file" id="csvFile" name="file" class="form-control" 
                           accept=".csv,.xlsx,.xls" required>
                    <small class="text-secondary">Supported formats: CSV, Excel (.xlsx, .xls)</small>
                </div>
                
                <div class="form-group">
                    <label for="uploadMode" class="form-label">Upload Mode</label>
                    <select id="uploadMode" name="uploadMode" class="form-control">
                        <option value="add">Add new products (skip duplicates)</option>
                        <option value="update">Update existing products</option>
                        <option value="replace">Replace all data (dangerous!)</option>
                    </select>
                    <small class="text-secondary">Choose how to handle existing products</small>
                </div>
                
                <div class="form-group">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <strong>Important:</strong> Make sure your CSV file has the correct column headers as shown in the sample format.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-upload"></i>
                    Upload File
                </button>
            </form>
            
            <!-- Upload Progress -->
            <div id="uploadProgress" style="display: none;" class="mt-3">
                <div style="background-color: #e9ecef; border-radius: 4px; overflow: hidden;">
                    <div id="progressBar" style="height: 8px; background-color: var(--primary-color); width: 0%; transition: width 0.3s;"></div>
                </div>
                <p class="text-center mt-2" id="progressText">Uploading...</p>
            </div>
            
            <!-- Upload Results -->
            <div id="uploadResults" style="display: none;" class="mt-3">
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> Upload Complete!</h6>
                    <div id="uploadSummary"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- CSV Format Guide -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">CSV Format Guide</h3>
        </div>
        <div class="card-body">
            <p class="mb-3">Your CSV file should have the following columns in this exact order:</p>
            
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Description</th>
                            <th>Required</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>productName</code></td>
                            <td>Name of the product</td>
                            <td><span class="badge badge-danger">Yes</span></td>
                            <td>Paracetamol 500mg</td>
                        </tr>
                        <tr>
                            <td><code>quantity</code></td>
                            <td>Stock quantity</td>
                            <td><span class="badge badge-danger">Yes</span></td>
                            <td>100</td>
                        </tr>
                        <tr>
                            <td><code>costPrice</code></td>
                            <td>Cost price per unit</td>
                            <td><span class="badge badge-danger">Yes</span></td>
                            <td>5.50</td>
                        </tr>
                        <tr>
                            <td><code>shelfLocation</code></td>
                            <td>Shelf location</td>
                            <td><span class="badge badge-warning">Optional</span></td>
                            <td>A1, B2, C3</td>
                        </tr>
                        <tr>
                            <td><code>expiryDate</code></td>
                            <td>Expiry date</td>
                            <td><span class="badge badge-warning">Optional</span></td>
                            <td>2025-12-31</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <h5>Sample CSV Content:</h5>
                <div class="bg-light p-3 rounded" style="font-family: monospace; font-size: 0.875rem;">
                    <div style="color: #28a745; font-weight: bold;">productName,quantity,costPrice,shelfLocation,expiryDate</div>
                    <div>Paracetamol 500mg,100,5.50,A1,2025-12-31</div>
                    <div>Amoxicillin 250mg,50,12.00,A2,2024-06-15</div>
                    <div>Vitamin C Tablets,200,8.75,B1,2026-03-20</div>
                    <div>Ibuprofen 400mg,75,15.25,A3,2025-08-10</div>
                </div>
            </div>
            
            <div class="mt-3 d-flex gap-2">
                <a href="sample_products.csv" class="btn btn-sm btn-success" download>
                    <i class="fas fa-download"></i>
                    Download Sample CSV
                </a>
                <button onclick="validateCSVFormat()" class="btn btn-sm btn-secondary">
                    <i class="fas fa-check"></i>
                    Validate Format
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload History -->
<div class="card mt-4">
    <div class="card-header">
        <div class="d-flex justify-between align-center">
            <h3 class="card-title">Recent Upload Activity</h3>
            <button onclick="refreshUploadHistory()" class="btn btn-sm btn-secondary">
                <i class="fas fa-refresh"></i>
                Refresh
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="uploadHistory">
            <?php if (empty($uploadHistory)): ?>
                <div class="text-center text-secondary py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No recent upload activity found.</p>
                    <p><small>Upload your first CSV file to see history here.</small></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Products Added</th>
                                <th>Time Range</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploadHistory as $entry): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($entry['upload_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $entry['products_count']; ?> products</span>
                                    </td>
                                    <td>
                                        <small class="text-secondary">
                                            <?php echo date('g:i A', strtotime($entry['first_upload'])); ?>
                                            <?php if ($entry['first_upload'] !== $entry['last_upload']): ?>
                                                - <?php echo date('g:i A', strtotime($entry['last_upload'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i> Complete
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tips and Best Practices -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Tips & Best Practices</h3>
    </div>
    <div class="card-body">
        <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div>
                <h6><i class="fas fa-lightbulb text-warning"></i> Data Preparation</h6>
                <ul class="list-unstyled">
                    <li>• Ensure product names are unique</li>
                    <li>• Use consistent date format (YYYY-MM-DD)</li>
                    <li>• Remove empty rows and columns</li>
                    <li>• Validate numeric values (quantities, prices)</li>
                </ul>
            </div>
            <div>
                <h6><i class="fas fa-shield-alt text-success"></i> Security & Backup</h6>
                <ul class="list-unstyled">
                    <li>• Always backup your data before bulk uploads</li>
                    <li>• Test with small files first</li>
                    <li>• Review upload summary carefully</li>
                    <li>• Keep original CSV files for reference</li>
                </ul>
            </div>
            <div>
                <h6><i class="fas fa-rocket text-primary"></i> Performance</h6>
                <ul class="list-unstyled">
                    <li>• Maximum 1000 products per file</li>
                    <li>• File size limit: 5MB</li>
                    <li>• Use UTF-8 encoding for special characters</li>
                    <li>• Avoid Excel formulas in CSV files</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('warning', 'Please select a file');
        return;
    }
    
    // Validate file size (5MB limit)
    if (file.size > 5 * 1024 * 1024) {
        showAlert('danger', 'File size too large. Maximum allowed size is 5MB.');
        return;
    }
    
    // Show progress
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadResults').style.display = 'none';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('uploadMode', document.getElementById('uploadMode').value);
    
    const submitBtn = document.querySelector('[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> Uploading...';
    submitBtn.disabled = true;
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 30;
        if (progress > 90) progress = 90;
        updateProgress(progress, 'Processing CSV file...');
    }, 500);
    
    try {
        const response = await fetch('upload_csv.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        clearInterval(progressInterval);
        updateProgress(100, 'Upload complete!');
        
        setTimeout(() => {
            document.getElementById('uploadProgress').style.display = 'none';
            
            if (result.error) {
                showAlert('danger', result.error);
            } else {
                document.getElementById('uploadResults').style.display = 'block';
                document.getElementById('uploadSummary').innerHTML = `
                    <div class="d-flex justify-between">
                        <span>Products processed:</span>
                        <strong>${result.processed || 'N/A'}</strong>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Successfully added:</span>
                        <strong class="text-success">${result.inserted || 'N/A'}</strong>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Errors:</span>
                        <strong class="text-danger">${result.errors || 0}</strong>
                    </div>
                `;
                
                showAlert('success', result.message);
                fileInput.value = '';
                refreshUploadHistory();
            }
        }, 1000);
        
    } catch (error) {
        clearInterval(progressInterval);
        showAlert('danger', 'Upload failed: ' + error.message);
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

function updateProgress(percent, text) {
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressText').textContent = text;
}

function validateCSVFormat() {
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('warning', 'Please select a CSV file first');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const csv = e.target.result;
        const lines = csv.split('\n');
        
        if (lines.length < 2) {
            showAlert('danger', 'CSV file must have at least a header row and one data row');
            return;
        }
        
        const header = lines[0].toLowerCase().replace(/\r/g, '');
        const expectedColumns = ['productname', 'quantity', 'costprice'];
        const actualColumns = header.split(',').map(col => col.trim());
        
        const missingColumns = expectedColumns.filter(col => !actualColumns.includes(col));
        
        if (missingColumns.length > 0) {
            showAlert('danger', `Missing required columns: ${missingColumns.join(', ')}`);
        } else {
            showAlert('success', `CSV format is valid! Found ${lines.length - 1} data rows.`);
        }
    };
    
    reader.readAsText(file);
}

function refreshUploadHistory() {
    // Reload the page to refresh upload history
    // In a real implementation, you'd make an AJAX call
    location.reload();
}

// Auto-refresh upload history every 30 seconds if upload is in progress
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(refreshUploadHistory, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

// File input change handler
document.getElementById('csvFile').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        console.log(`Selected file: ${fileName} (${fileSize} MB)`);
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add drag and drop functionality
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('csvFile');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadForm.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadForm.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadForm.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        uploadForm.classList.add('drag-over');
    }
    
    function unhighlight(e) {
        uploadForm.classList.remove('drag-over');
    }
    
    uploadForm.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            fileInput.files = files;
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    }
});
</script>

<style>
.drag-over {
    border: 2px dashed var(--primary-color) !important;
    background-color: rgba(37, 99, 235, 0.05) !important;
}

.list-unstyled {
    padding-left: 0;
    list-style: none;
}

.list-unstyled li {
    padding: 0.25rem 0;
    color: var(--text-secondary);
}
</style>

<?php require_once 'includes/footer.php'; ?>