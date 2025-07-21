<?php
// filepath: c:\xampp\htdocs\Librarymanagement\src\includes\header.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <header>
        <div class="header">
            <h1><i class="fas fa-book"></i> Library Management System</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i> Welcome, Admin
                <a href="logout.php" class="btn btn-small" style="margin-left: 10px;">Logout</a>
            </div>
        </div>
    </header>
    <nav class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Manage Members</a></li>
            <li><a href="issue-book.php"><i class="fas fa-hand-holding"></i> Issue Book</a></li>
            <li><a href="return-book.php"><i class="fas fa-undo"></i> Return Book</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </nav>
    <div class="main-content">