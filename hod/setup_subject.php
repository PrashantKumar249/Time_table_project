<?php
// setup_subject.php (New File)
include '../include/db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch HOD's branch
$hod_branch_query = "SELECT b.branch_id FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = $user_id";
$hod_branch_result = mysqli_query($conn, $hod_branch_query);
$hod_branch_id = mysqli_fetch_assoc($hod_branch_result)['branch_id'] ?? 1;

// Handle Add Section (A/B/C/D via JS, but save on submit if needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    $year = intval($_POST['section_year']);
    $semester = intval($_POST['section_semester']);
    $section_name = strtoupper(mysqli_real_escape_string($conn, trim($_POST['section_name'])));

    if ($year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 2 && !empty($section_name) && strlen($section_name) <= 1) {
        $check_query = "SELECT section_id FROM sections WHERE branch_id = $hod_branch_id AND year = $year AND semester = $semester AND section_name = '$section_name'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) == 0) {
            $query = "INSERT INTO sections (branch_id, year, semester, section_name) VALUES ($hod_branch_id, $year, $semester, '$section_name')";
            if (mysqli_query($conn, $query)) {
                $success_section = "Section '$section_name' added successfully!";
            } else {
                $error_section = "Error adding section: " . mysqli_error($conn);
            }
        } else {
            $error_section = "Section already exists.";
        }
    } else {
        $error_section = "Invalid input for section (e.g., A/B/C/D).";
    }
}

// Handle Allocate Subjects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_subjects'])) {
    $section_id = intval($_POST['selected_section_id']);
    $subject_ids = isset($_POST['selected_subjects']) ? array_map('intval', $_POST['selected_subjects']) : [];

    if ($section_id > 0 && !empty($subject_ids) && count($subject_ids) <= 6) {
        // Verify section and subjects match branch/year/sem
        $sec_query = "SELECT branch_id, year, semester FROM sections WHERE section_id = $section_id";
        $sec_result = mysqli_query($conn, $sec_query);
        if ($sec_row = mysqli_fetch_assoc($sec_result)) {
            $branch_id = $sec_row['branch_id'];
            $year = $sec_row['year'];
            $semester = $sec_row['semester'];

            // Delete old allocations
            mysqli_query($conn, "DELETE FROM section_subjects WHERE section_id = $section_id");

            // Insert new (validate subjects)
            $inserts = [];
            foreach ($subject_ids as $sub_id) {
                $sub_query = "SELECT subject_id FROM subjects WHERE subject_id = $sub_id AND branch_id = $branch_id AND year = $year AND semester = $semester";
                if (mysqli_num_rows(mysqli_query($conn, $sub_query)) > 0) {
                    $inserts[] = "($section_id, $sub_id)";
                }
            }
            if (!empty($inserts)) {
                $query = "INSERT INTO section_subjects (section_id, subject_id) VALUES " . implode(',', $inserts);
                if (mysqli_query($conn, $query)) {
                    $success_allocate = count($inserts) . " subjects allocated successfully!";
                } else {
                    $error_allocate = "Allocation error: " . mysqli_error($conn);
                }
            } else {
                $error_allocate = "No valid subjects to allocate.";
            }
        } else {
            $error_allocate = "Invalid section.";
        }
    } else {
        $error_allocate = "Select up to 6 subjects.";
    }
}

// Fetch Data
$years = [1,2,3,4];
$semesters = [1,2,3,4,5,6,7,8];

// Subjects by year-sem (for HOD's branch)
$subjects_query = "SELECT * FROM subjects WHERE branch_id = $hod_branch_id ORDER BY year, semester, subject_name";
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects_by_key = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $key = $row['year'] . '-' . $row['semester'];
    $subjects_by_key[$key][] = $row;
}

// Sections for HOD's branch
$sections_query = "SELECT * FROM sections WHERE branch_id = $hod_branch_id ORDER BY year, semester, section_name";
$sections_result = mysqli_query($conn, $sections_query);
$sections = [];
while ($row = mysqli_fetch_assoc($sections_result)) $sections[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Subjects in Sections</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .section { background: white; margin-bottom: 2rem; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section h2 { color: #1e293b; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; color: #475569; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-row .form-group { flex: 1 1 200px; min-width: 150px; }
        .btn { background: #3b82f6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: background 0.2s; }
        .btn:hover { background: #2563eb; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-add-section { background: #f59e0b; margin-left: 0.5rem; padding: 0.5rem 1rem; font-size: 0.875rem; }
        .btn-add-section:hover { background: #d97706; }
        .message { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .subjects-list { margin-top: 1rem; max-height: 300px; overflow-y: auto; }
        .subject-item { display: flex; align-items: center; padding: 0.5rem; border: 1px solid #e2e8f0; margin-bottom: 0.5rem; border-radius: 4px; }
        .subject-checkbox { margin-right: 0.5rem; }
        #subjectsContainer { display: none; margin-top: 1rem; }
        @media (max-width: 768px) { .form-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- Section: Add New Section (Optional, for A/B/C/D) -->
        <div class="section">
            <h2>Add New Section</h2>
            <?php if (isset($success_section)) echo "<div class='message success'>$success_section</div>"; ?>
            <?php if (isset($error_section)) echo "<div class='message error'>$error_section</div>"; ?>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="section_year">Year</label>
                        <select name="section_year" id="section_year" required>
                            <option value="">Select</option>
                            <?php foreach($years as $y) echo "<option value='$y'>$y</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section_semester">Semester</label>
                        <select name="section_semester" id="section_semester" required>
                            <option value="">Select</option>
                            <?php foreach($semesters as $s) echo "<option value='$s'>$s</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section_name">Section Name (A/B/C/D)</label>
                        <input type="text" name="section_name" id="section_name" placeholder="A" maxlength="1" required>
                    </div>
                </div>
                <button type="submit" name="add_section" class="btn btn-success">Add Section</button>
            </form>
        </div>

        <!-- Main Setup Form -->
        <div class="section">
            <h2>Setup Subjects in Section</h2>
            <?php if (isset($success_allocate)) echo "<div class='message success'>$success_allocate</div>"; ?>
            <?php if (isset($error_allocate)) echo "<div class='message error'>$error_allocate</div>"; ?>
            <form method="post" id="setupForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="year">Year</label>
                        <select id="year" name="year" required onchange="loadSubjectsAndSections()">
                            <option value="">Select</option>
                            <?php foreach($years as $y) echo "<option value='$y'>$y</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required onchange="loadSubjectsAndSections()">
                            <option value="">Select</option>
                            <?php foreach($semesters as $s) echo "<option value='$s'>$s</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section">Section</label>
                        <select id="section" name="selected_section_id" required>
                            <option value="">Select Section</option>
                            <option value="A">A</option>
                            <button type="button" onclick="addSection('B')" class="btn-add-section">Add B</button>
                            <button type="button" onclick="addSection('C')" class="btn-add-section">Add C</button>
                            <button type="button" onclick="addSection('D')" class="btn-add-section">Add D</button>
                        </select>
                    </div>
                </div>
                <div id="subjectsContainer">
                    <label>Select Subjects (Tick up to 6)</label>
                    <div id="subjectsList" class="subjects-list"></div>
                    <button type="submit" name="allocate_subjects" class="btn" style="margin-top:1rem;">Setup / Save</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const subjectsData = <?php echo json_encode($subjects_by_key); ?>;
        const sectionsData = <?php echo json_encode($sections); ?>;

        function loadSubjectsAndSections() {
            const year = document.getElementById('year').value;
            const sem = document.getElementById('semester').value;
            if (year && sem) {
                // Load Sections (filter existing + add new via buttons)
                const sectionSelect = document.getElementById('section');
                const key = year + '-' + sem;
                const filteredSections = sectionsData.filter(s => s.year == year && s.semester == sem);
                sectionSelect.innerHTML = '<option value="">Select Section</option>';
                filteredSections.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.section_id;
                    opt.text = s.section_name;
                    sectionSelect.appendChild(opt);
                });
                // Default A if not exists
                if (filteredSections.length === 0) {
                    const optA = document.createElement('option');
                    optA.value = 'A';
                    optA.text = 'A';
                    optA.selected = true;
                    sectionSelect.appendChild(optA);
                }
                loadSubjects();
            }
        }

        function addSection(secName) {
            const sectionSelect = document.getElementById('section');
            let opt = Array.from(sectionSelect.options).find(o => o.value === secName);
            if (!opt) {
                opt = document.createElement('option');
                opt.value = secName;
                opt.text = secName;
                sectionSelect.appendChild(opt);
            }
            sectionSelect.value = secName;
            loadSubjects(); // Reload subjects for new section (if needed, save section first)
        }

        function loadSubjects() {
            const year = document.getElementById('year').value;
            const sem = document.getElementById('semester').value;
            const section = document.getElementById('section').value;
            const container = document.getElementById('subjectsContainer');
            if (year && sem && section) {
                container.style.display = 'block';
                const key = year + '-' + sem;
                const subjects = subjectsData[key] || [];
                let html = '';
                subjects.slice(0, 10).forEach(sub => {
                    html += `<div class="subject-item">
                        <input type="checkbox" name="selected_subjects[]" value="${sub.subject_id}" class="subject-checkbox">
                        ${sub.subject_code} - ${sub.subject_name} (${sub.weekly_hours} hrs)
                    </div>`;
                });
                document.getElementById('subjectsList').innerHTML = html;
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>