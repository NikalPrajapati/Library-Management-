<?php
session_start();
// Add authentication check here if needed

// Database connection for dynamic data
$host = 'localhost';
$dbname = 'librarymanagement';
$username = 'root';
$password = '';

$reports = [];
$error = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get comprehensive reports data
    
    // Basic Statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM books");
    $reports['total_books'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'");
    $reports['total_active_members'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE return_date IS NULL");
    $reports['books_currently_issued'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE return_date IS NULL AND due_date < CURDATE()");
    $reports['overdue_books'] = $stmt->fetchColumn();
    
    // Monthly Statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    $reports['books_issued_this_month'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE return_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND return_date IS NOT NULL");
    $reports['books_returned_this_month'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    $reports['new_members_this_month'] = $stmt->fetchColumn();
    
    // Financial Reports
    $stmt = $pdo->query("SELECT COALESCE(SUM(fine), 0) FROM book_issues WHERE fine > 0");
    $reports['total_fines_collected'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(fine), 0) FROM book_issues WHERE fine > 0 AND return_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    $reports['fines_this_month'] = $stmt->fetchColumn();
    
    // Category-wise book distribution
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as count 
        FROM books 
        WHERE category IS NOT NULL AND category != '' 
        GROUP BY category 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $reports['category_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most active members
    $stmt = $pdo->query("
        SELECT m.name, m.email, COUNT(bi.id) as books_issued
        FROM members m
        LEFT JOIN book_issues bi ON m.id = bi.member_id
        WHERE m.status = 'active'
        GROUP BY m.id
        ORDER BY books_issued DESC
        LIMIT 10
    ");
    $reports['most_active_members'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most popular books
    $stmt = $pdo->query("
        SELECT b.title, b.author, COUNT(bi.id) as issue_count
        FROM books b
        LEFT JOIN book_issues bi ON b.id = bi.book_id
        GROUP BY b.id
        ORDER BY issue_count DESC
        LIMIT 10
    ");
    $reports['most_popular_books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent overdue books
    $stmt = $pdo->query("
        SELECT b.title, m.name as member_name, bi.issue_date, bi.due_date, 
               DATEDIFF(CURDATE(), bi.due_date) as days_overdue,
               bi.fine
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        JOIN members m ON bi.member_id = m.id
        WHERE bi.return_date IS NULL AND bi.due_date < CURDATE()
        ORDER BY days_overdue DESC
        LIMIT 10
    ");
    $reports['recent_overdue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly trends (last 12 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(issue_date, '%Y-%m') as month,
            COUNT(*) as issues
        FROM book_issues 
        WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $reports['monthly_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Today's statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE DATE(issue_date) = CURDATE()");
    $reports['books_issued_today'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE DATE(return_date) = CURDATE()");
    $reports['books_returned_today'] = $stmt->fetchColumn();
    
    // Weekly statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)");
    $reports['books_issued_this_week'] = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    // Use default values if database connection fails
    $reports = [
        'total_books' => 0,
        'total_active_members' => 0,
        'books_currently_issued' => 0,
        'overdue_books' => 0,
        'books_issued_this_month' => 0,
        'books_returned_this_month' => 0,
        'new_members_this_month' => 0,
        'total_fines_collected' => 0,
        'fines_this_month' => 0,
        'books_issued_today' => 0,
        'books_returned_today' => 0,
        'books_issued_this_week' => 0,
        'category_distribution' => [],
        'most_active_members' => [],
        'most_popular_books' => [],
        'recent_overdue' => [],
        'monthly_trends' => []
    ];
}

// Handle report generation and export
$generated_report = '';
$report_title = '';
if (isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    
    // Generate custom report based on type and date range
    if ($report_type && $date_from && $date_to) {
        try {
            switch ($report_type) {
                case 'issued_books':
                    $stmt = $pdo->prepare("
                        SELECT b.title, b.author, m.name as member_name, m.email, bi.issue_date, bi.due_date,
                               CASE WHEN bi.due_date < CURDATE() THEN 'Overdue' ELSE 'Active' END as status
                        FROM book_issues bi
                        JOIN books b ON bi.book_id = b.id
                        JOIN members m ON bi.member_id = m.id
                        WHERE bi.issue_date BETWEEN ? AND ?
                        ORDER BY bi.issue_date DESC
                    ");
                    $stmt->execute([$date_from, $date_to]);
                    $generated_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = "Books Issued Report ($date_from to $date_to)";
                    break;
                    
                case 'returned_books':
                    $stmt = $pdo->prepare("
                        SELECT b.title, b.author, m.name as member_name, m.email, bi.issue_date, bi.return_date,
                               DATEDIFF(bi.return_date, bi.due_date) as days_late, bi.fine
                        FROM book_issues bi
                        JOIN books b ON bi.book_id = b.id
                        JOIN members m ON bi.member_id = m.id
                        WHERE bi.return_date BETWEEN ? AND ?
                        ORDER BY bi.return_date DESC
                    ");
                    $stmt->execute([$date_from, $date_to]);
                    $generated_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = "Books Returned Report ($date_from to $date_to)";
                    break;
                    
                case 'fines_collected':
                    $stmt = $pdo->prepare("
                        SELECT b.title, m.name as member_name, m.email, bi.fine, bi.return_date,
                               DATEDIFF(bi.return_date, bi.due_date) as days_late
                        FROM book_issues bi
                        JOIN books b ON bi.book_id = b.id
                        JOIN members m ON bi.member_id = m.id
                        WHERE bi.fine > 0 AND bi.return_date BETWEEN ? AND ?
                        ORDER BY bi.fine DESC
                    ");
                    $stmt->execute([$date_from, $date_to]);
                    $generated_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = "Fines Collected Report ($date_from to $date_to)";
                    break;
                    
                case 'member_activity':
                    $stmt = $pdo->prepare("
                        SELECT m.name, m.email, m.phone,
                               COUNT(bi.id) as total_books_issued,
                               COUNT(CASE WHEN bi.return_date IS NULL THEN 1 END) as current_books,
                               COALESCE(SUM(bi.fine), 0) as total_fines
                        FROM members m
                        LEFT JOIN book_issues bi ON m.id = bi.member_id
                        WHERE m.created_at BETWEEN ? AND ?
                        GROUP BY m.id
                        ORDER BY total_books_issued DESC
                    ");
                    $stmt->execute([$date_from, $date_to]);
                    $generated_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = "Member Activity Report ($date_from to $date_to)";
                    break;
                    
                case 'overdue_analysis':
                    $stmt = $pdo->prepare("
                        SELECT b.title, b.author, m.name as member_name, m.email,
                               bi.issue_date, bi.due_date,
                               DATEDIFF(CURDATE(), bi.due_date) as days_overdue,
                               bi.fine
                        FROM book_issues bi
                        JOIN books b ON bi.book_id = b.id
                        JOIN members m ON bi.member_id = m.id
                        WHERE bi.return_date IS NULL 
                        AND bi.due_date BETWEEN ? AND ?
                        AND bi.due_date < CURDATE()
                        ORDER BY days_overdue DESC
                    ");
                    $stmt->execute([$date_from, $date_to]);
                    $generated_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = "Overdue Books Analysis ($date_from to $date_to)";
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Report generation failed: ' . $e->getMessage();
        }
    }
}

// Handle CSV export
if (isset($_POST['export_csv']) && !empty($generated_report)) {
    $filename = 'library_report_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    if (!empty($generated_report)) {
        fputcsv($output, array_keys($generated_report[0]));
        
        // Add data rows
        foreach ($generated_report as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Library Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

        .reports-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            flex-wrap: wrap;
        }

        .tab-button {
            flex: 1;
            min-width: 120px;
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

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
            background: white;
            border-radius: 10px;
            padding: 1rem;
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

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
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

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .alert-danger {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .report-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e1e5e9;
        }

        .section-header h3 {
            color: #333;
            font-size: 1.5rem;
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

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
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
            
            .reports-tabs {
                flex-direction: column;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e1e5e9;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-book"></i> Library Management System</h1>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> Welcome, Admin
            <a href="logout.php" class="btn" style="padding: 0.3rem 0.8rem; margin-left: 10px;">Logout</a>
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
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h2><i class="fas fa-chart-line"></i> Reports & Analytics</h2>
            <p>Comprehensive insights into your library's performance and statistics</p>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <h3><?php echo number_format($reports['total_books']); ?></h3>
                <p>Total Books</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 100%;"></div>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo number_format($reports['total_active_members']); ?></h3>
                <p>Active Members</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 85%;"></div>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-hand-holding"></i>
                <h3><?php echo number_format($reports['books_currently_issued']); ?></h3>
                <p>Currently Issued</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $reports['total_books'] > 0 ? ($reports['books_currently_issued'] / $reports['total_books']) * 100 : 0; ?>%;"></div>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <h3><?php echo number_format($reports['overdue_books']); ?></h3>
                <p>Overdue Books</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $reports['books_currently_issued'] > 0 ? ($reports['overdue_books'] / $reports['books_currently_issued']) * 100 : 0; ?>%; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);"></div>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <h3><?php echo number_format($reports['books_issued_today']); ?></h3>
                <p>Issued Today</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-undo"></i>
                <h3><?php echo number_format($reports['books_returned_today']); ?></h3>
                <p>Returned Today</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-week"></i>
                <h3><?php echo number_format($reports['books_issued_this_week']); ?></h3>
                <p>Issued This Week</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>₹<?php echo number_format($reports['total_fines_collected'], 2); ?></h3>
                <p>Total Fines</p>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Reports Tabs -->
        <div class="reports-tabs fade-in">
            <button class="tab-button active" onclick="openTab(event, 'overview')">
                <i class="fas fa-chart-pie"></i> Overview
            </button>
            <button class="tab-button" onclick="openTab(event, 'analytics')">
                <i class="fas fa-chart-line"></i> Analytics
            </button>
            <button class="tab-button" onclick="openTab(event, 'detailed')">
                <i class="fas fa-list-alt"></i> Detailed Reports
            </button>
            <button class="tab-button" onclick="openTab(event, 'custom')">
                <i class="fas fa-cog"></i> Custom Reports
            </button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="grid-2">
                <div class="report-section">
                    <div class="section-header">
                        <h3><i class="fas fa-chart-pie"></i> Category Distribution</h3>
                    </div>
                    <?php if (!empty($reports['category_distribution'])): ?>
                        <canvas id="categoryChart"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <h3>No data available</h3>
                            <p>Add some books with categories to see the distribution.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="report-section">
                    <div class="section-header">
                        <h3><i class="fas fa-trophy"></i> Most Active Members</h3>
                    </div>
                    <?php if (!empty($reports['most_active_members'])): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Books Issued</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($reports['most_active_members'], 0, 5) as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><strong><?php echo $member['books_issued']; ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No member activity</h3>
                            <p>Member activity will appear here once books are issued.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-fire"></i> Most Popular Books</h3>
                </div>
                <?php if (!empty($reports['most_popular_books'])): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Times Issued</th>
                                <th>Popularity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports['most_popular_books'] as $index => $book): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><strong><?php echo $book['issue_count']; ?></strong></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $reports['most_popular_books'][0]['issue_count'] > 0 ? ($book['issue_count'] / $reports['most_popular_books'][0]['issue_count']) * 100 : 0; ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No book issues recorded</h3>
                        <p>Popular books will appear here once books are issued to members.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Trends</h3>
                </div>
                <?php if (!empty($reports['monthly_trends'])): ?>
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h3>No trend data available</h3>
                        <p>Monthly trends will appear here as you use the system.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid-2">
                <div class="report-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-alt"></i> This Month Summary</h3>
                    </div>
                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="stat-card">
                            <i class="fas fa-hand-holding"></i>
                            <h3><?php echo number_format($reports['books_issued_this_month']); ?></h3>
                            <p>Books Issued</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-undo"></i>
                            <h3><?php echo number_format($reports['books_returned_this_month']); ?></h3>
                            <p>Books Returned</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-user-plus"></i>
                            <h3><?php echo number_format($reports['new_members_this_month']); ?></h3>
                            <p>New Members</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-dollar-sign"></i>
                            <h3>₹<?php echo number_format($reports['fines_this_month'], 2); ?></h3>
                            <p>Fines Collected</p>
                        </div>
                    </div>
                </div>

                <div class="report-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Overdue Analysis</h3>
                    </div>
                    <?php if (!empty($reports['recent_overdue'])): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Member</th>
                                    <th>Days Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($reports['recent_overdue'], 0, 5) as $overdue): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                                        <td><?php echo htmlspecialchars($overdue['member_name']); ?></td>
                                        <td>
                                            <span class="status-badge status-overdue">
                                                <?php echo $overdue['days_overdue']; ?> days
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>No overdue books!</h3>
                            <p>Great! All books are returned on time.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Reports Tab -->
        <div id="detailed" class="tab-content">
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-list-alt"></i> Comprehensive Reports</h3>
                    <button onclick="printReport()" class="btn">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>

                <!-- Category Distribution Details -->
                <?php if (!empty($reports['category_distribution'])): ?>
                    <h4 style="margin-bottom: 1rem; color: #333;">📚 Category Distribution</h4>
                    <table class="table" style="margin-bottom: 2rem;">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Number of Books</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_books_in_categories = array_sum(array_column($reports['category_distribution'], 'count'));
                            foreach ($reports['category_distribution'] as $category): 
                                $percentage = $total_books_in_categories > 0 ? ($category['count'] / $total_books_in_categories) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category']); ?></td>
                                    <td><?php echo $category['count']; ?></td>
                                    <td><?php echo number_format($percentage, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Most Active Members Details -->
                <?php if (!empty($reports['most_active_members'])): ?>
                    <h4 style="margin-bottom: 1rem; color: #333;">👥 Most Active Members</h4>
                    <table class="table" style="margin-bottom: 2rem;">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Member Name</th>
                                <th>Email</th>
                                <th>Books Issued</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports['most_active_members'] as $index => $member): ?>
                                <tr>
                                    <td>#<?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><strong><?php echo $member['books_issued']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Overdue Books Details -->
                <?php if (!empty($reports['recent_overdue'])): ?>
                    <h4 style="margin-bottom: 1rem; color: #333;">⚠️ Overdue Books Details</h4>
                    <table class="table" style="margin-bottom: 2rem;">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Member</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports['recent_overdue'] as $overdue): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                                    <td><?php echo htmlspecialchars($overdue['member_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($overdue['issue_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($overdue['due_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-overdue">
                                            <?php echo $overdue['days_overdue']; ?> days
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($overdue['fine'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custom Reports Tab -->
        <div id="custom" class="tab-content">
            <div class="report-section">
                <div class="section-header">
                    <h3><i class="fas fa-cog"></i> Generate Custom Report</h3>
                </div>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" class="form-control" required>
                                <option value="">Select Report Type</option>
                                <option value="issued_books">Books Issued Report</option>
                                <option value="returned_books">Books Returned Report</option>
                                <option value="fines_collected">Fines Collected Report</option>
                                <option value="member_activity">Member Activity Report</option>
                                <option value="overdue_analysis">Overdue Books Analysis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" required>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <button type="submit" name="generate_report" class="btn">
                            <i class="fas fa-chart-bar"></i> Generate Report
                        </button>
                    </div>
                </form>

                <?php if (!empty($generated_report)): ?>
                    <div style="margin-top: 2rem;">
                        <div class="section-header">
                            <h3><?php echo htmlspecialchars($report_title); ?></h3>
                            <div>
                                <form method="POST" action="" style="display: inline;">
                                    <?php foreach ($_POST as $key => $value): ?>
                                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" name="export_csv" class="btn btn-success">
                                        <i class="fas fa-download"></i> Export CSV
                                    </button>
                                </form>
                                <button onclick="printCustomReport()" class="btn btn-warning" style="margin-left: 0.5rem;">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        <div id="customReportContent">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($generated_report[0]) as $header): ?>
                                            <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generated_report as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                                <td><?php echo htmlspecialchars($cell); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
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

        // Category Chart
        <?php if (!empty($reports['category_distribution'])): ?>
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($reports['category_distribution'], 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($reports['category_distribution'], 'count')); ?>,
                    backgroundColor: [
                        '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
                        '#43e97b', '#38f9d7', '#ffecd2', '#fcb69f'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Monthly Trends Chart
        <?php if (!empty($reports['monthly_trends'])): ?>
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($reports['monthly_trends'], 'month')); ?>,
                datasets: [{
                    label: 'Books Issued',
                    data: <?php echo json_encode(array_column($reports['monthly_trends'], 'issues')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Print functionality
        function printReport() {
            window.print();
        }

        function printCustomReport() {
            const printContent = document.getElementById('customReportContent').innerHTML;
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Custom Report</title>');
            printWindow.document.write('<style>table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f2f2f2;}</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h1><?php echo htmlspecialchars($report_title ?? "Custom Report"); ?></h1>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('date_from').value = firstDay.toISOString().split('T')[0];
            document.getElementById('date_to').value = today.toISOString().split('T')[0];
        });

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