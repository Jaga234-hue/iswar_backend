<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get all POST data
$userInput = $_POST['userInput'] ?? '';
$threadId = $_POST['thread_id'] ?? '45';
$userName = $_POST['user_name'] ?? '';

// Validate input
if (empty($userInput)) {
    echo json_encode(["response" => "Error: No user input provided", "status" => "error"]);
    exit;
}

// Python command
$pythonCommand = '"C:\xampp\htdocs\CHATBOT\iswar_backend\myenv\Scripts\python.exe" main.py';

$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open($pythonCommand, $descriptorspec, $pipes, __DIR__);

if (is_resource($process)) {
    // Send data to Python as JSON
    $inputData = json_encode([
        "userInput" => $userInput,
        "thread_id" => $threadId,
        "user_name" => $userName
    ]);
    
    fwrite($pipes[0], $inputData);
    fclose($pipes[0]);

    // Read stdout
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    // Read stderr
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $return_value = proc_close($process);

    if ($error) {
        error_log("Python Error: " . $error);
        echo json_encode(["response" => "Error from Python: " . $error, "status" => "error"]);
    } else if ($output) {
        // Return the Python output directly
        echo $output;
    } else {
        echo json_encode(["response" => "No response from Python script.", "status" => "error"]);
    }
} else {
    echo json_encode(["response" => "Failed to run Python script.", "status" => "error"]);
}
?>