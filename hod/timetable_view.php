<?php
include '../include/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Determine accessible sections based on role
$sections = [];
if ($_SESSION['role'] === 'hod') {
    $query = "SELECT branch_id FROM hod WHERE user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    $hod = mysqli_fetch_assoc($result);
    $branch_id = $hod['branch_id'];
    $query = "SELECT section_id, section_name, year, semester, branch_name 
              FROM sections s 
              JOIN branches b ON s.branch_id = b.branch_id 
              WHERE s.branch_id = $branch_id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
} elseif ($_SESSION['role'] === 'faculty') {
    $query = "SELECT faculty_id FROM faculty WHERE user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    $faculty = mysqli_fetch_assoc($result);
    $faculty_id = $faculty['faculty_id'];
    $query = "SELECT DISTINCT s.section_id, s.section_name, s.year, s.semester, b.branch_name 
              FROM timetable_slots ts 
              JOIN sections s ON ts.section_id = s.section_id 
              JOIN branches b ON s.branch_id = b.branch_id 
              WHERE ts.faculty_id = $faculty_id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
}

// Fetch timetable for selected section
$timetable = [];
$selected_section_id = (int)($_GET['section_id'] ?? 0);
if ($selected_section_id) {
    $query = "SELECT ts.*, s.section_name, sub.subject_name, f.faculty_name, r.room_name 
              FROM timetable_slots ts 
              JOIN sections s ON ts.section_id = s.section_id 
              JOIN subjects sub ON ts.subject_id = sub.subject_id 
              JOIN faculty f ON ts.faculty_id = f.faculty_id 
              JOIN rooms r ON ts.room_id = r.room_id 
              WHERE ts.section_id = $selected_section_id 
              ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), ts.start_time";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $timetable[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable - Timetable Management System</title>
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
        h1 {
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
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
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
    </style>
    <script>
        function submitForm() {
            document.getElementById('viewForm').submit();
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>View Timetable</h1>
        <div class="nav">
            <a href="<?php echo $_SESSION['role'] === 'hod' ? 'hod_dashboard.php' : '../faculty/faculty_dashboard.php'; ?>">Back to Dashboard</a>
            <?php if ($selected_section_id): ?>
                <a href="export_pdf.php?section_id=<?php echo $selected_section_id; ?>">Export to PDF</a>
            <?php endif; ?>
        </div>
        <form id="viewForm" method="get">
            <div class="form-group">
                <label for="section_id">Select Section</label>
                <select id="section_id" name="section_id" onchange="submitForm()">
                    <option value="">Select a section</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['section_id']; ?>" <?php echo $selected_section_id == $section['section_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['branch_name'] . ' - ' . $section['section_name'] . ' (Year ' . $section['year'] . ', Sem ' . $section['semester'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if ($timetable): ?>
            <table>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Faculty</th>
                    <th>Room</th>
                </tr>
                <?php foreach ($timetable as $slot): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($slot['day_of_week']); ?></td>
                        <td><?php echo htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']); ?></td>
                        <td><?php echo htmlspecialchars($slot['subject_name']); ?></td>
                        <td><?php echo htmlspecialchars($slot['faculty_name']); ?></td>
                        <td><?php echo htmlspecialchars($slot['room_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php elseif ($selected_section_id): ?>
            <p>No timetable available for this section.</p>
        <?php endif; ?>
    </div>
</body>
</html>