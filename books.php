<?php
session_start();
// Add authentication check here if needed

// Database connection (adjust these credentials)
$host = 'localhost';
$dbname = 'librarymanagement';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_message = "Connection failed: " . $e->getMessage();
}

// Handle AJAX request for getting book data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_book' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM books WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($book);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_book'])) {
        $title = $_POST['title'];
        $author = $_POST['author'];
        $isbn = $_POST['isbn'];
        $category = $_POST['category'];
        $publisher = $_POST['publisher'];
        $publication_year = $_POST['publication_year'];
        $copies = $_POST['copies'];
        $description = $_POST['description'];
        
        try {
            $sql = "INSERT INTO books (title, author, isbn, category, publisher, publication_year, copies, available_copies, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $author, $isbn, $category, $publisher, $publication_year, $copies, $copies, $description]);
            
            $success_message = "Book added successfully!";
        } catch(PDOException $e) {
            $error_message = "Error adding book: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_book'])) {
        $id = $_POST['book_id'];
        $title = $_POST['title'];
        $author = $_POST['author'];
        $isbn = $_POST['isbn'];
        $category = $_POST['category'];
        $publisher = $_POST['publisher'];
        $publication_year = $_POST['publication_year'];
        $copies = $_POST['copies'];
        $description = $_POST['description'];
        
        try {
            // Get current available copies to maintain the relationship
            $current_sql = "SELECT copies, available_copies FROM books WHERE id = ?";
            $current_stmt = $pdo->prepare($current_sql);
            $current_stmt->execute([$id]);
            $current_book = $current_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate new available copies
            $issued_copies = $current_book['copies'] - $current_book['available_copies'];
            $new_available_copies = max(0, $copies - $issued_copies);
            
            $sql = "UPDATE books SET title=?, author=?, isbn=?, category=?, publisher=?, publication_year=?, copies=?, available_copies=?, description=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $author, $isbn, $category, $publisher, $publication_year, $copies, $new_available_copies, $description, $id]);
            
            $success_message = "Book updated successfully!";
        } catch(PDOException $e) {
            $error_message = "Error updating book: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_book'])) {
        $id = $_POST['book_id'];
        try {
            $sql = "DELETE FROM books WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $success_message = "Book deleted successfully!";
        } catch(PDOException $e) {
            $error_message = "Error deleting book: " . $e->getMessage();
        }
    }
}

// Fetch books with search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$books = [];
$categories = [];

if (isset($pdo)) {
    $sql = "SELECT * FROM books WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }

    if ($category_filter) {
        $sql .= " AND category = ?";
        $params[] = $category_filter;
    }

    $sql .= " ORDER BY title ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch categories for dropdown
        $categories_sql = "SELECT DISTINCT category FROM books ORDER BY category";
        $categories_stmt = $pdo->prepare($categories_sql);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        $error_message = "Error fetching books: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library Management</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
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

        .search-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 1rem;
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

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .book-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .book-card:hover {
            transform: translateY(-5px);
        }

        .book-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
        }

        .book-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .book-author {
            opacity: 0.9;
        }

        .book-body {
            padding: 1rem;
        }

        .book-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .book-actions {
            display: flex;
            gap: 0.5rem;
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
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            opacity: 0.7;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-low {
            background: #fff3cd;
            color: #856404;
        }

        .status-out {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .loading {
            text-align: center;
            padding: 1rem;
            color: #667eea;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .books-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
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
            <li><a href="books.php" class="active"><i class="fas fa-book"></i> Manage Books</a></li>
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
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="fas fa-book"></i> Manage Books</h2>
                <p>Add, edit, and manage your library's book collection</p>
            </div>
            <button class="btn" onclick="openModal('addBookModal')">
                <i class="fas fa-plus"></i> Add New Book
            </button>
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

        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="search">Search Books</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Search by title, author, or ISBN..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Books Grid -->
        <?php if (!empty($books)): ?>
            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                    <div class="book-card">
                        <div class="book-header">
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                        </div>
                        <div class="book-body">
                            <div class="book-info">
                                <div><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></div>
                                <div><strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?></div>
                                <div><strong>Publisher:</strong> <?php echo htmlspecialchars($book['publisher']); ?></div>
                                <div><strong>Year:</strong> <?php echo htmlspecialchars($book['publication_year']); ?></div>
                                <div><strong>Total Copies:</strong> <?php echo $book['copies']; ?></div>
                                <div>
                                    <strong>Available:</strong> 
                                    <span class="status-badge <?php 
                                        if ($book['available_copies'] == 0) echo 'status-out';
                                        elseif ($book['available_copies'] <= 2) echo 'status-low';
                                        else echo 'status-available';
                                    ?>">
                                        <?php echo $book['available_copies']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="book-actions">
                                <button class="btn btn-small btn-warning" onclick="editBook(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-small btn-danger" onclick="deleteBook(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <h3>No books found</h3>
                <p>Try adjusting your search criteria or add some books to get started.</p>
                <button class="btn" onclick="openModal('addBookModal')" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Add Your First Book
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Book</h3>
                <span class="close" onclick="closeModal('addBookModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="author">Author *</label>
                            <input type="text" id="author" name="author" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="isbn">ISBN *</label>
                            <input type="text" id="isbn" name="isbn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <input type="text" id="category" name="category" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" id="publisher" name="publisher" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year" class="form-control" min="1800" max="2025">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="copies">Number of Copies *</label>
                        <input type="number" id="copies" name="copies" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 1rem;">
                        <button type="button" class="btn" style="background: #6c757d;" onclick="closeModal('addBookModal')">Cancel</button>
                        <button type="submit" name="add_book" class="btn" style="margin-left: 0.5rem;">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div id="editBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Book</h3>
                <span class="close" onclick="closeModal('editBookModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="editLoadingMessage" class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading book details...
                </div>
                <form method="POST" action="" id="editBookForm" style="display: none;">
                    <input type="hidden" id="edit_book_id" name="book_id">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_title">Title *</label>
                            <input type="text" id="edit_title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_author">Author *</label>
                            <input type="text" id="edit_author" name="author" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_isbn">ISBN *</label>
                            <input type="text" id="edit_isbn" name="isbn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_category">Category *</label>
                            <input type="text" id="edit_category" name="category" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_publisher">Publisher</label>
                            <input type="text" id="edit_publisher" name="publisher" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_publication_year">Publication Year</label>
                            <input type="number" id="edit_publication_year" name="publication_year" class="form-control" min="1800" max="2025">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_copies">Number of Copies *</label>
                        <input type="number" id="edit_copies" name="copies" class="form-control" min="1" required>
                        <small style="color: #666; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> Note: Available copies will be adjusted automatically based on issued books.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="text-align: right; margin-top: 1rem;">
                        <button type="button" class="btn" style="background: #6c757d;" onclick="closeModal('editBookModal')">Cancel</button>
                        <button type="submit" name="edit_book" class="btn" style="margin-left: 0.5rem;">Update Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            // Reset edit form when closing edit modal
            if (modalId === 'editBookModal') {
                document.getElementById('editLoadingMessage').style.display = 'block';
                document.getElementById('editBookForm').style.display = 'none';
                document.getElementById('editBookForm').reset();
            }
        }

        function editBook(bookId) {
            // Show the edit modal
            openModal('editBookModal');
            
            // Show loading message
            document.getElementById('editLoadingMessage').style.display = 'block';
            document.getElementById('editBookForm').style.display = 'none';
            
            // Fetch book data via AJAX
            fetch(`books.php?ajax=get_book&id=${bookId}`)
                .then(response => response.json())
                .then(book => {
                    if (book && book.id) {
                        // Hide loading message and show form
                        document.getElementById('editLoadingMessage').style.display = 'none';
                        document.getElementById('editBookForm').style.display = 'block';
                        
                        // Populate the form fields
                        document.getElementById('edit_book_id').value = book.id;
                        document.getElementById('edit_title').value = book.title || '';
                        document.getElementById('edit_author').value = book.author || '';
                        document.getElementById('edit_isbn').value = book.isbn || '';
                        document.getElementById('edit_category').value = book.category || '';
                        document.getElementById('edit_publisher').value = book.publisher || '';
                        document.getElementById('edit_publication_year').value = book.publication_year || '';
                        document.getElementById('edit_copies').value = book.copies || '';
                        document.getElementById('edit_description').value = book.description || '';
                    } else {
                        // Handle error
                        document.getElementById('editLoadingMessage').innerHTML = 
                            '<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Error loading book details. Please try again.';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('editLoadingMessage').innerHTML = 
                        '<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Error loading book details. Please try again.';
                });
        }

        function deleteBook(bookId) {
            if (confirm('Are you sure you want to delete this book? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const bookIdInput = document.createElement('input');
                bookIdInput.type = 'hidden';
                bookIdInput.name = 'book_id';
                bookIdInput.value = bookId;
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_book';
                deleteInput.value = '1';
                
                form.appendChild(bookIdInput);
                form.appendChild(deleteInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    if (modal.id === 'editBookModal') {
                        closeModal('editBookModal');
                    } else {
                        modal.style.display = 'none';
                    }
                }
            }
        }

        // Form validation for edit form
        document.getElementById('editBookForm').addEventListener('submit', function(e) {
            const copies = parseInt(document.getElementById('edit_copies').value);
            if (copies < 1) {
                e.preventDefault();
                alert('Number of copies must be at least 1.');
                return false;
            }
        });
    </script>
</body>
</html>