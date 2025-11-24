<?php
session_start();
include '../include/db.php'; // Assuming config.php has $conn

// Check if user is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: faculty_login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

// Fetch leave status with substitute name
$leave_query = "SELECT fl.leave_id, fl.leave_date, fl.reason, fl.status, fl.created_at, fl.substitute_faculty_id,
                       sf.faculty_name AS substitute_name
                FROM faculty_leave fl
                LEFT JOIN faculty sf ON fl.substitute_faculty_id = sf.faculty_id
                WHERE fl.faculty_id = $faculty_id
                ORDER BY fl.created_at DESC";
$leave_result = mysqli_query($conn, $leave_query);
$leaves = [];
while ($row = mysqli_fetch_assoc($leave_result)) {
    $leaves[] = $row;
}

// Fetch faculty data for header if needed
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
            <h1 class="page-title">Track Leave Status</h1>
            <p class="page-subtitle">Monitor the status of your leave requests and assigned substitutes.</p>
            <a href="faculty_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <!-- Leave Status Section -->
    <main class="page-main">
        <section class="leave-status-section">
            <?php if (empty($leaves)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <p class="empty-text">No leave requests submitted yet. Submit one from the dashboard.</p>
                    <a href="request_leave.php" class="btn-secondary">Submit Leave Request</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="leave-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Substitute Teacher</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaves as $index => $leave): ?>
                                <tr class="<?php echo ($index % 2 == 0) ? 'table-row-alt' : ''; ?>">
                                    <td class="table-cell-date"><?php echo date('M d, Y', strtotime($leave['leave_date'])); ?></td>
                                    <td title="<?php echo htmlspecialchars($leave['reason']); ?>"><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
                                    <td><span class="status-badge status-<?php echo $leave['status']; ?>"><?php echo ucfirst($leave['status']); ?></span></td>
                                    <td><?php echo $leave['substitute_name'] ? htmlspecialchars($leave['substitute_name']) : 'None Assigned'; ?></td>
                                    <td class="table-cell-time"><?php echo date('M j, Y g:i A', strtotime($leave['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="leave-summary">
                    <p><strong>Total Requests:</strong> <?php echo count($leaves); ?> | <strong>Pending:</strong> <?php echo count(array_filter($leaves, function($l) { return $l['status'] === 'pending'; })); ?></p>
                </div>
            <?php endif; ?>
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

    .leave-status-section {
        margin-bottom: 2rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary);
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-text {
        font-size: 1rem;
        margin: 0;
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        margin-bottom: 1rem;
    }

    .leave-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .leave-table th,
    .leave-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .leave-table th {
        background: #f8fafc;
        font-weight: 600;
        color: var(--text-primary);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .leave-table tr:hover {
        background: #f8fafc;
    }

    .table-row-alt {
        background: #fafbfc;
    }

    .table-cell-date {
        font-weight: 500;
        width: 15%;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        width: 15%;
    }

    .status-pending {
        background: #fef3c7;
        color: #d97706;
    }

    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .leave-summary {
        text-align: center;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-size: 1rem;
    }

    .btn-secondary {
        background: var(--secondary-color);
        color: white;
        border: none;
        padding: 0.875rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .btn-secondary:hover {
        background: #059669;
        transform: translateY(-1px);
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

        .leave-table th,
        .leave-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.75rem;
        }

        .status-badge {
            width: auto;
            padding: 0.375rem 0.75rem;
        }
    }
</style>
<script>
    // No JS needed for this page
</script>
<?php include 'footer.php'; ?>