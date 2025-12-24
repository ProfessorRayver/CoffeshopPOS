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

// --- logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// --- EMPLOYEE PERFORMANCE (Updated Query) ---
// Now excludes 'admin', 'cashier', and 'superadmin' from the visual list
$employee_query = "
    SELECT 
        u.id,
        u.username,
        u.role,
        COUNT(ds.sale_time) as total_transactions,
        COALESCE(SUM(ds.price), 0) as total_sales,
        COALESCE(AVG(ds.price), 0) as avg_transaction,
        MIN(ds.sale_time) as first_sale,
        MAX(ds.sale_time) as last_sale
    FROM users u
    LEFT JOIN daily_sales ds ON u.id = ds.cashier_id 
        AND DATE(ds.sale_time) BETWEEN ? AND ?
    WHERE u.role IN ('cashier', 'admin')
    AND u.username NOT IN ('admin', 'cashier', 'superadmin') 
    GROUP BY u.id, u.username, u.role
    ORDER BY total_sales DESC
";
$stmt = mysqli_prepare($conn, $employee_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$employee_result = mysqli_stmt_get_result($stmt);

$employee_data = [];
$top_employee = ['name' => 'N/A', 'sales' => 0];

while($row = mysqli_fetch_assoc($employee_result)) {
    $employee_data[] = $row;
    
    // Only count as top employee if they actually made sales
    if ($row['total_sales'] > 0 && $row['total_sales'] > $top_employee['sales']) {
        $top_employee = ['name' => $row['username'], 'sales' => $row['total_sales']];
    }
}

// --- DAILY SALES PATTERN ---
$daily_query = "
    SELECT 
        DATE_FORMAT(sale_time, '%b %d') as date_label,
        COUNT(*) as transaction_count,
        COALESCE(SUM(price), 0) as total_sales
    FROM daily_sales
    WHERE DATE(sale_time) BETWEEN ? AND ?
    GROUP BY DATE(sale_time)
    ORDER BY DATE(sale_time) ASC
";
$stmt = mysqli_prepare($conn, $daily_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$daily_result = mysqli_stmt_get_result($stmt);

$daily_data = [];
$peak_day = ['name' => 'N/A', 'sales' => 0];
while($row = mysqli_fetch_assoc($daily_result)) {
    $daily_data[] = $row;
    if ($row['total_sales'] > $peak_day['sales']) {
        $peak_day = ['name' => $row['date_label'], 'sales' => $row['total_sales']];
    }
}

// --- WEEKLY COMPARISON (Last 4 Weeks) ---
$weekly_comparison = [];
for ($i = 3; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-$i weeks -" . date('w') . " days"));
    $week_end = date('Y-m-d', strtotime("-$i weeks +" . (6 - date('w')) . " days"));
    
    $week_query = "
        SELECT 
            COALESCE(SUM(price), 0) as total,
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
        COALESCE(SUM(price), 0) as total_sales
    FROM daily_sales
    WHERE MONTH(sale_time) = MONTH(CURRENT_DATE)
        AND YEAR(sale_time) = YEAR(CURRENT_DATE)
    GROUP BY DATE(sale_time), DAYNAME(sale_time)
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
        COALESCE(SUM(price), 0) as revenue
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
    <title>Analytics - CSR Cafe System</title>
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
            transition: background 0.3s, color 0.3s;
        }
        
        /* --- NIGHT MODE STYLES --- */
        body.night-mode {
            background: linear-gradient(135deg, #0f0806 0%, #1a0f0a 100%);
            color: var(--ivory);
        }
        
        body.night-mode .header-bar {
            background: #000;
            border: 1px solid #333;
        }
        
        body.night-mode .stat-card, 
        body.night-mode .panel,
        body.night-mode .filter-bar {
            background: #2c241b; /* Dark coffee brown */
            border-color: #5d4037;
            color: var(--ivory);
        }
        
        body.night-mode .stat-card.highlight {
            background: var(--gold);
            color: var(--espresso);
        }
        
        body.night-mode .panel-header {
            color: var(--gold);
            border-bottom-color: #5d4037;
        }
        
        body.night-mode .table-custom th {
            background: #1a0f0a;
            color: var(--gold);
        }
        
        body.night-mode .table-custom td {
            border-bottom-color: #4a3b2a;
            color: #ddd;
        }
        
        body.night-mode .table-custom tbody tr:hover {
            background: rgba(201, 169, 97, 0.1);
        }
        
        body.night-mode label {
            color: var(--gold);
        }
        
        body.night-mode input {
            background: #1a0f0a;
            color: var(--ivory);
            border-color: var(--gold);
        }
        
        /* ----------------------- */
        
        .container-wrapper {
            max-width: 1800px;
            margin: 0 auto;
        }
        
        /* HEADER BAR */
        .header-bar {
            background: var(--espresso);
            color: var(--gold);
            padding: 25px 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        
        .header-bar h1 {
            margin: 0;
            font-size: 2.2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .nav-links a {
            background: var(--gold);
            color: var(--espresso);
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #d4b56d;
            transform: scale(1.05);
        }
        
        /* Night Mode Toggle Button */
        .btn-theme-toggle {
            background: transparent;
            border: 2px solid var(--gold);
            color: var(--gold);
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        
        .btn-theme-toggle:hover {
            background: var(--gold);
            color: var(--espresso);
        }
        
        /* DATE FILTER */
        .filter-bar {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            gap: 20px;
            align-items: center;
            font-size: 1.1rem;
            transition: background 0.3s;
        }
        
        .filter-bar label {
            font-weight: bold;
            color: var(--espresso);
        }
        
        .filter-bar input {
            padding: 10px;
            border: 2px solid var(--gold);
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .filter-bar button {
            background: var(--espresso);
            color: var(--gold);
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
        }
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: background 0.3s;
        }
        
        .stat-card.highlight {
            background: var(--espresso);
            color: var(--gold);
        }
        
        .stat-card i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .stat-card .label {
            font-size: 1.1rem;
            letter-spacing: 1px;
            font-weight: bold;
        }
        
        /* PANELS */
        .panel {
            background: white;
            border: 3px solid var(--gold);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            transition: background 0.3s;
        }
        
        .panel-header {
            font-size: 1.6rem;
            font-weight: bold;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--espresso);
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .chart-container {
            position: relative;
            height: 450px;
        }
        
        /* TABLES */
        .table-custom {
            width: 100%;
            font-size: 1.1rem;
        }
        
        .table-custom th {
            background: var(--espresso);
            color: white;
            padding: 15px;
            font-size: 1rem;
            letter-spacing: 1px;
        }
        
        .table-custom td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table-custom tbody tr:hover {
            background: rgba(201,169,97,0.15);
        }
        
        .rank-badge {
            background: var(--gold);
            color: var(--espresso);
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.95rem;
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
        
        /* Performance Indicator */
        .performance-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .perf-excellent { background: #4CAF50; box-shadow: 0 0 5px #4CAF50; }
        .perf-good { background: #FFC107; box-shadow: 0 0 5px #FFC107; }
        .perf-average { background: #FF9800; }
        .perf-low { background: #F44336; }
    </style>
</head>
<body>
<div class="container-wrapper">
    <div class="header-bar">
        <h1><i class="fas fa-chart-line"></i> SALES ANALYTICS</h1>
        <div class="nav-links">
            <button id="themeToggle" class="btn-theme-toggle" title="Toggle Night Mode">
                <i class="fas fa-moon"></i>
            </button>
            <a href="admin.php"><i class="fas fa-arrow-left"></i> Back to Admin</a>
            <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <form class="filter-bar" method="GET">
        <label><i class="fas fa-calendar"></i> From:</label>
        <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
        
        <label>To:</label>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
        
        <button type="submit"><i class="fas fa-filter"></i> Apply Filter</button>
    </form>
    
    <div class="stats-grid">
        <div class="stat-card highlight">
            <i class="fas fa-star"></i>
            <div class="value"><?php echo $peak_day['name']; ?></div>
            <div class="label">PEAK SALES DATE</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-peso-sign"></i>
            <div class="value">₱<?php echo number_format($peak_day['sales'], 2); ?></div>
            <div class="label">PEAK DAY SALES</div>
        </div>
        
        <div class="stat-card highlight">
            <i class="fas fa-user-tie"></i>
            <div class="value"><?php echo strtoupper($top_employee['name']); ?></div>
            <div class="label">TOP EMPLOYEE</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-trophy"></i>
            <div class="value">₱<?php echo number_format($top_employee['sales'], 2); ?></div>
            <div class="label">TOP EMPLOYEE SALES</div>
        </div>
    </div>
    
    <div class="panel">
        <div class="panel-header">
            <i class="fas fa-users"></i> EMPLOYEE PERFORMANCE TRACKER (Real Staff Only)
        </div>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>RANK</th>
                    <th>EMPLOYEE</th>
                    <th>ROLE</th>
                    <th>TRANSACTIONS</th>
                    <th>TOTAL SALES</th>
                    <th>AVG PER SALE</th>
                    <th>DUTY TIME</th>
                    <th>PERFORMANCE</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach($employee_data as $emp): 
                    $badge_class = '';
                    if ($rank == 1) $badge_class = 'gold';
                    elseif ($rank == 2) $badge_class = 'silver';
                    elseif ($rank == 3) $badge_class = 'bronze';
                    
                    // Simple Performance Logic
                    $perf_class = 'perf-low';
                    if ($emp['total_sales'] >= 5000) $perf_class = 'perf-excellent';
                    elseif ($emp['total_sales'] >= 2000) $perf_class = 'perf-good';
                    elseif ($emp['total_sales'] >= 500) $perf_class = 'perf-average';
                    
                    $perf_label = '';
                    if ($perf_class == 'perf-excellent') $perf_label = 'Excellent';
                    elseif ($perf_class == 'perf-good') $perf_label = 'Good';
                    elseif ($perf_class == 'perf-average') $perf_label = 'Average';
                    else $perf_label = 'Low';
                    
                    // Automatic Duty Time Calculation
                    $duty_time = '0 hrs 0 min';
                    if ($emp['first_sale'] && $emp['last_sale']) {
                        $start = new DateTime($emp['first_sale']);
                        $end = new DateTime($emp['last_sale']);
                        
                        // If only 1 transaction, show N/A or minimal time
                        if ($emp['total_transactions'] > 1) {
                            $diff = $start->diff($end);
                            $duty_time = $diff->format('%h hrs %i min');
                        } else {
                            $duty_time = "Just started";
                        }
                    }
                ?>
                <tr>
                    <td><span class="rank-badge <?php echo $badge_class; ?>">#<?php echo $rank; ?></span></td>
                    <td><strong><?php echo strtoupper($emp['username']); ?></strong></td>
                    <td><?php echo strtoupper($emp['role']); ?></td>
                    <td><?php echo $emp['total_transactions']; ?></td>
                    <td><strong>₱<?php echo number_format($emp['total_sales'], 2); ?></strong></td>
                    <td>₱<?php echo number_format($emp['avg_transaction'], 2); ?></td>
                    <td><?php echo $duty_time; ?></td>
                    <td>
                        <span class="performance-indicator <?php echo $perf_class; ?>"></span>
                        <?php echo $perf_label; ?>
                    </td>
                </tr>
                <?php 
                    $rank++;
                endforeach; 
                if (count($employee_data) == 0) {
                    echo '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #888;">No employee activity found for this period.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="charts-grid">
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-calendar-day"></i> DAILY SALES PATTERN
            </div>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
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
                    <td><strong>₱<?php echo number_format($week['total'], 2); ?></strong></td>
                    <td>₱<?php echo number_format($avg_per_day, 2); ?></td>
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
                    <td><strong>₱<?php echo number_format($day['total_sales'], 2); ?></strong></td>
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
// NIGHT MODE TOGGLE SCRIPT
const themeToggle = document.getElementById('themeToggle');
const body = document.body;
const icon = themeToggle.querySelector('i');

// Check local storage for saved preference
if (localStorage.getItem('theme') === 'night') {
    body.classList.add('night-mode');
    icon.classList.remove('fa-moon');
    icon.classList.add('fa-sun');
}

themeToggle.addEventListener('click', () => {
    body.classList.toggle('night-mode');
    
    // Save preference and swap icon
    if (body.classList.contains('night-mode')) {
        localStorage.setItem('theme', 'night');
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
    } else {
        localStorage.setItem('theme', 'day');
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
    }
});

// Daily Pattern Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
const dailyChart = new Chart(dailyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($daily_data, 'date_label')); ?>,
        datasets: [{
            label: 'Sales (₱)',
            data: <?php echo json_encode(array_column($daily_data, 'total_sales')); ?>,
            backgroundColor: 'rgba(201, 169, 97, 0.8)',
            borderColor: '#1A0F0A',
            borderWidth: 2
        }, {
            label: 'Transactions',
            data: <?php echo json_encode(array_column($daily_data, 'transaction_count')); ?>,
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