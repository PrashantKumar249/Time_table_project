<?php
// subject_management.php
include '../include/db.php';
include 'header.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: ../index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
// Fetch HOD's branch - with proper error handling to avoid undefined key
$hod_branch_id = 1; // Default fallback to CS branch
$hod_branch_query = "SELECT b.branch_id, b.branch_name FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = $user_id";
$hod_branch_result = mysqli_query($conn, $hod_branch_query);
if ($hod_branch_result && mysqli_num_rows($hod_branch_result) > 0) {
    $hod_branch = mysqli_fetch_assoc($hod_branch_result);
    if (isset($hod_branch['branch_id']) && $hod_branch['branch_id'] > 0) {
        $hod_branch_id = $hod_branch['branch_id'];
    }
}
// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $branch_id = intval($_POST['branch_id']) ?: $hod_branch_id;
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    $subject_code = mysqli_real_escape_string($conn, trim($_POST['subject_code'])); // Manual code input
    $subject_name = mysqli_real_escape_string($conn, trim($_POST['subject_name']));
    $weekly_load = intval($_POST['weekly_load']);
    $type = mysqli_real_escape_string($conn, trim($_POST['type']));
    if ($branch_id > 0 && $year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 8 && !empty($subject_code) && !empty($subject_name) && $weekly_load > 0 && in_array($type, ['T', 'P'])) {
        // Check duplicate code
        $check_query = "SELECT subject_id FROM subjects WHERE branch_id = $branch_id AND subject_code = '$subject_code'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) == 0) {
            $query = "INSERT INTO subjects (branch_id, subject_code, type, subject_name, weekly_hours, year, semester) VALUES ($branch_id, '$subject_code', '$type', '$subject_name', $weekly_load, $year, $semester)";
            if (mysqli_query($conn, $query)) {
                $success_subject = "Subject '$subject_name' ($subject_code) added successfully!";
            } else {
                $error_subject = "Error adding subject: " . mysqli_error($conn);
            }
        } else {
            $error_subject = "Subject code '$subject_code' already exists for this branch.";
        }
    } else {
        $error_subject = "Invalid input. All fields required. Type must be T or P.";
    }
}
// Pagination and Search
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
// Base query for subjects
$subjects_query = "SELECT subject_id, subject_code, type, subject_name, weekly_hours, year, semester FROM subjects WHERE branch_id = $hod_branch_id";
if (!empty($search)) {
    $subjects_query .= " AND (subject_name LIKE '%$search%' OR subject_code LIKE '%$search%')";
}
$subjects_query .= " ORDER BY year, semester, subject_name LIMIT $limit OFFSET $offset";
// Count query for pagination
$count_query = "SELECT COUNT(*) as total FROM subjects WHERE branch_id = $hod_branch_id";
if (!empty($search)) {
    $count_query .= " AND (subject_name LIKE '%$search%' OR subject_code LIKE '%$search%')";
}
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);
// Fetch Subjects for List
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $row;
}
$years = [1,2,3,4];
$semesters = [1,2,3,4,5,6,7,8];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Existing styles + new for right-side link and edit button */
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; position: relative; }
        .section { background: white; margin-bottom: 2rem; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative; }
        .section h2 { color: #1e293b; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; color: #475569; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-row .form-group { flex: 1 1 200px; min-width: 150px; }
        .btn { background: #3b82f6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: background 0.2s; }
        .btn:hover { background: #2563eb; }
        .message { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .subjects-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .subjects-table th, .subjects-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .subjects-table th { background: #f1f5f9; font-weight: 600; }
        /* Right-side Link for Setup Subject in Sections - Nice Design with JS */
        .setup-link-container { position: absolute; top: 1rem; right: 1rem; z-index: 10; }
        .setup-link { display: flex; align-items: center; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 0.75rem 1rem; border-radius: 50px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .setup-link:hover { transform: translateY(-2px) scale(1.05); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); background: linear-gradient(135deg, #059669, #047857); }
        .setup-link i { margin-right: 0.5rem; font-size: 1.1rem; transition: transform 0.3s ease; }
        .setup-link:hover i { transform: rotate(10deg); }
        .setup-tooltip { position: absolute; top: -40px; right: 0; background: #1f2937; color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.875rem; opacity: 0; visibility: hidden; transition: all 0.3s ease; white-space: nowrap; z-index: 20; }
        .setup-link:hover .setup-tooltip { opacity: 1; visibility: visible; top: -35px; }
        /* Edit Button Style */
        .btn-edit { display: inline-flex; align-items: center; background: #f59e0b; color: white; padding: 0.5rem 0.75rem; border-radius: 4px; text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: background 0.2s; }
        .btn-edit:hover { background: #d97706; }
        .btn-edit i { margin-right: 0.25rem; }
        /* Pagination Styles */
        .pagination { margin-top: 1rem; text-align: center; }
        .pagination a, .pagination span { display: inline-block; margin: 0 2px; padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.2s; }
        .pagination a { color: #374151; background: #e5e7eb; }
        .pagination a:hover { background: #d1d5db; }
        .pagination .current { background: #3b82f6; color: white; }
        .pagination .prev, .pagination .next { padding: 0.5rem 1rem; background: #3b82f6; color: white; margin: 0 5px; }
        .pagination .prev:hover, .pagination .next:hover { background: #2563eb; }
        /* Search Form Styling */
        .search-container { display: flex; gap: 0.5rem; align-items: end; flex-wrap: wrap; }
        .search-container input { flex: 1; min-width: 200px; margin-bottom: 0; }
        .search-container .btn { margin: 0; padding: 0.75rem 1rem; white-space: nowrap; }
        .search-container .btn-clear { background: #6b7280; }
        .search-container .btn-clear:hover { background: #4b5563; }
        .search-container label { margin-bottom: 0.5rem; order: -1; width: 100%; }
        @media (max-width: 768px) { 
            .setup-link-container { position: static; margin: 1rem auto; text-align: center; } 
            .setup-tooltip { display: none; }
            .pagination { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.25rem; }
            .pagination a, .pagination span { padding: 0.4rem 0.6rem; font-size: 0.875rem; margin: 0; flex: 0 0 auto; min-width: 2.5rem; text-align: center; }
            .pagination .prev, .pagination .next { padding: 0.4rem 0.8rem; min-width: auto; margin: 0; }
            .pagination .prev, .pagination .next { font-size: 0.875rem; }
            .search-container { flex-direction: column; align-items: stretch; }
            .search-container input { min-width: auto; }
            .search-container .btn { width: 100%; margin-top: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Right-side Link for Setup Subject in Sections -->
        <div class="setup-link-container">
            <a href="setup_subject.php" class="setup-link" title="Setup Subjects in Sections">
                <i class="fas fa-cogs"></i>
                <span>Setup in Sections</span>
                <div class="setup-tooltip">Allocate subjects to sections (A/B/C/D)</div>
            </a>
        </div>
        <div class="section">
            <h2>Add Subject in Year</h2>
            <?php if (isset($success_subject)) echo "<div class='message success'>$success_subject</div>"; ?>
            <?php if (isset($error_subject)) echo "<div class='message error'>$error_subject</div>"; ?>
            <?php if (isset($_GET['success'])): ?>
                <script>alert('Subject updated successfully!');</script>
            <?php endif; ?>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="year">Year</label>
                        <select name="year" id="year" required>
                            <option value="">Select</option>
                            <?php foreach($years as $y) echo "<option value='$y'>$y</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" required>
                            <option value="">Select</option>
                            <?php foreach($semesters as $s) echo "<option value='$s'>$s</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select name="type" id="type" required>
                            <option value="">Select Type</option>
                            <option value="T">T (Theory)</option>
                            <option value="P">P (Practical)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject_code">Subject Code</label>
                        <input type="text" name="subject_code" id="subject_code" placeholder="e.g., BCS101" required>
                    </div>
                    <div class="form-group">
                        <label for="subject_name">Subject Name</label>
                        <input type="text" name="subject_name" id="subject_name" placeholder="e.g., Programming" required>
                    </div>
                    <div class="form-group">
                        <label for="weekly_load">Weekly Load (hrs)</label>
                        <input type="number" name="weekly_load" id="weekly_load" min="1" max="10" value="4" required>
                    </div>
                </div>
                <button type="submit" name="add_subject" class="btn">Add Subject</button>
            </form>
            <h3>Subjects List</h3>
            <form method="get" style="margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="search">Search Subjects</label>
                    <div class="search-container">
                        <input type="text" name="search" id="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn"><i class="fas fa-search"></i> Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="?" class="btn btn-clear"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <table class="subjects-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Type</th>
                        <th>Weekly Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($subjects as $sub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                            <td><?php echo $sub['year']; ?></td>
                            <td><?php echo $sub['semester']; ?></td>
                            <td><?php echo strtoupper($sub['type']); ?></td>
                            <td><?php echo $sub['weekly_hours']; ?></td>
                            <td>
                                <a href="edit_subject.php?subject_id=<?php echo $sub['subject_id']; ?>" class="btn-edit" title="Edit Subject">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="7">No subjects found<?php echo !empty($search) ? ' matching your search.' : ' added yet.'; ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="prev">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="next">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // JS for nice hover effects on link (already in CSS, but add click animation if needed)
        document.querySelector('.setup-link').addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => { this.style.transform = ''; }, 150);
        });
    </script>
<?php include 'footer.php'; ?>
</body>
</html>