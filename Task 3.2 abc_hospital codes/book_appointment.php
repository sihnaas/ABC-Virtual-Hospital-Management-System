<?php
require_once 'config.php';

// Handle AJAX request for doctors
if (isset($_GET['get_doctors'])) {
    $specialization_id = intval($_GET['get_doctors']);
    $doctors = getDoctorsBySpecializationAjax($specialization_id);
    header('Content-Type: application/json');
    echo json_encode($doctors);
    exit;
}

// Handle AJAX request for time slots
if (isset($_GET['get_time_slots'])) {
    $doctor_id = intval($_GET['doctor_id']);
    $date = $_GET['date'];
    $timeSlots = getAvailableTimeSlots($doctor_id, $date);
    header('Content-Type: application/json');
    echo json_encode($timeSlots);
    exit;
}

$successMessage = $errorMessage = '';
$selectedSpecialization = isset($_GET['specialization']) ? (int)$_GET['specialization'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        // Sanitize inputs
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $contact = sanitizeInput($_POST['contact']);
        $address = sanitizeInput($_POST['address']);
        $gender = sanitizeInput($_POST['gender']);
        $dob = sanitizeInput($_POST['dob']);
        $doctor_id = (int)$_POST['doctor'];
        $appointment_date = sanitizeInput($_POST['appointment_date']);
        $appointment_time = sanitizeInput($_POST['appointment_time']);
        $reason = sanitizeInput($_POST['reason']);

        // Validate email
        if (!validateEmail($email)) {
            throw new Exception("Invalid email address");
        }

        // Start transaction
        $conn->begin_transaction();

        // Check if patient exists
        $stmt = $conn->prepare("SELECT id FROM patient WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            $patient_id = $patient['id'];
        } else {
            // Insert new patient
            $stmt = $conn->prepare("INSERT INTO patient (name, email, contactNo, address, DoB, gender) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $email, $contact, $address, $dob, $gender);
            $stmt->execute();
            $patient_id = $conn->insert_id;
        }

        // Create appointment
        $stmt = $conn->prepare("INSERT INTO appointment (reason, doctor_id, patient_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $reason, $doctor_id, $patient_id);
        $stmt->execute();
        $appointment_id = $conn->insert_id;

        // Create doctor appointment
        $stmt = $conn->prepare("INSERT INTO appointment_doctor (date, time, doctor_id, appointment_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $appointment_date, $appointment_time, $doctor_id, $appointment_id);
        $stmt->execute();
        $appointment_doctor_id = $conn->insert_id;

        // Get next token number
        $stmt = $conn->prepare("SELECT COALESCE(MAX(tokenNo), 0) + 1 AS next_token FROM appointment_patient ap 
                               JOIN appointment a ON ap.appointment_id = a.id 
                               JOIN appointment_doctor ad ON a.id = ad.appointment_id 
                               WHERE ad.doctor_id = ? AND ad.date = ?");
        $stmt->bind_param("is", $doctor_id, $appointment_date);
        $stmt->execute();
        $token_result = $stmt->get_result();
        $token_row = $token_result->fetch_assoc();
        $token_no = $token_row['next_token'];

        // Create patient appointment
        $stmt = $conn->prepare("INSERT INTO appointment_patient (tokenNo, appointment_id, patient_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $token_no, $appointment_id, $patient_id);
        $stmt->execute();

        // Generate reference number
        $reference = generateReferenceNumber($patient_id, $doctor_id, $token_no, $appointment_doctor_id);

        $conn->commit();
        $successMessage = "Appointment booked successfully! Your reference number is: " . $reference;
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $errorMessage = "Error: " . $e->getMessage();
    } finally {
        if (isset($conn)) {
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - ABC Hospital</title>
    <?php echo getCommonCSS(); ?>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <style>
        /* Your existing CSS remains the same */
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">ABC Hospital</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="check_appointment.php">Check Appointment</a>
        </div>
    </nav>

    <div class="container">
        <div class="booking-container">
            <div class="booking-header">
                <h1>Book an Appointment</h1>
                <p>Fill in your details below to schedule an appointment</p>
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="reference-number"><?php echo $successMessage; ?></div>
                <div class="text-center">
                    <a href="check_appointment.php" class="btn">Check Appointment Status</a>
                    <a href="index.php" class="btn" style="margin-left: 10px;">Back to Home</a>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="appointmentForm">
                    <!-- Personal Information Fields -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="contact">Contact Number *</label>
                            <input type="tel" id="contact" name="contact" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="dob">Date of Birth *</label>
                            <input type="date" id="dob" name="dob" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="address">Address *</label>
                            <input type="text" id="address" name="address" class="form-control" required>
                        </div>

                        <!-- Appointment Fields -->
                        <div class="form-group">
                            <label for="specialization">Select Specialization *</label>
                            <select name="specialization" id="specialization" class="form-control" required>
                                <option value="">Select Specialization</option>
                                <?php
                                $specializations = getAllSpecializations();
                                foreach($specializations as $spec) {
                                    echo "<option value='" . $spec['id'] . "'>" . htmlspecialchars($spec['title']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="doctor">Select Doctor *</label>
                            <select name="doctor" id="doctor" class="form-control" required disabled>
                                <option value="">First Select Specialization</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="appointment_date">Select Date *</label>
                            <input type="date" name="appointment_date" id="appointment_date" 
                                   class="form-control" min="<?php echo date('Y-m-d'); ?>" 
                                   required disabled>
                        </div>

                        <div class="form-group">
                            <label for="appointment_time">Select Time *</label>
                            <select name="appointment_time" id="appointment_time" 
                                    class="form-control" required disabled>
                                <option value="">First Select Date</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Visit *</label>
                            <textarea id="reason" name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Book Appointment</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Unified functions for handling form interactions
        function getDoctors() {
            const specializationId = document.getElementById('specialization').value;
            const doctorSelect = document.getElementById('doctor');
            const dateInput = document.getElementById('appointment_date');
            const timeSelect = document.getElementById('appointment_time');
            
            // Reset dependent fields
            doctorSelect.disabled = true;
            dateInput.disabled = true;
            timeSelect.disabled = true;
            
            if (!specializationId) {
                doctorSelect.innerHTML = '<option value="">First Select Specialization</option>';
                return;
            }

            fetch(`book_appointment.php?get_doctors=${specializationId}`)
                .then(response => response.json())
                .then(doctors => {
                    let options = '<option value="">Select Doctor</option>';
                    doctors.forEach(doctor => {
                        options += `<option value="${doctor.id}">${doctor.name}</option>`;
                    });
                    doctorSelect.innerHTML = options;
                    doctorSelect.disabled = false;
                })
                .catch(error => console.error('Error:', error));
        }

        function getTimeSlots() {
            const doctorId = document.getElementById('doctor').value;
            const date = document.getElementById('appointment_date').value;
            const timeSelect = document.getElementById('appointment_time');
            
            if (!doctorId || !date) {
                timeSelect.disabled = true;
                timeSelect.innerHTML = '<option value="">First Select Doctor and Date</option>';
                return;
            }

            fetch(`book_appointment.php?get_time_slots=1&doctor_id=${doctorId}&date=${date}`)
                .then(response => response.json())
                .then(timeSlots => {
                    let options = '<option value="">Select Time</option>';
                    timeSlots.forEach(slot => {
                        const time = new Date('2000-01-01T' + slot).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        options += `<option value="${slot}">${time}</option>`;
                    });
                    timeSelect.innerHTML = options;
                    timeSelect.disabled = false;
                })
                .catch(error => console.error('Error:', error));
        }

        // Event Listeners
        document.getElementById('specialization').addEventListener('change', getDoctors);
        
        document.getElementById('doctor').addEventListener('change', function() {
            const dateInput = document.getElementById('appointment_date');
            dateInput.disabled = !this.value;
            if (this.value) {
                dateInput.min = new Date().toISOString().split('T')[0];
                getTimeSlots();
            }
        });

        document.getElementById('appointment_date').addEventListener('change', getTimeSlots);

        // Form Validation
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            const today = new Date();
            const selectedDate = new Date(document.getElementById('appointment_date').value);
            const dob = new Date(document.getElementById('dob').value);

            if (dob >= today) {
                e.preventDefault();
                alert('Please enter a valid date of birth');
                return;
            }

            if (selectedDate < today) {
                e.preventDefault();
                alert('Please select a future date for the appointment');
                return;
            }
        });
    </script>
</body>
</html>