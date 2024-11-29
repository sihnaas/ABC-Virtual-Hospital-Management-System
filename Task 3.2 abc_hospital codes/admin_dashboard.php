<?php
require_once 'config.php';
checkRole(['admin']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    // Add Doctor
    if (isset($_POST['add_doctor'])) {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $gender = sanitizeInput($_POST['gender']);
        $contact = sanitizeInput($_POST['contact']);
        $address = sanitizeInput($_POST['address']);
        $specialization = (int)$_POST['specialization'];
        $username = sanitizeInput($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // First create user
        $stmt = $conn->prepare("INSERT INTO user (role, username, password) VALUES ('doctor', ?, ?)");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Then create doctor
        $stmt = $conn->prepare("INSERT INTO doctor (name, gender, address, contactNo, email, specialization_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssii", $name, $gender, $address, $contact, $email, $specialization, $user_id);
        $stmt->execute();
        $_SESSION['message'] = "Doctor added successfully!";
    }

    // Add Receptionist
    if (isset($_POST['add_receptionist'])) {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $gender = sanitizeInput($_POST['gender']);
        $contact = sanitizeInput($_POST['contact']);
        $address = sanitizeInput($_POST['address']);
        $username = sanitizeInput($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // First create user
        $stmt = $conn->prepare("INSERT INTO user (role, username, password) VALUES ('receptionist', ?, ?)");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Then create receptionist
        $stmt = $conn->prepare("INSERT INTO receptionist (name, email, gender, contactNo, address, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $email, $gender, $contact, $address, $user_id);
        $stmt->execute();
        $_SESSION['message'] = "Receptionist added successfully!";
    }

    // Delete Doctor
    if (isset($_POST['delete_doctor'])) {
        $doctor_id = (int)$_POST['doctor_id'];
        $stmt = $conn->prepare("DELETE FROM user WHERE id = (SELECT user_id FROM doctor WHERE id = ?)");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $_SESSION['message'] = "Doctor deleted successfully!";
    }

    // Delete Receptionist
    if (isset($_POST['delete_receptionist'])) {
        $receptionist_id = (int)$_POST['receptionist_id'];
        $stmt = $conn->prepare("DELETE FROM user WHERE id = (SELECT user_id FROM receptionist WHERE id = ?)");
        $stmt->bind_param("i", $receptionist_id);
        $stmt->execute();
        $_SESSION['message'] = "Receptionist deleted successfully!";
    }

    $conn->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch existing doctors and receptionists
$conn = getDBConnection();
$doctors = $conn->query("
    SELECT d.*, s.title as specialization, u.username 
    FROM doctor d 
    JOIN specialization s ON d.specialization_id = s.id 
    JOIN user u ON d.user_id = u.id
    ORDER BY d.name
");

$receptionists = $conn->query("
    SELECT r.*, u.username 
    FROM receptionist r 
    JOIN user u ON r.user_id = u.id
    ORDER BY r.name
");

$specializations = $conn->query("SELECT * FROM specialization ORDER BY title");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ABC Hospital</title>
    <?php echo getCommonCSS(); ?>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            padding: 20px;
        }

        .section-title {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .form-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .grid-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .list-container {
            margin-top: 30px;
            overflow-x: auto;
        }

        .action-column {
            width: 100px;
            text-align: center;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .grid-form {
                grid-template-columns: 1fr;
            }
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

    <div class="container dashboard-container">
        <?php
        if (isset($_SESSION['message'])) {
            echo displayMessage($_SESSION['message']);
            unset($_SESSION['message']);
        }
        ?>

        <!-- Doctors Section -->
        <div class="card">
            <h2 class="section-title">Manage Doctors</h2>
            
            <!-- Add Doctor Form -->
            <div class="form-container">
                <h3>Add New Doctor</h3>
                <form method="POST" class="grid-form">
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Gender:</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Number:</label>
                        <input type="text" name="contact" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Address:</label>
                        <textarea name="address" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Specialization:</label>
                        <select name="specialization" class="form-control" required>
                            <?php while ($spec = $specializations->fetch_assoc()): ?>
                                <option value="<?php echo $spec['id']; ?>">
                                    <?php echo htmlspecialchars($spec['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_doctor" class="btn">Add Doctor</button>
                    </div>
                </form>
            </div>

            <!-- Doctors List -->
            <div class="list-container">
                <h3>Registered Doctors</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Specialization</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doctor = $doctors->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['username']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['contactNo']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                <td class="action-column">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                        <button type="submit" name="delete_doctor" class="btn btn-danger btn-small" 
                                                onclick="return confirm('Are you sure you want to delete this doctor?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Receptionists Section -->
        <div class="card">
            <h2 class="section-title">Manage Receptionists</h2>
            
            <!-- Add Receptionist Form -->
            <div class="form-container">
                <h3>Add New Receptionist</h3>
                <form method="POST" class="grid-form">
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Gender:</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Number:</label>
                        <input type="text" name="contact" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Address:</label>
                        <textarea name="address" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_receptionist" class="btn">Add Receptionist</button>
                    </div>
                </form>
            </div>

            <!-- Receptionists List -->
            <div class="list-container">
                <h3>Registered Receptionists</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($receptionist = $receptionists->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($receptionist['name']); ?></td>
                                <td><?php echo htmlspecialchars($receptionist['username']); ?></td>
                                <td><?php echo htmlspecialchars($receptionist['email']); ?></td>
                                <td><?php echo htmlspecialchars($receptionist['contactNo']); ?></td>
                                <td class="action-column">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="receptionist_id" value="<?php echo $receptionist['id']; ?>">
                                        <button type="submit" name="delete_receptionist" class="btn btn-danger btn-small"
                                                onclick="return confirm('Are you sure you want to delete this receptionist?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const password = this.querySelector('input[type="password"]');
                if (password && password.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long');
                }
            });
        });

        // Highlight table rows on hover
        document.querySelectorAll('tr').forEach(row => {
            row.addEventListener('mouseover', function() {
                this.style.backgroundColor = '#f5f5f5';
            });
            row.addEventListener('mouseout', function() {
                this.style.backgroundColor = '';
            });
        });
    </script>
</body>
</html>