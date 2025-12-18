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

// --- SEND RECEIPT EMAIL ---
if (isset($_POST['send_email'])) {
    $to = $_POST['email_to'];
    $subject = "Your Coffee Shop Receipt";
    $body = "Thank you for your purchase!\n\n" . $_POST['receipt_text'];
    $headers = "From: no-reply@coffeeshop.com";
    
    // Note: mail() requires a configured mail server (e.g., Mercury/Sendmail in XAMPP)
    $sent = @mail($to, $subject, $body, $headers); 
    $message = "Receipt sent to $to"; 
    $msgType = "success";
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
            overflow-y: auto;
            padding: 20px;
        }
        
        .container-wrapper {
            max-width: 1600px;
            margin: 0 auto;
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
        
        .btn-logout, .btn-analytics {
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
        
        /* TWO COLUMN LAYOUT */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        /* PANELS */
        .panel {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        
        .panel-header {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid currentColor;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--espresso);
        }
        
        /* TABLES */
        .table-custom {
            width: 100%;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .table-custom th {
            background: var(--espresso);
            color: white;
            padding: 12px;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        
        .table-custom td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table-custom tbody tr:hover {
            background: rgba(201,169,97,0.15);
        }
        
        /* TOTAL DISPLAY */
        .total-display {
            background: var(--espresso);
            color: var(--gold);
            padding: 18px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            border-radius: 10px;
            border: 3px solid var(--gold);
            margin-top: 15px;
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
            <a href="analytics.php" class="btn-analytics">
                <i class="fas fa-chart-line"></i> ANALYTICS
            </a>
            <a href="?logout=1" class="btn-logout">
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
                        <th>ACTION</th>
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
                        <td>
                            <button class="btn btn-sm btn-outline-dark btn-receipt" 
                                data-customer="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                data-item="<?php echo htmlspecialchars($row['drink_name']); ?>"
                                data-price="<?php echo number_format($row['price'], 2); ?>"
                                data-time="<?php echo date('Y-m-d H:i', strtotime($row['sale_time'])); ?>">
                                <i class="fas fa-print"></i>
                            </button>
                        </td>
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

<!-- RECEIPT MODAL -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt"></i> Receipt Options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Receipt Preview Area -->
                <div id="receipt-preview">
                    <div class="receipt-header">
                        <h4>COFFEE SHOP MASTER</h4>
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
                    <div class="receipt-row" style="font-weight:bold;">
                        <span>TOTAL</span>
                        <span id="r-total"></span>
                    </div>
                    <div class="receipt-footer">
                        <p>Thank you for your business!</p>
                        <div id="qrcode"></div>
                    </div>
                </div>
                
                <!-- Email Form -->
                <form method="POST" class="mt-3 border-top pt-3">
                    <label class="form-label">Email Receipt:</label>
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
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle Receipt Modal
    const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    
    document.querySelectorAll('.btn-receipt').forEach(btn => {
        btn.addEventListener('click', function() {
            const customer = this.dataset.customer;
            const item = this.dataset.item;
            const price = this.dataset.price;
            const time = this.dataset.time;
            
            document.getElementById('r-date').textContent = time;
            document.getElementById('r-customer').textContent = customer;
            document.getElementById('r-item').textContent = item;
            document.getElementById('r-price').textContent = '$' + price;
            document.getElementById('r-total').textContent = '$' + price;
            
            // Prepare text for email
            const receiptText = `Date: ${time}\nCustomer: ${customer}\nItem: ${item}\nPrice: $${price}\nTotal: $${price}`;
            document.getElementById('hidden_receipt_text').value = receiptText;
            
            // Generate QR Code
            document.getElementById('qrcode').innerHTML = '';
            new QRCode(document.getElementById('qrcode'), {
                text: receiptText,
                width: 80,
                height: 80
            });
            
            receiptModal.show();
        });
    });

    function printReceipt() {
        const content = document.getElementById('receipt-preview').innerHTML;
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Receipt</title>');
        printWindow.document.write('<style>body{font-family: monospace; padding: 20px;} .receipt-row{display:flex; justify-content:space-between;} .receipt-divider{border-top:1px dashed #000; margin:10px 0;} #qrcode{display:flex; justify-content:center; margin-top:20px;}</style>');
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
            filename: 'receipt.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
</body>
</html>