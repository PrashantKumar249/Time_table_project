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

// Handle timetable generation
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $section_id = (int)$_POST['section_id'];
    if (empty($section_id)) {
        $errors[] = 'Section is required';
    } else {
        // Verify section belongs to HOD's branch
        $query = "SELECT section_id FROM sections WHERE section_id = $section_id AND branch_id = $branch_id";
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) == 0) {
            $errors[] = 'Invalid section for your branch';
        } else {
            // Clear existing timetable for the section
            $query = "DELETE FROM timetable_slots WHERE section_id = $section_id";
            mysqli_query($conn, $query);

            // Fetch subjects for the section's branch
            $query = "SELECT subject_id, weekly_hours FROM subjects WHERE branch_id = $branch_id";
            $result = mysqli_query($conn, $query);
            $subjects = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $subjects[] = $row;
            }

            // Fetch available faculty for the branch
            $query = "SELECT f.faculty_id, fs.subject_id 
                      FROM faculty f 
                      JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id 
                      WHERE f.branch_id = $branch_id";
            $result = mysqli_query($conn, $query);
            $faculty_subjects = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $faculty_subjects[] = $row;
            }

            // Fetch available rooms
            $query = "SELECT room_id FROM rooms";
            $result = mysqli_query($conn, $query);
            $rooms = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rooms[] = $row;
            }

            // Fetch approved leaves
            $query = "SELECT faculty_id, leave_date FROM faculty_leave WHERE status = 'approved'";
            $result = mysqli_query($conn, $query);
            $leaves = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $leaves[] = $row;
            }

            // Define time slots (Monday-Friday, 9:00 AM-5:00 PM, 1-hour slots)
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            $times = ['09:00:00', '10:00:00', '11:00:00', '12:00:00', '13:00:00', '14:00:00', '15:00:00', '16:00:00'];

            // Generate timetable
            foreach ($subjects as $subject) {
                $weekly_hours = $subject['weekly_hours'];
                $subject_id = $subject['subject_id'];

                // Find eligible faculty for the subject
                $eligible_faculty = array_filter($faculty_subjects, function($fs) use ($subject_id) {
                    return $fs['subject_id'] == $subject_id;
                });
                if (empty($eligible_faculty)) {
                    $errors[] = "No faculty available for subject ID $subject_id";
                    continue;
                }

                // Assign slots for weekly hours
                $assigned_hours = 0;
                while ($assigned_hours < $weekly_hours) {
                    $day = $days[array_rand($days)];
                    $start_time = $times[array_rand($times)];
                    $end_time = date('H:i:s', strtotime($start_time) + 3600); // 1-hour slot

                    // Check for conflicts
                    $faculty_id = $eligible_faculty[array_rand(array_keys($eligible_faculty))]['faculty_id'];
                    $room_id = $rooms[array_rand(array_keys($rooms))]['room_id'];
                    $query = "SELECT COUNT(*) FROM timetable_slots 
                              WHERE (section_id = $section_id OR faculty_id = $faculty_id OR room_id = $room_id) 
                              AND day_of_week = '$day' AND start_time = '$start_time'";
                    $result = mysqli_query($conn, $query);
                    $count = mysqli_fetch_array($result)[0];
                    if ($count > 0) {
                        continue; // Conflict found, try another slot
                    }

                    // Check for leaves
                    $query = "SELECT COUNT(*) FROM faculty_leave 
                              WHERE faculty_id = $faculty_id AND leave_date = CURRENT_DATE AND status = 'approved'";
                    $result = mysqli_query($conn, $query);
                    if (mysqli_fetch_array($result)[0] > 0) {
                        continue; // Faculty on leave, try another
                    }

                    // Assign slot
                    $query = "INSERT INTO timetable_slots (section_id, subject_id, faculty_id, room_id, day_of_week, start_time, end_time) 
                              VALUES ($section_id, $subject_id, $faculty_id, $room_id, '$day', '$start_time', '$end_time')";
                    if (mysqli_query($conn, $query)) {
                        $assigned_hours++;
                    } else {
                        $errors[] = 'Error assigning slot: ' . mysqli_error($conn);
                    }
                }
            }
            if (empty($errors)) {
                $success = 'Timetable generated successfully for section ID ' . $section_id;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable - Timetable Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        h1 {
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
        select {
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
        .error, .success {
            text-align: center;
            margin-bottom: 10px;
        }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <a href="view_timetable.php" class="view-top-right" style="position:absolute;top:12px;right:12px;text-decoration:none;padding:6px 10px;border-radius:4px;background:#007bff;color:#fff;border:1px solid #006ae6;font-size:13px;">View Timetables</a>
        <h1>Generate Timetable</h1>
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
        <form method="post">
            <div class="form-group">
                <label for="section_id">Section (Year, Semester)</label>
                <select id="section_id" name="section_id" required>
                    <?php
                    $query = "SELECT section_id, section_name, year, semester FROM sections WHERE branch_id = $branch_id";
                    $result = mysqli_query($conn, $query);
                    while ($section = mysqli_fetch_assoc($result)): ?>
                        <option value="<?php echo $section['section_id']; ?>">
                            <?php echo htmlspecialchars($section['section_name'] . ' (Year ' . $section['year'] . ', Sem ' . $section['semester'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <button type="submit" name="generate_timetable">Generate Timetable</button>
                <a href="view_timetable.php" style="text-decoration:none;padding:6px 10px;border-radius:4px;background:#eee;color:#333;border:1px solid #ccc;font-size:14px;">View Timetables</a>
            </div>
        </form>
    </div>
</body>
</html>