<?php
session_start();
include '../include/db.php'; // Assuming config.php has $conn

// Check if user is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: faculty_login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

// Fetch timetable slots for the faculty
$timetable_query = "SELECT ts.day_of_week, ts.start_time, ts.end_time, sec.section_name, sec.year, sec.semester, sub.subject_name
                    FROM timetable_slots ts
                    JOIN sections sec ON ts.section_id = sec.section_id
                    JOIN subjects sub ON ts.subject_id = sub.subject_id
                    WHERE ts.faculty_id = $faculty_id
                    ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), ts.start_time";
$timetable_result = mysqli_query($conn, $timetable_query);
$timetable_slots = [];
while ($row = mysqli_fetch_assoc($timetable_result)) {
    $timetable_slots[] = $row;
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
            <h1 class="page-title">Your Timetable</h1>
            <p class="page-subtitle">View your weekly schedule with classes, times, and sections.</p>
            <a href="faculty_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <!-- Timetable Section -->
    <main class="page-main">
        <section class="timetable-section">
            <?php if (empty($timetable_slots)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🕐</div>
                    <p class="empty-text">No timetable slots scheduled yet. Check with your HOD for updates.</p>
                    <a href="faculty_dashboard.php" class="btn-secondary">Return to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="timetable-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time Slot</th>
                                <th>Year/Semester</th>
                                <th>Section</th>
                                <th>Subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable_slots as $index => $slot): ?>
                                <tr class="<?php echo ($index % 2 == 0) ? 'table-row-alt' : ''; ?>">
                                    <td class="table-cell-day"><?php echo ucfirst($slot['day_of_week']); ?></td>
                                    <td class="table-cell-time"><?php echo date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])); ?></td>
                                    <td class="table-cell-year"><?php echo $slot['year'] . '/' . $slot['semester']; ?></td>
                                    <td class="table-cell-section"><?php echo htmlspecialchars($slot['section_name']); ?></td>
                                    <td class="table-cell-subject"><?php echo htmlspecialchars($slot['subject_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="timetable-summary">
                    <p><strong>Total Lectures:</strong> <?php echo count($timetable_slots); ?> | <strong>Unique Subjects:</strong> <?php echo count(array_unique(array_column($timetable_slots, 'subject_name'))); ?></p>
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

    .timetable-section {
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

    .timetable-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .timetable-table th,
    .timetable-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .timetable-table th {
        background: #f8fafc;
        font-weight: 600;
        color: var(--text-primary);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .timetable-table tr:hover {
        background: #f8fafc;
    }

    .table-row-alt {
        background: #fafbfc;
    }

    .table-cell-day {
        font-weight: 600;
        color: var(--primary-color);
        width: 12%;
    }

    .table-cell-time {
        font-weight: 500;
        width: 20%;
    }

    .table-cell-year {
        font-weight: 500;
        width: 15%;
    }

    .table-cell-section {
        font-weight: 500;
        width: 15%;
    }

    .table-cell-subject {
        font-family: 'Monaco', monospace;
        font-weight: 600;
        color: var(--secondary-color);
        width: 38%;
    }

    .timetable-summary {
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

        .timetable-table th,
        .timetable-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.75rem;
        }

        .table-cell-time {
            font-size: 0.8rem;
        }
    }
</style>
<script>
    // Optional: Add print functionality for timetable
    function printTimetable() {
        window.print();
    }
</script>
<?php include 'footer.php'; ?>