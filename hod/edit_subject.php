<?php
// edit_subject.php
include '../include/db.php';

// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session check first
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'hod') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch HOD's branch
$hod_branch_id = 1; // Default fallback
$hod_branch_query = "SELECT b.branch_id FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = $user_id";
$hod_branch_result = mysqli_query($conn, $hod_branch_query);
if ($hod_branch_result && mysqli_num_rows($hod_branch_result) > 0) {
    $hod_branch = mysqli_fetch_assoc($hod_branch_result);
    $hod_branch_id = $hod_branch['branch_id'];
}

// Initialize variables
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$subject = null;
$error = '';
$success = '';

// Handle Update Subject FIRST (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    if ($subject_id > 0) {
        // Fetch the subject to validate and use current values if update fails
        $fetch_query = "SELECT * FROM subjects WHERE subject_id = $subject_id AND branch_id = $hod_branch_id";
        $fetch_result = mysqli_query($conn, $fetch_query);
        if ($fetch_result && mysqli_num_rows($fetch_result) > 0) {
            $subject = mysqli_fetch_assoc($fetch_result);
            // Process update
            $year = intval($_POST['year']);
            $semester = intval($_POST['semester']);
            $subject_code = mysqli_real_escape_string($conn, trim($_POST['subject_code']));
            $subject_name = mysqli_real_escape_string($conn, trim($_POST['subject_name']));
            $weekly_load = intval($_POST['weekly_load']);
            $type = mysqli_real_escape_string($conn, trim($_POST['type']));

            if ($year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 8 && !empty($subject_code) && !empty($subject_name) && $weekly_load > 0 && in_array($type, ['T', 'P'])) {
                // Check duplicate code excluding current subject
                $check_query = "SELECT subject_id FROM subjects WHERE branch_id = $hod_branch_id AND subject_code = '$subject_code' AND subject_id != $subject_id";
                $check_result = mysqli_query($conn, $check_query);
                if (mysqli_num_rows($check_result) == 0) {
                    $update_query = "UPDATE subjects SET subject_code = '$subject_code', type = '$type', subject_name = '$subject_name', weekly_hours = $weekly_load, year = $year, semester = $semester WHERE subject_id = $subject_id";
                    if (mysqli_query($conn, $update_query)) {
                        header('Location: subject_management.php?success=1');
                        exit;
                    } else {
                        $error = "Error updating subject: " . mysqli_error($conn);
                    }
                } else {
                    $error = "Subject code '$subject_code' already exists for this branch.";
                }
            } else {
                $error = "Invalid input. All fields required. Type must be T or P.";
            }
        } else {
            $error = "Subject not found or you don't have permission to edit it.";
        }
    } else {
        $error = "Invalid subject ID.";
    }
}

// If not POST or update failed, fetch subject for display
if ($subject_id > 0 && !$subject) {
    $fetch_query = "SELECT * FROM subjects WHERE subject_id = $subject_id AND branch_id = $hod_branch_id";
    $fetch_result = mysqli_query($conn, $fetch_query);
    if ($fetch_result && mysqli_num_rows($fetch_result) > 0) {
        $subject = mysqli_fetch_assoc($fetch_result);
    } else {
        $error = "Subject not found or you don't have permission to edit it.";
    }
} elseif ($subject_id <= 0) {
    $error = "Invalid subject ID.";
}

$years = [1, 2, 3, 4];
$semesters = [1, 2, 3, 4, 5, 6, 7, 8];

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subject - HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .section { background: white; margin-bottom: 2rem; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section h2 { color: #1e293b; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; color: #475569; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-row .form-group { flex: 1 1 200px; min-width: 150px; }
        .btn { background: #3b82f6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: background 0.2s; margin-right: 0.5rem; }
        .btn:hover { background: #2563eb; }
        .btn-cancel { background: #6b7280; }
        .btn-cancel:hover { background: #4b5563; }
        .message { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            .form-row .form-group { min-width: auto; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="section">
            <h2>Edit Subject</h2>
            <?php if (!empty($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($subject): ?>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="year">Year</label>
                            <select name="year" id="year" required>
                                <option value="">Select</option>
                                <?php foreach($years as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($subject['year'] == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select name="semester" id="semester" required>
                                <option value="">Select</option>
                                <?php foreach($semesters as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo ($subject['semester'] == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="type">Type</label>
                            <select name="type" id="type" required>
                                <option value="">Select Type</option>
                                <option value="T" <?php echo ($subject['type'] == 'T') ? 'selected' : ''; ?>>T (Theory)</option>
                                <option value="P" <?php echo ($subject['type'] == 'P') ? 'selected' : ''; ?>>P (Practical)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject_code">Subject Code</label>
                            <input type="text" name="subject_code" id="subject_code" value="<?php echo htmlspecialchars($subject['subject_code']); ?>" placeholder="e.g., BCS101" required>
                        </div>
                        <div class="form-group">
                            <label for="subject_name">Subject Name</label>
                            <input type="text" name="subject_name" id="subject_name" value="<?php echo htmlspecialchars($subject['subject_name']); ?>" placeholder="e.g., Programming" required>
                        </div>
                        <div class="form-group">
                            <label for="weekly_load">Weekly Load (hrs)</label>
                            <input type="number" name="weekly_load" id="weekly_load" min="1" max="10" value="<?php echo $subject['weekly_hours']; ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="update_subject" class="btn">Update Subject</button>
                    <a href="subject_management.php" class="btn btn-cancel">Cancel</a>
                </form>
            <?php else: ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <a href="subject_management.php" class="btn">Back to Subjects</a>
            <?php endif; ?>
        </div>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>