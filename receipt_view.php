<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Digital Receipt - CSR Cafe</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            background: linear-gradient(135deg, #2c241b 0%, #4a3b2a 100%);
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .receipt-container {
            background-color: #fff;
            width: 100%;
            max-width: 450px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            border-top: 5px solid #1A0F0A;
            border-radius: 8px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 20px;
        }
        
        .header h2 { 
            margin: 0; 
            color: #1A0F0A; 
            font-size: 1.8rem; 
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 0.9rem;
            margin: 5px 0;
        }
        
        .receipt-id {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.75rem;
            color: #666;
            margin-top: 10px;
        }
        
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: #333;
        }
        
        .row .label {
            color: #666;
        }
        
        .row .value {
            font-weight: bold;
            color: #1A0F0A;
        }
        
        .section {
            margin: 20px 0;
        }
        
        .section-title {
            font-weight: bold;
            color: #1A0F0A;
            margin-bottom: 15px;
            font-size: 1.1rem;
            padding-bottom: 8px;
            border-bottom: 2px solid #C9A961;
        }
        
        .items-list {
            border-top: 1px dashed #ccc;
            border-bottom: 1px dashed #ccc;
            padding: 15px 0;
            margin: 15px 0;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
        }
        
        .item-name {
            flex: 1;
            color: #333;
        }
        
        .item-price {
            font-weight: bold;
            color: #1A0F0A;
            margin-left: 15px;
        }
        
        .divider {
            border-top: 1px dashed #ccc;
            margin: 20px 0;
        }
        
        .total-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .total {
            font-weight: bold;
            font-size: 1.5rem;
            color: #1A0F0A;
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        
        .change {
            color: #28a745;
            font-size: 1.2rem;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.85rem;
            color: #888;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
        }
        
        .footer p {
            margin: 8px 0;
        }
        
        .badge {
            background: #C9A961;
            color: #1A0F0A;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .icon {
            color: #C9A961;
            margin-right: 8px;
        }
        
        .developers {
            margin-top: 25px;
            font-size: 0.7rem;
            color: #aaa;
            text-align: center;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
        }
    </style>
</head>
<body>
    <?php
    $receipt_id = isset($_GET['id']) ? htmlspecialchars($_GET['id']) : 'N/A';
    $customer = isset($_GET['customer']) ? htmlspecialchars($_GET['customer']) : 'Guest';
    $total = isset($_GET['total']) ? floatval($_GET['total']) : 0;
    $tendered = isset($_GET['tendered']) ? floatval($_GET['tendered']) : 0;
    $change = isset($_GET['change']) ? floatval($_GET['change']) : 0;
    $date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : date('Y-m-d H:i:s');
    $method = isset($_GET['method']) ? htmlspecialchars($_GET['method']) : 'Cash';
    
    // Decode items from JSON
    $items_json = isset($_GET['items']) ? $_GET['items'] : '[]';
    $items = json_decode($items_json, true);
    if (!is_array($items)) $items = [];
    ?>
    
    <div class="receipt-container">
        <div class="header">
            <h2><i class="fas fa-coffee icon"></i>CSR CAFE</h2>
            <p class="subtitle">OFFICIAL DIGITAL RECEIPT</p>
            <div class="receipt-id">
                <i class="fas fa-barcode"></i> <?php echo $receipt_id; ?>
            </div>
        </div>
        
        <div class="section">
            <div class="row">
                <span class="label"><i class="fas fa-calendar-alt icon"></i>Date:</span> 
                <span class="value"><?php echo date('M d, Y h:i A', strtotime($date)); ?></span>
            </div>
            <div class="row">
                <span class="label"><i class="fas fa-user icon"></i>Customer:</span> 
                <span class="value"><?php echo $customer; ?></span>
            </div>
            <div class="row">
                <span class="label"><i class="fas fa-credit-card icon"></i>Payment:</span> 
                <span class="badge badge-success"><?php echo $method; ?></span>
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="section">
            <div class="section-title"><i class="fas fa-list icon"></i>Order Items</div>
            <div class="items-list">
                <?php if (!empty($items)): ?>
                    <?php foreach($items as $item): ?>
                    <div class="item">
                        <span class="item-name">
                            <?php echo htmlspecialchars($item['name']); ?> 
                            <span style="color: #999;">(x<?php echo $item['qty']; ?>)</span>
                        </span>
                        <span class="item-price">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item">
                        <span class="item-name" style="color: #999;">No items found</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="total-section">
            <div class="total">
                <span>TOTAL:</span> 
                <span>₱<?php echo number_format($total, 2); ?></span>
            </div>
            
            <?php if ($method === 'Cash'): ?>
            <div class="row" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <span class="label">Cash Given:</span>
                <span class="value">₱<?php echo number_format($tendered, 2); ?></span>
            </div>
            <div class="change">
                <span>CHANGE:</span> 
                <span>₱<?php echo number_format($change, 2); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p><i class="fas fa-heart" style="color: #C9A961;"></i> Thank you for your purchase!</p>
            <p>Have a great day! ☕</p>
            <p style="margin-top: 15px; font-size: 0.75rem;">
                <i class="fas fa-phone"></i> Contact: (123) 456-7890 | 
                <i class="fas fa-envelope"></i> info@csrcafe.com
            </p>
        </div>
       
        <div class="developers">
            <b><i class="fas fa-code"></i> DEVELOPED BY:<br>
            Rayver S. Reyes - Full Stack Developer / Project Lead<br>
            Char Mae Grace Bering - Backend Developer & Database Handler<br>
            Sebastian Rafael Belando - Backend Developer</b>
        </div>
    </div>
</body>
</html>