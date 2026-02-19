<?php
require_once 'config/db.php';

class AuthController {

    public static function signup($body) {
        $db   = (new Database())->connect();
        $name  = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $phone = trim($body['phone'] ?? '');
        $ref   = trim($body['referral_code'] ?? '');

        if (!$email && !$phone)
            respond(false, "Email or phone number is required", null, 400);

        // Check for duplicates
        if ($email) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0)
                respond(false, "Email already registered", null, 409);
        }

        if ($phone) {
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0)
                respond(false, "Phone number already registered", null, 409);
        }

        // Validate referral code if provided
        if ($ref) {
            $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->bind_param("s", $ref);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0)
                respond(false, "Invalid referral code", null, 400);
        }

        // Generate OTP and expiry
        $otp        = strval(rand(100000, 999999));
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $user_ref   = strtoupper(substr(md5(uniqid()), 0, 8)); // unique referral code for new user

        $stmt = $db->prepare("INSERT INTO users (name, email, phone, referral_code, otp, otp_expires_at) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssss", $name, $email, $phone, $user_ref, $otp, $otp_expiry);

        if ($stmt->execute()) {
            // In production, send OTP via email/SMS. Here we return it for simulation.
            respond(true, "Registration successful. Check your email for OTP.", [
                "otp_simulated" => $otp,   // Remove this in production!
                "user_referral_code" => $user_ref
            ], 201);
        }

        respond(false, "Registration failed", null, 500);
    }

    public static function verify($body) {
        $db    = (new Database())->connect();
        $email = trim($body['email'] ?? '');
        $otp   = trim($body['otp'] ?? '');

        if (!$email || !$otp)
            respond(false, "Email and OTP are required", null, 400);

        $stmt = $db->prepare("SELECT id, otp, otp_expires_at, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user)
            respond(false, "User not found", null, 404);

        if ($user['is_verified'])
            respond(false, "Account already verified", null, 400);

        if ($user['otp'] !== $otp)
            respond(false, "Invalid OTP", null, 400);

        if (strtotime($user['otp_expires_at']) < time())
            respond(false, "OTP has expired. Please request a new one.", null, 400);

        // Mark as verified
        $stmt = $db->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();

        respond(true, "Account verified successfully", ["user_id" => $user['id']]);
    }
}