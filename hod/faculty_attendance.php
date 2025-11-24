<?php
include '../include/db.php';
include 'header.php';

// Check if user is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit;
}

// Get HOD's branch_id
$query = "SELECT h.branch_id FROM hod h WHERE h.user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: login.php');
    exit;
}
$hod_data = mysqli_fetch_assoc($result);
$branch_id = $hod_data['branch_id'];

// Default to today's date
$date = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : date('Y-m-d');

// Fetch all faculty in the branch
$faculty_query = "SELECT f.faculty_id, f.faculty_name, f.email 
                  FROM faculty f 
                  WHERE f.branch_id = $branch_id 
                  ORDER BY f.faculty_name";
$faculty_result = mysqli_query($conn, $faculty_query);
$faculty_list = [];
while ($row = mysqli_fetch_assoc($faculty_result)) {
    $faculty_list[$row['faculty_id']] = $row;
}

// Fetch attendance for the selected date
$attendance_query = "SELECT fa.faculty_id, fa.status, fa.reason, fa.attendance_date 
                     FROM faculty_attendance fa 
                     WHERE fa.attendance_date = '$date' AND fa.faculty_id IN (SELECT faculty_id FROM faculty WHERE branch_id = $branch_id)";
$attendance_result = mysqli_query($conn, $attendance_query);
$attendance_data = [];
while ($row = mysqli_fetch_assoc($attendance_result)) {
    $attendance_data[$row['faculty_id']] = $row;
}
?>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: #f3f4f6;
    margin: 0;
    padding: 0;
}
.container {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}
.page-header {
    background-color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
    text-align: center;
}
.page-title {
    font-size: 2.25rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.5rem;
}
.page-subtitle {
    color: #6b7280;
    font-size: 1rem;
}
.message-container {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}
.error-message {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.success-message {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}
.message-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.section {
    background-color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
}
.date-filter-form {
    text-align: center;
    margin-bottom: 1rem;
}
.form-group {
    display: inline-block;
    margin-right: 1rem;
}
.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
    margin-bottom: 0.5rem;
}
.form-input {
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 1rem;
}
.btn-primary {
    padding: 0.5rem 1rem;
    background-color: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;
    font-weight: 600;
    margin-left: 0.5rem;
}
.btn-primary:hover {
    background-color: #2563eb;
}
.table-wrapper {
    overflow-x: auto;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.data-table thead tr {
    background-color: #f9fafb;
}
.data-table th, .data-table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}
.data-table th {
    font-weight: 600;
    color: #374151;
}
.data-table tr:hover {
    background-color: #f9fafb;
}
.data-table tr.even-row {
    background-color: #f8fafc;
}
.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-present { background-color: #d1fae5; color: #065f46; }
.status-absent { background-color: #fee2e2; color: #991b1b; }
.status-pending { background-color: #fef3c7; color: #d97706; }
.no-requests {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
    font-size: 1.125rem;
}
.attendance-summary {
    text-align: center;
    padding: 1rem;
    background-color: #f8fafc;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    margin-top: 1rem;
    font-size: 1rem;
    color: #374151;
}
.back-link {
    display: inline-block;
    margin-top: 1rem;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}
.back-link:hover {
    text-decoration: underline;
}
@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    .data-table th, .data-table td {
        padding: 0.75rem;
        font-size: 0.75rem;
    }
    .form-group {
        margin-right: 0;
        display: block;
        margin-bottom: 1rem;
    }
    .btn-primary {
        margin-left: 0;
        margin-top: 0.5rem;
        width: 100%;
    }
}
</style>
<div class="container">
    <div class="page-header">
        <h1 class="page-title">Faculty Attendance</h1>
        <p class="page-subtitle">View attendance records for your department's faculty on the selected date.</p>
        <!-- <a href="hod_dashboard.php" class="back-btn">← Back to Dashboard</a> -->
    </div>

    <!-- Date Filter -->
    <div class="section">
        <form method="get" class="date-filter-form">
            <div class="form-group">
                <label for="date">Select Date</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="form-input" required>
                <button type="submit" class="btn-primary">Filter</button>
            </div>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="section">
        <h2 class="section-title">Attendance for <?php echo date('F j, Y', strtotime($date)); ?></h2>
        <?php if (empty($faculty_list)): ?>
            <div class="no-requests">
                <p>No faculty found in your department.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Faculty Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Reason (if Absent)</th>
                            <th>Marked On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faculty_list as $faculty_id => $faculty): ?>
                            <?php 
                            $attendance = $attendance_data[$faculty_id] ?? null;
                            $status = $attendance ? ucfirst($attendance['status']) : 'Not Marked';
                            $reason = $attendance && $attendance['status'] === 'absent' && !empty($attendance['reason']) ? htmlspecialchars($attendance['reason']) : 'N/A';
                            $marked_on = $attendance ? date('M j, Y g:i A', strtotime($attendance['attendance_date'])) : 'N/A';
                            ?>
                            <tr class="<?php echo ($faculty_id % 2 == 0) ? 'even-row' : ''; ?>">
                                <td><?php echo htmlspecialchars($faculty['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($status === 'Present') ? 'status-present' : (($status === 'Absent') ? 'status-absent' : 'status-pending'); ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td title="<?php echo $reason; ?>"><?php echo $reason; ?></td>
                                <td><?php echo $marked_on; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="attendance-summary">
                <p><strong>Total Faculty:</strong> <?php echo count($faculty_list); ?> | 
                   <strong>Present:</strong> <?php echo count(array_filter($faculty_list, function($f) use ($attendance_data) { return isset($attendance_data[$f['faculty_id']]) && $attendance_data[$f['faculty_id']]['status'] === 'present'; })); ?> | 
                   <strong>Absent:</strong> <?php echo count(array_filter($faculty_list, function($f) use ($attendance_data) { return isset($attendance_data[$f['faculty_id']]) && $attendance_data[$f['faculty_id']]['status'] === 'absent'; })); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="hod_dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</div>
<?php include 'footer.php'; ?>