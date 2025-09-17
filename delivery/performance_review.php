<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header("Location: ../login.php");
    exit();
}
require_once('../config/db_connect.php');
$partner_id = $_SESSION['user_id'];

// Sidebar user info
$user_stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $partner_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// KPI queries
$kpis = [
    'delivered_all' => 0,
    'cancelled_all' => 0,
    'in_progress_all' => 0,
    'delivered_30' => 0,
    'cancelled_30' => 0,
];

// All-time
$q1 = $conn->prepare("SELECT 
    SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered_all,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_all,
    SUM(CASE WHEN status IN ('pending','out_for_delivery') THEN 1 ELSE 0 END) AS in_progress_all
  FROM delivery_assignments WHERE partner_id = ?");
$q1->bind_param('i', $partner_id);
$q1->execute();
$res1 = $q1->get_result()->fetch_assoc();
$q1->close();
if ($res1) { $kpis = array_merge($kpis, $res1); }

// Last 30 days (based on assigned_at if present)
$q2 = $conn->prepare("SELECT 
    SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered_30,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_30
  FROM delivery_assignments WHERE partner_id = ? AND (assigned_at IS NULL OR assigned_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))");
$q2->bind_param('i', $partner_id);
$q2->execute();
$res2 = $q2->get_result()->fetch_assoc();
$q2->close();
if ($res2) { $kpis = array_merge($kpis, $res2); }

// Breakdown by meal type for charts/cards
$by_meal = [];
$q3 = $conn->prepare("SELECT meal_type,
    SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) delivered,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) cancelled
  FROM delivery_assignments WHERE partner_id = ? GROUP BY meal_type");
$q3->bind_param('i', $partner_id);
$q3->execute();
$r3 = $q3->get_result();
while ($row = $r3->fetch_assoc()) { $by_meal[] = $row; }
$q3->close();

// Fetch customer feedback related to this partner's deliveries
$feedbacks = [];
$feedback_sql = "
    SELECT f.rating, f.comments, f.created_at, u.name as customer_name
    FROM feedback f
    JOIN subscriptions s ON f.user_id = s.user_id
    JOIN (
        SELECT DISTINCT subscription_id FROM delivery_assignments WHERE partner_id = ?
    ) da ON s.subscription_id = da.subscription_id
    JOIN users u ON f.user_id = u.user_id
    WHERE f.feedback_type = 'service'
    ORDER BY f.created_at DESC
    LIMIT 10
";
$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param('i', $partner_id);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();
while ($row = $feedback_result->fetch_assoc()) {
    $feedbacks[] = $row;
}
$feedback_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Performance Review - Tiffinly</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="../user/user_css/profile_style.css">
  <style>
    :root { --primary-color:#2C7A7B; --light:#F9F9F9; --dark:#2C3E50; --light-gray:#e9ecef; }
    body { font-family:'Poppins',sans-serif; margin:0; background:var(--light); color:var(--dark); }
    .dashboard-container { display:grid; grid-template-columns:280px 1fr; min-height:100vh; height:100vh; overflow:hidden; }
    .sidebar { background:#fff; box-shadow:0 4px 8px rgba(0,0,0,.05); padding:30px 0; position:sticky; top:0; height:100vh; overflow-y:auto; }
    .sidebar-header{font-weight:700;font-size:24px;padding:0 25px 15px;border-bottom:1px solid #f0f0f0;text-align:center;color:#2C3E50}
    .admin-profile{display:flex;align-items:center;padding:20px 25px;border-bottom:1px solid #f0f0f0;margin-bottom:15px}
    .admin-avatar{width:50px;height:50px;border-radius:50%;background:#F39C12;display:flex;align-items:center;justify-content:center;margin-right:15px;font-size:20px;font-weight:600;color:#fff}
    .admin-info h4{margin:0;font-size:16px}.admin-info p{margin:3px 0 0;font-size:13px;opacity:.8}
    .menu-category{color:var(--primary-color);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:12px 25px;margin-top:15px}
    .menu-item{padding:12px 25px;display:flex;align-items:center;color:var(--dark);text-decoration:none;transition:.2s;font-size:15px;border-left:3px solid transparent}
    .menu-item i{margin-right:12px;width:20px;text-align:center}
    .menu-item:hover,.menu-item.active{background:#F0F7F7;color:var(--primary-color);border-left:3px solid var(--primary-color);transform:translateX(5px)}
    .main-content{padding:30px;background:var(--light);height:100vh;overflow-y:auto;display:flex;flex-direction:column}
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    .header h1{margin:0;font-size:28px;position:relative}
    .header h1:after{content:'';position:absolute;bottom:-5px;left:0;width:50px;height:3px;background:var(--primary-color);border-radius:3px}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:20px}
    .stat-card{background:#fff;border:1px solid var(--light-gray);border-radius:12px;padding:18px}
    .stat-card h3{margin:0 0 6px 0;font-size:14px;color:#666}
    .stat-card .value{font-size:26px;font-weight:700;color:#2C3E50}
    .content-card{background:#fff;border:1px solid var(--light-gray);border-radius:12px;padding:18px;margin-bottom:20px}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{padding:10px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
    .badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600}
    .badge.delivered{background:#e8f5e9;color:#2e7d32}.badge.cancelled{background:#fdecea;color:#c62828}.badge.pending{background:#fff3cd;color:#947600}
    footer{text-align:center;padding:20px;margin-top:20px;color:#777;font-size:13px;border-top:1px solid #eee}
    @media (max-width: 992px) { .dashboard-container{grid-template-columns:1fr}.sidebar{display:none} }
  </style>
</head>
<body>
<div class="dashboard-container">
  <div class="sidebar">
    <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp; Tiffinly</div>
    <div class="admin-profile">
      <div class="admin-avatar"><?php echo strtoupper(substr($user['name'] ?? 'P', 0, 1)); ?></div>
      <div class="admin-info">
        <h4><?php echo htmlspecialchars($user['name'] ?? 'Partner'); ?></h4>
        <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
      </div>
    </div>
    <div class="sidebar-menu">
      <a href="partner_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="partner_profile.php" class="menu-item"><i class="fas fa-user"></i> My Profile</a>
      <div class="menu-category">Manage Deliveries</div>
      <a href="available_orders.php" class="menu-item"><i class="fas fa-search"></i> Available Orders</a>
      <a href="my_deliveries.php" class="menu-item"><i class="fas fa-truck"></i> My Deliveries</a>
      <a href="delivery_history.php" class="menu-item"><i class="fas fa-history"></i> Delivery History</a>
      <a href="performance_review.php" class="menu-item active"><i class="fas fa-chart-line"></i> Performance Review</a>
      <a href="earnings.php" class="menu-item"><i class="fas fa-wallet"></i> Earnings & Incentives</a>
      <a href="log_issues.php" class="menu-item"><i class="fas fa-exclamation-triangle"></i> Log Issues</a>
      <div style="margin-top:30px"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
  </div>
  <div class="main-content">
    <div class="header">
      <div>
        <h1>Performance Review</h1>
        <p>Your delivery KPIs and recent activity.</p>
      </div>
    </div>

    <div class="stats">
      <div class="stat-card"><h3>All-time Delivered</h3><div class="value"><?php echo (int)($kpis['delivered_all'] ?? 0); ?></div></div>
      <div class="stat-card"><h3>All-time Cancelled</h3><div class="value"><?php echo (int)($kpis['cancelled_all'] ?? 0); ?></div></div>
      <div class="stat-card"><h3>In Progress</h3><div class="value"><?php echo (int)($kpis['in_progress_all'] ?? 0); ?></div></div>
      </div>

    <div class="content-card">
      <h3 style="margin-top:0">Breakdown by Meal Type</h3>
      <table class="table">
        <thead><tr><th>Meal Type</th><th>Delivered</th><th>Cancelled</th></tr></thead>
        <tbody>
        <?php if (empty($by_meal)): ?>
          <tr><td colspan="3" style="color:#777">No records found</td></tr>
        <?php else: foreach ($by_meal as $m): ?>
          <tr>
            <td><?php echo htmlspecialchars($m['meal_type']); ?></td>
            <td><?php echo (int)$m['delivered']; ?></td>
            <td><?php echo (int)$m['cancelled']; ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="content-card">
      <h3 style="margin-top:0">Customer Feedbacks</h3>
      <table class="table">
        <thead><tr><th>Customer</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
        <tbody>
          <?php if (empty($feedbacks)): ?>
            <tr><td colspan="4" style="color:#777">No customer feedback found.</td></tr>
          <?php else: foreach ($feedbacks as $f): ?>
            <tr>
              <td><?php echo htmlspecialchars($f['customer_name']); ?></td>
              <td>
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star" style="color: <?php echo $i <= $f['rating'] ? '#F39C12' : '#ddd'; ?>;"></i>
                <?php endfor; ?>
              </td>
              <td>
                <?php 
                  $comment = $f['comments'];
                  // Remove the partner prefix, e.g., "[Partner: Manoj #16 • 9470076894] "
                  $cleaned_comment = preg_replace('/^\[Partner:.*?\]\s*/', '', $comment);
                  echo htmlspecialchars($cleaned_comment); 
                ?>
              </td>
              <td><?php echo date('d M Y', strtotime($f['created_at'])); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <footer>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved. Partner Portal</footer>
  </div>
</div>
</body>
</html>
