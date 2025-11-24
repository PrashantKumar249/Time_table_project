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

// Handle leave approval/rejection
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_leave']) || isset($_POST['reject_leave']))) {
    $leave_id = (int)$_POST['leave_id'];
    $status = isset($_POST['approve_leave']) ? 'approved' : 'rejected';
    
    // Optional: For approval, you can add substitute logic here if needed
    $substitute_id = isset($_POST['substitute_faculty_id']) ? (int)$_POST['substitute_faculty_id'] : null;
    $update_query = "UPDATE faculty_leave SET status = '$status', substitute_faculty_id = " . ($substitute_id ? $substitute_id : 'NULL') . " WHERE leave_id = $leave_id AND faculty_id IN (SELECT faculty_id FROM faculty WHERE branch_id = $branch_id)";
    
    if (mysqli_query($conn, $update_query)) {
        $success = "Leave request " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully";
    } else {
        $errors[] = 'Error updating leave request: ' . mysqli_error($conn);
    }
}

// Fetch pending leave requests
$query = "SELECT fl.leave_id, fl.leave_date, fl.reason, fl.status, fl.created_at, f.faculty_name, f.email, f.faculty_id
          FROM faculty_leave fl
          JOIN faculty f ON fl.faculty_id = f.faculty_id
          WHERE f.branch_id = $branch_id AND fl.status = 'pending'
          ORDER BY fl.leave_date ASC, fl.created_at ASC";
$result = mysqli_query($conn, $query);
$leave_requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leave_requests[] = $row;
}

// Fetch available substitutes (other faculty in the same branch)
$substitutes_query = "SELECT faculty_id, faculty_name FROM faculty WHERE branch_id = $branch_id AND faculty_id != (SELECT faculty_id FROM faculty WHERE branch_id = $branch_id LIMIT 1)"; // Exclude self if needed, but adjust
$substitutes_result = mysqli_query($conn, $substitutes_query);
$substitutes = [];
while ($row = mysqli_fetch_assoc($substitutes_result)) {
    $substitutes[] = $row;
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
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    background-color: #fff;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    table-layout: fixed;
}
.data-table thead tr {
    background-color: #f9fafb;
}
.data-table th, .data-table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    word-wrap: break-word;
}
.data-table th {
    font-weight: 600;
    color: #374151;
    background-color: #f3f4f6;
}
.data-table tr:hover {
    background-color: #f9fafb;
}
.data-table tr.even-row {
    background-color: #f8fafc;
}
.reason-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.actions {
    display: flex;
    gap: 0.5rem;
}
.button {
    padding: 0.5rem 1rem;
    color: #fff;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.875rem;
    transition: background-color 0.2s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    min-width: 80px;
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
.substitute-select {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    min-width: 150px;
}
.no-requests {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
    font-size: 1.125rem;
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
    .actions {
        flex-direction: column;
        gap: 0.25rem;
    }
    .button {
        width: 100%;
        min-width: auto;
    }
    .substitute-select {
        width: 100%;
        min-width: auto;
    }
}
</style>
<div class="container">
    <div class="page-header">
        <h1 class="page-title">Leave Requests Management</h1>
        <p class="page-subtitle">Review and manage leave requests from your department's faculty.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="message-container error-message">
            <h3 class="message-title">Errors:</h3>
            <ul>
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

    <div class="section">
        <h2 class="section-title">Pending Leave Requests</h2>
        <?php if (empty($leave_requests)): ?>
            <div class="no-requests">
                <p>No pending leave requests at the moment.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Faculty Name</th>
                        <th>Leave Date</th>
                        <th>Reason</th>
                        <th>Requested On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_requests as $index => $request): ?>
                        <tr class="<?php echo ($index % 2 == 0) ? 'even-row' : ''; ?>">
                            <td><?php echo htmlspecialchars($request['faculty_name']); ?> <br><small><?php echo htmlspecialchars($request['email']); ?></small></td>
                            <td><?php echo date('M d, Y', strtotime($request['leave_date'])); ?></td>
                            <td class="reason-cell" title="<?php echo htmlspecialchars($request['reason']); ?>"><?php echo htmlspecialchars(substr($request['reason'], 0, 50)) . (strlen($request['reason']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                            <td class="actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?php echo $request['leave_id']; ?>">
                                    <select name="substitute_faculty_id" class="substitute-select">
                                        <option value="">No Substitute</option>
                                        <?php foreach ($substitutes as $sub): ?>
                                            <option value="<?php echo $sub['faculty_id']; ?>"><?php echo htmlspecialchars($sub['faculty_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="approve_leave" class="button button-approve">Approve</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?php echo $request['leave_id']; ?>">
                                    <button type="submit" name="reject_leave" class="button button-reject" onclick="return confirm('Are you sure you want to reject this leave request?');">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="hod_dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</div>
<?php include 'footer.php'; ?>