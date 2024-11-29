<?php
require_once 'config.php';

// Check if user is logged in and is a receptionist
checkRole(['receptionist']);

// Get receptionist details
$receptionist = getUserDetails($_SESSION['user_id'], 'receptionist');

// Handle appointment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $conn = getDBConnection();
    
    try {
        $conn->begin_transaction();
        
        // Update appointment_receptionist table
        $stmt = $conn->prepare("
            INSERT INTO appointment_receptionist (isConfirmed, receptionist_id, appointment_id)
            VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE isConfirmed = 1
        ");
        $stmt->bind_param("ii", $receptionist['id'], $appointment_id);
        $stmt->execute();
        
        // Update appointment status
        $stmt = $conn->prepare("UPDATE appointment SET status = 1 WHERE id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Appointment confirmed successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error confirming appointment: " . $e->getMessage();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - ABC Hospital</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <?php echo getCommonCSS(); ?>
    <style>
        .dashboard-header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card, .appointment-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2em;
            color: #3498db;
            font-weight: bold;
        }
        
        .tab, .filter-btn, .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .tab.active, .filter-btn.active {
            background: #3498db;
            color: white;
        }
        
        .btn {
            background: #3498db;
            color: white;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .hidden {
            display: none;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">ABC Hospital</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="login.php?logout=1">Logout</a>
        </div>
    </nav>

    <div class="dashboard-header">
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars($receptionist['name']); ?></h1>
            <p>Receptionist Dashboard</p>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success fade-in"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger fade-in"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="stats-container">
            <?php
            $conn = getDBConnection();
            
            // Get pending appointments count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment WHERE status = 0");
            $stmt->execute();
            $pending_count = $stmt->get_result()->fetch_assoc()['count'];
            
            // Get today's appointments count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment_doctor WHERE date = CURDATE()");
            $stmt->execute();
            $today_count = $stmt->get_result()->fetch_assoc()['count'];
            
            // Get confirmed appointments count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment WHERE status = 1");
            $stmt->execute();
            $confirmed_count = $stmt->get_result()->fetch_assoc()['count'];
            
            $conn->close();
            ?>
            
            <div class="stat-card">
                <h3>Pending Appointments</h3>
                <div class="number"><?php echo $pending_count; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Today's Appointments</h3>
                <div class="number"><?php echo $today_count; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Confirmed Appointments</h3>
                <div class="number"><?php echo $confirmed_count; ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Manage Appointments</h2>
            
            <div class="search-bar">
                <input type="text" id="searchAppointments" placeholder="Search appointments..." onkeyup="searchAppointments()">
            </div>
            
            <div class="filter-container">
                <button class="filter-btn active" data-filter="all" onclick="filterAppointments('all')">All</button>
                <button class="filter-btn" data-filter="pending" onclick="filterAppointments('pending')">Pending</button>
                <button class="filter-btn" data-filter="confirmed" onclick="filterAppointments('confirmed')">Confirmed</button>
            </div>

            <div id="appointmentsList">
                <?php
                $conn = getDBConnection();
                $query = "
                    SELECT 
                        a.id as appointment_id,
                        a.status,
                        a.reason,
                        p.name as patient_name,
                        p.contactNo as patient_contact,
                        d.name as doctor_name,
                        s.title as specialization,
                        ad.date,
                        ad.time,
                        ap.tokenNo
                    FROM appointment a
                    JOIN patient p ON a.patient_id = p.id
                    JOIN doctor d ON a.doctor_id = d.id
                    JOIN specialization s ON d.specialization_id = s.id
                    JOIN appointment_doctor ad ON a.id = ad.appointment_id
                    JOIN appointment_patient ap ON a.id = ap.appointment_id
                    ORDER BY ad.date ASC, ad.time ASC
                ";
                
                $result = $conn->query($query);
                while ($appointment = $result->fetch_assoc()):
                ?>
                    <div class="appointment-card" data-status="<?php echo $appointment['status'] ? 'confirmed' : 'pending'; ?>">
                        <div class="header">
                            <h3>Appointment #<?php echo $appointment['appointment_id']; ?></h3>
                            <?php if (!$appointment['status']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <button type="submit" name="confirm_appointment" class="btn" onclick="return confirm('Confirm this appointment?')">
                                        Confirm Appointment
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="btn" style="background-color: #27ae60; cursor: default;">Confirmed</span>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-details">
                            <div>
                                <strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?><br>
                                <strong>Contact:</strong> <?php echo htmlspecialchars($appointment['patient_contact']); ?>
                            </div>
                            <div>
                                <strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?><br>
                                <strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?>
                            </div>
                            <div>
                                <strong>Date:</strong> <?php echo formatDate($appointment['date']); ?><br>
                                <strong>Time:</strong> <?php echo formatTime($appointment['time']); ?>
                            </div>
                            <div>
                                <strong>Token Number:</strong> <?php echo $appointment['tokenNo']; ?><br>
                                <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                            </div>
                        </div>
                    </div>
                <?php 
                endwhile;
                $conn->close();
                ?>
            </div>
        </div>
    </div>

    <script>
        function searchAppointments() {
            const searchInput = document.getElementById('searchAppointments').value.toLowerCase();
            const appointments = document.getElementsByClassName('appointment-card');
            
            for (let appointment of appointments) {
                const text = appointment.textContent.toLowerCase();
                if (text.includes(searchInput)) {
                    appointment.classList.remove('hidden');
                } else {
                    appointment.classList.add('hidden');
                }
            }
        }

        function filterAppointments(status) {
            const appointments = document.getElementsByClassName('appointment-card');
            const filterBtns = document.getElementsByClassName('filter-btn');
            
            // Update active filter button
            for (let btn of filterBtns) {
                btn.classList.remove('active');
                if (btn.dataset.filter === status) {
                    btn.classList.add('active');
                }
            }
            
            // Filter appointments
            for (let appointment of appointments) {
                if (status === 'all' || appointment.dataset.status === status) {
                    appointment.classList.remove('hidden');
                } else {
                    appointment.classList.add('hidden');
                }
            }
        }
    </script>
</body>
</html>
