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
        
        // Verify password
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
    <title>Login - CRS Cafe System</title>
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
            /* Fallback background if video doesn't load */
            background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%); 
            font-family: 'Courier New', monospace; 
            min-height: 100vh;
            padding: 20px;
            overflow-y: auto;
            position: relative;
        }

        /* --- VIDEO BACKGROUND STYLES --- */
        #bg-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -1; /* Places video behind everything */
            object-fit: cover; /* Ensures video covers screen without stretching */
            filter: brightness(0.4); /* Darkens video so login form stands out */
        }
        
        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 40px);
        }
        
        .login-container {
            /* Made background slightly transparent white to show video blur behind it */
            background: rgba(255, 255, 255, 0.95);
            border: 4px solid var(--gold);
            border-radius: 20px;
            padding: 60px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.5);
            max-width: 500px;
            width: 100%;
            backdrop-filter: blur(5px); /* Modern frosted glass effect */
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 50%; /* Optional: Makes logo round */
            border: 3px solid var(--gold);
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
            background: rgba(255,255,255,0.9);
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
            background: rgba(201, 169, 97, 0.15);
            border: 2px dashed var(--gold);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            font-size: 0.95rem;
            color: var(--espresso);
        }
        
        .demo-info strong {
            color: #8B4513; /* Darker brown for better readability */
        }
        
        .demo-info code {
            background: rgba(26, 15, 10, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
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

        /* MINI GAME STYLES */
        .game-section {
            background: rgba(255, 255, 255, 0.5);
            border: 2px solid var(--gold);
            padding: 20px;
            border-radius: 10px;
            margin-top: 60px;
            text-align: center;
            position: relative;
        }

        .game-section::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 10%;
            width: 80%;
            height: 2px;
            background: var(--gold);
            opacity: 0.8;
        }

        .game-title {
            color: var(--espresso);
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        #gameCanvas {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 10px;
            display: block;
            margin: 0 auto 15px;
            cursor: pointer;
        }

        .game-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .score-display {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--espresso);
        }

        .btn-game {
            background: var(--espresso);
            color: var(--gold);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-game:hover {
            background: var(--gold);
            color: var(--espresso);
        }

        .game-instructions {
            font-size: 0.85rem;
            color: var(--espresso);
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="intro.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>

    <div class="page-wrapper">
        <div class="login-container">
        <div class="login-header">
            <img src="logo.jpg" alt="CRS Cafe Logo" class="login-logo">
            <h1>CRS CAFE</h1>
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

        <div class="game-section">
            <div class="game-title">
                <i class="fas fa-gamepad"></i> COFFEE BEAN CATCHER
            </div>
            <canvas id="gameCanvas" width="400" height="300"></canvas>
            <div class="game-controls">
                <div class="score-display">
                    <i class="fas fa-star"></i> Score: <span id="score">0</span>
                </div>
                <button class="btn-game" onclick="restartGame()">
                    <i class="fas fa-redo"></i> Restart
                </button>
            </div>
            <div class="game-instructions">
                Move your mouse to catch falling coffee beans! Avoid the burnt beans (red)
            </div>
        </div>
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

        // COFFEE BEAN CATCHER GAME
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        
        let score = 0;
        let gameRunning = true;
        let basketX = canvas.width / 2;
        const basketWidth = 60;
        const basketHeight = 15;
        
        let beans = [];
        let lastBeanTime = 0;
        const beanInterval = 1000; // New bean every second

        class Bean {
            constructor() {
                this.x = Math.random() * (canvas.width - 20) + 10;
                this.y = 0;
                this.size = 15;
                this.speed = 2 + Math.random() * 2;
                this.isBad = Math.random() < 0.2; // 20% chance of bad bean
            }

            update() {
                this.y += this.speed;
            }

            draw() {
                ctx.fillStyle = this.isBad ? '#8B0000' : '#6F4E37';
                ctx.beginPath();
                ctx.ellipse(this.x, this.y, this.size/2, this.size/1.5, 0, 0, Math.PI * 2);
                ctx.fill();
                
                // Add shine
                ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
                ctx.beginPath();
                ctx.ellipse(this.x - 3, this.y - 3, this.size/4, this.size/3, 0, 0, Math.PI * 2);
                ctx.fill();
            }

            isOffScreen() {
                return this.y > canvas.height;
            }

            isCaught() {
                return (
                    this.y + this.size >= canvas.height - basketHeight &&
                    this.x >= basketX - basketWidth/2 &&
                    this.x <= basketX + basketWidth/2
                );
            }
        }

        function drawBasket() {
            // Basket body
            ctx.fillStyle = '#C9A961';
            ctx.beginPath();
            ctx.moveTo(basketX - basketWidth/2, canvas.height - basketHeight);
            ctx.lineTo(basketX + basketWidth/2, canvas.height - basketHeight);
            ctx.lineTo(basketX + basketWidth/2 - 5, canvas.height);
            ctx.lineTo(basketX - basketWidth/2 + 5, canvas.height);
            ctx.closePath();
            ctx.fill();
            
            // Basket outline
            ctx.strokeStyle = '#1A0F0A';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Handle
            ctx.strokeStyle = '#1A0F0A';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.arc(basketX, canvas.height - basketHeight - 10, 15, 0, Math.PI, true);
            ctx.stroke();
        }

        function drawScore() {
            ctx.fillStyle = '#1A0F0A';
            ctx.font = 'bold 20px Courier New';
            ctx.fillText('Score: ' + score, 10, 30);
        }

        function updateScore(value) {
            score += value;
            document.getElementById('score').textContent = score;
        }

        function gameLoop(timestamp) {
            if (!gameRunning) return;

            // Clear canvas
            ctx.fillStyle = '#FDFBF7';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Spawn new beans
            if (timestamp - lastBeanTime > beanInterval) {
                beans.push(new Bean());
                lastBeanTime = timestamp;
            }

            // Update and draw beans
            beans.forEach((bean, index) => {
                bean.update();
                bean.draw();

                // Check if caught
                if (bean.isCaught()) {
                    if (bean.isBad) {
                        updateScore(-5);
                    } else {
                        updateScore(10);
                    }
                    beans.splice(index, 1);
                }

                // Remove off-screen beans
                if (bean.isOffScreen()) {
                    beans.splice(index, 1);
                }
            });

            drawBasket();
            drawScore();

            requestAnimationFrame(gameLoop);
        }

        // Mouse movement
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            basketX = e.clientX - rect.left;
            basketX = Math.max(basketWidth/2, Math.min(canvas.width - basketWidth/2, basketX));
        });

        // Touch support for mobile
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            basketX = touch.clientX - rect.left;
            basketX = Math.max(basketWidth/2, Math.min(canvas.width - basketWidth/2, basketX));
        });

        function restartGame() {
            score = 0;
            beans = [];
            gameRunning = true;
            updateScore(0);
            requestAnimationFrame(gameLoop);
        }

        // Start game
        requestAnimationFrame(gameLoop);
    </script>
</body>
</html>