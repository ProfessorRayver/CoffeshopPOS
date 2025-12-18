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

// --- HANDLE LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// --- WEEKLY ANALYSIS ---
// Get sales by day of week
$weekly_query = "
    SELECT 
        DAYNAME(sale_time) as day_name,
        DAYOFWEEK(sale_time) as day_num,
        COUNT(*) as transaction_count,
        SUM(price) as total_sales
    FROM daily_sales
    WHERE DATE(sale_time) BETWEEN ? AND ?
    GROUP BY day_name, day_num
    ORDER BY day_num
";
$stmt = mysqli_prepare($conn, $weekly_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$weekly_result = mysqli_stmt_get_result($stmt);

$weekly_data = [];
$peak_day = ['name' => 'N/A', 'sales' => 0];
while($row = mysqli_fetch_assoc($weekly_result)) {
    $weekly_data[] = $row;
    if ($row['total_sales'] > $peak_day['sales']) {
        $peak_day = ['name' => $row['day_name'], 'sales' => $row['total_sales']];
    }
}

// --- WEEKLY COMPARISON (Last 4 Weeks) ---
$weekly_comparison = [];
for ($i = 3; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-$i weeks Sunday"));
    $week_end = date('Y-m-d', strtotime("-$i weeks Saturday"));
    
    $week_query = "
        SELECT 
            SUM(price) as total,
            COUNT(*) as transactions
        FROM daily_sales
        WHERE DATE(sale_time) BETWEEN ? AND ?
    ";
    $stmt = mysqli_prepare($conn, $week_query);
    mysqli_stmt_bind_param($stmt, "ss", $week_start, $week_end);
    mysqli_stmt_execute($stmt);
    $week_result = mysqli_stmt_get_result($stmt);
    $week_data = mysqli_fetch_assoc($week_result);
    
    $weekly_comparison[] = [
        'week' => 'Week ' . (4 - $i),
        'start' => $week_start,
        'end' => $week_end,
        'total' => $week_data['total'] ?? 0,
        'transactions' => $week_data['transactions'] ?? 0
    ];
}

// --- MONTHLY HOTSPOTS ---
$monthly_query = "
    SELECT 
        DATE(sale_time) as sale_date,
        DAYNAME(sale_time) as day_name,
        COUNT(*) as transaction_count,
        SUM(price) as total_sales
    FROM daily_sales
    WHERE MONTH(sale_time) = MONTH(CURRENT_DATE)
        AND YEAR(sale_time) = YEAR(CURRENT_DATE)
    GROUP BY sale_date, day_name
    ORDER BY total_sales DESC
    LIMIT 10
";
$monthly_result = mysqli_query($conn, $monthly_query);
$monthly_hotspots = [];
while($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_hotspots[] = $row;
}

// --- BEST SELLING PRODUCTS ---
$products_query = "
    SELECT 
        drink_name,
        COUNT(*) as sold_count,
        SUM(price) as revenue
    FROM daily_sales
    WHERE DATE(sale_time) BETWEEN ? AND ?
    GROUP BY drink_name
    ORDER BY sold_count DESC
    LIMIT 5
";
$stmt = mysqli_prepare($conn, $products_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);
$top_products = [];
while($row = mysqli_fetch_assoc($products_result)) {
    $top_products[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics - Coffee Shop System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
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
            max-width: 1800px;
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
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            background: var(--gold);
            color: var(--espresso);
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #d4b56d;
            transform: scale(1.05);
        }
        
        /* DATE FILTER */
        .filter-bar {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filter-bar label {
            font-weight: bold;
            color: var(--espresso);
        }
        
        .filter-bar input {
            padding: 8px;
            border: 2px solid var(--gold);
            border-radius: 6px;
        }
        
        .filter-bar button {
            background: var(--espresso);
            color: var(--gold);
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card.highlight {
            background: var(--espresso);
            color: var(--gold);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-card .label {
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        
        /* PANELS */
        .panel {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .panel-header {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--espresso);
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
        }
        
        /* TABLES */
        .table-custom {
            width: 100%;
            font-size: 0.9rem;
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
        
        .rank-badge {
            background: var(--gold);
            color: var(--espresso);
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .rank-badge.gold {
            background: #FFD700;
        }
        
        .rank-badge.silver {
            background: #C0C0C0;
        }
        
        .rank-badge.bronze {
            background: #CD7F32;
        }
    </style>
</head>
<body>
<div class="container-wrapper">
    <!-- HEADER -->
    <div class="header-bar">
        <h1><i class="fas fa-chart-line"></i> SALES ANALYTICS</h1>
        <div class="nav-links">
            <a href="admin.php"><i class="fas fa-arrow-left"></i> Back to Admin</a>
            <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- DATE FILTER -->
    <form class="filter-bar" method="GET">
        <label><i class="fas fa-calendar"></i> From:</label>
        <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
        
        <label>To:</label>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
        
        <button type="submit"><i class="fas fa-filter"></i> Apply Filter</button>
    </form>
    
    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card highlight">
            <i class="fas fa-star"></i>
            <div class="value"><?php echo $peak_day['name']; ?></div>
            <div class="label">PEAK DAY OF WEEK</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-dollar-sign"></i>
            <div class="value">$<?php echo number_format($peak_day['sales'], 2); ?></div>
            <div class="label">PEAK DAY SALES</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-fire"></i>
            <div class="value"><?php echo count($monthly_hotspots); ?></div>
            <div class="label">MONTHLY HOTSPOTS</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-chart-bar"></i>
            <div class="value"><?php echo count($weekly_comparison); ?></div>
            <div class="label">WEEKS TRACKED</div>
        </div>
    </div>
    
    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-calendar-week"></i> WEEKLY SALES PATTERN
            </div>
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-trophy"></i> TOP 5 PRODUCTS
            </div>
            <div class="chart-container">
                <canvas id="productsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- WEEKLY COMPARISON TABLE -->
    <div class="panel">
        <div class="panel-header">
            <i class="fas fa-exchange-alt"></i> 4-WEEK COMPARISON
        </div>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>WEEK</th>
                    <th>PERIOD</th>
                    <th>TRANSACTIONS</th>
                    <th>TOTAL SALES</th>
                    <th>AVG PER DAY</th>
                    <th>GROWTH</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $prev_total = 0;
                foreach($weekly_comparison as $week): 
                    $avg_per_day = $week['total'] / 7;
                    $growth = $prev_total > 0 ? (($week['total'] - $prev_total) / $prev_total * 100) : 0;
                    $growth_class = $growth >= 0 ? 'text-success' : 'text-danger';
                    $growth_icon = $growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                ?>
                <tr>
                    <td><strong><?php echo $week['week']; ?></strong></td>
                    <td><?php echo date('M d', strtotime($week['start'])) . ' - ' . date('M d', strtotime($week['end'])); ?></td>
                    <td><?php echo $week['transactions']; ?></td>
                    <td><strong>$<?php echo number_format($week['total'], 2); ?></strong></td>
                    <td>$<?php echo number_format($avg_per_day, 2); ?></td>
                    <td class="<?php echo $growth_class; ?>">
                        <?php if ($prev_total > 0): ?>
                            <i class="fas <?php echo $growth_icon; ?>"></i>
                            <?php echo number_format(abs($growth), 1); ?>%
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    $prev_total = $week['total'];
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>
    
    <!-- MONTHLY HOTSPOTS -->
    <div class="panel">
        <div class="panel-header">
            <i class="fas fa-fire"></i> THIS MONTH'S HOTSPOT DAYS (Top 10)
        </div>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>RANK</th>
                    <th>DATE</th>
                    <th>DAY</th>
                    <th>TRANSACTIONS</th>
                    <th>TOTAL SALES</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach($monthly_hotspots as $day): 
                    $badge_class = '';
                    if ($rank == 1) $badge_class = 'gold';
                    elseif ($rank == 2) $badge_class = 'silver';
                    elseif ($rank == 3) $badge_class = 'bronze';
                ?>
                <tr>
                    <td><span class="rank-badge <?php echo $badge_class; ?>">#<?php echo $rank; ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($day['sale_date'])); ?></td>
                    <td><strong><?php echo $day['day_name']; ?></strong></td>
                    <td><?php echo $day['transaction_count']; ?></td>
                    <td><strong>$<?php echo number_format($day['total_sales'], 2); ?></strong></td>
                </tr>
                <?php 
                    $rank++;
                endforeach; 
                if (count($monthly_hotspots) == 0) {
                    echo '<tr><td colspan="5" style="text-align: center; padding: 40px;">No data available for this month</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Weekly Pattern Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
const weeklyChart = new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($weekly_data, 'day_name')); ?>,
        datasets: [{
            label: 'Sales ($)',
            data: <?php echo json_encode(array_column($weekly_data, 'total_sales')); ?>,
            backgroundColor: 'rgba(201, 169, 97, 0.8)',
            borderColor: '#1A0F0A',
            borderWidth: 2
        }, {
            label: 'Transactions',
            data: <?php echo json_encode(array_column($weekly_data, 'transaction_count')); ?>,
            backgroundColor: 'rgba(26, 15, 10, 0.8)',
            borderColor: '#C9A961',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Top Products Chart
const productsCtx = document.getElementById('productsChart').getContext('2d');
const productsChart = new Chart(productsCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($top_products, 'drink_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($top_products, 'sold_count')); ?>,
            backgroundColor: [
                '#C9A961',
                '#1A0F0A',
                '#8B7355',
                '#D4B56D',
                '#6B5D52'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
</body>
</html>