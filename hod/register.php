<?php
include '../include/db.php';

// Check if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'hod') {
    header('Location: dashboard.php');
    exit;
}

// Handle registration form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    $hod_name = mysqli_real_escape_string($conn, $_POST['hod_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $branch_id = intval($_POST['branch_id']);

    // Validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($hod_name) || empty($email) || empty($branch_id)) {
        $errors[] = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    } else {
        // Check if username or email already exists
        $check_query = "SELECT user_id FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Username or email already registered';
        } else {
            // Check if branch_id exists
            $branch_query = "SELECT branch_id FROM branches WHERE branch_id = $branch_id";
            $branch_result = mysqli_query($conn, $branch_query);
            if (mysqli_num_rows($branch_result) == 0) {
                $errors[] = 'Invalid branch selected';
            } else {
                // Insert into users table (plain-text password, no hashing)
                $insert_user_query = "INSERT INTO users (username, password, role, email) VALUES ('$username', '$password', 'hod', '$email')";
                if (mysqli_query($conn, $insert_user_query)) {
                    $user_id = mysqli_insert_id($conn); // Get the inserted user_id
                    // Insert into hod table (self-created for initial HOD)
                    $insert_hod_query = "INSERT INTO hod (user_id, branch_id, hod_name, email, created_by) VALUES ($user_id, $branch_id, '$hod_name', '$email', $user_id)";
                    if (mysqli_query($conn, $insert_hod_query)) {
                        // Set session variables and redirect to dashboard
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['role'] = 'hod';
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to register HOD details: ' . mysqli_error($conn);
                        // Rollback: delete the user if hod insertion fails
                        mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");
                    }
                } else {
                    $errors[] = 'Registration failed: ' . mysqli_error($conn);
                }
            }
        }
    }
}

// Fetch branches for dropdown
$branches_query = "SELECT branch_id, branch_name FROM branches";
$branches_result = mysqli_query($conn, $branches_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Register - Timetable Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .register-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"], input[type="password"], input[type="email"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            font-size: 14px;
            text-align: center;
        }
        .switch-auth {
            text-align: center;
            margin-top: 10px;
        }
        .switch-auth a {
            color: #007bff;
            text-decoration: none;
        }
        .switch-auth a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        function validateForm() {
            var username = document.getElementById('username').value;
            var password = document.getElementById('password').value;
            var confirm_password = document.getElementById('confirm_password').value;
            var hod_name = document.getElementById('hod_name').value;
            var email = document.getElementById('email').value;
            var branch_id = document.getElementById('branch_id').value;
            if (!username || !password || !confirm_password || !hod_name || !email || !branch_id) {
                alert('All fields are required');
                return false;
            }
            if (password !== confirm_password) {
                alert('Passwords do not match');
                return false;
            }
            if (password.length < 6) {
                alert('Password must be at least 6 characters');
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="register-container">
        <h2>HOD Register</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" onsubmit="return validateForm();">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="hod_name">HOD Name</label>
                <input type="text" id="hod_name" name="hod_name" required>
            </div>
            <div class="form-group">
                <label for="branch_id">Branch</label>
                <select id="branch_id" name="branch_id" required>
                    <option value="">Select Branch</option>
                    <?php while ($branch = mysqli_fetch_assoc($branches_result)): ?>
                        <option value="<?php echo $branch['branch_id']; ?>">
                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="switch-auth">
            <a href="login.php">Already have an account? Login</a>
        </div>
        <div class="switch-role">
            <a href="../faculty/register.php">Register as Faculty</a>
        </div>
    </div>
</body>
</html>