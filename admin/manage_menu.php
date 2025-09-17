<?php
session_start();
// Admin auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// DB connection (match admin_dashboard.php style)
$db = new mysqli('localhost', 'root', '', 'tiffinly');
if ($db->connect_error) { die('Connection failed: ' . $db->connect_error); }

$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? '';

$alert = null; $alert_type = 'success';

// Helper: sanitize int
function to_int($v) { return intval($v ?? 0); }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $meal_name = trim($_POST['meal_name'] ?? '');
        $category_id = to_int($_POST['category_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        // Optional assignments to plans/days/slots
        // Single-select plan
        $assign_plan_id = isset($_POST['assign_plan_id']) ? intval($_POST['assign_plan_id']) : 0;
        $assign_plan_ids = $assign_plan_id > 0 ? [$assign_plan_id] : [];
        $assign_days = isset($_POST['assign_days']) && is_array($_POST['assign_days']) ? array_map('strtoupper', array_map('trim', $_POST['assign_days'])) : [];
        $assign_slots = isset($_POST['assign_slots']) && is_array($_POST['assign_slots']) ? array_map(function($v){ return strtolower(trim($v)); }, $_POST['assign_slots']) : [];

        // If category has a fixed slot, prefer it automatically
        $slot_from_category = null;
        $col = $db->query("SHOW COLUMNS FROM meal_categories LIKE 'slot'");
        if ($col && $col->num_rows > 0 && $category_id > 0) {
            $slot_stmt = $db->prepare('SELECT slot FROM meal_categories WHERE category_id = ?');
            $slot_stmt->bind_param('i', $category_id);
            $slot_stmt->execute();
            $slot_res = $slot_stmt->get_result();
            if ($slot_row = $slot_res->fetch_assoc()) {
                $sv = strtolower(trim($slot_row['slot'] ?? ''));
                if (in_array($sv, ['breakfast','lunch','dinner'], true)) {
                    $slot_from_category = $sv;
                }
            }
            $slot_stmt->close();
        }
        if ($slot_from_category) {
            $assign_slots = [$slot_from_category];
        }
        if ($meal_name === '' || $category_id <= 0) {
            $alert = 'Meal name and category are required.'; $alert_type = 'error';
        } else {
            // Do not insert image_url; rely on DB default/NULL
            $stmt = $db->prepare('INSERT INTO meals (category_id, meal_name, is_active) VALUES (?, ?, ?)');
            $stmt->bind_param('isi', $category_id, $meal_name, $is_active);
            if ($stmt->execute()) {
                $new_meal_id = $db->insert_id;
                // If admin selected plan/day/slot assignments, create plan_meals rows
                if (!empty($assign_plan_ids) && !empty($assign_days) && !empty($assign_slots)) {
                    $ins = $db->prepare('INSERT INTO plan_meals (plan_id, day_of_week, meal_type, meal_id) VALUES (?, ?, ?, ?)');
                    foreach ($assign_plan_ids as $pid) {
                        foreach ($assign_days as $day) {
                            // Expect day as MONDAY..SUNDAY
                            $day_norm = strtoupper(trim($day));
                            foreach ($assign_slots as $slot) {
                                // Expect slot as breakfast/lunch/dinner
                                $slot_norm = strtolower(trim($slot));
                                // Avoid duplicates: check existence
                                $chk = $db->prepare('SELECT 1 FROM plan_meals WHERE plan_id = ? AND day_of_week = ? AND meal_type = ? AND meal_id = ? LIMIT 1');
                                $chk->bind_param('issi', $pid, $day_norm, $slot_norm, $new_meal_id);
                                $chk->execute();
                                $exists = $chk->get_result()->fetch_row();
                                $chk->close();
                                if (!$exists) {
                                    $ins->bind_param('issi', $pid, $day_norm, $slot_norm, $new_meal_id);
                                    $ins->execute();
                                }
                            }
                        }
                    }
                    $ins->close();
                }
                $alert = 'Meal added successfully.'; $alert_type = 'success';
            } else { $alert = 'Failed to add meal: ' . $db->error; $alert_type = 'error'; }
            $stmt->close();
            // Redirect back with filters (PRG)
            $_SESSION['flash_msg'] = $alert; $_SESSION['flash_type'] = $alert_type;
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: manage_menu.php' . ($qs ? ('?' . $qs) : ''));
            exit();
        }
    }

    if ($action === 'update') {
        $meal_id = to_int($_POST['meal_id'] ?? 0);
        $meal_name = trim($_POST['meal_name'] ?? '');
        $category_id = to_int($_POST['category_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($meal_name === '' || $category_id <= 0) {
            $alert = 'Meal, name and category are required.'; $alert_type = 'error';
        } else {
            // Validate meal exists (allows id 0 if it exists)
            $exist = $db->prepare('SELECT 1 FROM meals WHERE meal_id = ? LIMIT 1');
            $exist->bind_param('i', $meal_id);
            $exist->execute();
            $exists = $exist->get_result()->fetch_row();
            $exist->close();
            if (!$exists) {
                $alert = 'Invalid meal selected.'; $alert_type = 'error';
            } else {
                // Do not update image_url
                $stmt = $db->prepare('UPDATE meals SET category_id = ?, meal_name = ?, is_active = ? WHERE meal_id = ?');
                $stmt->bind_param('isii', $category_id, $meal_name, $is_active, $meal_id);
                if ($stmt->execute()) { $alert = 'Meal updated successfully.'; $alert_type = 'success'; }
                else { $alert = 'Failed to update meal: ' . $db->error; $alert_type = 'error'; }
                $stmt->close();
            }
            $_SESSION['flash_msg'] = $alert; $_SESSION['flash_type'] = $alert_type;
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: manage_menu.php' . ($qs ? ('?' . $qs) : ''));
            exit();
        }
    }

    if ($action === 'delete') {
        $meal_id = to_int($_POST['meal_id'] ?? 0);
        // Validate meal exists (allows id 0 if it exists)
        $exist = $db->prepare('SELECT 1 FROM meals WHERE meal_id = ? LIMIT 1');
        $exist->bind_param('i', $meal_id);
        $exist->execute();
        $exists = $exist->get_result()->fetch_row();
        $exist->close();
        if (!$exists) { $alert = 'Invalid meal selected.'; $alert_type = 'error'; }
        else {
            // Optional: check if used in plan_meals and prevent or cascade manually
            $check = $db->prepare('SELECT COUNT(*) c FROM plan_meals WHERE meal_id = ?');
            $check->bind_param('i', $meal_id);
            $check->execute();
            $c = $check->get_result()->fetch_assoc()['c'] ?? 0;
            $check->close();
            if ($c > 0) {
                $alert = 'Cannot delete: meal is used in plan schedules. Remove from plans first.'; $alert_type = 'error';
            } else {
                $stmt = $db->prepare('DELETE FROM meals WHERE meal_id = ?');
                $stmt->bind_param('i', $meal_id);
                if ($stmt->execute()) { $alert = 'Meal deleted successfully.'; $alert_type = 'success'; }
                else { $alert = 'Failed to delete meal: ' . $db->error; $alert_type = 'error'; }
                $stmt->close();
            }
            $_SESSION['flash_msg'] = $alert; $_SESSION['flash_type'] = $alert_type;
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: manage_menu.php' . ($qs ? ('?' . $qs) : ''));
            exit();
        }
    }

    if ($action === 'toggle') {
        $meal_id = to_int($_POST['meal_id'] ?? 0);
        $to = to_int($_POST['to'] ?? 0) ? 1 : 0;
        // Validate meal exists (allows id 0 if it exists)
        $exist = $db->prepare('SELECT 1 FROM meals WHERE meal_id = ? LIMIT 1');
        $exist->bind_param('i', $meal_id);
        $exist->execute();
        $exists = $exist->get_result()->fetch_row();
        $exist->close();
        if ($exists) {
            $stmt = $db->prepare('UPDATE meals SET is_active = ? WHERE meal_id = ?');
            $stmt->bind_param('ii', $to, $meal_id);
            if ($stmt->execute()) { $alert = 'Status updated.'; $alert_type = 'success'; }
            else { $alert = 'Failed to update status.'; $alert_type = 'error'; }
            $stmt->close();
            $_SESSION['flash_msg'] = $alert; $_SESSION['flash_type'] = $alert_type;
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: manage_menu.php' . ($qs ? ('?' . $qs) : ''));
            exit();
        }
    }
}

// Fetch categories
$categories = [];
$res = $db->query("SELECT category_id, category_name, meal_type, option_type FROM meal_categories ORDER BY meal_type, option_type, category_name");
while ($row = $res->fetch_assoc()) { $categories[] = $row; }
$res->close();

// Fetch all plans for assignment UI in Add Meal
$plans_all = [];
$res = $db->query("SELECT plan_id, plan_name, plan_type, is_active FROM meal_plans ORDER BY plan_type, plan_name");
while ($row = $res->fetch_assoc()) { $plans_all[] = $row; }
$res->close();

// Pull any flash message from session
if (isset($_SESSION['flash_msg'])) {
    $alert = $_SESSION['flash_msg'];
    $alert_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Build filters from GET
$filter_meal_type   = trim($_GET['meal_type']   ?? ''); // e.g., veg, non-veg
$filter_option_type = trim($_GET['option_type'] ?? ''); // e.g., basic, premium
$filter_status      = isset($_GET['status']) ? trim($_GET['status']) : 'active'; // default to active to avoid flooding
$filter_category_id = intval($_GET['category_id'] ?? 0);
$filter_q           = trim($_GET['q'] ?? '');
$filters_applied    = isset($_GET['apply']);

// Derive distinct types from categories for dropdowns
$distinct_meal_types = array_values(array_unique(array_map(function($c){ return strtolower(trim($c['meal_type'])); }, $categories)));
$distinct_option_types = array_values(array_unique(array_map(function($c){ return strtolower(trim($c['option_type'])); }, $categories)));
sort($distinct_meal_types);
sort($distinct_option_types);

// Pagination
$per_page = max(10, min(100, intval($_GET['per_page'] ?? 50)));
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build reusable WHERE clause from filters
$where = ' WHERE 1=1';
$types = '';
$params = [];

if ($filter_meal_type !== '') { $where .= " AND LOWER(TRIM(c.meal_type)) = ?"; $types .= 's'; $params[] = strtolower($filter_meal_type); }
if ($filter_option_type !== '') { $where .= " AND LOWER(TRIM(c.option_type)) = ?"; $types .= 's'; $params[] = strtolower($filter_option_type); }
if ($filter_status === 'active') { $where .= " AND m.is_active = 1"; }
elseif ($filter_status === 'inactive') { $where .= " AND m.is_active = 0"; }
if ($filter_category_id > 0) { $where .= " AND c.category_id = ?"; $types .= 'i'; $params[] = $filter_category_id; }
if ($filter_q !== '') { $like = '%'.$filter_q.'%'; $where .= " AND (m.meal_name LIKE ? OR c.category_name LIKE ?)"; $types .= 'ss'; $params[] = $like; $params[] = $like; }

// Total count (only when filters are applied)
$total_rows = 0; $total_pages = 1;
if ($filters_applied) {
  $count_sql = "SELECT COUNT(*) as cnt FROM meals m JOIN meal_categories c ON m.category_id = c.category_id".$where;
  $stmt = $db->prepare($count_sql);
  if ($stmt && $types !== '') { $stmt->bind_param($types, ...$params); }
  if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $total_rows = intval($row['cnt'] ?? 0);
    $stmt->close();
  } else {
    $res = $db->query(str_replace('COUNT(*) as cnt','COUNT(*) as cnt',$count_sql));
    if ($res) { $row = $res->fetch_assoc(); $total_rows = intval($row['cnt'] ?? 0); }
  }
  $total_pages = max(1, (int)ceil($total_rows / $per_page));
  if ($offset >= $total_rows) { $page = 1; $offset = 0; }
}

// Fetch meals (paginated) only when filters are applied
$meals = [];
if ($filters_applied) {
  $data_sql = "SELECT m.meal_id, m.meal_name, m.is_active,
                      c.category_id, c.category_name, c.meal_type, c.option_type,
                      GROUP_CONCAT(DISTINCT p.plan_type) AS plan_types
               FROM meals m
               JOIN meal_categories c ON m.category_id = c.category_id
               LEFT JOIN plan_meals pm ON pm.meal_id = m.meal_id
               LEFT JOIN meal_plans p ON p.plan_id = pm.plan_id".$where.
              " GROUP BY m.meal_id, m.meal_name, m.is_active, c.category_id, c.category_name, c.meal_type, c.option_type
                ORDER BY c.option_type, c.meal_type, c.category_name, m.meal_name LIMIT ? OFFSET ?";
  $stmt = $db->prepare($data_sql);
  if ($stmt) {
    $types_data = $types . 'ii';
    $params_data = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types_data, ...$params_data);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $meals[] = $row; }
    $stmt->close();
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tiffinly - Manage Menu</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    
    :root { --primary-color:#2C7A7B; --secondary-color:#F39C12; --dark-color:#2C3E50; --light-color:#F9F9F9; --shadow-sm:0 1px 3px rgba(0,0,0,.12); --shadow-md:0 4px 6px rgba(0,0,0,.1);}    
    body{font-family:'Poppins',sans-serif;margin:0;background:var(--light-color);color:var(--dark-color);}    
    *, *::before, *::after{box-sizing:border-box}
    .dashboard-container{display:grid;grid-template-columns:280px 1fr;min-height:100vh;height:100vh;overflow:hidden}
    .sidebar{background:#fff;box-shadow:var(--shadow-md);padding:30px 0;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-header{font-weight:700;font-size:24px;padding:0 20px 12px;border-bottom:1px solid #f0f0f0;text-align:center;color:#2C3E50}
    .admin-profile{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid #f0f0f0;margin-bottom:10px}
    .admin-avatar{width:42px;height:42px;border-radius:50%;background:#F39C12;display:flex;align-items:center;justify-content:center;margin-right:12px;font-size:18px;font-weight:600;color:#fff}
    .admin-info h4{margin:0;font-size:15px}.admin-info p{margin:3px 0 0;font-size:12px;opacity:.8}
    .menu-category{color:var(--primary-color);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:10px 20px;margin-top:10px}
    .menu-item{padding:10px 20px;display:flex;align-items:center;color:var(--dark-color);text-decoration:none;transition:.2s;font-size:14px;border-left:3px solid transparent}
    .menu-item i{margin-right:10px;width:18px;text-align:center}
    .menu-item:hover,.menu-item.active{background:#F0F7F7;color:var(--primary-color);border-left:3px solid var(--primary-color);transform:translateX(3px)}
    .main-content{padding:22px;background:var(--light-color);min-height:100vh;overflow-y:auto;display:flex;flex-direction:column}
    .header{display:flex;justify-content:space-between;align-items:center;margin:0 auto 16px;max-width:1100px;width:100%}
    .header h1{margin:0;font-size:28px;position:relative}
    .header h1:after{content:'';position:absolute;bottom:-4px;left:0;width:50px;height:3px;background:var(--primary-color);border-radius:3px;transition:all .3s ease}
    .header:hover h1:after{width:100%}
    .header p{margin:5px 0 0;color:#777;font-size:16px;transition:all .3s ease}
    .header:hover p{color:var(--dark-color)}
    .content-card{background:#fff;border-radius:10px;padding:14px;box-shadow:var(--shadow-sm);border:1px solid #eee;margin:0 auto 14px;max-width:1100px;width:100%}
    /* Unified compact inputs */
    .row{display:grid;grid-template-columns:1.6fr 1fr 1.2fr .6fr;gap:12px;align-items:end}
    .form-grid{display:grid;gap:12px}
    .form-grid.add{grid-template-columns:2fr 1.4fr 1.6fr .6fr;align-items:end}
    /* Add Meal assignment layout */
    .assign-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:12px;align-items:start}
    .assign-col-left{display:grid;grid-template-columns:1fr;gap:12px}
    .days-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px;font-size:12px}
    .slots-grid{display:grid;grid-template-columns:1fr;gap:6px;font-size:12px}
    .form-actions{display:flex;justify-content:flex-end;margin-top:10px}
    select[multiple]{min-height:116px}
    .span-all{grid-column:1 / -1}
    .stack{display:grid;grid-template-columns:1fr;gap:6px}
    .w-100{width:100%}
    .input-sm, textarea.input-sm, select.input-sm{width:100%}
    .form-group{margin-bottom:8px}
    label{display:block;margin-bottom:4px;font-weight:600;font-size:12px}
    input[type=text],select,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;font-family:'Poppins';font-size:13px}
    /* Normalize control heights/alignment */
    input.input-sm[type=text]{height:34px;line-height:20px}
    select.input-sm{height:34px;line-height:20px;padding:6px 8px;font-size:12px;border-radius:6px}
    /* Ensure stacked inputs fill width in table edit form */
    .stack > input,.stack > select,.stack > textarea{width:100%}
    /* Align checkbox with label baseline */
    input[type=checkbox]{transform:translateY(1px)}
    textarea{min-height:36px}
    .input-sm{padding:6px;font-size:12px;border-radius:6px;height:34px}
    textarea.input-sm{height:34px;resize:vertical}
    .btn{background:#2C7A7B;color:#fff;border:none;border-radius:8px;padding:8px 10px;font-weight:600;cursor:pointer;font-size:13px}
    .btn.secondary{background:#6c757d}
    .btn.danger{background:#c0392b}
    .btn.sm{padding:5px 8px;font-size:12px;border-radius:6px;height:34px;display:inline-flex;align-items:center;gap:6px}
    .btn.group{margin-right:6px}
    .alert{padding:8px 10px;border-radius:8px;margin-bottom:10px;font-size:13px}
    .alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
    .alert.error{background:#fdecea;color:#c62828;border:1px solid #f5c6cb}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{border-bottom:1px solid #eee;padding:8px;text-align:left;font-size:13px;vertical-align:middle}
    th{color:#475569}
    .status-badge{display:inline-block;padding:2px 7px;border-radius:999px;font-size:11px}
    .status-badge.on{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
    .status-badge.off{background:#fff3cd;color:#856404;border:1px solid #ffeeba}
    .actions{display:flex;gap:6px;flex-wrap:wrap}
    footer{text-align:center;padding:12px;margin-top:auto;color:#777;font-size:13px;border-top:1px solid #eee}
    @media(max-width:1200px){.row{grid-template-columns:1fr 1fr 1fr .6fr 1fr .6fr}.form-grid.add{grid-template-columns:1.6fr 1fr 1.2fr .6fr}}
    @media(max-width:992px){.dashboard-container{grid-template-columns:1fr}.sidebar{display:none}.row{grid-template-columns:1fr 1fr}.main-content{padding:16px}}
  </style>
</head>
<body>
  <div class="dashboard-container">
    <div class="sidebar">
      <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp; Tiffinly</div>
      <div class="admin-profile">
        <div class="admin-avatar"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
        <div class="admin-info">
          <h4><?php echo htmlspecialchars($admin_name); ?></h4>
          <p><?php echo htmlspecialchars($admin_email); ?></p>
        </div>
      </div>
      <div class="sidebar-menu">
        <div class="menu-category">Dashboard & Overview</div>
        <a class="menu-item" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <div class="menu-category">Meal Management</div>
        <a class="menu-item active" href="manage_menu.php"><i class="fas fa-utensils"></i> Manage Menu</a>
        <a class="menu-item" href="manage_plans.php"><i class="fas fa-box"></i> Manage Plans</a>
        <a class="menu-item" href="manage_popular_meals.php"><i class="fas fa-star"></i> Manage Popular Meals</a>
        <div class="menu-category">User & Subscriptions</div>
        <a class="menu-item" href="manage_users.php"><i class="fas fa-users"></i> Users Data</a>
        <a class="menu-item" href="manage_subscriptions.php"><i class="fas fa-calendar-check"></i>Subscriptions Data</a>
        <div class="menu-category">Delivery & Partner Management</div>
        <a class="menu-item" href="manage_partners.php"><i class="fas fa-hands-helping"></i> Manage Partners</a>
        <a class="menu-item" href="manage_delivery.php"><i class="fas fa-truck"></i>  Delivery Data</a>
        <a class="menu-item" href="pending_delivery.php"><i class="fas fa-clock"></i> Pending Delivery</a>
        <div class="menu-category">Inquiry & Feedback</div>
        <a class="menu-item" href="manage_inquiries.php"><i class="fas fa-users"></i> Manage Inquiries</a>
        <a class="menu-item" href="view_feedback.php"><i class="fas fa-comment-alt"></i> View Feedback</a>
        <div style="margin-top:30px"><a class="menu-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
      </div>
    </div>

    <div class="main-content">
      <div class="header">
        <div>
          <h1>Manage Menu</h1>
          <p>Create, update, activate or delete meals. Changes reflect on user menus.</p>
        </div>
      </div>

      <div class="content-card">
        <?php if ($alert): ?>
          <div class="alert <?php echo $alert_type; ?>"><?php echo htmlspecialchars($alert); ?></div>
        <?php endif; ?>
        <h3 style="margin:0 0 8px 0">Add New Meal</h3>
        <form method="post">
          <input type="hidden" name="action" value="add">
          <div class="form-grid add">
            <div class="form-group">
              <label for="meal_name">Meal Name</label>
              <input class="input-sm w-100" type="text" id="meal_name" name="meal_name" placeholder="e.g., Paneer curry + roti" required>
            </div>
            <div class="form-group">
              <label for="category_id">Category</label>
              <select class="input-sm w-100" id="category_id" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo (int)$c['category_id']; ?>">
                    <?php echo htmlspecialchars($c['meal_type'].' • '.ucfirst($c['option_type']).' • '.$c['category_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="form-group">
              <label>Status</label>
              <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" id="is_active" name="is_active" checked> Active</label>
            </div>
          </div>

          <!-- Optional: Assign to Plans and Schedule now -->
          <div class="assign-grid" style="margin-top:10px;">
            <div class="assign-col-left">
              <div class="form-group">
                <label>Assign to Plan</label>
                <select class="input-sm w-100" name="assign_plan_id">
                  <?php foreach ($plans_all as $p): ?>
                    <option value="<?php echo (int)$p['plan_id']; ?>">
                      <?php echo ucfirst(htmlspecialchars($p['plan_type'])).' • '.htmlspecialchars($p['plan_name']).($p['is_active']? '':' (inactive)'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Days of Week</label>
                <div class="days-grid">
                  <?php foreach (['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'] as $d): ?>
                    <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="assign_days[]" value="<?php echo $d; ?>"> <?php echo ucfirst(strtolower($d)); ?></label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Meal Slots</label>
              <div class="slots-grid">
                <?php foreach (['breakfast','lunch','dinner'] as $s): ?>
                  <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="assign_slots[]" value="<?php echo $s; ?>"> <?php echo ucfirst($s); ?></label>
                <?php endforeach; ?>
              </div>
              <small style="color:#64748B">If the selected category has a slot set, that slot will be used automatically and ignore manual selection.</small>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn"><i class="fas fa-plus"></i> Add</button>
          </div>
        </form>
      </div>

      <div class="content-card">
        <h3 style="margin:0 0 8px 0">Existing Meals</h3>
        <form method="get" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1.5fr .5fr;gap:10px;align-items:end;margin-bottom:8px">
          <input type="hidden" name="apply" value="1">
          <div class="form-group">
            <label>Meal Type</label>
            <select class="input-sm" name="meal_type">
              <option value="">All</option>
              <?php foreach ($distinct_meal_types as $mt): ?>
                <option value="<?php echo htmlspecialchars($mt); ?>" <?php echo ($filter_meal_type === $mt)?'selected':''; ?>><?php echo ucfirst($mt); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Option</label>
            <select class="input-sm" name="option_type">
              <option value="">All</option>
              <?php foreach ($distinct_option_types as $ot): ?>
                <option value="<?php echo htmlspecialchars($ot); ?>" <?php echo ($filter_option_type === $ot)?'selected':''; ?>><?php echo ucfirst($ot); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select class="input-sm" name="status">
              <option value="" <?php echo ($filter_status==='')?'selected':''; ?>>All</option>
              <option value="active" <?php echo ($filter_status==='active')?'selected':''; ?>>Active</option>
              <option value="inactive" <?php echo ($filter_status==='inactive')?'selected':''; ?>>Inactive</option>
            </select>
          </div>
          <div class="form-group">
            <label>Category</label>
            <select class="input-sm" name="category_id">
              <option value="0">All Categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?php echo (int)$c['category_id']; ?>" <?php echo ($filter_category_id===(int)$c['category_id'])?'selected':''; ?>>
                  <?php echo htmlspecialchars($c['meal_type'].' • '.ucfirst($c['option_type']).' • '.$c['category_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Quick Search</label>
            <input class="input-sm" type="text" name="q" placeholder="Search meal or category..." value="<?php echo htmlspecialchars($filter_q); ?>">
          </div>
          <div class="form-group" style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn sm" type="submit"><i class="fas fa-search"></i> Filter</button>
            <a class="btn sm secondary" href="manage_menu.php">Reset</a>
          </div>
        </form>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:8px;flex-wrap:wrap">
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php 
              // Quick preset chips based on available values
              $has_basic = in_array('basic', $distinct_option_types);
              $has_premium = in_array('premium', $distinct_option_types);
              $has_veg = in_array('veg', $distinct_meal_types);
              $has_nonveg = in_array('non-veg', $distinct_meal_types) || in_array('nonveg', $distinct_meal_types);
              $nonveg_val = in_array('non-veg', $distinct_meal_types) ? 'non-veg' : (in_array('nonveg', $distinct_meal_types) ? 'nonveg' : 'non-veg');
              $base = ['apply'=>1,'status'=>$filter_status?:'active'];
            ?>
            <?php if ($has_basic && $has_veg): $qp = http_build_query($base + ['option_type'=>'basic','meal_type'=>'veg']); ?>
              <a class="btn sm secondary" href="manage_menu.php?<?php echo $qp; ?>">Basic • Veg</a>
            <?php endif; ?>
            <?php if ($has_basic && $has_nonveg): $qp = http_build_query($base + ['option_type'=>'basic','meal_type'=>$nonveg_val]); ?>
              <a class="btn sm secondary" href="manage_menu.php?<?php echo $qp; ?>">Basic • Non‑Veg</a>
            <?php endif; ?>
            <?php if ($has_premium && $has_veg): $qp = http_build_query($base + ['option_type'=>'premium','meal_type'=>'veg']); ?>
              <a class="btn sm secondary" href="manage_menu.php?<?php echo $qp; ?>">Premium • Veg</a>
            <?php endif; ?>
            <?php if ($has_premium && $has_nonveg): $qp = http_build_query($base + ['option_type'=>'premium','meal_type'=>$nonveg_val]); ?>
              <a class="btn sm secondary" href="manage_menu.php?<?php echo $qp; ?>">Premium • Non‑Veg</a>
            <?php endif; ?>
          </div>
          <span style="font-size:12px;color:#475569;background:#F1F5F9;padding:4px 8px;border-radius:999px">Results: <?php echo number_format($total_rows); ?><?php if($filters_applied): ?> • Page <?php echo $page; ?> of <?php echo $total_pages; ?><?php endif; ?></span>
        </div>
        <table>
          <colgroup>
            <col style=\"width:6%\">
            <col style=\"width:40%\">
            <col style=\"width:12%\">
            <col style=\"width:12%\">
            <col style=\"width:10%\">
            <col style=\"width:10%\">
            <col style=\"width:10%\">
          </colgroup>
          <thead>
            <tr>
              <th>ID</th>
              <th>Meal / Category</th>
              <th>Type</th>
              <th>Option</th>
              <th>In Plans</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$filters_applied): ?>
              <tr><td colspan=\"7\" style=\"color:#64748B\">Apply a filter or choose a preset above to view meals.</td></tr>
            <?php elseif (empty($meals)): ?>
              <tr><td colspan=\"7\" style=\"color:#777\">No meals found for the selected filters.</td></tr>
            <?php else: foreach ($meals as $m): ?>
              <tr>
                <td>#<?php echo (int)$m['meal_id']; ?></td>
                <td>
                  <form method="post" class="stack">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="meal_id" value="<?php echo (int)$m['meal_id']; ?>">
                    <input class="input-sm w-100" type="text" name="meal_name" value="<?php echo htmlspecialchars($m['meal_name']); ?>" required>
                    <select class="input-sm w-100" name="category_id" required>
                      <?php foreach ($categories as $c): $sel = ((int)$c['category_id'] === (int)$m['category_id']) ? 'selected' : ''; ?>
                        <option value="<?php echo (int)$c['category_id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['category_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="is_active" value="<?php echo (int)$m['is_active']; ?>">
                    <div class="actions">
                      <button class="btn sm group" type="submit">Save</button>
                    </div>
                  </form>
                </td>
                <td><?php echo htmlspecialchars($m['meal_type']); ?></td>
                <td><?php echo ucfirst(htmlspecialchars($m['option_type'])); ?></td>
                <td>
                  <?php
                    $types_list = array_filter(array_map('trim', explode(',', strtolower($m['plan_types'] ?? ''))));
                    $has_basic = in_array('basic', $types_list, true);
                    $has_premium = in_array('premium', $types_list, true);
                    if ($has_basic) {
                      echo '<span style="background:#eef2ff;color:#334155;border:1px solid #e5e7eb;border-radius:999px;padding:2px 6px;font-size:11px;margin-right:4px">Basic</span>';
                    }
                    if ($has_premium) {
                      echo '<span style="background:#fff7ed;color:#7c2d12;border:1px solid #ffedd5;border-radius:999px;padding:2px 6px;font-size:11px">Premium</span>';
                    }
                    if (!$has_basic && !$has_premium) {
                      echo '<span style="color:#64748B;font-size:12px">—</span>';
                    }
                  ?>
                </td>
                <td>
                  <span class="status-badge <?php echo $m['is_active']? 'on':'off'; ?>"><?php echo $m['is_active']? 'Active':'Inactive'; ?></span>
                </td>
                <td>
                  <div class="actions">
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="meal_id" value="<?php echo (int)$m['meal_id']; ?>">
                      <input type="hidden" name="to" value="<?php echo $m['is_active']? 0:1; ?>">
                      <button class="btn sm secondary" type="submit"><?php echo $m['is_active']? 'Deactivate':'Activate'; ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <!-- Pagination controls -->
        <?php if ($filters_applied): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:10px;flex-wrap:wrap">
          <div>
            <form method="get" style="display:flex;align-items:center;gap:6px">
              <?php foreach (["meal_type","option_type","status","category_id","q","apply"] as $k): if(isset($_GET[$k])): ?>
                <input type="hidden" name="<?php echo $k; ?>" value="<?php echo htmlspecialchars($_GET[$k]); ?>">
              <?php endif; endforeach; ?>
              <label style="font-size:12px;color:#475569">Per page</label>
              <select class="input-sm" name="per_page" onchange="this.form.submit()">
                <?php foreach ([10,25,50,75,100] as $pp): ?>
                  <option value="<?php echo $pp; ?>" <?php echo ($per_page==$pp)?'selected':''; ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <?php
              // Build base query string preserving filters and per_page
              $base_params = $_GET; $base_params['per_page'] = $per_page; $base_params['apply'] = 1;
              $qs_base = http_build_query($base_params);
              $prev_qs = $qs_base.'&page='.max(1,$page-1);
              $next_qs = $qs_base.'&page='.min($total_pages,$page+1);
            ?>
            <a class="btn sm secondary" href="manage_menu.php?<?php echo $prev_qs; ?>" style="pointer-events:<?php echo ($page<=1)?'none':'auto'; ?>;opacity:<?php echo ($page<=1)?'0.6':'1'; ?>">Prev</a>
            <span style="font-size:12px;color:#475569">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
            <a class="btn sm secondary" href="manage_menu.php?<?php echo $next_qs; ?>" style="pointer-events:<?php echo ($page>=$total_pages)?'none':'auto'; ?>;opacity:<?php echo ($page>=$total_pages)?'0.6':'1'; ?>">Next</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <footer>
        <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
      </footer>
    </div>
  </div>
</body>
</html>
