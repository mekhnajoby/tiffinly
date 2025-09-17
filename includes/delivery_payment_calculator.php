<?php
/**
 * Delivery Payment Calculator
 * Handles calculation of delivery partner payments based on the new pricing structure
 */

class DeliveryPaymentCalculator {
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    /**
     * Calculate payment for a delivery partner for a specific date range
     * 
     * @param int $partner_id Delivery partner ID
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array Payment details including amount and breakdown
     */
    public function calculatePayment($partner_id, $start_date, $end_date) {
        // Get all delivered orders for the partner in the date range
        $deliveries = $this->getDeliveries($partner_id, $start_date, $end_date);
        
        // Group deliveries by date
        $deliveries_by_date = [];
        foreach ($deliveries as $delivery) {
            $date = $delivery['delivery_date'];
            if (!isset($deliveries_by_date[$date])) {
                $deliveries_by_date[$date] = [];
            }
            $deliveries_by_date[$date][] = $delivery;
        }
        
        // Calculate payment for each day
        $total_payment = 0;
        $daily_breakdown = [];
        
        foreach ($deliveries_by_date as $date => $day_deliveries) {
            $delivery_count = count($day_deliveries);
            $day_payment = $this->calculateDailyPayment($delivery_count, $date);
            
            $daily_breakdown[] = [
                'date' => $date,
                'delivery_count' => $delivery_count,
                'payment' => $day_payment
            ];
            
            $total_payment += $day_payment;
        }
        
        return [
            'total_payment' => $total_payment,
            'total_deliveries' => count($deliveries),
            'days_worked' => count($deliveries_by_date),
            'daily_breakdown' => $daily_breakdown,
            'payment_date' => date('Y-m-d')
        ];
    }
    
    /**
     * Get all delivered orders for a partner in a date range
     */
    private function getDeliveries($partner_id, $start_date, $end_date) {
        $sql = "SELECT da.assignment_id, da.delivery_date, da.status, da.meal_type,
                       s.subscription_id, s.plan_id, s.dietary_preference
                FROM delivery_assignments da
                JOIN subscriptions s ON da.subscription_id = s.subscription_id
                WHERE da.partner_id = ?
                AND da.delivery_date BETWEEN ? AND ?
                AND da.status = 'delivered'
                ORDER BY da.delivery_date, da.meal_type";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iss", $partner_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Calculate payment for a single day
     */
    private function calculateDailyPayment($delivery_count, $date) {
        // Get the active rate for the date
        $sql = "SELECT base_rate, bonus_rate, bonus_threshold, min_guarantee
                FROM delivery_rates
                WHERE effective_from <= ?
                AND is_active = 1
                ORDER BY effective_from DESC
                LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $rate = $stmt->get_result()->fetch_assoc();
        
        if (!$rate) {
            // Default rates if none found in the database
                $rate = [
                    'base_rate' => 40.00,
                    'bonus_rate' => 0.00,
                    'bonus_threshold' => 0,
                    'min_guarantee' => 0.00
                ];
        }
        
        // Calculate base payment
        $payment = $delivery_count * $rate['base_rate'];
        
        // Add bonus for deliveries above threshold
        if ($delivery_count > $rate['bonus_threshold']) {
            $bonus_deliveries = $delivery_count - $rate['bonus_threshold'];
            $payment += $bonus_deliveries * $rate['bonus_rate'];
        }
        
        // Apply minimum guarantee
        if ($payment < $rate['min_guarantee'] && $delivery_count > 0) {
            $payment = $rate['min_guarantee'];
        }
        
        return round($payment, 2);
    }
    
    /**
     * Record payment in the database
     */
    public function recordPayment($partner_id, $amount, $start_date, $end_date, $delivery_count) {
        $sql = "INSERT INTO partner_payments 
                (partner_id, amount, delivery_count, payment_method, payment_status, created_at)
                VALUES (?, ?, ?, 'system', 'success', NOW())";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idi", $partner_id, $amount, $delivery_count);
        return $stmt->execute();
    }
}
