<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include __DIR__ . '/../include/db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hod') {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';
$all_timetables = [];
$slot_times = [];

// get branch_id
$branch_id = 0;
$hq = mysqli_query($conn, "SELECT branch_id FROM hod WHERE user_id = " . intval($_SESSION['user_id']) . " LIMIT 1");
if ($hq && mysqli_num_rows($hq)) { $hr = mysqli_fetch_assoc($hq); $branch_id = intval($hr['branch_id']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $start_time = $_POST['start_time'] ?? '09:00';
    $end_time = $_POST['end_time'] ?? '17:00';
    $lunch_start = $_POST['lunch_start'] ?? '13:00';
    $lunch_end = $_POST['lunch_end'] ?? '14:00';
    $requested_lectures = max(1, intval($_POST['num_lectures'] ?? 6));
    $year = intval($_POST['year'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);
    $selected_sections = $_POST['sections'] ?? [];
    $section_coordinators = $_POST['section_coordinator'] ?? [];

    if (empty($selected_sections)) {
        $errors[] = 'Select at least one section.';
    }

    if (empty($errors)) {
        // Optionally create new section
        $new_section_name = trim($_POST['new_section_name'] ?? '');
        $new_section_room = trim($_POST['new_section_room'] ?? '');
        if ($new_section_name !== '') {
            $sname = strtoupper(mysqli_real_escape_string($conn, $new_section_name));
            if (preg_match('/^[A-Z]$/', $sname)) {
                $room_part = 'NULL';
                if ($new_section_room !== '') {
                    $rnum = mysqli_real_escape_string($conn, $new_section_room);
                    $rq = mysqli_query($conn, "SELECT room_id FROM rooms WHERE room_number='$rnum' LIMIT 1");
                    if ($rq && mysqli_num_rows($rq)) { $rr = mysqli_fetch_assoc($rq); $room_part = intval($rr['room_id']); }
                    else { mysqli_query($conn, "INSERT INTO rooms (room_number,capacity) VALUES ('$rnum',0)"); $room_part = mysqli_insert_id($conn); }
                }
                $room_sql = ($room_part === 'NULL') ? 'NULL' : intval($room_part);
                mysqli_query($conn, "INSERT INTO sections (branch_id,year,semester,section_name,room_id) VALUES (".intval($branch_id).",$year,$semester,'$sname',$room_sql)");
                $new_id = mysqli_insert_id($conn);
                if ($new_id) $selected_sections[] = $new_id;
            }
        }

        // Load subjects for selected sections
        $sec_ids = array_map('intval', $selected_sections);
        $sec_list = implode(',', $sec_ids);
        $section_subject_map = [];
        if (!empty($sec_list)) {
            $ssq = "SELECT ss.section_id, ss.subject_id, s.subject_code, s.subject_name, s.weekly_hours, s.type FROM section_subjects ss JOIN subjects s ON ss.subject_id=s.subject_id WHERE ss.section_id IN ($sec_list)";
            $ssr = mysqli_query($conn, $ssq);
            while ($r = mysqli_fetch_assoc($ssr)) {
                $sid = intval($r['section_id']); $subid = intval($r['subject_id']);
                if (!isset($section_subject_map[$sid])) $section_subject_map[$sid] = [];
                $section_subject_map[$sid][$subid] = ['subject_code' => $r['subject_code'], 'subject_name' => $r['subject_name'], 'weekly_hours' => intval($r['weekly_hours']), 'type' => $r['type']];
            }
        }

        // Load faculty with load-based capacity
        $faculty = [];
        $fq = "SELECT f.faculty_id, f.faculty_name, COALESCE(fld.total_load, 18) AS capacity FROM faculty f LEFT JOIN faculty_load_details fld ON f.faculty_id=fld.faculty_id WHERE f.branch_id=" . intval($branch_id);
        $fr = mysqli_query($conn, $fq);
        while ($f = mysqli_fetch_assoc($fr)) {
            $fid = intval($f['faculty_id']);
            $capacity = max(1, intval($f['capacity']));
            $faculty[$fid] = ['name' => $f['faculty_name'], 'subjects' => [], 'capacity' => $capacity, 'assigned_slots' => 0];
        }

        // Faculty subjects mapping
        $sub_ids = [];
        foreach ($section_subject_map as $arr) foreach ($arr as $sid => $info) $sub_ids[] = $sid;
        $sub_ids = array_unique($sub_ids);
        if (!empty($sub_ids)) {
            $sub_list = implode(',', $sub_ids);
            $fss = mysqli_query($conn, "SELECT faculty_id, subject_id FROM faculty_subjects WHERE subject_id IN ($sub_list)");
            while ($r = mysqli_fetch_assoc($fss)) {
                $fid = intval($r['faculty_id']); $s = intval($r['subject_id']);
                if (isset($faculty[$fid])) $faculty[$fid]['subjects'][] = $s;
            }
        }

        // Load rooms
        $rooms = [];
        $rr = mysqli_query($conn, "SELECT room_id, room_number, is_lab FROM rooms ORDER BY room_number");
        while ($r = mysqli_fetch_assoc($rr)) {
            $rooms[intval($r['room_id'])] = ['number' => $r['room_number'], 'is_lab' => ($r['is_lab'] ? 1 : 0)];
        }

        // Prepare slot times (Asia/Kolkata)
        $tz = new DateTimeZone('Asia/Kolkata');
        $slot_duration = 50; // minutes
        $cur = DateTime::createFromFormat('H:i', $start_time, $tz);
        $end_dt = DateTime::createFromFormat('H:i', $end_time, $tz);
        $lunch_start_dt = DateTime::createFromFormat('H:i', $lunch_start, $tz);
        $lunch_end_dt = DateTime::createFromFormat('H:i', $lunch_end, $tz);
        $slot_times = [];
        $idx = 1;
        while (count($slot_times) < $requested_lectures) {
            $sdt = clone $cur;
            $edt = clone $cur;
            $edt->add(new DateInterval('PT' . $slot_duration . 'M'));
            if ($edt > $end_dt) break;
            if ($sdt < $lunch_end_dt && $edt > $lunch_start_dt) { $cur = clone $lunch_end_dt; continue; }
            $slot_times[$idx] = ['start' => $sdt->format('H:i:s'), 'end' => $edt->format('H:i:s'), 'label' => $sdt->format('H:i') . '-' . $edt->format('H:i')];
            $cur = $edt;
            $idx++;
        }
        $num_lectures = count($slot_times);

        // Global conflict trackers
        $faculty_bookings = []; // faculty_id => [day => [slot => true]]
        $room_bookings = [];    // room_id => [day => [slot => true]]
        $section_bookings = []; // section_id => [day => [slot => true]]

        // Delete existing slots for selected sections
        foreach ($sec_ids as $secid) {
            mysqli_query($conn, "DELETE FROM timetable_slots WHERE section_id = " . intval($secid));
        }

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // GENERATE PER SECTION
        foreach ($sec_ids as $section_id) {
            $section_name = '';
            $sr = mysqli_query($conn, "SELECT section_name FROM sections WHERE section_id=" . intval($section_id));
            if ($sr && mysqli_num_rows($sr)) { $section_name = mysqli_fetch_assoc($sr)['section_name']; }

            // Build subject queue based on weekly_hours
            $subject_queue = [];
            if (!empty($section_subject_map[$section_id])) {
                foreach ($section_subject_map[$section_id] as $subid => $sinfo) {
                    $count = max(1, intval($sinfo['weekly_hours']));
                    for ($i = 0; $i < $count; $i++) {
                        $subject_queue[] = ['subject_id' => $subid, 'subject_code' => $sinfo['subject_code'], 'subject_name' => $sinfo['subject_name'], 'type' => $sinfo['type']];
                    }
                }
            }

            if (empty($subject_queue)) {
                $timetable = [];
                foreach ($days as $d) for ($sl = 1; $sl <= $num_lectures; $sl++) $timetable[$d][$sl] = null;
                $all_timetables[$section_id] = ['name' => $section_name, 'timetable' => $timetable];
                continue;
            }

            // Shuffle queue for better distribution
            shuffle($subject_queue);
            $qidx = 0;
            $timetable = [];

            // Assign subjects to slots
            foreach ($days as $day) {
                for ($slot = 1; $slot <= $num_lectures; $slot++) {
                    if ($qidx >= count($subject_queue)) break;

                    $sub = $subject_queue[$qidx];
                    $is_lab = (isset($sub['type']) && strtoupper($sub['type']) === 'P');

                    // Try lab assignment (2 consecutive slots)
                    if ($is_lab && $slot < $num_lectures) {
                        $assigned = false;
                        foreach ($faculty as $fid => &$finfo) {
                            if (in_array($sub['subject_id'], $finfo['subjects'])) {
                                if ($finfo['assigned_slots'] + 2 > $finfo['capacity']) continue;
                                if (isset($faculty_bookings[$fid][$day][$slot]) || isset($faculty_bookings[$fid][$day][$slot + 1])) continue;
                                if (isset($section_bookings[$section_id][$day][$slot]) || isset($section_bookings[$section_id][$day][$slot + 1])) continue;

                                // Find available room
                                foreach ($rooms as $rid => $rinfo) {
                                    if (isset($room_bookings[$rid][$day][$slot]) || isset($room_bookings[$rid][$day][$slot + 1])) continue;

                                    // Assign
                                    $faculty_bookings[$fid][$day][$slot] = true;
                                    $faculty_bookings[$fid][$day][$slot + 1] = true;
                                    $room_bookings[$rid][$day][$slot] = true;
                                    $room_bookings[$rid][$day][$slot + 1] = true;
                                    $section_bookings[$section_id][$day][$slot] = true;
                                    $section_bookings[$section_id][$day][$slot + 1] = true;
                                    $finfo['assigned_slots'] += 2;

                                    $start1 = $slot_times[$slot]['start'];
                                    $end2 = $slot_times[$slot + 1]['end'];
                                    $cell_data = ['subject_id' => $sub['subject_id'], 'subject_code' => $sub['subject_code'], 'subject_name' => $sub['subject_name'], 'faculty_id' => $fid, 'faculty' => $finfo['name'], 'room_id' => $rid, 'room' => $rinfo['number'], 'is_lab' => 1];

                                    // Insert both slots
                                    $ins1 = "INSERT INTO timetable_slots (section_id,subject_id,faculty_id,room_id,day_of_week,start_time,end_time) VALUES (" . intval($section_id) . "," . intval($sub['subject_id']) . "," . intval($fid) . "," . intval($rid) . ",'" . $day . "','" . $start1 . "','" . $slot_times[$slot + 1]['end'] . "')";
                                    if (!mysqli_query($conn, $ins1)) $errors[] = 'Insert error: ' . mysqli_error($conn);

                                    $timetable[$day][$slot] = $cell_data;
                                    $timetable[$day][$slot + 1] = $cell_data;
                                    $slot++; // Skip next slot
                                    $qidx++;
                                    $assigned = true;
                                    break;
                                }
                                if ($assigned) break;
                            }
                        }
                        if ($assigned) continue;
                    }

                    // Single slot assignment
                    $assigned_fid = null;
                    $assigned_rid = null;

                    foreach ($faculty as $fid => &$finfo) {
                        if (!in_array($sub['subject_id'], $finfo['subjects'])) continue;
                        if ($finfo['assigned_slots'] >= $finfo['capacity']) continue;
                        if (isset($faculty_bookings[$fid][$day][$slot])) continue;
                        $assigned_fid = $fid;
                        break;
                    }

                    foreach ($rooms as $rid => $rinfo) {
                        if (isset($room_bookings[$rid][$day][$slot])) continue;
                        $assigned_rid = $rid;
                        break;
                    }

                    if ($assigned_fid !== null && $assigned_rid !== null && !isset($section_bookings[$section_id][$day][$slot])) {
                        $faculty_bookings[$assigned_fid][$day][$slot] = true;
                        $room_bookings[$assigned_rid][$day][$slot] = true;
                        $section_bookings[$section_id][$day][$slot] = true;
                        $faculty[$assigned_fid]['assigned_slots']++;

                        $st = $slot_times[$slot]['start'];
                        $en = $slot_times[$slot]['end'];
                        $cell_data = ['subject_id' => $sub['subject_id'], 'subject_code' => $sub['subject_code'], 'subject_name' => $sub['subject_name'], 'faculty_id' => $assigned_fid, 'faculty' => $faculty[$assigned_fid]['name'], 'room_id' => $assigned_rid, 'room' => $rooms[$assigned_rid]['number'], 'is_lab' => 0];

                        $ins = "INSERT INTO timetable_slots (section_id,subject_id,faculty_id,room_id,day_of_week,start_time,end_time) VALUES (" . intval($section_id) . "," . intval($sub['subject_id']) . "," . intval($assigned_fid) . "," . intval($assigned_rid) . ",'" . $day . "','" . $st . "','" . $en . "')";
                        if (!mysqli_query($conn, $ins)) $errors[] = 'Insert error: ' . mysqli_error($conn);
                        $timetable[$day][$slot] = $cell_data;
                    } else {
                        $timetable[$day][$slot] = null;
                    }

                    $qidx++;
                }
            }

            $all_timetables[$section_id] = ['name' => $section_name, 'timetable' => $timetable];
        }

        if (empty($errors)) $success = 'Timetables generated and saved successfully!';
    }
}

// prepare form options
$years_options = [];
$yrq = mysqli_query($conn, "SELECT DISTINCT year FROM sections WHERE branch_id=".intval($branch_id)." ORDER BY year");
if ($yrq) while ($r = mysqli_fetch_assoc($yrq)) $years_options[] = intval($r['year']);

$all_sections = [];
$asq = mysqli_query($conn, "SELECT section_id, section_name, year, semester FROM sections WHERE branch_id=".intval($branch_id)." ORDER BY year, semester, section_name");
if ($asq) while ($r = mysqli_fetch_assoc($asq)) $all_sections[] = $r;

$coordinators = [];
$cq = mysqli_query($conn, "SELECT faculty_id, faculty_name FROM faculty WHERE branch_id=".intval($branch_id)." AND is_coordinator=1 ORDER BY faculty_name");
if ($cq && mysqli_num_rows($cq)>0) while ($r = mysqli_fetch_assoc($cq)) $coordinators[] = $r;
else { $cq2 = mysqli_query($conn, "SELECT faculty_id,faculty_name FROM faculty WHERE branch_id=".intval($branch_id)." ORDER BY faculty_name"); if ($cq2) while ($r = mysqli_fetch_assoc($cq2)) $coordinators[] = $r; }

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Generate Timetable</title>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; }
    .container { max-width: 1400px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
    .header { margin-bottom: 30px; border-bottom: 3px solid #2c3e50; padding-bottom: 15px; }
    .header h1 { color: #2c3e50; font-size: 28px; }
    .header p { color: #7f8c8d; margin-top: 5px; font-size: 14px; }
    .form-section { background: #f8f9fa; padding: 25px; border-radius: 8px; margin-top: 30px; border: 1px solid #e9ecef; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { font-weight: 600; color: #2c3e50; margin-bottom: 6px; font-size: 13px; }
    .form-group input, .form-group select { padding: 9px; border: 1px solid #bdc3c7; border-radius: 5px; font-size: 13px; transition: border 0.3s; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 5px rgba(52,152,219,0.3); }
    .btn-submit { background: #27ae60; color: #fff; padding: 11px 22px; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.3s; grid-column: 1 / -1; }
    .btn-submit:hover { background: #229954; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; font-size: 14px; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; font-size: 14px; }
    .timetable-section { margin-top: 35px; page-break-after: avoid; }
    .section-header { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); color: #fff; padding: 15px 20px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
    .section-header h2 { font-size: 16px; margin: 0; }
    .coordinator-badge { background: #f39c12; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    .timetable { width: 100%; border-collapse: collapse; border: 2px solid #34495e; margin-bottom: 30px; }
    .timetable th { background: #34495e; color: #fff; padding: 12px; text-align: center; font-weight: 600; font-size: 13px; border: 1px solid #2c3e50; }
    .timetable td { border: 1px solid #bdc3c7; padding: 10px; text-align: center; font-size: 11px; height: 80px; vertical-align: top; }
    .time-slot { background: #ecf0f1; font-weight: 700; width: 70px; min-width: 70px; }
    .empty-cell { background: #fafafa; color: #95a5a6; }
    .class-cell { background: #e8f4f8; border: 2px solid #3498db; }
    .lab-cell { background: #fff3cd !important; border: 2px solid #f39c12 !important; }
    .subject-code { font-weight: 700; color: #2c3e50; display: block; }
    .subject-name { font-size: 10px; color: #34495e; margin: 2px 0; }
    .faculty-name { font-size: 9px; color: #7f8c8d; font-style: italic; }
    .room-name { font-size: 9px; color: #16a085; font-weight: 500; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Timetable Generator</h1>
        <p>Generate timetables based on faculty load capacity with conflict prevention</p>
    </div>

    <?php if (!empty($success)): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="error"><?php foreach($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div><?php endif; ?>

    <?php if (!empty($all_timetables)): ?>
        <?php $days = ['Monday','Tuesday','Wednesday','Thursday','Friday']; ?>
        <?php foreach ($all_timetables as $section_id => $data):
            $section_name = $data['name']; $timetable = $data['timetable'];
            $coord_name = '';
            if (isset($section_coordinators[$section_id]) && intval($section_coordinators[$section_id])>0) {
                $cid = intval($section_coordinators[$section_id]);
                $cr = mysqli_query($conn, "SELECT faculty_name FROM faculty WHERE faculty_id=$cid LIMIT 1"); 
                if ($cr && mysqli_num_rows($cr)) $coord_name = mysqli_fetch_assoc($cr)['faculty_name'];
            }
        ?>
            <div class="timetable-section">
                <div class="section-header">
                    <h2>Section <?php echo htmlspecialchars($section_name); ?></h2>
                    <?php if($coord_name): ?><span class="coordinator-badge">Coordinator: <?php echo htmlspecialchars($coord_name); ?></span><?php endif; ?>
                </div>
                <table class="timetable">
                    <thead>
                        <tr>
                            <th class="time-slot">Time</th>
                            <?php foreach($days as $d) echo '<th>' . htmlspecialchars($d) . '</th>'; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($slot_times as $slot=>$st): ?>
                            <tr>
                                <td class="time-slot"><?php echo htmlspecialchars($st['label']); ?></td>
                                <?php foreach($days as $d): ?>
                                    <td class="<?php echo (empty($timetable[$d][$slot]) ? 'empty-cell' : 'class-cell ' . (isset($timetable[$d][$slot]['is_lab']) && $timetable[$d][$slot]['is_lab'] ? 'lab-cell' : '')); ?>">
                                        <?php if (!empty($timetable[$d][$slot])): $cell = $timetable[$d][$slot]; ?>
                                            <span class="subject-code"><?php echo htmlspecialchars($cell['subject_code'] ?? ''); ?></span>
                                            <span class="subject-name"><?php echo htmlspecialchars(substr($cell['subject_name'] ?? '', 0, 25)); ?></span>
                                            <span class="faculty-name"><?php echo htmlspecialchars(substr($cell['faculty'] ?? '', 0, 15)); ?></span>
                                            <span class="room-name">Rm: <?php echo htmlspecialchars($cell['room'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="form-section">
        <h3 style="margin-bottom: 20px; color: #2c3e50; font-size: 16px;">Generate Timetable</h3>
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" value="09:00" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" value="17:00" required>
                </div>
                <div class="form-group">
                    <label>Lunch Start</label>
                    <input type="time" name="lunch_start" value="13:00" required>
                </div>
                <div class="form-group">
                    <label>Lunch End</label>
                    <input type="time" name="lunch_end" value="14:00" required>
                </div>
                <div class="form-group">
                    <label>Lectures/Day</label>
                    <input type="number" name="num_lectures" min="4" max="10" value="6" required>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select id="year" name="year" required>
                        <option value="">Select Year</option>
                        <?php foreach($years_options as $yr) echo '<option value="'.intval($yr).'">Year '.intval($yr).'</option>'; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select id="semester" name="semester" required disabled>
                        <option value="">Select Year First</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label style="margin-bottom: 10px;">Select Sections</label>
                    <div style="border: 1px solid #bdc3c7; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto; background: #fff;">
                        <div id="section_choice_container"></div>
                        <label style="margin-top: 8px; display: block; border-top: 1px solid #e9ecef; padding-top: 8px;"><input type="checkbox" id="select_all_sections"> Select All</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Add New Section</label>
                    <input type="text" name="new_section_name" placeholder="Single letter (A-Z)" maxlength="1">
                </div>
                <div class="form-group">
                    <label>New Section Room</label>
                    <input type="text" name="new_section_room" placeholder="e.g., R101">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label style="margin-bottom: 10px;">Assign Coordinators (Optional)</label>
                    <div id="coordinator_container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 10px;"></div>
                </div>

                <button type="submit" name="generate_timetable" class="btn-submit">Generate & Save Timetable</button>
            </div>
        </form>
    </div>
</div>

<script>
const allSections = <?php echo json_encode($all_sections); ?>;
const coordinatorsList = <?php echo json_encode($coordinators); ?>;

function populateSections() {
    const year = parseInt(document.getElementById('year').value) || 0;
    const sem = parseInt(document.getElementById('semester').value) || 0;
    const container = document.getElementById('section_choice_container');
    const coordContainer = document.getElementById('coordinator_container');
    container.innerHTML = '';
    coordContainer.innerHTML = '';

    if (!year || !sem) return;

    allSections.forEach(s => {
        if (parseInt(s.year) === year && parseInt(s.semester) === sem) {
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.alignItems = 'center';
            row.style.gap = '8px';
            row.style.marginBottom = '8px';

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'sections[]';
            cb.value = s.section_id;
            cb.className = 'sec-checkbox';
            cb.id = 'sec_' + s.section_id;

            const lbl = document.createElement('label');
            lbl.htmlFor = cb.id;
            lbl.textContent = 'Section ' + s.section_name;
            lbl.style.fontWeight = '600';
            lbl.style.marginBottom = '0';
            lbl.style.cursor = 'pointer';

            row.appendChild(cb);
            row.appendChild(lbl);
            container.appendChild(row);

            // Coordinator select
            const crow = document.createElement('div');
            crow.style.display = 'flex';
            crow.style.alignItems = 'center';
            crow.style.gap = '8px';
            crow.style.padding = '10px';
            crow.style.background = '#f9f9f9';
            crow.style.borderRadius = '5px';

            const lab = document.createElement('label');
            lab.textContent = 'Section ' + s.section_name + ':';
            lab.style.minWidth = '100px';
            lab.style.fontWeight = '500';
            lab.style.marginBottom = '0';
            lab.style.fontSize = '13px';

            const select = document.createElement('select');
            select.name = 'section_coordinator[' + s.section_id + ']';
            select.style.flex = '1';

            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '(None)';
            select.appendChild(empty);

            coordinatorsList.forEach(c => {
                const o = document.createElement('option');
                o.value = c.faculty_id;
                o.textContent = c.faculty_name;
                select.appendChild(o);
            });

            crow.appendChild(lab);
            crow.appendChild(select);
            coordContainer.appendChild(crow);
        }
    });

    const selectAll = document.getElementById('select_all_sections');
    if (selectAll) {
        selectAll.checked = false;
        selectAll.onchange = () => {
            document.querySelectorAll('#section_choice_container input.sec-checkbox').forEach(ch => ch.checked = selectAll.checked);
        };
    }
}

document.getElementById('year').addEventListener('change', function() {
    const semSelect = document.getElementById('semester');
    semSelect.innerHTML = '';
    semSelect.disabled = true;
    const year = parseInt(this.value) || 0;
    if (year > 0) {
        semSelect.disabled = false;
        let semesters = [];
        if (year === 1) semesters = [1, 2];
        else if (year === 2) semesters = [3, 4];
        else if (year === 3) semesters = [5, 6];
        else semesters = [7, 8];
        semesters.forEach(s => {
            const o = document.createElement('option');
            o.value = s;
            o.textContent = 'Semester ' + s;
            semSelect.appendChild(o);
        });
        populateSections();
    }
});

document.getElementById('semester').addEventListener('change', populateSections);
</script>

</body>
</html>
