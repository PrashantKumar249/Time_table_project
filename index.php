<?php
session_start();
include 'include/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hod_login'])) {
        $username = mysqli_real_escape_string($conn, $_POST['hod_username']);
        $password = mysqli_real_escape_string($conn, $_POST['hod_password']);

        $query = "SELECT user_id, role FROM users WHERE username = '$username' AND password = '$password' AND role = 'hod'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            header('Location: hod/hod_dashboard.php');
            exit;
        } else {
            $error = "Invalid HOD username or password.";
        }
    } elseif (isset($_POST['faculty_login'])) {
        $email = mysqli_real_escape_string($conn, $_POST['faculty_email']);
        $password = mysqli_real_escape_string($conn, $_POST['faculty_password']);

        $query = "SELECT u.user_id, u.role, f.faculty_id, f.faculty_name, f.branch_id
                  FROM users u
                  LEFT JOIN faculty f ON u.user_id = f.user_id
                  WHERE u.email = '$email' AND u.password = '$password' AND u.role = 'faculty'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['faculty_id'] = $user['faculty_id'];
            $_SESSION['faculty_name'] = $user['faculty_name'];
            $_SESSION['branch_id'] = $user['branch_id'];
            header('Location: faculty/faculty_dashboard.php');
            exit;
        } else {
            $error = "Invalid faculty email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Timetable Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-wrapper {
            display: flex;
            max-width: 1000px;
            width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-radius: 16px;
            overflow: hidden;
            background: white;
        }
        .sidebar {
            width: 350px;
            background-color: #f8fafc;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-right: 1px solid #e2e8f0;
            transition: background-color 0.3s ease;
        }
        .sidebar.faculty {
            background-color: #f0fdf4;
            border-right-color: #dcfce7;
        }
        .sidebar h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            text-align: center;
        }
        .sidebar p {
            font-size: 1rem;
            color: #64748b;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .toggle-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        .toggle-btn:hover {
            background-color: #e2e8f0;
            border-color: #94a3b8;
            color: #334155;
            transform: translateY(-1px);
        }
        .login-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }
        .login-form {
            width: 100%;
            max-width: 350px;
            transition: opacity 0.3s ease, transform 0.3s ease;
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .login-form.hidden {
            opacity: 0;
            transform: translateY(10px) scale(0.98);
            pointer-events: none;
        }
        .login-form.faculty-form {
            transform: translateY(0) scale(1);
        }
        h2 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #1e293b;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            display: block;
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background-color: #ffffff;
            color: #1e293b;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        .form-group input::placeholder {
            color: #94a3b8;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-1px);
        }
        .error-message {
            color: #dc2626;
            text-align: center;
            margin-bottom: 1.5rem;
            background-color: #fef2f2;
            padding: 0.875rem;
            border-radius: 8px;
            border: 1px solid #fecaca;
            font-size: 0.875rem;
        }
        .btn {
            width: 100%;
            padding: 0.875rem;
            background-color: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn:active {
            transform: translateY(0);
        }
        .btn.faculty-btn {
            background-color: #10b981;
        }
        .btn.faculty-btn:hover {
            background-color: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        .register-link a {
            color: #3b82f6;
            font-weight: 500;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
            color: #2563eb;
        }
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                max-width: none;
                border-radius: 0;
                width: 100%;
                height: 100vh;
            }
            .sidebar {
                width: 100%;
                height: 40%;
                padding: 2rem;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            .login-container {
                padding: 2rem 1rem;
                height: 60%;
                justify-content: center;
            }
            .toggle-btn {
                top: 1rem;
                right: 1rem;
            }
            .login-form {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="sidebar" id="sidebar">
            <button class="toggle-btn" onclick="toggleLogin()">Switch to Faculty Login</button>
            <h2 id="sidebar-title">HOD Portal</h2>
            <p id="sidebar-desc">Manage timetables and faculty for your department efficiently.</p>
        </div>

        <div class="login-container">
            <!-- HOD Form -->
            <form method="post" class="login-form" id="hod-form">
                <h2>HOD Login</h2>
                <?php if ($error): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="hod_username">Username</label>
                    <input type="text" id="hod_username" name="hod_username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="hod_password">Password</label>
                    <input type="password" id="hod_password" name="hod_password" placeholder="Enter your password" required>
                </div>
                <button type="submit" name="hod_login" class="btn">Login</button>
                <div class="register-link">
                    Don't have an account? <a href="hod/register.php">Register here</a>
                </div>
            </form>

            <!-- Faculty Form -->
            <form method="post" class="login-form faculty-form hidden" id="faculty-form">
                <h2>Faculty Login</h2>
                <?php if ($error): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="faculty_email">Email</label>
                    <input type="email" id="faculty_email" name="faculty_email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="faculty_password">Password</label>
                    <input type="password" id="faculty_password" name="faculty_password" placeholder="Enter your password" required>
                </div>
                <button type="submit" name="faculty_login" class="btn faculty-btn">Login</button>
                <!-- <div class="register-link">
                    Don't have an account? <a href="faculty/register.php">Register here</a>
                </div> -->
            </form>
        </div>
    </div>

    <script>
        let isFaculty = false;

        function toggleLogin() {
            const sidebar = document.getElementById('sidebar');
            const hodForm = document.getElementById('hod-form');
            const facultyForm = document.getElementById('faculty-form');
            const sidebarTitle = document.getElementById('sidebar-title');
            const sidebarDesc = document.getElementById('sidebar-desc');
            const toggleBtn = document.querySelector('.toggle-btn');

            isFaculty = !isFaculty;

            if (isFaculty) {
                // Switch to Faculty
                sidebar.classList.add('faculty');
                sidebarTitle.textContent = 'Faculty Portal';
                sidebarDesc.textContent = 'Access your timetable, request leaves, and manage attendance.';
                toggleBtn.textContent = 'Switch to HOD Login';
                hodForm.classList.add('hidden');
                facultyForm.classList.remove('hidden');
            } else {
                // Switch to HOD
                sidebar.classList.remove('faculty');
                sidebarTitle.textContent = 'HOD Portal';
                sidebarDesc.textContent = 'Manage timetables and faculty for your department efficiently.';
                toggleBtn.textContent = 'Switch to Faculty Login';
                facultyForm.classList.add('hidden');
                hodForm.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>