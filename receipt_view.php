<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Receipt</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f4f4f4;
            padding: 20px;
            margin: 0;
            display: flex;
            justify-content: center;
        }
        .receipt-container {
            background-color: #fff;
            width: 100%;
            max-width: 400px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-top: 5px solid #1A0F0A;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 15px;
        }
        .header h2 { margin: 0; color: #1A0F0A; font-size: 1.5rem; }
        .header p { margin: 5px 0 0; color: #666; font-size: 0.9rem; }
        
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 1rem;
            color: #333;
        }
        .divider {
            border-top: 1px dashed #ccc;
            margin: 15px 0;
        }
        .total {
            font-weight: bold;
            font-size: 1.2rem;
            color: #000;
        }
        .footer {
            text-align: center;
            margin-top: 25px;
            font-size: 0.85rem;
            color: #888;
        }
        .developers {
            margin-top: 20px;
            font-size: 0.7rem;
            color: #aaa;
            text-align: center;
            border-top: 1px dashed #ccc;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h2>CRS CAFE</h2>
            <p>OFFICIAL DIGITAL RECEIPT</p>
        </div>
        
        <div class="row"><span>Date:</span> <span><?php echo htmlspecialchars($_GET['date'] ?? '-'); ?></span></div>
        <div class="row"><span>Customer:</span> <span><?php echo htmlspecialchars($_GET['customer'] ?? 'Guest'); ?></span></div>
        
        <div class="divider"></div>
        
        <div class="row"><span>Item:</span> <span><?php echo htmlspecialchars($_GET['item'] ?? '-'); ?></span></div>
        <div class="row"><span>Price:</span> <span>₱<?php echo number_format((float)($_GET['price'] ?? 0), 2); ?></span></div>
        
        <div class="divider"></div>
        
        <div class="row"><span>Cash Given:</span> <span>₱<?php echo number_format((float)($_GET['tendered'] ?? 0), 2); ?></span></div>
        <div class="row total"><span>Change:</span> <span>₱<?php echo number_format((float)($_GET['change'] ?? 0), 2); ?></span></div>
        
        <div class="footer"><p>Thank you for your purchase!<br>Have a great day!</p></div>
       
        <div class="developers">
             <b><i class="fas fa-code"></i> DEVELOPED BY:<br>
                Rayver S. Reyes - full stack developer / project lead
            <br> Char Mae Grace Bering - backend developer & database handler 
            <br>Sebastian Rafael Belando - backend developer </b>
        </div>
    </div>
</body>
</html>
