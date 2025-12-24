<?php
session_start();

// Check if user is logged in and is an admin
// (Supports both 'admin' and 'super_admin' roles just in case DB still has them)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
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

// --- LOG OUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// =======================
//    MENU LOGIC
// =======================
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
        $stmt = mysqli_prepare($conn, "INSERT INTO menu_tbl (product_id, drink_name, type, price) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $pid, $name, $type, $price);
        mysqli_stmt_execute($stmt);
        $message = "Added: $name";
        $msgType = "success";
    }
}

if (isset($_POST['update_drink'])) {
    $pid = $_POST['update_pid'];
    $price = $_POST['update_price'];
    $stmt = mysqli_prepare($conn, "UPDATE menu_tbl SET price = ? WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "ss", $price, $pid);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Price updated for ID: $pid";
        $msgType = "warning";
    }
}

if (isset($_POST['remove_drink'])) {
    $pid = $_POST['remove_pid'];
    $stmt = mysqli_prepare($conn, "DELETE FROM menu_tbl WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $pid);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Removed Item ID: $pid";
        $msgType = "danger";
    }
}

// =======================
//    USER / STAFF LOGIC
// =======================
if (isset($_POST['add_user'])) {
    $u_name = mysqli_real_escape_string($conn, $_POST['new_username']);
    $u_pass = $_POST['new_password'];
    $u_role = $_POST['new_role'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$u_name'");
    if (mysqli_num_rows($check) > 0) {
        $message = "Username '$u_name' already exists!";
        $msgType = "danger";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $u_name, $u_pass, $u_role);
        if (mysqli_stmt_execute($stmt)) {
            $message = "New $u_role account created: $u_name";
            $msgType = "success";
        }
    }
}

if (isset($_POST['delete_user'])) {
    $del_id = $_POST['delete_user_id'];
    if ($del_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id = '$del_id'");
        $message = "User deleted successfully.";
        $msgType = "warning";
    } else {
        $message = "You cannot delete your own account!";
        $msgType = "danger";
    }
}

// --- RESET DAY ---
if (isset($_POST['reset_day'])) {
    mysqli_query($conn, "TRUNCATE TABLE daily_sales");
    $message = "DAY RESET COMPLETE";
    $msgType = "dark";
}

// --- EXPORT TO CSV ---
if (isset($_POST['export_csv'])) {
    $filename = "Sales_Report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Time', 'Customer', 'Item', 'Price', 'Tendered', 'Change'));
    $query = "SELECT sale_time, customer_name, drink_name, price, amount_tendered, change_amount FROM daily_sales ORDER BY sale_time DESC";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// --- FETCH DATA ---
$sales_data = mysqli_query($conn, "SELECT * FROM daily_sales ORDER BY sale_time DESC");
$total_q = mysqli_query($conn, "SELECT SUM(price) as total FROM daily_sales");
$grand_total = mysqli_fetch_assoc($total_q)['total'] ?? 0;
$daily_target = 5000.00;
$progress_pct = ($grand_total > 0) ? ($grand_total / $daily_target) * 100 : 0;
$progress_color = ($progress_pct >= 100) ? 'success' : 'warning';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - CSR Cafe System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --espresso: #1A0F0A;
            --gold: #C9A961;
            --cream: #F5F5F0;
            --ivory: #FDFBF7;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%);
            font-family: 'Courier New', monospace;
            min-height: 100vh;
            overflow-y: hidden;
            padding: 20px;
        }
        
        #bg-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -1;
            object-fit: cover;
            filter: brightness(0.5);
        }
        
        .container-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            height: 100%;
        }
        
        .header-bar {
            background: rgba(26, 15, 10, 0.95);
            color: var(--gold);
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .header-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-logout, .btn-analytics, .btn-cashier {
            background: var(--gold);
            color: var(--espresso);
            border: none;
            padding: 8px 20px;
            font-size: 0.9rem;
            font-weight: bold;
            border-radius: 6px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-analytics { background: #4CAF50; color: white; }
        .btn-cashier { background: #007bff; color: white; }
        
        .btn-logout:hover { background: #d4b56d; transform: scale(1.05); color: var(--espresso); }
        .btn-analytics:hover { background: #45a049; transform: scale(1.05); color: white; }
        .btn-cashier:hover { background: #0069d9; transform: scale(1.05); color: white; }
        
        .main-layout {
            display: grid;
            grid-template-columns: 40% 60%;
            gap: 20px;
            margin-bottom: 20px;
            height: calc(100vh - 140px);
        }
        
        .panel {
            background: rgba(255, 255, 255, 0.92);
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            backdrop-filter: blur(5px);
        }
        
        .panel-padding { padding: 20px; overflow-y: auto; }
        
        .panel-header {
            background: rgba(255, 255, 255, 0.5);
            font-size: 1.4rem;
            font-weight: bold;
            padding: 20px;
            border-bottom: 3px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--espresso);
            flex-shrink: 0;
        }
        
        .table-scroll-area { flex-grow: 1; overflow-y: auto; }
        
        .table-custom { width: 100%; font-size: 0.9rem; border-collapse: collapse; }
        .table-custom th { background: var(--espresso); color: white; padding: 12px; font-size: 0.85rem; position: sticky; top: 0; z-index: 10; }
        .table-custom td { padding: 12px; border-bottom: 1px solid #e0e0e0; background: rgba(255,255,255,0.4); }
        
        .total-display {
            background: rgba(26, 15, 10, 0.95);
            color: var(--gold);
            padding: 20px;
            text-align: center;
            border-top: 3px solid var(--gold);
        }
        
        /* Main Tabs in Left Panel */
        .main-tabs .nav-link {
            background: rgba(255,255,255,0.5);
            color: var(--espresso);
            border-bottom: 3px solid transparent;
            border-radius: 0;
            width: 50%;
            font-weight: bold;
            padding: 15px;
        }
        .main-tabs .nav-link.active { background: white; border-bottom: 3px solid var(--espresso); color: var(--espresso); }
        
        .sub-tabs .nav-link {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px 12px;
            margin-right: 5px;
            color: #555;
            font-weight: bold;
        }
        .sub-tabs .nav-link.active { background: var(--espresso); color: white; border-color: var(--espresso); }
        
        /* User Table */
        .user-table th { background: #666; color: white; padding: 10px; font-size: 0.85rem; }
        .user-table td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 0.95rem; vertical-align: middle; }
        
        .btn-receipt {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video"><source src="newbgvideo.mp4" type="video/mp4"></video>

<div class="container-wrapper">
    <div class="header-bar">
        <h1><i class="fas fa-user-shield"></i> ADMIN DASHBOARD</h1>
        <div class="user-info">
            <div class="username me-3"><i class="fas fa-user-cog"></i> <?php echo strtoupper($_SESSION['username']); ?></div>
            <a href="analytics.php" class="btn-analytics"><i class="fas fa-chart-line"></i> ANALYTICS</a>
            <a href="?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> LOGOUT</a>
        </div>
    </div>
    
    <div class="main-layout">
        <div class="panel">
            <ul class="nav nav-pills main-tabs" role="tablist">
                <li class="nav-item w-50" role="presentation">
                    <button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#menu-tab-content">
                        <i class="fas fa-coffee"></i> MENU
                    </button>
                </li>
                <li class="nav-item w-50" role="presentation">
                    <button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#staff-tab-content">
                        <i class="fas fa-users"></i> STAFF
                    </button>
                </li>
            </ul>

            <div class="panel-padding tab-content h-100">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="tab-pane fade show active" id="menu-tab-content">
                    <ul class="nav nav-tabs sub-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#add-prod">Add</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#upd-prod">Update</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#del-prod">Remove</button></li>
                    </ul>
                    
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="add-prod">
                            <form method="POST" class="row g-2">
                                <div class="col-6"><input type="text" name="new_pid" class="form-control form-control-sm" placeholder="ID" required></div>
                                <div class="col-6"><input type="text" name="new_type" class="form-control form-control-sm" placeholder="Type" required></div>
                                <div class="col-12"><input type="text" name="new_name" class="form-control form-control-sm" placeholder="Drink Name" required></div>
                                <div class="col-12"><input type="number" step="0.01" name="new_price" class="form-control form-control-sm" placeholder="Price" required></div>
                                <button type="submit" name="add_drink" class="btn btn-success w-100 mt-2">Add Item</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="upd-prod">
                            <form method="POST" class="row g-2">
                                <div class="col-6"><input type="text" name="update_pid" class="form-control form-control-sm" placeholder="Product ID" required></div>
                                <div class="col-6"><input type="number" step="0.01" name="update_price" class="form-control form-control-sm" placeholder="New Price" required></div>
                                <button type="submit" name="update_drink" class="btn btn-warning w-100 mt-2">Update Price</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="del-prod">
                            <form method="POST">
                                <input type="text" name="remove_pid" class="form-control form-control-sm mb-2" placeholder="Product ID to Delete" required>
                                <button type="submit" name="remove_drink" class="btn btn-danger w-100 mt-2" onclick="return confirm('Delete this item?')">Delete Item</button>
                            </form>
                        </div>
                    </div>
                    
                    <hr>
                    <form method="POST" onsubmit="return confirm('WARNING: Reset sales history?');">
                        <button type="submit" name="reset_day" class="btn btn-dark w-100 btn-sm"><i class="fas fa-power-off"></i> RESET SALES DAY</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="staff-tab-content">
                    <ul class="nav nav-tabs sub-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#list-staff">View Staff</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#add-staff">Add Staff</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="list-staff">
                            <table class="table user-table table-hover">
                                <thead><tr><th>User</th><th>Role</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php
                                    $u_res = mysqli_query($conn, "SELECT * FROM users ORDER BY id ASC");
                                    while($u = mysqli_fetch_assoc($u_res)):
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $u['username']; ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo $u['role']; ?></span></td>
                                        <td>
                                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" onsubmit="return confirm('Permanently delete user: <?php echo $u['username']; ?>?');" style="display:inline;">
                                                <input type="hidden" name="delete_user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">DELETE</button>
                                            </form>
                                            <?php else: ?><small class="text-muted">(You)</small><?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="tab-pane fade" id="add-staff">
                            <form method="POST" class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">New Username</label>
                                    <input type="text" name="new_username" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted">Role</label>
                                    <select name="new_role" class="form-select">
                                        <option value="cashier">Cashier</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" name="add_user" class="btn btn-primary w-100 py-2">Create Account</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: auto; padding: 10px; text-align: center; border-top: 1px solid #eee; font-size: 0.7rem; color: #888;">
                <i class="fas fa-code"></i> Dev Team: Rayver, Char Mae, Sebastian
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-header">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <span><i class="fas fa-receipt"></i> TODAY'S SALES</span>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="export_csv" class="btn btn-success btn-sm" style="margin: 0;">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="table-scroll-area">
                <table class="table-custom">
                    <thead><tr><th width="15%">TIME</th><th width="35%">CUSTOMER</th><th width="30%">ITEM</th><th width="20%" class="text-end">PRICE</th></tr></thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($sales_data) > 0) {
                            mysqli_data_seek($sales_data, 0); 
                            while ($row = mysqli_fetch_assoc($sales_data)): 
                        ?>
                        <tr>
                            <td><?php echo date('H:i', strtotime($row['sale_time'])); ?></td>
                            <td><?php echo strtoupper(substr($row['customer_name'], 0, 15)); ?></td>
                            <td><?php echo $row['drink_name']; ?></td>
                            <td class="text-end fw-bold">₱<?php echo number_format($row['price'], 2); ?></td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else { 
                            echo '<tr><td colspan="4" class="text-center p-5 text-muted">No sales yet</td></tr>'; 
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="total-display">
                <div class="fw-bold fs-3 mb-2">TOTAL: ₱<?php echo number_format($grand_total, 2); ?></div>
                <div class="text-start fs-6">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="color:var(--gold)">Goal: <?php echo number_format($progress_pct, 0); ?>%</span>
                        <span style="color:var(--gold)">Target: ₱<?php echo number_format($daily_target); ?></span>
                    </div>
                    <div class="progress" style="height: 10px; background: rgba(201,169,97,0.3);">
                        <div class="progress-bar bg-<?php echo $progress_color; ?>" style="width: <?php echo min($progress_pct, 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>