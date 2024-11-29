<?php
require_once 'config.php';
checkRole(['doctor']);

$doctor_id = $_SESSION['user_id'];
$message = '';

// Get doctor's details
$doctor_details = getUserDetails($_SESSION['user_id'], 'doctor');

// Get today's date
$today = date('Y-m-d');

// Filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : $today;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get appointments
$conn = getDBConnection();
$query = "
    SELECT 
        a.id as appointment_id,
        p.name as patient_name,
        p.contactNo as patient_contact,
        ad.date,
        ad.time,
        a.status,
        a.reason,
        ap.tokenNo
    FROM appointment a
    JOIN appointment_doctor ad ON a.id = ad.appointment_id
    JOIN patient p ON a.patient_id = p.id
    JOIN appointment_receptionist ar ON a.id = ar.appointment_id
    WHERE a.doctor_id = ? 
    AND ad.date = ?
    AND ar.isConfirmed = 1
";

if ($status_filter !== 'all') {
    $query .= " AND a.status = " . ($status_filter === 'completed' ? '1' : '0');
}

$query .= " ORDER BY ad.time ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $doctor_details['id'], $date_filter);
$stmt->execute();
$result = $stmt->get_result();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['new_status'];
    
    $update_stmt = $conn->prepare("UPDATE appointment SET status = ? WHERE id = ? AND doctor_id = ?");
    $update_stmt->bind_param("iii", $new_status, $appointment_id, $doctor_details['id']);
    
    if ($update_stmt->execute()) {
        $message = "Appointment status updated successfully!";
        // Refresh the page to show updated data
        header("Location: doctor_appointments.php?date=" . $date_filter . "&status=" . $status_filter);
        exit();
    } else {
        $message = "Error updating appointment status.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointments</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <?php echo getCommonCSS(); ?>
    <style>
        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .appointment-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .appointment-time {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .token-number {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .status-pending {
            background: #f1c40f;
            color: #000;
        }
        
        .status-completed {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="doctor_dashboard.php" class="logo">ABC Hospital</a>
        <div class="nav-links">
            <a href="doctor_dashboard.php">Dashboard</a>
            <a href="doctor_schedule.php">Manage Schedule</a>
            <a href="doctor_appointments.php">View Appointments</a>
            <a href="login.php?logout=1">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1>My Appointments</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="filters">
            <div class="form-group">
                <label>Date:</label>
                <input type="date" 
                       class="form-control" 
                       value="<?php echo $date_filter; ?>" 
                       onchange="window.location.href='?date='+this.value+'&status=<?php echo $status_filter; ?>'"
                       min="<?php echo $today; ?>">
            </div>

            <div class="form-group">
                <label>Status:</label>
                <select class="form-control" 
                        onchange="window.location.href='?date=<?php echo $date_filter; ?>&status='+this.value">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($appointment = $result->fetch_assoc()): ?>
                <div class="appointment-card">
                    <div class="appointment-header">
                        <span class="appointment-time">
                            <?php echo formatTime($appointment['time']); ?>
                        </span>
                        <span class="token-number">
                            Token: <?php echo $appointment['tokenNo']; ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <strong>Patient:</strong> <?php echo $appointment['patient_name']; ?><br>
                        <strong>Contact:</strong> <?php echo $appointment['patient_contact']; ?><br>
                        <strong>Reason:</strong> <?php echo $appointment['reason']; ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="status-badge <?php echo $appointment['status'] ? 'status-completed' : 'status-pending'; ?>">
                            <?php echo $appointment['status'] ? 'Completed' : 'Pending'; ?>
                        </span>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $appointment['status'] ? '0' : '1'; ?>">
                            <button type="submit" name="update_status" class="btn">
                                Mark as <?php echo $appointment['status'] ? 'Pending' : 'Completed'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p class="text-center">No appointments found for this date.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>