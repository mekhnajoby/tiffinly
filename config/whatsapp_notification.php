<?php
/**
 * WhatsApp Notification Helper
 * Creates a direct WhatsApp link for the user to receive messages
 * This method doesn't send messages automatically but provides a clickable link
 */

function getWhatsAppLink($phone, $message) {
    // Format phone number (remove any non-numeric characters)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Add country code if not present (assuming India +91 by default)
    if (strlen($phone) === 10) {
        $phone = '91' . $phone;
    }
    
    // Encode the message for URL
    $encodedMessage = urlencode($message);
    
    // Create WhatsApp direct link
    $whatsappLink = "https://wa.me/{$phone}?text={$encodedMessage}";
    
    return $whatsappLink;
}

/**
 * Sends a WhatsApp notification by returning a clickable link
 * Returns the link that the user can click to open WhatsApp with the pre-filled message
 */
function sendWhatsAppNotification($phone, $message) {
    return getWhatsAppLink($phone, $message);
}

/**
 * Gets the user's WhatsApp link for order confirmation
 */
function getOrderConfirmationLink($userId, $orderDetails) {
    global $db;

    // Get user's phone number from database
    $stmt = $db->prepare("SELECT phone FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || empty($user['phone'])) {
        return false;
    }

    // Format dates to dd-mm-yyyy format
    $start_date_formatted = date('d-m-Y', strtotime($orderDetails['start_date']));
    $end_date_formatted = date('d-m-Y', strtotime($orderDetails['end_date']));

    // Calculate delivery days - assume Full Week for orders (all days are delivery days)
    $schedule = isset($orderDetails['schedule']) ? $orderDetails['schedule'] : 'Full Week';
    $delivery_days = calculateDeliveryDays($orderDetails['start_date'], $orderDetails['end_date'], $schedule);

    // Create the message
    $message = "🎉 *Order Confirmed!* 🎉\n\n";
    $message .= "Hello! Your order has been confirmed.\n\n";
    $message .= "*Order Details:*\n";
    $message .= "Plan: {$orderDetails['plan_name']}\n";
    $message .= "Duration: {$delivery_days} delivery days ({$start_date_formatted} to {$end_date_formatted})\n";
    $message .= "Amount: ₹{$orderDetails['amount']}\n\n";
    $message .= "Thank you for choosing Tiffinly!\n";
    $message .= "For any queries, contact us at +91 1234567890.";

    // Return the WhatsApp link
    return getWhatsAppLink($user['phone'], $message);
}

/**
 * Format phone number for WhatsApp API
 * Removes all non-numeric characters and ensures it starts with country code (91 for India)
 */
/**
 * Calculate the number of delivery days based on schedule type
 */
function calculateDeliveryDays($start_date, $end_date, $schedule) {
    $start_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);

    // Loop through each day of the subscription period
    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start_date_obj, $interval, $end_date_obj->modify('+1 day'));

    $delivery_days = 0;

    foreach ($date_range as $date) {
        $day_of_week = $date->format('N'); // 1 (Monday) to 7 (Sunday)

        $should_deliver = false;
        switch ($schedule) {
            case 'Weekdays':
                if ($day_of_week <= 5) $should_deliver = true; // Mon-Fri
                break;
            case 'Extended':
                if ($day_of_week <= 6) $should_deliver = true; // Mon-Sat
                break;
            case 'Full Week':
                $should_deliver = true; // Mon-Sun
                break;
            default:
                $should_deliver = true; // Default to all days if schedule not recognized
                break;
        }

        if ($should_deliver) {
            $delivery_days++;
        }
    }

    return $delivery_days;
}

function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // If number starts with 0, remove it
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }

    // If number doesn't start with country code (91 for India), add it
    if (substr($phone, 0, 2) !== '91' && strlen($phone) === 10) {
        $phone = '91' . $phone;
    }

    return $phone;
}

/**
 * Send subscription confirmation message
 */
function sendSubscriptionConfirmation($userPhone, $subscriptionDetails) {
    $formattedPhone = formatPhoneNumber($userPhone);

    // Format dates to dd-mm-yyyy format
    $start_date_formatted = date('d-m-Y', strtotime($subscriptionDetails['start_date']));
    $end_date_formatted = date('d-m-Y', strtotime($subscriptionDetails['end_date']));

    // Calculate actual delivery days based on schedule
    $delivery_days = calculateDeliveryDays($subscriptionDetails['start_date'], $subscriptionDetails['end_date'], $subscriptionDetails['schedule']);

    $message = "🎉 *Subscription Confirmed!* 🎉\n\n";
    $message .= "*Plan:* {$subscriptionDetails['plan_name']}\n";
    $message .= "*Schedule:* {$subscriptionDetails['schedule']}\n";
    $message .= "*Duration:* {$delivery_days} delivery days ({$start_date_formatted} to {$end_date_formatted})\n";
    $message .= "*Amount Paid:* ₹{$subscriptionDetails['amount']}\n\n";
    $message .= "Thank you for subscribing to Tiffinly! Your meals will be delivered as per schedule.\n\n";
    $message .= "For any queries, please contact us at +91 1234567890 or support@tiffinly.com";

    return sendWhatsAppNotification($formattedPhone, $message);
}
