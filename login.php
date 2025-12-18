<?php
session_start();

// --- DATABASE CONNECTION ---
$DBHost = "localhost";
$DBUser = "root";
$DBPass = "";
$DBName = "cafe_db";
$conn = mysqli_connect($DBHost, $DBUser, $DBPass, $DBName);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$error = "";

// --- HANDLE LOGIN ---
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Check user credentials
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify password (plain text comparison - in production use password_hash/password_verify)
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: cashier.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Coffee Shop System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --espresso: #1A0F0A; 
            --gold: #C9A961; 
            --cream: #F5F5F0; 
            --ivory: #FDFBF7;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: linear-gradient(135deg, var(--ivory) 0%, var(--cream) 100%); 
            font-family: 'Courier New', monospace; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border: 4px solid var(--gold);
            border-radius: 20px;
            padding: 60px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header i {
            font-size: 5rem;
            color: var(--espresso);
            margin-bottom: 20px;
        }
        
        .login-header h1 {
            color: var(--espresso);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--gold);
            font-size: 1.2rem;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .form-label {
            color: var(--espresso);
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: block;
            letter-spacing: 1px;
        }
        
        .form-control {
            padding: 18px;
            font-size: 1.1rem;
            border: 3px solid var(--gold);
            border-radius: 10px;
            margin-bottom: 25px;
            font-family: 'Courier New', monospace;
        }
        
        .form-control:focus {
            border-color: var(--espresso);
            box-shadow: 0 0 0 0.2rem rgba(201, 169, 97, 0.25);
        }
        
        .btn-login {
            background: var(--espresso);
            color: var(--gold);
            border: none;
            padding: 20px;
            font-size: 1.4rem;
            font-weight: bold;
            width: 100%;
            border-radius: 10px;
            transition: all 0.3s;
            letter-spacing: 2px;
        }
        
        .btn-login:hover {
            background: var(--gold);
            color: var(--espresso);
            transform: scale(1.02);
        }
        
        .alert {
            padding: 18px;
            font-size: 1.1rem;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .demo-info {
            background: rgba(201, 169, 97, 0.1);
            border: 2px dashed var(--gold);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            font-size: 0.95rem;
            color: var(--espresso);
        }
        
        .demo-info strong {
            color: var(--gold);
        }
        
        .demo-info code {
            background: rgba(26, 15, 10, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        
        .password-container {
            position: relative;
            margin-bottom: 25px;
        }
        
        .password-container .form-control {
            margin-bottom: 0;
            padding-right: 50px;
        }
        
        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--espresso);
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-coffee"></i>
            <h1>COFFEE SHOP</h1>
            <p>MASTER SYSTEM</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <label class="form-label">USERNAME</label>
            <input type="text" name="username" class="form-control" required autofocus>
            
            <label class="form-label">PASSWORD</label>
            <div class="password-container">
                <input type="password" name="password" id="password" class="form-control" required>
                <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
            </div>
            
            <button type="submit" name="login" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> LOGIN
            </button>
        </form>
        
        <div class="demo-info">
            <strong><i class="fas fa-info-circle"></i> DEMO ACCOUNTS:</strong><br><br>
            <strong>Admin:</strong> <code>admin/admin123</code><br>
            <strong>Cashier:</strong> <code>cashier/cashier123</code>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>