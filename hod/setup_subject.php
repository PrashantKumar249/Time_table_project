<?php
// setup_subject.php (Updated: Year-focused allocation, All year subjects, Section add multiple A/B, Minimal styling)
include '../include/db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch HOD's branch with error handling
$hod_branch_id = 1; // Default fallback
$hod_branch_query = "SELECT b.branch_id FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = $user_id";
$hod_branch_result = mysqli_query($conn, $hod_branch_query);
if ($hod_branch_result && mysqli_num_rows($hod_branch_result) > 0) {
    $hod_branch_row = mysqli_fetch_assoc($hod_branch_result);
    if (isset($hod_branch_row['branch_id']) && $hod_branch_row['branch_id'] > 0) {
        $hod_branch_id = $hod_branch_row['branch_id'];
    }
}

// Handle Add Section (Normal form submission)
$success_section = '';
$error_section = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    $section_name = strtoupper(mysqli_real_escape_string($conn, trim($_POST['section_name'])));
    if ($year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 8 && !empty($section_name) && strlen($section_name) <= 3) { // Allow A, B, etc. up to 3 chars
        $check_query = "SELECT section_id FROM sections WHERE branch_id = $hod_branch_id AND year = $year AND semester = $semester AND section_name = '$section_name'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) == 0) {
            $query = "INSERT INTO sections (branch_id, year, semester, section_name) VALUES ($hod_branch_id, $year, $semester, '$section_name')";
            if (mysqli_query($conn, $query)) {
                $new_section_id = mysqli_insert_id($conn);
                $success_section = "Section '$section_name' added successfully for Year $year, Sem $semester! ID: $new_section_id";
            } else {
                $error_section = "Error adding section: " . mysqli_error($conn);
            }
        } else {
            $error_section = "Section '$section_name' already exists for this year/sem.";
        }
    } else {
        $error_section = "Invalid input. Year (1-4), Sem (1-8), Section (e.g., A, B).";
    }
}

// Handle Subject Allocation to Section (Save selected subjects to section_subjects)
$success_alloc = '';
$error_alloc = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_subjects'])) {
    $section_id = intval($_POST['section_id']);
    $selected_subjects_input = $_POST['selected_subjects'] ?? '';
    $selected_subjects = array_filter(explode(',', $selected_subjects_input));
    if ($section_id > 0 && !empty($selected_subjects)) {
        // Clear existing allocations for this section
        $clear_query = "DELETE FROM section_subjects WHERE section_id = $section_id";
        mysqli_query($conn, $clear_query);
        
        // Insert new allocations
        $insert_count = 0;
        foreach ($selected_subjects as $sub_id) {
            $sub_id = intval(trim($sub_id));
            if ($sub_id > 0) {
                $query = "INSERT INTO section_subjects (section_id, subject_id) VALUES ($section_id, $sub_id)";
                if (mysqli_query($conn, $query)) {
                    $insert_count++;
                }
            }
        }
        if ($insert_count > 0) {
            $success_alloc = "$insert_count subjects allocated to the selected section successfully!";
        } else {
            $error_alloc = "No subjects were allocated.";
        }
    } else {
        $error_alloc = "Please select a section and at least one subject.";
    }
}

// Fetch Years for Dropdown
$years_query = "SELECT DISTINCT year FROM sections WHERE branch_id = $hod_branch_id ORDER BY year";
$years_result = mysqli_query($conn, $years_query);
$years_options = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $years_options[] = $row['year'];
}

// Fetch Semesters (1-8 for frontend)
$sem_options = range(1, 8);

// Fetch Sections Dynamically (filtered by year and sem via JS/AJAX, but initial load none)
$sections_query = "SELECT section_id, section_name, year, semester FROM sections WHERE branch_id = $hod_branch_id ORDER BY year, semester, section_name";
$sections_result = mysqli_query($conn, $sections_query);
$all_sections = [];
while ($row = mysqli_fetch_assoc($sections_result)) {
    $all_sections[] = $row;
}

// Fetch All Subjects for the Branch (Year-filtered via JS)
$subjects_query = "SELECT subject_id, subject_code, subject_name, year, semester, weekly_hours FROM subjects WHERE branch_id = $hod_branch_id ORDER BY year, subject_name";
$subjects_result = mysqli_query($conn, $subjects_query);
$all_subjects = [];
while ($row = mysqli_fetch_assoc($subjects_result)) {
    $all_subjects[] = $row;
}
?>

<style>
    /* Minimal Styling */
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #45a049; }
    button:disabled { background: #ccc; cursor: not-allowed; }
    .subjects-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
    .subject-item { margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 4px; }
    .add-section-btn { background: yellow; color: black; padding: 10px 15px; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .modal-content { background: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 400px; }
    .error { color: red; }
    .success { color: green; }
</style>

<div class="container">
    <h1>Setup Subjects & Sections</h1>

    <?php if ($success_section): ?>
        <p class="success"><?php echo htmlspecialchars($success_section); ?></p>
    <?php endif; ?>
    <?php if ($error_section): ?>
        <p class="error"><?php echo htmlspecialchars($error_section); ?></p>
    <?php endif; ?>

    <?php if ($success_alloc): ?>
        <p class="success"><?php echo htmlspecialchars($success_alloc); ?></p>
    <?php endif; ?>
    <?php if ($error_alloc): ?>
        <p class="error"><?php echo htmlspecialchars($error_alloc); ?></p>
    <?php endif; ?>

    <!-- Section Addition -->
    <h2>Add Sections (e.g., A, B for Year/Sem)</h2>
    <form method="POST">
        <input type="hidden" name="add_section" value="1">
        <div class="form-group">
            <label for="year">Year:</label>
            <input type="number" id="year" name="year" min="1" max="4" required placeholder="1">
        </div>
        <div class="form-group">
            <label for="semester">Semester (1-8):</label>
            <select id="semester" name="semester" required>
                <option value="">Select Semester</option>
                <?php foreach ($sem_options as $sem): ?>
                    <option value="<?php echo $sem; ?>"><?php echo $sem; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="section_name">Section Name (e.g., A, B):</label>
            <input type="text" id="section_name" name="section_name" maxlength="3" required placeholder="A">
        </div>
        <button type="submit" class="add-section-btn">Add Section</button>
    </form>

    <!-- Subject Allocation -->
    <h2>Allocate Subjects to Section</h2>
    <form method="POST">
        <div class="form-group">
            <label for="alloc_year">Year (Main Filter):</label>
            <select id="alloc_year" name="year" required onchange="filterSectionsAndSubjects()">
                <option value="">Select Year</option>
                <?php foreach ($years_options as $yr): ?>
                    <option value="<?php echo $yr; ?>"><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="alloc_sem">Semester (For Storage):</label>
            <select id="alloc_sem" name="semester" required onchange="filterSectionsAndSubjects()">
                <option value="">Select Semester</option>
                <?php foreach ($sem_options as $sem): ?>
                    <option value="<?php echo $sem; ?>"><?php echo $sem; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="section_id">Section:</label>
            <select id="section_id" name="section_id" required disabled onchange="toggleSaveButton()">
                <option value="">Select Year/Sem First</option>
            </select>
        </div>
        <div class="form-group">
            <label>Subjects for Year (All Semesters):</label>
            <div id="subjects-container" class="subjects-list">
                <p>Select Year First to Load Subjects</p>
            </div>
            <input type="hidden" id="selected_subjects" name="selected_subjects" value="">
        </div>
        <button type="submit" name="allocate_subjects" id="saveBtn" disabled>Save Allocation</button>
    </form>
</div>

<script>
let allSections = <?php echo json_encode($all_sections); ?>;
let allSubjects = <?php echo json_encode($all_subjects); ?>;
let selectedSubjects = [];

function filterSectionsAndSubjects() {
    const year = document.getElementById('alloc_year').value;
    const sem = document.getElementById('alloc_sem').value;
    const sectionSelect = document.getElementById('section_id');
    const subjectsContainer = document.getElementById('subjects-container');

    if (!year || !sem) {
        sectionSelect.innerHTML = '<option value="">Select Year and Sem First</option>';
        sectionSelect.disabled = true;
        subjectsContainer.innerHTML = '<p>Select Year and Sem First to Load Subjects</p>';
        selectedSubjects = [];
        updateHiddenInput();
        toggleSaveButton();
        return;
    }

    // Filter sections by year and semester directly
    const filteredSections = allSections.filter(s => parseInt(s.year) === parseInt(year) && parseInt(s.semester) === parseInt(sem));
    sectionSelect.innerHTML = '<option value="">Select Section</option>';
    if (filteredSections.length === 0) {
        sectionSelect.innerHTML += '<option value="" disabled>No sections found</option>';
    } else {
        filteredSections.forEach(sec => {
            const option = document.createElement('option');
            option.value = sec.section_id;
            option.textContent = `${sec.section_name} (Year ${sec.year}, Sem ${sec.semester})`;
            sectionSelect.appendChild(option);
        });
    }
    sectionSelect.disabled = filteredSections.length === 0;

    // Filter subjects by year and semester
    const filteredSubjects = allSubjects.filter(sub => parseInt(sub.year) === parseInt(year) && parseInt(sub.semester) === parseInt(sem));
    subjectsContainer.innerHTML = '';
    if (filteredSubjects.length === 0) {
        subjectsContainer.innerHTML = '<p>No subjects for this year/semester.</p>';
    } else {
        filteredSubjects.forEach(sub => {
            const div = document.createElement('div');
            div.className = 'subject-item';
            div.innerHTML = `
                <input type="checkbox" value="${sub.subject_id}" onchange="handleCheckboxChange(this)">
                <label>${sub.subject_code} - ${sub.subject_name} (${sub.weekly_hours} hrs)</label>
            `;
            subjectsContainer.appendChild(div);
        });
    }
    selectedSubjects = [];
    updateHiddenInput();
    toggleSaveButton();
}

function handleCheckboxChange(checkbox) {
    const id = parseInt(checkbox.value);
    if (checkbox.checked) {
        if (selectedSubjects.length < 10) { // Limit to 10 subjects max
            if (!selectedSubjects.includes(id)) selectedSubjects.push(id);
        } else {
            checkbox.checked = false;
            alert('Maximum 10 subjects allowed!');
            return;
        }
    } else {
        selectedSubjects = selectedSubjects.filter(s => s !== id);
    }
    updateHiddenInput();
    toggleSaveButton();
}

function updateHiddenInput() {
    document.getElementById('selected_subjects').value = selectedSubjects.join(',');
}

function toggleSaveButton() {
    const yearSelected = document.getElementById('alloc_year').value;
    const semSelected = document.getElementById('alloc_sem').value;
    const sectionSelected = document.getElementById('section_id').value;
    const btn = document.getElementById('saveBtn');
    btn.disabled = !yearSelected || !semSelected || !sectionSelected || selectedSubjects.length === 0;
}

document.addEventListener('DOMContentLoaded', function() {
    toggleSaveButton();
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>