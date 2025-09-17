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

// Per-delivery rate
$RATE_PER_DELIVERY = 40.00; // currency units per delivered meal assignment

// Total delivered (all-time)
$q_all = $conn->prepare("SELECT COUNT(*) AS delivered_cnt FROM delivery_assignments WHERE partner_id = ? AND status = 'delivered'");
$q_all->bind_param('i', $partner_id);
$q_all->execute();
$delivered_all = ($q_all->get_result()->fetch_assoc()['delivered_cnt'] ?? 0);
$q_all->close();

// Total payouts received (This will now be the source for "Total Earnings")
$q_payouts = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM partner_payments WHERE partner_id = ? AND payment_status = 'success'");
$q_payouts->bind_param('i', $partner_id);
$q_payouts->execute();
$total_payouts = ($q_payouts->get_result()->fetch_assoc()['total'] ?? 0.0);
$q_payouts->close();
$total_earnings = $total_payouts; // Aligning total earnings with total payouts received

// Unpaid dues (delivered & unpaid assignments)
$q_unpaid = $conn->prepare("SELECT COUNT(*) AS cnt FROM delivery_assignments WHERE partner_id = ? AND status = 'delivered' AND payment_status = 'unpaid'");
$q_unpaid->bind_param('i', $partner_id);
$q_unpaid->execute();
$unpaid_cnt = ($q_unpaid->get_result()->fetch_assoc()['cnt'] ?? 0);
$q_unpaid->close();
$unpaid_due = $unpaid_cnt * $RATE_PER_DELIVERY;

// Active subscription orders count
$q_active = $conn->prepare("SELECT COUNT(DISTINCT subscription_id) as active_cnt FROM delivery_assignments WHERE partner_id = ? AND status IN ('pending', 'out_for_delivery')");
$q_active->bind_param('i', $partner_id);
$q_active->execute();
$active_orders_count = ($q_active->get_result()->fetch_assoc()['active_cnt'] ?? 0);
$q_active->close();

// Recent delivered list
$recent = [];
$q_recent = $conn->prepare("SELECT da.assignment_id, da.subscription_id, da.meal_type, da.delivery_date, s.schedule, mp.plan_name
  FROM delivery_assignments da JOIN subscriptions s ON da.subscription_id = s.subscription_id JOIN meal_plans mp ON s.plan_id = mp.plan_id
  WHERE da.partner_id = ? AND da.status = 'delivered'
  ORDER BY da.delivery_date DESC, da.assignment_id DESC LIMIT 15");
$q_recent->bind_param('i', $partner_id);
$q_recent->execute();
$r_recent = $q_recent->get_result();
while ($row = $r_recent->fetch_assoc()) { $recent[] = $row; }
$q_recent->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Earnings & Incentives - Tiffinly</title>
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
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:20px}
    .stat-card{background:#fff;border:1px solid var(--light-gray);border-radius:12px;padding:18px}
    .stat-card h3{margin:0 0 6px 0;font-size:14px;color:#666}
    .stat-card .value{font-size:26px;font-weight:700;color:#2C3E50}
    .content-card{background:#fff;border:1px solid var(--light-gray);border-radius:12px;padding:18px;margin-bottom:20px}
    .sub-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:10px 0 6px}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{padding:10px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
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
      <a href="performance_review.php" class="menu-item"><i class="fas fa-chart-line"></i> Performance Review</a>
      <a href="earnings.php" class="menu-item active"><i class="fas fa-wallet"></i> Earnings & Incentives</a>
      <a href="log_issues.php" class="menu-item"><i class="fas fa-exclamation-triangle"></i> Log Issues</a>
      <div style="margin-top:30px"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
  </div>
  <div class="main-content">
    <div class="header">
      <div>
        <h1>Earnings & Incentives</h1>
        <p>Overview of your delivered tasks and payouts.</p>
      </div>
    </div>

    <div class="stats">
      <div class="stat-card"><h3>Meals Delivered (All-time)</h3><div class="value"><?php echo (int)$delivered_all; ?></div></div>
      <div class="stat-card"><h3>Total Earnings (All-time)</h3><div class="value">₹<?php echo number_format($total_earnings, 2); ?></div></div>
      <div class="stat-card"><h3>My Active Orders</h3><div class="value"><?php echo (int)$active_orders_count; ?></div></div>
      </div>

    <div class="content-card">
      <h3 style="margin-top:0">Payouts & Dues</h3>
      <div class="sub-stats">
        <div class="stat-card"><h3>Unpaid Deliveries</h3><div class="value"><?php echo (int)$unpaid_cnt; ?></div></div>
        <div class="stat-card"><h3>Unpaid Due</h3><div class="value">₹<?php echo number_format($unpaid_due, 2); ?></div></div>
        <div class="stat-card"><h3>Total Payouts Received</h3><div class="value">₹<?php echo number_format((float)$total_payouts, 2); ?></div></div>
      </div>
    </div>

    <div class="content-card">
      <h3 style="margin-top:0">Recent Delivered Tasks</h3>
      <table class="table" id="recent-tasks-table">
        <thead><tr><th>Assignment ID</th><th>Subscription</th><th>Meal</th><th>Plan & Schedule</th><th>Delivered On</th></tr></thead>
        <tbody id="recent-tasks-body">
        <?php if (empty($recent)): ?>
          <tr><td colspan="5" style="color:#777">No delivered tasks yet</td></tr>
        <?php else: foreach ($recent as $index => $r): ?>
          <tr class="task-row" <?php if ($index >= 3) echo 'style="display:none;"'; ?>>
            <td>#<?php echo (int)$r['assignment_id']; ?></td>
            <td><?php echo (int)$r['subscription_id']; ?></td>
            <td><?php echo htmlspecialchars($r['meal_type']); ?></td>
            <td><?php echo htmlspecialchars($r['plan_name'] . ' (' . $r['schedule'] . ')'); ?></td>
            <td><?php echo $r['delivery_date'] ? date('d M Y', strtotime($r['delivery_date'])) : 'N/A'; ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php if (count($recent) > 3): ?>
        <button id="toggle-tasks-btn" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">Show More</button>
      <?php endif; ?>
    </div>

    <div class="content-card">
      <h3 style="margin-top:0">Past Payouts History</h3>
      <div class="table-container" style="overflow-x:auto;">
        <table class="payment-table" style="width:100%; border-collapse:separate; border-spacing:0 8px; background:#fff;">
          <thead>
            <tr style="background:#f8fafc;">
              <th style="padding:12px 18px; text-align:left; border-bottom:2px solid #e5e7eb;">Payout ID</th>
              <th style="padding:12px 18px; text-align:left; border-bottom:2px solid #e5e7eb;">Payment Date</th>
              <th style="padding:12px 18px; text-align:right; border-bottom:2px solid #e5e7eb;">Amount</th>
              <th style="padding:12px 18px; text-align:right; border-bottom:2px solid #e5e7eb;">Deliveries Paid For</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $recent_pay = [];
              $qp = $conn->prepare("SELECT payment_id, amount, delivery_count, created_at FROM partner_payments WHERE partner_id = ? AND payment_status = 'success' ORDER BY created_at DESC LIMIT 10");
              $qp->bind_param('i', $partner_id);
              $qp->execute();
              $rp = $qp->get_result();
              while ($row = $rp->fetch_assoc()) { $recent_pay[] = $row; }
              $qp->close();
            ?>
            <?php if (empty($recent_pay)): ?>
              <tr><td colspan="4" style="text-align:center;padding:20px;color:#666">No past payouts found.</td></tr>
            <?php else: $i=0; foreach ($recent_pay as $p): $i++; ?>
              <tr class="payout-row" style="<?php echo $i > 3 ? 'display:none;' : ''; ?> background:#f9fafb; border-radius:8px;">
                <td style="padding:10px 18px; border-bottom:1px solid #f1f5f9; border-radius:8px 0 0 8px;">#<?php echo htmlspecialchars($p['payment_id']); ?></td>
                <td style="padding:10px 18px; border-bottom:1px solid #f1f5f9;"><?php echo date('M d, Y h:i A', strtotime($p['created_at'])); ?></td>
                <td style="padding:10px 18px; text-align:right; border-bottom:1px solid #f1f5f9;">₹<?php echo number_format($p['amount'], 2); ?></td>
                <td style="padding:10px 18px; text-align:right; border-bottom:1px solid #f1f5f9; border-radius:0 8px 8px 0;"><?php echo (int)$p['delivery_count']; ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($recent_pay) > 3): ?>
        <button id="toggle-payouts-btn" class="btn btn-primary" style="margin-top: 15px;">Show More</button>
      <?php endif; ?>
    </div>

    <footer>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved. Partner Portal</footer>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Toggle for Past Payouts History
    const payoutBtn = document.getElementById('toggle-payouts-btn');
    if (payoutBtn) {
      payoutBtn.addEventListener('click', function() {
        const rows = document.querySelectorAll('.payout-row');
        let isShowingMore = this.textContent === 'Show More';
        for (let i = 3; i < rows.length; i++) {
          rows[i].style.display = isShowingMore ? 'table-row' : 'none';
        }
        this.textContent = isShowingMore ? 'Show Less' : 'Show More';
      });
    }
    // ...existing code for tasks toggle...
    const toggleBtn = document.getElementById('toggle-tasks-btn');
    if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const rows = document.querySelectorAll('#recent-tasks-body .task-row');
                const isShowingAll = this.textContent === 'Show Less';

                rows.forEach((row, index) => {
                    if (index >= 3) {
                        row.style.display = isShowingAll ? 'none' : 'table-row';
                    }
                });

                this.textContent = isShowingAll ? 'View More' : 'Show Less';
            });
        }
    });
  </script>
</div>
</body>
</html>
