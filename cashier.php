<?php
session_start();

// login sessions checking
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'cashier' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

// database conn
$DBHost = "localhost";
$DBUser = "root";
$DBPass = "";
$DBName = "cafe_db";
$conn = mysqli_connect($DBHost, $DBUser, $DBPass, $DBName);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$message = "";
$msgType = "";
$receipt_data = null;

// log out function
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// process order
if (isset($_POST['process_order'])) {
    $customer = $_POST['customer_name'];
    $pid = $_POST['product_id'];
    $amount_given = floatval($_POST['amount_given']);
    
    // search name of drink
    $menu_check = mysqli_query($conn, "SELECT * FROM menu_tbl WHERE product_id = '$pid'");
    if (mysqli_num_rows($menu_check) > 0) {
        $menu_item = mysqli_fetch_assoc($menu_check);
        $d_name = $menu_item['drink_name'];
        $d_price = $menu_item['price'];
        
        if ($amount_given < $d_price) {
            $message = "Insufficient Payment! Price is ₱" . number_format($d_price, 2);
            $msgType = "danger";
        } else {
            $change = $amount_given - $d_price;
            
            // Add to Daily Sales with Payment Info
            $insert = "INSERT INTO daily_sales (customer_name, product_id, drink_name, price, amount_tendered, change_amount) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert);
            mysqli_stmt_bind_param($stmt, "sssddd", $customer, $pid, $d_name, $d_price, $amount_given, $change);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "SOLD: $d_name to $customer - ₱" . number_format($d_price, 2);
                $msgType = "success";
                
                // Prepare data for receipt Modal
                $receipt_data = [
                    'customer' => $customer,
                    'item' => $d_name,
                    'price' => $d_price,
                    'tendered' => $amount_given,
                    'change' => $change,
                    'date' => date('Y-m-d H:i:s')
                ];
            }
        }
    } else {
        $message = "Error: Product ID '$pid' not found!";
        $msgType = "danger";
    }
}

// fetch of data
$menu_data = mysqli_query($conn, "SELECT * FROM menu_tbl ORDER BY product_id ASC");

// suggestion of best seller
$best_q = mysqli_query($conn, "SELECT product_id FROM daily_sales GROUP BY product_id ORDER BY COUNT(*) DESC LIMIT 1");
$best_seller_id = (mysqli_num_rows($best_q) > 0) ? mysqli_fetch_assoc($best_q)['product_id'] : null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cashier - CRS Cafe System</title>
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
        }
        
        .user-info .username {
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        
        .btn-logout {
            background: var(--gold);
            color: var(--espresso);
            border: none;
            padding: 8px 20px;
            font-size: 0.9rem;
            font-weight: bold;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: #d4b56d;
            transform: scale(1.05);
        }
        
        /* TWO COLUMN LAYOUT */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        /* LARGE PANELS */
        .panel {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        
        .panel-dark {
            background: var(--espresso);
            color: var(--gold);
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
        }
        
        /* POS FORM */
        .pos-form label {
            display: block;
            margin-bottom: 8px;
            margin-top: 15px;
            font-weight: bold;
            font-size: 0.95rem;
            letter-spacing: 1px;
        }
        
        .pos-form .form-control {
            background: rgba(255,255,255,0.1);
            border: 2px solid var(--gold);
            color: white;
            padding: 12px;
            font-size: 1rem;
            border-radius: 8px;
        }
        
        .pos-form .form-control::placeholder {
            color: rgba(201,169,97,0.5);
        }
        
        .btn-charge {
            background: var(--gold);
            color: var(--espresso);
            font-weight: bold;
            width: 100%;
            font-size: 1.2rem;
            border: none;
            padding: 15px;
            margin-top: 20px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-charge:hover {
            background: #d4b56d;
            transform: scale(1.02);
        }
        
        /* SEARCH BAR */
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid var(--gold);
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: 1.1rem;
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
        
        .alert {
            margin-bottom: 15px;
            padding: 12px;
            font-size: 0.9rem;
            border-radius: 8px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1rem;
        }
        
        /* COFFEE FACTS SECTION */
        .coffee-facts-section {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .coffee-facts-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            align-items: center;
        }
        
        .facts-text h2 {
            color: var(--espresso);
            font-size: 1.3rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .facts-text p {
            color: var(--espresso);
            font-size: 1rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .image-placeholder {
            background: linear-gradient(135deg, var(--cream) 0%, var(--ivory) 100%);
            border: 2px dashed var(--gold);
            border-radius: 10px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 0.9rem;
        }
        
        .image-placeholder i {
            font-size: 2.5rem;
            margin-bottom: 8px;
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
    </style>
</head>
<body>
<div class="container-wrapper">
    <!-- header-->
    <div class="header-bar">
        <h1><i class="fas fa-cash-register"></i> CASHIER DASHBOARD</h1>
        <div class="user-info">
            <div id="liveClock" style="font-weight: bold; color: var(--gold); margin-bottom: 5px; font-size: 1.1rem;"></div>
            <div class="username">
                <i class="fas fa-user"></i> <?php echo strtoupper($_SESSION['username']); ?>
            </div>
            <a href="?logout=1" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
            </a>
        </div>
    </div>
    
    <!-- columns layout -->
    <div class="main-layout">
        <!-- LEFT: POS ORDER ENTRY -->
        <div class="panel panel-dark">
            <div class="panel-header">
                <i class="fas fa-shopping-cart"></i> ORDER ENTRY
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off" class="pos-form">
                <label>CUSTOMER NAME</label>
                <input type="text" name="customer_name" class="form-control" required autofocus>
                
                <label>DRINK ID</label>
                <input type="text" name="product_id" class="form-control" placeholder="See menu list on the right" required>
                
                <label>AMOUNT TENDERED (₱)</label>
                <input type="number" step="0.01" name="amount_given" class="form-control" placeholder="e.g. 200.00" required>
                
                <button type="submit" name="process_order" class="btn btn-charge">
                    <i class="fas fa-coins"></i> CHARGE
                </button>
            </form>
        </div>
        
        <!-- RIGHT: MENU LIST -->
        <div class="panel">
            <div class="panel-header" style="color: var(--espresso);">
                <i class="fas fa-book"></i> MENU CATALOG
            </div>
            
            <div class="search-box">
                <input type="text" id="menuSearch" placeholder="Search drinks by name or ID..." autocomplete="off">
                <i class="fas fa-search"></i>
            </div>
            
            <table class="table-custom" id="menuTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>DRINK NAME</th>
                        <th>TYPE</th>
                        <th>PRICE</th>
                    </tr>
                </thead>
                <tbody id="menuTableBody">
                    <?php while($m = mysqli_fetch_assoc($menu_data)): ?>
                    <tr>
                        <td><strong><?php echo $m['product_id']; ?></strong></td>
                        <td>
                            <?php echo $m['drink_name']; ?>
                            <?php if ($best_seller_id && $m['product_id'] == $best_seller_id): ?>
                                <span class="badge bg-warning text-dark ms-2"><i class="fas fa-crown"></i> BEST SELLER</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $m['type']; ?></td>
                        <td>₱<?php echo number_format($m['price'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div id="noResults" class="no-results" style="display: none;">
                <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
                <p>No drinks found matching your search</p>
            </div>
        </div>
    </div>
    
    <!-- coffee facts section -->
    <div class="coffee-facts-section">
        <div class="coffee-facts-content">
            <div class="facts-text">
                <h2><i class="fas fa-lightbulb"></i> DID YOU KNOW?</h2>
                <p id="coffeeFact">Coffee is the second most traded commodity in the world after oil!</p>
            </div>
            <div class="facts-image">
                <div class="image-placeholder">
                    <i class="fas fa-image"></i>
                    <span>Coffee Image</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ABOUT US SECTION -->
    <div class="about-section">
        <h2><i class="fas fa-users"></i> ABOUT US</h2>
        <p>This CRS Cafe Master System was crafted with passion and dedication</p>
        <p>to streamline coffee shop operations and enhance customer experience.</p>
        <div class="developers">
            <i class="fas fa-code"></i> DEVELOPED BY:<br>
                  Rayver S. Reyes - full stack developer / project lead
            <br> Char Mae Grace Bering - backend developer & database handler 
            <br>Sebastian Rafael Belando - backend developer
        </div>
    </div>
</div>




<!-- TRANSACTION RECEIPT MODAL -->
<div class="modal fade" id="transactionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> TRANSACTION SUCCESSFUL</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <?php if ($receipt_data): ?>
                <h3 class="mb-3">₱<?php echo number_format($receipt_data['change'], 2); ?></h3>
                <p class="text-muted">CHANGE DUE</p>
                
                <hr class="my-4">
                
                <div class="row mb-2">
                    <div class="col-6 text-start text-muted">Customer:</div>
                    <div class="col-6 text-end fw-bold"><?php echo strtoupper($receipt_data['customer']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-start text-muted">Item:</div>
                    <div class="col-6 text-end fw-bold"><?php echo $receipt_data['item']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-start text-muted">Price:</div>
                    <div class="col-6 text-end">₱<?php echo number_format($receipt_data['price'], 2); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-start text-muted">Cash Given:</div>
                    <div class="col-6 text-end">₱<?php echo number_format($receipt_data['tendered'], 2); ?></div>
                </div>
                
                <div class="mt-4 d-flex justify-content-center">
                    <?php 
                    // 1. Detect the current server URL
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    if ($host === 'localhost' || $host === '127.0.0.1') {
                        $ip = gethostbyname(gethostname());
                        if ($ip !== '127.0.0.1' && $ip !== 'localhost') {
                            $host = $ip;
                        } else {
                            $out = [];
                            @exec('ipconfig', $out);
                            foreach($out as $line) {
                                if(preg_match('/IPv4 Address.*: (192\.168\.\d+\.\d+)/', $line, $matches)) {
                                    $host = $matches[1];
                                    break;
                                }
                            }
                        }
                    }
                    
                    $path = dirname($_SERVER['PHP_SELF']);
                    $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
                    $base_url = $protocol . "://" . $host . $path . "receipt_view.php";
                    $params = [
                        'date' => $receipt_data['date'],
                        'customer' => $receipt_data['customer'],
                        'item' => $receipt_data['item'],
                        'price' => $receipt_data['price'],
                        'tendered' => $receipt_data['tendered'],
                        'change' => $receipt_data['change']
                    ];
                    $full_url = $base_url . "?" . http_build_query($params);
                    
                    // 3. Generate QR Code pointing to that URL
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($full_url);
                    ?>
                    <img src="<?php echo $qr_url; ?>" alt="Receipt QR Code" style="border: 2px solid #eee; padding: 5px;">
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// COFFEE FACTS ROTATION
const coffeeFacts = [
    "Coffee is the second most traded commodity in the world after oil!",
    "The world consumes over 2.25 billion cups of coffee every single day.",
    "Coffee beans are actually seeds found inside coffee cherries.",
    "Espresso means 'pressed out' in Italian, referring to how it's made.",
    "The most expensive coffee in the world can cost up to $600 per pound!",
    "Coffee was discovered by goats in Ethiopia around 800 AD.",
    "Dark roast coffee has LESS caffeine than light roast.",
    "Coffee stays warm 20% longer when you add cream to it."
];

let factIndex = 0;
function rotateFact() {
    factIndex = (factIndex + 1) % coffeeFacts.length;
    document.getElementById('coffeeFact').textContent = coffeeFacts[factIndex];
}
setInterval(rotateFact, 8000);

// LIVE CLOCK
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateString = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    document.getElementById('liveClock').innerHTML = `<i class="far fa-clock"></i> ${dateString} - ${timeString}`;
}
setInterval(updateClock, 1000);
updateClock(); // Run immediately

// SEARCH FUNCTIONALITY
document.getElementById('menuSearch').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const tableBody = document.getElementById('menuTableBody');
    const rows = tableBody.getElementsByTagName('tr');
    const noResults = document.getElementById('noResults');
    const menuTable = document.getElementById('menuTable');
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        const productId = cells[0].textContent.toLowerCase();
        const drinkName = cells[1].textContent.toLowerCase();
        
        if (productId.includes(searchTerm) || drinkName.includes(searchTerm)) {
            rows[i].style.display = '';
            visibleCount++;
        } else {
            rows[i].style.display = 'none';
        }
    }
    
    if (visibleCount === 0 && searchTerm !== '') {
        noResults.style.display = 'block';
        menuTable.style.display = 'none';
    } else {
        noResults.style.display = 'none';
        menuTable.style.display = 'table';
    }
});

// SHOW RECEIPT MODAL IF DATA EXISTS
<?php if ($receipt_data): ?>
    const modalElement = document.getElementById('transactionModal');
    const transactionModal = new bootstrap.Modal(modalElement);
    transactionModal.show();
<?php endif; ?>
</script>
</body>
</html>