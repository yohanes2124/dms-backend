<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Simple API responses for testing
$path = $_SERVER['REQUEST_URI'];

if (strpos($path, '/api/auth/login') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Demo login logic
        $validUsers = [
            'admin@university.edu' => ['password' => 'admin123', 'type' => 'admin', 'name' => 'Admin User'],
            'supervisor.a@university.edu' => ['password' => 'supervisor123', 'type' => 'supervisor', 'name' => 'John Supervisor'],
            'alice.johnson@student.university.edu' => ['password' => 'student123', 'type' => 'student', 'name' => 'Alice Johnson']
        ];
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (isset($validUsers[$email]) && $validUsers[$email]['password'] === $password) {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => 1,
                        'name' => $validUsers[$email]['name'],
                        'email' => $email,
                        'user_type' => $validUsers[$email]['type']
                    ],
                    'token' => 'demo-jwt-token-' . time()
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
        }
    }
} elseif (strpos($path, '/api/auth/register') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'id' => rand(100, 999),
                    'name' => $input['name'] ?? 'New User',
                    'email' => $input['email'] ?? 'user@example.com',
                    'user_type' => $input['user_type'] ?? 'student'
                ],
                'token' => 'demo-jwt-token-' . time()
            ]
        ]);
    }
} elseif (strpos($path, '/api/applications') !== false) {
    echo json_encode([
        'success' => true,
        'data' => [
            'data' => [],
            'message' => 'Applications endpoint working'
        ]
    ]);
} else {
    // Default API response
    echo json_encode([
        'success' => true,
        'message' => 'DMS API is working!',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /api/auth/login',
            'POST /api/auth/register',
            'GET /api/applications'
        ]
    ]);
}
?>