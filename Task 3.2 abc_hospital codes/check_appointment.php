<?php
require_once 'config.php';

$message = '';
$appointmentDetails = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_number'])) {
    $reference = sanitizeInput($_POST['reference_number']);
    $decoded = decodeReferenceNumber($reference);
    
    if ($decoded) {
        $conn = getDBConnection();
        
        // Comprehensive query to get all appointment details
        $query = "
            SELECT 
                a.id as appointment_id,
                a.status as appointment_status,
                a.reason,
                p.name as patient_name,
                p.contactNo as patient_contact,
                d.name as doctor_name,
                s.title as specialization,
                ad.date as appointment_date,
                ad.time as appointment_time,
                ap.tokenNo as token_number,
                ar.isConfirmed as is_confirmed,
                r.name as receptionist_name
            FROM appointment a
            JOIN patient p ON a.patient_id = p.id
            JOIN doctor d ON a.doctor_id = d.id
            JOIN specialization s ON d.specialization_id = s.id
            JOIN appointment_doctor ad ON a.id = ad.appointment_id
            JOIN appointment_patient ap ON a.id = ap.appointment_id
            LEFT JOIN appointment_receptionist ar ON a.id = ar.appointment_id
            LEFT JOIN receptionist r ON ar.receptionist_id = r.id
            WHERE p.id = ? AND d.id = ? AND ap.tokenNo = ? AND ad.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiii", 
            $decoded['patient_id'],
            $decoded['doctor_id'],
            $decoded['token_no'],
            $decoded['appointment_doctor_id']
        );
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $appointmentDetails = $result->fetch_assoc();
        } else {
            $message = "No appointment found with the provided reference number.";
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $message = "Invalid reference number format.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Appointment Status - ABC Hospital</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <?php echo getCommonCSS(); ?>
    <style>
        .status-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
        }

        .status-confirmed {
            background-color: #27ae60;
        }

        .status-pending {
            background-color: #f1c40f;
        }

        .status-section {
            margin: 15px 0;
        }

        .status-section h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }

        .status-item strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .reference-form {
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .timeline {
            position: relative;
            margin: 20px 0;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 100%;
            background: #e0e0e0;
        }

        .timeline-item {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            position: relative;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            background: #3498db;
            border-radius: 50%;
        }

        @media print {
            .no-print {
                display: none;
            }
            .container {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">ABC Hospital</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="book_appointment.php">Book Appointment</a>
        </div>
    </nav>

    <div class="container">
        <div class="reference-form no-print">
            <h2 class="text-center">Check Appointment Status</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="reference_number">Reference Number:</label>
                    <input type="text" 
                           id="reference_number" 
                           name="reference_number" 
                           class="form-control" 
                           placeholder="Enter your reference number (e.g., REF-0001-0001-001-0001)"
                           required
                           pattern="REF-\d{4}-\d{4}-\d{3}-\d{4}"
                           title="Please enter a valid reference number format">
                </div>
                <button type="submit" class="btn" style="width: 100%;">Check Status</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-danger">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($appointmentDetails): ?>
            <div class="status-card">
                <div class="status-header">
                    <h2>Appointment Details</h2>
                    <span class="status-badge <?php echo $appointmentDetails['is_confirmed'] ? 'status-confirmed' : 'status-pending'; ?>">
                        <?php echo $appointmentDetails['is_confirmed'] ? 'Confirmed' : 'Pending Confirmation'; ?>
                    </span>
                </div>

                <div class="status-grid">
                    <div class="status-item">
                        <strong>Patient Name</strong>
                        <?php echo htmlspecialchars($appointmentDetails['patient_name']); ?>
                    </div>

                    <div class="status-item">
                        <strong>Token Number</strong>
                        <?php echo htmlspecialchars($appointmentDetails['token_number']); ?>
                    </div>

                    <div class="status-item">
                        <strong>Doctor</strong>
                        <?php echo htmlspecialchars($appointmentDetails['doctor_name']); ?>
                    </div>

                    <div class="status-item">
                        <strong>Specialization</strong>
                        <?php echo htmlspecialchars($appointmentDetails['specialization']); ?>
                    </div>

                    <div class="status-item">
                        <strong>Date</strong>
                        <?php echo formatDate($appointmentDetails['appointment_date']); ?>
                    </div>

                    <div class="status-item">
                        <strong>Time</strong>
                        <?php echo formatTime($appointmentDetails['appointment_time']); ?>
                    </div>
                </div>

                <div class="status-section">
                    <h3>Appointment Status Timeline</h3>
                    <div class="timeline">
                        <div class="timeline-item">
                            <strong>Appointment Booked</strong>
                            <p>Your appointment has been successfully registered in our system.</p>
                        </div>

                        <?php if ($appointmentDetails['is_confirmed']): ?>
                            <div class="timeline-item">
                                <strong>Appointment Confirmed</strong>
                                <p>Confirmed by: <?php echo htmlspecialchars($appointmentDetails['receptionist_name']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center no-print">
                    <button onclick="window.print()" class="btn">Print Details</button>
                    <a href="index.php" class="btn" style="margin-left: 10px;">Back to Home</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const refNumber = document.getElementById('reference_number').value;
            const pattern = /^REF-\d{4}-\d{4}-\d{3}-\d{4}$/;
            
            if (!pattern.test(refNumber)) {
                e.preventDefault();
                alert('Please enter a valid reference number format: REF-0000-0000-000-0000');
            }
        });
    </script>
</body>
</html>