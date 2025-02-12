<?php
// Set the content type to JSON for the response
header('Content-Type: application/json');

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the JSON data from the request body
    $json_data = file_get_contents('php://input');

    // Decode the JSON data into a PHP associative array
    $data = json_decode($json_data, true);

    // Check if JSON decoding was successful
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        // JSON decoding failed
        http_response_code(400); // Bad Request
        $error_message = 'Invalid JSON data: ' . json_last_error_msg();
        error_log($error_message);  // Log the JSON error
        echo json_encode(['error' => $error_message]);
        exit;
    }

    // Generate a unique filename based on the current timestamp
    $timestamp = time();
    $filename = "b2c_timeout_" . $timestamp . ".json";
    $filePath = __DIR__ . "/" . $filename; // Store files in the same directory as the PHP script

    // Write JSON data to file
    try {
        if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT))) {
            // Log the file creation and data
            $log_message = "File $filename created at: " . date("Y-m-d H:i:s") . PHP_EOL;
            $log_message .= "Data: " . $json_data . PHP_EOL;  // Log the entire JSON data
            file_put_contents("log_b2c_timeout_file_creation.log", $log_message, FILE_APPEND);

            // Example response
            $response = [
                'status' => 'success',
                'message' => 'B2C timeout data received and stored',
                'file' => $filename,
            ];
        } else {
            // Failed to create the file
            http_response_code(500); // Internal Server Error
            $response = [
                'status' => 'error',
                'message' => 'Failed to store B2C timeout data on the server',
            ];
            error_log("Failed to write data to file: $filePath"); // Log the file writing error
        }
    } catch (Exception $e) {
        // Catch any other exceptions during file writing
        http_response_code(500); // Internal Server Error
        $response = [
            'status' => 'error',
            'message' => 'Failed to store B2C timeout data on the server: ' . $e->getMessage(),
        ];
        error_log("Exception during file writing: " . $e->getMessage());
    }

    // Send a JSON response back to the client
    echo json_encode($response);
} else {
    // Not a POST request
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
}
?>