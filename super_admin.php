<?php
session_start();

// DEBUG: Temporarily uncomment these lines to see what's in the session
// echo "<pre>";
// print_r($_SESSION);
// echo "</pre>";
// die();

// SECURITY: STRICT CHECK FOR SUPER ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    // If they are just a normal admin, send them back to dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        // Not logged in or not authorized
        header("Location: login.php");
    }
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

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- CREATE NEW USER ---
if (isset($_POST['create_user'])) {
    $u_name = mysqli_real_escape_string($conn, $_POST['new_username']);
    $u_pass = $_POST['new_password']; // Plain text for demo; use password_hash() in production
    $u_role = $_POST['new_role'];
    
    // Check if username exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$u_name'");
    if (mysqli_num_rows($check) > 0) {
        $message = "Username '$u_name' already exists!";
        $msgType = "danger";
    } else {
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $u_name, $u_pass, $u_role);
        if (mysqli_stmt_execute($stmt)) {
            $message = "New $u_role account created: $u_name";
            $msgType = "success";
        } else {
            $message = "Error creating account.";
            $msgType = "danger";
        }
    }
}

// --- DELETE USER ---
if (isset($_POST['delete_user'])) {
    $del_id = $_POST['delete_user_id'];
    // Prevent deleting yourself
    if ($del_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id = '$del_id'");
        $message = "User deleted successfully.";
        $msgType = "warning";
    } else {
        $message = "You cannot delete your own account!";
        $msgType = "danger";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Super Admin - User Management</title>
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
            overflow-y: auto;
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* HEADER */
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
        
        .btn-back {
            background: var(--gold);
            color: var(--espresso);
            border: none;
            padding: 8px 20px;
            font-weight: bold;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #d4b56d;
            color: var(--espresso);
            transform: scale(1.05);
        }

        /* PANELS */
        .panel {
            background: rgba(255, 255, 255, 0.95);
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-bottom: 20px;
            backdrop-filter: blur(5px);
        }

        .panel-header {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--gold);
            color: var(--espresso);
        }

        /* FORMS */
        .form-label {
            font-weight: bold;
            color: var(--espresso);
        }
        
        .form-control, .form-select {
            border: 2px solid #ddd;
            padding: 10px;
        }
        
        .btn-create {
            background: var(--espresso);
            color: var(--gold);
            border: 2px solid var(--gold);
            font-weight: bold;
            padding: 10px 20px;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .btn-create:hover {
            background: var(--gold);
            color: var(--espresso);
        }

        /* TABLE */
        .table-custom {
            width: 100%;
            margin-top: 20px;
        }
        
        .table-custom th {
            background: var(--espresso);
            color: white;
            padding: 15px;
        }
        
        .table-custom td {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .badge-role {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .role-super { background: #6f42c1; color: white; }
        .role-admin { background: #0d6efd; color: white; }
        .role-cashier { background: #198754; color: white; }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="newbgvideo.mp4" type="video/mp4">
    </video>

    <div class="container-wrapper">
        <div class="header-bar">
            <h1><i class="fas fa-users-cog"></i> SUPER ADMIN PANEL</h1>
            <div>
                <span class="me-3 text-white">Welcome, <?php echo strtoupper($_SESSION['username']); ?></span>
                <a href="admin.php" class="btn-back"><i class="fas fa-arrow-left"></i> BACK TO DASHBOARD</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-user-plus"></i> CREATE NEW USER
                    </div>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="new_username" class="form-control" placeholder="Enter username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Enter password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="new_role" class="form-select">
                                <option value="cashier">Cashier</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="create_user" class="btn btn-create">
                            <i class="fas fa-save"></i> CREATE ACCOUNT
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-users"></i> EXISTING ACCOUNTS
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>USERNAME</th>
                                    <th>ROLE</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $u_res = mysqli_query($conn, "SELECT * FROM users ORDER BY id ASC");
                                while($u = mysqli_fetch_assoc($u_res)):
                                    $roleClass = 'role-cashier';
                                    if($u['role'] == 'admin') $roleClass = 'role-admin';
                                    if($u['role'] == 'super_admin') $roleClass = 'role-super';
                                ?>
                                <tr>
                                    <td>#<?php echo $u['id']; ?></td>
                                    <td><strong><?php echo $u['username']; ?></strong></td>
                                    <td>
                                        <span class="badge-role <?php echo $roleClass; ?>">
                                            <?php echo strtoupper($u['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete user: <?php echo $u['username']; ?>?');">
                                            <input type="hidden" name="delete_user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> DELETE
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-muted text-center d-block"><i class="fas fa-user-circle"></i> (You)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>