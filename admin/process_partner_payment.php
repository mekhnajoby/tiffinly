<?php
// This file contains the payment calculation functions
// It should be included by other files that need these functions

// Database connection function
function getDBConnection() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli('localhost', 'root', '', 'tiffinly');
        if ($db->connect_error) {
            die("Connection failed: " . $db->connect_error);
        }
    }
    return $db;
}

// Base rates per delivery (in INR)
$base_rates = [
    'basic' => 25,    // Increased from 15 to 25
    'standard' => 30, // Increased from 18 to 30
    'premium' => 35   // Increased from 20 to 35
];

// Distance rate per km (one way)
define('DISTANCE_RATE', 6);  // Increased from 5 to 6 per km

// Bonus percentages (kept same but will be more meaningful with higher base rates)
const BONUS_ON_TIME = 0.05;    // 5% for on-time delivery
const BONUS_HIGH_RATING = 0.05; // 5% for high rating (≥4.5)
const BONUS_BULK = 0.05;        // 5% for bulk deliveries (≥10)

// Minimum delivery amount (to ensure partners earn fairly)
define('MIN_DAILY_EARNING', 250);  // Minimum ₹250 per day

/**
 * Calculate partner payment based on deliveries
 * @param int $partner_id Partner ID
 * @param string $delivery_date Delivery date (Y-m-d)
 * @return array [total_amount, delivery_count, details, subscription_id]
 */
function calculatePartnerPayment($partner_id, $delivery_date) {
    $db = getDBConnection();
    
    // Base rates per delivery (in INR)
    $base_rates = [
        'basic' => 25,    // Basic plan rate per delivery
        'standard' => 30, // Standard plan rate per delivery
        'premium' => 35   // Premium plan rate per delivery
    ];
    
    // Distance rate per km (one way)
    $distance_rate = 6;
    
    // Bonus percentages
    $bonus_on_time = 0.05;    // 5% for on-time delivery
    $bonus_high_rating = 0.05; // 5% for high rating (≥4.5)
    $bonus_bulk = 0.05;        // 5% for bulk deliveries (≥10)
    
    $partner_id = (int)$partner_id;
    $delivery_date = $db->real_escape_string($delivery_date);
    // Fetch all delivered assignments for the partner on the given date with subscription and plan details
    $stmt = $db->prepare("
        SELECT 
            da.assignment_id, 
            da.subscription_id, 
            da.meal_type, 
            mp.plan_type, 
            a.distance_km, 
            mp.plan_id,
            da.delivery_time, 
            da.actual_delivery_time,
            mp.plan_name,
            mp.price as plan_price
        FROM delivery_assignments da
        JOIN deliveries d ON da.subscription_id = d.subscription_id AND da.delivery_date = d.delivery_date
        JOIN subscriptions s ON da.subscription_id = s.subscription_id
        JOIN meal_plans mp ON s.plan_id = mp.plan_id
        LEFT JOIN addresses a ON s.delivery_address_id = a.address_id
        WHERE da.partner_id = ? 
          AND DATE(da.delivery_date) = ?
          AND da.status = 'delivered'
          AND da.payment_status = 'unpaid'
    ");
    $stmt->bind_param("is", $partner_id, $delivery_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deliveries = [];
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }
    
    $total_amount = 0;
    $delivery_count = 0;
    $on_time_deliveries = 0;
    $subscription_ids = [];
    $details = [];
    $total_deliveries = count($deliveries);
    $plan_breakdown = [
        'basic' => 0,
        'standard' => 0,
        'premium' => 0
    ];
    
    // Calculate base amount and on-time deliveries
    $base_amount = 0;
    $distance_amount = 0;
    $on_time_deliveries = 0;
    $total_distance = 0;
    
    foreach ($deliveries as $delivery) {
        // Track subscription IDs
        if (!in_array($delivery['subscription_id'], $subscription_ids)) {
            $subscription_ids[] = $delivery['subscription_id'];
        }
        
        // Calculate base amount based on plan type
        $plan_type = strtolower($delivery['plan_type'] ?? 'basic');
        $plan_type = in_array($plan_type, ['basic', 'standard', 'premium']) ? $plan_type : 'basic';
        $rate = $base_rates[$plan_type];
        $plan_breakdown[$plan_type]++;
        $base_amount += $rate;
        
        // Calculate distance amount (one way, only for distance > 3km)
        $distance = (float)($delivery['distance_km'] ?? 0);
        if ($distance > 3) {
            $distance_amount += ($distance - 3) * $distance_rate;
        }
        $total_distance = max($total_distance, $distance);
        
        // Check if delivery was on time
        $on_time = false;
        if ($delivery['delivery_time'] && $delivery['actual_delivery_time']) {
            $scheduled = new DateTime($delivery['delivery_time']);
            $actual = new DateTime($delivery['actual_delivery_time']);
            $diff = $scheduled->diff($actual);
            $minutes = $diff->i + ($diff->h * 60);
            $on_time = $minutes <= 30;
            if ($on_time) $on_time_deliveries++;
        }
        
        // Store delivery details
        $details[] = [
            'subscription_id' => $delivery['subscription_id'],
            'meal_type' => $delivery['meal_type'],
            'plan_type' => $plan_type,
            'distance_km' => $distance,
            'base_rate' => $rate,
            'distance_amount' => ($distance > 3) ? ($distance - 3) * $distance_rate : 0,
            'on_time' => $on_time,
            'delivery_amount' => $rate + (($distance > 3) ? ($distance - 3) * $distance_rate : 0),
            'plan_price' => $delivery['plan_price'] ?? 0
        ];
    }
    
    // Calculate on-time percentage
    $on_time_percentage = $total_deliveries > 0 ? ($on_time_deliveries / $total_deliveries) * 100 : 0;
    
    // Calculate total amount before bonuses
    $total_amount = $base_amount + $distance_amount;
    
    // Get average rating for the partner (last 30 days)
    $rating_stmt = $db->prepare("
        SELECT AVG(rating) as avg_rating
        FROM feedback
        WHERE partner_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $rating_stmt->bind_param("i", $partner_id);
    $rating_stmt->execute();
    $rating_result = $rating_stmt->get_result()->fetch_assoc();
    $avg_rating = $rating_result['avg_rating'] ?? 0;
    
    // Calculate bonuses
    $bonuses = [
        'on_time' => 0,
        'high_rating' => 0,
        'bulk' => 0,
        'total_bonus' => 0
    ];
    
    // On-time delivery bonus (5% if more than 70% on-time)
    if ($total_deliveries > 0 && $on_time_percentage >= 70) {
        $bonuses['on_time'] = $base_amount * BONUS_ON_TIME;
    }
    
    // High rating bonus (5% if rating >= 4.5)
    if ($avg_rating >= 4.5) {
        $bonuses['high_rating'] = $base_amount * BONUS_HIGH_RATING;
    }
    
    // Bulk delivery bonus (tiered)
    if ($total_deliveries >= 20) {
        $bonuses['bulk'] = $base_amount * BONUS_BULK; // 5%
    } elseif ($total_deliveries >= 16) {
        $bonuses['bulk'] = $base_amount * (BONUS_BULK * 0.75); // 3.75%
    } elseif ($total_deliveries >= 10) {
        $bonuses['bulk'] = $base_amount * (BONUS_BULK * 0.5); // 2.5%
    }
    
    // Calculate total bonus
    $total_bonus = array_sum($bonuses);
    $bonuses['total_bonus'] = $total_bonus;
    
    // Calculate final amount with base, distance, and bonuses
    $total_amount = $base_amount + $distance_amount + $total_bonus;
    
    // Ensure minimum daily earning if there are deliveries
    if ($total_amount < MIN_DAILY_EARNING && $total_deliveries > 0) {
        $bonuses['min_earn_adjustment'] = MIN_DAILY_EARNING - $total_amount;
        $total_amount = MIN_DAILY_EARNING;
    }
    
    // Prepare metrics for response
    $metrics = [
        'on_time_percentage' => round($on_time_percentage, 1),
        'avg_rating' => round($avg_rating, 1),
        'total_distance' => round($total_distance, 2),
        'delivery_count' => $total_deliveries,
        'plan_breakdown' => $plan_breakdown
    ];
    
    // Return payment details
    return [
        'total_amount' => round($total_amount, 2),
        'base_amount' => round($base_amount, 2),
        'distance_amount' => round($distance_amount, 2),
        'bonuses' => [
            'on_time' => round($bonuses['on_time'], 2),
            'high_rating' => round($bonuses['high_rating'], 2),
            'bulk' => round($bonuses['bulk'], 2),
            'total' => round($total_bonus, 2)
        ],
        'delivery_count' => $total_deliveries,
        'on_time_percentage' => $on_time_percentage,
        'avg_rating' => round($avg_rating, 1),
        'details' => $details,
        'subscription_id' => !empty($subscription_ids) ? $subscription_ids[0] : null,
        'metrics' => $metrics
    ];
}

function processPartnerPayment($partner_id, $delivery_date) {
    $db = getDBConnection();
    
    // Calculate payment based on deliveries
    $payment_info = calculatePartnerPayment($partner_id, $delivery_date);
    
    if ($payment_info['delivery_count'] === 0) {
        throw new Exception("No unpaid deliveries found for the selected partner and date.");
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Insert payment record
        $stmt = $db->prepare("
            INSERT INTO partner_payments 
            (partner_id, subscription_id, payment_date, amount, base_amount, 
             bonus_amount, delivery_count, distance_amount, status) 
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, 'completed')
        ");
        
        $stmt->bind_param(
            "iiddddi", 
            $partner_id,
            $payment_info['subscription_id'],
            $payment_info['total_amount'],
            $payment_info['base_amount'],
            $payment_info['bonuses']['total'],
            $payment_info['delivery_count'],
            $payment_info['distance_amount']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record payment: " . $db->error);
        }
        
        $payment_id = $db->insert_id;
        
        // Update delivery assignments to mark as paid
        $update_stmt = $db->prepare("
            UPDATE delivery_assignments 
            SET payment_status = 'paid', 
                payment_id = ?,
                payment_date = NOW()
            WHERE partner_id = ? 
            AND DATE(delivery_date) = ?
            AND status = 'delivered'
            AND payment_status = 'unpaid'");
        
        $update_stmt->bind_param("iis", $payment_id, $partner_id, $delivery_date);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update delivery records: " . $db->error);
        }
        
        // Commit transaction
        $db->commit();
        
        // Prepare success message with payment details
        $message = "Payment of ₹" . number_format($payment_info['total_amount'], 2) . 
                  " to partner ID #" . $partner_id . 
                  " for " . $payment_info['delivery_count'] . " deliveries on " . 
                  date('M d, Y', strtotime($delivery_date)) . " has been processed successfully.\n\n";
        
        // Add payment breakdown
        $message .= "Payment Breakdown:\n";
        $message .= "- Base Amount: ₹" . number_format($payment_info['base_amount'], 2) . "\n";
        $message .= "- Distance Amount: ₹" . number_format($payment_info['distance_amount'], 2) . "\n";
        
        // Add bonus details
        $bonus_details = [];
        if ($payment_info['bonuses']['on_time'] > 0) {
            $bonus_details[] = "On-time delivery bonus: ₹" . number_format($payment_info['bonuses']['on_time'], 2) . 
                             " (" . $payment_info['metrics']['on_time_percentage'] . "% on-time)";
        }
        if ($payment_info['bonuses']['high_rating'] > 0) {
            $bonus_details[] = "High rating bonus: ₹" . number_format($payment_info['bonuses']['high_rating'], 2) . 
                             " (Avg rating: " . $payment_info['metrics']['avg_rating'] . ")";
        }
        if ($payment_info['bonuses']['bulk'] > 0) {
            $bonus_details[] = "Bulk delivery bonus: ₹" . number_format($payment_info['bonuses']['bulk'], 2) . 
                             " (" . $payment_info['delivery_count'] . " deliveries)";
        }
        
        if (!empty($bonus_details)) {
            $message .= "\nBonuses Applied:\n- " . implode("\n- ", $bonus_details);
        }
        
        // Add total bonuses
        $message .= "\n\nTotal Bonuses: ₹" . number_format($payment_info['bonuses']['total'], 2);
        
        $_SESSION['success_message'] = $message;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        $_SESSION['error_message'] = "Payment processing failed: " . $e->getMessage();
    }
    
    header("Location: manage_partners.php");
    exit();
}
?>