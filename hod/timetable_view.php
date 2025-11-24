<?php
// session_start();
include '../include/db.php';
include 'header.php';

// Check if user is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit;
}

// Get HOD's branch_id
$query = "SELECT h.branch_id, h.hod_name, b.branch_name FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: login.php');
    exit;
}
$hod_data = mysqli_fetch_assoc($result);
$branch_id = $hod_data['branch_id'];

// Handle form submission for timetable generation
$errors = [];
$success = null;
$all_timetables = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $lunch_start = $_POST['lunch_start'];
    $lunch_end = $_POST['lunch_end'];
    $requested_lectures = (int)$_POST['num_lectures'];
    $year = (int)$_POST['year'];
    $semester = (int)$_POST['semester'];
    $coordinator_name = mysqli_real_escape_string($conn, $_POST['coordinator_name']);
    $coordinator_phone = mysqli_real_escape_string($conn, $_POST['coordinator_phone']);

    // Get all sections for year/sem
    $section_query = "SELECT section_id, section_name FROM sections WHERE branch_id = $branch_id AND year = $year AND semester = $semester";
    $section_result = mysqli_query($conn, $section_query);
    $sections = [];
    if ($section_result) {
        while ($sec = mysqli_fetch_assoc($section_result)) {
            $sections[] = $sec;
        }
    }

    if (empty($sections)) {
        $errors[] = 'No sections found for selected year and semester.';
    } else {
        // Fetch subjects for branch/year/sem
        $subjects_query = "SELECT subject_id, subject_code, subject_name, weekly_hours FROM subjects WHERE branch_id = $branch_id AND year = $year AND semester = $semester AND weekly_hours > 0 ORDER BY subject_name";
        $subjects_result = mysqli_query($conn, $subjects_query);
        $subjects = [];
        while ($sub = mysqli_fetch_assoc($subjects_result)) {
            $subjects[] = $sub;
        }

        // Create subject pool based on weekly hours
        $subject_pool = [];
        foreach ($subjects as $sub) {
            for ($i = 0; $i < (int)$sub['weekly_hours']; $i++) {
                $subject_pool[] = $sub;
            }
        }
        shuffle($subject_pool); // Random order for variety

        // Fetch faculty and their subjects
        $faculty_query = "SELECT faculty_id, faculty_name FROM faculty WHERE branch_id = $branch_id ORDER BY faculty_name";
        $faculty_result = mysqli_query($conn, $faculty_query);
        $faculty = [];
        while ($fac = mysqli_fetch_assoc($faculty_result)) {
            $fac_id = $fac['faculty_id'];
            $faculty[$fac_id] = ['name' => $fac['faculty_name'], 'subjects' => []];
            $sub_q = "SELECT subject_id FROM faculty_subjects WHERE faculty_id = $fac_id";
            $sub_r = mysqli_query($conn, $sub_q);
            while ($s = mysqli_fetch_assoc($sub_r)) {
                $faculty[$fac_id]['subjects'][] = $s['subject_id'];
            }
        }

        // Days
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // Calculate slot times (50 min each, skip lunch, respect end time)
        $slot_duration = 50; // minutes
        $current_time = new DateTime($start_time);
        $slot_times = [];
        $slot_num = 1;
        $end_dt = new DateTime($end_time);
        $lunch_start_dt = new DateTime($lunch_start);
        $lunch_end_dt = new DateTime($lunch_end);
        while (count($slot_times) < $requested_lectures) {
            $slot_start = clone $current_time;
            $slot_end = clone $current_time;
            $slot_end->add(new DateInterval('PT' . $slot_duration . 'M'));
            
            if ($slot_end > $end_dt) {
                break;
            }
            
            // Skip if during lunch
            if ($slot_start < $lunch_end_dt && $slot_end > $lunch_start_dt) {
                $current_time = clone $lunch_end_dt; // Jump to after lunch
                continue;
            }
            
            $slot_times[$slot_num] = $slot_start->format('H:i') . '-' . $slot_end->format('H:i');
            $current_time = $slot_end;
            $slot_num++;
        }

        $num_lectures = count($slot_times);
        if ($num_lectures < $requested_lectures) {
            $errors[] = "Only $num_lectures lecture slots could be scheduled within the given time frame (requested: $requested_lectures).";
        }

        // Initialize faculty availability
        $faculty_availability = [];
        foreach ($days as $day) {
            $faculty_availability[$day] = array_fill(1, $num_lectures, []);
        }

        // Generate timetable for each section
        foreach ($sections as $sec) {
            $section_id = $sec['section_id'];
            $section_name = $sec['section_name'];
            $timetable = [];
            $slot_index = 0;

            foreach ($days as $day) {
                $timetable[$day] = [];
                for ($slot = 1; $slot <= $num_lectures; $slot++) {
                    if ($slot_index < count($subject_pool)) {
                        $subject = $subject_pool[$slot_index];
                        // Find available faculty for this subject
                        $available_fac = null;
                        foreach ($faculty as $fac_id => $fac_data) {
                            if (in_array($subject['subject_id'], $fac_data['subjects'])) {
                                if (!isset($faculty_availability[$day][$slot][$fac_id]) || empty($faculty_availability[$day][$slot][$fac_id])) {
                                    $available_fac = $fac_data['name'];
                                    $faculty_availability[$day][$slot][$fac_id] = true; // Mark as booked
                                    break;
                                }
                            }
                        }
                        if ($available_fac) {
                            $timetable[$day][$slot] = [
                                'subject_code' => $subject['subject_code'],
                                'subject_name' => $subject['subject_name'],
                                'faculty' => $available_fac
                            ];
                        } else {
                            $timetable[$day][$slot] = ['subject_code' => '-', 'subject_name' => '', 'faculty' => ''];
                        }
                        $slot_index++;
                    } else {
                        $timetable[$day][$slot] = ['subject_code' => '-', 'subject_name' => '', 'faculty' => ''];
                    }
                }
            }
            $all_timetables[$section_name] = $timetable;
        }

        $success = "Timetable generated for Year $year, Semester $semester with $num_lectures lectures per day. Sections: " . implode(', ', array_column($sections, 'section_name')) . ". Coordinator: $coordinator_name ($coordinator_phone)";
    }
}

// Fetch possible years for form
$years_query = "SELECT DISTINCT year FROM sections WHERE branch_id = $branch_id ORDER BY year";
$years_result = mysqli_query($conn, $years_query);
$years_options = [];
while ($yr = mysqli_fetch_assoc($years_result)) {
    $years_options[] = $yr['year'];
}
?>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: #f3f4f6;
    margin: 0;
    padding: 0;
}
.dashboard-container {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}
.text-center-container {
    text-align: center;
    margin-bottom: 2rem;
}
.title {
    font-size: 2.25rem;
    font-weight: 700;
    color: #1f2937;
}
.subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.5rem;
}
.section-container {
    margin-top: 2rem;
    background-color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
}
.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
    margin-bottom: 0.25rem;
}
.input-field {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    box-sizing: border-box;
}
.button {
    padding: 0.75rem 1.5rem;
    background-color: #2563eb;
    color: #fff;
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;
    font-weight: 600;
}
.button:hover {
    background-color: #1d4ed8;
}
.timetable-display {
    margin-top: 2rem;
}
.timetable-section {
    margin-bottom: 3rem;
}
.timetable-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}
.timetable-table th, .timetable-table td {
    border: 1px solid #d1d5db;
    padding: 0.5rem;
    text-align: left;
}
.timetable-table th {
    background-color: #e5e7eb;
}
.time-slot {
    background-color: #f9fafb;
    font-weight: bold;
}
.lunch-slot {
    background-color: #fef3c7;
    text-align: center;
    font-style: italic;
}
.success-message {
    background-color: #d1fae5;
    color: #065f46;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}
.error-message {
    background-color: #fee2e2;
    color: #dc2626;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.getElementById('year');
    const semesterSelect = document.getElementById('semester');

    yearSelect.addEventListener('change', function() {
        const year = parseInt(this.value);
        semesterSelect.innerHTML = '<option value="">Select Semester</option>';
        semesterSelect.disabled = true;
        if (year > 0) {
            semesterSelect.disabled = false;
            let semesters = [];
            if (year === 1) {
                semesters = [1, 2];
            } else if (year === 2) {
                semesters = [3, 4];
            } else if (year === 3) {
                semesters = [5, 6];
            } else if (year === 4) {
                semesters = [7, 8];
            }
            semesters.forEach(sem => {
                const option = document.createElement('option');
                option.value = sem;
                option.textContent = sem;
                semesterSelect.appendChild(option);
            });
        }
    });
});
</script>
<div class="dashboard-container">
    <div class="text-center-container">
        <h2 class="title">Generate Timetable</h2>
        <p class="subtitle">Create automatic timetable for all sections in selected year/semester.</p>
    </div>

    <?php if (isset($success)): ?>
        <?php 
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $lunch_start_dt = new DateTime($lunch_start);
        $lunch_end_dt = new DateTime($lunch_end);
        ?>
        <div class="success-message">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php foreach ($all_timetables as $section_name => $timetable): ?>
            <div class="timetable-section">
                <h3>Timetable for Section <?php echo htmlspecialchars($section_name); ?></h3>
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Time Slot</th>
                            <?php foreach ($days as $day): ?>
                                <th><?php echo htmlspecialchars($day); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $prev_end = null;
                        $inserted = false;
                        foreach ($slot_times as $slot => $time_range): 
                            list($slot_start_str, $slot_end_str) = explode('-', $time_range);
                            $slot_start_dt_temp = DateTime::createFromFormat('H:i', $slot_start_str);
                            $slot_end_dt_temp = DateTime::createFromFormat('H:i', $slot_end_str);
                            if ($prev_end && $prev_end < $lunch_start_dt && $slot_start_dt_temp > $lunch_end_dt && !$inserted): 
                        ?>
                            <tr class="lunch-slot">
                                <td><?php echo htmlspecialchars($lunch_start . '-' . $lunch_end); ?> (Lunch Break)</td>
                                <?php foreach ($days as $day): ?>
                                    <td>Lunch Break</td>
                                <?php endforeach; ?>
                            </tr>
                            <?php 
                                $inserted = true;
                            endif; 
                            $prev_end = clone $slot_end_dt_temp;
                        ?>
                        <tr class="time-slot">
                            <td><?php echo htmlspecialchars($time_range); ?></td>
                            <?php foreach ($days as $day): ?>
                                <td>
                                    <?php if (isset($timetable[$day][$slot]) && $timetable[$day][$slot]['subject_code'] !== '-'): ?>
                                        <strong><?php echo htmlspecialchars($timetable[$day][$slot]['subject_code']); ?></strong><br>
                                        <?php echo htmlspecialchars($timetable[$day][$slot]['subject_name']); ?><br>
                                        <small><?php echo htmlspecialchars($timetable[$day][$slot]['faculty']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; 
                        if (!$inserted && $lunch_start && $lunch_end): ?>
                        <tr class="lunch-slot">
                            <td><?php echo htmlspecialchars($lunch_start . '-' . $lunch_end); ?> (Lunch Break)</td>
                            <?php foreach ($days as $day): ?>
                                <td>Lunch Break</td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="section-container">
        <h3 class="section-title">Timetable Generation Form</h3>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="start_time">College Start Time:</label>
                    <input type="time" id="start_time" name="start_time" class="input-field" value="09:00" required>
                </div>
                <div class="form-group">
                    <label for="end_time">College End Time:</label>
                    <input type="time" id="end_time" name="end_time" class="input-field" value="17:00" required>
                </div>
                <div class="form-group">
                    <label for="lunch_start">Lunch Start Time:</label>
                    <input type="time" id="lunch_start" name="lunch_start" class="input-field" value="13:00" required>
                </div>
                <div class="form-group">
                    <label for="lunch_end">Lunch End Time:</label>
                    <input type="time" id="lunch_end" name="lunch_end" class="input-field" value="14:00" required>
                </div>
                <div class="form-group">
                    <label for="num_lectures">Lectures per Day (excluding lunch):</label>
                    <input type="number" id="num_lectures" name="num_lectures" class="input-field" min="4" max="8" value="6" required>
                </div>
                <div class="form-group">
                    <label for="year">Year:</label>
                    <select id="year" name="year" class="input-field" required>
                        <option value="">Select Year</option>
                        <?php foreach ($years_options as $yr): ?>
                            <option value="<?php echo $yr; ?>"><?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="semester">Semester:</label>
                    <select id="semester" name="semester" class="input-field" required disabled>
                        <option value="">Select Year First</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="coordinator_name">Coordinator Name:</label>
                    <input type="text" id="coordinator_name" name="coordinator_name" class="input-field" required>
                </div>
                <div class="form-group">
                    <label for="coordinator_phone">Coordinator Phone:</label>
                    <input type="tel" id="coordinator_phone" name="coordinator_phone" class="input-field" required>
                </div>
            </div>
            <button type="submit" name="generate_timetable" class="button">Generate Timetable for All Sections</button>
        </form>
    </section>
</div>
<?php include 'footer.php'; ?>