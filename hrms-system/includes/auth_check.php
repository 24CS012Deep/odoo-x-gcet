<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    $today = date('Y-m-d');
    
    if ($action == 'checkin') {
        // Check if already checked in today
        $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $check_stmt->bind_param("is", $user_id, $today);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Already checked in today']);
            $check_stmt->close();
            $conn->close();
            exit();
        }
        $check_stmt->close();
        
        // Insert check-in record
        $current_time = date('Y-m-d H:i:s');
        $insert_stmt = $conn->prepare("
            INSERT INTO attendance (user_id, check_in, status, date) 
            VALUES (?, ?, 'present', ?)
        ");
        $insert_stmt->bind_param("iss", $user_id, $current_time, $today);
        
        if ($insert_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Checked in successfully',
                'time' => date('h:i A', strtotime($current_time))
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to check in']);
        }
        $insert_stmt->close();
        
    } elseif ($action == 'checkout') {
        // Get today's check-in record
        $check_stmt = $conn->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND date = ? AND check_out IS NULL");
        $check_stmt->bind_param("is", $user_id, $today);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'No check-in found for today']);
            $check_stmt->close();
            $conn->close();
            exit();
        }
        
        $attendance = $result->fetch_assoc();
        $check_stmt->close();
        
        // Calculate hours worked
        $current_time = date('Y-m-d H:i:s');
        $check_in_time = strtotime($attendance['check_in']);
        $check_out_time = strtotime($current_time);
        $hours_worked = ($check_out_time - $check_in_time) / 3600;
        
        // Update check-out time and hours worked
        $update_stmt = $conn->prepare("
            UPDATE attendance 
            SET check_out = ?, hours_worked = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("sdi", $current_time, $hours_worked, $attendance['id']);
        
        if ($update_stmt->execute()) {
            // If worked less than 4 hours, mark as half-day
            if ($hours_worked < 4) {
                $status_stmt = $conn->prepare("UPDATE attendance SET status = 'half-day' WHERE id = ?");
                $status_stmt->bind_param("i", $attendance['id']);
                $status_stmt->execute();
                $status_stmt->close();
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Checked out successfully',
                'time' => date('h:i A', strtotime($current_time)),
                'hours' => round($hours_worked, 2)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to check out']);
        }
        $update_stmt->close();
        
    } elseif ($action == 'manual_checkout' && isset($_GET['id'])) {
        $attendance_id = intval($_GET['id']);
        
        // Verify ownership
        $verify_stmt = $conn->prepare("SELECT id, check_in FROM attendance WHERE id = ? AND user_id = ? AND check_out IS NULL");
        $verify_stmt->bind_param("ii", $attendance_id, $user_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid attendance record']);
            $verify_stmt->close();
            $conn->close();
            exit();
        }
        
        $attendance = $result->fetch_assoc();
        $verify_stmt->close();
        
        // Set check-out to end of working day (5:00 PM)
        $check_in_date = date('Y-m-d', strtotime($attendance['check_in']));
        $check_out_time = $check_in_date . ' 17:00:00';
        
        $check_in_time = strtotime($attendance['check_in']);
        $check_out_time_stamp = strtotime($check_out_time);
        $hours_worked = ($check_out_time_stamp - $check_in_time) / 3600;
        
        $update_stmt = $conn->prepare("
            UPDATE attendance 
            SET check_out = ?, hours_worked = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("sdi", $check_out_time, $hours_worked, $attendance_id);
        
        if ($update_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Manual check-out recorded',
                'time' => date('h:i A', strtotime($check_out_time))
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update record']);
        }
        $update_stmt->close();
    }
}

$conn->close();
?>