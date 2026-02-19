<?php
require_once 'config/db.php';

class FoodController {

    public static function list() {
        $db   = (new Database())->connect();
        $result = $db->query("SELECT * FROM foods WHERE is_available = 1 ORDER BY created_at DESC");
        $foods = $result->fetch_all(MYSQLI_ASSOC);
        respond(true, "Food items retrieved", $foods);
    }

    public static function add($body) {
        $db   = (new Database())->connect();
        $name  = trim($body['name'] ?? '');
        $desc  = trim($body['description'] ?? '');
        $price = floatval($body['price'] ?? 0);
        $cat   = trim($body['category'] ?? '');

        if (!$name || $price <= 0)
            respond(false, "Name and valid price are required", null, 400);

        $stmt = $db->prepare("INSERT INTO foods (name, description, price, category) VALUES (?,?,?,?)");
        $stmt->bind_param("ssds", $name, $desc, $price, $cat);

        if ($stmt->execute())
            respond(true, "Food item added", ["id" => $db->insert_id], 201);

        respond(false, "Failed to add food item", null, 500);
    }
}