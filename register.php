<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $password = $_POST['password'];


    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $passwordHash]);

    header("Location: login.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #6dd5ed, #2193b0);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            padding: 30px;
            width: 100%;
            max-width: 450px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .form-control {
            border-radius: 10px;
        }

        .btn-custom {
            background-color: #2193b0;
            border: none;
            border-radius: 10px;
            padding: 10px;
            color: white;
            font-weight: bold;
        }

        .btn-custom:hover {
            background-color: #1b7a99;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
    </style>
</head>
<body>

  <div class="card">
    <h2> Registration</h2>
    <form method="post" action="register.php">
      <div class="mb-3">
        <label for="name" class="form-label">👤 Name</label>
        <input type="text" class="form-control" id="name" name="name" required />
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">📧 Email</label>
        <input type="email" class="form-control" id="email" name="email" required />
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">🔑 Password</label>
        <input type="password" class="form-control" id="password" name="password" required />
      </div>

      <button type="submit" class="btn btn-custom w-100">Register</button>
    </form>
    <a href="login.php" class="btn btn-link mt-3">Already have an account? Login here</a>
  </div>

</body>
</html>
