<?php
session_start();
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php'; // provides $conn

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS popular_meals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  meal_id INT NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  description TEXT NULL,
  plan_type ENUM('basic','premium') NOT NULL,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (meal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$errors = [];
$messages = [];

function sanitize_text($s){ return trim($s ?? ''); }

// Admin info for sidebar
$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? '';

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'add') {
    $meal_id = (int)($_POST['meal_id'] ?? 0);
    $plan_type = strtolower(sanitize_text($_POST['plan_type'] ?? ''));
    $description = sanitize_text($_POST['description'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (!in_array($plan_type, ['basic','premium'], true)) { $errors[] = 'Invalid plan type.'; }
    if ($meal_id <= 0) { $errors[] = 'Please select a meal.'; }

    // Image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Invalid image format. Use JPG, PNG, or WEBP.';
        } else {
            $safeName = 'popular_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetDirFs = __DIR__ . '/../user/assets/meals/';
            if (!is_dir($targetDirFs)) { @mkdir($targetDirFs, 0777, true); }
            $targetFs = $targetDirFs . $safeName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFs)) {
                $errors[] = 'Failed to save uploaded image.';
            } else {
                // URL as seen from user pages
                $image_url = 'assets/meals/' . $safeName;
            }
        }
    } else {
        $errors[] = 'Please upload an image.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO popular_meals (meal_id, image_url, description, plan_type, sort_order, is_active) VALUES (?,?,?,?,?,1)");
        $stmt->bind_param('isssi', $meal_id, $image_url, $description, $plan_type, $sort_order);
        if ($stmt->execute()) {
            $messages[] = 'Popular meal added successfully.';
        } else {
            $errors[] = 'DB error: '.$conn->error;
        }
        $stmt->close();
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM popular_meals WHERE id=?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) { $messages[] = 'Deleted.'; } else { $errors[] = 'Failed to delete.'; }
        $stmt->close();
    }
}

if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE popular_meals SET is_active=? WHERE id=?');
        $stmt->bind_param('ii', $is_active, $id);
        if ($stmt->execute()) { $messages[] = 'Updated status.'; } else { $errors[] = 'Failed to update status.'; }
        $stmt->close();
    }
}

if ($action === 'resort') {
    // expects arrays id[] and sort_order[] aligned by index
    if (!empty($_POST['id']) && !empty($_POST['sort_order'])) {
        foreach ($_POST['id'] as $idx => $id) {
            $id = (int)$id;
            $so = (int)($_POST['sort_order'][$idx] ?? 0);
            if ($id > 0) {
                $conn->query('UPDATE popular_meals SET sort_order='.(int)$so.' WHERE id='.(int)$id);
            }
        }
        $messages[] = 'Sort order updated.';
    }
}

// Fetch meals for dropdown (list all meals)
$meals = [];
$res = $conn->query("SELECT meal_id, meal_name FROM meals ORDER BY meal_name");
if ($res) { while ($r = $res->fetch_assoc()) { $meals[] = $r; } $res->close(); }

// Fetch existing popular meals
$pop = [];
$res = $conn->query("SELECT pm.id, pm.image_url, pm.description, pm.plan_type, pm.sort_order, pm.is_active, m.meal_name
                     FROM popular_meals pm JOIN meals m ON m.meal_id=pm.meal_id
                     ORDER BY pm.sort_order, pm.id");
if ($res) { while ($r=$res->fetch_assoc()) { $pop[]=$r; } $res->close(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tiffinly - Manage Popular Meals</title>
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
    label{display:block;margin-bottom:4px;font-weight:600;font-size:12px}
    input[type=text],input[type=number],select,textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-family:'Poppins';font-size:15px}
    textarea{min-height:120px}
    .btn{background:#2C7A7B;color:#fff;border:none;border-radius:8px;padding:8px 10px;font-weight:600;cursor:pointer;font-size:13px}
    .btn.secondary{background:#6c757d}
    .btn.danger{background:#c0392b}
    .btn.sm{padding:5px 8px;font-size:12px;border-radius:6px}
    .alert{padding:8px 10px;border-radius:8px;margin-bottom:10px;font-size:13px}
    .alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9}
    .alert.error{background:#fdecea;color:#c62828;border:1px solid #f5c6cb}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;font-size:13px}
    .badge{font-size:11px;padding:2px 8px;border-radius:999px;background:#F1F5F9;color:#334155;border:1px solid #E2E8F0}
    img.thumb{width:80px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb}
    footer{text-align:center;padding:12px;margin-top:auto;color:#777;font-size:13px;border-top:1px solid #eee}
    @media(max-width:992px){.dashboard-container{grid-template-columns:1fr}.sidebar{display:none}.main-content{padding:16px}}
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
        <a class="menu-item" href="manage_plans.php"><i class="fas fa-box"></i> Manage Plans</a>
        <a class="menu-item active" href="manage_popular_meals.php"><i class="fas fa-star"></i> Manage Popular Meals</a>
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
          <h1>Manage Popular Meals</h1>
          <p>Create, activate and order popular meals for Basic/Premium. Reflected in Compare Plans.</p>
        </div>
      </div>

      <div class="content-card">
        <?php if ($errors): ?>
          <div class="alert error">
            <ul style="margin:0 0 0 16px; padding:0;">
              <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <?php if ($messages): ?>
          <div class="alert success">
            <ul style="margin:0 0 0 16px; padding:0;">
              <?php foreach ($messages as $m): ?><li><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <h3 style="margin:0 0 8px 0">Add Popular Meal</h3>
        <form method="post" enctype="multipart/form-data" style="display:grid;gap:12px">
          <input type="hidden" name="action" value="add">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label>Meal</label>
              <select name="meal_id" required>
                <option value="">-- Select meal --</option>
                <?php foreach ($meals as $m): ?>
                  <option value="<?php echo (int)$m['meal_id']; ?>"><?php echo htmlspecialchars($m['meal_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Plan Type</label>
              <select name="plan_type" required>
                <option value="basic">Basic</option>
                <option value="premium">Premium</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label>Image</label>
              <input type="file" name="image" accept="image/*" required>
            </div>
            <div>
              <label>Sort Order</label>
              <input type="number" name="sort_order" value="0" min="0">
            </div>
          </div>
          <div>
            <label>Description (optional)</label>
            <textarea name="description" placeholder="Short description..."></textarea>
          </div>
          <div style="display:flex;justify-content:flex-end">
            <button class="btn" type="submit"><i class="fas fa-plus"></i> Add</button>
          </div>
        </form>
      </div>

      <div class="content-card">
        <h3 style="margin:0 0 8px 0">Existing Popular Meals</h3>
        <?php if (empty($pop)): ?>
          <div style="color:#777">No entries yet.</div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="resort">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Image</th>
                  <th>Meal</th>
                  <th>Plan</th>
                  <th>Description</th>
                  <th>Active</th>
                  <th>Sort</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pop as $row): ?>
                  <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><img src="<?php echo htmlspecialchars('../user/'.$row['image_url']); ?>" class="thumb" alt=""></td>
                    <td><?php echo htmlspecialchars($row['meal_name']); ?></td>
                    <td><span class="badge"><?php echo htmlspecialchars(ucfirst($row['plan_type'])); ?></span></td>
                    <td style="max-width:420px"><?php echo htmlspecialchars($row['description']); ?></td>
                    <td>
                      <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <input type="hidden" name="is_active" value="<?php echo $row['is_active']?0:1; ?>">
                        <button class="btn secondary sm" type="submit"><?php echo $row['is_active']?'Deactivate':'Activate'; ?></button>
                      </form>
                    </td>
                    <td>
                      <input type="hidden" name="id[]" value="<?php echo (int)$row['id']; ?>">
                      <input type="number" name="sort_order[]" value="<?php echo (int)$row['sort_order']; ?>" style="width:70px">
                    </td>
                    <td>
                      <form method="post" onsubmit="return confirm('Delete this item?');" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="btn danger sm" type="submit"><i class="fas fa-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div style="display:flex;justify-content:flex-end;margin-top:10px">
              <button class="btn" type="submit">Update Sort</button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <footer>© <?php echo date('Y'); ?> Tiffinly Admin</footer>
    </div>
  </div>

</body>
</html>
