<?php
session_start();
// Add authentication check here if needed

// Database connection for dynamic data
$host = 'localhost';
$dbname = 'librarymanagement';
$username = 'root';
$password = '';

$categories = [];
$error = '';
$success = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            
            if (!empty($name)) {
                try {
                    // Create categories table if it doesn't exist
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS categories (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) UNIQUE NOT NULL,
                        description TEXT,
                        book_count INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )";
                    $pdo->exec($create_table_sql);
                    
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    $success = 'Category added successfully!';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = 'Category already exists!';
                    } else {
                        $error = 'Error adding category: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Category name is required!';
            }
        }
        
        if (isset($_POST['delete_category'])) {
            $id = intval($_POST['category_id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Category deleted successfully!';
            } catch (PDOException $e) {
                $error = 'Error deleting category: ' . $e->getMessage();
            }
        }
        
        if (isset($_POST['edit_category'])) {
            $id = intval($_POST['category_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $success = 'Category updated successfully!';
            } catch (PDOException $e) {
                $error = 'Error updating category: ' . $e->getMessage();
            }
        }
    }
    
    // Get categories with book count
    $stmt = $pdo->query("
        SELECT c.*, 
               COALESCE(COUNT(b.id), 0) as book_count 
        FROM categories c 
        LEFT JOIN books b ON c.name = b.category 
        GROUP BY c.id 
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COALESCE(COUNT(b.id), 0) as book_count 
            FROM categories c 
            LEFT JOIN books b ON c.name = b.category 
            WHERE c.name LIKE ? OR c.description LIKE ?
            GROUP BY c.id 
            ORDER BY c.name ASC
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$searchTerm, $searchTerm]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Manage Categories - Library Management</title>
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

        .controls-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
            flex: 1;
            min-width: 300px;
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

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
            box-shadow: 0 4px 15px rgba(255, 167, 38, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(255, 167, 38, 0.4);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .categories-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .category-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .category-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .category-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .book-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            font-weight: 600;
        }

        .category-actions {
            display: flex;
            gap: 0.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            border-left: 4px solid;
            backdrop-filter: blur(10px);
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
            
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .categories-grid {
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
            <li><a href="categories.php" class="active"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h2><i class="fas fa-tags"></i> Manage Categories</h2>
            <p>Organize your library collection with custom categories</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards fade-in">
            <div class="stat-card">
                <i class="fas fa-tags"></i>
                <h3><?php echo count($categories); ?></h3>
                <p>Total Categories</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <h3><?php echo array_sum(array_column($categories, 'book_count')); ?></h3>
                <p>Total Books</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <h3><?php echo !empty($categories) ? max(array_column($categories, 'book_count')) : 0; ?></h3>
                <p>Most Popular</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-plus-circle"></i>
                <h3><?php echo date('M Y'); ?></h3>
                <p>This Month</p>
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

        <!-- Controls Section -->
        <div class="controls-section fade-in">
            <div class="controls-row">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search categories..." value="<?php echo htmlspecialchars($search); ?>">
                    <button onclick="searchCategories()" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <button onclick="openModal('addCategoryModal')" class="btn">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </div>

        <!-- Categories Section -->
        <div class="categories-section fade-in">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> Categories</h3>
                <span><?php echo count($categories); ?> categories found</span>
            </div>

            <?php if (!empty($categories)): ?>
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card">
                            <div class="category-header">
                                <div>
                                    <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                    <div class="category-description">
                                        <?php echo htmlspecialchars($category['description'] ?: 'No description available'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="category-stats">
                                <div class="book-count">
                                    <i class="fas fa-book"></i>
                                    <span><?php echo $category['book_count']; ?> books</span>
                                </div>
                                <small class="text-muted">
                                    Created: <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                </small>
                            </div>
                            
                            <div class="category-actions">
                                <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')" class="btn btn-small btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteCategory(<?php echo $category['id']; ?>)" class="btn btn-small btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3>No categories found</h3>
                    <p>Start by adding your first category to organize your books.</p>
                    <button onclick="openModal('addCategoryModal')" class="btn" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Add First Category
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Category</h3>
                <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief description of this category..."></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn" style="background: #6c757d; margin-right: 0.5rem;" onclick="closeModal('addCategoryModal')">Cancel</button>
                        <button type="submit" name="add_category" class="btn">
                            <i class="fas fa-save"></i> Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Category</h3>
                <span class="close" onclick="closeModal('editCategoryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="form-group">
                        <label for="edit_name">Category Name *</label>
                        <input type="text" id="edit_name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="button" class="btn" style="background: #6c757d; margin-right: 0.5rem;" onclick="closeModal('editCategoryModal')">Cancel</button>
                        <button type="submit" name="edit_category" class="btn">
                            <i class="fas fa-save"></i> Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Edit category function
        function editCategory(id, name, description) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            openModal('editCategoryModal');
        }

        // Delete category function
        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="category_id" value="${id}">
                    <input type="hidden" name="delete_category" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search function
        function searchCategories() {
            const searchTerm = document.getElementById('searchInput').value;
            window.location.href = `categories.php?search=${encodeURIComponent(searchTerm)}`;
        }

        // Enter key search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchCategories();
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

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all category cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.category-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = `opacity 0.5s ease ${index * 0.1}s, transform 0.5s ease ${index * 0.1}s`;
                observer.observe(card);
            });
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Add mobile menu button for responsive design
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
            mobileMenuBtn.onclick = toggleSidebar;
            document.body.appendChild(mobileMenuBtn);
        }
    </script>
</body>
</html>