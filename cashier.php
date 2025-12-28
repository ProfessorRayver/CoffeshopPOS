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

$message = "";
$msgType = "";
$receipt_data = null;

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- ACTION: ADD TO CART ---
if (isset($_POST['add_to_cart'])) {
    $pid = $_POST['product_id'];
    $qty = intval($_POST['quantity']);
    if ($qty < 1) $qty = 1;

    $query = mysqli_query($conn, "SELECT * FROM menu_tbl WHERE product_id = '$pid'");
    if (mysqli_num_rows($query) > 0) {
        $item = mysqli_fetch_assoc($query);
        $found = false;
        foreach ($_SESSION['cart'] as &$cart_item) {
            if ($cart_item['id'] === $pid) {
                $cart_item['qty'] += $qty;
                $cart_item['subtotal'] = $cart_item['qty'] * $cart_item['price'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $item['product_id'], 'name' => $item['drink_name'],
                'price' => $item['price'], 'qty' => $qty, 'subtotal' => $item['price'] * $qty
            ];
        }
    } else {
        $message = "Product ID not found!"; $msgType = "danger";
    }
}

// --- ACTION: REMOVE ITEM ---
if (isset($_POST['remove_index'])) {
    $index = $_POST['remove_index'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}

// --- ACTION: PROCESS PAYMENT (CASH OR PAYPAL) ---
if (isset($_POST['process_payment'])) {
    if (empty($_SESSION['cart'])) {
        $message = "Cart is empty!"; $msgType = "danger";
    } else {
        $customer = $_POST['customer_name'];
        $method = $_POST['payment_method']; 
        $cashier_id = $_SESSION['user_id'];
        
        $grand_total = 0;
        foreach ($_SESSION['cart'] as $item) { $grand_total += $item['subtotal']; }
        
        $amount_given = ($method === 'PayPal') ? $grand_total : floatval($_POST['amount_given']);
        
        if ($method === 'Cash' && $amount_given < $grand_total) {
            $message = "Insufficient Cash!"; $msgType = "danger";
        } else {
            $change = ($method === 'PayPal') ? 0 : ($amount_given - $grand_total);
            $sale_date = date('Y-m-d H:i:s');
            
            $stmt = mysqli_prepare($conn, "INSERT INTO daily_sales (customer_name, product_id, drink_name, price, amount_tendered, change_amount, cashier_id, sale_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $transaction_items = []; 
            foreach ($_SESSION['cart'] as $item) {
                $db_name = ($item['qty'] > 1) ? $item['name'] . " (x" . $item['qty'] . ")" : $item['name'];
                mysqli_stmt_bind_param($stmt, "sssdddis", $customer, $item['id'], $db_name, $item['subtotal'], $amount_given, $change, $cashier_id, $sale_date);
                mysqli_stmt_execute($stmt);
                $transaction_items[] = $item;
            }
            
            $receipt_data = [
                'customer' => $customer, 'items' => $transaction_items, 'total' => $grand_total,
                'tendered' => $amount_given, 'change' => $change, 'date' => $sale_date, 'method' => $method
            ];
            
            $_SESSION['cart'] = [];
            $message = "Payment Received via $method!"; $msgType = "success";
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
    <script src="https://www.paypal.com/sdk/js?client-id=AXa2yZbyGHtToJq-Dl5m8ShrXtUkbnNd19HJCGdvs5JioOXwEm2xLHLejgh-JOxgTOPIMFVhZuwd3lCu&currency=PHP&disable-funding=card"></script>
    <style>
        :root { --espresso: #1A0F0A; --gold: #C9A961; --cream: #F5F5F0; --ivory: #FDFBF7; }
        body { background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%); font-family: 'Courier New', monospace; min-height: 100vh; padding: 20px; overflow-x: hidden; }
        #bg-video { position: fixed; right: 0; bottom: 0; min-width: 100%; min-height: 100%; z-index: -1; object-fit: cover; filter: brightness(0.4); }
        .panel { background: rgba(255, 255, 255, 0.92); border: 3px solid var(--gold); border-radius: 12px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); backdrop-filter: blur(5px); }
        .panel-dark { background: rgba(26, 15, 10, 0.95); color: var(--gold); }
        .cart-area { background: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 15px; overflow-y: auto; height: 220px; border: 1px solid var(--gold); }
        .table-cart { width: 100%; color: white; font-size: 0.9rem; }
        .payment-toggle { display: flex; gap: 10px; margin-bottom: 15px; }
        .btn-pay-opt { flex: 1; border: 2px solid var(--gold); background: transparent; color: var(--gold); font-weight: bold; padding: 10px; border-radius: 8px; }
        .btn-pay-opt.active { background: var(--gold); color: var(--espresso); }
        .facts-section { background: rgba(255, 255, 255, 0.92); border: 3px solid var(--gold); border-radius: 12px; padding: 10px 20px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center; }
        .facts-logo { height: 70px; border: 2px solid var(--gold); border-radius: 50%; background: white; }
    </style>
</head>
<body>
    <video autoplay muted loop id="bg-video"><source src="newbgvideo.mp4" type="video/mp4"></video>

<div class="container mx-auto" style="max-width: 1500px;">
    <div class="panel panel-dark mb-4 d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-cash-register"></i> CASHIER DASHBOARD</h1>
        <div class="text-end">
            <div id="liveClock" class="fw-bold mb-1"></div>
            <span class="small"><i class="fas fa-user"></i> <?php echo strtoupper($_SESSION['username']); ?></span>
            <a href="?logout=1" class="btn btn-sm btn-outline-warning ms-3">LOGOUT</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="panel panel-dark h-100">
                <h4 class="border-bottom border-warning pb-2 mb-3"><i class="fas fa-shopping-cart"></i> CURRENT ORDER</h4>
                
                <?php if($message): ?><div class="alert alert-<?php echo $msgType; ?> p-2 small"><?php echo $message; ?></div><?php endif; ?>

                <form method="POST" class="d-flex gap-2 mb-3">
                    <input type="text" name="product_id" class="form-control bg-transparent text-white border-warning" placeholder="Product ID" required autofocus style="flex:2;">
                    <input type="number" name="quantity" id="quantity" value="1" min="1" class="form-control bg-transparent text-white border-warning text-center" style="width: 80px;">
                    <button type="submit" name="add_to_cart" class="btn btn-warning fw-bold">ADD</button>
                </form>

                <div class="cart-area">
                    <table class="table table-cart table-borderless">
                        <thead class="text-warning border-bottom border-secondary">
                            <tr><th>Item</th><th>Qty</th><th class="text-end">Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['cart'] as $idx => $itm): ?>
                            <tr>
                                <td><?php echo $itm['name']; ?></td>
                                <td><?php echo $itm['qty']; ?></td>
                                <td class="text-end">₱<?php echo number_format($itm['subtotal'], 2); ?></td>
                                <td class="text-end"><form method="POST"><input type="hidden" name="remove_index" value="<?php echo $idx; ?>"><button class="btn btn-link text-danger p-0"><i class="fas fa-times"></i></button></form></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-auto pt-3 border-top border-warning">
                    <h3 class="text-end text-warning mb-3">TOTAL: ₱<?php echo number_format($cart_total, 2); ?></h3>
                    
                    <form method="POST" id="mainCheckoutForm">
                        <input type="hidden" name="payment_method" id="payment_method" value="Cash">
                        <input type="hidden" name="process_payment" value="1">
                        
                        <div class="mb-3">
                            <label class="small text-warning">PAYMENT METHOD</label>
                            <div class="payment-toggle">
                                <button type="button" id="btnCash" class="btn-pay-opt active" onclick="switchMethod('Cash')"><i class="fas fa-money-bill"></i> CASH</button>
                                <button type="button" id="btnPaypal" class="btn-pay-opt" onclick="switchMethod('PayPal')"><i class="fab fa-paypal"></i> PAYPAL</button>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="small">CUSTOMER NAME</label>
                                <input type="text" name="customer_name" id="customer_name" class="form-control bg-transparent text-white border-warning" value="Guest" required>
                            </div>
                            <div class="col-6" id="cashInputArea">
                                <label class="small">AMOUNT TENDERED</label>
                                <input type="number" step="0.01" name="amount_given" id="amount_given" class="form-control bg-transparent text-white border-warning" placeholder="0.00">
                            </div>
                        </div>

                        <div id="paypal-button-container" class="mt-3 d-none"></div>
                        
                        <div id="cashActions" class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-outline-danger w-25" onclick="location.href='?clear_cart=1'">CLEAR</button>
                            <button type="submit" class="btn btn-success w-75 fw-bold"><i class="fas fa-print"></i> PAY & PRINT</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="panel h-100">
                <h4 class="mb-3"><i class="fas fa-book"></i> MENU CATALOG</h4>
                <input type="text" id="menuSearch" class="form-control border-warning mb-3" placeholder="Search drinks...">
                <div style="overflow-y:auto; max-height: 450px;">
                    <table class="table table-hover table-sm">
                        <thead class="table-dark"><tr><th>ID</th><th>NAME</th><th>PRICE</th></tr></thead>
                        <tbody id="menuTableBody">
                            <?php while($m = mysqli_fetch_assoc($menu_data)): ?>
                            <tr><td><?php echo $m['product_id']; ?></td><td><?php echo $m['drink_name']; ?></td><td>₱<?php echo $m['price']; ?></td></tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="facts-section">
        <div>
            <h6 class="fw-bold mb-1"><i class="fas fa-lightbulb"></i> DID YOU KNOW?</h6>
            <p id="coffeeFact" class="small mb-0">Coffee is the second most traded commodity after oil.</p>
        </div>
        <img src="logo.jpg" alt="Logo" class="facts-logo">
    </div>
</div>

<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5>TRANSACTION SUCCESS (<?php echo $receipt_data['method'] ?? ''; ?>)</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <?php if ($receipt_data): ?>
                <h2 class="text-success">₱<?php echo number_format($receipt_data['change'], 2); ?></h2>
                <p class="text-muted small">CHANGE DUE</p>
                <hr>
                <div class="text-start small">
                    <?php foreach($receipt_data['items'] as $itm): ?>
                    <div class="d-flex justify-content-between"><span><?php echo $itm['name']; ?> (x<?php echo $itm['qty']; ?>)</span><span>₱<?php echo number_format($itm['subtotal'], 2); ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button class="btn btn-success w-100" data-bs-dismiss="modal">OK</button></div>
        </div>
    </div>
</div>

<script>
// --- PAYPAL INTEGRATION (FIXED TO REMOVE CREDIT CARD BUTTON) ---
paypal.Buttons({
    style: { layout: 'horizontal', tagline: false },
    fundingSource: paypal.FUNDING.PAYPAL, // STRICTLY ONLY PAYPAL
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                amount: { value: '<?php echo $cart_total; ?>', currency_code: 'PHP' }
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            document.getElementById('payment_method').value = 'PayPal';
            document.getElementById('mainCheckoutForm').submit();
        });
    }
}).render('#paypal-button-container');

function switchMethod(m) {
    document.getElementById('payment_method').value = m;
    const isPaypal = (m === 'PayPal');
    document.getElementById('btnPaypal').classList.toggle('active', isPaypal);
    document.getElementById('btnCash').classList.toggle('active', !isPaypal);
    document.getElementById('cashInputArea').classList.toggle('d-none', isPaypal);
    document.getElementById('cashActions').classList.toggle('d-none', isPaypal);
    document.getElementById('paypal-button-container').classList.toggle('d-none', !isPaypal);
}

setInterval(() => { document.getElementById('liveClock').innerHTML = new Date().toLocaleTimeString(); }, 1000);
document.getElementById('menuSearch').addEventListener('keyup', function() {
    let t = this.value.toLowerCase();
    document.querySelectorAll('#menuTableBody tr').forEach(r => { r.style.display = r.innerText.toLowerCase().includes(t) ? '' : 'none'; });
});

<?php if ($receipt_data): ?>
    new bootstrap.Modal(document.getElementById('transactionModal')).show();
<?php endif; ?>
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>