<?php
require_once 'config.php';

// Check if user is logged in as doctor
checkRole(['doctor']);

$doctor_id = null;
$success_message = $error_message = '';

// Get doctor details
$stmt = getDBConnection()->prepare("SELECT id FROM doctor WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if($row = $result->fetch_assoc()) {
    $doctor_id = $row['id'];
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['add_schedule'])) {
        $date = sanitizeInput($_POST['date']);
        $times = $_POST['times'];
        
        // Validate date
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            $error_message = "Cannot set schedule for past dates";
        } else {
            $stmt = $conn->prepare("INSERT INTO appointment_doctor (date, time, doctor_id) VALUES (?, ?, ?)");
            
            foreach ($times as $time) {
                if (isTimeSlotAvailable($doctor_id, $date, $time)) {
                    $stmt->bind_param("ssi", $date, $time, $doctor_id);
                    $stmt->execute();
                }
            }
            $success_message = "Schedule updated successfully";
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_slot'])) {
        $slot_id = (int)$_POST['slot_id'];
        $stmt = $conn->prepare("DELETE FROM appointment_doctor WHERE id = ? AND doctor_id = ? AND NOT EXISTS (SELECT 1 FROM appointment WHERE appointment_id = appointment_doctor.id)");
        $stmt->bind_param("ii", $slot_id, $doctor_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success_message = "Time slot deleted successfully";
        } else {
            $error_message = "Cannot delete booked time slot";
        }
        $stmt->close();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule - ABC Hospital</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <?php echo getCommonCSS(); ?>
    <style>
        .schedule-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .time-slot {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .schedule-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .schedule-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .delete-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">ABC Hospital</a>
        <div class="nav-links">
            <a href="doctor_dashboard.php">Dashboard</a>
            <a href="doctor_appointments.php">View Appointments</a>
            <a href="login.php?logout=1">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1>Manage Schedule</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="schedule-form">
            <h2>Add Available Time Slots</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="date">Select Date:</label>
                    <input type="date" id="date" name="date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Select Time Slots:</label>
                    <div class="time-slots">
                        <?php
                        $start = strtotime('09:00');
                        $end = strtotime('17:00');
                        $interval = 30 * 60; // 30 minutes

                        for ($time = $start; $time <= $end; $time += $interval) {
                            $timeStr = date('H:i', $time);
                            echo "<div class='time-slot'>";
                            echo "<input type='checkbox' name='times[]' value='$timeStr' id='time_$timeStr'>";
                            echo "<label for='time_$timeStr'>" . date('h:i A', $time) . "</label>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <button type="submit" name="add_schedule" class="btn">Add Time Slots</button>
            </form>
        </div>

        <h2>Current Schedule</h2>
        <div class="schedule-list">
            <?php
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                SELECT ad.id, ad.date, ad.time, 
                       CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as is_booked
                FROM appointment_doctor ad
                LEFT JOIN appointment a ON a.id = ad.appointment_id
                WHERE ad.doctor_id = ? AND ad.date >= CURDATE()
                ORDER BY ad.date, ad.time
            ");
            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $current_date = null;
            while ($row = $result->fetch_assoc()) {
                if ($current_date !== $row['date']) {
                    if ($current_date !== null) {
                        echo "</div>";
                    }
                    $current_date = $row['date'];
                    echo "<div class='schedule-card'>";
                    echo "<h3>" . formatDate($row['date']) . "</h3>";
                }
                
                echo "<div style='display: flex; justify-content: space-between; margin: 10px 0;'>";
                echo "<span>" . formatTime($row['time']) . "</span>";
                if (!$row['is_booked']) {
                    echo "<form method='POST' style='display: inline;'>";
                    echo "<input type='hidden' name='slot_id' value='{$row['id']}'>";
                    echo "<button type='submit' name='delete_slot' class='delete-btn'>Delete</button>";
                    echo "</form>";
                } else {
                    echo "<span style='color: #27ae60;'>Booked</span>";
                }
                echo "</div>";
            }
            if ($current_date !== null) {
                echo "</div>";
            }
            $stmt->close();
            $conn->close();
            ?>
        </div>
    </div>

    <script>
        // Add date validation
        document.getElementById('date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0,0,0,0);
            
            if (selectedDate < today) {
                alert('Please select a future date');
                this.value = '';
            }
        });
    </script>
</body>
</html>