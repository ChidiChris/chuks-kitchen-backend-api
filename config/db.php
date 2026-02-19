<?php
class Database {
    private $host = "localhost";
    private $db   = "chuks_kitchen";
    private $user = "root";
    private $pass = "";
    public $conn;

    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db);
        if ($this->conn->connect_error) {
            die(json_encode(["success" => false, "message" => "DB connection failed"]));
        }
        return $this->conn;
    }
}