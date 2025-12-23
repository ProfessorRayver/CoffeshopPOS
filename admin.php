<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- database conn ---
$DBHost = "localhost";
$DBUser = "root";
$DBPass = "";
$DBName = "cafe_db";
$conn = mysqli_connect($DBHost, $DBUser, $DBPass, $DBName);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$message = "";
$msgType = "";

// ---  log out ---
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


// --- EXPORT TO CSV ---
if (isset($_POST['export_csv'])) {
    $filename = "Sales_Report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Time', 'Customer', 'Item', 'Price', 'Tendered', 'Change'));
    
    $query = "SELECT sale_time, customer_name, drink_name, price, amount_tendered, change_amount FROM daily_sales ORDER BY sale_time DESC";
    $result = mysqli_query($conn, $query);
    while($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// ---  fetch of data ---
$sales_data = mysqli_query($conn, "SELECT * FROM daily_sales ORDER BY sale_time DESC");
$total_q = mysqli_query($conn, "SELECT SUM(price) as total FROM daily_sales");
$grand_total = mysqli_fetch_assoc($total_q)['total'] ?? 0;

// --- calculate the goal saless ---
$daily_target = 5000.00; // <----   Set daily goal here
$progress_pct = ($grand_total > 0) ? ($grand_total / $daily_target) * 100 : 0;
$progress_color = ($progress_pct >= 100) ? 'success' : 'warning';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - CRS Cafe System</title>
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: linear-gradient(135deg, var(--ivory) 0%, var(--cream) 100%); 
            font-family: 'Courier New', monospace; 
            min-height: 100vh;
            overflow-y: hidden; /* UPDATED: Prevent body scroll */
            padding: 20px;
        }
        
        .container-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            height: 100%; /* UPDATED */
        }
        
        /* HEADER BAR */
        .header-bar {
            background: var(--espresso);
            color: var(--gold);
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info .username {
            font-size: 0.95rem;
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
        
        .btn-analytics {
            background: #4CAF50;
            color: white;
        }
        
        .btn-cashier {
            background: #007bff;
            color: white;
        }
        
        .btn-logout:hover {
            background: #d4b56d;
            transform: scale(1.05);
            color: var(--espresso);
        }
        
        .btn-analytics:hover {
            background: #45a049;
            transform: scale(1.05);
            color: white;
        }
        
        .btn-cashier:hover {
            background: #0069d9;
            transform: scale(1.05);
            color: white;
        }
        
        /* --- MAIN LAYOUT FIXED FOR ALIGNMENT --- */
        .main-layout {
            display: grid;
            grid-template-columns: 40% 60%;
            gap: 20px;
            margin-bottom: 20px;
            height: calc(100vh - 140px); /* UPDATED: Fill remaining screen height */
        }

        /* PANEL STYLES UPDATED FOR FLEXBOX */
        .panel {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 0; /* UPDATED: No padding on container, handled by children */
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden; /* UPDATED: Clean corners */
        }

        .panel-padding {
            padding: 20px; /* Helper for content that needs padding */
            overflow-y: auto;
        }

        .panel-header {
            background: white;
            font-size: 1.4rem;
            font-weight: bold;
            padding: 20px;
            border-bottom: 3px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--espresso);
            flex-shrink: 0; /* UPDATED: Prevent shrinking */
        }
        
        /* UPDATED: SCROLLABLE TABLE AREA */
        .table-scroll-area {
            flex-grow: 1;
            overflow-y: auto;
            position: relative;
        }

        /* TABLES */
        .table-custom {
            width: 100%;
            font-size: 0.9rem;
            border-collapse: collapse; /* UPDATED */
        }
        
        .table-custom th {
            background: var(--espresso);
            color: white;
            padding: 12px;
            font-size: 0.85rem;
            letter-spacing: 1px;
            position: sticky; /* UPDATED */
            top: 0;
            z-index: 10;
        }
        
        .table-custom td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table-custom tbody tr:hover {
            background: rgba(201,169,97,0.15);
        }
        
        /* UPDATED: TOTAL FOOTER FIXED TO BOTTOM */
        .total-display {
            background: var(--espresso);
            color: var(--gold);
            padding: 20px;
            text-align: center;
            border-top: 3px solid var(--gold);
            flex-shrink: 0;
        }
        
        /* ADMIN TABS */
        .nav-tabs {
            border: none;
            margin-bottom: 20px;
            gap: 8px;
        }
        
        .nav-tabs .nav-link {
            background: transparent;
            border: 2px solid #ddd;
            color: var(--espresso);
            padding: 10px 20px;
            font-size: 0.95rem;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--espresso);
            color: white;
            border-color: var(--espresso);
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .form-control-sm {
            padding: 10px;
            font-size: 0.9rem;
            border: 2px solid #ddd;
            border-radius: 6px;
        }
        
        .btn-sm {
            padding: 10px;
            font-size: 0.9rem;
            margin-top: 10px;
            font-weight: bold;
            border-radius: 6px;
        }
        
        .btn-reset {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: var(--espresso);
            color: white;
            border: 2px solid var(--gold);
            font-weight: bold;
            font-size: 0.95rem;
            border-radius: 8px;
        }
        
        .btn-reset:hover {
            background: var(--gold);
            color: var(--espresso);
        }
        
        .alert {
            margin-bottom: 15px;
            padding: 12px;
            font-size: 0.9rem;
            border-radius: 8px;
        }
        
        /* ABOUT US SECTION */
        .about-section {
            background: var(--espresso);
            color: var(--gold);
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .about-section h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .about-section p {
            font-size: 0.95rem;
            margin-bottom: 8px;
            line-height: 1.6;
        }
        
        .developers {
            margin-top: 15px;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 14px; }
        ::-webkit-scrollbar-track { background: var(--cream); }
        ::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; border: 3px solid var(--cream); }
        ::-webkit-scrollbar-thumb:hover { background: var(--espresso); }

        /* RECEIPT STYLES */
        #receipt-preview {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: #fff;
            border: 1px dashed #ccc;
            color: #000;
        }
        .receipt-header { text-align: center; margin-bottom: 20px; }
        .receipt-details { margin-bottom: 20px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .receipt-divider { border-top: 1px dashed #000; margin: 10px 0; }
        .receipt-footer { text-align: center; margin-top: 20px; font-size: 0.8rem; }
        #qrcode { display: flex; justify-content: center; margin-top: 15px; }
        
        /* TABLE ACTIONS */
        .btn-receipt {
            background: #4CAF50 !important;
            color: white !important;
            border: none !important;
            transition: all 0.2s;
        }
        
        .btn-receipt:hover {
            background: #45a049 !important;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
<div class="container-wrapper">
    <div class="header-bar">
        <h1><i class="fas fa-user-shield"></i> ADMIN DASHBOARD</h1>
        <div class="user-info">
            <div class="username">
                <i class="fas fa-user-cog"></i> <?php echo strtoupper($_SESSION['username']); ?>
            </div>
            <a href="analytics.php" class="btn-analytics">
                <i class="fas fa-chart-line"></i> ANALYTICS
            </a>
            <a href="cashier.php" class="btn-cashier">
                <i class="fas fa-cash-register"></i> CASHIER
            </a>
            <a href="?logout=1" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
            </a>
        </div>
    </div>
    
    <div class="main-layout">
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-tools"></i> PRODUCT MANAGEMENT
            </div>
            
            <div class="panel-padding">
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
                    
                    <div class="tab-pane fade" id="remove">
                        <form method="POST">
                            <input type="text" name="remove_pid" class="form-control form-control-sm mb-3" placeholder="Product ID to Delete" required>
                            <button type="submit" name="remove_drink" class="btn btn-danger btn-sm w-100" onclick="return confirm('Are you sure you want to delete this item?')">
                                <i class="fas fa-trash-alt"></i> Delete Item Permanently
                            </button>
                        </form>
                    </div>
                </div>
                
                <form method="POST" onsubmit="return confirm('WARNING: This will delete all sales history for today. Continue?');">
                    <button type="submit" name="reset_day" class="btn btn-reset">
                        <i class="fas fa-power-off"></i> END DAY / RESET SALES
                    </button>
                </form>
            </div>
            
             <div style="margin-top: auto; padding: 20px; text-align: center; border-top: 1px solid #eee; font-size: 0.8rem; color: #888;">
                <i class="fas fa-code"></i> Developed by: Rayver S. Reyes, Char Mae Grace Bering, Sebastian Rafael Belando
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
                    <thead>
                        <tr>
                            <th style="width: 15%;">TIME</th>
                            <th style="width: 35%;">CUSTOMER</th>
                            <th style="width: 30%;">ITEM</th>
                            <th style="width: 20%; text-align: right;">PRICE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($sales_data) > 0) {
                            mysqli_data_seek($sales_data, 0); 
                            while($row = mysqli_fetch_assoc($sales_data)): 
                        ?>
                        <tr>
                            <td><?php echo date('H:i', strtotime($row['sale_time'])); ?></td>
                            <td><?php echo strtoupper(substr($row['customer_name'], 0, 15)); ?></td>
                            <td><?php echo $row['drink_name']; ?></td>
                            <td style="text-align: right; font-weight: bold;">₱<?php echo number_format($row['price'], 2); ?></td>
                        </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo '<tr><td colspan="4" style="text-align: center; color: #999; padding: 60px;">No sales recorded yet</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="total-display">
                <div style="font-size: 1.8rem; margin-bottom: 10px; font-weight: bold;">
                    TOTAL: ₱<?php echo number_format($grand_total, 2); ?>
                </div>
                
                <div style="font-size: 0.9rem; text-align: left;">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="color: var(--gold);">Daily Goal: <?php echo number_format($progress_pct, 0); ?>%</span>
                        <span style="color: var(--gold);">Target: ₱<?php echo number_format($daily_target); ?></span>
                    </div>
                    <div class="progress" style="height: 10px; background-color: rgba(201,169,97,0.3);">
                        <div class="progress-bar bg-<?php echo $progress_color; ?>" role="progressbar" 
                             style="width: <?php echo min($progress_pct, 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt"></i> Transaction Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receipt-preview">
                    <div class="receipt-header">
                        <h4>CRS CAFE MASTER</h4>
                        <p>123 Brew Street, Java City</p>
                        <p>Tel: (555) 123-4567</p>
                    </div>
                    <div class="receipt-divider"></div>
                    <div class="receipt-details">
                        <div class="receipt-row"><span>Date:</span> <span id="r-date"></span></div>
                        <div class="receipt-row"><span>Customer:</span> <span id="r-customer"></span></div>
                    </div>
                    <div class="receipt-divider"></div>
                    <div class="receipt-details">
                        <div class="receipt-row">
                            <span id="r-item"></span>
                            <span id="r-price"></span>
                        </div>
                    </div>
                    <div class="receipt-divider"></div>
                    <div class="receipt-details">
                        <div class="receipt-row">
                            <span>Amount Tendered:</span>
                            <span id="r-tendered"></span>
                        </div>
                        <div class="receipt-row">
                            <span>Change:</span>
                            <span id="r-change"></span>
                        </div>
                    </div>
                    <div class="receipt-divider"></div>
                    <div class="receipt-row" style="font-weight:bold; font-size: 1.2rem;">
                        <span>TOTAL</span>
                        <span id="r-total"></span>
                    </div>
                    <div class="receipt-footer">
                        <p>Thank you for your business!</p>
                        <div id="qrcode"></div>
                    </div>
                </div>
                
                <form method="POST" class="mt-3 border-top pt-3">
                    <label class="form-label"><i class="fas fa-envelope"></i> Email Receipt:</label>
                    <div class="input-group">
                        <input type="email" name="email_to" class="form-control" placeholder="customer@email.com" required>
                        <input type="hidden" name="receipt_text" id="hidden_receipt_text">
                        <button type="submit" name="send_email" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-success" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle Receipt Modal
    document.addEventListener('DOMContentLoaded', function() {
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        
        // Add click event to all receipt buttons
        document.querySelectorAll('.btn-receipt').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('View button clicked'); // Debug log
                
                const customer = this.getAttribute('data-customer');
                const item = this.getAttribute('data-item');
                const price = this.getAttribute('data-price');
                const time = this.getAttribute('data-time');
                const tendered = this.getAttribute('data-tendered');
                const change = this.getAttribute('data-change');
                
                console.log('Data:', {customer, item, price, time, tendered, change}); // Debug log
                
                // Update receipt content
                document.getElementById('r-date').textContent = time;
                document.getElementById('r-customer').textContent = customer;
                document.getElementById('r-item').textContent = item;
                document.getElementById('r-price').textContent = '₱' + price;
                document.getElementById('r-tendered').textContent = '₱' + tendered;
                document.getElementById('r-change').textContent = '₱' + change;
                document.getElementById('r-total').textContent = '₱' + price;
                
                // Create Receipt Text for QR Code
                // We define it here so it's available for the QR code generation
                const receiptText = `RECEIPT\nDate: ${time}\nCustomer: ${customer}\nItem: ${item}\nTotal: ₱${price}`;

                // Generate QR Code
                const qrcodeContainer = document.getElementById('qrcode');
                qrcodeContainer.innerHTML = ''; // Clear previous QR code
                new QRCode(qrcodeContainer, {
                    text: receiptText, 
                    width: 80,
                    height: 80
                });
                
                // Show modal
                receiptModal.show();
            });
        });
    });

    function printReceipt() {
        const content = document.getElementById('receipt-preview').innerHTML;
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Receipt</title>');
        printWindow.document.write('<style>body{font-family: monospace; padding: 20px;} .receipt-row{display:flex; justify-content:space-between;} .receipt-divider{border-top:1px dashed #000; margin:10px 0;} .receipt-header{text-align:center;} #qrcode{display:flex; justify-content:center; margin-top:20px;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }

    function downloadPDF() {
        const element = document.getElementById('receipt-preview');
        const opt = {
            margin: 1,
            filename: 'receipt_' + new Date().getTime() + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
</body>
</html>