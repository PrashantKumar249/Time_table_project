<?php
session_start();
include '../include/db.php'; // Assuming config.php has $conn

$faculty_id = $_SESSION['faculty_id'];

// Fetch faculty data for header
$query = "SELECT f.faculty_name, f.email, b.branch_name
          FROM faculty f
          JOIN branches b ON f.branch_id = b.branch_id
          WHERE f.faculty_id = $faculty_id";
$result = mysqli_query($conn, $query);
$faculty_data = mysqli_fetch_assoc($result);
?>
<?php include 'header.php'; ?>
<div class="dashboard-container">
<center><h1>Faculty Dashboard</h1></center>

    <!-- Main Dashboard Grid -->
    <main class="dashboard-main">
        <!-- Assigned Subjects Card -->
        <a href="assigned_subjects.php" class="dashboard-card-link">
            <section class="dashboard-card">
                <div class="card-icon">📚</div>
                <h3 class="card-title">Assigned Subjects</h3>
                <p class="card-description">View all subjects assigned to you for this semester.</p>
                <span class="card-arrow">→</span>
            </section>
        </a>

        <!-- Timetable Card -->
        <a href="faculty_timetable.php" class="dashboard-card-link">
            <section class="dashboard-card">
                <div class="card-icon">🕐</div>
                <h3 class="card-title">Your Timetable</h3>
                <p class="card-description">Check your weekly schedule with classes, times, and sections.</p>
                <span class="card-arrow">→</span>
            </section>
        </a>

        <!-- Request Leave Card -->
        <a href="request_leave.php" class="dashboard-card-link">
            <section class="dashboard-card">
                <div class="card-icon">📅</div>
                <h3 class="card-title">Request Leave / Mark Absent</h3>
                <p class="card-description">Submit a leave request or mark yourself absent for a specific date.</p>
                <span class="card-arrow">→</span>
            </section>
        </a>

        <!-- Track Leave Status Card -->
        <a href="leave_status.php" class="dashboard-card-link">
            <section class="dashboard-card">
                <div class="card-icon">📋</div>
                <h3 class="card-title">Track Leave Status</h3>
                <p class="card-description">Monitor the status of your leave requests and assigned substitutes.</p>
                <span class="card-arrow">→</span>
            </section>
        </a>
        <a href="mark_attendace.php" class="dashboard-card-link">
            <section class="dashboard-card">
                <div class="card-icon">📋</div>
                <h3 class="card-title">Mark Attendance</h3>
                <p class="card-description">Record attendance for your classes and manage student records.</p>
                <span class="card-arrow">→</span>
            </section>
        </a>
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

    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
        font-family: 'Inter', sans-serif;
    }

    .dashboard-header {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        text-align: center;
        border: 1px solid var(--border-color);
    }

    .dashboard-welcome {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.025em;
    }

    .dashboard-info {
        color: var(--text-secondary);
        font-size: 1rem;
        margin: 0;
    }

    .info-item {
        display: inline;
    }

    .info-separator {
        margin: 0 0.5rem;
        color: var(--text-secondary);
    }

    .dashboard-main {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .dashboard-card-link {
        text-decoration: none;
        color: inherit;
    }

    .dashboard-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .dashboard-card:hover::before {
        transform: scaleX(1);
    }

    .dashboard-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
    }

    .card-icon {
        font-size: 3.5rem;
        margin-bottom: 1rem;
        opacity: 0.8;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .dashboard-card:hover .card-icon {
        opacity: 1;
        transform: scale(1.1);
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.75rem 0;
    }

    .card-description {
        font-size: 0.95rem;
        color: var(--text-secondary);
        margin: 0 0 1rem 0;
        line-height: 1.5;
    }

    .card-arrow {
        font-size: 1.5rem;
        color: var(--primary-color);
        transition: transform 0.3s ease;
    }

    .dashboard-card:hover .card-arrow {
        transform: translateX(5px);
    }

    .dashboard-footer {
        text-align: center;
        margin-top: 2rem;
        padding: 1rem;
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 0.875rem 2rem;
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
        background: #4b5563;
        transform: translateY(-1px);
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0.5rem;
        }

        .dashboard-main {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .dashboard-header {
            padding: 1.5rem;
        }

        .dashboard-welcome {
            font-size: 1.5rem;
        }

        .card-icon {
            font-size: 2.5rem;
        }

        .card-title {
            font-size: 1.125rem;
        }

        .card-description {
            font-size: 0.875rem;
        }
    }
</style>
<?php include 'footer.php'; ?>