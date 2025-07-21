<?php
session_start();
require_once 'config/database.php';

// Check if the user is authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Handle book return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_book'])) {
    $issue_id = $_POST['issue_id'];
    $return_date = date('Y-m-d');

    // Update the book issue record
    $stmt = $pdo->prepare("UPDATE book_issues SET return_date = :return_date WHERE id = :issue_id");
    $stmt->execute(['return_date' => $return_date, 'issue_id' => $issue_id]);

    // Redirect to the return page with a success message
    header("Location: return-book.php?success=Book returned successfully.");
    exit();
}

// Fetch issued books for return
$stmt = $pdo->query("SELECT bi.id, b.title, m.name, bi.due_date FROM book_issues bi JOIN books b ON bi.book_id = b.id JOIN members m ON bi.member_id = m.id WHERE bi.return_date IS NULL");
$issued_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <h2>Return Book</h2>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Member</th>
                    <th>Due Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issued_books as $book): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                        <td><?php echo htmlspecialchars($book['name']); ?></td>
                        <td><?php echo htmlspecialchars($book['due_date']); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="issue_id" value="<?php echo $book['id']; ?>">
                                <button type="submit" name="return_book" class="btn">Return</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>