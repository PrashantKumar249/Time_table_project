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

$success_section = '';
$error_section = '';

// Handle Delete Section
if (isset($_GET['delete_section_id'])) {
    $del_sec_id = intval($_GET['delete_section_id']);
    $del_check = mysqli_query($conn, "SELECT section_id FROM sections WHERE section_id = $del_sec_id AND branch_id = $hod_branch_id");
    if (mysqli_num_rows($del_check) > 0) {
        mysqli_query($conn, "DELETE FROM timetable_slots WHERE section_id = $del_sec_id");
        mysqli_query($conn, "DELETE FROM section_subjects WHERE section_id = $del_sec_id");
        mysqli_query($conn, "DELETE FROM sections WHERE section_id = $del_sec_id");
        $success_section = "Section deleted successfully!";
    } else {
        $error_section = "Invalid section or permission denied.";
    }
}

// Check if editing
$edit_section = null;
if (isset($_GET['edit_section_id'])) {
    $ed_id = intval($_GET['edit_section_id']);
    $ed_q = mysqli_query($conn, "SELECT s.*, r.room_number FROM sections s LEFT JOIN rooms r ON s.room_id = r.room_id WHERE s.section_id = $ed_id AND s.branch_id = $hod_branch_id");
    if ($ed_q && mysqli_num_rows($ed_q) > 0) {
        $edit_section = mysqli_fetch_assoc($ed_q);
    }
}

// Handle Add/Update Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_section']) || isset($_POST['update_section']))) {
    $is_update = isset($_POST['update_section']);
    $sec_id = $is_update ? intval($_POST['section_id']) : 0;
    
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    $section_name = strtoupper(mysqli_real_escape_string($conn, trim($_POST['section_name'])));
    $room_input = isset($_POST['room_number']) ? trim($_POST['room_number']) : '';

    if ($year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 8 && preg_match('/^[A-Z]$/', $section_name)) {
        
        $room_id = 'NULL';
        if ($room_input !== '') {
            $room_safe = mysqli_real_escape_string($conn, $room_input);
            $room_check = mysqli_query($conn, "SELECT room_id FROM rooms WHERE room_number = '$room_safe' LIMIT 1");
            if ($room_check && mysqli_num_rows($room_check) > 0) {
                $room_id = intval(mysqli_fetch_assoc($room_check)['room_id']);
            } else {
                if (mysqli_query($conn, "INSERT INTO rooms (room_number, capacity) VALUES ('$room_safe', 0)")) {
                    $room_id = mysqli_insert_id($conn);
                }
            }
        }
        $room_part = ($room_id === 'NULL') ? 'NULL' : intval($room_id);

        if ($is_update) {
            $check_query = "SELECT section_id FROM sections WHERE branch_id = $hod_branch_id AND year = $year AND semester = $semester AND section_name = '$section_name' AND section_id != $sec_id";
            if (mysqli_num_rows(mysqli_query($conn, $check_query)) == 0) {
                if (mysqli_query($conn, "UPDATE sections SET year=$year, semester=$semester, section_name='$section_name', room_id=$room_part WHERE section_id=$sec_id AND branch_id=$hod_branch_id")) {
                    $success_section = "Section updated successfully!";
                    $edit_section = null; // Clear edit mode
                } else {
                    $error_section = "Error updating section.";
                }
            } else {
                $error_section = "Another section with this name already exists for this year/sem.";
            }
        } else {
            $check_query = "SELECT section_id FROM sections WHERE branch_id = $hod_branch_id AND year = $year AND semester = $semester AND section_name = '$section_name'";
            if (mysqli_num_rows(mysqli_query($conn, $check_query)) == 0) {
                if (mysqli_query($conn, "INSERT INTO sections (branch_id, year, semester, section_name, room_id) VALUES ($hod_branch_id, $year, $semester, '$section_name', $room_part)")) {
                    $success_section = "Section '$section_name' added successfully!";
                } else {
                    $error_section = "Error adding section.";
                }
            } else {
                $error_section = "Section '$section_name' already exists for this year/sem.";
            }
        }
    } else {
        $error_section = "Invalid input. Year (1-4), Sem (1-8), Section must be a single uppercase letter (A-Z).";
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
$sections_query = "SELECT s.section_id, s.section_name, s.year, s.semester, s.room_id, r.room_number FROM sections s LEFT JOIN rooms r ON s.room_id = r.room_id WHERE s.branch_id = $hod_branch_id ORDER BY s.year, s.semester, s.section_name";
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

// Build section -> allocated subjects mapping
$section_allocations = [];
$alloc_q = "SELECT section_id, subject_id FROM section_subjects";
$alloc_r = mysqli_query($conn, $alloc_q);
if ($alloc_r) {
    while ($ar = mysqli_fetch_assoc($alloc_r)) {
        $sid = intval($ar['section_id']);
        $sub = intval($ar['subject_id']);
        if (!isset($section_allocations[$sid])) $section_allocations[$sid] = [];
        $section_allocations[$sid][] = $sub;
    }
}

// Build subject map for quick lookup by subject_id
$subject_map = [];
foreach ($all_subjects as $s) {
    $subject_map[intval($s['subject_id'])] = $s;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Subjects - HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Modern Professional Styling */
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; color: #334155; }
        .page-header { background: #fff; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; margin-bottom: 30px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { margin: 0; font-size: 24px; color: #0f172a; font-weight: 700; }
        .header-actions a { display: inline-flex; align-items: center; background: #3b82f6; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: background 0.2s; }
        .header-actions a i { margin-right: 8px; }
        .header-actions a:hover { background: #2563eb; }

        .main-container { max-width: 1300px; margin: 0 auto; padding: 0 20px 40px; display: grid; grid-template-columns: 1fr; gap: 30px; }
        @media (min-width: 992px) {
            .main-container { grid-template-columns: 1fr 1.2fr; }
        }

        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); padding: 25px; margin-bottom: 25px; border: 1px solid #f1f5f9; }
        .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9; }
        .card-title { font-size: 18px; font-weight: 600; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: #3b82f6; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #475569; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; transition: all 0.2s; outline: none; background: #fdfdfd; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); background: #fff; }
        
        .btn-primary { background: #3b82f6; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; font-family: inherit; transition: all 0.2s; width: 100%; display: inline-flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-primary:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
        
        .btn-success { background: #10b981; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; font-family: inherit; transition: all 0.2s; width: 100%; display: inline-flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-success:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }

        .subjects-list { max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #f8fafc; }
        /* Scrollbar for subjects list */
        .subjects-list::-webkit-scrollbar { width: 6px; }
        .subjects-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 8px; }
        .subjects-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        .subjects-list::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .subject-item { margin-bottom: 8px; padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: space-between; transition: border-color 0.2s; cursor: pointer; }
        .subject-item:hover { border-color: #3b82f6; }
        .subject-item label { margin: 0; font-weight: 500; font-size: 14px; color: #334155; cursor: pointer; flex: 1; display:flex; flex-direction: column; }
        .subject-item label .sub-code { font-size: 12px; color: #64748b; margin-bottom: 3px; font-weight: 600; }
        .subject-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #3b82f6; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .sections-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .section-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s; }
        .section-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .section-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px dashed #cbd5e1; }
        .sec-name { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
        .sec-badge { font-size: 11px; padding: 4px 8px; background: #eff6ff; color: #2563eb; border-radius: 20px; font-weight: 600; border: 1px solid #bfdbfe; }
        .sec-info { font-size: 13px; color: #64748b; margin-top: 5px; display: flex; align-items: center; gap: 15px; }
        .sec-info span { display: flex; align-items: center; gap: 5px; }

        .alloc-title { font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .alloc-list { display: flex; flex-direction: column; gap: 8px; }
        .alloc-badge { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px; font-size: 13px; color: #334155; font-weight: 500; display: flex; justify-content: space-between; align-items: center; }
        .alloc-badge .code { font-weight: 700; color: #0f172a; font-size: 12px; }
        .alloc-badge .hrs { font-size: 11px; color: #64748b; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; }
        
        .empty-state { text-align: center; padding: 20px; color: #94a3b8; font-size: 14px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
        
        .helper-text { font-size: 12px; color: #64748b; margin-top: 5px; display: block; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>Setup Subjects & Sections</h1>
    <div class="header-actions">
        <a href="subject_management.php"><i class="fas fa-arrow-left"></i> Back to Subjects</a>
    </div>
</div>

<div class="main-container">

    <div class="left-col">
        <?php if ($success_section): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_section); ?></div>
        <?php endif; ?>
        <?php if ($error_section): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_section); ?></div>
        <?php endif; ?>

        <?php if ($success_alloc): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_alloc); ?></div>
        <?php endif; ?>
        <?php if ($error_alloc): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_alloc); ?></div>
        <?php endif; ?>

        <div class="card" id="section-form">
            <div class="card-header">
                <h2 class="card-title"><i class="fas <?php echo $edit_section ? 'fa-edit' : 'fa-layer-group'; ?>"></i> <?php echo $edit_section ? 'Edit Section' : 'Create New Section'; ?></h2>
                <?php if ($edit_section): ?>
                    <a href="setup_subject.php" style="font-size: 13px; color: #ef4444; text-decoration: none; margin-top: 5px; display: inline-block;">Cancel Edit</a>
                <?php endif; ?>
            </div>
            <form method="POST">
                <?php if ($edit_section): ?>
                    <input type="hidden" name="update_section" value="1">
                    <input type="hidden" name="section_id" value="<?php echo intval($edit_section['section_id']); ?>">
                <?php else: ?>
                    <input type="hidden" name="add_section" value="1">
                <?php endif; ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" class="form-control" min="1" max="4" required placeholder="e.g. 1" value="<?php echo $edit_section ? intval($edit_section['year']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" class="form-control" required>
                            <option value="">Select</option>
                            <?php foreach ($sem_options as $sem): ?>
                                <option value="<?php echo $sem; ?>" <?php echo ($edit_section && $edit_section['semester'] == $sem) ? 'selected' : ''; ?>><?php echo $sem; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="section_name">Section Name</label>
                    <input type="text" id="section_name" name="section_name" class="form-control" maxlength="1" pattern="[A-Z]" title="Enter single uppercase letter (A-Z)" required placeholder="e.g. A" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')" value="<?php echo $edit_section ? htmlspecialchars($edit_section['section_name']) : ''; ?>">
                    <span class="helper-text">Single uppercase letter (A, B, C...)</span>
                </div>
                <div class="form-group">
                    <label for="room_number">Room Number (Optional)</label>
                    <input type="text" id="room_number" name="room_number" class="form-control" maxlength="50" placeholder="e.g. Room 101 or Lab CS1" value="<?php echo $edit_section ? htmlspecialchars($edit_section['room_number'] ?? '') : ''; ?>">
                </div>
                <button type="submit" class="btn-primary"><i class="fas <?php echo $edit_section ? 'fa-save' : 'fa-plus'; ?>"></i> <?php echo $edit_section ? 'Update Section' : 'Add Section'; ?></button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-tasks"></i> Allocate Subjects to Section</h2>
            </div>
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="alloc_year">Filter by Year</label>
                        <select id="alloc_year" name="year" class="form-control" required onchange="filterSectionsAndSubjects()">
                            <option value="">Select Year</option>
                            <?php foreach ($years_options as $yr): ?>
                                <option value="<?php echo $yr; ?>">Year <?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc_sem">Filter by Semester</label>
                        <select id="alloc_sem" name="semester" class="form-control" required onchange="filterSectionsAndSubjects()">
                            <option value="">Select Semester</option>
                            <?php foreach ($sem_options as $sem): ?>
                                <option value="<?php echo $sem; ?>">Sem <?php echo $sem; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="section_id">Target Section</label>
                    <select id="section_id" name="section_id" class="form-control" required disabled onchange="filterSectionsAndSubjects()">
                        <option value="">Select Year/Sem First</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Subjects for Allocation</label>
                    <div id="subjects-container" class="subjects-list">
                        <div class="empty-state">Select Year and Semester first to load available subjects</div>
                    </div>
                    <input type="hidden" id="selected_subjects" name="selected_subjects" value="">
                </div>
                <button type="submit" name="allocate_subjects" id="saveBtn" class="btn-success" disabled><i class="fas fa-save"></i> Save Current Allocation</button>
            </form>
        </div>
    </div>

    <div class="right-col">
        <div class="card" style="height: 100%;">
            <div class="card-header" style="flex-wrap:wrap;">
                <h2 class="card-title"><i class="fas fa-sitemap"></i> Sections Overview</h2>
                <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                    <select id="overview_year" class="form-control" style="width:auto; padding:6px 12px; font-size:13px;" onchange="renderOverview()">
                        <option value="">Select Year</option>
                        <?php foreach ($years_options as $yr): ?>
                            <option value="<?php echo $yr; ?>">Year <?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="overview_sem" class="form-control" style="width:auto; padding:6px 12px; font-size:13px;" onchange="renderOverview()">
                        <option value="">Select Sem</option>
                        <?php foreach ($sem_options as $sem): ?>
                            <option value="<?php echo $sem; ?>">Sem <?php echo $sem; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div id="sections-overview-container" style="min-height: 200px;">
                <div class="empty-state" style="padding: 40px 20px;">
                    <i class="fas fa-filter" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <p style="margin:0; font-weight:500; color:#64748b;">Waiting for selection.</p>
                    <p style="margin:5px 0 0; font-size:13px;">Please select a Year and Semester to view sections.</p>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
let allSections = <?php echo json_encode($all_sections); ?>;
let allSubjects = <?php echo json_encode($all_subjects); ?>;
let sectionAllocations = <?php echo json_encode($section_allocations); ?>; 
let selectedSubjects = [];

function filterSectionsAndSubjects() {
    const year = document.getElementById('alloc_year').value;
    const sem = document.getElementById('alloc_sem').value;
    const sectionSelect = document.getElementById('section_id');
    const subjectsContainer = document.getElementById('subjects-container');

    if (!year || !sem) {
        sectionSelect.innerHTML = '<option value="">Select Year and Sem First</option>';
        sectionSelect.disabled = true;
        subjectsContainer.innerHTML = '<div class="empty-state">Select Year and Semester first to load available subjects</div>';
        selectedSubjects = [];
        updateHiddenInput();
        toggleSaveButton();
        return;
    }

    const prevSelected = sectionSelect.value ? String(sectionSelect.value) : null;
    const filteredSections = allSections.filter(s => parseInt(s.year) === parseInt(year) && parseInt(s.semester) === parseInt(sem));
    sectionSelect.innerHTML = '<option value="">Select Section</option>';
    if (filteredSections.length === 0) {
        sectionSelect.innerHTML += '<option value="" disabled>No sections found</option>';
    } else {
        filteredSections.forEach(sec => {
            const option = document.createElement('option');
            option.value = sec.section_id;
            const roomText = sec.room_number ? ` (Room: ${sec.room_number})` : '';
            option.textContent = `Section ${sec.section_name}${roomText}`;
            if (prevSelected && String(sec.section_id) === prevSelected) {
                option.selected = true;
            }
            sectionSelect.appendChild(option);
        });
    }
    sectionSelect.disabled = filteredSections.length === 0;

    const filteredSubjects = allSubjects.filter(sub => parseInt(sub.year) === parseInt(year) && parseInt(sub.semester) === parseInt(sem));
    subjectsContainer.innerHTML = '';
    if (filteredSubjects.length === 0) {
        subjectsContainer.innerHTML = '<div class="empty-state">No subjects found for this year & sem combination.</div>';
    } else {
        const selectedSectionId = sectionSelect.value ? parseInt(sectionSelect.value) : null;
        const allocatedForSection = (selectedSectionId && sectionAllocations[selectedSectionId]) ? sectionAllocations[selectedSectionId] : [];
        selectedSubjects = [];
        filteredSubjects.forEach(sub => {
            const div = document.createElement('div');
            div.className = 'subject-item';
            div.onclick = function(e) {
                if(e.target.tagName !== 'INPUT') {
                    const cb = this.querySelector('input[type="checkbox"]');
                    cb.checked = !cb.checked;
                    handleCheckboxChange(cb);
                }
            };
            const isChecked = allocatedForSection.includes(parseInt(sub.subject_id));
            div.innerHTML = `
                <label for="sub_${sub.subject_id}">
                    <span class="sub-code">${sub.subject_code} &bull; ${sub.weekly_hours} hrs</span>
                    <span>${sub.subject_name}</span>
                </label>
                <input id="sub_${sub.subject_id}" type="checkbox" value="${sub.subject_id}" onchange="handleCheckboxChange(this)" ${isChecked ? 'checked' : ''} onclick="event.stopPropagation()">
            `;
            subjectsContainer.appendChild(div);
            if (isChecked) selectedSubjects.push(parseInt(sub.subject_id));
        });
        updateHiddenInput();
    }
    toggleSaveButton();
}

function handleCheckboxChange(checkbox) {
    const id = parseInt(checkbox.value);
    if (checkbox.checked) {
        if (!selectedSubjects.includes(id)) selectedSubjects.push(id);
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
    // Render initially empty overview
    renderOverview();
});

function renderOverview() {
    const year = document.getElementById('overview_year').value;
    const sem = document.getElementById('overview_sem').value;
    const container = document.getElementById('sections-overview-container');

    if (!year || !sem) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 40px 20px;">
                <i class="fas fa-filter" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                <p style="margin:0; font-weight:500; color:#64748b;">Waiting for selection.</p>
                <p style="margin:5px 0 0; font-size:13px;">Please select a Year and Semester to view sections.</p>
            </div>
        `;
        return;
    }

    const filteredSections = allSections.filter(s => parseInt(s.year) === parseInt(year) && parseInt(s.semester) === parseInt(sem));
    
    if (filteredSections.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 40px 20px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                <p style="margin:0; font-weight:500; color:#64748b;">No sections found.</p>
                <p style="margin:5px 0 0; font-size:13px;">No sections exist for Year ${year}, Sem ${sem}.</p>
            </div>
        `;
        return;
    }

    const subjectMap = {};
    allSubjects.forEach(s => { subjectMap[s.subject_id] = s; });

    let html = '<div class="sections-grid" style="display:flex; flex-direction:column;">';
    
    filteredSections.forEach(sec => {
        let roomText = sec.room_number ? `<span><i class="fas fa-door-open"></i> ${sec.room_number}</span>` : '';
        
        let allocHtml = '';
        const allocated = sectionAllocations[sec.section_id] || [];
        if (allocated.length === 0) {
            allocHtml = '<div class="empty-state" style="padding:10px;"><i class="fas fa-exclamation-triangle" style="color:#fbbf24; margin-right:5px;"></i> No subjects allocated</div>';
        } else {
            allocHtml = '<div class="alloc-list">';
            allocated.forEach(subid => {
                const ss = subjectMap[subid];
                if (ss) {
                    allocHtml += `
                        <div class="alloc-badge">
                            <span style="display:flex; flex-direction:column; gap:2px;"><span class="code">${ss.subject_code}</span><span>${ss.subject_name}</span></span>
                            <span class="hrs">${ss.weekly_hours}h</span>
                        </div>
                    `;
                } else {
                    allocHtml += `<div class="alloc-badge"><span>Unknown Subject ID ${subid}</span></div>`;
                }
            });
            allocHtml += '</div>';
        }

        html += `
            <div class="section-card">
                <div class="section-card-header">
                    <div>
                        <h3 class="sec-name">Section ${sec.section_name}</h3>
                        <div class="sec-info">
                            <span><i class="fas fa-calendar-alt"></i> Yr ${sec.year} / Sem ${sec.semester}</span>
                            ${roomText}
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px; align-items:flex-end;">
                        <span class="sec-badge" style="margin-bottom:5px;">ID: ${sec.section_id}</span>
                        <div style="display:flex; gap:5px;">
                            <a href="?edit_section_id=${sec.section_id}#section-form" style="color: #f59e0b; text-decoration: none; font-size: 13px; border: 1px solid #fcf6cc; background: #fffbeb; padding: 4px 8px; border-radius: 4px;"><i class="fas fa-edit"></i> Edit</a>
                            <a href="?delete_section_id=${sec.section_id}" onclick="return confirm('Delete this section? This will also remove all its subjects and timetables.');" style="color: #ef4444; text-decoration: none; font-size: 13px; border: 1px solid #fee2e2; background: #fef2f2; padding: 4px 8px; border-radius: 4px;"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                </div>
                <div class="alloc-title">Allocated Subjects</div>
                ${allocHtml}
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>