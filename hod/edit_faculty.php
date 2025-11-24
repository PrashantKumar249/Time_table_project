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

// Get faculty_id from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: faculty_management.php');
    exit;
}
$faculty_id = (int)$_GET['id'];

// Fetch existing faculty data
$faculty_query = "SELECT f.faculty_id, u.user_id, f.faculty_name, f.email, u.username, u.password
                  FROM faculty f
                  JOIN users u ON f.user_id = u.user_id
                  WHERE f.faculty_id = $faculty_id AND f.branch_id = $branch_id";
$faculty_result = mysqli_query($conn, $faculty_query);
if (!$faculty_result || mysqli_num_rows($faculty_result) == 0) {
    header('Location: faculty_management.php');
    exit;
}
$faculty_data = mysqli_fetch_assoc($faculty_result);

// Fetch existing subjects for this faculty
$subjects_query = "SELECT s.subject_code, s.subject_name, s.weekly_hours
                   FROM subjects s
                   JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
                   WHERE fs.faculty_id = $faculty_id";
$subjects_result = mysqli_query($conn, $subjects_query);
$existing_subjects = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $existing_subjects[] = $row;
}

// Handle form submissions
$errors = [];
$success = '';

// Update Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_faculty'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $new_password = !empty($_POST['password']) ? mysqli_real_escape_string($conn, $_POST['password']) : $faculty_data['password'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    if (empty($username) || empty($email)) {
        $errors[] = 'Username and email are required';
    } else {
        // Check if username or email exists for other users
        $check_query = "SELECT user_id FROM users WHERE (username = '$username' OR email = '$email') AND user_id != " . $faculty_data['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Username or email already exists for another user';
        } else {
            // Update user
            $password_update = !empty($_POST['password']) ? ", password = '$new_password'" : '';
            $user_query = "UPDATE users SET username = '$username'$password_update WHERE user_id = " . $faculty_data['user_id'];
            if (mysqli_query($conn, $user_query)) {
                // Update faculty
                $faculty_name = $username; // Set faculty_name to username
                $faculty_update = "UPDATE faculty SET faculty_name = '$faculty_name', email = '$email' WHERE faculty_id = $faculty_id";
                if (mysqli_query($conn, $faculty_update)) {
                    // Handle subjects - first delete existing links
                    mysqli_query($conn, "DELETE FROM faculty_subjects WHERE faculty_id = $faculty_id");
                    
                    // Add new subjects
                    $subjects_added = 0;
                    if (isset($_POST['subject_code']) && is_array($_POST['subject_code'])) {
                        foreach ($_POST['subject_code'] as $index => $subject_code) {
                            $subject_code = mysqli_real_escape_string($conn, trim($subject_code));
                            $subject_name = mysqli_real_escape_string($conn, trim($_POST['subject_name'][$index]));
                            $weekly_hours = (int)$_POST['weekly_hours'][$index];
                            
                            if (empty($subject_code) || empty($subject_name) || $weekly_hours <= 0) {
                                continue; // Skip invalid subjects
                            }
                            
                            // Check if subject exists
                            $check_sub_query = "SELECT subject_id FROM subjects WHERE subject_code = '$subject_code'";
                            $check_sub_result = mysqli_query($conn, $check_sub_query);
                            if (mysqli_num_rows($check_sub_result) == 0) {
                                // Insert new subject
                                $sub_insert = "INSERT INTO subjects (branch_id, subject_code, subject_name, weekly_hours) 
                                               VALUES ($branch_id, '$subject_code', '$subject_name', $weekly_hours)";
                                if (mysqli_query($conn, $sub_insert)) {
                                    $subject_id = mysqli_insert_id($conn);
                                } else {
                                    $errors[] = 'Error creating subject ' . $subject_code . ': ' . mysqli_error($conn);
                                    continue;
                                }
                            } else {
                                $sub_row = mysqli_fetch_assoc($check_sub_result);
                                $subject_id = $sub_row['subject_id'];
                            }
                            
                            // Link to faculty
                            $link_query = "INSERT IGNORE INTO faculty_subjects (faculty_id, subject_id) VALUES ($faculty_id, $subject_id)";
                            if (mysqli_query($conn, $link_query)) {
                                $subjects_added++;
                            } else {
                                $errors[] = 'Error linking subject ' . $subject_code . ' to faculty';
                            }
                        }
                    }
                    
                    $success = 'Faculty updated successfully' . ($subjects_added > 0 ? ' with ' . $subjects_added . ' subject(s)' : '');
                } else {
                    $errors[] = 'Error updating faculty: ' . mysqli_error($conn);
                }
            } else {
                $errors[] = 'Error updating user: ' . mysqli_error($conn);
            }
        }
    }
}

// Delete Faculty (if needed, but since it's edit, maybe not, but keep for consistency)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faculty'])) {
    $query = "SELECT user_id FROM faculty WHERE faculty_id = $faculty_id AND branch_id = $branch_id";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $user_id = $row['user_id'];
        // Delete from faculty_subjects first
        mysqli_query($conn, "DELETE FROM faculty_subjects WHERE faculty_id = $faculty_id");
        // Delete from faculty
        if (mysqli_query($conn, "DELETE FROM faculty WHERE faculty_id = $faculty_id")) {
            // Delete from users
            mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");
            header('Location: faculty_management.php');
            exit;
        } else {
            $errors[] = 'Error deleting faculty: ' . mysqli_error($conn);
        }
    } else {
        $errors[] = 'Faculty not found or not in your branch';
    }
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
.title {
    font-size: 2.25rem;
    font-weight: 700;
    color: #1f2937;
    text-align: center;
    margin-bottom: 2rem;
}
.message-container {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
    opacity: 0;
}
.message-container.show {
    max-height: 200px;
    opacity: 1;
    transition: max-height 0.3s ease-in, opacity 0.3s ease-in;
}
.message-container.error-message {
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
.close-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}
.close-btn:hover {
    opacity: 1;
}
.form-card {
    background-color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.form-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-grid > *:last-child {
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
    transition: border-color 0.2s ease;
}
.input-field:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.subject-section {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: #f9fafb;
}
.subject-section h4 {
    margin-top: 0;
    color: #374151;
}
.remove-subject {
    background-color: #ef4444;
    color: #fff;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    cursor: pointer;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}
.remove-subject:hover {
    background-color: #dc2626;
}
.add-subject-btn {
    background-color: #10b981;
    color: #fff;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    cursor: pointer;
    font-weight: 600;
}
.add-subject-btn:hover {
    background-color: #059669;
}
.button {
    padding: 0.75rem 1.5rem;
    color: #fff;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: background-color 0.2s ease;
}
.button-primary {
    background-color: #3b82f6;
}
.button-primary:hover {
    background-color: #2563eb;
}
.button-danger {
    background-color: #ef4444;
}
.button-danger:hover {
    background-color: #dc2626;
}
@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    .form-grid {
        grid-template-columns: 1fr;
    }
    .subject-section {
        padding: 0.75rem;
    }
}
</style>
<script>
let subjectCount = <?php echo count($existing_subjects) + 1; ?>;

function addSubject() {
    const container = document.getElementById('subjects-container');
    const newSection = document.createElement('div');
    newSection.className = 'subject-section';
    newSection.id = 'subject-' + subjectCount;
    newSection.innerHTML = `
        <h4>Subject ${subjectCount}</h4>
        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label>Subject Code</label>
                <input type="text" name="subject_code[]" required class="input-field">
            </div>
            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="subject_name[]" required class="input-field">
            </div>
            <div class="form-group">
                <label>Weekly Hours</label>
                <input type="number" name="weekly_hours[]" min="1" max="10" required class="input-field">
            </div>
            <div class="form-group" style="grid-column: span 3; margin-top: 1rem;">
                <button type="button" onclick="removeSubject('subject-${subjectCount}')" class="remove-subject">Remove Subject</button>
            </div>
        </div>
    `;
    container.appendChild(newSection);
    subjectCount++;
}

function removeSubject(id) {
    const element = document.getElementById(id);
    if (element) {
        element.remove();
    }
}

// Toggle success message
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.querySelector('.success-message');
    if (successMsg) {
        successMsg.classList.add('show');
        const closeBtn = document.createElement('button');
        closeBtn.className = 'close-btn';
        closeBtn.innerHTML = '&times;';
        closeBtn.onclick = function() {
            successMsg.classList.remove('show');
        };
        successMsg.appendChild(closeBtn);
    }
});
</script>
<div class="container">
    <h1 class="title">Edit Faculty: <?php echo htmlspecialchars($faculty_data['faculty_name'] ?? 'Unknown'); ?></h1>

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
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>

    <!-- Edit Faculty Form -->
    <div class="form-card">
        <h2 class="form-title">Edit Faculty Details</h2>
        <form method="post" class="form-grid">
            <div class="form-group">
                <label for="username">Username (will be used as Faculty Name)</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($faculty_data['username']); ?>" required class="input-field">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($faculty_data['email']); ?>" required class="input-field">
            </div>
            <div class="form-group">
                <label for="password">New Password (leave blank to keep current)</label>
                <input type="password" id="password" name="password" class="input-field">
            </div>
            
            <!-- Existing Subjects -->
            <?php if (!empty($existing_subjects)): ?>
                <?php foreach ($existing_subjects as $index => $sub): ?>
                    <div class="subject-section" id="subject-<?php echo $index; ?>">
                        <h4>Subject <?php echo $index + 1; ?></h4>
                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                            <div class="form-group">
                                <label>Subject Code</label>
                                <input type="text" name="subject_code[]" value="<?php echo htmlspecialchars($sub['subject_code']); ?>" required class="input-field">
                            </div>
                            <div class="form-group">
                                <label>Subject Name</label>
                                <input type="text" name="subject_name[]" value="<?php echo htmlspecialchars($sub['subject_name']); ?>" required class="input-field">
                            </div>
                            <div class="form-group">
                                <label>Weekly Hours</label>
                                <input type="number" name="weekly_hours[]" min="1" max="10" value="<?php echo $sub['weekly_hours']; ?>" required class="input-field">
                            </div>
                            <div class="form-group" style="grid-column: span 3; margin-top: 1rem;">
                                <button type="button" onclick="removeSubject('subject-<?php echo $index; ?>')" class="remove-subject">Remove Subject</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Subjects Section -->
            <div class="form-group" style="grid-column: span 2;">
                <label>Subjects (One faculty can teach multiple subjects)</label>
                <div id="subjects-container">
                    <?php if (empty($existing_subjects)): ?>
                        <div class="subject-section" id="subject-0">
                            <h4>Subject 1</h4>
                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                                <div class="form-group">
                                    <label>Subject Code</label>
                                    <input type="text" name="subject_code[]" required class="input-field">
                                </div>
                                <div class="form-group">
                                    <label>Subject Name</label>
                                    <input type="text" name="subject_name[]" required class="input-field">
                                </div>
                                <div class="form-group">
                                    <label>Weekly Hours</label>
                                    <input type="number" name="weekly_hours[]" min="1" max="10" required class="input-field">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addSubject()" class="add-subject-btn" style="margin-top: 0.5rem;">Add Another Subject</button>
            </div>
            
            <div class="form-group" style="grid-column: span 2;">
                <button type="submit" name="update_faculty" class="button button-primary">Update Faculty</button>
                <a href="faculty_management.php" class="button" style="background-color: #6b7280; margin-left: 1rem;">Cancel</a>
            </div>
        </form>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="faculty_management.php" class="button button-primary">Back to Faculty Management</a>
    </div>
</div>  
<?php include 'footer.php'; ?>