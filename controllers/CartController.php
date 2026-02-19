<?php
require_once 'config/db.php';

class CartController {

    public static function add($body) {
        $db      = (new Database())->connect();
        $user_id = intval($body['user_id'] ?? 0);
        $food_id = intval($body['food_id'] ?? 0);
        $qty     = intval($body['quantity'] ?? 1);

        if (!$user_id || !$food_id)
            respond(false, "user_id and food_id are required", null, 400);

        // Check food availability
        $stmt = $db->prepare("SELECT id, is_available FROM foods WHERE id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $food = $stmt->get_result()->fetch_assoc();

        if (!$food)
            respond(false, "Food item not found", null, 404);

        if (!$food['is_available'])
            respond(false, "Sorry, this item is currently unavailable", null, 400);

        // Check if already in cart — update quantity instead
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ?");
        $stmt->bind_param("ii", $user_id, $food_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $new_qty = $existing['quantity'] + $qty;
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_qty, $existing['id']);
            $stmt->execute();
            respond(true, "Cart updated", ["cart_id" => $existing['id']]);
        }

        $stmt = $db->prepare("INSERT INTO cart (user_id, food_id, quantity) VALUES (?,?,?)");
        $stmt->bind_param("iii", $user_id, $food_id, $qty);

        if ($stmt->execute())
            respond(true, "Item added to cart", ["cart_id" => $db->insert_id], 201);

        respond(false, "Failed to add to cart", null, 500);
    }

    public static function view($user_id) {
        $db = (new Database())->connect();
        $user_id = intval($user_id);

        $stmt = $db->prepare("
            SELECT c.id, f.name, f.price, c.quantity, (f.price * c.quantity) AS subtotal, f.is_available
            FROM cart c
            JOIN foods f ON c.food_id = f.id
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total = array_sum(array_column($items, 'subtotal'));

        respond(true, "Cart retrieved", ["items" => $items, "total" => $total]);
    }

    public static function clear($user_id) {
        $db = (new Database())->connect();
        $user_id = intval($user_id);

        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        respond(true, "Cart cleared");
    }
}