<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
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

// Ensure issues table exists (safe no-op if already exists)
$conn->query("CREATE TABLE IF NOT EXISTS delivery_issues (
  issue_id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  subscription_id INT NOT NULL,
  partner_id INT NOT NULL,
  meal_type VARCHAR(50) DEFAULT NULL,
  issue_type VARCHAR(100) DEFAULT NULL,
  status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_partner_created (partner_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$alert = null; $alert_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'log_issue';

    if ($action === 'resolve_issue') {
        $issue_id_to_resolve = intval($_POST['issue_id'] ?? 0);
        if ($issue_id_to_resolve > 0) {
            $resolve_stmt = $conn->prepare("UPDATE delivery_issues SET status = 'resolved' WHERE issue_id = ? AND partner_id = ?");
            $resolve_stmt->bind_param("ii", $issue_id_to_resolve, $partner_id);
            if ($resolve_stmt->execute()) {
                $alert = 'Issue marked as resolved.';
            } else {
                $alert = 'Failed to resolve issue.';
                $alert_type = 'error';
            }
            $resolve_stmt->close();
        }
    } elseif ($action === 'log_issue') {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $issue_type = trim($_POST['issue_type'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($assignment_id <= 0 || $description === '') {
            $alert = 'Please select an assignment and provide a description.';
            $alert_type = 'error';
        } else {
            // Validate that assignment belongs to this partner and fetch needed fields
            $val = $conn->prepare("SELECT assignment_id, subscription_id, meal_type FROM delivery_assignments WHERE assignment_id = ? AND partner_id = ? LIMIT 1");
            $val->bind_param('ii', $assignment_id, $partner_id);
            $val->execute();
            $a = $val->get_result()->fetch_assoc();
            $val->close();

            if (!$a) {
                $alert = 'Invalid assignment selected.';
                $alert_type = 'error';
            } else {
                $ins = $conn->prepare("INSERT INTO delivery_issues (assignment_id, subscription_id, partner_id, meal_type, issue_type, description) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->bind_param('iiisss', $a['assignment_id'], $a['subscription_id'], $partner_id, $a['meal_type'], $issue_type, $description);
                if ($ins->execute()) {
                    $alert = 'Issue logged successfully.';
                    $alert_type = 'success';
                } else {
                    $alert = 'Failed to log issue. Please try again.';
                    $alert_type = 'error';
                }
                $ins->close();
            }
        }
    }
}

// Fetch active/recent subscriptions assigned to this partner for the dropdown
$subscriptions = [];
$as = $conn->prepare("SELECT DISTINCT s.subscription_id, u.name as user_name
  FROM delivery_assignments da 
  JOIN subscriptions s ON da.subscription_id = s.subscription_id
  JOIN users u ON s.user_id = u.user_id
  WHERE da.partner_id = ? ORDER BY s.subscription_id DESC");
$as->bind_param('i', $partner_id);
$as->execute();
$rs = $as->get_result();
while ($row = $rs->fetch_assoc()) { $subscriptions[] = $row; }
$as->close();

// Fetch recent issues
$issues = [];
$iq = $conn->prepare("SELECT i.issue_id, i.assignment_id, i.subscription_id, i.meal_type, i.issue_type, i.description, i.created_at, i.status
  FROM delivery_issues i WHERE i.partner_id = ? ORDER BY i.issue_id DESC LIMIT 20");
$iq->bind_param('i', $partner_id);
$iq->execute();
$ri = $iq->get_result();
while ($row = $ri->fetch_assoc()) { $issues[] = $row; }
$iq->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log Issues - Tiffinly</title>
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
    .header h1:after{content:'';position:absolute;bottom:-5px;left:0;width:50px;height:3px;background:var(--primary-color);border-radius:3px;transition: all 0.3s ease;}
    .header:hover h1:after {
        width: 100%;
    }
    .content-card{background:#fff;border:1px solid var(--light-gray);border-radius:12px;padding:18px;margin-bottom:20px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start}
    .form-group{margin-bottom:12px}
    label{display:block;margin-bottom:6px;font-weight:600;font-size:14px;color:#2C3E50}
    select,textarea,input[type=text]{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-family:'Poppins';font-size:14px}
    textarea{min-height:110px}
    /* Make the Description field span full width for better alignment */
    .form-row .form-group:nth-child(2){grid-column:1 / -1}
    .btn{background:#2C7A7B;color:#fff;border:none;border-radius:8px;padding:10px 14px;font-weight:600;cursor:pointer}
    .alert{padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:14px}
    .alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
    .alert.error{background:#fdecea;color:#c62828;border:1px solid #f5c6cb}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{padding:10px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
    footer{text-align:center;padding:20px;margin-top:20px;color:#777;font-size:13px;border-top:1px solid #eee}
    @media (max-width: 992px) { .dashboard-container{grid-template-columns:1fr}.sidebar{display:none} .form-row{grid-template-columns:1fr} }
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
      <a href="earnings.php" class="menu-item"><i class="fas fa-wallet"></i> Earnings & Incentives</a>
      <a href="log_issues.php" class="menu-item active"><i class="fas fa-exclamation-triangle"></i> Log Issues</a>
      <div style="margin-top:30px"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
  </div>
  <div class="main-content">
    <div class="header">
      <div>
        <h1>Log Issues</h1>
        <p>Report delivery problems for quick resolution.</p>
      </div>
    </div>

    <div class="content-card">
      <?php if ($alert): ?>
        <div class="alert <?php echo $alert_type; ?>"><?php echo htmlspecialchars($alert); ?></div>
      <?php endif; ?>
      <form method="POST" id="logIssueForm" action="log_issues.php">
        <input type="hidden" name="assignment_id" id="assignment_id" value="">
        <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr; align-items: end;">
            <div class="form-group">
                <label for="subscription_select">Subscription</label>
                <select id="subscription_select" required>
                    <option value="">-- Select a Subscription --</option>
                    <?php foreach ($subscriptions as $sub): ?>
                        <option value="<?php echo (int)$sub['subscription_id']; ?>">
                            #<?php echo (int)$sub['subscription_id']; ?> (<?php echo htmlspecialchars($sub['user_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_select">Delivery Date</label>
                <select id="date_select" required disabled><option value="">-- Select Subscription First --</option></select>
            </div>
            <div class="form-group">
                <label for="meal_select">Meal Type</label>
                <select id="meal_select" required disabled><option value="">-- Select Date First --</option></select>
            </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="issue_type">Issue Type (optional)</label>
            <input type="text" id="issue_type" name="issue_type" placeholder="e.g., Customer not available, Address issue, Item missing" />
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Describe the issue in detail..." required></textarea>
          </div>
        </div>
        <button type="submit" name="action" value="log_issue" class="btn"><i class="fas fa-paper-plane"></i> Submit Issue</button>
      </form>
    </div>

    <div class="content-card">
      <h3 style="margin-top:0">Recent Issues</h3>
      <table class="table">
        <thead><tr><th>ID</th><th>Assignment</th><th>Subscription</th><th>Meal</th><th>Type</th><th>Description</th><th>Logged At</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($issues)): ?>
            <tr><td colspan="9" style="color:#777">No issues logged yet</td></tr>
          <?php else: foreach ($issues as $i): ?>
            <tr>
              <td>#<?php echo (int)$i['issue_id']; ?></td>
              <td>#<?php echo (int)$i['assignment_id']; ?></td>
              <td><?php echo (int)$i['subscription_id']; ?></td>
              <td><?php echo htmlspecialchars($i['meal_type']); ?></td>
              <td><?php echo htmlspecialchars($i['issue_type'] ?: '-'); ?></td>
              <td style="max-width: 300px; white-space: normal;"><?php echo nl2br(htmlspecialchars($i['description'])); ?></td>
              <td><?php echo date('M d, Y, h:i A', strtotime($i['created_at'])); ?></td>
              <td><span class="badge" style="background-color: <?php echo $i['status'] === 'resolved' ? '#27ae60' : '#f39c12'; ?>; color: white;"><?php echo ucfirst($i['status']); ?></span></td>
              <td>
                <?php if ($i['status'] !== 'resolved'): ?>
                    <form method="POST" action="log_issues.php" style="display:inline;">
                        <input type="hidden" name="issue_id" value="<?php echo (int)$i['issue_id']; ?>">
                        <button type="submit" name="action" value="resolve_issue" class="btn sm" style="background-color: #27ae60;">Resolve</button>
                    </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <footer>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved. Partner Portal</footer>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const subSelect = document.getElementById('subscription_select');
        const dateSelect = document.getElementById('date_select');
        const mealSelect = document.getElementById('meal_select');
        const assignmentIdInput = document.getElementById('assignment_id');
        let assignmentsData = []; // To store fetched assignments

        subSelect.addEventListener('change', function() {
            const subId = this.value;
            dateSelect.innerHTML = '<option value="">Loading...</option>';
            dateSelect.disabled = true;
            mealSelect.innerHTML = '<option value="">-- Select Date First --</option>';
            mealSelect.disabled = true;
            assignmentIdInput.value = '';

            if (!subId) {
                dateSelect.innerHTML = '<option value="">-- Select Subscription First --</option>';
                return;
            }

            fetch(`../ajax/get_partner_assignments.php?subscription_id=${subId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignments.length > 0) {
                        assignmentsData = data.assignments;
                        const uniqueDates = [...new Set(assignmentsData.map(a => a.delivery_date))];
                        dateSelect.innerHTML = '<option value="">-- Select a Date --</option>';
                        uniqueDates.forEach(date => {
                            const option = document.createElement('option');
                            option.value = date;
                            option.textContent = new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                            dateSelect.appendChild(option);
                        });
                        dateSelect.disabled = false;
                    } else {
                        assignmentsData = [];
                        dateSelect.innerHTML = '<option value="">No assignments found</option>';
                    }
                });
        });

        dateSelect.addEventListener('change', function() {
            const selectedDate = this.value;
            mealSelect.innerHTML = '<option value="">-- Select Meal Type --</option>';
            mealSelect.disabled = true;
            assignmentIdInput.value = '';

            if (!selectedDate) return;

            const mealsForDate = assignmentsData.filter(a => a.delivery_date === selectedDate);
            if (mealsForDate.length > 0) {
                mealsForDate.forEach(meal => {
                    const option = document.createElement('option');
                    option.value = meal.assignment_id;
                    option.textContent = `${meal.meal_type} (${meal.status})`;
                    mealSelect.appendChild(option);
                });
                mealSelect.disabled = false;
            }
        });

        mealSelect.addEventListener('change', function() {
            assignmentIdInput.value = this.value;
        });
    });
    </script>
  </div>
</div>
</body>
</html>
