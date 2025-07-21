<?php
session_start();
// Add authentication check here if needed

// Database connection
$host = 'localhost';
$dbname = 'librarymanagement';
$username = 'root';
$password = '';

$success = '';
$error = '';
$books = [];
$members = [];
$issued_books = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $create_tables = [
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
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) UNIQUE,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    foreach ($create_tables as $sql) {
        $pdo->exec($sql);
    }
    
    // Insert default settings if not exist
    $default_settings = [
        'loan_duration_days' => '14',
        'max_books_per_member' => '5',
        'fine_per_day' => '2.00'
    ];
    
    foreach ($default_settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['issue_book'])) {
            $book_id = intval($_POST['book_id']);
            $member_id = intval($_POST['member_id']);
            $issue_date = $_POST['issue_date'];
            $notes = trim($_POST['notes']);
            
            // Get loan duration from settings
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'loan_duration_days'");
            $stmt->execute();
            $loan_duration = intval($stmt->fetchColumn()) ?: 14;
            
            // Calculate due date
            $due_date = date('Y-m-d', strtotime($issue_date . ' +' . $loan_duration . ' days'));
            
            // Check if book is available
            $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE id = ?");
            $stmt->execute([$book_id]);
            $available_copies = intval($stmt->fetchColumn());
            
            if ($available_copies > 0) {
                // Check member's current book count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE member_id = ? AND return_date IS NULL");
                $stmt->execute([$member_id]);
                $current_books = intval($stmt->fetchColumn());
                
                // Get max books limit
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_books_per_member'");
                $stmt->execute();
                $max_books = intval($stmt->fetchColumn()) ?: 5;
                
                if ($current_books < $max_books) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Issue the book
                        $stmt = $pdo->prepare("INSERT INTO book_issues (book_id, member_id, issue_date, due_date, notes) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$book_id, $member_id, $issue_date, $due_date, $notes]);
                        
                        // Update book availability
                        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
                        $stmt->execute([$book_id]);
                        
                        $pdo->commit();
                        $success = 'Book issued successfully!';
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $error = 'Error issuing book: ' . $e->getMessage();
                    }
                } else {
                    $error = "Member has reached the maximum limit of $max_books books.";
                }
            } else {
                $error = 'This book is not available for issuing.';
            }
        }
        
        if (isset($_POST['return_book'])) {
            $issue_id = intval($_POST['issue_id']);
            $return_date = $_POST['return_date'];
            $fine = floatval($_POST['fine']);
            
            try {
                $pdo->beginTransaction();
                
                // Get book_id for updating availability
                $stmt = $pdo->prepare("SELECT book_id FROM book_issues WHERE id = ?");
                $stmt->execute([$issue_id]);
                $book_id = $stmt->fetchColumn();
                
                // Update issue record
                $stmt = $pdo->prepare("UPDATE book_issues SET return_date = ?, fine = ?, status = 'returned' WHERE id = ?");
                $stmt->execute([$return_date, $fine, $issue_id]);
                
                // Update book availability
                $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
                $stmt->execute([$book_id]);
                
                $pdo->commit();
                $success = 'Book returned successfully!';
            } catch (Exception $e) {
                $pdo->rollback();
                $error = 'Error returning book: ' . $e->getMessage();
            }
        }
    }
    
    // Fetch available books
    $stmt = $pdo->query("SELECT * FROM books WHERE available_copies > 0 ORDER BY title");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch active members
    $stmt = $pdo->query("SELECT * FROM members WHERE status = 'active' ORDER BY name");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch currently issued books
    $stmt = $pdo->query("
        SELECT bi.*, b.title, b.author, m.name as member_name, m.email,
               DATEDIFF(CURDATE(), bi.due_date) as days_overdue,
               CASE 
                   WHEN bi.due_date < CURDATE() THEN 'overdue'
                   WHEN DATEDIFF(bi.due_date, CURDATE()) <= 3 THEN 'due_soon'
                   ELSE 'active'
               END as issue_status
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        JOIN members m ON bi.member_id = m.id
        WHERE bi.return_date IS NULL
        ORDER BY bi.due_date ASC
    ");
    $issued_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
} catch (PDOException $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
}

// Search functionality
$search_books = isset($_GET['search_books']) ? trim($_GET['search_books']) : '';
$search_members = isset($_GET['search_members']) ? trim($_GET['search_members']) : '';

if ($search_books) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE (title LIKE ? OR author LIKE ? OR isbn LIKE ?) AND available_copies > 0 ORDER BY title");
        $search_term = "%$search_books%";
        $stmt->execute([$search_term, $search_term, $search_term]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Search failed: ' . $e->getMessage();
    }
}

if ($search_members) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE (name LIKE ? OR email LIKE ?) AND status = 'active' ORDER BY name");
        $search_term = "%$search_members%";
        $stmt->execute([$search_term, $search_term]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Search failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Book - Library Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: #333;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            display: inline-block;
            margin-right: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 99;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar li {
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .sidebar a {
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar a:hover, .sidebar a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }

        .sidebar a.active {
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .sidebar i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .stat-card p {
            color: #666;
        }

        .issue-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
            font-weight: 600;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-button:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .tab-content {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .item-card {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }

        .item-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .item-card h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .item-card p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }

        .table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-due-soon {
            background: #fff3cd;
            color: #856404;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(212, 237, 218, 0.9);
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-danger {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 2rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .close:hover {
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .selection-grid {
                grid-template-columns: 1fr;
            }
            
            .issue-tabs {
                flex-direction: column;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            <li><a href="issue-book.php" class="active"><i class="fas fa-hand-holding"></i> Issue Book</a></li>
            <li><a href="return-book.php"><i class="fas fa-undo"></i> Return Book</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h2><i class="fas fa-hand-holding"></i> Issue Book</h2>
            <p>Issue books to library members with automated tracking and due dates</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards fade-in">
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <h3><?php echo count($books); ?></h3>
                <p>Available Books</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo count($members); ?></h3>
                <p>Active Members</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-hand-holding"></i>
                <h3><?php echo count($issued_books); ?></h3>
                <p>Currently Issued</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <h3><?php echo $settings['loan_duration_days'] ?? '14'; ?></h3>
                <p>Loan Duration (Days)</p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Issue Tabs -->
        <div class="issue-tabs fade-in">
            <button class="tab-button active" onclick="openTab(event, 'issue')">
                <i class="fas fa-plus"></i> Issue New Book
            </button>
            <button class="tab-button" onclick="openTab(event, 'current')">
                <i class="fas fa-list"></i> Currently Issued
            </button>
            <button class="tab-button" onclick="openTab(event, 'quick-return')">
                <i class="fas fa-undo"></i> Quick Return
            </button>
        </div>

        <!-- Issue New Book Tab -->
        <div id="issue" class="tab-content active">
            <form method="POST" action="">
                <div class="form-grid">
                    <!-- Book Selection -->
                    <div class="form-section">
                        <h3 style="margin-bottom: 1rem; color: #333;"><i class="fas fa-book"></i> Select Book</h3>
                        <div class="search-box">
                            <input type="text" id="bookSearch" class="search-input" placeholder="Search books by title, author, or ISBN...">
                            <button type="button" onclick="searchBooks()" class="btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        
                        <input type="hidden" id="selected_book_id" name="book_id" required>
                        <div id="bookSelection" class="selection-grid">
                            <?php if (!empty($books)): ?>
                                <?php foreach ($books as $book): ?>
                                    <div class="item-card" onclick="selectBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                        <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                                        <p><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?: 'N/A'); ?></p>
                                        <p><strong>Available:</strong> <?php echo $book['available_copies']; ?> copies</p>
                                        <?php if ($book['isbn']): ?>
                                            <p><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-book"></i>
                                    <h3>No books available</h3>
                                    <p>No books are currently available for issuing.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div id="selectedBookInfo" style="margin-top: 1rem; display: none;">
                            <div class="alert alert-success">
                                <strong>Selected Book:</strong> <span id="selectedBookTitle"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Member Selection -->
                    <div class="form-section">
                        <h3 style="margin-bottom: 1rem; color: #333;"><i class="fas fa-user"></i> Select Member</h3>
                        <div class="search-box">
                            <input type="text" id="memberSearch" class="search-input" placeholder="Search members by name or email...">
                            <button type="button" onclick="searchMembers()" class="btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        
                        <input type="hidden" id="selected_member_id" name="member_id" required>
                        <div id="memberSelection" class="selection-grid">
                            <?php if (!empty($members)): ?>
                                <?php foreach ($members as $member): ?>
                                    <div class="item-card" onclick="selectMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['name']); ?>')">
                                        <h4><?php echo htmlspecialchars($member['name']); ?></h4>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone'] ?: 'N/A'); ?></p>
                                        <p><strong>Member since:</strong> <?php echo date('M Y', strtotime($member['membership_date'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>No active members</h3>
                                    <p>No active members available to issue books.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div id="selectedMemberInfo" style="margin-top: 1rem; display: none;">
                            <div class="alert alert-success">
                                <strong>Selected Member:</strong> <span id="selectedMemberName"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Issue Details -->
                <div class="form-section">
                    <h3 style="margin-bottom: 1rem; color: #333;"><i class="fas fa-calendar"></i> Issue Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="issue_date">Issue Date</label>
                            <input type="date" id="issue_date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="calculated_due_date">Due Date (Auto-calculated)</label>
                            <input type="date" id="calculated_due_date" class="form-control" readonly>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Any special instructions or notes..."></textarea>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" name="issue_book" class="btn" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            <i class="fas fa-hand-holding"></i> Issue Book
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Currently Issued Tab -->
        <div id="current" class="tab-content">
            <div class="form-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3><i class="fas fa-list"></i> Currently Issued Books</h3>
                    <span class="status-badge status-active"><?php echo count($issued_books); ?> books issued</span>
                </div>
                
                <?php if (!empty($issued_books)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Member</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issued_books as $issue): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($issue['title']); ?></strong><br>
                                        <small>by <?php echo htmlspecialchars($issue['author']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($issue['member_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($issue['email']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($issue['issue_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($issue['due_date'])); ?></td>
                                    <td>
                                        <?php if ($issue['issue_status'] == 'overdue'): ?>
                                            <span class="status-badge status-overdue">
                                                Overdue (<?php echo $issue['days_overdue']; ?> days)
                                            </span>
                                        <?php elseif ($issue['issue_status'] == 'due_soon'): ?>
                                            <span class="status-badge status-due-soon">
                                                Due Soon
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="openReturnModal(<?php echo $issue['id']; ?>, '<?php echo addslashes($issue['title']); ?>', '<?php echo addslashes($issue['member_name']); ?>', '<?php echo $issue['days_overdue']; ?>')" class="btn btn-small btn-warning">
                                            <i class="fas fa-undo"></i> Return
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-hand-holding"></i>
                        <h3>No books currently issued</h3>
                        <p>All books have been returned or no books have been issued yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Return Tab -->
        <div id="quick-return" class="tab-content">
            <div class="form-section">
                <h3 style="margin-bottom: 1rem; color: #333;"><i class="fas fa-undo"></i> Quick Return</h3>
                <p style="margin-bottom: 2rem; color: #666;">Select a book to return quickly without going through the detailed process.</p>
                
                <?php if (!empty($issued_books)): ?>
                    <div class="selection-grid">
                        <?php foreach ($issued_books as $issue): ?>
                            <div class="item-card" onclick="openReturnModal(<?php echo $issue['id']; ?>, '<?php echo addslashes($issue['title']); ?>', '<?php echo addslashes($issue['member_name']); ?>', '<?php echo $issue['days_overdue']; ?>')">
                                <h4><?php echo htmlspecialchars($issue['title']); ?></h4>
                                <p><strong>Member:</strong> <?php echo htmlspecialchars($issue['member_name']); ?></p>
                                <p><strong>Issue Date:</strong> <?php echo date('M d, Y', strtotime($issue['issue_date'])); ?></p>
                                <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($issue['due_date'])); ?></p>
                                <?php if ($issue['issue_status'] == 'overdue'): ?>
                                    <span class="status-badge status-overdue">
                                        Overdue (<?php echo $issue['days_overdue']; ?> days)
                                    </span>
                                <?php elseif ($issue['issue_status'] == 'due_soon'): ?>
                                    <span class="status-badge status-due-soon">Due Soon</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No books to return</h3>
                        <p>All books have been returned. Great job!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Return Book Modal -->
    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-undo"></i> Return Book</h3>
                <span class="close" onclick="closeModal('returnModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" id="return_issue_id" name="issue_id">
                    
                    <div id="returnBookInfo" style="margin-bottom: 1.5rem;"></div>
                    
                    <div class="form-group">
                        <label for="return_date">Return Date</label>
                        <input type="date" id="return_date" name="return_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fine">Fine Amount (₹)</label>
                        <input type="number" id="fine" name="fine" class="form-control" step="0.01" min="0" value="0">
                        <small style="color: #666;">Fine will be auto-calculated based on overdue days</small>
                    </div>
                    
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn" style="background: #6c757d; margin-right: 0.5rem;" onclick="closeModal('returnModal')">Cancel</button>
                        <button type="submit" name="return_book" class="btn btn-success">
                            <i class="fas fa-check"></i> Return Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Book selection
        let selectedBookId = null;
        function selectBook(bookId, bookTitle) {
            // Remove previous selection
            document.querySelectorAll('#bookSelection .item-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update hidden input and display
            document.getElementById('selected_book_id').value = bookId;
            document.getElementById('selectedBookTitle').textContent = bookTitle;
            document.getElementById('selectedBookInfo').style.display = 'block';
            
            selectedBookId = bookId;
            updateDueDate();
        }

        // Member selection
        let selectedMemberId = null;
        function selectMember(memberId, memberName) {
            // Remove previous selection
            document.querySelectorAll('#memberSelection .item-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update hidden input and display
            document.getElementById('selected_member_id').value = memberId;
            document.getElementById('selectedMemberName').textContent = memberName;
            document.getElementById('selectedMemberInfo').style.display = 'block';
            
            selectedMemberId = memberId;
        }

        // Update due date when issue date changes
        function updateDueDate() {
            const issueDate = document.getElementById('issue_date').value;
            const loanDuration = <?php echo $settings['loan_duration_days'] ?? 14; ?>;
            
            if (issueDate) {
                const due = new Date(issueDate);
                due.setDate(due.getDate() + loanDuration);
                document.getElementById('calculated_due_date').value = due.toISOString().split('T')[0];
            }
        }

        // Search functions
        function searchBooks() {
            const searchTerm = document.getElementById('bookSearch').value;
            window.location.href = `issue-book.php?search_books=${encodeURIComponent(searchTerm)}`;
        }

        function searchMembers() {
            const searchTerm = document.getElementById('memberSearch').value;
            window.location.href = `issue-book.php?search_members=${encodeURIComponent(searchTerm)}`;
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Return book modal
        function openReturnModal(issueId, bookTitle, memberName, daysOverdue) {
            document.getElementById('return_issue_id').value = issueId;
            
            let statusInfo = '';
            let fine = 0;
            
            if (daysOverdue > 0) {
                const finePerDay = <?php echo $settings['fine_per_day'] ?? 2; ?>;
                fine = daysOverdue * finePerDay;
                statusInfo = `<div class="alert alert-danger">
                    <strong>Overdue:</strong> This book is ${daysOverdue} days overdue. 
                    Suggested fine: ₹${fine.toFixed(2)}
                </div>`;
            } else {
                statusInfo = `<div class="alert alert-success">
                    <strong>On Time:</strong> This book is being returned on time.
                </div>`;
            }
            
            document.getElementById('returnBookInfo').innerHTML = `
                <h4 style="margin-bottom: 1rem;">Book: ${bookTitle}</h4>
                <p style="margin-bottom: 1rem;"><strong>Member:</strong> ${memberName}</p>
                ${statusInfo}
            `;
            
            document.getElementById('fine').value = fine.toFixed(2);
            
            openModal('returnModal');
        }

        // Event listeners
        document.getElementById('issue_date').addEventListener('change', updateDueDate);

        // Enter key search
        document.getElementById('bookSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchBooks();
            }
        });

        document.getElementById('memberSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchMembers();
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            }
        }

        // Initialize due date
        updateDueDate();

        // Mobile responsive
        if (window.innerWidth <= 768) {
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.style.cssText = `
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: #667eea;
                color: white;
                border: none;
                padding: 10px;
                border-radius: 5px;
                cursor: pointer;
            `;
            mobileMenuBtn.onclick = () => document.querySelector('.sidebar').classList.toggle('open');
            document.body.appendChild(mobileMenuBtn);
        }
    </script>
</body>
</html>