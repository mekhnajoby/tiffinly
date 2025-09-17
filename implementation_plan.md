# Implementation Plan for Delivery Tracking Fixes

## Issues with track_order.php

1. The query is not properly joining tables to get active orders
2. The condition for showing "No Active Orders" is not working correctly
3. The file is not properly displaying delivery partner information
4. The AJAX endpoint for order tracking references a non-existent table

## Proposed Fixes

### 1. Fix track_order.php query
The current query needs to be updated to properly join tables and get active orders:

```php
// Current problematic query:
$sql = "
    SELECT
        s.subscription_id, s.plan_id, s.dietary_preference, s.schedule,
        d.status AS delivery_status,
        da.partner_id,
        p.name AS partner_name, p.phone AS partner_phone,
        dpd.vehicle_type, dpd.vehicle_number,
        dpref.time_slot,
        d.delivery_date,
        da.meal_type
    FROM subscriptions s
    LEFT JOIN delivery_assignments da ON s.subscription_id = da.subscription_id
    LEFT JOIN deliveries d ON s.subscription_id = d.subscription_id AND d.delivery_date = CURDATE()
    LEFT JOIN users p ON da.partner_id = p.user_id AND p.role = 'delivery'
    LEFT JOIN delivery_partner_details dpd ON da.partner_id = dpd.partner_id
    LEFT JOIN delivery_preferences dpref ON s.user_id = dpref.user_id AND da.meal_type = dpref.meal_type
    WHERE s.user_id = ?
      AND s.status = 'active'
      AND s.payment_status = 'paid'
      AND CURDATE() BETWEEN s.start_date AND s.end_date
    ORDER BY FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner')
";

// Issues:
// 1. It's only looking for deliveries for today, but not checking if there are any assigned deliveries
// 2. It's not properly handling the case where there's an active subscription but no assigned deliveries yet
```

### 2. Fix the display logic
The display logic needs to be updated to properly show:
- Active orders with assigned delivery partners
- Active orders without assigned delivery partners (awaiting assignment)
- Proper "No Active Orders" message when there are no active subscriptions

### 3. Fix AJAX endpoint
The AJAX endpoint needs to be updated to work with the existing tables instead of the non-existent "orders" table.

## Implementation Steps

### Step 1: Update track_order.php
1. Fix the main query to properly get active subscriptions
2. Update the display logic to handle different states:
   - Active subscription with assigned delivery partner
   - Active subscription without assigned delivery partner
   - No active subscriptions
3. Ensure proper error handling

### Step 2: Update available_orders.php
1. Fix the delivery assignment process to properly insert records with AUTO_INCREMENT IDs
2. Ensure proper handling of meal types when assigning orders

### Step 3: Update my_deliveries.php
1. Fix the query to properly get assigned deliveries
2. Ensure proper display of delivery information

### Step 4: Update update_delivery_status.php
1. Ensure it works with the corrected database schema

### Step 5: Fix AJAX endpoint
1. Update get_order_status.php to work with existing tables