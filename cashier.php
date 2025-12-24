<?php
session_start();

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'cashier' && $_SESSION['role'] !== 'admin')) {
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

// --- INITIALIZE VARIABLES ---
$message = "";
$msgType = "";
$receipt_data = null;

// Initialize Cart Session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- ACTION: ADD ITEM TO CART ---
if (isset($_POST['add_to_cart'])) {
    $pid = $_POST['product_id'];
    $qty = intval($_POST['quantity']);
    if ($qty < 1) $qty = 1;

    // Fetch product details
    $query = mysqli_query($conn, "SELECT * FROM menu_tbl WHERE product_id = '$pid'");
    if (mysqli_num_rows($query) > 0) {
        $item = mysqli_fetch_assoc($query);
        
        // Check if item already exists in cart, if so, update quantity
        $found = false;
        foreach ($_SESSION['cart'] as &$cart_item) {
            if ($cart_item['id'] === $pid) {
                $cart_item['qty'] += $qty;
                $cart_item['subtotal'] = $cart_item['qty'] * $cart_item['price'];
                $found = true;
                break;
            }
        }
        
        // If new item, add to array
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $item['product_id'],
                'name' => $item['drink_name'],
                'type' => $item['type'],
                'price' => $item['price'],
                'qty' => $qty,
                'subtotal' => $item['price'] * $qty
            ];
        }
    } else {
        $message = "Product ID not found!";
        $msgType = "danger";
    }
}

// --- ACTION: REMOVE ITEM FROM CART ---
if (isset($_POST['remove_index'])) {
    $index = $_POST['remove_index'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
    }
}

// --- ACTION: CLEAR CART ---
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
}

// --- ACTION: PROCESS PAYMENT (CHECKOUT) ---
if (isset($_POST['process_payment'])) {
    if (empty($_SESSION['cart'])) {
        $message = "Cart is empty!";
        $msgType = "danger";
    } else {
        $customer = $_POST['customer_name'];
        $amount_given = floatval($_POST['amount_given']);
        $cashier_id = $_SESSION['user_id'];
        
        // Calculate Grand Total
        $grand_total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $grand_total += $item['subtotal'];
        }
        
        if ($amount_given < $grand_total) {
            $message = "Insufficient Payment! Need ₱" . number_format($grand_total, 2);
            $msgType = "danger";
        } else {
            $change = $amount_given - $grand_total;
            $sale_date = date('Y-m-d H:i:s');
            
            $stmt = mysqli_prepare($conn, "INSERT INTO daily_sales (customer_name, product_id, drink_name, price, amount_tendered, change_amount, cashier_id, sale_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $transaction_items = []; 
            
            foreach ($_SESSION['cart'] as $item) {
                $db_name = ($item['qty'] > 1) ? $item['name'] . " (x" . $item['qty'] . ")" : $item['name'];
                $total_item_price = $item['subtotal']; 
                
                mysqli_stmt_bind_param($stmt, "sssdddis", $customer, $item['id'], $db_name, $total_item_price, $amount_given, $change, $cashier_id, $sale_date);
                mysqli_stmt_execute($stmt);
                
                $transaction_items[] = [
                    'name' => $item['name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal']
                ];
            }
            
            $receipt_data = [
                'customer' => $customer,
                'items' => $transaction_items,
                'total' => $grand_total,
                'tendered' => $amount_given,
                'change' => $change,
                'date' => $sale_date
            ];
            
            $_SESSION['cart'] = [];
            $message = "Transaction Complete! Change: ₱" . number_format($change, 2);
            $msgType = "success";
        }
    }
}

$menu_data = mysqli_query($conn, "SELECT * FROM menu_tbl ORDER BY product_id ASC");

$cart_total = 0;
foreach ($_SESSION['cart'] as $c) { $cart_total += $c['subtotal']; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cashier - CSR Cafe System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root { --espresso: #1A0F0A; --gold: #C9A961; --cream: #F5F5F0; --ivory: #FDFBF7; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%); font-family: 'Courier New', monospace; min-height: 100vh; overflow-y: auto; padding: 20px; }
        #bg-video { position: fixed; right: 0; bottom: 0; min-width: 100%; min-height: 100%; z-index: -1; object-fit: cover; filter: brightness(0.5); }
        .container-wrapper { max-width: 1600px; margin: 0 auto; }
        
        /* HEADER */
        .header-bar { background: rgba(26, 15, 10, 0.9); color: var(--gold); padding: 20px 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(5px); }
        .header-bar h1 { margin: 0; font-size: 1.8rem; display: flex; align-items: center; gap: 15px; }
        .user-info { text-align: right; }
        .btn-logout { background: var(--gold); color: var(--espresso); border: none; padding: 8px 20px; font-weight: bold; border-radius: 6px; transition: 0.3s; }
        .btn-logout:hover { background: #d4b56d; transform: scale(1.05); }
        .btn-music { background: transparent; border: 2px solid var(--gold); color: var(--gold); padding: 6px 12px; border-radius: 6px; margin-right: 8px; cursor: pointer; }
        
        /* PANELS */
        .main-layout { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; margin-bottom: 20px; }
        
        /* Compact Panel */
        .panel { 
            background: rgba(255, 255, 255, 0.92); 
            border: 3px solid var(--gold); 
            border-radius: 12px; 
            padding: 20px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.2); 
            backdrop-filter: blur(5px); 
            display: flex; 
            flex-direction: column; 
        }
        
        .panel-dark { background: rgba(26, 15, 10, 0.95); color: var(--gold); }
        .panel-header { font-size: 1.4rem; font-weight: bold; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 3px solid currentColor; display: flex; align-items: center; gap: 10px; }
        
        /* LEFT PANEL: CART & INPUTS */
        .pos-input-group { display: flex; gap: 10px; margin-bottom: 15px; }
        .pos-form .form-control { background: rgba(255,255,255,0.1); border: 2px solid var(--gold); color: white; padding: 10px; font-weight: bold; }
        .pos-form .form-control::placeholder { color: rgba(201,169,97,0.5); }
        
        .qty-wrapper { display: flex; border: 2px solid var(--gold); border-radius: 6px; overflow: hidden; width: 120px; }
        .btn-qty { background: rgba(255,255,255,0.1); border: none; color: var(--gold); width: 35px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-qty:hover { background: var(--gold); color: var(--espresso); }
        .input-qty { width: 50px; background: transparent; border: none; color: white; text-align: center; font-weight: bold; -moz-appearance: textfield; }
        .input-qty::-webkit-outer-spin-button, .input-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        
        .btn-add { background: var(--gold); color: var(--espresso); border: none; font-weight: bold; padding: 0 20px; border-radius: 6px; flex-grow: 1; }
        .btn-add:hover { background: #d4b56d; }

        /* CART AREA */
        .cart-area { 
            flex-grow: 0; 
            background: rgba(255,255,255,0.05); 
            border-radius: 8px; 
            margin-bottom: 15px; 
            overflow-y: auto; 
            height: 220px; 
            border: 1px solid var(--gold); 
        }
        
        .table-cart { width: 100%; color: white; font-size: 0.9rem; border-collapse: collapse; }
        .table-cart th { position: sticky; top: 0; background: var(--gold); color: var(--espresso); padding: 8px; text-align: left; }
        .table-cart td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .btn-remove { color: #ff6b6b; background: none; border: none; cursor: pointer; }
        .btn-remove:hover { color: red; }

        /* CHECKOUT AREA */
        .checkout-area { border-top: 2px solid var(--gold); padding-top: 15px; }
        .total-display { font-size: 1.5rem; font-weight: bold; text-align: right; margin-bottom: 10px; color: var(--gold); }
        .btn-pay { background: #28a745; color: white; width: 100%; padding: 15px; font-size: 1.2rem; font-weight: bold; border: none; border-radius: 8px; }
        .btn-pay:hover { background: #218838; }
        
        /* RIGHT PANEL: MENU */
        .menu-scroll-area { 
            flex-grow: 1; 
            overflow-y: auto; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            background: rgba(255,255,255,0.5); 
            max-height: 500px; 
        }
        
        .table-menu { width: 100%; font-size: 0.9rem; border-collapse: collapse; }
        .table-menu th { background: var(--espresso); color: white; padding: 10px; position: sticky; top: 0; }
        .table-menu td { padding: 10px; border-bottom: 1px solid #ddd; }
        .search-box input { width: 100%; padding: 10px; border: 2px solid var(--gold); border-radius: 6px; margin-bottom: 10px; }

        /* --- UPDATED COMPACT FACTS SECTION --- */
        .coffee-facts-section { 
            background: rgba(255, 255, 255, 0.92); 
            border: 3px solid var(--gold); 
            border-radius: 12px; 
            padding: 10px 20px; /* Reduced padding */
            margin-top: 20px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(5px);
        }
        .coffee-facts-content { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            gap: 20px; 
        }
        .facts-text { flex: 1; }
        .facts-text h2 { color: var(--espresso); font-size: 1.1rem; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .facts-text p { color: var(--espresso); font-size: 0.9rem; margin: 0; line-height: 1.4; }
        
        .facts-image { text-align: right; }
        .facts-logo {
            height: 70px; /* Smaller Logo */
            width: auto;
            border: 2px solid var(--gold);
            border-radius: 50%;
            padding: 3px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .about-section { background: rgba(26, 15, 10, 0.95); color: var(--gold); border: 3px solid var(--gold); border-radius: 12px; padding: 15px; margin-top: 20px; text-align: center; }
        .about-section h2 { font-size: 1.3rem; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .about-section p { font-size: 0.85rem; margin-bottom: 5px; }
        .developers { margin-top: 10px; font-size: 0.9rem; font-weight: bold; }
        
        /* MODAL */
        .receipt-list { font-family: 'Courier New', monospace; font-size: 0.9rem; width: 100%; margin: 15px 0; }
        .receipt-list td { padding: 4px 0; }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video"><source src="newbgvideo.mp4" type="video/mp4"></video>
    <audio id="bg-music" loop autoplay><source src="bgmcafe.mp3" type="audio/mpeg"></audio>

<div class="container-wrapper">
    <div class="header-bar">
        <h1><i class="fas fa-cash-register"></i> CASHIER DASHBOARD</h1>
        <div class="user-info">
            <div id="liveClock" style="font-weight: bold; margin-bottom: 5px;"></div>
            <div style="font-size: 0.9rem;">
                <i class="fas fa-user"></i> <?php echo strtoupper($_SESSION['username']); ?>
            </div>
            <div style="margin-top: 5px;">
                <button id="musicToggle" class="btn-music"><i class="fas fa-volume-up"></i></button>
                <a href="?logout=1" class="btn btn-logout">LOGOUT</a>
            </div>
        </div>
    </div>
    
    <div class="main-layout">
        <div class="panel panel-dark">
            <div class="panel-header"><i class="fas fa-shopping-cart"></i> CURRENT ORDER</div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?> p-2 mb-3"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <div class="pos-input-group">
                    <input type="text" name="product_id" class="form-control" placeholder="Enter Product ID" required autofocus style="flex: 2;">
                    
                    <div class="qty-wrapper">
                        <button type="button" class="btn-qty" onclick="adjustQty(-1)">-</button>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" class="input-qty" required>
                        <button type="button" class="btn-qty" onclick="adjustQty(1)">+</button>
                    </div>
                    
                    <button type="submit" name="add_to_cart" class="btn-add"><i class="fas fa-plus"></i> ADD</button>
                </div>
            </form>
            
            <div class="cart-area">
                <table class="table-cart">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th style="width: 50px;">Qty</th>
                            <th style="width: 80px; text-align: right;">Price</th>
                            <th style="width: 80px; text-align: right;">Total</th>
                            <th style="width: 30px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($_SESSION['cart'])): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px; color:#888;">Order list is empty</td></tr>
                        <?php else: ?>
                            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td style="text-align: center;"><?php echo $item['qty']; ?></td>
                                <td style="text-align: right;">₱<?php echo number_format($item['price'], 2); ?></td>
                                <td style="text-align: right;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                <td style="text-align: center;">
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="remove_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="btn-remove"><i class="fas fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="checkout-area">
                <div class="total-display">TOTAL: ₱<?php echo number_format($cart_total, 2); ?></div>
                
                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label>CUSTOMER NAME</label>
                        <input type="text" name="customer_name" class="form-control" required placeholder="Guest">
                    </div>
                    <div class="mb-3">
                        <label>AMOUNT TENDERED</label>
                        <input type="number" step="0.01" name="amount_given" class="form-control" required placeholder="e.g. 500.00">
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="clear_cart" class="btn btn-secondary w-25" formnovalidate>CLEAR</button>
                        <button type="submit" name="process_payment" class="btn btn-pay w-75"><i class="fas fa-money-bill-wave"></i> PAY & PRINT</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-header" style="color: var(--espresso);"><i class="fas fa-book"></i> MENU</div>
            <div class="search-box">
                <input type="text" id="menuSearch" placeholder="Search ID or Name...">
            </div>
            <div class="menu-scroll-area">
                <table class="table-menu" id="menuTable">
                    <thead><tr><th>ID</th><th>NAME</th><th>TYPE</th><th>PRICE</th></tr></thead>
                    <tbody id="menuTableBody">
                        <?php while($m = mysqli_fetch_assoc($menu_data)): ?>
                        <tr>
                            <td><strong><?php echo $m['product_id']; ?></strong></td>
                            <td><?php echo $m['drink_name']; ?></td>
                            <td><?php echo $m['type']; ?></td>
                            <td>₱<?php echo number_format($m['price'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="coffee-facts-section">
        <div class="coffee-facts-content">
            <div class="facts-text">
                <h2><i class="fas fa-lightbulb"></i> DID YOU KNOW?</h2>
                <p id="coffeeFact">Coffee is the second most traded commodity in the world after oil!</p>
            </div>
            <div class="facts-image"><img src="logo.jpg" alt="Logo" class="facts-logo"></div>
        </div>
    </div>
    
    <div class="about-section">
        <h2><i class="fas fa-users"></i> ABOUT US</h2>
        <p>CSR Cafe Master System - Streamlined Operations</p>
        <div class="developers">Developed By: Rayver, Char Mae, Sebastian</div>
    </div>
</div>

<div class="modal fade" id="transactionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> PAYMENT SUCCESSFUL</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <?php if ($receipt_data): ?>
                <h2 class="mb-1">₱<?php echo number_format($receipt_data['change'], 2); ?></h2>
                <p class="text-muted mb-4">CHANGE DUE</p>
                
                <div style="border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 10px 0;">
                    <table class="receipt-list">
                        <?php foreach($receipt_data['items'] as $itm): ?>
                        <tr>
                            <td class="text-start"><?php echo $itm['name']; ?> <small>x<?php echo $itm['qty']; ?></small></td>
                            <td class="text-end">₱<?php echo number_format($itm['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between mt-3 fw-bold">
                    <span>TOTAL</span>
                    <span>₱<?php echo number_format($receipt_data['total'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between text-muted">
                    <span>CASH</span>
                    <span>₱<?php echo number_format($receipt_data['tendered'], 2); ?></span>
                </div>
                
                <div class="mt-4">
                    <?php 
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $path = dirname($_SERVER['PHP_SELF']);
                    $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
                    
                    $params = [
                        'date' => $receipt_data['date'],
                        'customer' => $receipt_data['customer'],
                        'price' => $receipt_data['total'],
                        'tendered' => $receipt_data['tendered'],
                        'change' => $receipt_data['change'],
                        'item' => 'Multiple Items' 
                    ];
                    $full_url = $protocol . "://" . $host . $path . "receipt_view.php?" . http_build_query($params);
                    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($full_url);
                    ?>
                    <img src="<?php echo $qr_url; ?>" alt="Receipt QR" style="border: 2px solid #eee; padding: 5px;">
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
function adjustQty(val) {
    const qtyInput = document.getElementById('quantity');
    let currentQty = parseInt(qtyInput.value) || 1;
    let newQty = currentQty + val;
    if (newQty < 1) newQty = 1;
    qtyInput.value = newQty;
}

document.addEventListener('DOMContentLoaded', function() {
    const bgMusic = document.getElementById('bg-music');
    const musicBtn = document.getElementById('musicToggle');
    const icon = musicBtn.querySelector('i');
    
    bgMusic.volume = 0.3;
    musicBtn.addEventListener('click', () => {
        if (bgMusic.paused) {
            bgMusic.play();
            icon.classList.replace('fa-volume-mute', 'fa-volume-up');
        } else {
            bgMusic.pause();
            icon.classList.replace('fa-volume-up', 'fa-volume-mute');
        }
    });
    bgMusic.play().catch(() => {
        document.addEventListener('click', () => bgMusic.play(), { once: true });
    });
});

document.getElementById('menuSearch').addEventListener('keyup', function() {
    const term = this.value.toLowerCase();
    const rows = document.querySelectorAll('#menuTableBody tr');
    let hasResult = false;
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(term)) {
            row.style.display = '';
            hasResult = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('noResults').style.display = hasResult ? 'none' : 'block';
});

function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').innerHTML = `<i class="far fa-clock"></i> ` + 
        now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) + ' - ' +
        now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateClock, 1000);
updateClock();

const facts = [
    "Coffee is the second most traded commodity after oil.",
    "Espresso means 'pressed out' in Italian.",
    "Coffee beans are actually fruit seeds.",
    "The world drinks 2.25 billion cups daily."
];
let fIdx = 0;
setInterval(() => {
    fIdx = (fIdx + 1) % facts.length;
    document.getElementById('coffeeFact').innerText = facts[fIdx];
}, 8000);

<?php if ($receipt_data): ?>
    new bootstrap.Modal(document.getElementById('transactionModal')).show();
<?php endif; ?>
</script>
</body>
</html>