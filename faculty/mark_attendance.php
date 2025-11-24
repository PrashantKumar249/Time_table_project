<?php
session_start();
include '../include/db.php'; // Assuming config.php has $conn

// Check if user is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: faculty_login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];
$today = date('Y-m-d');
$college_start_time = '09:00:00'; // Assume college starts at 9 AM
$current_time = date('H:i:s');

// Check if attendance already marked for today
$check_query = "SELECT attendance_id FROM faculty_attendance WHERE faculty_id = $faculty_id AND attendance_date = '$today'";
$check_result = mysqli_query($conn, $check_query);
$already_marked = mysqli_num_rows($check_result) > 0;

// Handle attendance marking
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : '';
    
    if (empty($status)) {
        $error = 'Please select attendance status.';
    } elseif (strtotime($current_time) >= strtotime($college_start_time)) {
        $error = 'Attendance marking is only allowed before college start time (9:00 AM).';
    } elseif ($already_marked) {
        $error = 'Attendance already marked for today.';
    } else {
        $query = "INSERT INTO faculty_attendance (faculty_id, attendance_date, status, reason) VALUES ($faculty_id, '$today', '$status', '$reason')";
        if (mysqli_query($conn, $query)) {
            $success = 'Attendance marked successfully!';
            $already_marked = true; // Update flag
        } else {
            $error = 'Error marking attendance: ' . mysqli_error($conn);
        }
    }
}

// Fetch faculty data for header
$query = "SELECT f.faculty_name, f.email, b.branch_name
          FROM faculty f
          JOIN branches b ON f.branch_id = b.branch_id
          WHERE f.faculty_id = $faculty_id";
$result = mysqli_query($conn, $query);
$faculty_data = mysqli_fetch_assoc($result);

// Fetch today's attendance if marked
$attendance_query = "SELECT status, reason FROM faculty_attendance WHERE faculty_id = $faculty_id AND attendance_date = '$today'";
$attendance_result = mysqli_query($conn, $attendance_query);
$today_attendance = mysqli_fetch_assoc($attendance_result);
?>
<?php include 'header.php'; ?>
<div class="page-container">
    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1 class="page-title">Mark Attendance</h1>
            <p class="page-subtitle">Mark your attendance for today. Available only before 9:00 AM. If absent, provide a reason for substitute assignment.</p>
            <a href="faculty_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <!-- Attendance Section -->
    <main class="page-main">
        <section class="attendance-section">
            <?php if ($already_marked): ?>
                <div class="attendance-status">
                    <div class="status-icon <?php echo ($today_attendance['status'] === 'present') ? 'status-present' : 'status-absent'; ?>">
                        <?php echo ($today_attendance['status'] === 'present') ? '✅' : '❌'; ?>
                    </div>
                    <h2 class="status-title">Attendance Marked for Today</h2>
                    <p class="status-subtitle">Status: <strong><?php echo ucfirst($today_attendance['status']); ?></strong></p>
                    <?php if ($today_attendance['status'] === 'absent' && !empty($today_attendance['reason'])): ?>
                        <p class="status-reason"><strong>Reason:</strong> <?php echo htmlspecialchars($today_attendance['reason']); ?></p>
                    <?php endif; ?>
                    <a href="faculty_dashboard.php" class="btn-secondary">Back to Dashboard</a>
                </div>
            <?php elseif (strtotime($current_time) >= strtotime($college_start_time)): ?>
                <div class="empty-state">
                    <div class="empty-icon">⏰</div>
                    <p class="empty-text">Attendance marking is closed for today. It is only available before 9:00 AM.</p>
                    <a href="faculty_dashboard.php" class="btn-secondary">Return to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="form-container">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form method="post" class="attendance-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Today's Date</label>
                                <p class="form-static"><?php echo date('F j, Y'); ?></p>
                                <small class="form-help">Current time: <?php echo date('g:i A'); ?></small>
                            </div>
                            <div class="form-group">
                                <label for="status">Attendance Status</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="status" value="present" required> Present
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="status" value="absent"> Absent
                                    </label>
                                </div>
                                <small class="form-help">Select your status for today. If absent, provide a reason below.</small>
                            </div>
                        </div>
                        <div id="reason-group" class="form-row" style="display: none;">
                            <div class="form-group full-width">
                                <label for="reason">Reason for Absence</label>
                                <textarea id="reason" name="reason" rows="4" placeholder="Provide a reason (e.g., illness, personal emergency). This will help assign a substitute." class="form-input"></textarea>
                                <small class="form-help">Reason is required for absence marking.</small>
                            </div>
                        </div>
                        <button type="submit" name="mark_attendance" class="btn-primary full-width">Mark Attendance</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
    :root {
        --primary-color: #3b82f6;
        --secondary-color: #10b981;
        --danger-color: #ef4444;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --bg-primary: #f8fafc;
        --bg-card: #ffffff;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .page-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
        font-family: 'Inter', sans-serif;
    }

    .page-header {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        text-align: center;
        border: 1px solid var(--border-color);
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.025em;
    }

    .page-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin: 0;
    }

    .back-btn {
        display: inline-block;
        margin-top: 1rem;
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .back-btn:hover {
        background-color: var(--primary-color);
        color: white;
    }

    .page-main {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
    }

    .attendance-section {
        margin-bottom: 2rem;
    }

    .form-container {
        max-width: 600px;
        margin: 0 auto;
    }

    .attendance-status {
        text-align: center;
        padding: 3rem 1rem;
    }

    .status-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }

    .status-present {
        color: var(--secondary-color);
    }

    .status-absent {
        color: var(--danger-color);
    }

    .status-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .status-subtitle {
        font-size: 1.125rem;
        color: var(--text-secondary);
        margin: 0 0 1rem 0;
    }

    .status-reason {
        font-size: 1rem;
        color: var(--text-primary);
        margin: 0;
        font-style: italic;
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid var(--danger-color);
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .attendance-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-static {
        padding: 0.875rem 1rem;
        background: #f8fafc;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 1rem;
        font-weight: 500;
    }

    .radio-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .radio-label {
        display: flex;
        align-items: center;
        font-size: 0.95rem;
        color: var(--text-primary);
        cursor: pointer;
        padding: 0.75rem;
        border-radius: 8px;
        transition: background-color 0.2s ease;
        border: 1px solid var(--border-color);
    }

    .radio-label:hover {
        background-color: #f8fafc;
    }

    .radio-label input[type="radio"] {
        margin-right: 0.75rem;
        transform: scale(1.2);
    }

    .form-input {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: #ffffff;
        color: var(--text-primary);
        font-size: 1rem;
        transition: all 0.2s ease;
        font-family: inherit;
        resize: vertical;
    }

    .form-input::placeholder {
        color: #cbd5e1;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: translateY(-1px);
    }

    .form-help {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
        display: block;
    }

    .btn-primary {
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary-color), #2563eb);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }

    .full-width {
        width: 100%;
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 0.5rem;
        }

        .page-header {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .radio-group {
            flex-direction: row;
            flex-wrap: wrap;
        }

        .radio-label {
            flex: 1;
            min-width: 120px;
        }

        .form-static {
            font-size: 0.875rem;
        }
    }
</style>
<script>
    // Show/hide reason field based on status
    document.addEventListener('DOMContentLoaded', function() {
        const statusRadios = document.querySelectorAll('input[name="status"]');
        const reasonGroup = document.getElementById('reason-group');
        const reasonInput = document.getElementById('reason');

        function toggleReason() {
            if (document.querySelector('input[name="status"]:checked').value === 'absent') {
                reasonGroup.style.display = 'block';
                reasonInput.required = true;
            } else {
                reasonGroup.style.display = 'none';
                reasonInput.required = false;
                reasonInput.value = '';
            }
        }

        statusRadios.forEach(radio => radio.addEventListener('change', toggleReason));

        // Auto-focus on date input
        document.getElementById('leave_date').focus();
    });
</script>
<?php include 'footer.php'; ?>