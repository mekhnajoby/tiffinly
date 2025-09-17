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

// --- Handle Inquiry Response/Status Update ---
if(isset($_POST['update_inquiry'])) {
    $inquiry_id = $_POST['inquiry_id'];
    $response = trim($_POST['response'] ?? '');
    $status = $_POST['status'] ?? 'pending';

    // Validate status
    if (!in_array($status, ['pending', 'responded', 'closed'])) {
        $status = 'pending'; // Default to pending if invalid status is provided
    }

    // Update inquiry in database
    $stmt = $db->prepare("UPDATE inquiries SET response = ?, status = ? WHERE inquiry_id = ?");
    $stmt->bind_param("ssi", $response, $status, $inquiry_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_inquiries.php");
    exit();
}

// Get admin data from session
$admin_name = $_SESSION['name'];
$admin_email = $_SESSION['email'];

// --- Fetching Inquiries ---
$inquiries_result = $db->query("SELECT i.inquiry_id, u.name AS user_name, u.email AS user_email, i.message, i.inquiry_type, i.response, i.status, i.created_at FROM inquiries i JOIN users u ON i.user_id = u.user_id ORDER BY i.created_at DESC");

// --- Fetching Delivery Issues ---
$issues_sql = "SELECT di.*, p.name as partner_name FROM delivery_issues di JOIN users p ON di.partner_id = p.user_id ORDER BY di.created_at DESC";
$issues_result = $db->query($issues_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Manage Inquiries</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
        100% { transform: translateY(0px); }
    }
    
    @keyframes slideInLeft {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
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
        height: 100vh;
        overflow: hidden;
    }

    .sidebar {
        background-color: white;
        box-shadow: var(--shadow-md);
        padding: 30px 0;
        z-index: 10;
        animation: slideInLeft 0.6s ease-out;
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
        animation: fadeIn 0.8s ease-out;
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
        animation: fadeIn 0.6s ease-out;
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
    
    .menu-item:before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(44, 122, 123, 0.1), transparent);
        transition: var(--transition-medium);
    }
    
    .menu-item:hover:before {
        left: 100%;
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
    
    .menu-item:hover i {
        transform: scale(1.2);
    }

    .main-content {
        padding: 35px;
        background-color: var(--light-color);
        animation: fadeIn 0.8s ease-out;
        height: 100vh;
        overflow-y: auto;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 35px;
        animation: slideInRight 0.6s ease-out;
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
    
    .header p {
        margin: 5px 0 0;
        color: #777;
        font-size: 16px;
        transition: var(--transition-medium);
    }
    
    .header:hover p {
        color: var(--dark-color);
    }

    .content-card {
        background-color: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-medium);
        transform: translateY(0);
        margin-bottom: 25px;
    }
    
    .content-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-3px);
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
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: var(--transition-fast);
    }
    .btn-danger {
        background-color: var(--error-color);
        color: white;
    }
    .btn-danger:hover {
        background-color: #C0392B;
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    .btn-primary:hover {
        background-color: #245B5C;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
    }
    #responseForm { display: none; }

    footer {
        text-align: center;
        padding: 20px;
        margin-top: 40px;
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
                <a href="manage_partners.php" class="menu-item">
                    <i class="fas fa-hands-helping"></i> Manage Partners
                </a>
                <a href="manage_delivery.php" class="menu-item">
                    <i class="fas fa-truck"></i>  Delivery Data
                </a>
                <a href="pending_delivery.php" class="menu-item">
                    <i class="fas fa-clock"></i> Pending Delivery
                </a>
                
                <div class="menu-category">Inquiry & Feedback Management</div>
                <a href="manage_inquiries.php" class="menu-item active">
                    <i class="fas fa-question-circle"></i> Manage Inquiries
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
                <h1>Manage Inquiries</h1>
            </div>

            <!-- Existing Inquiries Table -->
            <div class="content-card">
                <h2>All Inquiries</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Name</th>
                                <th>User Email</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Response</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $inquiries_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['inquiry_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($row['inquiry_type']); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['message'], 0, 100)) . (strlen($row['message']) > 100 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['response'] ?? 'N/A', 0, 100)) . (strlen($row['response'] ?? '') > 100 ? '...' : ''); ?></td>
                                <td><span class="badge" style="background-color: #e9ecef; color: #495057;"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="showResponseForm(<?php echo $row['inquiry_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['message'])); ?>', '<?php echo htmlspecialchars(addslashes($row['response'] ?? '')); ?>', '<?php echo htmlspecialchars($row['status']); ?>')">Respond</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Response Form (Hidden initially) -->
            <div class="content-card" id="responseForm" style="display:none;">
                <h2>Respond to Inquiry</h2>
                <form method="POST" action="manage_inquiries.php">
                    <input type="hidden" name="inquiry_id" id="form_inquiry_id">
                    <div class="form-group">
                        <label>Original Message</label>
                        <textarea class="form-control" id="form_original_message" rows="5" readonly></textarea>
                    </div>
                    <div class="form-group">
                        <label for="response">Your Response</label>
                        <textarea class="form-control" name="response" id="form_response" rows="5"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="form_status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="responded">Responded</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <button type="submit" name="update_inquiry" class="btn btn-primary">Update Inquiry</button>
                </form>
            </div>

            <!-- Delivery Partner Issues Table -->
            <div class="content-card">
                <h2>Delivery Partner Issues</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Issue ID</th>
                                <th>Partner Name</th>
                                <th>Subscription ID</th>
                                <th>Assignment ID</th>
                                <th>Issue Type</th>
                                <th>Description</th>
                                <th>Logged At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($issues_result && $issues_result->num_rows > 0): ?>
                                <?php while($issue = $issues_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($issue['issue_id']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['partner_name']); ?></td>
                                    <td>#<?php echo htmlspecialchars($issue['subscription_id']); ?></td>
                                    <td>#<?php echo htmlspecialchars($issue['assignment_id']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['issue_type'] ?: 'General'); ?></td>
                                    <td><?php echo htmlspecialchars($issue['description']); ?></td>
                                    <td><?php echo date('M d, Y, h:i A', strtotime($issue['created_at'])); ?></td>
                                    <td><span class="badge" style="background-color: <?php echo $issue['status'] === 'resolved' ? '#27ae60' : '#f39c12'; ?>; color: white;"><?php echo htmlspecialchars(ucfirst($issue['status'])); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">No issues have been logged by delivery partners.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer>
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>
    <script>
    // In the JavaScript section, replace the existing showResponseForm function with this:
function showResponseForm(inquiry_id, message, response, status) {
    document.getElementById('form_inquiry_id').value = inquiry_id;
    document.getElementById('form_original_message').value = message;
    document.getElementById('form_response').value = response;
    document.getElementById('form_status').value = status;
    
    // Show the form
    document.getElementById('responseForm').style.display = 'block';
    
    // Scroll to the form smoothly
    const formElement = document.getElementById('responseForm');
    formElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Optional: Highlight the form briefly
    formElement.style.transition = 'box-shadow 0.5s ease';
    formElement.style.boxShadow = '0 0 0 3px rgba(44, 122, 123, 0.3)';
    setTimeout(() => {
        formElement.style.boxShadow = 'var(--shadow-sm)';
    }, 1000);
}
    </script>
</body>
</html>
<?php
$db->close();
?>