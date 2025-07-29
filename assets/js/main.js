// Main Admin Panel JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the admin panel
    initializeAdminPanel();
});

function initializeAdminPanel() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // Active navigation highlighting
    setActiveNavigation();
    
    // Initialize modals
    initializeModals();
    
    // Initialize form validations
    initializeFormValidations();
    
    // Initialize data tables
    initializeDataTables();
}

function setActiveNavigation() {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });
}

function initializeModals() {
    // Modal functionality
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const closeBtns = document.querySelectorAll('.close-btn, .modal-close');

    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
            }
        });
    });

    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Close modal when clicking outside
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
}

function initializeFormValidations() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = 'var(--danger-color)';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    field.style.borderColor = 'var(--danger-color)';
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.style.borderColor = '';
}

function initializeDataTables() {
    // Add search functionality to tables
    const searchInputs = document.querySelectorAll('.table-search');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const tableId = this.getAttribute('data-table');
            const table = document.getElementById(tableId);
            filterTable(table, this.value);
        });
    });
}

function filterTable(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// API Helper functions
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'API request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        showAlert('error', error.message);
        throw error;
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    // Insert at the top of content area
    const contentArea = document.querySelector('.content-area');
    if (contentArea) {
        contentArea.insertBefore(alertDiv, contentArea.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Product Management Functions
function deleteProduct(productId) {
    if (confirmDelete('Are you sure you want to delete this product?')) {
        apiRequest(`api/products.php?id=${productId}`, 'DELETE')
            .then(result => {
                showAlert('success', 'Product deleted successfully');
                location.reload();
            })
            .catch(error => {
                showAlert('error', 'Failed to delete product');
            });
    }
}

function deleteMultipleProducts() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const productIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (productIds.length === 0) {
        showAlert('warning', 'Please select products to delete');
        return;
    }
    
    if (confirmDelete(`Are you sure you want to delete ${productIds.length} products?`)) {
        apiRequest('api/products.php', 'DELETE', { ids: productIds })
            .then(result => {
                showAlert('success', `${productIds.length} products deleted successfully`);
                location.reload();
            })
            .catch(error => {
                showAlert('error', 'Failed to delete products');
            });
    }
}

// Export Functions
function exportToPDF(reportType) {
    window.open(`api/export.php?type=${reportType}&format=pdf`, '_blank');
}

function exportToCSV(reportType) {
    window.open(`api/export.php?type=${reportType}&format=csv`, '_blank');
}

// Dashboard Functions
function updateDashboard() {
    const dateFilter = document.getElementById('dateFilter')?.value;
    const url = `api/dashboard.php${dateFilter ? `?date=${dateFilter}` : ''}`;
    
    apiRequest(url)
        .then(data => {
            updateDashboardStats(data);
        })
        .catch(error => {
            showAlert('error', 'Failed to update dashboard');
        });
}

function updateDashboardStats(data) {
    // Update stat cards
    const statElements = {
        'todaySales': document.getElementById('todaySales'),
        'todayProfit': document.getElementById('todayProfit'),
        'outOfStock': document.getElementById('outOfStock'),
        'expiredMedicines': document.getElementById('expiredMedicines')
    };
    
    Object.keys(statElements).forEach(key => {
        if (statElements[key] && data[key] !== undefined) {
            statElements[key].textContent = data[key];
        }
    });
}

// File Upload Functions
function handleFileUpload(inputElement, uploadUrl) {
    const file = inputElement.files[0];
    if (!file) {
        showAlert('warning', 'Please select a file');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    const submitBtn = inputElement.closest('form').querySelector('[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.innerHTML = '<span class="loading"></span> Uploading...';
    submitBtn.disabled = true;
    
    fetch(uploadUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.error) {
            showAlert('error', result.error);
        } else {
            showAlert('success', result.message);
            // Reset form
            inputElement.closest('form').reset();
        }
    })
    .catch(error => {
        showAlert('error', 'Upload failed: ' + error.message);
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}