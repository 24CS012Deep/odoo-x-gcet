<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['profile_picture'];
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit();
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large']);
        exit();
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
    $upload_dir = '../assets/images/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $conn = getConnection();
        
        // Get old picture
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $old_picture = $result['profile_picture'];
        $stmt->close();
        
        // Delete old picture if not default
        if ($old_picture && $old_picture != 'default.png' && file_exists($upload_dir . $old_picture)) {
            unlink($upload_dir . $old_picture);
        }
        
        // Update database
        $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_filename, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Update session
        $_SESSION['profile_picture'] = $new_filename;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture updated',
            'image_url' => '../assets/images/profiles/' . $new_filename
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
}
?>