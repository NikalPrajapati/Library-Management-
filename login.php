<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $_SESSION['user'] = $_POST['email'] ?? 'Guest'; 
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #e96443, #904e95);
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .card {
      padding: 30px;
      width: 100%;
      max-width: 400px;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      background-color: white;
    }
    .btn-custom {
      background-color: #904e95;
      border: none;
      border-radius: 10px;
      padding: 10px;
      color: white;
      font-weight: bold;
    }
    .btn-custom:hover {
      background-color: #6b3d70;
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
    <h2>🔐 Login</h2>

    <form method="post" action="login.php">
      <div class="mb-3">
        <label for="email" class="form-label">📧 Email</label>
        <input type="email" class="form-control" id="email" name="email" required />
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">🔑 Password</label>
        <input type="password" class="form-control" id="password" name="password" required />
      </div>

       <div class="mb-3">     
        <label>
          <input type="checkbox" name="remember"> Remember me
        </label>
      </div>

      <div class="container" style="background-color:#f1f1f1">
        <span class="psw">Forgot <a href="#">password?</a></span>
      </div>

      <button type="submit" class="btn btn-custom w-100">Login</button>
    </form>

    <a href="register.php" class="btn btn-link mt-3">New account? Register here</a>
  </div>
</body>
</html> 
