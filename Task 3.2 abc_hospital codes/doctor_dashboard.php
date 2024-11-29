<?php
require_once 'config.php';

// Check if user is logged in and is a doctor
checkRole(['doctor']);

$doctor_id = null;
$doctor_details = null;
$success_message = '';
$error_message = '';

// Get doctor details
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT d.*, s.title as specialization FROM doctor d 
                       JOIN specialization s ON d.specialization_id = s.id 
                       WHERE d.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$doctor_details = $stmt->get_result()->fetch_assoc();
$doctor_id = $doctor_details['id'];

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $contact = sanitizeInput($_POST['contact']);
    $address = sanitizeInput($_POST['address']);
    
    $stmt = $conn->prepare("UPDATE doctor SET name=?, email=?, contactNo=?, address=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $email, $contact, $address, $doctor_id);
    
    if ($stmt->execute()) {
        $success_message = "Profile updated successfully!";
        $doctor_details['name'] = $name;
        $doctor_details['email'] = $email;
        $doctor_details['contactNo'] = $contact;
        $doctor_details['address'] = $address;
    } else {
        $error_message = "Error updating profile!";
    }
}

// Handle schedule creation
if (isset($_POST['create_schedule'])) {
    $date = sanitizeInput($_POST['schedule_date']);
    $time = sanitizeInput($_POST['schedule_time']);
    
    if (isTimeSlotAvailable($doctor_id, $date, $time)) {
        $stmt = $conn->prepare("INSERT INTO appointment_doctor (date, time, doctor_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $date, $time, $doctor_id);
        
        if ($stmt->execute()) {
            $success_message = "Schedule created successfully!";
        } else {
            $error_message = "Error creating schedule!";
        }
    } else {
        $error_message = "This time slot is already booked!";
    }
}

// Handle schedule deletion
if (isset($_POST['delete_schedule'])) {
    $schedule_id = sanitizeInput($_POST['schedule_id']);
    
    // First check if this schedule has any appointments
    $check_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointment a
        JOIN appointment_patient ap ON a.id = ap.appointment_id
        WHERE a.id = ?
    ");
    $check_stmt->bind_param("i", $schedule_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result['count'] == 0) {
        // No appointments, safe to delete
        $stmt = $conn->prepare("DELETE FROM appointment_doctor WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $schedule_id, $doctor_id);
        
        if ($stmt->execute()) {
            $success_message = "Schedule deleted successfully!";
        } else {
            $error_message = "Error deleting schedule!";
        }
    } else {
        $error_message = "Cannot delete schedule with existing appointments!";
    }
}

// Get all created schedules
$schedule_stmt = $conn->prepare("
    SELECT id, date, time 
    FROM appointment_doctor 
    WHERE doctor_id = ? AND date >= CURDATE()
    ORDER BY date ASC, time ASC
");
$schedule_stmt->bind_param("i", $doctor_id);
$schedule_stmt->execute();
$created_schedules = $schedule_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming appointments
// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT 
        a.id as appointment_id,
        p.name as patient_name,
        p.contactNo as patient_contact,
        ad.date,
        ad.time,
        ap.tokenNo,
        a.reason,
        ar.isConfirmed
    FROM appointment a
    JOIN appointment_doctor ad ON a.id = ad.appointment_id
    JOIN appointment_patient ap ON a.id = ap.appointment_id
    JOIN patient p ON ap.patient_id = p.id
    JOIN appointment_receptionist ar ON a.id = ar.appointment_id
    WHERE a.doctor_id = ? AND ad.date >= CURDATE()
    ORDER BY ad.date ASC, ad.time ASC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_appointments = [];
while($row = $result->fetch_assoc()) {
    $upcoming_appointments[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - ABC Hospital</title>
    <?php echo getCommonCSS(); ?>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <style>
        .tab-container {
            margin-top: 20px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .tab-button.active {
            background: #3498db;
            color: white;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .schedule-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .appointment-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-confirmed {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        
        .profile-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .time-slot {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
        }
        
        .time-slot:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">ABC Hospital</a>
        <div class="nav-links">
            <span style="margin-right: 20px;">Dr. <?php echo htmlspecialchars($doctor_details['name']); ?></span>
            <a href="login.php?logout=1">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php
        if ($success_message) {
            echo "<div class='alert alert-success'>$success_message</div>";
        }
        if ($error_message) {
            echo "<div class='alert alert-danger'>$error_message</div>";
        }
        ?>

        <div class="stats-container">
            <div class="stat-card">
                <h3><?php echo count($upcoming_appointments); ?></h3>
                <p>Upcoming Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php 
                    $confirmed = array_filter($upcoming_appointments, function($apt) {
                        return $apt['isConfirmed'] == 1;
                    });
                    echo count($confirmed);
                ?></h3>
                <p>Confirmed Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $doctor_details['specialization']; ?></h3>
                <p>Specialization</p>
            </div>
        </div>

        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab('profile')">Profile</button>
                <button class="tab-button" onclick="openTab('schedule')">Create Schedule</button>
                <button class="tab-button" onclick="openTab('appointments')">Appointments</button>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content active">
                <div class="card">
                    <h2>Doctor Profile</h2>
                    <form method="POST" class="profile-section">
                        <div class="info-section">
                            <div class="form-group">
                                <label>Doctor ID</label>
                                <input type="text" class="form-control" value="<?php echo $doctor_details['id']; ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Specialization</label>
                                <input type="text" class="form-control" value="<?php echo $doctor_details['specialization']; ?>" disabled>
                            </div>
                        </div>
                        <div class="edit-section">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($doctor_details['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctor_details['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($doctor_details['contactNo']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" class="form-control" required><?php echo htmlspecialchars($doctor_details['address']); ?></textarea>
                            </div>
                            <button type="submit" name="update_profile" class="btn">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div id="schedule" class="tab-content">
                <div class="card">
                    <h2>Create Appointment Schedule</h2>
                    <form method="POST" id="scheduleForm">
                        <div class="form-group">
                            <label>Select Date</label>
                            <input type="date" name="schedule_date" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Select Time</label>
                            <input type="time" name="schedule_time" class="form-control" required>
                        </div>
                        <button type="submit" name="create_schedule" class="btn">Create Schedule</button>
                    </form>
                    
                    <!-- Inside the schedule tab content -->
                    <div class="card">
                        <h3>Created Time Slots</h3>
                        <div class="schedule-grid">
                            <?php foreach ($created_schedules as $schedule): ?>
                                <div class="schedule-card">
                                    <p>Date: <?php echo formatDate($schedule['date']); ?></p>
                                    <p>Time: <?php echo formatTime($schedule['time']); ?></p>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                        <button type="submit" name="delete_schedule" class="btn btn-danger" style="margin-top: 10px;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <h3 style="margin-top: 30px;">Your Upcoming Schedules</h3>
                    <?php
                    $stmt = $conn->prepare("
                        SELECT date, time, id 
                        FROM appointment_doctor 
                        WHERE doctor_id = ? AND date >= CURDATE()
                        ORDER BY date ASC, time ASC
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <div class="schedule-grid">
                        <?php foreach ($schedules as $schedule): ?>
                            <div class="schedule-card">
                                <div style="font-weight: bold;"><?php echo formatDate($schedule['date']); ?></div>
                                <div><?php echo formatTime($schedule['time']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Appointments Tab -->
            <div id="appointments" class="tab-content">
                <div class="card">
                    <h2>Upcoming Appointments</h2>
                    <?php if (empty($upcoming_appointments)): ?>
                        <p>No upcoming appointments.</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <div class="appointment-card">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <h3>Patient: <?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                    <span class="<?php echo $appointment['isConfirmed'] ? 'status-confirmed' : 'status-pending'; ?>">
                                        <?php echo $appointment['isConfirmed'] ? 'Confirmed' : 'Pending'; ?>
                                    </span>
                                </div>
                                <div style="margin-top: 10px;">
                                    <p><strong>Date:</strong> <?php echo formatDate($appointment['date']); ?></p>
                                    <p><strong>Time:</strong> <?php echo formatTime($appointment['time']); ?></p>
                                    <p><strong>Token Number:</strong> <?php echo $appointment['tokenNo']; ?></p>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($appointment['patient_contact']); ?></p>
                                    <p><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let content of tabContents) {
                content.classList.remove('active');
            }
            
            // Deactivate all tab buttons
            const tabButtons = document.getElementsByClassName('tab-button');
            for (let button of tabButtons) {
                button.classList.remove('active');
            }
            
            // Show selected tab content and activate button
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Form validation for schedule creation
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            const date = new Date(this.schedule_date.value);
            const time = this.schedule_time.value;
            
            // Prevent scheduling in the past
            if (date < new Date().setHours(0,0,0,0)) {
                e.preventDefault();
                alert('Cannot schedule appointments in the past!');
                return;
            }
            
            // Weekend validation (optional, remove if weekend appointments are allowed)
            if (date.getDay() === 0 || date.getDay() === 6) {
                if (!confirm('Are you sure you want to schedule on a weekend?')) {
                    e.preventDefault();
                    return;
                }
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.getElementsByClassName('alert');
            for (let alert of alerts) {
                alert.style.display = 'none';
            }
        }, 5000);

        // Add confirmation for profile updates
        document.querySelector('form').addEventListener('submit', function(e) {
            if (this.update_profile) {
                if (!confirm('Are you sure you want to update your profile?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>