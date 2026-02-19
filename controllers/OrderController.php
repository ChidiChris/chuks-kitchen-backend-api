<?php
require_once 'config/db.php';

class OrderController {

    public static function create($body) {
        $db      = (new Database())->connect();
        $user_id = intval($body['user_id'] ?? 0);
        $payment = $body['payment_status'] ?? 'unpaid'; // simulated

        if (!$user_id)
            respond(false, "user_id is required", null, 400);

        // Fetch cart items
        $stmt = $db->prepare("
            SELECT c.food_id, c.quantity, f.price, f.name, f.is_available
            FROM cart c
            JOIN foods f ON c.food_id = f.id
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($cart_items))
            respond(false, "Cart is empty", null, 400);

        // Validate all items still available
        foreach ($cart_items as $item) {
            if (!$item['is_available'])
                respond(false, "'{$item['name']}' is no longer available. Please remove it from your cart.", null, 400);
        }

        // Calculate total
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart_items));

        // Simulate payment check
        if ($payment !== 'paid')
            respond(false, "Payment not completed. Please complete payment to place order.", null, 402);

        // Create order
        $stmt = $db->prepare("INSERT INTO orders (user_id, total_price, status, payment_status) VALUES (?,?,'pending','paid')");
        $stmt->bind_param("id", $user_id, $total);
        $stmt->execute();
        $order_id = $db->insert_id;

        // Insert order items
        foreach ($cart_items as $item) {
            $stmt = $db->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?,?,?,?)");
            $stmt->bind_param("iiid", $order_id, $item['food_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }

        // Clear cart after order
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        respond(true, "Order placed successfully", ["order_id" => $order_id, "total" => $total, "status" => "pending"], 201);
    }

    public static function get($order_id) {
        $db = (new Database())->connect();
        $order_id = intval($order_id);

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order)
            respond(false, "Order not found", null, 404);

        $stmt = $db->prepare("
            SELECT oi.quantity, oi.price, f.name
            FROM order_items oi
            JOIN foods f ON oi.food_id = f.id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        respond(true, "Order retrieved", $order);
    }

    public static function cancel($order_id) {
        $db = (new Database())->connect();
        $order_id = intval($order_id);

        $stmt = $db->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order)
            respond(false, "Order not found", null, 404);

        // Only allow cancel if still pending
        if (!in_array($order['status'], ['pending', 'confirmed']))
            respond(false, "Order cannot be cancelled at this stage (status: {$order['status']})", null, 400);

        $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        respond(true, "Order cancelled successfully");
    }
}