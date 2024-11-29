<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '1234');
define('DB_NAME', 'abc_hospital_00');

// Establish database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Function to sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Function to check user role
function checkRole($allowedRoles) {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: index.php");
        exit();
    }
}

// Function to generate reference number for appointments
function generateReferenceNumber($patient_id, $doctor_id, $token_no, $appointment_doctor_id) {
    $reference = sprintf("REF-%04d-%04d-%03d-%04d", 
        $patient_id, 
        $doctor_id, 
        $token_no, 
        $appointment_doctor_id
    );
    return $reference;
}

// Function to decode reference number
function decodeReferenceNumber($reference) {
    if (preg_match('/REF-(\d{4})-(\d{4})-(\d{3})-(\d{4})/', $reference, $matches)) {
        return [
            'patient_id' => (int)$matches[1],
            'doctor_id' => (int)$matches[2],
            'token_no' => (int)$matches[3],
            'appointment_doctor_id' => (int)$matches[4]
        ];
    }
    return false;
}

// Function to format date
function formatDate($date) {
    return date('F d, Y', strtotime($date));
}

// Function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Function to check if appointment time is available
function isTimeSlotAvailable($doctor_id, $date, $time) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointment_doctor 
        WHERE doctor_id = ? AND date = ? AND time = ?
    ");
    $stmt->bind_param("iss", $doctor_id, $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $row['count'] == 0;
}

// Function to get user details
function getUserDetails($user_id, $role) {
    $conn = getDBConnection();
    $table = strtolower($role);
    
    $stmt = $conn->prepare("
        SELECT * FROM {$table} 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $details;
}





// Add these functions in config.php

// Function to get doctor's available time slots
function getAvailableTimeSlots($doctor_id, $date) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT time 
        FROM appointment_doctor 
        WHERE doctor_id = ? 
        AND date = ? 
        AND appointment_id IS NULL 
        ORDER BY time ASC
    ");
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $timeSlots = [];
    while($row = $result->fetch_assoc()) {
        $timeSlots[] = $row['time'];
    }
    
    $stmt->close();
    $conn->close();
    
    return $timeSlots;
}

// Function to get doctors by specialization with AJAX response format
function getDoctorsBySpecializationAjax($specialization_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT id, name
        FROM doctor 
        WHERE specialization_id = ?
    ");
    $stmt->bind_param("i", $specialization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $doctors = [];
    while($row = $result->fetch_assoc()) {
        $doctors[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    return $doctors;
}









// Function to check if specialization exists
function getSpecializationById($id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT title FROM specialization WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $row ? $row['title'] : null;
}

// Function to get all doctors by specialization
function getDoctorsBySpecialization($specialization_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT d.id, d.name, d.email, d.contactNo, s.title as specialization
        FROM doctor d
        JOIN specialization s ON d.specialization_id = s.id
        WHERE d.specialization_id = ?
    ");
    $stmt->bind_param("i", $specialization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    $stmt->close();
    $conn->close();
    
    return $doctors;
}

// Function to get all specializations
function getAllSpecializations() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM specialization ORDER BY title");
    $specializations = [];
    while ($row = $result->fetch_assoc()) {
        $specializations[] = $row;
    }
    $conn->close();
    
    return $specializations;
}

// Common CSS styles
function getCommonCSS() {
    return '
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: Arial, sans-serif;
            }
            
            body {
                background-color: #f4f4f4;
                line-height: 1.6;
            }
            
            .container {
                width: 90%;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .navbar {
                background-color: #2c3e50;
                padding: 1rem;
                color: white;
            }
            
            .navbar a {
                color: white;
                text-decoration: none;
                padding: 0.5rem 1rem;
            }
            
            .navbar a:hover {
                background-color: #34495e;
                border-radius: 4px;
            }
            
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                border: none;
                cursor: pointer;
            }
            
            .btn:hover {
                background-color: #2980b9;
            }
            
            .btn-danger {
                background-color: #e74c3c;
            }
            
            .btn-danger:hover {
                background-color: #c0392b;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-control {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-top: 5px;
            }
            
            .alert {
                padding: 1rem;
                margin-bottom: 1rem;
                border-radius: 4px;
            }
            
            .alert-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .alert-danger {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 1rem 0;
            }
            
            table th, table td {
                padding: 12px;
                text-align: left;
                border: 1px solid #ddd;
            }
            
            table th {
                background-color: #f8f9fa;
            }
            
            .card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .text-center {
                text-align: center;
            }
        </style>
    ';
}

// Function to display messages
function displayMessage($message, $type = 'success') {
    return "<div class='alert alert-{$type}'>{$message}</div>";
}
?>