<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// File path
$file = 'user.csv';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log for debugging (remove in production)
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Input: " . $input . "\n", FILE_APPEND);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
    exit;
}

$username = isset($data['user_name']) ? trim($data['user_name']) : '';

if ($username === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username not provided"]);
    exit;
}

// Validate username
if (strlen($username) < 2 || strlen($username) > 50) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username must be between 2 and 50 characters"]);
    exit;
}

// Validate username format (alphanumeric and underscores only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username can only contain letters, numbers, and underscores"]);
    exit;
}

try {
    // Load existing users from CSV
    $users = [];
    if (file_exists($file)) {
        $fileHandle = fopen($file, 'r');
        if ($fileHandle) {
            while (($row = fgetcsv($fileHandle)) !== false) {
                if (count($row) >= 2) {
                    $users[] = $row;
                }
            }
            fclose($fileHandle);
        }
    }

    // Check if username already exists
    foreach ($users as $user) {
        if (isset($user[1]) && strtolower($user[1]) === strtolower($username)) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Username already exists"]);
            exit;
        }
    }

    // Generate new ID
    $newId = count($users) > 0 ? (int)$users[count($users) - 1][0] + 1 : 1;

    // Add new user
    $fileHandle = fopen($file, 'a');
    if ($fileHandle) {
        fputcsv($fileHandle, [$newId, $username]);
        fclose($fileHandle);
        
        // Return success response with user data
        http_response_code(200);
        echo json_encode([
            "status" => "success", 
            "message" => "User enrolled successfully",
            "user_id" => $newId,
            "username" => $username
        ]);
        
        // Log success
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - User enrolled: " . $username . " (ID: " . $newId . ")\n", FILE_APPEND);
    } else {
        throw new Exception("Cannot open file for writing");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
    
    // Log error
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>