<?php
session_name('jo_session');
session_start();
require "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jo') {
    header("Location: login.php");
    exit;
}

require "jo_header.php";

// Update last activity
$stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();

// Get full name
$fullName = $_SESSION['username'];
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if($data && !empty($data['full_name'])){
    $fullName = $data['full_name'];
}

// Total Reports
$totalReports = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM weekly_reports WHERE user_id=".$_SESSION['user_id']);
if($result){
    $totalReports = $result->fetch_assoc()['total'];
}

// Last Report
$lastReport = '-';
$result = $conn->query("SELECT report_name FROM weekly_reports WHERE user_id=".$_SESSION['user_id']." ORDER BY created_at DESC LIMIT 1");
if($result && $row = $result->fetch_assoc()){
    $lastReport = $row['report_name'];
}

// This Month Reports
$thisMonth = date('Y-m');
$monthlyReports = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM weekly_reports WHERE user_id=".$_SESSION['user_id']." AND DATE_FORMAT(created_at,'%Y-%m') = '$thisMonth'");
if($result){
    $monthlyReports = $result->fetch_assoc()['total'];
}

// Average Reports
$totalMonths = max(1, $conn->query("SELECT COUNT(DISTINCT DATE_FORMAT(created_at,'%Y-%m')) as months FROM weekly_reports WHERE user_id=".$_SESSION['user_id'])->fetch_assoc()['months']);
$averageReports = $totalReports > 0 ? round($totalReports / $totalMonths, 1) : 0;
?>

<style>
    body.dark .analytics-card {
    background: linear-gradient(145deg, #1e1e1e, #2a2a2a);
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
}

body.dark .analytics-card h4,
body.dark .analytics-card p {
    color: #f5f5f5;
}

body.dark .card-blue { border-left: 5px solid #4db8ff; color: #4db8ff; }
body.dark .card-orange { border-left: 5px solid #ffaa33; color: #ffaa33; }
body.dark .card-purple { border-left: 5px solid #cc88ff; color: #cc88ff; }
body.dark .card-green { border-left: 5px solid #55dd77; color: #55dd77; }

/* Dark mode for chart */
body.dark .analytics-chart {
    background: linear-gradient(145deg, #1e1e1e, #2a2a2a);
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
}

/* Dark mode for headings */
body.dark h2,
body.dark h3,
body.dark p {
    color: #f5f5f5;
}

/* Dark mode for main content */
body.dark .main-content {
    background-color: #121212;
}
.analytics-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.analytics-card {
    background: linear-gradient(145deg, #ffffff, #f0f2f5);
    transition: all 0.3s;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
.card-blue { border-left: 5px solid #001f3f; color: #001f3f; }
.card-orange { border-left: 5px solid #ff8800; color: #ff8800; }
.card-maroon { border-left: 5px solid maroon; color: maroon; }
.card-green { border-left: 5px solid #28a745; color: #28a745; }
.card-purple { border-left: 5px solid purple; color: purple; }
.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
}
.card-icon { font-size: 28px; margin-bottom: 10px; }
.analytics-chart { padding: 25px; border-radius: 12px; background: linear-gradient(145deg, #ffffff, #f4f6f9); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
</style>
<div class="main-content"> <!-- IMPORTANT: match your jo_header layout -->

<h2>JO Dashboard</h2>
<p>Welcome, <strong><?= htmlspecialchars($fullName) ?></strong>.</p>


 <h3>Report Analytics</h3>

    <div class="analytics-cards">
        <!-- Total Reports -->
        <div class="analytics-card card-blue">
            <div class="card-icon">📝</div>
            <h4>Total Reports</h4>
            <p><?= $totalReports ?></p>
        </div>

        <!-- This Month -->
        <div class="analytics-card card-orange">
            <div class="card-icon">📊</div>
            <h4>This Month</h4>
            <p><?= $monthlyReports ?></p>
        </div>

        <!-- Average Reports -->
        <div class="analytics-card card-purple">
            <div class="card-icon">📈</div>
            <h4>Monthly Average</h4>
            <p><?= $averageReports ?></p>
        </div>

        <!-- Last Report -->
        <div class="analytics-card card-green">
            <div class="card-icon">📅</div>
            <h4>Last Report Submitted</h4>
            <p style="font-size:14px"><?= htmlspecialchars($lastReport) ?></p>
        </div>
    </div>

    <!-- Combined Bar Chart -->
    <div class="analytics-chart">
        <canvas id="combinedChart"></canvas>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('combinedChart').getContext('2d');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Total Reports', 'This Month', 'Average Reports'],
        datasets: [{
            label: 'Report Metrics',
            data: [<?= $totalReports ?>, <?= $monthlyReports ?>, <?= $averageReports ?>],
            backgroundColor: ['#001f3f','#ff8c00','#6f42c1'],
            borderRadius: 6,
            barPercentage: 0.6,
            categoryPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
        },
        scales: {
            y: { beginAtZero: true },
            x: { display: true }
        }
    }
});
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Total Reports', 'This Month', 'Average Reports'],
        datasets: [{
            label: 'Report Metrics',
            data: [<?= $totalReports ?>, <?= $monthlyReports ?>, <?= $averageReports ?>],
            backgroundColor: ['#001f3f','#ff8c00','#6f42c1'],
            borderRadius: 6,
            barPercentage: 0.6,
            categoryPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
        },
        scales: {
            y: { beginAtZero: true },
            x: { display: true }
        }
    }
});

// Apply dark mode to chart on load
function applyChartDarkMode(isDark) {
    chart.options.scales.x.ticks = { color: isDark ? '#eee' : '#333' };
    chart.options.scales.y.ticks = { color: isDark ? '#eee' : '#333' };
    chart.options.scales.x.grid  = { color: isDark ? '#444' : '#ddd' };
    chart.options.scales.y.grid  = { color: isDark ? '#444' : '#ddd' };
    chart.update();
}

// Check on load
if (localStorage.getItem("darkMode") === "enabled") {
    applyChartDarkMode(true);
}

// Listen for dark toggle button
document.getElementById("darkToggle").addEventListener("click", function() {
    const isDark = document.body.classList.contains("dark");
    applyChartDarkMode(isDark);
});
</script>
<?php require "jo_footer.php"; ?>
