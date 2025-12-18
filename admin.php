<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
$DBHost = "localhost";
$DBUser = "root";
$DBPass = "";
$DBName = "cafe_db";
$conn = mysqli_connect($DBHost, $DBUser, $DBPass, $DBName);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$message = "";
$msgType = "";

// --- HANDLE LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- ADD NEW DRINK ---
if (isset($_POST['add_drink'])) {
    $pid = $_POST['new_pid'];
    $name = $_POST['new_name'];
    $type = $_POST['new_type'];
    $price = $_POST['new_price'];
    
    $check = mysqli_query($conn, "SELECT product_id FROM menu_tbl WHERE product_id = '$pid'");
    if (mysqli_num_rows($check) > 0) {
        $message = "ID $pid already exists!"; 
        $msgType = "danger";
    } else {
        $sqli = "INSERT INTO menu_tbl (product_id, drink_name, type, price) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sqli);
        mysqli_stmt_bind_param($stmt, "ssss", $pid, $name, $type, $price);
        mysqli_stmt_execute($stmt);
        $message = "Added: $name"; 
        $msgType = "success";
    }
}

// --- UPDATE PRICE ---
if (isset($_POST['update_drink'])) {
    $pid = $_POST['update_pid'];
    $price = $_POST['update_price'];
    
    $sqli = "UPDATE menu_tbl SET price = ? WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $sqli);
    mysqli_stmt_bind_param($stmt, "ss", $price, $pid);
    if(mysqli_stmt_execute($stmt)){
        $message = "Price updated for ID: $pid"; 
        $msgType = "warning";
    }
}

// --- REMOVE DRINK ---
if (isset($_POST['remove_drink'])) {
    $pid = $_POST['remove_pid'];
    $sqli = "DELETE FROM menu_tbl WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $sqli);
    mysqli_stmt_bind_param($stmt, "s", $pid);
    if(mysqli_stmt_execute($stmt)){
        $message = "Removed Item ID: $pid"; 
        $msgType = "danger";
    }
}

// --- RESET DAY ---
if (isset($_POST['reset_day'])) {
    mysqli_query($conn, "TRUNCATE TABLE daily_sales");
    $message = "DAY RESET COMPLETE"; 
    $msgType = "dark";
}

// --- FETCH DATA ---
$sales_data = mysqli_query($conn, "SELECT * FROM daily_sales ORDER BY sale_time DESC");
$total_q = mysqli_query($conn, "SELECT SUM(price) as total FROM daily_sales");
$grand_total = mysqli_fetch_assoc($total_q)['total'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Coffee Shop System</title>
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
            overflow-y: auto;
            padding: 40px;
        }
        
        .container-wrapper {
            max-width: 2000px;
            margin: 0 auto;
        }
        
        /* HEADER BAR */
        .header-bar {
            background: var(--espresso);
            color: var(--gold);
            padding: 40px 50px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-bar h1 {
            margin: 0;
            font-size: 3rem;
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info .username {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .btn-logout {
            background: var(--gold);
            color: var(--espresso);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #d4b56d;
            transform: scale(1.05);
            color: var(--espresso);
        }
        
        /* TWO COLUMN LAYOUT */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        /* PANELS */
        .panel {
            background: white;
            border: 4px solid var(--gold);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            min-height: 600px;
        }
        
        .panel-header {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 4px solid currentColor;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--espresso);
        }
        
        /* TABLES */
        .table-custom {
            width: 100%;
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        
        .table-custom th {
            background: var(--espresso);
            color: white;
            padding: 20px;
            font-size: 1.1rem;
            letter-spacing: 2px;
        }
        
        .table-custom td {
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .table-custom tbody tr:hover {
            background: rgba(201,169,97,0.15);
        }
        
        /* TOTAL DISPLAY */
        .total-display {
            background: var(--espresso);
            color: var(--gold);
            padding: 30px;
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            border-radius: 15px;
            border: 4px solid var(--gold);
            margin-top: 30px;
        }
        
        /* ADMIN TABS */
        .nav-tabs {
            border: none;
            margin-bottom: 30px;
            gap: 10px;
        }
        
        .nav-tabs .nav-link {
            background: transparent;
            border: 3px solid #ddd;
            color: var(--espresso);
            padding: 15px 30px;
            font-size: 1.2rem;
            border-radius: 12px 12px 0 0;
            font-weight: bold;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--espresso);
            color: white;
            border-color: var(--espresso);
        }
        
        .tab-content {
            padding: 30px 0;
        }
        
        .form-control-sm {
            padding: 15px;
            font-size: 1.1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        
        .btn-sm {
            padding: 15px;
            font-size: 1.1rem;
            margin-top: 15px;
            font-weight: bold;
            border-radius: 8px;
        }
        
        .btn-reset {
            width: 100%;
            margin-top: 30px;
            padding: 18px;
            background: var(--espresso);
            color: white;
            border: 3px solid var(--gold);
            font-weight: bold;
            font-size: 1.2rem;
            border-radius: 12px;
        }
        
        .btn-reset:hover {
            background: var(--gold);
            color: var(--espresso);
        }
        
        .alert {
            margin-bottom: 30px;
            padding: 20px;
            font-size: 1.1rem;
            border-radius: 12px;
        }
        
        /* ABOUT US SECTION */
        .about-section {
            background: var(--espresso);
            color: var(--gold);
            border: 4px solid var(--gold);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .about-section h2 {
            font-size: 2.5rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .about-section p {
            font-size: 1.3rem;
            margin-bottom: 15px;
            line-height: 1.8;
        }
        
        .developers {
            margin-top: 30px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 14px; }
        ::-webkit-scrollbar-track { background: var(--cream); }
        ::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; border: 3px solid var(--cream); }
        ::-webkit-scrollbar-thumb:hover { background: var(--espresso); }
    </style>
</head>
<body>
<div class="container-wrapper">
    <!-- HEADER -->
    <div class="header-bar">
        <h1><i class="fas fa-user-shield"></i> ADMIN DASHBOARD</h1>
        <div class="user-info">
            <div class="username">
                <i class="fas fa-user-cog"></i> <?php echo strtoupper($_SESSION['username']); ?>
            </div>
            <a href="?logout=1" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
            </a>
        </div>
    </div>
    
    <!-- MAIN TWO COLUMN LAYOUT -->
    <div class="main-layout">
        <!-- LEFT: TODAY'S SALES -->
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-receipt"></i> TODAY'S SALES
            </div>
            
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>TIME</th>
                        <th>CUSTOMER</th>
                        <th>ITEM</th>
                        <th>PRICE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (mysqli_num_rows($sales_data) > 0) {
                        while($row = mysqli_fetch_assoc($sales_data)): 
                    ?>
                    <tr>
                        <td><?php echo date('H:i', strtotime($row['sale_time'])); ?></td>
                        <td><?php echo strtoupper(substr($row['customer_name'], 0, 15)); ?></td>
                        <td><?php echo $row['drink_name']; ?></td>
                        <td>$<?php echo number_format($row['price'], 2); ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    } else {
                        echo '<tr><td colspan="4" style="text-align: center; color: #999; padding: 60px;">No sales recorded yet</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="total-display">
                TOTAL: $<?php echo number_format($grand_total, 2); ?>
            </div>
        </div>
        
        <!-- RIGHT: ADMIN PANEL -->
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-tools"></i> PRODUCT MANAGEMENT
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#add">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#update">
                        <i class="fas fa-edit"></i> Update
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#remove">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- ADD TAB -->
                <div class="tab-pane fade show active" id="add">
                    <form method="POST" class="row g-3">
                        <div class="col-6">
                            <input type="text" name="new_pid" class="form-control form-control-sm" placeholder="Product ID" required>
                        </div>
                        <div class="col-6">
                            <input type="text" name="new_name" class="form-control form-control-sm" placeholder="Drink Name" required>
                        </div>
                        <div class="col-6">
                            <input type="text" name="new_type" class="form-control form-control-sm" placeholder="Type (e.g., Hot, Cold)" required>
                        </div>
                        <div class="col-6">
                            <input type="number" step="0.01" name="new_price" class="form-control form-control-sm" placeholder="Price" required>
                        </div>
                        <button type="submit" name="add_drink" class="btn btn-success btn-sm w-100">
                            <i class="fas fa-plus-circle"></i> Add New Item to Menu
                        </button>
                    </form>
                </div>
                
                <!-- UPDATE TAB -->
                <div class="tab-pane fade" id="update">
                    <form method="POST" class="row g-3">
                        <div class="col-6">
                            <input type="text" name="update_pid" class="form-control form-control-sm" placeholder="Product ID" required>
                        </div>
                        <div class="col-6">
                            <input type="number" step="0.01" name="update_price" class="form-control form-control-sm" placeholder="New Price" required>
                        </div>
                        <button type="submit" name="update_drink" class="btn btn-warning btn-sm w-100">
                            <i class="fas fa-edit"></i> Update Price
                        </button>
                    </form>
                </div>
                
                <!-- REMOVE TAB -->
                <div class="tab-pane fade" id="remove">
                    <form method="POST">
                        <input type="text" name="remove_pid" class="form-control form-control-sm mb-3" placeholder="Product ID to Delete" required>
                        <button type="submit" name="remove_drink" class="btn btn-danger btn-sm w-100" onclick="return confirm('Are you sure you want to delete this item?')">
                            <i class="fas fa-trash-alt"></i> Delete Item Permanently
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- RESET DAY BUTTON -->
            <form method="POST" onsubmit="return confirm('WARNING: This will delete all sales history for today. Continue?');">
                <button type="submit" name="reset_day" class="btn btn-reset">
                    <i class="fas fa-power-off"></i> END DAY / RESET SALES
                </button>
            </form>
        </div>
    </div>
    
    <!-- ABOUT US SECTION -->
    <div class="about-section">
        <h2><i class="fas fa-users"></i> ABOUT US</h2>
        <p>This Coffee Shop Master System was crafted with passion and dedication</p>
        <p>to streamline coffee shop operations and enhance customer experience.</p>
        <div class="developers">
            <i class="fas fa-code"></i> DEVELOPED BY:<br>
            Char Mae Grace Bering & Rayver S. Reyes
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>