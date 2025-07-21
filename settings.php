<?php
session_start();
// Add authentication check here if needed

// Database connection
$host = 'localhost';
$dbname = 'librarymanagement'; // Match your dashboard database name
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_message = "Connection failed: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_general_settings'])) {
        $library_name = trim($_POST['library_name']);
        $library_address = trim($_POST['library_address']);
        $library_phone = trim($_POST['library_phone']);
        $library_email = trim($_POST['library_email']);
        $max_books_per_member = intval($_POST['max_books_per_member']);
        $loan_duration_days = intval($_POST['loan_duration_days']);
        $fine_per_day = floatval($_POST['fine_per_day']);
        
        try {
            // Create settings table if it doesn't exist
            $create_table_sql = "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) UNIQUE,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $pdo->exec($create_table_sql);
            
            $settings = [
                'library_name' => $library_name,
                'library_address' => $library_address,
                'library_phone' => $library_phone,
                'library_email' => $library_email,
                'max_books_per_member' => $max_books_per_member,
                'loan_duration_days' => $loan_duration_days,
                'fine_per_day' => $fine_per_day
            ];
            
            foreach ($settings as $key => $value) {
                $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key, $value]);
            }
            
            $success_message = "General settings saved successfully!";
        } catch(PDOException $e) {
            $error_message = "Error saving settings: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['backup_database'])) {
        // Database backup functionality
        $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = 'backups/' . $backup_file;
        
        // Create backups directory if it doesn't exist
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }
        
        try {
            // For Windows XAMPP
            $mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
            if (file_exists($mysqldump_path)) {
                $command = "\"$mysqldump_path\" --host=$host --user=$username --password=$password $dbname > \"$backup_path\"";
            } else {
                $command = "mysqldump --host=$host --user=$username --password=$password $dbname > \"$backup_path\"";
            }
            
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($backup_path)) {
                $success_message = "Database backup created successfully: $backup_file";
            } else {
                $error_message = "Error creating database backup. Please check if mysqldump is available.";
            }
        } catch(Exception $e) {
            $error_message = "Error creating backup: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        $category_description = trim($_POST['category_description']);
        
        try {
            // Create categories table if it doesn't exist
            $create_table_sql = "CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($create_table_sql);
            
            $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_name, $category_description]);
            
            $success_message = "Category added successfully!";
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $error_message = "Category already exists!";
            } else {
                $error_message = "Error adding category: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $category_id = intval($_POST['category_id']);
        
        try {
            $sql = "DELETE FROM categories WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_id]);
            
            $success_message = "Category deleted successfully!";
        } catch(PDOException $e) {
            $error_message = "Error deleting category: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['create_tables'])) {
        try {
            // Create all required tables
            $tables = [
                "CREATE TABLE IF NOT EXISTS books (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    author VARCHAR(255) NOT NULL,
                    isbn VARCHAR(20),
                    category VARCHAR(100),
                    publisher VARCHAR(255),
                    publication_year INT,
                    copies INT DEFAULT 1,
                    available_copies INT DEFAULT 1,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE,
                    phone VARCHAR(20),
                    address TEXT,
                    membership_date DATE,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS book_issues (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    book_id INT,
                    member_id INT,
                    issue_date DATE,
                    due_date DATE,
                    return_date DATE NULL,
                    fine DECIMAL(10,2) DEFAULT 0,
                    status ENUM('issued', 'returned', 'overdue') DEFAULT 'issued',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
                    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) UNIQUE,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(255) UNIQUE,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS activities (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    activity_type VARCHAR(100),
                    description TEXT,
                    book_id INT NULL,
                    member_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL,
                    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
                )"
            ];
            
            foreach ($tables as $table_sql) {
                $pdo->exec($table_sql);
            }
            
            // Insert default settings
            $default_settings = [
                'library_name' => 'My Library',
                'max_books_per_member' => '5',
                'loan_duration_days' => '14',
                'fine_per_day' => '2.00'
            ];
            
            foreach ($default_settings as $key => $value) {
                $sql = "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key, $value]);
            }
            
            $success_message = "All database tables created successfully!";
        } catch(PDOException $e) {
            $error_message = "Error creating tables: " . $e->getMessage();
        }
    }
}

// Fetch current settings
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch(PDOException $e) {
    // Use default values if table doesn't exist
    $current_settings = [
        'library_name' => 'My Library',
        'max_books_per_member' => '5',
        'loan_duration_days' => '14',
        'fine_per_day' => '2.00'
    ];
}

// Fetch categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Categories table might not exist yet
}

// Fetch backup files
$backup_files = [];
if (is_dir('backups')) {
    $files = array_diff(scandir('backups'), array('.', '..'));
    $backup_files = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'sql';
    });
    rsort($backup_files); // Sort newest first
}

// Get database statistics
$db_stats = [];
try {
    $tables = ['books', 'members', 'categories', 'settings', 'book_issues', 'activities'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $db_stats[$table] = [
                'count' => $stmt->fetchColumn(),
                'status' => 'Active'
            ];
        } catch(Exception $e) {
            $db_stats[$table] = [
                'count' => 'N/A',
                'status' => 'Missing'
            ];
        }
    }
} catch(Exception $e) {
    // Handle if database connection fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Library Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            display: inline-block;
            margin-right: 2rem;
        }

        .user-info {
            float: right;
            margin-top: 5px;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 80px;
            width: 250px;
            height: calc(100vh - 80px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar li {
            border-bottom: 1px solid #eee;
        }

        .sidebar a {
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            color: #333;
            transition: background 0.3s;
        }

        .sidebar a:hover, .sidebar a.active {
            background: #f8f9fa;
            color: #667eea;
        }

        .sidebar i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .settings-tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 1rem;
            background: white;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
        }

        .tab-button.active {
            background: #f8f9fa;
            border-bottom-color: #667eea;
            color: #667eea;
        }

        .tab-button:hover {
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
            font-size: 1rem;
        }

        .btn:hover {
            background: #5a6fd8;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.9rem;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .setting-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .setting-card h4 {
            margin-bottom: 0.5rem;
            color: #667eea;
        }

        .categories-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }

        .backup-files {
            max-height: 300px;
            overflow-y: auto;
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #ddd;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-book"></i> Library Management System</h1>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> Welcome, Admin
            <a href="logout.php" class="btn btn-small" style="margin-left: 10px;">Logout</a>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Manage Members</a></li>
            <li><a href="issue-book.php"><i class="fas fa-hand-holding"></i> Issue Book</a></li>
            <li><a href="return-book.php"><i class="fas fa-undo"></i> Return Book</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-cog"></i> System Settings</h2>
            <p>Configure your library management system settings and preferences</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <button class="tab-button active" onclick="openTab(event, 'general')">
                <i class="fas fa-sliders-h"></i> General Settings
            </button>
            <button class="tab-button" onclick="openTab(event, 'categories')">
                <i class="fas fa-tags"></i> Categories
            </button>
            <button class="tab-button" onclick="openTab(event, 'database')">
                <i class="fas fa-database"></i> Database Setup
            </button>
            <button class="tab-button" onclick="openTab(event, 'backup')">
                <i class="fas fa-download"></i> Backup
            </button>
            <button class="tab-button" onclick="openTab(event, 'system')">
                <i class="fas fa-info-circle"></i> System Info
            </button>
        </div>

        <!-- General Settings Tab -->
        <div id="general" class="tab-content active">
            <h3><i class="fas fa-sliders-h"></i> General Settings</h3>
            <form method="POST" action="">
                <div class="settings-grid">
                    <div>
                        <h4>Library Information</h4>
                        <div class="form-group">
                            <label for="library_name">Library Name</label>
                            <input type="text" id="library_name" name="library_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['library_name'] ?? 'My Library'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="library_address">Address</label>
                            <textarea id="library_address" name="library_address" class="form-control" rows="3"><?php echo htmlspecialchars($current_settings['library_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="library_phone">Phone</label>
                                <input type="tel" id="library_phone" name="library_phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['library_phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="library_email">Email</label>
                                <input type="email" id="library_email" name="library_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['library_email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4>Loan Settings</h4>
                        <div class="form-group">
                            <label for="max_books_per_member">Maximum Books per Member</label>
                            <input type="number" id="max_books_per_member" name="max_books_per_member" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['max_books_per_member'] ?? '5'); ?>" min="1" max="20" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_duration_days">Loan Duration (Days)</label>
                            <input type="number" id="loan_duration_days" name="loan_duration_days" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['loan_duration_days'] ?? '14'); ?>" min="1" max="365" required>
                        </div>
                        <div class="form-group">
                            <label for="fine_per_day">Fine per Day (₹)</label>
                            <input type="number" id="fine_per_day" name="fine_per_day" class="form-control" step="0.01" 
                                   value="<?php echo htmlspecialchars($current_settings['fine_per_day'] ?? '2.00'); ?>" min="0" required>
                        </div>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 2rem;">
                    <button type="submit" name="save_general_settings" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Categories Tab -->
        <div id="categories" class="tab-content">
            <h3><i class="fas fa-tags"></i> Manage Categories</h3>
            
            <div class="settings-grid">
                <div>
                    <h4>Add New Category</h4>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category_description">Description</label>
                            <textarea id="category_description" name="category_description" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" name="add_category" class="btn">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </form>
                </div>
                
                <div>
                    <h4>Existing Categories</h4>
                    <div class="categories-list">
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <div class="category-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                        <?php if ($category['description']): ?>
                                            <br><small><?php echo htmlspecialchars($category['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" name="delete_category" class="btn btn-small btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this category?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No categories found. Add some categories to get started.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Setup Tab -->
        <div id="database" class="tab-content">
            <h3><i class="fas fa-database"></i> Database Setup</h3>
            
            <div class="setting-card" style="margin-bottom: 2rem;">
                <h4>Create Database Tables</h4>
                <p>Click the button below to create all necessary database tables for the library management system.</p>
                <form method="POST" action="" style="margin-top: 1rem;">
                    <button type="submit" name="create_tables" class="btn btn-success" 
                            onclick="return confirm('This will create all necessary database tables. Continue?')">
                        <i class="fas fa-database"></i> Create Tables
                    </button>
                </form>
            </div>

            <h4>Database Tables Status</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Records</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($db_stats as $table => $stats): ?>
                        <tr>
                            <td><?php echo ucfirst($table); ?></td>
                            <td>
                                <?php if ($stats['status'] === 'Active'): ?>
                                    <span style="color: green;">✅ Active</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ Missing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $stats['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Backup Tab -->
        <div id="backup" class="tab-content">
            <h3><i class="fas fa-download"></i> Backup & Restore</h3>
            
            <div class="settings-grid">
                <div>
                    <h4>Create Backup</h4>
                    <p>Create a backup of your entire database including all books, members, and transaction data.</p>
                    <form method="POST" action="">
                        <button type="submit" name="backup_database" class="btn btn-warning">
                            <i class="fas fa-download"></i> Create Backup Now
                        </button>
                    </form>
                </div>
                
                <div>
                    <h4>Available Backups</h4>
                    <div class="backup-files">
                        <?php if (!empty($backup_files)): ?>
                            <?php foreach ($backup_files as $backup): ?>
                                <div class="backup-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($backup); ?></strong>
                                        <br><small><?php echo date('M d, Y H:i', filemtime('backups/' . $backup)); ?></small>
                                    </div>
                                    <div>
                                        <a href="backups/<?php echo htmlspecialchars($backup); ?>" class="btn btn-small" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No backup files found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info Tab -->
        <div id="system" class="tab-content">
            <h3><i class="fas fa-info-circle"></i> System Information</h3>
            
            <div class="stats-overview">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $db_stats['books']['count'] ?? 0; ?></div>
                    <div>Total Books</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $db_stats['members']['count'] ?? 0; ?></div>
                    <div>Total Members</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $db_stats['categories']['count'] ?? 0; ?></div>
                    <div>Categories</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format(disk_free_space('.') / 1024 / 1024, 0); ?>MB</div>
                    <div>Free Space</div>
                </div>
            </div>
            
            <div class="settings-grid">
                <div class="setting-card">
                    <h4>Database Information</h4>
                    <p><strong>Host:</strong> <?php echo htmlspecialchars($host); ?></p>
                    <p><strong>Database:</strong> <?php echo htmlspecialchars($dbname); ?></p>
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>MySQL Version:</strong> <?php 
                        try {
                            echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                        } catch(Exception $e) { echo "Unknown"; }
                    ?></p>
                </div>
                
                <div class="setting-card">
                    <h4>System Status</h4>
                    <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    <p><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
                    <p><strong>Max Upload Size:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                    <p><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tab buttons
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].classList.remove("active");
            }
            
            // Show the selected tab and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Clear form values when successfully submitted
        <?php if (isset($success_message)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Only clear category form, not settings form
            if ('<?php echo isset($_POST["add_category"]) ? "true" : "false"; ?>' === 'true') {
                document.getElementById('category_name').value = '';
                document.getElementById('category_description').value = '';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>