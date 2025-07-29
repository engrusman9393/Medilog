# MediLog Admin Panel

A comprehensive pharmacy and medical store management system with a beautiful, modern admin panel built with PHP and MySQL.

## Features

### 🏥 **Complete Pharmacy Management**
- **Product Management**: Add, edit, delete products with categories
- **Inventory Tracking**: Real-time stock monitoring with alerts
- **Sales Management**: Complete POS system with receipt generation
- **CSV Import**: Bulk product upload via CSV files
- **Multi-user Support**: Pharmacy/shop registration system

### 📊 **Analytics & Reporting**
- **Dashboard**: Real-time analytics with sales, profit, and stock insights
- **Advanced Reports**: Sales, inventory, profit analysis, and product performance
- **Export Options**: PDF and CSV export for all reports
- **Date Filtering**: Custom date range reporting

### 🔐 **Security & Authentication**
- **User Registration**: Shop/pharmacy registration with different business types
- **Secure Login**: Password hashing and session management
- **Role-based Access**: User-specific data isolation

### 💡 **Smart Features**
- **Stock Alerts**: Low stock and expiry date notifications
- **Search & Filter**: Advanced filtering across all modules
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile
- **Modern UI**: Beautiful, intuitive interface with dark/light themes

## Technology Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Custom CSS with Font Awesome icons
- **Charts**: Chart.js for analytics visualization

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for dependencies)

### Database Setup

1. Create a new MySQL database:
```sql
CREATE DATABASE medicgeg_medilog_app_db;
```

2. Create the required tables:

```sql
-- Users table for authentication
CREATE TABLE users_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    shop_name VARCHAR(255),
    user_type ENUM('pharmacy', 'medical_store', 'clinic', 'hospital') DEFAULT 'pharmacy',
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Inventory table for products
CREATE TABLE inventory_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    productName VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 0,
    costPrice DECIMAL(10, 2) NOT NULL,
    selling_price DECIMAL(10, 2) DEFAULT NULL,
    shelfLocation VARCHAR(50),
    expiryDate DATE,
    category VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_name (productName),
    INDEX idx_category (category),
    INDEX idx_expiry (expiryDate)
);

-- Sales table for transactions
CREATE TABLE sales_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(20),
    total_amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    final_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'card', 'upi', 'cheque') DEFAULT 'cash',
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users_tbl(id),
    INDEX idx_sale_date (sale_date),
    INDEX idx_customer (customer_name)
);

-- Sale items table for detailed transaction records
CREATE TABLE sale_items_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales_tbl(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES inventory_tbl(id),
    INDEX idx_sale_id (sale_id),
    INDEX idx_product_id (product_id)
);

-- Stock movements table for tracking inventory changes
CREATE TABLE stock_movements_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    action_type ENUM('add', 'remove', 'set') NOT NULL,
    quantity_before INT NOT NULL,
    quantity_after INT NOT NULL,
    quantity_changed INT NOT NULL,
    reason VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES inventory_tbl(id),
    FOREIGN KEY (created_by) REFERENCES users_tbl(id),
    INDEX idx_product_id (product_id),
    INDEX idx_created_at (created_at)
);
```

### Application Setup

1. **Clone or download** the project files to your web server directory

2. **Update database configuration** in `config/database.php`:
```php
private $servername = "localhost";
private $username = "your_db_username";
private $password = "your_db_password";
private $dbname = "medicgeg_medilog_app_db";
```

3. **Set proper file permissions**:
```bash
chmod 755 /path/to/your/project
chmod -R 644 /path/to/your/project/*
chmod -R 755 /path/to/your/project/uploads (if you create this directory)
```

4. **Create a default admin user** (optional):
```sql
INSERT INTO users_tbl (name, email, password, shop_name, user_type) 
VALUES ('Admin User', 'admin@medilog.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MediLog Pharmacy', 'pharmacy');
-- Default password is 'password'
```

## Usage

### Getting Started

1. **Access the application** via your web browser
2. **Register a new account** or login with existing credentials
3. **Set up your pharmacy/shop profile** in Settings
4. **Start adding products** via Products page or CSV upload
5. **Begin making sales** through the Sales module

### Key Workflows

#### Adding Products
1. Go to **Products** → **Add Product**
2. Fill in product details (name, quantity, prices, etc.)
3. Set category and shelf location for better organization
4. Save the product

#### Bulk Import via CSV
1. Go to **Upload CSV**
2. Download the sample CSV template
3. Fill in your product data following the format
4. Upload the CSV file

#### Making a Sale
1. Go to **Sales** → **New Sale**
2. Search and add products to the cart
3. Enter customer information (optional)
4. Apply discounts if needed
5. Select payment method and complete sale

#### Generating Reports
1. Go to **Reports & Analytics**
2. Select report type (Sales, Inventory, Profit, Products)
3. Set date range filters
4. Export as PDF or CSV

## File Structure

```
medilog-admin/
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet
│   └── js/
│       └── main.js            # JavaScript functionality
├── api/                       # API endpoints
│   ├── products.php           # Product CRUD operations
│   ├── dashboard.php          # Dashboard data
│   ├── export.php             # Report exports
│   └── update_stock.php       # Stock management
├── config/
│   └── database.php           # Database configuration
├── includes/
│   ├── header.php             # Common header template
│   ├── footer.php             # Common footer template
│   └── auth.php               # Authentication functions
├── dashboard.php              # Main dashboard
├── products.php               # Product management
├── inventory.php              # Inventory overview
├── sales.php                  # Sales management
├── reports.php                # Reports and analytics
├── upload.php                 # CSV upload interface
├── settings.php               # User settings
├── login.php                  # Login page
├── register.php               # Registration page
├── logout.php                 # Logout handler
├── upload_csv.php             # CSV processing (existing)
└── sample_products.csv        # Sample CSV template
```

## API Endpoints

### Products API (`api/products.php`)
- `GET /api/products.php` - List all products
- `GET /api/products.php?id=1` - Get single product
- `POST /api/products.php` - Create new product
- `PUT /api/products.php?id=1` - Update product
- `DELETE /api/products.php?id=1` - Delete product

### Dashboard API (`api/dashboard.php`)
- `GET /api/dashboard.php` - Get dashboard statistics
- `GET /api/dashboard.php?date=2024-01-15` - Get data for specific date

### Export API (`api/export.php`)
- `GET /api/export.php?type=sales&format=csv` - Export sales as CSV
- `GET /api/export.php?type=inventory&format=pdf` - Export inventory as PDF

## Customization

### Database Table Names
Replace the following table names in the code with your actual table names:
- `users_tbl` → Your users table
- `inventory_tbl` → Your products/inventory table  
- `sales_tbl` → Your sales table
- `sale_items_tbl` → Your sale items table
- `stock_movements_tbl` → Your stock movements table

### Styling
- Modify `assets/css/style.css` for custom styling
- Update CSS variables in `:root` for color scheme changes
- Add your logo by replacing the Font Awesome icon in the header

### Features
- Add new report types in `reports.php`
- Extend product fields in the database and forms
- Implement additional user roles and permissions

## Security Considerations

1. **Change default database credentials**
2. **Use HTTPS** in production
3. **Implement proper input validation**
4. **Regular database backups**
5. **Keep PHP and MySQL updated**
6. **Implement rate limiting** for API endpoints

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Support

For support and questions:
- Create an issue on GitHub
- Check the documentation
- Review the code comments for implementation details

---

**MediLog Admin Panel** - Making pharmacy management simple and efficient! 🏥💊