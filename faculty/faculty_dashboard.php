<?php
include '../include/db.php';

// Check if user is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

// Get faculty_id
$query = "SELECT faculty_id FROM faculty WHERE user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: login.php');
    exit;
}
$faculty = mysqli_fetch_assoc($result);
$faculty_id = $faculty['faculty_id'];

// Handle leave request submission
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_leave'])) {
    $leave_date = mysqli_real_escape_string($conn, $_POST['leave_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    if (empty($leave_date) || empty($reason)) {
        $errors[] = 'Leave date and reason are required';
    } else {
        $query = "INSERT INTO faculty_leave (faculty_id, leave_date, reason, status) 
                  VALUES ($faculty_id, '$leave_date', '$reason', 'pending')";
        if (mysqli_query($conn, $query)) {
            $success = 'Leave request submitted successfully';
        } else {
            $errors[] = 'Error submitting leave request: ' . mysqli_error($conn);
        }
    }
}

// Fetch faculty's timetable
$query = "SELECT ts.*, s.section_name, sub.subject_name, r.room_name 
          FROM timetable_slots ts 
          JOIN sections s ON ts.section_id = s.section_id 
          JOIN subjects sub ON ts.subject_id = sub.subject_id 
          JOIN rooms r ON ts.room_id = r.room_id 
          WHERE ts.faculty_id = $faculty_id";
$result = mysqli_query($conn, $query);
$timetable = [];
while ($row = mysqli_fetch_assoc($result)) {
    $timetable[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Timetable Management System</title>
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
        input[type="date"], textarea {
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
        function validateForm() {
            var date = document.getElementById('leave_date').value;
            var reason = document.getElementById('reason').value;
            if (!date || !reason.trim()) {
                alert('Leave date and reason are required');
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Faculty Dashboard</h1>
        <div class="nav">
            <a href="logout.php">Logout</a>
            <a href="../hod/timetable_view.php">View Full Timetable</a>
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
        <!-- Request Leave Form -->
        <div class="section">
            <h2>Request Leave</h2>
            <form method="post" onsubmit="return validateForm();">
                <div class="form-group">
                    <label for="leave_date">Leave Date</label>
                    <input type="date" id="leave_date" name="leave_date" required>
                </div>
                <div class="form-group">
                    <label for="reason">Reason</label>
                    <textarea id="reason" name="reason" required></textarea>
                </div>
                <button type="submit" name="request_leave">Submit Leave Request</button>
            </form>
        </div>
        <!-- Faculty Timetable -->
        <div class="section">
            <h2>My Timetable</h2>
            <table>
                <tr>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Room</th>
                    <th>Day</th>
                    <th>Time</th>
                </tr>
                <?php foreach ($timetable as $slot): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($slot['section_name']); ?></td>
                        <td><?php echo htmlspecialchars($slot['subject_name']); ?></td>
                        <td><?php echo htmlspecialchars($slot['room_name']); ?></td>
                        <td><?php echo htmlspecialchars($slot['day_of_week']); ?></td>
                        <td><?php echo htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>