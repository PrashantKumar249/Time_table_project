<?php
session_start();
include '../include/db.php'; // Assuming config.php has $conn

// Check if user is faculty (optional, since header might handle it)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: faculty_login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

// Fetch faculty data for header if needed
$query = "SELECT f.faculty_name, f.email, b.branch_name
          FROM faculty f
          JOIN branches b ON f.branch_id = b.branch_id
          WHERE f.faculty_id = $faculty_id";
$result = mysqli_query($conn, $query);
$faculty_data = mysqli_fetch_assoc($result);

// Fetch assigned subjects
$subjects_query = "SELECT s.subject_id, s.subject_code, s.subject_name, s.weekly_hours
                   FROM subjects s
                   JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
                   WHERE fs.faculty_id = $faculty_id
                   ORDER BY s.subject_code";
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $row;
}
?>
<?php include 'header.php'; ?>
<div class="page-container">
    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1 class="page-title">Assigned Subjects</h1>
            <p class="page-subtitle">View all subjects assigned to you for the current semester.</p>
            <a href="faculty_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <!-- Subjects Section -->
    <main class="page-main">
        <section class="subjects-section">
            <?php if (empty($subjects)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <p class="empty-text">No subjects assigned yet. Contact your HOD to assign subjects.</p>
                    <a href="faculty_dashboard.php" class="btn-secondary">Return to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="subjects-table">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Weekly Hours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $index => $subject): ?>
                                <tr class="<?php echo ($index % 2 == 0) ? 'table-row-alt' : ''; ?>">
                                    <td class="table-cell-code"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td class="table-cell-hours"><?php echo $subject['weekly_hours']; ?> hrs</td>
                                    <td>
                                        <button class="btn-edit" onclick="editSubject(<?php echo $subject['subject_id']; ?>)">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="subjects-summary">
                    <p><strong>Total Subjects:</strong> <?php echo count($subjects); ?> | <strong>Total Weekly Hours:</strong> <?php echo array_sum(array_column($subjects, 'weekly_hours')); ?> hrs</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Edit Subject Modal (Hidden by default) -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Subject</h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <form id="editSubjectForm" method="post" action="edit_subject.php">
            <input type="hidden" id="edit_subject_id" name="subject_id">
            <div class="form-group">
                <label for="edit_subject_code">Subject Code</label>
                <input type="text" id="edit_subject_code" name="subject_code" required class="form-input">
            </div>
            <div class="form-group">
                <label for="edit_subject_name">Subject Name</label>
                <input type="text" id="edit_subject_name" name="subject_name" required class="form-input">
            </div>
            <div class="form-group">
                <label for="edit_weekly_hours">Weekly Hours</label>
                <input type="number" id="edit_weekly_hours" name="weekly_hours" min="1" max="10" required class="form-input">
            </div>
            <button type="submit" class="btn-primary">Update Subject</button>
        </form>
    </div>
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

    .subjects-section {
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

    .subjects-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .subjects-table th,
    .subjects-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .subjects-table th {
        background: #f8fafc;
        font-weight: 600;
        color: var(--text-primary);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .subjects-table tr:hover {
        background: #f8fafc;
    }

    .table-row-alt {
        background: #fafbfc;
    }

    .table-cell-code {
        font-family: 'Monaco', monospace;
        font-weight: 600;
        color: var(--primary-color);
        width: 20%;
    }

    .subjects-summary {
        text-align: center;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
    }

    .btn-edit {
        background: var(--secondary-color);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s ease;
    }

    .btn-edit:hover {
        background: #059669;
    }

    /* Modal Styles */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 2rem;
        width: 90%;
        max-width: 500px;
        box-shadow: var(--shadow-lg);
        position: relative;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 1rem;
    }

    .modal-header h3 {
        margin: 0;
        color: var(--text-primary);
        font-size: 1.25rem;
        font-weight: 600;
    }

    .close-modal {
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-secondary);
        background: none;
        border: none;
        transition: color 0.2s ease;
    }

    .close-modal:hover {
        color: var(--text-primary);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
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
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

        .subjects-table th,
        .subjects-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.75rem;
        }
    }
</style>
<script>
    function editSubject(subjectId) {
        // Fetch subject details via AJAX or populate from data attributes
        // For simplicity, assume we populate the modal
        document.getElementById('edit_subject_id').value = subjectId;
        // Here, you would fetch and populate the fields
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>
<?php include 'footer.php'; ?>