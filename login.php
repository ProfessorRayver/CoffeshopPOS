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
    <title>Login - CSR Cafe System</title>
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
            background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%); 
            font-family: 'Courier New', monospace; 
            min-height: 100vh;
            padding: 20px;
            overflow-y: auto;
        }

        /* --- VIDEO BACKGROUND --- */
        #bg-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -1;
            object-fit: cover;
            filter: brightness(0.4);
        }
        
        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 40px);
            padding: 20px;
        }
        
        /* --- LAYOUT CONTAINER --- */
        .panels-container {
            display: flex;
            gap: 0; 
            width: 1400px;
            max-width: 95%;
            align-items: stretch;
            height: 750px; 
            max-height: 95vh;
        }

        /* --- LEFT PANEL: WELCOME CARD (70%) --- */
        .welcome-card {
            flex: 7;
            background: linear-gradient(135deg, rgba(26, 15, 10, 0.9) 0%, rgba(62, 39, 35, 0.9) 100%);
            color: var(--gold);
            border: 4px solid var(--gold);
            border-right: none;
            border-radius: 20px 0 0 20px;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.6);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('logo.jpg') center/cover;
            opacity: 0.1;
            z-index: 0;
        }

        .welcome-content {
            z-index: 1;
            max-width: 80%;
        }

        .welcome-card h1 {
            font-size: 5rem;
            margin-bottom: 20px;
            line-height: 1.1;
            text-shadow: 4px 4px 8px rgba(0,0,0,0.8);
            font-weight: 800;
        }

        .welcome-card p {
            font-size: 1.8rem;
            color: var(--cream);
            margin-bottom: 40px;
            letter-spacing: 4px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .quote-box {
            border-top: 3px solid var(--gold);
            border-bottom: 3px solid var(--gold);
            padding: 30px 0;
            margin-top: 30px;
            font-style: italic;
            font-size: 1.4rem;
            color: var(--ivory);
            line-height: 1.6;
        }

        /* --- RIGHT PANEL: LOGIN CARD (30%) --- */
        .login-card {
            flex: 3;
            background: rgba(255, 255, 255, 0.95);
            border: 4px solid var(--gold);
            border-left: none;
            border-radius: 0 20px 20px 0;
            box-shadow: 0 15px 40px rgba(0,0,0,0.6);
            display: block; /* Allows scrolling flow */
            overflow-y: auto; 
            padding: 0 30px; 
            backdrop-filter: blur(10px);
            position: relative;
            scrollbar-width: thin;
            scrollbar-color: var(--gold) transparent;
        }

        /* WRAPPER 1: LOGIN CONTENT */
        .login-content-wrapper {
            min-height: 100%; /* Forces full height */
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 0;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .login-logo {
            max-width: 90px;
            height: auto;
            margin-bottom: 15px;
            border-radius: 50%;
            border: 3px solid var(--gold);
        }
        
        .login-header h2 {
            color: var(--espresso);
            font-size: 1.6rem;
            margin: 0;
            font-weight: bold;
            border-bottom: 2px solid var(--gold);
            display: inline-block;
            padding-bottom: 8px;
        }
        
        .form-label {
            color: var(--espresso);
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
            letter-spacing: 1px;
        }
        
        .form-control {
            padding: 14px;
            font-size: 1rem;
            border: 2px solid var(--gold);
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.8);
        }
        
        .form-control:focus {
            border-color: var(--espresso);
            box-shadow: 0 0 0 0.2rem rgba(201, 169, 97, 0.25);
            background: white;
        }
        
        .btn-login {
            background: var(--espresso);
            color: var(--gold);
            border: none;
            padding: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            width: 100%;
            border-radius: 8px;
            transition: all 0.3s;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background: var(--gold);
            color: var(--espresso);
            transform: scale(1.02);
        }
        
        .alert {
            padding: 12px;
            font-size: 0.9rem;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .demo-info {
            background: rgba(201, 169, 97, 0.15);
            border: 1px dashed var(--gold);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--espresso);
        }
        
        .password-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .password-container .form-control {
            margin-bottom: 0;
            padding-right: 40px;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--espresso);
            font-size: 1.1rem;
        }

        .scroll-hint {
            text-align: center;
            font-size: 0.8rem;
            color: var(--espresso);
            opacity: 0.5;
            margin-top: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-5px);}
            60% {transform: translateY(-3px);}
        }

        /* GAME SECTION - Pushed below fold */
        .game-section {
            background: rgba(26, 15, 10, 0.05);
            border-top: 2px dashed var(--gold);
            padding: 30px 0;
            text-align: center;
            margin-bottom: 40px; 
        }

        .game-title {
            color: var(--espresso);
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        #gameCanvas {
            background: white;
            border: 2px solid var(--gold);
            border-radius: 8px;
            display: block;
            margin: 0 auto 10px;
            cursor: pointer;
            max-width: 100%;
        }

        .game-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 0 10px;
        }

        .btn-game {
            background: var(--gold);
            color: var(--espresso);
            border: none;
            padding: 5px 15px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.8rem;
            cursor: pointer;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .panels-container {
                flex-direction: column;
                height: auto;
            }
            .welcome-card {
                padding: 40px;
                border-right: 4px solid var(--gold);
                border-bottom: none;
                border-radius: 20px 20px 0 0;
            }
            .welcome-card h1 { font-size: 3rem; }
            .login-card {
                border-left: 4px solid var(--gold);
                border-top: none;
                border-radius: 0 0 20px 20px;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="intro.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>

    <audio id="bg-music" loop autoplay>
        <source src="intromusic.mp3" type="audio/mpeg">
    </audio>

    <div class="page-wrapper">
        <div class="panels-container">
            
            <div class="welcome-card">
                <div class="welcome-content">
                    <h1>WELCOME<br>TO CSR CAFE</h1>
                    <p>MASTER SYSTEM</p>
                    
                    <div class="quote-box">
                        <i class="fas fa-mug-hot" style="font-size: 2.5rem; margin-bottom: 20px; display:block; color: var(--gold);"></i>
                        "Coffee is a language in itself.<br>Speak it fluently."
                    </div>
                    
                    <div style="margin-top: 50px; font-size: 1rem; opacity: 0.8;">
                        <i class="fas fa-wifi"></i> System Online & Ready | <i class="fas fa-clock"></i> <?php echo date('h:i A'); ?>
                    </div>
                </div>
            </div>

            <div class="login-card">
                
                <div class="login-content-wrapper">
                    <div class="login-header">
                        <img src="logo.jpg" alt="CSR Cafe Logo" class="login-logo">
                        <br>
                        <h2>STAFF LOGIN</h2>
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
                            <i class="fas fa-sign-in-alt"></i> ENTER SYSTEM
                        </button>
                    </form>
                    
                    <div class="demo-info">
                        <strong><i class="fas fa-key"></i> DEMO ACCESS:</strong><br>
                        Admin: <code>admin/admin123</code><br>
                        Cashier: <code>cashier/cashier123</code>
                    </div>
                    
                    <div class="scroll-hint">
                        <i class="fas fa-chevron-down"></i> Scroll down for a break
                    </div>
                </div>

                <div class="game-section">
                    <div class="game-title">
                        Waiting? Catch some beans!
                    </div>
                    <canvas id="gameCanvas" width="320" height="200"></canvas>
                    <div class="game-controls">
                        <div class="score-display">
                            <i class="fas fa-star"></i> Score: <span id="score">0</span>
                        </div>
                        <button class="btn-game" onclick="restartGame()">
                            <i class="fas fa-redo"></i> Restart
                        </button>
                    </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            const bgMusic = document.getElementById('bg-music');
            bgMusic.volume = 0.5;
            bgMusic.play().catch(error => {
                document.addEventListener('click', () => bgMusic.play(), { once: true });
            });
        });

        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        
        let score = 0;
        let gameRunning = true;
        let basketX = canvas.width / 2;
        const basketWidth = 40; 
        const basketHeight = 10;
        
        let beans = [];
        let lastBeanTime = 0;
        const beanInterval = 1000;

        class Bean {
            constructor() {
                this.x = Math.random() * (canvas.width - 20) + 10;
                this.y = 0;
                this.size = 10;
                this.speed = 1.5 + Math.random() * 1.5;
                this.isBad = Math.random() < 0.2;
            }

            update() {
                this.y += this.speed;
            }

            draw() {
                ctx.fillStyle = this.isBad ? '#8B0000' : '#6F4E37';
                ctx.beginPath();
                ctx.ellipse(this.x, this.y, this.size/2, this.size/1.5, 0, 0, Math.PI * 2);
                ctx.fill();
                
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
            ctx.fillStyle = '#C9A961';
            ctx.beginPath();
            ctx.moveTo(basketX - basketWidth/2, canvas.height - basketHeight);
            ctx.lineTo(basketX + basketWidth/2, canvas.height - basketHeight);
            ctx.lineTo(basketX + basketWidth/2 - 5, canvas.height);
            ctx.lineTo(basketX - basketWidth/2 + 5, canvas.height);
            ctx.closePath();
            ctx.fill();
            
            ctx.strokeStyle = '#1A0F0A';
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        function updateScore(value) {
            score += value;
            document.getElementById('score').textContent = score;
        }

        function gameLoop(timestamp) {
            if (!gameRunning) return;

            ctx.fillStyle = '#FDFBF7';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            if (timestamp - lastBeanTime > beanInterval) {
                beans.push(new Bean());
                lastBeanTime = timestamp;
            }

            beans.forEach((bean, index) => {
                bean.update();
                bean.draw();

                if (bean.isCaught()) {
                    if (bean.isBad) {
                        updateScore(-5);
                    } else {
                        updateScore(10);
                    }
                    beans.splice(index, 1);
                }

                if (bean.isOffScreen()) {
                    beans.splice(index, 1);
                }
            });

            drawBasket();
            requestAnimationFrame(gameLoop);
        }

        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            basketX = e.clientX - rect.left;
            basketX = Math.max(basketWidth/2, Math.min(canvas.width - basketWidth/2, basketX));
        });

        function restartGame() {
            score = 0;
            beans = [];
            gameRunning = true;
            updateScore(0);
            requestAnimationFrame(gameLoop);
        }

        requestAnimationFrame(gameLoop);
    </script>
</body>
</html>