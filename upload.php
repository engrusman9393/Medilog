<?php
$pageTitle = 'Upload CSV';
require_once 'includes/header.php';
?>

<div class="d-flex justify-between align-center mb-4">
    <div>
        <h2>Upload Products via CSV</h2>
        <p class="text-secondary">Upload multiple products to your inventory using CSV files</p>
    </div>
    <a href="inventory.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Back to Inventory
    </a>
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
            
            <div id="uploadProgress" style="display: none;" class="mt-3">
                <div class="progress">
                    <div class="progress-bar" style="width: 0%"></div>
                </div>
                <p class="text-center mt-2">Uploading...</p>
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>productName</code></td>
                            <td>Name of the product</td>
                            <td><span class="badge badge-danger">Yes</span></td>
                        </tr>
                        <tr>
                            <td><code>quantity</code></td>
                            <td>Stock quantity</td>
                            <td><span class="badge badge-danger">Yes</span></td>
                        </tr>
                        <tr>
                            <td><code>costPrice</code></td>
                            <td>Cost price per unit</td>
                            <td><span class="badge badge-danger">Yes</span></td>
                        </tr>
                        <tr>
                            <td><code>shelfLocation</code></td>
                            <td>Shelf location (e.g., A1, B2)</td>
                            <td><span class="badge badge-warning">Optional</span></td>
                        </tr>
                        <tr>
                            <td><code>expiryDate</code></td>
                            <td>Expiry date (YYYY-MM-DD or MM/DD/YYYY)</td>
                            <td><span class="badge badge-warning">Optional</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <h5>Sample CSV Content:</h5>
                <div class="bg-light p-3 rounded">
                    <code>
                        productName,quantity,costPrice,shelfLocation,expiryDate<br>
                        Paracetamol 500mg,100,5.50,A1,2025-12-31<br>
                        Amoxicillin 250mg,50,12.00,A2,2024-06-15<br>
                        Vitamin C Tablets,200,8.75,B1,2026-03-20
                    </code>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="sample_products.csv" class="btn btn-sm btn-secondary" download>
                    <i class="fas fa-download"></i>
                    Download Sample CSV
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Upload History -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Recent Uploads</h3>
    </div>
    <div class="card-body">
        <div id="uploadHistory">
            <p class="text-secondary text-center">No recent uploads</p>
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
    
    const formData = new FormData();
    formData.append('file', file);
    
    const submitBtn = document.querySelector('[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> Uploading...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('upload_csv.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.error) {
            showAlert('danger', result.error);
        } else {
            showAlert('success', result.message);
            fileInput.value = '';
            loadUploadHistory();
        }
    } catch (error) {
        showAlert('danger', 'Upload failed: ' + error.message);
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

function loadUploadHistory() {
    // This would load recent upload history from the server
    // For now, we'll just show a placeholder
}

// Load upload history on page load
document.addEventListener('DOMContentLoaded', loadUploadHistory);
</script>

<?php require_once 'includes/footer.php'; ?>