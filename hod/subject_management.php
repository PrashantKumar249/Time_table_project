<?php
// subject_management.php
// session_start();
include '../include/db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch HOD's branch (assuming hod table links user_id to branch_id; adjust query if different)
$hod_branch_query = "SELECT b.branch_id, b.branch_name FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = $user_id";
$hod_branch_result = mysqli_query($conn, $hod_branch_query);
$hod_branch = mysqli_fetch_assoc($hod_branch_result);
$hod_branch_id = $hod_branch['branch_id'] ?? 1; // Fallback to branch 1 (Computer Science)

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $branch_id = intval($_POST['branch_id']) ?: $hod_branch_id;
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    $subject_name = mysqli_real_escape_string($conn, trim($_POST['subject_name']));
    $weekly_load = intval($_POST['weekly_load']); // Maps to weekly_hours in DB

    if ($branch_id > 0 && $year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 2 && !empty($subject_name) && $weekly_load > 0) {
        // Generate simple code or leave blank; assume manual entry if needed
        $subject_code = 'SUB' . $year . $semester . rand(100, 999); // Auto-generate for demo
        $query = "INSERT INTO subjects (branch_id, subject_code, subject_name, weekly_hours, year, semester) VALUES ($branch_id, '$subject_code', '$subject_name', $weekly_load, $year, $semester)";
        if (mysqli_query($conn, $query)) {
            $success_subject = "Subject '$subject_name' added successfully!";
        } else {
            $error_subject = "Error adding subject: " . mysqli_error($conn);
        }
    } else {
        $error_subject = "Invalid input for subject.";
    }
}

// Fetch Subjects for List (for HOD's branch)
$subjects_query = "SELECT subject_id, subject_code, subject_name, weekly_hours, year, semester FROM subjects WHERE branch_id = $hod_branch_id ORDER BY year, semester, subject_name";
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $row;
}

$years = [1,2,3,4];
$semesters = [1,2,3,4,5,6,7,8];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - HOD Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .section { background: white; margin-bottom: 2rem; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section h2 { color: #1e293b; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; color: #475569; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-row .form-group { flex: 1 1 200px; min-width: 150px; }
        .btn { background: #3b82f6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: background 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn:hover { background: #2563eb; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .message { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .subjects-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .subjects-table th, .subjects-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .subjects-table th { background: #f1f5f9; font-weight: 600; }
        .setup-section { position: fixed; top: 20%; right: 2rem; background: #3b82f6; color: white; padding: 1rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; }
        .setup-section a { color: white; text-decoration: none; }
        @media (max-width: 768px) { .form-row { flex-direction: column; } .setup-section { position: static; margin: 1rem 0; text-align: center; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- Setup Subject in Sections - Corner Button -->
        <div class="setup-section">
            <h3>Setup Subjects in Sections</h3>
            <a href="setup_subject.php" class="btn">Go to Setup</a>
        </div>

        <!-- Section 1: Add Subject in Year -->
        <div class="section">
            <h2>Add Subject in Year</h2>
            <?php if (isset($success_subject)) echo "<div class='message success'>$success_subject</div>"; ?>
            <?php if (isset($error_subject)) echo "<div class='message error'>$error_subject</div>"; ?>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="year">Year</label>
                        <select name="year" id="year" required><option value="">Select</option><?php foreach($years as $y) echo "<option value='$y'>$y</option>"; ?></select>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" required><option value="">Select</option><?php foreach($semesters as $s) echo "<option value='$s'>$s</option>"; ?></select>
                    </div>
                    <div class="form-group">
                        <label for="subject_name">Subject Name</label>
                        <input type="text" name="subject_name" id="subject_name" placeholder="e.g., Programming" required>
                    </div>
                    <div class="form-group">
                        <label for="weekly_load">Weekly Load (hrs)</label>
                        <input type="number" name="weekly_load" id="weekly_load" min="1" max="10" value="4" required>
                    </div>
                </div>
                <button type="submit" name="add_subject" class="btn">Add Subject</button>
            </form>

            <!-- Show Subjects Table -->
            <h3>Subjects List</h3>
            <table class="subjects-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Weekly Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($subjects as $sub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                            <td><?php echo $sub['year']; ?></td>
                            <td><?php echo $sub['semester']; ?></td>
                            <td><?php echo $sub['weekly_hours']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="5">No subjects added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>