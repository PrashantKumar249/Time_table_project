<?php
// session_start();
include '../include/db.php';
include 'header.php';

// Check if user is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit;
}

// Get HOD's branch_id and name
$query = "SELECT h.branch_id, h.hod_name, b.branch_name FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: login.php');
    exit;
}
$hod_data = mysqli_fetch_assoc($result);
$branch_id = $hod_data['branch_id'];

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

// Fetch sections for timetable generation
$query = "SELECT section_id, section_name, year, semester FROM sections WHERE branch_id = $branch_id";
$result = mysqli_query($conn, $query);
$sections = [];
while ($row = mysqli_fetch_assoc($result)) {
    $sections[] = $row;
}

// Fetch all faculty names with their subjects for the current HOD's branch
$query = "SELECT f.faculty_name, f.email, s.subject_name
          FROM faculty f
          LEFT JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
          LEFT JOIN subjects s ON fs.subject_id = s.subject_id
          WHERE f.branch_id = $branch_id
          ORDER BY f.faculty_name";
$result = mysqli_query($conn, $query);
$faculty_subjects = [];
while ($row = mysqli_fetch_assoc($result)) {
    if (!isset($faculty_subjects[$row['faculty_name']])) {
        $faculty_subjects[$row['faculty_name']] = [
            'email' => $row['email'],
            'subjects' => []
        ];
    }
    if ($row['subject_name']) {
        $faculty_subjects[$row['faculty_name']]['subjects'][] = $row['subject_name'];
    }
}

// Fetch pending leave requests
$query = "SELECT fl.leave_id, fl.leave_date, fl.reason, fl.status, f.faculty_name
          FROM faculty_leave fl
          JOIN faculty f ON fl.faculty_id = f.faculty_id
          WHERE f.branch_id = $branch_id AND fl.status = 'pending' ORDER BY fl.leave_date";
$result = mysqli_query($conn, $query);
$leave_requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leave_requests[] = $row;
}
?>
<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background-color: #f3f4f6;
        margin: 0;
        padding: 0;
    }

    .dashboard-container {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .text-center-container {
        text-align: center;
        margin-bottom: 2rem;
    }

    .title {
        font-size: 2.25rem;
        font-weight: 700;
        color: #1f2937;
    }

    .subtitle {
        font-size: 0.875rem;
        color: #6b7280;
        margin-top: 0.5rem;
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
    }

    .message-list {
        margin-top: 0.5rem;
        list-style: disc;
        padding-left: 1.5rem;
        font-size: 0.875rem;
    }

    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .card-link {
        text-decoration: none;
    }

    .card {
        background-color: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease-in-out;
    }

    .card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        transform: translateY(-5px);
    }

    .card-icon {
        width: 4rem;
        height: 4rem;
        margin: 0 auto 1rem;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }

    .card-icon svg {
        width: 2rem;
        height: 2rem;
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
    }

    .card-subtitle {
        font-size: 0.875rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }

    .card-blue .card-icon {
        background-color: #3b82f6;
    }

    .card-green .card-icon {
        background-color: #22c55e;
    }

    .card-red .card-icon {
        background-color: #ef4444;
    }

    .card-orange .card-icon {
        background-color: #f97316;
    }

    .section-container {
        margin-top: 2rem;
        background-color: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        padding: 1.5rem;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .form-card {
        background-color: #f9fafb;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-top: 1rem;
    }

    .form-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 1rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    @media (min-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr 1fr;
        }

        .form-grid>*:last-child {
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

    .input-field {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        box-sizing: border-box;
        transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .input-field:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
    }

    .button {
        padding: 0.75rem 1.5rem;
        color: #fff;
        border-radius: 0.375rem;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.2s ease-in-out;
    }

    .button-primary {
        background-color: #2563eb;
    }

    .button-primary:hover {
        background-color: #1d4ed8;
    }

    .button-danger {
        background-color: #ef4444;
    }

    .button-danger:hover {
        background-color: #dc2626;
    }

    .button-approve {
        background-color: #22c55e;
    }

    .button-approve:hover {
        background-color: #16a34a;
    }

    .button-reject {
        background-color: #ef4444;
    }

    .button-reject:hover {
        background-color: #dc2626;
    }

    .actions-form {
        display: flex;
        gap: 0.5rem;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        font-size: 0.875rem;
        table-layout: fixed;
    }

    .data-table thead tr {
        background-color: #e5e7eb;
    }

    .data-table th,
    .data-table td {
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        text-align: left;
        word-wrap: break-word;
    }

    .data-table th {
        font-weight: 600;
        color: #374151;
    }

    .no-requests-message {
        padding: 1rem;
        background-color: #f1f5f9;
        color: #6b7280;
        text-align: center;
        border-radius: 0.5rem;
    }

    footer {
        background-color: #1f2937;
        color: #d1d6e0;
        text-align: center;
        padding: 2rem 0;
        margin-top: 4rem;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .footer-links {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .footer-links a {
        color: #d1d6e0;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .footer-links a:hover {
        color: #fff;
    }

    .footer-bottom {
        border-top: 1px solid #374151;
        padding-top: 1rem;
        font-size: 0.875rem;
    }

    @media (max-width: 768px) {
        .footer-links {
            gap: 1rem;
        }

        .footer-links a {
            font-size: 0.875rem;
        }
    }
</style>
<div class="dashboard-container">
    <div class="text-center-container">
        <h2 class="title">HOD Dashboard</h2>
        <p class="subtitle">Manage your department's faculty, timetables, and leave requests.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="message-container error-message">
            <h3 class="message-title">Errors:</h3>
            <ul class="message-list">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="message-container success-message">
            <h3 class="message-title">Success:</h3>
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>

    <!-- Main Cards Section -->
    <section class="cards-grid">
        <!-- Timetable Management Card -->
        <a href="timetable_generate.php" class="card-link">
            <div class="card card-blue">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 class="card-title">Timetable Management</h3>
                <p class="card-subtitle">View, edit, and generate timetables.</p>
            </div>
        </a>

        <!-- Subject Management Card -->
        <a href="subject_management.php" class="card-link">
            <div class="card card-blue">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 class="card-title">Subject Management</h3>
                <p class="card-subtitle">View, edit, and manage subjects.</p>
            </div>
        </a>

        <!-- Faculty Management Card -->
        <a href="faculty_management.php" class="card-link">
            <div class="card card-green">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM9.002 10C8.502 10 8 10.5 8 11v4c0 .5.5.999 1.002 1.002l2.996-2.996A.999.999 0 0012 11c0-.5-.5-.999-1.002-1.002z" />
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8 7a7 7 0 100-14 7 7 0 000 14z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 class="card-title">Faculty Management</h3>
                <p class="card-subtitle">Add faculty, view details, and track attendance.</p>
            </div>
        </a>

        <!-- Leave Requests Card -->
        <a href="leave_requests.php" class="card-link">
            <div class="card card-red">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001 1h2a1 1 0 001-1V8a1 1 0 00-1-1h-1.445z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 class="card-title">Leave Requests</h3>
                <p class="card-subtitle">Approve or reject pending leave requests.</p>
            </div>
        </a>

        <!-- Faculty Attendance Card -->
        <a href="faculty_attendance.php" class="card-link">
            <div class="card card-orange">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6 9a1 1 0 112 0 1 1 0 01-2 0zm4 0a1 1 0 112 0 1 1 0 01-2 0zm4 0a1 1 0 112 0 1 1 0 01-2 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 class="card-title">Faculty Attendance</h3>
                <p class="card-subtitle">View and manage faculty attendance records.</p>
            </div>
        </a>
        <a href="faculty_load.php" class="card-link">
            <div class="card card-orange">
                <div class="card-icon">
                    <img src="i../con/dean.png" class="h-8 w-8" alt="Dean icon">
                </div>
                <h3 class="card-title">Faculty Load</h3>
                <p class="card-subtitle">View and manage faculty load records.</p>
            </div>
        </a>
    </section>
</div>
<?php include 'footer.php'; ?>