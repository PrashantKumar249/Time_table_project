<?php
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
// Fetch subjects for the branch for dropdown
$subjects_query = "SELECT subject_id, CONCAT(subject_code, ' - ', subject_name, ' (Year ', year, ', Sem ', semester, ')') AS display_name
                   FROM subjects WHERE branch_id = $branch_id ORDER BY year, semester, subject_code";
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects_options = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $subjects_options[] = $row;
}
// Handle form submissions
$errors = [];
$success = '';
// Add Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    $faculty_name = $username; // Set faculty_name to username since field is removed
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
   
    if (empty($username) || empty($password) || empty($email)) {
        $errors[] = 'All basic fields are required';
    } else {
        // Check if username or email exists
        $check_query = "SELECT user_id FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Username or email already exists';
        } else {
            // Insert user
            $query = "INSERT INTO users (username, password, role, email) VALUES ('$username', '$password', 'faculty', '$email')";
            if (mysqli_query($conn, $query)) {
                $user_id = mysqli_insert_id($conn);
               
                // Insert faculty
                $query = "INSERT INTO faculty (user_id, branch_id, faculty_name, email, created_by, password)
                          VALUES ($user_id, $branch_id, '$faculty_name', '$email', " . (int)$_SESSION['user_id'] . ", '$password')";
                if (mysqli_query($conn, $query)) {
                    $faculty_id = mysqli_insert_id($conn);
                   
                    // Handle subjects
                    $subjects_added = 0;
                    if (isset($_POST['subject_id']) && is_array($_POST['subject_id'])) {
                        foreach ($_POST['subject_id'] as $subject_id) {
                            $subject_id = (int)$subject_id;
                            if ($subject_id > 0) {
                                // Verify subject belongs to branch
                                $verify_query = "SELECT subject_id FROM subjects WHERE subject_id = $subject_id AND branch_id = $branch_id";
                                $verify_result = mysqli_query($conn, $verify_query);
                                if (mysqli_num_rows($verify_result) > 0) {
                                    $insert_query = "INSERT INTO faculty_subjects (faculty_id, subject_id) VALUES ($faculty_id, $subject_id)";
                                    if (mysqli_query($conn, $insert_query)) {
                                        $subjects_added++;
                                    }
                                }
                            }
                        }
                    }
                    if ($subjects_added > 0) {
                        $success = "Faculty '$faculty_name' added successfully with $subjects_added subjects.";
                    } else {
                        $success = "Faculty '$faculty_name' added successfully. No subjects assigned.";
                    }
                } else {
                    $errors[] = 'Error adding faculty: ' . mysqli_error($conn);
                    // Rollback user if faculty insert fails
                    $delete_user = "DELETE FROM users WHERE user_id = $user_id";
                    mysqli_query($conn, $delete_user);
                }
            } else {
                $errors[] = 'Error creating user: ' . mysqli_error($conn);
            }
        }
    }
}
// Delete Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faculty'])) {
    $faculty_id = (int)$_POST['faculty_id'];
    // First, delete related records
    $delete_fs = "DELETE FROM faculty_subjects WHERE faculty_id = $faculty_id";
    mysqli_query($conn, $delete_fs);
    $delete_fa = "DELETE FROM faculty_attendance WHERE faculty_id = $faculty_id";
    mysqli_query($conn, $delete_fa);
    $delete_fl = "DELETE FROM faculty_leave WHERE faculty_id = $faculty_id";
    mysqli_query($conn, $delete_fl);
    // Get user_id from faculty
    $get_user = "SELECT user_id FROM faculty WHERE faculty_id = $faculty_id";
    $user_result = mysqli_query($conn, $get_user);
    if ($user_result && $user_row = mysqli_fetch_assoc($user_result)) {
        $user_id = $user_row['user_id'];
        // Delete faculty
        $delete_faculty = "DELETE FROM faculty WHERE faculty_id = $faculty_id AND branch_id = $branch_id";
        if (mysqli_query($conn, $delete_faculty)) {
            // Delete user
            $delete_user = "DELETE FROM users WHERE user_id = $user_id";
            mysqli_query($conn, $delete_user);
            $success = 'Faculty deleted successfully.';
        } else {
            $errors[] = 'Error deleting faculty: ' . mysqli_error($conn);
        }
    } else {
        $errors[] = 'Faculty not found.';
    }
}
// Fetch faculty list with pagination
$per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

// Total count
$total_query = "SELECT COUNT(*) as total FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE f.branch_id = $branch_id";
$total_result = mysqli_query($conn, $total_query);
$total_records = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_records / $per_page);

$faculty_query = "
    SELECT f.faculty_id, f.faculty_name, u.username, u.email, f.created_by
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.branch_id = $branch_id
    ORDER BY f.faculty_name
    LIMIT $offset, $per_page
";
$faculty_result = mysqli_query($conn, $faculty_query);
$faculty_list_raw = [];
while ($row = mysqli_fetch_assoc($faculty_result)) {
    $faculty_list_raw[] = $row;
}
// Build faculty list with subjects
$faculty_list = [];
foreach ($faculty_list_raw as $fac) {
    $fac_id = $fac['faculty_id'];
    $subjects_query = "
        SELECT s.subject_code
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.subject_id
        WHERE fs.faculty_id = $fac_id
        ORDER BY s.subject_code
    ";
    $subjects_result = mysqli_query($conn, $subjects_query);
    $subjects = [];
    while ($sub_row = mysqli_fetch_assoc($subjects_result)) {
        $subjects[] = $sub_row['subject_code'];
    }
    $fac['subjects'] = $subjects;
    $faculty_list[$fac['faculty_name']] = $fac;
}
?>
<div class="container">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <!-- Add Faculty Form -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-user-plus"></i> Add New Faculty</h2>
        </div>
        <div class="card-body">
            <form method="post" id="addFacultyForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" required class="input-field">
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" required class="input-field">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" required class="input-field">
                    </div>
                </div>
               
                <!-- Subjects Section -->
                <div class="form-group" style="grid-column: span 2;">
                    <label><i class="fas fa-book"></i> Subjects (Select from existing subjects)</label>
                    <div id="subjects-container">
                        <div class="subject-section" id="subject-0">
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                                <div class="form-group">
                                    <label>Subject</label>
                                    <select name="subject_id[]" required class="input-field">
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects_options as $option): ?>
                                            <option value="<?php echo $option['subject_id']; ?>"><?php echo htmlspecialchars($option['display_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addSubject()" class="add-subject-btn" style="margin-top: 0.5rem;"><i class="fas fa-plus"></i> Add Another Subject</button>
                </div>
               
                <div class="form-group" style="grid-column: span 2; text-align: center;">
                    <button type="submit" name="add_faculty" class="button button-primary"><i class="fas fa-save"></i> Add Faculty</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Faculty List -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-users"></i> Faculty Members</h2>
        </div>
        <div class="card-body">
            <?php if (empty($faculty_list)): ?>
                <p style="text-align: center; color: #6b7280; padding: 2rem;">No faculty members found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Subjects Taught</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faculty_list as $name => $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td><?php echo htmlspecialchars($data['username']); ?></td>
                                    <td><?php echo htmlspecialchars($data['email']); ?></td>
                                    <td>
                                        <?php if (!empty($data['subjects'])): ?>
                                            <?php echo htmlspecialchars(implode(', ', array_unique($data['subjects']))); ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="edit_faculty.php?id=<?php echo $data['faculty_id']; ?>" class="button button-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;"><i class="fas fa-edit"></i> Edit</a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this faculty?');">
                                            <input type="hidden" name="faculty_id" value="<?php echo $data['faculty_id']; ?>">
                                            <button type="submit" name="delete_faculty" class="button button-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="pagination-link">&laquo; Previous</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="pagination-current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>" class="pagination-link"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="pagination-link">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div style="text-align: center; margin-top: 2rem;">
        <a href="hod_dashboard.php" class="button button-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>
<script>
function addSubject() {
    const container = document.getElementById('subjects-container');
    const newSection = document.createElement('div');
    newSection.className = 'subject-section';
    newSection.id = 'subject-' + Date.now(); // Unique ID
    newSection.innerHTML = `
        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label>Subject</label>
                <select name="subject_id[]" required class="input-field">
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects_options as $option): ?>
                        <option value="<?php echo $option['subject_id']; ?>"><?php echo htmlspecialchars($option['display_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="button" onclick="removeSubject('${newSection.id}')" class="remove-subject-btn" style="margin-top: 0.5rem; background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; cursor: pointer;"><i class="fas fa-minus"></i> Remove</button>
    `;
    container.appendChild(newSection);
}
function removeSubject(sectionId) {
    const section = document.getElementById(sectionId);
    if (section && document.querySelectorAll('.subject-section').length > 1) {
        section.remove();
    }
}
</script>
<style>
body {
    background: white;
    min-height: 100vh;
    padding: 0;
    margin: 0;
}
.container { 
    max-width: 1200px; 
    margin: 0 auto; 
    padding: 1rem; 
    width: 100%;
    box-sizing: border-box;
}
.card { 
    background: white; 
    border-radius: 8px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    margin-bottom: 2rem; 
    overflow: hidden; 
    border: 1px solid #e2e8f0;
}
.card-header { 
    background: #f8f9fa; 
    padding: 1rem; 
    border-bottom: 1px solid #dee2e6; 
    color: #333;
}
.card-header h2 { 
    margin: 0; 
    font-size: 1.25rem; 
    display: flex; 
    align-items: center; 
}
.card-header h2 i {
    margin-right: 0.5rem;
    color: #3b82f6;
}
.card-body { 
    padding: 1.5rem; 
}
.form-grid { 
    display: grid; 
    gap: 1rem; 
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}
.form-group { 
    display: flex; 
    flex-direction: column; 
}
.form-group label { 
    font-weight: 500; 
    margin-bottom: 0.5rem; 
    color: #555; 
    display: flex;
    align-items: center;
}
.form-group label i {
    margin-right: 0.5rem;
    color: #3b82f6;
}
.input-field { 
    padding: 0.5rem; 
    border: 1px solid #ddd; 
    border-radius: 4px; 
    font-size: 0.9rem; 
    transition: border-color 0.3s ease;
}
.input-field:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}
.subject-section { 
    margin-bottom: 1rem; 
    padding: 1rem; 
    border: 1px solid #e2e8f0; 
    border-radius: 4px; 
    background: #f8fafc;
    transition: border-color 0.3s ease;
}
.subject-section:hover {
    border-color: #3b82f6;
}
.add-subject-btn { 
    background: #10b981; 
    color: white; 
    border: none; 
    padding: 0.25rem 0.5rem; 
    border-radius: 4px; 
    cursor: pointer; 
    font-weight: 500;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
}
.add-subject-btn:hover { 
    background: #059669;
}
.add-subject-btn i {
    margin-right: 0.125rem;
}
.remove-subject-btn { 
    background: #ef4444; 
    color: white; 
    border: none; 
    padding: 0.25rem 0.5rem; 
    border-radius: 4px; 
    cursor: pointer; 
    font-size: 0.75rem;
    display: flex;
    align-items: center;
}
.remove-subject-btn:hover { 
    background: #dc2626 !important; 
}
.table-responsive { 
    overflow-x: auto; 
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.data-table { 
    width: 100%; 
    border-collapse: collapse; 
}
.data-table th, .data-table td { 
    padding: 0.75rem; 
    text-align: left; 
    border-bottom: 1px solid #e2e8f0; 
}
.data-table th { 
    background: #f8f9fa; 
    font-weight: 600; 
    color: #1e293b;
}
.actions { 
    white-space: nowrap; 
}
.button { 
    display: inline-block; 
    padding: 0.25rem 0.5rem; 
    border-radius: 4px; 
    text-decoration: none; 
    font-size: 0.75rem; 
    cursor: pointer; 
    border: none; 
    margin: 0 0.125rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}
.button i {
    margin-right: 0.125rem;
}
.button-primary { 
    background: #3b82f6; 
    color: white; 
}
.button-primary:hover { 
    background: #2563eb;
}
.button-danger { 
    background: #ef4444; 
    color: white; 
}
.button-danger:hover { 
    background: #dc2626;
}
.alert { 
    padding: 0.75rem; 
    margin-bottom: 1rem; 
    border-radius: 4px; 
    font-weight: 500;
}
.alert-success { 
    background: #d1fae5; 
    color: #065f46; 
    border: 1px solid #a7f3d0; 
}
.alert-danger { 
    background: #fee2e2; 
    color: #991b1b; 
    border: 1px solid #fecaca; 
}
.pagination {
    text-align: center;
    margin-top: 1rem;
    padding: 1rem 0;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    margin: 0 0.125rem;
    border: 1px solid #ddd;
    text-decoration: none;
    border-radius: 4px;
    color: #333;
    font-size: 0.85rem;
}
.pagination a:hover {
    background: #f8f9fa;
}
.pagination .pagination-current {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}
@media (max-width: 768px) {
    .container { padding: 0.5rem; }
    .form-grid { grid-template-columns: 1fr; }
    .card-body { padding: 1rem; }
    .data-table th, .data-table td { padding: 0.5rem; font-size: 0.85rem; }
    .button { padding: 0.2rem 0.4rem; font-size: 0.7rem; }
    .pagination a, .pagination span { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
}
</style>
<?php include 'footer.php'; ?>