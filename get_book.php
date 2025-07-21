<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = 'localhost';
$dbname = 'librarymanagement';
$username = 'root';
$password = '';

// Response helper function
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Error handler
function handleError($message, $code = 400) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => uniqid()
    ], $code);
}

// Database connection with error handling
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    handleError('Database connection failed: ' . $e->getMessage(), 500);
}

// Input validation and sanitization
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';

// Handle different actions
switch ($action) {
    case 'get':
        handleGetBook();
        break;
    case 'search':
        handleSearchBooks();
        break;
    case 'availability':
        handleCheckAvailability();
        break;
    case 'popular':
        handleGetPopularBooks();
        break;
    case 'recent':
        handleGetRecentBooks();
        break;
    case 'category':
        handleGetBooksByCategory();
        break;
    case 'suggestions':
        handleGetSuggestions();
        break;
    default:
        handleError('Invalid action specified');
}

// Get single book by ID
function handleGetBook() {
    global $pdo;
    
    if (!isset($_GET['id'])) {
        handleError('Book ID is required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        handleError('Invalid book ID provided');
    }
    
    try {
        // Get book details with additional information
        $sql = "
            SELECT 
                b.*,
                COALESCE(COUNT(bi.id), 0) as total_issues,
                COALESCE(COUNT(CASE WHEN bi.return_date IS NULL THEN 1 END), 0) as current_issues,
                CASE 
                    WHEN b.available_copies > 0 THEN 'available'
                    WHEN b.available_copies = 0 AND b.copies > 0 THEN 'unavailable'
                    ELSE 'unknown'
                END as availability_status,
                DATE_FORMAT(b.created_at, '%M %d, %Y') as formatted_date
            FROM books b
            LEFT JOIN book_issues bi ON b.id = bi.book_id
            WHERE b.id = ?
            GROUP BY b.id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $book = $stmt->fetch();
        
        if ($book) {
            // Add computed fields
            $book['is_available'] = $book['available_copies'] > 0;
            $book['utilization_rate'] = $book['copies'] > 0 ? 
                round((($book['copies'] - $book['available_copies']) / $book['copies']) * 100, 2) : 0;
            
            // Get issue history (last 5 issues)
            $history_sql = "
                SELECT 
                    bi.issue_date,
                    bi.return_date,
                    bi.due_date,
                    m.name as member_name,
                    CASE 
                        WHEN bi.return_date IS NULL THEN 'issued'
                        WHEN bi.return_date > bi.due_date THEN 'returned_late'
                        ELSE 'returned_on_time'
                    END as status
                FROM book_issues bi
                JOIN members m ON bi.member_id = m.id
                WHERE bi.book_id = ?
                ORDER BY bi.issue_date DESC
                LIMIT 5
            ";
            
            $history_stmt = $pdo->prepare($history_sql);
            $history_stmt->execute([$id]);
            $book['issue_history'] = $history_stmt->fetchAll();
            
            sendResponse([
                'success' => true,
                'data' => $book,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            handleError('Book not found', 404);
        }
    } catch (PDOException $e) {
        handleError('Database error: ' . $e->getMessage(), 500);
    }
}

// Search books with filters
function handleSearchBooks() {
    global $pdo;
    
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    $author = $_GET['author'] ?? '';
    $available_only = isset($_GET['available_only']) && $_GET['available_only'] === 'true';
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    
    try {
        $conditions = [];
        $params = [];
        
        if (!empty($query)) {
            $conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.description LIKE ?)";
            $searchTerm = "%$query%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($category)) {
            $conditions[] = "b.category = ?";
            $params[] = $category;
        }
        
        if (!empty($author)) {
            $conditions[] = "b.author LIKE ?";
            $params[] = "%$author%";
        }
        
        if ($available_only) {
            $conditions[] = "b.available_copies > 0";
        }
        
        $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM books b $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();
        
        // Get books with pagination
        $sql = "
            SELECT 
                b.*,
                COALESCE(COUNT(bi.id), 0) as total_issues,
                CASE 
                    WHEN b.available_copies > 0 THEN 'available'
                    WHEN b.available_copies = 0 AND b.copies > 0 THEN 'unavailable'
                    ELSE 'unknown'
                END as availability_status
            FROM books b
            LEFT JOIN book_issues bi ON b.id = bi.book_id
            $where_clause
            GROUP BY b.id
            ORDER BY b.title ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
        $books = $stmt->fetchAll();
        
        // Add computed fields
        foreach ($books as &$book) {
            $book['is_available'] = $book['available_copies'] > 0;
            $book['utilization_rate'] = $book['copies'] > 0 ? 
                round((($book['copies'] - $book['available_copies']) / $book['copies']) * 100, 2) : 0;
        }
        
        sendResponse([
            'success' => true,
            'data' => $books,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ],
            'filters' => [
                'query' => $query,
                'category' => $category,
                'author' => $author,
                'available_only' => $available_only
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        handleError('Search failed: ' . $e->getMessage(), 500);
    }
}

// Check book availability
function handleCheckAvailability() {
    global $pdo;
    
    if (!isset($_GET['id'])) {
        handleError('Book ID is required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        handleError('Invalid book ID provided');
    }
    
    try {
        $sql = "
            SELECT 
                id,
                title,
                copies,
                available_copies,
                (available_copies > 0) as is_available,
                CASE 
                    WHEN available_copies > 0 THEN 'available'
                    WHEN available_copies = 0 AND copies > 0 THEN 'all_issued'
                    ELSE 'unavailable'
                END as status
            FROM books 
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $book = $stmt->fetch();
        
        if ($book) {
            sendResponse([
                'success' => true,
                'data' => $book,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            handleError('Book not found', 404);
        }
    } catch (PDOException $e) {
        handleError('Database error: ' . $e->getMessage(), 500);
    }
}

// Get popular books
function handleGetPopularBooks() {
    global $pdo;
    
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    
    try {
        $sql = "
            SELECT 
                b.*,
                COUNT(bi.id) as issue_count,
                CASE 
                    WHEN b.available_copies > 0 THEN 'available'
                    WHEN b.available_copies = 0 AND b.copies > 0 THEN 'unavailable'
                    ELSE 'unknown'
                END as availability_status
            FROM books b
            LEFT JOIN book_issues bi ON b.id = bi.book_id
            GROUP BY b.id
            HAVING issue_count > 0
            ORDER BY issue_count DESC, b.title ASC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $books = $stmt->fetchAll();
        
        sendResponse([
            'success' => true,
            'data' => $books,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        handleError('Database error: ' . $e->getMessage(), 500);
    }
}

// Get recently added books
function handleGetRecentBooks() {
    global $pdo;
    
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    
    try {
        $sql = "
            SELECT 
                b.*,
                COALESCE(COUNT(bi.id), 0) as total_issues,
                CASE 
                    WHEN b.available_copies > 0 THEN 'available'
                    WHEN b.available_copies = 0 AND b.copies > 0 THEN 'unavailable'
                    ELSE 'unknown'
                END as availability_status,
                DATE_FORMAT(b.created_at, '%M %d, %Y') as formatted_date
            FROM books b
            LEFT JOIN book_issues bi ON b.id = bi.book_id
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $books = $stmt->fetchAll();
        
        sendResponse([
            'success' => true,
            'data' => $books,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        handleError('Database error: ' . $e->getMessage(), 500);
    }
}

// Get books by category
function handleGetBooksByCategory() {
    global $pdo;
    
    if (!isset($_GET['category'])) {
        handleError('Category is required');
    }
    
    $category = trim($_GET['category']);
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    
    try {
        $sql = "
            SELECT 
                b.*,
                COALESCE(COUNT(bi.id), 0) as total_issues,
                CASE 
                    WHEN b.available_copies > 0 THEN 'available'
                    WHEN b.available_copies = 0 AND b.copies > 0 THEN 'unavailable'
                    ELSE 'unknown'
                END as availability_status
            FROM books b
            LEFT JOIN book_issues bi ON b.id = bi.book_id
            WHERE b.category = ?
            GROUP BY b.id
            ORDER BY b.title ASC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category, $limit]);
        $books = $stmt->fetchAll();
        
        sendResponse([
            'success' => true,
            'data' => $books,
            'category' => $category,
            'count' => count($books),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        handleError('Database error: ' . $e->getMessage(), 500);
    }
}

// Get book suggestions for autocomplete
function handleGetSuggestions() {
    global $pdo;
    
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'title'; // title, author, category
    $limit = min(10, max(1, (int)($_GET['limit'] ?? 5)));
    
    if (strlen($query) < 2) {
        handleError('Query must be at least 2 characters long');
    }
    
    try {
        $suggestions = [];
        
        switch ($type) {
            case 'title':
                $sql = "SELECT DISTINCT title as suggestion FROM books WHERE title LIKE ? ORDER BY title LIMIT ?";
                break;
            case 'author':
                $sql = "SELECT DISTINCT author as suggestion FROM books WHERE author LIKE ? ORDER BY author LIMIT ?";
                break;
            case 'category':
                $sql = "SELECT DISTINCT category as suggestion FROM books WHERE category LIKE ? AND category IS NOT NULL ORDER BY category LIMIT ?";
                break;
            default:
                handleError('Invalid suggestion type');
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["%$query%", $limit]);
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        sendResponse([
            'success' => true,
            'data' => $suggestions,
            'query' => $query,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        handleError('Database error: ' . $e->getMessage(), 500);
    }
}
?>