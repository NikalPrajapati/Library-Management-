<?php
session_start();
// Add authentication check here if needed

// Database connection for dynamic data
$host = 'localhost';
$dbname = 'librarymanagement';
$username = 'root';
$password = '';

 $stats = [
     'total_books' => 0,
     'active_members' => 0,
     'books_issued' => 0,
     'overdue_books' => 0
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get total books
    $stmt = $pdo->query("SELECT COUNT(*) FROM books");
    $stats['total_books'] = $stmt->fetchColumn();
    
    // Get active members
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'");
    $stats['active_members'] = $stmt->fetchColumn();
    
    // Get books issued
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE return_date IS NULL");
    $stats['books_issued'] = $stmt->fetchColumn();
    
    // Get overdue books
    $stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE return_date IS NULL AND due_date < CURDATE()");
    $stats['overdue_books'] = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    // Use default values if database connection fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management Dashboard</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .books { color: #4CAF50; }
        .members { color: #2196F3; }
        .issued { color: #FF9800; }
        .overdue { color: #F44336; }

        .dashboard-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eee;
        }

        .btn {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.9rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-issued {
            background: #fff3cd;
            color: #856404;
        }

        .status-returned {
            background: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .search-bar {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .search-results {
            display: none;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            position: absolute;
            width: calc(100% - 3rem);
            z-index: 1000;
        }

        .search-result-item {
            padding: 0.5rem;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Manage Members</a></li>
            <li><a href="issue-book.php"><i class="fas fa-hand-holding"></i> Issue Book</a></li>
            <li><a href="return-book.php"><i class="fas fa-undo"></i> Return Book</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="location.href='books.php'">
                <i class="fas fa-book books"></i>
                <h3><?php echo number_format($stats['total_books']); ?></h3>
                <p>Total Books</p>
            </div>
            <div class="stat-card" onclick="location.href='members.php'">
                <i class="fas fa-users members"></i>
                <h3><?php echo number_format($stats['active_members']); ?></h3>
                <p>Active Members</p>
            </div>
            <div class="stat-card" onclick="location.href='issued-books.php'">
                <i class="fas fa-hand-holding issued"></i>
                <h3><?php echo number_format($stats['books_issued']); ?></h3>
                <p>Books Issued</p>
            </div>
            <div class="stat-card" onclick="location.href='overdue-books.php'">
                <i class="fas fa-exclamation-triangle overdue"></i>
                <h3><?php echo number_format($stats['overdue_books']); ?></h3>
                <p>Overdue Books</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            </div>
            <div class="quick-actions">
                <a href="books.php" class="action-card">
                    <i class="fas fa-plus-circle books" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Add New Book</h4>
                </a>
                <a href="members.php" class="action-card">
                    <i class="fas fa-user-plus members" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Add New Member</h4>
                </a>
                <a href="issue-book.php" class="action-card">
                    <i class="fas fa-hand-holding issued" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Issue Book</h4>
                </a>
                <a href="return-book.php" class="action-card">
                    <i class="fas fa-undo overdue" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Return Book</h4>
                </a>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Activities</h2>
                <a href="activities.php" class="btn">View All</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Activity</th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2025-07-20</td>
                        <td>Book Issued</td>
                        <td>The Great Gatsby</td>
                        <td>John Doe</td>
                        <td><span class="status status-issued">Issued</span></td>
                    </tr>
                    <tr>
                        <td>2025-07-20</td>
                        <td>Book Returned</td>
                        <td>To Kill a Mockingbird</td>
                        <td>Jane Smith</td>
                        <td><span class="status status-returned">Returned</span></td>
                    </tr>
                    <tr>
                        <td>2025-07-19</td>
                        <td>Book Issued</td>
                        <td>1984</td>
                        <td>Mike Johnson</td>
                        <td><span class="status status-overdue">Overdue</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Overdue Books -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-triangle overdue"></i> Overdue Books</h2>
                <a href="overdue-books.php" class="btn">View All</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Member</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1984</td>
                        <td>Mike Johnson</td>
                        <td>2025-07-05</td>
                        <td>2025-07-15</td>
                        <td>5 days</td>
                        <td><button class="btn btn-small" onclick="sendReminder(1)">Send Reminder</button></td>
                    </tr>
                    <tr>
                        <td>Pride and Prejudice</td>
                        <td>Sarah Wilson</td>
                        <td>2025-07-01</td>
                        <td>2025-07-11</td>
                        <td>9 days</td>
                        <td><button class="btn btn-small" onclick="sendReminder(2)">Send Reminder</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Quick Search -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-search"></i> Quick Search</h2>
            </div>
            <div style="position: relative;">
                <input type="text" class="search-bar" id="quickSearch" placeholder="Search books, members, or transactions...">
                <div id="searchResults" class="search-results"></div>
            </div>
        </div>
    </div>

    <script>
        // Quick search functionality
        document.getElementById('quickSearch').addEventListener('input', function(e) {
            const query = e.target.value;
            const resultsDiv = document.getElementById('searchResults');
            
            if (query.length > 2) {
                // Simulate search results (replace with actual AJAX call)
                const mockResults = [
                    { type: 'book', title: 'The Great Gatsby', author: 'F. Scott Fitzgerald' },
                    { type: 'member', name: 'John Doe', id: 'M001' },
                    { type: 'transaction', book: '1984', member: 'Jane Smith', status: 'Issued' }
                ].filter(item => 
                    JSON.stringify(item).toLowerCase().includes(query.toLowerCase())
                );
                
                if (mockResults.length > 0) {
                    resultsDiv.innerHTML = mockResults.map(item => {
                        if (item.type === 'book') {
                            return `<div class="search-result-item" onclick="location.href='books.php?search=${item.title}'">${item.title} by ${item.author}</div>`;
                        } else if (item.type === 'member') {
                            return `<div class="search-result-item" onclick="location.href='members.php?search=${item.name}'">${item.name} (${item.id})</div>`;
                        } else {
                            return `<div class="search-result-item" onclick="location.href='transactions.php?search=${item.book}'">${item.book} - ${item.member} (${item.status})</div>`;
                        }
                    }).join('');
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.innerHTML = '<div class="search-result-item">No results found</div>';
                    resultsDiv.style.display = 'block';
                }
            } else {
                resultsDiv.style.display = 'none';
            }
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#quickSearch') && !e.target.closest('#searchResults')) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });

        // Send reminder functionality
        function sendReminder(issueId) {
            if (confirm('Send reminder email to member?')) {
                // Simulate sending reminder
                alert('Reminder sent successfully!');
                // Replace with actual AJAX call to send reminder
                // fetch('send-reminder.php', { method: 'POST', body: JSON.stringify({issueId}) })
            }
        }

        // Logout functionality
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                location.href = 'logout.php';
            }
        }

        // Add click handlers for quick actions with visual feedback
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-3px)';
                }, 150);
            });
        });

        // Add click handlers for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-5px)';
                }, 150);
            });
        });

        // Navigation function for quick actions
        function navigateTo(page) {
            location.href = page;
        }

        // Function to refresh dashboard data
        function refreshDashboard() {
            location.reload();
        }

        // Auto-refresh dashboard every 5 minutes
        setInterval(refreshDashboard, 300000);
    </script>
</body>
</html>