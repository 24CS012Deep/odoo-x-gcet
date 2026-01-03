<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit();
}

$conn = getConnection();

// Get current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

// For demo purposes - in production, use password_verify()
if ($current_password == 'demo123' || password_verify($current_password, $result['password'])) {
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
}

$conn->close();
?>