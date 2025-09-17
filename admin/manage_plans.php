<?php
session_start();
// Admin auth
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// DB connection
$db = new mysqli('localhost', 'root', '', 'tiffinly');
if ($db->connect_error) { die('Connection failed: ' . $db->connect_error); }

// Ensure auxiliary tables exist for images and features
$db->query("CREATE TABLE IF NOT EXISTS plan_images (
  image_id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  KEY(plan_id),
  KEY(sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->query("CREATE TABLE IF NOT EXISTS plan_features (
  feature_id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  feature_text VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  KEY(plan_id),
  KEY(sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure schedule options table exists (used for Weekdays/Extended/Full Week pricing and days)
$db->query("CREATE TABLE IF NOT EXISTS plan_schedule_options (
  schedule_id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  schedule_type ENUM('weekdays','extended','full_week') NOT NULL,
  description VARCHAR(255) NULL,
  days_count INT DEFAULT 0,
  price_multiplier DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  UNIQUE KEY uniq_plan_schedule (plan_id, schedule_type),
  KEY(plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? '';

// Helpers
function to_int($v) { return intval($v ?? 0); }
function to_dec($v) { return number_format((float)($v ?? 0), 2, '.', ''); }

$flash = null; $flash_type = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_plan') {
        $plan_name = trim($_POST['plan_name'] ?? '');
        $plan_type = trim($_POST['plan_type'] ?? 'basic');
        $description = trim($_POST['description'] ?? '');
        $base_price = (float)($_POST['base_price'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($plan_name === '' || !in_array($plan_type, ['basic','premium'])) {
            $flash = 'Plan name and type are required.'; $flash_type = 'error';
        } else {
            $stmt = $db->prepare('INSERT INTO meal_plans (plan_name, description, plan_type, is_active, base_price) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssid', $plan_name, $description, $plan_type, $is_active, $base_price);
            if ($stmt->execute()) {
                $new_plan_id = $db->insert_id;
                // Optional: initial schedules with fixed days (5,6,7); allow only multipliers from form
                $defs = [
                  'weekdays' => ['label' => 'Weekdays', 'days' => 5],
                  'extended' => ['label' => 'Extended Week', 'days' => 6],
                  'full_week' => ['label' => 'Full Week', 'days' => 7]
                ];
                foreach ($defs as $key => $cfg) {
                    $mult = isset($_POST[$key.'_mult_add']) && $_POST[$key.'_mult_add'] !== '' ? (float)$_POST[$key.'_mult_add'] : null;
                    if ($mult !== null) {
                        $ins = $db->prepare('INSERT INTO plan_schedule_options (plan_id, schedule_type, description, days_count, price_multiplier) VALUES (?, ?, NULL, ?, ?)');
                        $ins->bind_param('isid', $new_plan_id, $key, $cfg['days'], $mult);
                        $ins->execute();
                        $ins->close();
                    }
                }
                // Optional: initial image (URL or file upload)
                $img_url = trim($_POST['image_url_add'] ?? '');
                $img_sort = intval($_POST['image_sort_add'] ?? 0);
                if (isset($_FILES['image_file_add']) && is_array($_FILES['image_file_add']) && ($_FILES['image_file_add']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['image_file_add']['tmp_name'];
                    $name = $_FILES['image_file_add']['name'];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (in_array($ext, $allowed, true)) {
                        $uploadDir = __DIR__ . '/../user/assets/meals/';
                        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                        $newName = 'plan_' . $new_plan_id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (@move_uploaded_file($tmp, $dest)) {
                            $img_url = 'assets/meals/' . $newName; // relative to user page root
                        }
                    }
                }
                if ($img_url !== '') {
                    $ins = $db->prepare('INSERT INTO plan_images (plan_id, image_url, sort_order) VALUES (?, ?, ?)');
                    $ins->bind_param('isi', $new_plan_id, $img_url, $img_sort);
                    $ins->execute();
                    $ins->close();
                }
                // Optional: features (one per line)
                $features_text = trim($_POST['features_add'] ?? '');
                if ($features_text !== '') {
                    $lines = preg_split("/\r\n|\r|\n/", $features_text);
                    $ord = 0;
                    foreach ($lines as $line) {
                        $t = trim($line);
                        if ($t === '') continue;
                        $ins = $db->prepare('INSERT INTO plan_features (plan_id, feature_text, sort_order) VALUES (?, ?, ?)');
                        $ins->bind_param('isi', $new_plan_id, $t, $ord);
                        $ins->execute();
                        $ins->close();
                        $ord++;
                    }
                }
                $flash = 'Plan added.'; $flash_type = 'success';
            }
            else { $flash = 'Failed to add plan: '.$db->error; $flash_type = 'error'; }
            $stmt->close();
        }
    }

    if ($action === 'add_image') {
        $plan_id = to_int($_POST['plan_id'] ?? 0);
        $image_url = trim($_POST['image_url'] ?? '');
        $sort_order = to_int($_POST['sort_order'] ?? 0);
        // If a file is uploaded, prefer that
        if (isset($_FILES['image_file']) && is_array($_FILES['image_file']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image_file']['tmp_name'];
            $name = $_FILES['image_file']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed, true)) {
                $uploadDir = __DIR__ . '/../user/assets/meals/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $newName = 'plan_' . $plan_id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (@move_uploaded_file($tmp, $dest)) {
                    $image_url = 'assets/meals/' . $newName; // relative URL used by user pages
                }
            }
        }
        if ($plan_id > 0 && $image_url !== '') {
            $stmt = $db->prepare('INSERT INTO plan_images (plan_id, image_url, sort_order) VALUES (?, ?, ?)');
            $stmt->bind_param('isi', $plan_id, $image_url, $sort_order);
            if ($stmt->execute()) { $flash = 'Image added.'; $flash_type = 'success'; } else { $flash = 'Failed to add image.'; $flash_type = 'error'; }
            $stmt->close();
        }
    }

    if ($action === 'delete_image') {
        $image_id = to_int($_POST['image_id'] ?? 0);
        if ($image_id > 0) {
            $stmt = $db->prepare('DELETE FROM plan_images WHERE image_id = ?');
            $stmt->bind_param('i', $image_id);
            if ($stmt->execute()) { $flash = 'Image deleted.'; $flash_type = 'success'; } else { $flash = 'Failed to delete image.'; $flash_type = 'error'; }
            $stmt->close();
        }
    }

    if ($action === 'add_feature') {
        $plan_id = to_int($_POST['plan_id'] ?? 0);
        $feature_text = trim($_POST['feature_text'] ?? '');
        $sort_order = to_int($_POST['sort_order'] ?? 0);
        if ($plan_id > 0 && $feature_text !== '') {
            $stmt = $db->prepare('INSERT INTO plan_features (plan_id, feature_text, sort_order) VALUES (?, ?, ?)');
            $stmt->bind_param('isi', $plan_id, $feature_text, $sort_order);
            if ($stmt->execute()) { $flash = 'Feature added.'; $flash_type = 'success'; } else { $flash = 'Failed to add feature.'; $flash_type = 'error'; }
            $stmt->close();
        }
    }

    if ($action === 'delete_feature') {
        $feature_id = to_int($_POST['feature_id'] ?? 0);
        if ($feature_id > 0) {
            $stmt = $db->prepare('DELETE FROM plan_features WHERE feature_id = ?');
            $stmt->bind_param('i', $feature_id);
            if ($stmt->execute()) { $flash = 'Feature deleted.'; $flash_type = 'success'; } else { $flash = 'Failed to delete feature.'; $flash_type = 'error'; }
            $stmt->close();
        }
    }

    if ($action === 'update_plan') {
        $plan_id = to_int($_POST['plan_id'] ?? 0);
        $plan_name = trim($_POST['plan_name'] ?? '');
        $plan_type = trim($_POST['plan_type'] ?? 'basic');
        $description = trim($_POST['description'] ?? '');
        $base_price = (float)($_POST['base_price'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($plan_id <= 0 || $plan_name === '' || !in_array($plan_type, ['basic','premium'])) {
            $flash = 'Invalid plan data.'; $flash_type = 'error';
        } else {
            $stmt = $db->prepare('UPDATE meal_plans SET plan_name = ?, description = ?, plan_type = ?, is_active = ?, base_price = ? WHERE plan_id = ?');
            $stmt->bind_param('sssidi', $plan_name, $description, $plan_type, $is_active, $base_price, $plan_id);
            if ($stmt->execute()) { $flash = 'Plan updated.'; $flash_type = 'success'; }
            else { $flash = 'Failed to update plan: '.$db->error; $flash_type = 'error'; }
            $stmt->close();
        }
    }

    if ($action === 'delete_plan') {
        $plan_id = to_int($_POST['plan_id'] ?? 0);
        if ($plan_id <= 0) { $flash = 'Invalid plan.'; $flash_type = 'error'; }
        else {
            // Do not delete if referenced by subscriptions or plan_meals
            $check1 = $db->prepare('SELECT COUNT(*) c FROM subscriptions WHERE plan_id = ?');
            $check1->bind_param('i', $plan_id); $check1->execute(); $c1 = $check1->get_result()->fetch_assoc()['c'] ?? 0; $check1->close();
            $check2 = $db->prepare('SELECT COUNT(*) c FROM plan_meals WHERE plan_id = ?');
            $check2->bind_param('i', $plan_id); $check2->execute(); $c2 = $check2->get_result()->fetch_assoc()['c'] ?? 0; $check2->close();
            if (($c1 + $c2) > 0) { $flash = 'Cannot delete: plan has references. Remove usages first.'; $flash_type = 'error'; }
            else {
                // Clean up related data then delete the plan
                $db->query("DELETE FROM plan_schedule_options WHERE plan_id = ".$plan_id);
                $db->query("DELETE FROM plan_images WHERE plan_id = ".$plan_id);
                $db->query("DELETE FROM plan_features WHERE plan_id = ".$plan_id);
                $stmt = $db->prepare('DELETE FROM meal_plans WHERE plan_id = ?');
                $stmt->bind_param('i', $plan_id);
                if ($stmt->execute()) { $flash = 'Plan deleted.'; $flash_type = 'success'; }
                else { $flash = 'Failed to delete plan: '.$db->error; $flash_type = 'error'; }
                $stmt->close();
            }
        }
    }

    if ($action === 'save_schedule') {
        $plan_id = to_int($_POST['plan_id'] ?? 0);
        if ($plan_id > 0) {
            $map = [
                'weekdays' => ['label' => 'Weekdays'],
                'extended' => ['label' => 'Extended Week'],
                'full_week' => ['label' => 'Full Week']
            ];
            foreach ($map as $key => $_) {
                $days = to_int($_POST[$key.'_days'] ?? 0);
                $mult = (float)($_POST[$key.'_mult'] ?? 1.00);
                // Upsert schedule option
                $sel = $db->prepare('SELECT schedule_id FROM plan_schedule_options WHERE plan_id = ? AND schedule_type = ?');
                $sel->bind_param('is', $plan_id, $key);
                $sel->execute(); $res = $sel->get_result(); $row = $res->fetch_assoc(); $sel->close();
                if ($row) {
                    $sid = (int)$row['schedule_id'];
                    $upd = $db->prepare('UPDATE plan_schedule_options SET days_count = ?, price_multiplier = ? WHERE schedule_id = ?');
                    $upd->bind_param('idi', $days, $mult, $sid);
                    $upd->execute(); $upd->close();
                } else {
                    $ins = $db->prepare('INSERT INTO plan_schedule_options (plan_id, schedule_type, description, days_count, price_multiplier) VALUES (?, ?, NULL, ?, ?)');
                    $ins->bind_param('isid', $plan_id, $key, $days, $mult);
                    $ins->execute(); $ins->close();
                }
            }
            $flash = 'Schedule options saved.'; $flash_type = 'success';
        }
    }

    if ($action === 'reset_schedule_defaults') {
        $plan_id = to_int($_POST['plan_id'] ?? 0);
        if ($plan_id > 0) {
            $defaults = [
                'weekdays' => ['days'=>5, 'mult'=>1.00],
                'extended' => ['days'=>6, 'mult'=>1.20],
                'full_week' => ['days'=>7, 'mult'=>1.75],
            ];
            foreach ($defaults as $key => $vals) {
                // Upsert with defaults
                $sel = $db->prepare('SELECT schedule_id FROM plan_schedule_options WHERE plan_id = ? AND schedule_type = ?');
                $sel->bind_param('is', $plan_id, $key);
                $sel->execute(); $res = $sel->get_result(); $row = $res->fetch_assoc(); $sel->close();
                if ($row) {
                    $sid = (int)$row['schedule_id'];
                    $upd = $db->prepare('UPDATE plan_schedule_options SET days_count = ?, price_multiplier = ? WHERE schedule_id = ?');
                    $upd->bind_param('idi', $vals['days'], $vals['mult'], $sid);
                    $upd->execute(); $upd->close();
                } else {
                    $ins = $db->prepare('INSERT INTO plan_schedule_options (plan_id, schedule_type, description, days_count, price_multiplier) VALUES (?, ?, NULL, ?, ?)');
                    $ins->bind_param('isid', $plan_id, $key, $vals['days'], $vals['mult']);
                    $ins->execute(); $ins->close();
                }
            }
            $flash = 'Reset to standard defaults applied.'; $flash_type = 'success';
        }
    }

    // Redirect to avoid resubmission
    $_SESSION['flash_msg'] = $flash; $_SESSION['flash_type'] = $flash_type;
    header('Location: manage_plans.php');
    exit();
}

// Pull any flash
if (isset($_SESSION['flash_msg'])) { $flash = $_SESSION['flash_msg']; $flash_type = $_SESSION['flash_type'] ?? 'success'; unset($_SESSION['flash_msg'], $_SESSION['flash_type']); }

// Fetch plans
$plans = [];
$res = $db->query('SELECT plan_id, plan_name, description, plan_type, is_active, base_price FROM meal_plans ORDER BY plan_type, plan_name');
while ($row = $res->fetch_assoc()) { $plans[] = $row; }
$res->close();

// Fetch schedule options per plan
$schedules = [];
if (!empty($plans)) {
    $plan_ids = array_map(fn($p)=> (int)$p['plan_id'], $plans);
    $in = implode(',', array_fill(0, count($plan_ids), '?'));
    // mysqli doesn't support binding list directly in prepare for IN clause; fetch all and bucket
    $res = $db->query('SELECT schedule_id, plan_id, schedule_type, days_count, price_multiplier FROM plan_schedule_options');
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['plan_id'];
        if (!isset($schedules[$pid])) $schedules[$pid] = [];
        $schedules[$pid][$row['schedule_type']] = $row;
    }
    $res->close();
}

// Seed missing standard schedules for existing plans (non-destructive: only inserts if a schedule row is missing)
if (!empty($plans)) {
    $seed = [
        'weekdays' => ['days'=>5, 'mult'=>1.00],
        'extended' => ['days'=>6, 'mult'=>1.20],
        'full_week' => ['days'=>7, 'mult'=>1.75],
    ];
    foreach ($plans as $p) {
        $pid = (int)$p['plan_id'];
        $have = $schedules[$pid] ?? [];
        foreach ($seed as $key=>$vals) {
            if (!isset($have[$key])) {
                $ins = $db->prepare('INSERT INTO plan_schedule_options (plan_id, schedule_type, description, days_count, price_multiplier) VALUES (?, ?, NULL, ?, ?)');
                $ins->bind_param('isid', $pid, $key, $vals['days'], $vals['mult']);
                $ins->execute();
                $ins->close();
                // reflect seeded value in current render
                if (!isset($schedules[$pid])) { $schedules[$pid] = []; }
                $schedules[$pid][$key] = [
                    'schedule_id' => null,
                    'plan_id' => $pid,
                    'schedule_type' => $key,
                    'days_count' => $vals['days'],
                    'price_multiplier' => $vals['mult']
                ];
            }
        }
    }
}

// Normalize days_count for all existing schedule rows to fixed values (Weekdays=5, Extended=6, Full Week=7)
// This ensures consistency with the new policy where only multipliers vary and days are fixed.
@$db->query("UPDATE plan_schedule_options SET days_count = 5 WHERE schedule_type = 'weekdays' AND days_count <> 5");
@$db->query("UPDATE plan_schedule_options SET days_count = 6 WHERE schedule_type = 'extended' AND days_count <> 6");
@$db->query("UPDATE plan_schedule_options SET days_count = 7 WHERE schedule_type = 'full_week' AND days_count <> 7");

// Normalize multipliers to defaults for consistency on existing data
@$db->query("UPDATE plan_schedule_options SET price_multiplier = 1.00 WHERE schedule_type = 'weekdays' AND price_multiplier <> 1.00");
@$db->query("UPDATE plan_schedule_options SET price_multiplier = 1.20 WHERE schedule_type = 'extended' AND price_multiplier <> 1.20");
@$db->query("UPDATE plan_schedule_options SET price_multiplier = 1.75 WHERE schedule_type = 'full_week' AND price_multiplier <> 1.75");

// Fetch images and features per plan (after $plans is populated)
$images = []; $features = [];
if (!empty($plans)) {
    $ids = array_map(fn($p)=> (int)$p['plan_id'], $plans);
    $id_list = implode(',', $ids);
    if ($id_list !== '') {
        $res = $db->query('SELECT image_id, plan_id, image_url, sort_order FROM plan_images WHERE plan_id IN ('.$id_list.') ORDER BY sort_order, image_id');
        if ($res) { while ($row = $res->fetch_assoc()) { $pid=(int)$row['plan_id']; if(!isset($images[$pid])) $images[$pid]=[]; $images[$pid][]=$row; } $res->close(); }
        $res = $db->query('SELECT feature_id, plan_id, feature_text, sort_order FROM plan_features WHERE plan_id IN ('.$id_list.') ORDER BY sort_order, feature_id');
        if ($res) { while ($row = $res->fetch_assoc()) { $pid=(int)$row['plan_id']; if(!isset($features[$pid])) $features[$pid]=[]; $features[$pid][]=$row; } $res->close(); }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tiffinly - Manage Plans</title>
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
    .content-card{background:#fff;border-radius:10px;padding:14px;box-shadow:var(--shadow-sm);border:1px solid #eee;margin:0 auto 14px;max-width:1280px;width:100%}
    .form-grid{display:grid;gap:12px}
    .form-grid.add{grid-template-columns:1.2fr 1fr .6fr .6fr}
    .span-all{grid-column:1 / -1}
    label{display:block;margin-bottom:4px;font-weight:600;font-size:12px}
    input[type=text],input[type=number],select,textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-family:'Poppins';font-size:15px}
    textarea{min-height:140px}
    .btn{background:#2C7A7B;color:#fff;border:none;border-radius:8px;padding:8px 10px;font-weight:600;cursor:pointer;font-size:13px}
    .btn.secondary{background:#6c757d}
    .btn.danger{background:#c0392b}
    .btn.sm{padding:5px 8px;font-size:12px;border-radius:6px}
    .alert{padding:8px 10px;border-radius:8px;margin-bottom:10px;font-size:13px}
    .alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
    .alert.error{background:#fdecea;color:#c62828;border:1px solid #f5c6cb}
    /* Card grid for existing plans */
    .plans-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
    .plan-card{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .plan-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
    .plan-card-title{display:flex;gap:8px;align-items:center}
    .type-badge{background:#F1F5F9;color:#334155;border:1px solid #E2E8F0;border-radius:999px;font-size:11px;padding:2px 8px}
    .status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
    .status-active{background:#2e7d32}
    .status-inactive{background:#c62828}
    .price-chip{background:#FFF7ED;color:#9A3412;border:1px solid #FED7AA;border-radius:8px;padding:4px 8px;font-size:12px}
    .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    /* Features list alignment */
    .features-list{margin:0 0 8px 0;padding:0;list-style:none}
    .features-list li{display:flex;align-items:center;gap:8px;padding-left:18px;position:relative;margin-bottom:4px}
    .features-list li:before{content:'\2022';position:absolute;left:0;top:0;color:#334155;font-size:18px;line-height:1}
    .features-list form{margin-left:auto;display:inline}
    footer{text-align:center;padding:12px;margin-top:auto;color:#777;font-size:13px;border-top:1px solid #eee}
    @media(max-width:992px){.dashboard-container{grid-template-columns:1fr}.sidebar{display:none}.main-content{padding:16px}.grid-3{grid-template-columns:1fr}.plans-grid{grid-template-columns:1fr}}
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
        <a class="menu-item" href="manage_menu.php"><i class="fas fa-utensils"></i> Manage Menu</a>
        <a class="menu-item active" href="manage_plans.php"><i class="fas fa-box"></i> Manage Plans</a>
        <a class="menu-item" href="manage_popular_meals.php"><i class="fas fa-star"></i> Manage Popular Meals</a>
        <div class="menu-category">User & Subscriptions</div>
        <a class="menu-item" href="manage_users.php"><i class="fas fa-users"></i> Users Data</a>
        <a class="menu-item" href="manage_subscriptions.php"><i class="fas fa-calendar-check"></i> Subscriptions Data</a>
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
          <h1>Manage Plans</h1>
          <p>Create, update, activate and price plans. Changes reflect on user Browse Plans.</p>
        </div>
      </div>

      <div class="content-card">
        <?php if ($flash): ?>
          <div class="alert <?php echo $flash_type; ?>"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>
        <h3 style="margin:0 0 8px 0">Add New Plan</h3>
        <form method="post" class="form-grid add" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_plan">
          <div>
            <label>Plan Name</label>
            <input type="text" name="plan_name" placeholder="e.g., Basic" required>
          </div>
          <div>
            <label>Type</label>
            <select name="plan_type" required>
              <option value="basic">Basic</option>
              <option value="premium">Premium</option>
            </select>
          </div>
          <div>
            <label>Base Price (₹)</label>
            <input type="number" name="base_price" min="0" step="1" placeholder="1999" required>
          </div>
          <div>
            <label>Status</label>
            <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" checked> Active</label>
          </div>
          <div class="span-all">
            <label>Description</label>
            <textarea name="description" placeholder="Short description..."></textarea>
          </div>
          <div class="span-all" style="border-top:1px dashed #e5e7eb;padding-top:10px">
            <h4 style="margin:6px 0 8px 0;font-size:14px">Initial Price Multipliers (optional) — Days are preset (Weekdays=5, Extended=6, Full Week=7)</h4>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px">
                <strong style="font-size:13px">Weekdays (5 days)</strong>
                <div style="display:grid;grid-template-columns:1fr;gap:8px;margin-top:6px">
                  <div>
                    <label style="font-size:11px">Price x</label>
                    <input type="number" step="0.01" min="0" name="weekdays_mult_add" placeholder="1.00">
                  </div>
                </div>
              </div>
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px">
                <strong style="font-size:13px">Extended Week (6 days)</strong>
                <div style="display:grid;grid-template-columns:1fr;gap:8px;margin-top:6px">
                  <div>
                    <label style="font-size:11px">Price x</label>
                    <input type="number" step="0.01" min="0" name="extended_mult_add" placeholder="1.20">
                  </div>
                </div>
              </div>
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px">
                <strong style="font-size:13px">Full Week (7 days)</strong>
                <div style="display:grid;grid-template-columns:1fr;gap:8px;margin-top:6px">
                  <div>
                    <label style="font-size:11px">Price x</label>
                    <input type="number" step="0.01" min="0" name="full_week_mult_add" placeholder="1.75">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="span-all" style="border-top:1px dashed #e5e7eb;padding-top:10px">
            <h4 style="margin:6px 0 8px 0;font-size:14px">Initial Image (optional)</h4>
            <div style="display:grid;grid-template-columns:2fr .6fr;gap:8px;align-items:end">
              <div>
                <label>Image URL</label>
                <input type="text" name="image_url_add" placeholder="https://...">
              </div>
              <div>
                <label>Sort</label>
                <input type="number" name="image_sort_add" value="0" min="0">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr;gap:8px;margin-top:8px">
              <div>
                <label>Or Upload Image</label>
                <input type="file" name="image_file_add" accept="image/*">
              </div>
            </div>
          </div>
          <div class="span-all" style="border-top:1px dashed #e5e7eb;padding-top:10px">
            <h4 style="margin:6px 0 8px 0;font-size:14px">Initial Features (optional)</h4>
            <label>One per line</label>
            <textarea name="features_add" placeholder="e.g., Freshly cooked daily&#10;e.g., Free delivery on full week" rows="3"></textarea>
          </div>
          <div style="grid-column:1 / -1; justify-self:end; display:flex; justify-content:flex-end; margin-top:8px">
            <button class="btn" type="submit"><i class="fas fa-plus"></i> Add</button>
          </div>
        </form>
      </div>

      <div class="content-card">
        <h3 style="margin:0 0 10px 0">Existing Plans</h3>
        <?php if (empty($plans)): ?>
          <div style="color:#777">No plans found. Add one above.</div>
        <?php else: ?>
          <div class="plans-grid">
            <?php foreach ($plans as $p): $pid=(int)$p['plan_id']; $sc=$schedules[$pid] ?? []; ?>
              <div class="plan-card">
                <div class="plan-card-header">
                  <div class="plan-card-title">
                    <span class="status-dot <?php echo $p['is_active']? 'status-active':'status-inactive'; ?>"></span>
                    <strong><?php echo htmlspecialchars($p['plan_name']); ?></strong>
                    <span class="type-badge">ID: <?php echo $pid; ?></span>
                    <span class="type-badge"><?php echo ucfirst(htmlspecialchars($p['plan_type'])); ?></span>
                  </div>
                  <div class="price-chip">Base: ₹<?php echo number_format((float)$p['base_price'],0); ?></div>
                </div>
                <div style="font-size:12px;color:#6b7280;margin-bottom:8px"><?php echo htmlspecialchars($p['description']); ?></div>

                <div class="grid-3">
                  <form method="post" class="span-all">
                    <input type="hidden" name="action" value="update_plan">
                    <input type="hidden" name="plan_id" value="<?php echo $pid; ?>">
                    <div style="display:grid;grid-template-columns:1fr;gap:10px">
                      <div>
                        <label>Name</label>
                        <input type="text" name="plan_name" value="<?php echo htmlspecialchars($p['plan_name']); ?>" required>
                      </div>
                      <div>
                        <label>Type</label>
                        <select name="plan_type">
                          <option value="basic" <?php echo ($p['plan_type']==='basic')?'selected':''; ?>>Basic</option>
                          <option value="premium" <?php echo ($p['plan_type']==='premium')?'selected':''; ?>>Premium</option>
                        </select>
                      </div>
                      <div>
                        <label>Base Price (₹)</label>
                        <input type="number" name="base_price" min="0" step="1" value="<?php echo (float)$p['base_price']; ?>">
                      </div>
                      <div>
                        <label>Status</label>
                        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" <?php echo $p['is_active']? 'checked':''; ?>> Active</label>
                      </div>
                      <div>
                        <label>Description</label>
                        <textarea name="description" placeholder="Description..." rows="6"><?php echo htmlspecialchars($p['description']); ?></textarea>
                      </div>
                      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                        <button class="btn sm" type="submit" name="action" value="update_plan">Save</button>
                        <button class="btn sm danger" type="submit" name="action" value="delete_plan" onclick="return confirm('Delete this plan? This cannot be undone.');"><i class="fas fa-trash"></i> Delete</button>
                      </div>
                    </div>
                  </form>

                  <!-- Schedule editing is disabled for existing plans; days are preset and multipliers are defined at plan creation. -->

                  <div class="span-all" style="border-top:1px dashed #e5e7eb;padding-top:10px">
                    <h4 style="margin:6px 0 8px 0;font-size:14px">Plan Images</h4>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
                      <?php $imgs = $images[$pid] ?? []; if (empty($imgs)): ?>
                        <div style="font-size:12px;color:#6b7280">No images yet.</div>
                      <?php else: foreach ($imgs as $im): ?>
                        <?php $u = $im['image_url']; $displayUrl = (strpos($u,'assets/')===0) ? ('../user/'.$u) : $u; ?>
                        <div style="border:1px solid #E2E8F0;border-radius:8px;padding:6px;display:flex;align-items:center;gap:10px">
                          <img src="<?php echo htmlspecialchars($displayUrl); ?>" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:6px">
                          <div style="display:flex;flex-direction:column;gap:4px;max-width:360px">
                            <a href="<?php echo htmlspecialchars($displayUrl); ?>" target="_blank" style="font-size:12px;color:#2563EB;word-break:break-all;text-decoration:none">
                              <?php echo htmlspecialchars($u); ?>
                            </a>
                            <div style="font-size:11px;color:#6b7280">Sort: <?php echo (int)$im['sort_order']; ?></div>
                          </div>
                          <form method="post" onsubmit="return confirm('Remove this image?');">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?php echo (int)$im['image_id']; ?>">
                            <button class="btn sm danger" type="submit"><i class="fas fa-trash"></i></button>
                          </form>
                        </div>
                      <?php endforeach; endif; ?>
                    </div>
                    <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:2fr .6fr 1.2fr auto;gap:10px;align-items:end">
                      <input type="hidden" name="action" value="add_image">
                      <input type="hidden" name="plan_id" value="<?php echo $pid; ?>">
                      <div>
                        <label>Image URL</label>
                        <input type="text" name="image_url" placeholder="https://...">
                      </div>
                      <div>
                        <label>Sort</label>
                        <input type="number" name="sort_order" value="0" min="0">
                      </div>
                      <div>
                        <label>Or Upload</label>
                        <input type="file" name="image_file" accept="image/*">
                      </div>
                      <div style="grid-column:1 / -1; justify-self:end; display:flex; justify-content:flex-end; margin-top:2px">
                        <button class="btn sm" type="submit"><i class="fas fa-plus"></i> Add Image</button>
                      </div>
                    </form>
                  </div>

                  <div class="span-all" style="border-top:1px dashed #e5e7eb;padding-top:10px">
                    <h4 style="margin:6px 0 8px 0;font-size:14px">Plan Features</h4>
                    <ul class="features-list">
                      <?php $fts = $features[$pid] ?? []; if (empty($fts)): ?>
                        <li style="font-size:12px;color:#6b7280"><span>No features yet.</span></li>
                      <?php else: foreach ($fts as $ft): ?>
                        <li>
                          <span class="feature-text"><?php echo htmlspecialchars($ft['feature_text']); ?></span>
                          <form method="post" onsubmit="return confirm('Remove this feature?');">
                            <input type="hidden" name="action" value="delete_feature">
                            <input type="hidden" name="feature_id" value="<?php echo (int)$ft['feature_id']; ?>">
                            <button class="btn sm danger" type="submit"><i class="fas fa-trash"></i></button>
                          </form>
                        </li>
                      <?php endforeach; endif; ?>
                    </ul>
                    <form method="post" style="display:grid;grid-template-columns:2fr .6fr auto;gap:10px;align-items:end">
                      <input type="hidden" name="action" value="add_feature">
                      <input type="hidden" name="plan_id" value="<?php echo $pid; ?>">
                      <div>
                        <label>Feature</label>
                        <input type="text" name="feature_text" placeholder="e.g., Premium packaging" required>
                      </div>
                      <div>
                        <label>Sort</label>
                        <input type="number" name="sort_order" value="0" min="0">
                      </div>
                      <div style="justify-self:end; display:flex; justify-content:flex-end; margin-top:2px">
                        <button class="btn sm" type="submit"><i class="fas fa-plus"></i> Add Feature</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <footer>
        <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
      </footer>
    </div>
  </div>

<script>
  (function(){
    document.querySelectorAll('[data-filebox]').forEach(function(box){
      var input = box.querySelector('[data-fileinput]');
      var btn = box.querySelector('.file-trigger');
      var nameEl = box.querySelector('[data-filename]');
      if(btn && input){
        btn.addEventListener('click', function(){ input.click(); });
      }
      if(input && nameEl){
        input.addEventListener('change', function(){
          nameEl.textContent = (input.files && input.files[0]) ? input.files[0].name : 'No file chosen';
        });
      }
    });
  })();
  </script>
</body>
</html>
