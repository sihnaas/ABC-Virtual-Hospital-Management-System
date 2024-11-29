<?php
require_once 'config.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Process login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $error = "";

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $conn = getDBConnection();
        
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, role, password FROM user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'doctor':
                        header("Location: doctor_dashboard.php");
                        break;
                    case 'receptionist':
                        header("Location: receptionist_dashboard.php");
                        break;
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Username not found";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ABC Hospital</title>
    <?php echo getCommonCSS(); ?>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .login-container {
            max-width: 400px;
            margin: auto;
            padding: 20px;
        }

        .login-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-out;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            transition: border-color 0.3s ease, box-shadow 0.3s;
        }

        .form-control:focus {
            border-color: #4299e1;
            box-shadow: 0 0 8px rgba(66, 153, 225, 0.4);
            outline: none;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #4299e1;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #3182ce;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: white;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .error-message {
            background-color: #fdecea;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #718096;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>ABC Hospital Login</h1>
                <p>Please login to access your dashboard</p>
            </div>

            <?php if (isset($error) && !empty($error)): ?>
                <div class="error-message fade-in">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-toggle">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <i class="toggle-icon" onclick="togglePassword()">👁️</i>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Login</button>
            </form>
        </div>
        <a href="index.php" class="back-link">← Back to Homepage</a>
    </div>

    <script>
        function validateForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                alert('Please fill in all fields');
                return false;
            }
            return true;
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = '🔒';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = '👁️';
            }
        }

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            if (validateForm()) {
                const submitBtn = document.querySelector('.submit-btn');
                submitBtn.textContent = 'Logging in...';
                submitBtn.style.opacity = '0.7';
                submitBtn.disabled = true;
            }
        });

        // Add animation to error message
        document.addEventListener('DOMContentLoaded', function() {
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.style.animation = 'fadeIn 0.5s ease-out';
            }
        });
    </script>
</body>
</html>
