<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once('../config/db_connect.php');

// Get admin data from session
$admin_name = $_SESSION['name'];
$admin_email = $_SESSION['email'];

// Fetch unassigned subscriptions
$unassigned_sql = "
    SELECT s.*
    FROM subscriptions s
    LEFT JOIN delivery_assignments da ON s.subscription_id = da.subscription_id
    WHERE s.status = 'active' AND da.assignment_id IS NULL
";
$unassigned_res = $conn->query($unassigned_sql);

// Fetch all delivery partners
$partners_sql = "SELECT user_id, name FROM users WHERE role = 'delivery'";
$partners_res = $conn->query($partners_sql);
$partners = [];
while ($row = $partners_res->fetch_assoc()) {
    $partners[] = $row;
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_partner'])) {
    $subscription_id = (int)$_POST['subscription_id'];
    $partner_id = (int)$_POST['partner_id'];

    if ($subscription_id > 0 && $partner_id > 0) {
        // Get subscription details
        $sub_stmt = $conn->prepare("SELECT * FROM subscriptions WHERE subscription_id = ?");
        $sub_stmt->bind_param("i", $subscription_id);
        $sub_stmt->execute();
        $sub_res = $sub_stmt->get_result();
        $subscription = $sub_res->fetch_assoc();

        if ($subscription) {
            $start_date = new DateTime($subscription['start_date']);
            $end_date = new DateTime($subscription['end_date']);
            $schedule = $subscription['schedule'];

            $interval = new DateInterval('P1D');
            $daterange = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

            $insert_stmt = $conn->prepare("INSERT INTO delivery_assignments (subscription_id, delivery_date, partner_id, meal_type) VALUES (?, ?, ?, ?)");

            foreach ($daterange as $date) {
                $day_of_week = $date->format('N'); // 1 (for Monday) through 7 (for Sunday)
                $should_deliver = false;
                switch ($schedule) {
                    case 'Weekdays':
                        if ($day_of_week <= 5) $should_deliver = true;
                        break;
                    case 'Extended':
                        if ($day_of_week <= 6) $should_deliver = true;
                        break;
                    case 'Full Week':
                        $should_deliver = true;
                        break;
                }

                if ($should_deliver) {
                    $delivery_date_str = $date->format('Y-m-d');
                    foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal_type) {
                        $insert_stmt->bind_param("isis", $subscription_id, $delivery_date_str, $partner_id, $meal_type);
                        $insert_stmt->execute();
                    }
                }
            }
            $insert_stmt->close();
            $_SESSION['success_message'] = "Subscription #$subscription_id assigned to partner #$partner_id successfully.";
            header("Location: pending_delivery.php");
            exit();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Deliveries - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2C7A7B;
            --secondary-color: #F39C12;
            --dark-color: #2C3E50;
            --light-color: #F9F9F9;
            --light-gray: #e9ecef;
            --border-radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        }

        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background-color: var(--light-color); color: var(--dark-color); }

        .dashboard-container { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; height: 100vh; overflow: hidden; }
        .sidebar { background-color: #fff; box-shadow: var(--shadow-md); padding: 30px 0; height: 100vh; overflow-y: auto; position: sticky; top: 0; }
        .sidebar-header { font-weight: 700; font-size: 24px; padding: 0 25px 15px; border-bottom: 1px solid #f0f0f0; text-align: center; color: #2C3E50; }
        .admin-profile { display: flex; align-items: center; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; margin-bottom: 15px; }
        .admin-avatar { width: 50px; height: 50px; border-radius: 50%; background-color: #F39C12; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 20px; font-weight: 600; color: white; }
        .admin-info h4 { margin: 0; font-size: 16px; }
        .admin-info p { margin: 3px 0 0; font-size: 13px; opacity: 0.8; }
        .menu-category { color: var(--primary-color); font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 25px; margin-top: 15px; }
        .menu-item { padding: 12px 25px; display: flex; align-items: center; color: var(--dark-color); text-decoration: none; transition: all .2s; font-size: 15px; border-left: 3px solid transparent; }
        .menu-item i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .menu-item:hover, .menu-item.active { background-color: #F0F7F7; color: var(--primary-color); border-left: 3px solid var(--primary-color); transform: translateX(5px); }

        .main-content { padding: 30px; background-color: var(--light-color); min-height: 100vh; overflow-y: auto; display: flex; flex-direction: column; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 {
            font-size: 28px;
            margin: 0;
            position: relative;
            display: inline-block;
        }
        
        .header h1:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .header:hover h1:after {
            width: 100%;
        }

        .content-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(44,122,123,0.09);
            padding: 28px 32px;
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp; Tiffinly</div>
        <div class="admin-profile">
            <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                <p><?php echo htmlspecialchars($admin_email); ?></p>
            </div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-category">Dashboard & Overview</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <div class="menu-category">Meal Management</div>
            <a href="manage_menu.php" class="menu-item"><i class="fas fa-utensils"></i> Manage Menu</a>
            <a href="manage_plans.php" class="menu-item"><i class="fas fa-box"></i> Manage Plans</a>
            <a href="manage_popular_meals.php" class="menu-item"><i class="fas fa-star"></i> Manage Popular Meals</a>
            <div class="menu-category">User & Subscriptions</div>
            <a href="manage_users.php" class="menu-item"><i class="fas fa-users"></i> Users Data</a>
            <a href="manage_subscriptions.php" class="menu-item"><i class="fas fa-calendar-check"></i> Subscriptions Data</a>
            <div class="menu-category">Delivery & Partner Management</div>
            <a href="manage_partners.php" class="menu-item"><i class="fas fa-hands-helping"></i> Manage Partners</a>
            <a href="manage_delivery.php" class="menu-item"><i class="fas fa-truck"></i>  Delivery Data</a>
            <a href="pending_delivery.php" class="menu-item active"><i class="fas fa-clock"></i> Pending Delivery</a>
            <div class="menu-category">Inquiry & Feedback Management</div>
            <a href="manage_inquiries.php" class="menu-item"><i class="fas fa-users"></i> Manage Inquiries</a>
            <a href="view_feedback.php" class="menu-item"><i class="fas fa-comment-alt"></i> View Feedback</a>
            <div style="margin-top: 30px;"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Pending Deliveries</h1>
            <a href="manage_partners.php#addPartnerForm" class="btn btn-primary">Add New Partner</a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" style="margin: 15px 0; background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px;">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <table>
                <thead>
                    <tr>
                        <th>Subscription ID</th>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Schedule</th>
                        <th>Assign Partner</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($unassigned_res->num_rows > 0): ?>
                        <?php while($sub = $unassigned_res->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $sub['subscription_id']; ?></td>
                                <td><?php 
                                    $user_stmt = $conn->prepare("SELECT name, phone FROM users WHERE user_id = ?");
                                    $user_stmt->bind_param("i", $sub['user_id']);
                                    $user_stmt->execute();
                                    $user_res = $user_stmt->get_result();
                                    $user = $user_res->fetch_assoc();
                                    echo htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['phone']) . ')';
                                ?></td>
                                <td><?php 
                                    $plan_stmt = $conn->prepare("SELECT plan_name FROM meal_plans WHERE plan_id = ?");
                                    $plan_stmt->bind_param("i", $sub['plan_id']);
                                    $plan_stmt->execute();
                                    $plan_res = $plan_stmt->get_result();
                                    $plan = $plan_res->fetch_assoc();
                                    echo htmlspecialchars($plan['plan_name']);
                                ?></td>
                                <td><?php echo htmlspecialchars($sub['schedule']); ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="subscription_id" value="<?php echo $sub['subscription_id']; ?>">
                                        <select name="partner_id" required>
                                            <option value="">Select Partner</option>
                                            <?php foreach ($partners as $partner): ?>
                                                <option value="<?php echo $partner['user_id']; ?>"><?php echo htmlspecialchars($partner['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td>
                                        <button type="submit" name="assign_partner" class="btn btn-primary">Assign</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:#666">No pending deliveries to assign.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
