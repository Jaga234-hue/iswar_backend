<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get all POST data
$userInput = $_POST['userInput'] ?? '';
$threadId = $_POST['thread_id'] ?? '';
$userName = $_POST['user_name'] ?? '';

// if thread id is 0, create a new thread id rand between 100000 to 999999 

// Validate input
if (empty($userInput)) {
    echo json_encode(["response" => "Error: No user input provided"]);
    exit;
}

// USE THIS EXACT PATH to your virtual environment Python
$pythonCommand = '"C:\xampp\htdocs\Project\chatenv\Scripts\python.exe" main.py';

$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open($pythonCommand, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Send ALL data to Python as JSON
    $inputData = json_encode([
        "userInput" => $userInput,
        "thread_id" => $threadId,
        "user_name" => $userName
    ]);
    
    fwrite($pipes[0], $inputData);
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $return_value = proc_close($process);

    if ($error) {
        echo json_encode(["response" => "Error from Python: " . $error]);
    } else {
        // Return the raw output or parse as JSON
        if ($output) {
            $decoded = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['response'])) {
                echo $output; // Already valid JSON
            } else {
                echo json_encode(["response" => $output]);
            }
        } else {
            echo json_encode(["response" => "No response from Python script."]);
        }
    }
} else {
    echo json_encode(["response" => "Failed to run Python script."]);
}
?>