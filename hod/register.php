<?php
session_start();
include '../include/db.php';

$errors = [];
$success = '';

// Fetch all branches from the database for the dropdown
$branches_query = "SELECT branch_name FROM branches ORDER BY branch_name";
$branches_result = mysqli_query($conn, $branches_query);
$existing_branches = [];
if ($branches_result) {
    while ($row = mysqli_fetch_assoc($branches_result)) {
        $existing_branches[] = $row['branch_name'];
    }
}

// Handle form submission for new HOD registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_hod'])) {
    // Sanitize and validate inputs
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $hod_name = mysqli_real_escape_string($conn, $_POST['hod_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $college_name = mysqli_real_escape_string($conn, $_POST['college_name']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    // Determine the branch name from the form
    $selected_branch = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $new_branch = isset($_POST['new_branch_name']) ? mysqli_real_escape_string($conn, $_POST['new_branch_name']) : '';

    if ($selected_branch === 'other' && !empty($new_branch)) {
        $branch_name = $new_branch;
    } else if ($selected_branch !== 'other' && in_array($selected_branch, $existing_branches)) {
        $branch_name = $selected_branch;
    } else {
        $errors[] = 'Invalid branch selection.';
    }

    // Check for file upload
    $logo_path = '';
    if (isset($_FILES['college_logo']) && $_FILES['college_logo']['error'] === UPLOAD_ERR_OK) {
        $logo_tmp_name = $_FILES['college_logo']['tmp_name'];
        $logo_name = basename($_FILES['college_logo']['name']);
        $logo_extension = pathinfo($logo_name, PATHINFO_EXTENSION);
        
        // Define the target directory
        $upload_dir = '../logo/';
    }

    if (empty($username) || empty($password) || empty($hod_name) || empty($email) || empty($branch_name) || empty($college_name) || empty($address) || !isset($_FILES['college_logo']) || $_FILES['college_logo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'All fields are required.';
    } else {
        // Start a transaction for data integrity
        mysqli_begin_transaction($conn);
        try {
            // Check if the determined branch already exists
            $query = "SELECT branch_id FROM branches WHERE branch_name = '$branch_name'";
            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $branch = mysqli_fetch_assoc($result);
                $branch_id = $branch['branch_id'];
            } else {
                // If branch doesn't exist, create it
                $query = "INSERT INTO branches (branch_name) VALUES ('$branch_name')";
                if (!mysqli_query($conn, $query)) {
                    throw new Exception('Error creating branch: ' . mysqli_error($conn));
                }
                $branch_id = mysqli_insert_id($conn);
            }

            // Insert into users table
            $query = "INSERT INTO users (username, password, role, email) VALUES ('$username', '$password', 'hod', '$email')";
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Error creating user: ' . mysqli_error($conn));
            }
            $user_id = mysqli_insert_id($conn);

            // Handle the logo file upload after user_id is generated
            $logo_filename = $user_id . '.' . $logo_extension;
            $logo_full_path = $upload_dir . $logo_filename;
            if (!move_uploaded_file($logo_tmp_name, $logo_full_path)) {
                throw new Exception('Error uploading logo file.');
            }

            // Insert into hod table with only the filename
            $query = "INSERT INTO hod (user_id, branch_id, hod_name, email, created_by, college_name, address, college_logo)
                      VALUES ($user_id, $branch_id, '$hod_name', '$email', $user_id, '$college_name', '$address', '$logo_filename')";
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Error adding HOD details: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);

            // Set session variables and redirect
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'hod';
            
            header('Location: hod_dashboard.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Timetable Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .register-container {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 500px;
        }
        h2 {
            font-size: 2.25rem;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
            .full-width {
                grid-column: span 2;
            }
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }
        .required-star {
            color: red;
        }
        .form-group input, .form-group select {
            display: block;
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-sizing: border-box;
        }
        .form-group input[type="file"] {
            padding: 0.5rem;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }
        .message-container {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        .success-message {
            background-color: #d1fae5;
            color: #065f46;
        }
        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background-color: #2563eb;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
        .login-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: #4b5563;
        }
        .login-link a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Register HOD</h2>
        <?php if (!empty($errors)): ?>
            <div class="message-container error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="message-container success-message">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <div class="form-group">
                <label for="username">Username<span class="required-star">*</span></label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password<span class="required-star">*</span></label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="hod_name">HOD Name<span class="required-star">*</span></label>
                <input type="text" id="hod_name" name="hod_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email<span class="required-star">*</span></label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group full-width">
                <label for="branch_name">Branch Name<span class="required-star">*</span></label>
                <select id="branch_name" name="branch_name" required>
                    <option value="">Select a branch</option>
                    <?php foreach ($existing_branches as $branch): ?>
                        <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other (Specify below)</option>
                </select>
            </div>
            <div id="new_branch_group" class="form-group full-width hidden">
                <label for="new_branch_name">New Branch Name<span class="required-star">*</span></label>
                <input type="text" id="new_branch_name" name="new_branch_name" disabled>
            </div>
            <div class="form-group full-width">
                <label for="college_name">College Name<span class="required-star">*</span></label>
                <input type="text" id="college_name" name="college_name" required>
            </div>
            <div class="form-group full-width">
                <label for="address">Address<span class="required-star">*</span></label>
                <input type="text" id="address" name="address" required>
            </div>
            <div class="form-group full-width">
                <label for="college_logo">College Logo<span class="required-star">*</span></label>
                <input type="file" id="college_logo" name="college_logo" accept="image/*" required>
            </div>
            <div class="form-group full-width">
                <button type="submit" name="register_hod" class="btn">Register</button>
            </div>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Log in here.</a>
        </div>
    </div>
    <script>
        document.getElementById('branch_name').addEventListener('change', function() {
            var newBranchGroup = document.getElementById('new_branch_group');
            var newBranchInput = document.getElementById('new_branch_name');
            if (this.value === 'other') {
                newBranchGroup.classList.remove('hidden');
                newBranchInput.disabled = false;
                newBranchInput.required = true;
            } else {
                newBranchGroup.classList.add('hidden');
                newBranchInput.disabled = true;
                newBranchInput.required = false;
            }
        });
    </script>
</body>
</html>
