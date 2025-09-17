<?php
session_start();
// Check if admin is logged in, otherwise redirect to login page
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'tiffinly');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// --- Payment Processing ---
if (isset($_POST['process_payment']) && isset($_POST['razorpay_payment_id'])) {
    $partner_id = (int)$_POST['partner_id'];
    $delivery_date = $_POST['delivery_date'];
    $subscription_id = (int)$_POST['subscription_id'];
    $amount_due = (float)$_POST['amount'];
    $delivery_count = (int)$_POST['delivery_count'];
    $transaction_ref = $_POST['razorpay_payment_id'];

    // 1. Insert into partner_payments
    $stmt = $db->prepare("INSERT INTO partner_payments (partner_id, subscription_id, delivery_date, amount, delivery_count, payment_method, payment_status, transaction_ref) VALUES (?, ?, ?, ?, ?, 'Razorpay', 'success', ?)");
    $stmt->bind_param("iisdis", $partner_id, $subscription_id, $delivery_date, $amount_due, $delivery_count, $transaction_ref);
    $stmt->execute();
    $payment_id = $db->insert_id;
    $stmt->close();

    // 2. Update delivery_assignments table - THIS IS THE SINGLE SOURCE OF TRUTH
    $update_stmt_2 = $db->prepare("UPDATE delivery_assignments SET payment_status = 'paid', payment_id = ? WHERE partner_id = ? AND subscription_id = ? AND delivery_date = ? AND status = 'delivered'");
    $update_stmt_2->bind_param("iiis", $payment_id, $partner_id, $subscription_id, $delivery_date);
    $update_stmt_2->execute();
    $update_stmt_2->close();

    // 3. Update deliveries table (for consistency)
    $update_stmt_1 = $db->prepare("UPDATE deliveries SET payment_status = 'paid' WHERE subscription_id = ? AND delivery_date = ?");
    $update_stmt_1->bind_param("is", $subscription_id, $delivery_date);
    $update_stmt_1->execute();
    $update_stmt_1->close();

    $_SESSION['success_message'] = "Payment of ₹" . number_format($amount_due, 2) . " for subscription #$subscription_id on $delivery_date processed successfully with TXN ID: $transaction_ref.";
    header("Location: manage_partners.php");
    exit();
}


// --- Helper functions ---
function norm_sched($s) {
    $s = strtolower(trim((string)$s));
    $map = [
        'daily' => 'daily', 'everyday' => 'daily', 'all' => 'daily', 'all days' => 'daily',
        'fullweek' => 'fullweek', 'full week' => 'fullweek', 'full_week' => 'fullweek',
        'extended' => 'extended',
        'weekday' => 'weekdays', 'weekdays' => 'weekdays', 'mon-fri' => 'weekdays', 'mon to fri' => 'weekdays',
        'weekend' => 'weekends', 'weekends' => 'weekends', 'sat-sun' => 'weekends', 'sat & sun' => 'weekends'
    ];
    return $map[$s] ?? 'daily';
}

function count_days_for_range($start_date, $end_date, $schedule) {
    if (empty($start_date) || empty($end_date)) return 0;
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
    } catch (Exception $e) { return 0; }
    if ($end < $start) return 0;
    $norm = norm_sched($schedule);
    $count = 0; $cur = clone $start;
    while ($cur <= $end) {
        $dow = (int)$cur->format('N');
        $ok = (
            $norm === 'daily' ||
            $norm === 'fullweek' ||
            ($norm === 'extended' && $dow >= 1 && $dow <= 6) ||
            ($norm === 'weekdays' && $dow >= 1 && $dow <= 5) ||
            ($norm === 'weekends' && $dow >= 6)
        );
        if ($ok) $count++;
        $cur->modify('+1 day');
    }
    return $count;
}

// --- Add New Partner ---
if(isset($_POST['add_partner'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $vehicle_type = $_POST['vehicle_type'];
    $vehicle_number = $_POST['vehicle_number'];
    $license_number = $_POST['license_number'];
    $aadhar_number = $_POST['aadhar_number'];
    $availability = $_POST['availability'];

    $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'delivery')");
    $stmt->bind_param("ssss", $name, $email, $password, $phone);
    $stmt->execute();
    $partner_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $db->prepare("INSERT INTO delivery_partner_details (partner_id, vehicle_type, vehicle_number, license_number, aadhar_number, availability) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $partner_id, $vehicle_type, $vehicle_number, $license_number, $aadhar_number, $availability);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_partners.php");
    exit();
}

// --- Remove Partner ---
if(isset($_POST['remove_partner'])) {
    $partner_id = $_POST['partner_id'];
    $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND role = 'delivery'");
    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_partners.php");
    exit();
}


// Get admin data from session
$admin_name = $_SESSION['name'];
$admin_email = $_SESSION['email'];

// --- Fetching Data ---
$partners_result = $db->query("SELECT u.*, dp.* FROM users u JOIN delivery_partner_details dp ON u.user_id = dp.partner_id WHERE u.role = 'delivery'");
$partners_map = [];
while($p = $partners_result->fetch_assoc()) {
    $partners_map[$p['user_id']] = $p;
}

$unpaid_deliveries_sql = "
    SELECT
        da.partner_id,
        da.subscription_id,
        da.delivery_date,
        COUNT(da.assignment_id) AS unpaid_deliveries_count
    FROM
        delivery_assignments da
    WHERE
        da.status = 'delivered' AND da.payment_status = 'unpaid'
    GROUP BY
        da.partner_id, da.subscription_id, da.delivery_date
    ORDER BY
        da.delivery_date DESC, da.partner_id
";

$unpaid_deliveries_result = $db->query($unpaid_deliveries_sql);

$unpaid_deliveries_data = [];
if ($unpaid_deliveries_result && $unpaid_deliveries_result->num_rows > 0) {
    while ($row = $unpaid_deliveries_result->fetch_assoc()) {
        $unpaid_deliveries_data[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Manage Partners</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
    :root {
        --primary-color: #2C7A7B;
        --secondary-color: #F39C12;
        --accent-color: #F1C40F;
        --dark-color: #2C3E50;
        --light-color: #F9F9F9;
        --success-color: #27AE60;
        --error-color: #E74C3C;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
        --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --transition-fast: all 0.2s ease;
        --transition-medium: all 0.3s ease;
        --transition-slow: all 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        padding: 0;
        background-color: var(--light-color);
        color: var(--dark-color);
        overflow-x: hidden;
    }
    
    .dashboard-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        min-height: 100vh;
    }

    .sidebar {
        background-color: white;
        box-shadow: var(--shadow-md);
        padding: 30px 0;
        z-index: 10;
        height: 100vh;
        overflow-y: auto;
        position: sticky;
        top: 0;
    }

    .sidebar-header {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 24px;
        padding: 0 25px 15px;
        border-bottom: 1px solid #f0f0f0;
        text-align: center;
        color: #2C3E50;
    }

    .admin-profile {
        display: flex;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        margin-bottom: 15px;
    }
    
    .admin-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color:  #F39C12;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
        font-weight: 600;
        color: white;
    }
    
    .admin-info h4 {
        margin: 0;
        font-size: 16px;
    }
    
    .admin-info p {
        margin: 3px 0 0;
        font-size: 13px;
        opacity: 0.8;
    }

    .sidebar-menu {
        padding: 15px 0;
    }
    
    .menu-category {
        color: var(--primary-color);
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 25px;
        margin-top: 15px;
    }
    
    .menu-item {
        padding: 12px 25px;
        display: flex;
        align-items: center;
        color: var(--dark-color);
        text-decoration: none;
        transition: var(--transition-medium);
        font-size: 15px;
        border-left: 3px solid transparent;
        position: relative;
        overflow: hidden;
    }
    
    .menu-item:hover, .menu-item.active {
        background-color: #F0F7F7;
        color: var(--primary-color);
        border-left: 3px solid var(--primary-color);
        transform: translateX(5px);
    }
    
    .menu-item i {
        margin-right: 12px;
        font-size: 16px;
        width: 20px;
        text-align: center;
        transition: var(--transition-fast);
    }
    
    .main-content {
        padding: 30px;
        background-color: var(--light-color);
        height: 100vh;
        overflow-y: auto;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 35px;
    }
    
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
        transition: var(--transition-medium);
    }
    
    .header:hover h1:after {
        width: 100%;
    }
    
    
    .content-card {
        background-color: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 25px;
    }

    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    th {
        background-color: #f8f9fa;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
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
    .btn-primary:hover {
        background-color: #245B5C;
    }
    .btn-remove {
        background-color: #dc3545;
        color: white;
    }
    .btn-remove:hover {
        background-color: #c82333;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
    }
    footer {
        text-align: center;
        padding: 20px;
        margin-top: 30px;
        color: #777;
        font-size: 14px;
        border-top: 1px solid #eee;
    }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-utensils"></i>&nbsp; Tiffinly
            </div>
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                    <p><?php echo htmlspecialchars($admin_email); ?></p>
                </div>
            </div>
            <div class="sidebar-menu">
                <div class="menu-category">Dashboard & Overview</div>
                <a href="admin_dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <div class="menu-category">Meal Management</div>
                <a href="manage_menu.php" class="menu-item">
                    <i class="fas fa-utensils"></i> Manage Menu
                </a>
                <a href="manage_plans.php" class="menu-item">
                    <i class="fas fa-box"></i> Manage Plans
                </a>
                <a href="manage_popular_meals.php" class="menu-item">
                    <i class="fas fa-star"></i> Manage Popular Meals
                </a>

                <div class="menu-category">User & Subscriptions</div>
                <a href="manage_users.php" class="menu-item">
                    <i class="fas fa-users"></i> Users Data
                </a>
                <a href="manage_subscriptions.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> Subscriptions Data
                </a>

                <div class="menu-category">Delivery & Partner Management</div>
                <a href="manage_partners.php" class="menu-item active">
                    <i class="fas fa-hands-helping"></i> Manage Partners
                </a>
                <a href="manage_delivery.php" class="menu-item">
                    <i class="fas fa-truck"></i>  Delivery Data
                </a>
                <a href="pending_delivery.php" class="menu-item">
                    <i class="fas fa-clock"></i> Pending Delivery
                </a>
                
                <div class="menu-category">Inquiry & Feedback Management</div>
                <a href="manage_inquiries.php" class="menu-item">
                    <i class="fas fa-users"></i> Manage Inquiries
                </a>
                <a href="view_feedback.php" class="menu-item">
                    <i class="fas fa-comment-alt"></i> View Feedback
                </a>
                
                <div style="margin-top: 30px;">
                    <a href="../logout.php" class="menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="header">
                <h1>Manage Delivery Partners</h1>
                <p>Add, view, and process payments for delivery partners.</p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" style="margin: 15px 0; background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px;">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Existing Partners Table -->
            <div class="content-card">
               <h2>All Delivery Partners</h2>
               <div class="table-container">
                   <table>
                       <thead>
                           <tr>
                               <th>Partner ID</th>
                               <th>Name</th>
                               <th>Email</th>
                               <th>Phone</th>
                               <th>Vehicle Type</th>
                               <th>Vehicle Number</th>
                               <th>License</th>
                               <th>Availability</th>
                               <th>Actions</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php mysqli_data_seek($partners_result, 0); ?>
                           <?php foreach($partners_map as $partner): ?>
                           <tr>
                               <td><?php echo $partner['user_id']; ?></td>
                               <td><?php echo htmlspecialchars($partner['name']); ?></td>
                               <td><?php echo htmlspecialchars($partner['email']); ?></td>
                               <td><?php echo htmlspecialchars($partner['phone']); ?></td>
                               <td><?php echo htmlspecialchars($partner['vehicle_type']); ?></td>
                               <td><?php echo htmlspecialchars($partner['vehicle_number']); ?></td>
                               <td><?php echo htmlspecialchars($partner['license_number']); ?></td>
                               <td><?php echo htmlspecialchars($partner['availability']); ?></td>
                               <td>
                                   <form method="POST" style="display:inline;">
                                       <input type="hidden" name="partner_id" value="<?php echo $partner['user_id']; ?>">
                                       <button type="submit" name="remove_partner" class="btn btn-remove" onclick="return confirm('Are you sure you want to remove this partner? This action cannot be undone.')">
                                           <i class="fas fa-trash-alt"></i> Remove
                                       </button>
                                   </form>
                               </td>
                           </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
               </div>
           </div>

            <!-- Pay Partners Table -->
            <div class="content-card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Partner Payments (Daily Breakdown)</h2>
                    <a href="#payout-history" style="font-size: 14px; text-decoration: none;">View Past Payout History</a>
                </div>
                <div class="table-container">
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th>Partner</th>
                                <th>Subscription ID</th>
                                <th>Delivery Date</th>
                                <th>Completed Deliveries</th>
                                <th>Amount Due</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($unpaid_deliveries_data)): ?>
                                <tr><td colspan="6" style="text-align:center;padding:20px;color:#666">No pending payments found.</td></tr>
                            <?php else: foreach ($unpaid_deliveries_data as $payment):
                                $amount_due = 40 * $payment['unpaid_deliveries_count'];
                                $partner_details = $partners_map[$payment['partner_id']] ?? null;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($partner_details['name'] ?? 'N/A'); ?> (ID: <?php echo $payment['partner_id']; ?>)</td>
                                    <td>#<?php echo $payment['subscription_id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['delivery_date'])); ?></td>
                                    <td><?php echo $payment['unpaid_deliveries_count']; ?></td>
                                    <td>₹<?php echo number_format($amount_due, 2); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary" 
                                            onclick="payWithRazorpay({
                                                amount: <?php echo $amount_due; ?>,
                                                partner_name: '<?php echo htmlspecialchars($partner_details['name'] ?? 'N/A'); ?>',
                                                partner_email: '<?php echo htmlspecialchars($partner_details['email'] ?? ''); ?>',
                                                partner_phone: '<?php echo htmlspecialchars($partner_details['phone'] ?? ''); ?>',
                                                partner_id: <?php echo $payment['partner_id']; ?>,
                                                subscription_id: <?php echo $payment['subscription_id']; ?>,
                                                delivery_date: '<?php echo $payment['delivery_date']; ?>',
                                                delivery_count: <?php echo $payment['unpaid_deliveries_count']; ?>
                                            })">
                                            Pay with Razorpay
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add New Partner Card -->
            <div class="content-card">
                <h2 style="cursor: pointer;" onclick="toggleAddPartnerForm()">Add New Partner <i class="fas fa-plus-circle"></i></h2>
                <form id="addPartnerForm" method="POST" action="manage_partners.php" style="display:none;">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <input type="text" name="vehicle_type" required>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Number</label>
                        <input type="text" name="vehicle_number" required>
                    </div>
                    <div class="form-group">
                        <label>License Number</label>
                        <input type="text" name="license_number" required>
                    </div>
                    <div class="form-group">
                        <label>Aadhar Number</label>
                        <input type="text" name="aadhar_number" required>
                    </div>
                    <div class="form-group">
                        <label>Availability</label>
                        <select name="availability" required>
                            <option value="Part-time">Part-time</option>
                            <option value="Full-time">Full-time</option>
                        </select>
                    </div>
                    <button type="submit" name="add_partner" class="btn btn-primary">Add Partner</button>
                </form>
            </div>

            <!-- Past Payouts History -->
            <div class="content-card" id="payout-history">
                <h2>Past Payouts History</h2>
                <div class="table-container">
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th>Payout ID</th>
                                <th>Partner Name</th>
                                <th>Payment Date</th>
                                <th>Subscription ID</th>
                                <th>Delivery Date Paid For</th>
                                <th>Amount</th>
                                <th>Deliveries Paid For</th>
                            </tr>
                        </thead>
                        <tbody id="payouts-tbody">
                            <?php
                            $payouts_history_sql = "
                    SELECT pp.payment_id, pp.amount, pp.delivery_count, pp.created_at, pp.delivery_date, pp.subscription_id,
                        u.name as partner_name 
                                FROM partner_payments pp 
                                JOIN users u ON pp.partner_id = u.user_id 
                                WHERE pp.payment_status = 'success' ORDER BY pp.created_at DESC";
                            $payouts_history_result = $db->query($payouts_history_sql);
                            if ($payouts_history_result && $payouts_history_result->num_rows > 0):
                                $payout_count = 0;
                                while ($payout = $payouts_history_result->fetch_assoc()): 
                                    $payout_count++;
                                    $row_style = $payout_count > 3 ? 'display: none;' : '';
                                    ?>
                                    <tr class="payout-row" style="<?php echo $row_style; ?>">
                                        <td>#<?php echo htmlspecialchars($payout['payment_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payout['partner_name']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($payout['created_at'])); ?></td>
                                        <td>#<?php echo htmlspecialchars($payout['subscription_id']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($payout['delivery_date'])); ?></td>
                                        <td>₹<?php echo number_format($payout['amount'], 2); ?></td>
                                        <td><?php echo (int)$payout['delivery_count']; ?></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr><td colspan="6" style="text-align:center;padding:20px;color:#666">No past payouts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($payouts_history_result && $payouts_history_result->num_rows > 3): ?>
                <button id="toggle-payouts-btn" class="btn btn-primary" style="margin-top: 15px;">Show More</button>
                <?php endif; ?>
            </div>
            
            <footer>
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>
    <script>
        function toggleAddPartnerForm() {
            var form = document.getElementById('addPartnerForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('toggle-payouts-btn');
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    const payoutRows = document.querySelectorAll('.payout-row');
                    let isShowingMore = this.textContent === 'Show More';
                    for (let i = 3; i < payoutRows.length; i++) {
                        payoutRows[i].style.display = isShowingMore ? 'table-row' : 'none';
                    }
                    this.textContent = isShowingMore ? 'Show Less' : 'Show More';
                });
            }
        });

        function payWithRazorpay(paymentData) {
            var options = {
                "key": "rzp_test_1DP5mmOlF5G5ag",
                "amount": Math.round(paymentData.amount * 100),
                "currency": "INR",
                "name": "Tiffinly Partner Payout",
                "description": `Payout for sub #${paymentData.subscription_id} on ${paymentData.delivery_date}`,
                "image": "https://cdn.razorpay.com/static/assets/razorpay-glyph.svg",
                "prefill": {
                    "name": paymentData.partner_name,
                    "email": paymentData.partner_email,
                    "contact": paymentData.partner_phone
                },
                "handler": function (response){
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'manage_partners.php';
                    var inputs = {
                        process_payment: 1,
                        razorpay_payment_id: response.razorpay_payment_id,
                        partner_id: paymentData.partner_id,
                        subscription_id: paymentData.subscription_id,
                        delivery_date: paymentData.delivery_date,
                        amount: paymentData.amount,
                        delivery_count: paymentData.delivery_count
                    };
                    for (var key in inputs) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = inputs[key];
                        form.appendChild(input);
                    }
                    document.body.appendChild(form);
                    form.submit();
                },
                "modal": {
                    "ondismiss": function(){
                        alert('Payment cancelled.');
                    }
                },
                "theme": { "color": "#2C7A7B" }
            };
            var rzp = new Razorpay(options);
            rzp.open();
        }
    </script>
</body>
</html>
<?php
$db->close();
?>