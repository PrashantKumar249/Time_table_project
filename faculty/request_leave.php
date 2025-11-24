<?php
session_start();
include '../include/db.php'; // Assuming config.php has $conn

// Check if user is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: faculty_login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

// Handle leave request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_leave'])) {
    $leave_date = mysqli_real_escape_string($conn, $_POST['leave_date']);
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    if (empty($leave_date) || empty($leave_type) || empty($reason)) {
        $leave_error = 'All fields are required';
    } else {
        // Prefix reason with leave type for clarity
        $full_reason = "Leave Type: " . ucfirst($leave_type) . " | Reason: " . $reason;
        $query = "INSERT INTO faculty_leave (faculty_id, leave_date, reason) VALUES ($faculty_id, '$leave_date', '$full_reason')";
        if (mysqli_query($conn, $query)) {
            $leave_success = 'Leave request submitted successfully';
        } else {
            $leave_error = 'Error submitting leave: ' . mysqli_error($conn);
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
?>
<?php include 'header.php'; ?>
<div class="page-container">
    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1 class="page-title">Request Leave</h1>
            <p class="page-subtitle">Submit a leave request or mark yourself absent for a specific date.</p>
            <a href="faculty_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <!-- Leave Request Form Section -->
    <main class="page-main">
        <section class="leave-request-section">
            <div class="form-container">
                <?php if (isset($leave_error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($leave_error); ?></div>
                <?php endif; ?>
                <?php if (isset($leave_success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($leave_success); ?></div>
                <?php endif; ?>
                <form method="post" class="leave-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leave_date">Select Date</label>
                            <input type="date" id="leave_date" name="leave_date" required class="form-input">
                            <small class="form-help">Choose the date for your leave request</small>
                        </div>
                        <div class="form-group">
                            <label>Leave Type</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="leave_type" value="full" required> Full Day
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="leave_type" value="half"> Half Day
                                </label>
                            </div>
                            <small class="form-help">Select Full Day for entire day absence or Half Day for morning/afternoon</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="reason">Reason</label>
                            <textarea id="reason" name="reason" rows="5" placeholder="Provide a detailed reason for your leave (e.g., medical appointment, family emergency, etc.). For half day, specify morning or afternoon if applicable." required class="form-input"></textarea>
                            <small class="form-help">Be descriptive to help with approval processing</small>
                        </div>
                    </div>
                    <button type="submit" name="request_leave" class="btn-primary full-width">Submit Leave Request</button>
                </form>
            </div>
        </section>
    </main>
</div>

<style>
    :root {
        --primary-color: #3b82f6;
        --secondary-color: #10b981;
        --accent-color: #f59e0b;
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

    .leave-request-section {
        margin-bottom: 2rem;
    }

    .form-container {
        max-width: 600px;
        margin: 0 auto;
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

    .leave-form {
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
        padding: 0.5rem;
        border-radius: 6px;
        transition: background-color 0.2s ease;
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
    }
</style>
<script>
    // Auto-focus on date input
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('leave_date').focus();
    });
</script>
<?php include 'footer.php'; ?>