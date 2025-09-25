<?php
include '../include/db.php';

// Check if user is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit;
}

// Get HOD's branch_id
$query = "SELECT branch_id FROM hod WHERE user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: login.php');
    exit;
}
$hod = mysqli_fetch_assoc($result);
$branch_id = $hod['branch_id'];

// Handle form submissions
$errors = [];
$success = '';

// Add Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $faculty_name = mysqli_real_escape_string($conn, $_POST['faculty_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    if (empty($username) || empty($password) || empty($faculty_name) || empty($email)) {
        $errors[] = 'All fields are required';
    } else {
        $query = "INSERT INTO users (username, password, role, email) VALUES ('$username', '$password', 'faculty', '$email')";
        if (mysqli_query($conn, $query)) {
            $user_id = mysqli_insert_id($conn);
            $query = "INSERT INTO faculty (user_id, branch_id, faculty_name, email, created_by) 
                      VALUES ($user_id, $branch_id, '$faculty_name', '$email', " . (int)$_SESSION['user_id'] . ")";
            if (mysqli_query($conn, $query)) {
                $success = 'Faculty added successfully';
            } else {
                $errors[] = 'Error adding faculty: ' . mysqli_error($conn);
            }
        } else {
            $errors[] = 'Error creating user: ' . mysqli_error($conn);
        }
    }
}

// Add HOD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hod'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $hod_name = mysqli_real_escape_string($conn, $_POST['hod_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    if (empty($username) || empty($password) || empty($hod_name) || empty($email)) {
        $errors[] = 'All fields are required';
    } else {
        $query = "INSERT INTO users (username, password, role, email) VALUES ('$username', '$password', 'hod', '$email')";
        if (mysqli_query($conn, $query)) {
            $user_id = mysqli_insert_id($conn);
            $query = "INSERT INTO hod (user_id, branch_id, hod_name, email, created_by) 
                      VALUES ($user_id, $branch_id, '$hod_name', '$email', " . (int)$_SESSION['user_id'] . ")";
            if (mysqli_query($conn, $query)) {
                $success = 'HOD added successfully';
            } else {
                $errors[] = 'Error adding HOD: ' . mysqli_error($conn);
            }
        } else {
            $errors[] = 'Error creating user: ' . mysqli_error($conn);
        }
    }
}

// Add Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
    if (empty($branch_name)) {
        $errors[] = 'Branch name is required';
    } else {
        $query = "INSERT INTO branches (branch_name) VALUES ('$branch_name')";
        if (mysqli_query($conn, $query)) {
            $success = 'Branch added successfully';
        } else {
            $errors[] = 'Error adding branch: ' . mysqli_error($conn);
        }
    }
}

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_leave']) || isset($_POST['reject_leave']))) {
    $leave_id = (int)$_POST['leave_id'];
    $status = isset($_POST['approve_leave']) ? 'approved' : 'rejected';
    $query = "UPDATE faculty_leave SET status = '$status' WHERE leave_id = $leave_id AND faculty_id IN 
              (SELECT faculty_id FROM faculty WHERE branch_id = $branch_id)";
    if (mysqli_query($conn, $query)) {
        $success = "Leave request $status successfully";
    } else {
        $errors[] = 'Error updating leave request: ' . mysqli_error($conn);
    }
}

// Fetch pending leave requests
$query = "SELECT fl.leave_id, fl.leave_date, fl.reason, fl.status, f.faculty_name 
          FROM faculty_leave fl 
          JOIN faculty f ON fl.faculty_id = f.faculty_id 
          WHERE f.branch_id = $branch_id AND fl.status = 'pending'";
$result = mysqli_query($conn, $query);
$leave_requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leave_requests[] = $row;
}

// Fetch sections for timetable generation
$query = "SELECT section_id, section_name, year, semester FROM sections WHERE branch_id = $branch_id";
$result = mysqli_query($conn, $query);
$sections = [];
while ($row = mysqli_fetch_assoc($result)) {
    $sections[] = $row;
}

// Fetch branches
$query = "SELECT * FROM branches";
$result = mysqli_query($conn, $query);
$branches = [];
while ($row = mysqli_fetch_assoc($result)) {
    $branches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - Timetable Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        .nav {
            margin-bottom: 20px;
            text-align: center;
        }
        .nav a {
            margin: 0 10px;
            color: #007bff;
            text-decoration: none;
        }
        .nav a:hover {
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error, .success {
            text-align: center;
            margin-bottom: 10px;
        }
        .error { color: red; }
        .success { color: green; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .section {
            margin-top: 20px;
        }
    </style>
    <script>
        function validateForm(formId) {
            var form = document.getElementById(formId);
            var inputs = form.getElementsByTagName('input');
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].required && !inputs[i].value.trim()) {
                    alert('All fields are required');
                    return false;
                }
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>HOD Dashboard</h1>
        <div class="nav">
            <a href="logout.php">Logout</a>
            <a href="timetable_view.php">View Timetables</a>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        <!-- Add Faculty Form -->
        <div class="section">
            <h2>Add Faculty</h2>
            <form id="addFacultyForm" method="post" onsubmit="return validateForm('addFacultyForm');">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="faculty_name">Faculty Name</label>
                    <input type="text" id="faculty_name" name="faculty_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" required>
                </div>
                <button type="submit" name="add_faculty">Add Faculty</button>
            </form>
        </div>
        <!-- Add HOD Form -->
        <div class="section">
            <h2>Add HOD</h2>
            <form id="addHodForm" method="post" onsubmit="return validateForm('addHodForm');">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="hod_name">HOD Name</label>
                    <input type="text" id="hod_name" name="hod_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" required>
                </div>
                <button type="submit" name="add_hod">Add HOD</button>
            </form>
        </div>
        <!-- Add Branch Form -->
        <div class="section">
            <h2>Add Branch</h2>
            <form id="addBranchForm" method="post" onsubmit="return validateForm('addBranchForm');">
                <div class="form-group">
                    <label for="branch_name">Branch Name</label>
                    <input type="text" id="branch_name" name="branch_name" required>
                </div>
                <button type="submit" name="add_branch">Add Branch</button>
            </form>
        </div>
        <!-- Branches List -->
        <div class="section">
            <h2>Branches</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Created At</th>
                </tr>
                <?php foreach ($branches as $branch): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($branch['branch_id']); ?></td>
                        <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                        <td><?php echo htmlspecialchars($branch['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <!-- Leave Requests -->
        <div class="section">
            <h2>Pending Leave Requests</h2>
            <table>
                <tr>
                    <th>Faculty Name</th>
                    <th>Leave Date</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($leave_requests as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['faculty_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_date']); ?></td>
                        <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                                <button type="submit" name="approve_leave">Approve</button>
                                <button type="submit" name="reject_leave" style="background-color: red;">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <!-- Generate Timetable Form -->
        <div class="section">
            <h2>Generate Timetable</h2>
            <form method="post" action="generate_timetable.php">
                <div class="form-group">
                    <label for="section_id">Section (Year, Semester)</label>
                    <select id="section_id" name="section_id" required>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['section_id']; ?>">
                                <?php echo htmlspecialchars($section['section_name'] . ' (Year ' . $section['year'] . ', Sem ' . $section['semester'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate_timetable">Generate Timetable</button>
            </form>
        </div>
    </div>
</body>
</html>