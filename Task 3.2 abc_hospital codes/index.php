<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to ABC Hospital</title>
    <?php echo getCommonCSS(); ?>
    <link rel="stylesheet" href="styles.css">
    <style>
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('/api/placeholder/1200/400');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-bottom: 40px;
        }

        .hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 1.2em;
            margin-bottom: 30px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .feature-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #666;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin: 40px 0;
        }

        .action-button {
            padding: 15px 30px;
            font-size: 1.1em;
            border-radius: 25px;
            transition: transform 0.3s ease;
        }

        .action-button:hover {
            transform: scale(1.05);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background-color: #34495e;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5em;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .logo img {
            max-height: 50px;
            margin-right: 10px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2em;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-button {
                width: 80%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">
            <img src="logoabc.jpg" alt="ABC Hospital Logo"> ABC Hospital
        </a>
        <div class="nav-links">
            <a href="#our-services">Our Services</a>
            <a href="#reviews">Reviews</a>
            <a href="#about-us">About Us</a>
            <?php if (isLoggedIn()): ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Dashboard</a>
                <?php elseif ($_SESSION['role'] === 'doctor'): ?>
                    <a href="doctor_dashboard.php">Doctor Dashboard</a>
                <?php elseif ($_SESSION['role'] === 'receptionist'): ?>
                    <a href="receptionist_dashboard.php">Receptionist Dashboard</a>
                <?php endif; ?>
                <a href="login.php?logout=1">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="hero">
        <div class="container">
            <h1>Welcome to ABC Hospital</h1>
            <p>Your Health is Our Priority</p>
        </div>
    </div>

    <div class="container">
        <div class="action-buttons">
            <a href="book_appointment.php" class="btn action-button" style="background-color: #27ae60;">
                Book Appointment
            </a>
            <a href="check_appointment.php" class="btn action-button" style="background-color: #2980b9;">
                Check Appointment Status
            </a>
        </div>

        <section id="our-services">
            <h2 class="text-center">Available Services</h2>
            <div class="features">
                <div class="feature-card">
                    <h3>General Medicine</h3>
                    <p>Providing comprehensive healthcare for all ages.</p>
                </div>
                <div class="feature-card">
                    <h3>Pediatrics</h3>
                    <p>Specialized care for children from newborn to adolescence.</p>
                </div>
                <div class="feature-card">
                    <h3>Cardiology</h3>
                    <p>Advanced heart care with top cardiologists.</p>
                </div>
                <div class="feature-card">
                    <h3>Orthopedics</h3>
                    <p>Treatment for bone and joint conditions.</p>
                </div>
                <div class="feature-card">
                    <h3>Emergency Care</h3>
                    <p>24/7 emergency services for urgent medical needs.</p>
                </div>
            </div>
        </section>

        <section id="reviews" style="margin-top: 40px;">
            <h2 class="text-center">Reviews from Top Celebrities</h2>
            <div class="features">
                <div class="feature-card">
                    <h3>John Doe</h3>
                    <p>"ABC Hospital is simply the best. Their care saved my life!"</p>
                    <p>Rating: ⭐⭐⭐⭐⭐</p>
                </div>
                <div class="feature-card">
                    <h3>Jane Smith</h3>
                    <p>"I was amazed by the professionalism and expertise of the doctors."</p>
                    <p>Rating: ⭐⭐⭐⭐⭐</p>
                </div>
                <div class="feature-card">
                    <h3>Robert Brown</h3>
                    <p>"I felt truly cared for from the moment I walked in. Highly recommend!"</p>
                    <p>Rating: ⭐⭐⭐⭐</p>
                </div>
            </div>
        </section>

        <section id="about-us" style="margin-top: 40px;">
            <h2 class="text-center">About Us</h2>
            <p style="text-align: center; padding: 20px;">
                ABC Hospital has been providing quality healthcare for over 50 years. Our mission is to deliver the best medical services with a focus on compassionate care. Our team of experienced doctors and healthcare professionals are dedicated to your well-being. We offer a wide range of medical services, from routine check-ups to complex surgeries, ensuring the highest standards of care.
            </p>
        </section>
    </div>

    <footer style="background: #2c3e50; color: white; padding: 20px 0; margin-top: 40px;">
        <div class="container text-center">
            <p>© 2024 ABC Hospital. All rights reserved.</p>
            <p>Contact us: info@abchospital.com | Emergency: +1234567890</p>
        </div>
    </footer>

    <script>
        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add animation on scroll for feature cards
        window.addEventListener('scroll', function() {
            const cards = document.querySelectorAll('.feature-card');
            cards.forEach(card => {
                const cardPosition = card.getBoundingClientRect().top;
                const screenPosition = window.innerHeight;
                if(cardPosition < screenPosition) {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }
            });
        });
    </script>
</body>
</html>
