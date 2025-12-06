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
// Handle AJAX search request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    $search = isset($_GET['search']) ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $per_page = 5;
    $offset = ($page - 1) * $per_page;
    $search_condition = '';
    if (!empty($search)) {
        $search_condition = " AND (f.faculty_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
    }
    // Total count
    $total_query = "SELECT COUNT(*) as total FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE f.branch_id = $branch_id" . $search_condition;
    $total_result = mysqli_query($conn, $total_query);
    $total_records = mysqli_fetch_assoc($total_result)['total'];
    $total_pages = ceil($total_records / $per_page);
    $faculty_query = "
        SELECT f.faculty_id, f.faculty_name, u.username, u.email, f.created_by, f.is_coordinator
        FROM faculty f
        JOIN users u ON f.user_id = u.user_id
        WHERE f.branch_id = $branch_id" . $search_condition . "
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
    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'faculty_list' => $faculty_list,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'search' => $search
    ]);
    exit;
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
    $is_coordinator = isset($_POST['is_coordinator']) ? 1 : 0;
  
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
                $query = "INSERT INTO faculty (user_id, branch_id, faculty_name, email, created_by, password, is_coordinator)
                          VALUES ($user_id, $branch_id, '$faculty_name', '$email', " . (int)$_SESSION['user_id'] . ", '$password', $is_coordinator)";
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
// Initial load without search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $search_condition = " AND (f.faculty_name LIKE '%$search_escaped%' OR u.username LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%')";
}
$search_param = !empty($search) ? 'search=' . urlencode($search) . '&' : '';
// Fetch initial faculty list with pagination
$per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;
// Total count
$total_query = "SELECT COUNT(*) as total FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE f.branch_id = $branch_id" . $search_condition;
$total_result = mysqli_query($conn, $total_query);
$total_records = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_records / $per_page);
$faculty_query = "
    SELECT f.faculty_id, f.faculty_name, u.username, u.email, f.created_by, f.is_coordinator
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.branch_id = $branch_id" . $search_condition . "
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
                    <div class="form-group">
                        <label for="is_coordinator"><i class="fas fa-user-check"></i> Coordinator</label>
                        <div style="display: flex; align-items: center; margin-top: 0.25rem;">
                            <input type="checkbox" id="is_coordinator" name="is_coordinator" class="coordinator-checkbox">
                            <span style="margin-left: 0.5rem; color: #666; font-size: 0.9rem;">Mark as Coordinator</span>
                        </div>
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
              
                <div class="form-group" style="grid-column: span 2; display: flex; gap: 0.5rem; align-items: center;">
                    <button type="submit" name="add_faculty" class="button-submit"><i class="fas fa-save"></i> Add Faculty</button>
                    <button type="reset" class="button-reset"><i class="fas fa-times"></i> Clear</button>
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
            <?php if (empty($faculty_list) && empty($search)): ?>
                <p style="text-align: center; color: #6b7280; padding: 2rem;">No faculty members found.</p>
            <?php endif; ?>
            <form method="GET" class="search-form" id="searchForm">
                <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, username, or email..." class="input-field">
                <button type="submit" class="button button-primary"><i class="fas fa-search"></i> Search</button>
            </form>
            <div id="suggestions-dropdown" class="suggestions-dropdown" style="display: none;"></div>
            <div id="faculty-results">
                <?php if (!empty($faculty_list)): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Faculty Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Coordinator</th>
                                    <th>Subjects Taught</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="faculty-tbody">
                                <?php foreach ($faculty_list as $name => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($name); ?></td>
                                        <td><?php echo htmlspecialchars($data['username']); ?></td>
                                        <td><?php echo htmlspecialchars($data['email']); ?></td>
                                        <td>
                                            <span class="coordinator-badge <?php echo ($data['is_coordinator'] ?? 0) ? 'coordinator-yes' : 'coordinator-no'; ?>">
                                                <?php echo ($data['is_coordinator'] ?? 0) ? 'Yes' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($data['subjects'])): ?>
                                                <?php echo htmlspecialchars(implode(', ', array_unique($data['subjects']))); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <a href="edit_faculty.php?id=<?php echo $data['faculty_id']; ?>" class="button button-primary" style="padding: 0.375rem 0.75rem; font-size: 0.8rem;"><i class="fas fa-edit"></i> Edit</a>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this faculty?');">
                                                <input type="hidden" name="faculty_id" value="<?php echo $data['faculty_id']; ?>">
                                                <button type="submit" name="delete_faculty" class="button button-danger" style="padding: 0.375rem 0.75rem; font-size: 0.8rem;"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination" id="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo $search_param; ?>page=<?php echo $page - 1; ?>" class="pagination-link" onclick="loadResults(<?php echo $page - 1; ?>, '<?php echo htmlspecialchars($search); ?>')">&laquo; Previous</a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="pagination-current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo $search_param; ?>page=<?php echo $i; ?>" class="pagination-link" onclick="loadResults(<?php echo $i; ?>, '<?php echo htmlspecialchars($search); ?>')"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo $search_param; ?>page=<?php echo $page + 1; ?>" class="pagination-link" onclick="loadResults(<?php echo $page + 1; ?>, '<?php echo htmlspecialchars($search); ?>')">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (!empty($search)): ?>
                    <p style="text-align: center; color: #6b7280; padding: 2rem;">No faculty members found for "<?php echo htmlspecialchars($search); ?>".</p>
                <?php endif; ?>
            </div>
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
// AJAX for search suggestions
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsDropdown = document.getElementById('suggestions-dropdown');
    let timeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            if (query.length < 2) {
                suggestionsDropdown.style.display = 'none';
                if (query === '') {
                    loadResults(1, '');
                }
                return;
            }
            // Immediate suggestion fetch on key press with minimal debounce
            timeout = setTimeout(() => {
                // Show suggestions
                fetch(`facultysuggest.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            suggestionsDropdown.innerHTML = data.map(suggestion =>
                                `<div class="suggestion-item" onclick="selectSuggestion('${suggestion.name} (${suggestion.username})')">
                                    ${suggestion.name} (${suggestion.username})
                                </div>`
                            ).join('');
                            suggestionsDropdown.style.display = 'block';
                        } else {
                            suggestionsDropdown.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching suggestions:', error);
                        suggestionsDropdown.style.display = 'none';
                    });
                // Live search
                loadResults(1, query);
            }, 100); // Reduced debounce to 100ms for faster response on key press
        });
        searchInput.addEventListener('blur', function() {
            setTimeout(() => {
                suggestionsDropdown.style.display = 'none';
            }, 200);
        });
        suggestionsDropdown.addEventListener('mouseover', function() {
            searchInput.focus();
        });
    }
});
function selectSuggestion(value) {
    const searchInput = document.getElementById('searchInput');
    searchInput.value = value;
    document.getElementById('suggestions-dropdown').style.display = 'none';
    loadResults(1, value);
}
function loadResults(page, search) {
    const url = `?ajax=search&search=${encodeURIComponent(search)}&page=${page}`;
    fetch(url)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('faculty-tbody');
            const pagination = document.getElementById('pagination');
            const facultyResults = document.getElementById('faculty-results');
            if (Object.keys(data.faculty_list).length === 0) {
                facultyResults.innerHTML = `<p style="text-align: center; color: #6b7280; padding: 2rem;">${data.search ? 'No faculty members found for "' + data.search + '".' : 'No faculty members found.'}</p>`;
                return;
            }
            let tableHTML = `
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Coordinator</th>
                                <th>Subjects Taught</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            Object.entries(data.faculty_list).forEach(([name, fac]) => {
                const subjects = fac.subjects && fac.subjects.length > 0 ? fac.subjects.join(', ') : 'N/A';
                const isCoordinator = fac.is_coordinator ? 'Yes' : 'No';
                const coordinatorClass = fac.is_coordinator ? 'coordinator-yes' : 'coordinator-no';
                tableHTML += `
                    <tr>
                        <td>${escapeHtml(name)}</td>
                        <td>${escapeHtml(fac.username)}</td>
                        <td>${escapeHtml(fac.email)}</td>
                        <td><span class="coordinator-badge ${coordinatorClass}">${isCoordinator}</span></td>
                        <td>${escapeHtml(subjects)}</td>
                        <td class="actions">
                            <a href="edit_faculty.php?id=${fac.faculty_id}" class="button button-primary" style="padding: 0.375rem 0.75rem; font-size: 0.8rem;"><i class="fas fa-edit"></i> Edit</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this faculty?');">
                                <input type="hidden" name="faculty_id" value="${fac.faculty_id}">
                                <button type="submit" name="delete_faculty" class="button button-danger" style="padding: 0.375rem 0.75rem; font-size: 0.8rem;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                `;
            });
            tableHTML += `
                        </tbody>
                    </table>
            `;
                if (data.total_pages > 1) {
                tableHTML += `
                    <div class="pagination">
                `;
                if (data.current_page > 1) {
                    const prevHref = `?search=${encodeURIComponent(data.search)}&page=${data.current_page - 1}`;
                    tableHTML += `<a href="${prevHref}" class="pagination-link" onclick="loadResults(${data.current_page - 1}, '${escapeHtml(data.search)}')">&laquo; Previous</a>`;
                }
                for (let i = 1; i <= data.total_pages; i++) {
                    if (i === data.current_page) {
                        tableHTML += `<span class="pagination-current">${i}</span>`;
                    } else {
                        const href = `?search=${encodeURIComponent(data.search)}&page=${i}`;
                        tableHTML += `<a href="${href}" class="pagination-link" onclick="loadResults(${i}, '${escapeHtml(data.search)}')">${i}</a>`;
                    }
                }
                if (data.current_page < data.total_pages) {
                    const nextHref = `?search=${encodeURIComponent(data.search)}&page=${data.current_page + 1}`;
                    tableHTML += `<a href="${nextHref}" class="pagination-link" onclick="loadResults(${data.current_page + 1}, '${escapeHtml(data.search)}')">Next &raquo;</a>`;
                }
                tableHTML += `</div>`;
            }
            tableHTML += `</div>`;
            facultyResults.innerHTML = tableHTML;
        })
        .catch(error => {
            console.error('Error loading results:', error);
        });
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.16s ease;
    width: auto;
    box-shadow: 0 1px 2px rgba(16,185,129,0.12);
}
.add-subject-btn:hover {
    background: #059669;
}
.button-submit {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.16s ease;
    width: auto;
    box-shadow: 0 1px 2px rgba(59, 130, 246, 0.12);
}
.button-submit:hover {
    background: #2563eb;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
}
.button-reset {
    background: #6b7280;
    color: white;
    border: none;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.16s ease;
    width: auto;
    box-shadow: 0 1px 2px rgba(107, 114, 128, 0.12);
}
.button-reset:hover {
    background: #4b5563;
    box-shadow: 0 2px 4px rgba(107, 114, 128, 0.2);
}
.add-subject-btn i {
    margin-right: 0.125rem;
}
.button-submit i,
.button-reset i {
    margin-right: 0.4rem;
}
.remove-subject-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 0.325rem 0.6rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.82rem;
    display: inline-flex;
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
    display: inline-flex;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    margin: 0 0.125rem;
    transition: all 0.14s ease;
    align-items: center;
    width: auto;
}
.button i {
    margin-right: 0.4rem;
}
.button-primary {
    background: #3b82f6;
    color: white;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(59, 130, 246, 0.12);
}
.button-primary:hover {
    background: #2563eb;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
}
.button-danger {
    background: #ef4444;
    color: white;
}
.button-danger:hover {
    background: #dc2626;
}
.button-secondary {
    background: #6b7280;
    color: white;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(107, 114, 128, 0.12);
}
.button-secondary:hover {
    background: #4b5563;
    box-shadow: 0 2px 4px rgba(107, 114, 128, 0.2);
}
.form-group .add-subject-btn,
.form-group .remove-subject-btn {
    /* prevent stretch inside .form-group (which uses align-items: stretch) */
    align-self: flex-start;
}

/* ensure buttons inside search or other full-width containers don't expand */
.search-form .button,
.card-header .button {
    width: auto;
}
.coordinator-checkbox {
    width: 1.25rem;
    height: 1.25rem;
    cursor: pointer;
    accent-color: #3b82f6;
}
.coordinator-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    min-width: 50px;
}
.coordinator-yes {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.coordinator-no {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
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
.search-form {
    display: flex;
    margin-bottom: 1rem;
    gap: 0;
    position: relative;
}
.search-form .input-field {
    border-radius: 4px 0 0 4px;
    border-right: none;
    flex: 1;
    max-width: 400px;
}
.search-form .button {
    border-radius: 0 4px 4px 0;
    border-left: none;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    margin: 0;
}
.suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-width: 400px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
}
.suggestion-item {
    padding: 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.9rem;
    color: #374151;
}
.suggestion-item:hover {
    background: #f3f4f6;
}
.suggestion-item:last-child {
    border-bottom: none;
}
@media (max-width: 768px) {
    .container { padding: 0.5rem; }
    .form-grid { grid-template-columns: 1fr; }
    .card-body { padding: 1rem; }
    .data-table th, .data-table td { padding: 0.5rem; font-size: 0.85rem; }
    .button { padding: 0.2rem 0.4rem; font-size: 0.7rem; }
    .pagination a, .pagination span { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
    .search-form {
        flex-direction: column;
    }
    .search-form .input-field {
        border-radius: 4px;
        border-right: 1px solid #ddd;
        max-width: none;
        margin-bottom: 0.5rem;
    }
    .search-form .button {
        border-radius: 4px;
        border-left: 1px solid #3b82f6;
        width: 100%;
    }
    .suggestions-dropdown {
        position: static;
        max-width: none;
        margin-top: 0.5rem;
    }
}
</style>
<?php include 'footer.php'; ?>