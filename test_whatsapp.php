<?php
require_once 'config/whatsapp_notification.php';

$testPhone = '';
$testMessage = 'Test message from Tiffinly - ' . date('Y-m-d H:i:s');
$whatsappLink = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['phone'])) {
    $testPhone = trim($_POST['phone']);
    $testPhone = preg_replace('/[^0-9]/', '', $testPhone);
    
    // Add country code if not present (assuming India +91 by default)
    if (strlen($testPhone) === 10) {
        $testPhone = '91' . $testPhone;
    }
    
    // Generate WhatsApp link
    $whatsappLink = sendWhatsAppNotification($testPhone, $testMessage);
}

// Display the link and a clickable button
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test WhatsApp Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
        }
        .whatsapp-btn {
            display: inline-block;
            background-color: #25D366;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            margin: 20px 0;
            transition: background-color 0.3s;
        }
        .whatsapp-btn:hover {
            background-color: #128C7E;
        }
        .whatsapp-btn i {
            margin-right: 10px;
        }
        .debug-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
            text-align: left;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <h1>Test WhatsApp Notification</h1>
    
    <form method="post" action="">
        <div style="margin: 20px 0;">
            <label for="phone" style="display: block; margin-bottom: 5px; font-weight: bold;">
                Enter your WhatsApp number (with country code):
            </label>
            <input type="tel" id="phone" name="phone" 
                   placeholder="e.g., 911234567890" 
                   style="padding: 10px; width: 250px; border: 1px solid #ddd; border-radius: 4px;"
                   value="<?php echo htmlspecialchars($testPhone); ?>">
            <button type="submit" style="padding: 10px 20px; background: #25D366; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Test WhatsApp
            </button>
        </div>
    </form>
    
    <?php if (!empty($whatsappLink)): ?>
        <div style="margin: 20px 0;">
            <p>Click the button below to test the WhatsApp notification:</p>
            <a href="<?php echo htmlspecialchars($whatsappLink); ?>" class="whatsapp-btn" target="_blank">
                <i class="fab fa-whatsapp"></i> Open WhatsApp
            </a>
        </div>
        
        <div class="debug-info">
            <strong>Debug Information:</strong><br>
            Phone: <?php echo htmlspecialchars($testPhone); ?><br>
            Message: <?php echo htmlspecialchars($testMessage); ?><br>
            Generated Link: <?php echo htmlspecialchars($whatsappLink); ?>
        </div>
    <?php else: ?>
        <p>Error: Could not generate WhatsApp link.</p>
    <?php endif; ?>
    
    <p style="margin-top: 30px;">
        <strong>Note:</strong> This will open WhatsApp Web or the WhatsApp app with a pre-filled message.
        You need to manually send the message to yourself.
    </p>
</body>
</html>
