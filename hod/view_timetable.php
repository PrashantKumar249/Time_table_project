<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include __DIR__ . '/../include/db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hod') {
    header('Location: login.php');
    exit;
}

// Helper for initials
function get_initials($name) {
    if (!$name) return '';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
    }
    return $initials;
}

function fmt_time_ampm($t){ 
    return date('g:i A', strtotime($t)); 
}

// Fetch HOD Detail
$branch_id = 0;
$college_name = "College Name";
$address = "";
$college_logo = "";
$branch_name = "DEPARTMENT";

$hq = mysqli_query($conn, "SELECT hod.branch_id, hod.college_name, hod.address, hod.college_logo, b.branch_name 
                           FROM hod 
                           LEFT JOIN branches b ON hod.branch_id = b.branch_id
                           WHERE hod.user_id = " . intval($_SESSION['user_id']) . " LIMIT 1");
if ($hq && mysqli_num_rows($hq)) { 
    $hr = mysqli_fetch_assoc($hq); 
    $branch_id = intval($hr['branch_id']); 
    if(!empty($hr['college_name'])) $college_name = $hr['college_name'];
    if(!empty($hr['address'])) $address = $hr['address'];
    if(!empty($hr['college_logo'])) $college_logo = $hr['college_logo'];
    if(!empty($hr['branch_name'])) $branch_name = $hr['branch_name'];
}

$filter_year = intval($_GET['year'] ?? 0);
$filter_sem = intval($_GET['semester'] ?? 0);
$filter_sec = isset($_GET['section']) ? mysqli_real_escape_string($conn, trim($_GET['section'])) : '';
$show_all = isset($_GET['show_all']) ? 1 : 0;

// Fetch sections
$sections = [];
if ($filter_year > 0 || $filter_sem > 0 || $filter_sec !== '' || $show_all) {
    $where = "s.branch_id=".intval($branch_id);
    if ($filter_year) $where .= " AND s.year=".intval($filter_year);
    if ($filter_sem) $where .= " AND s.semester=".intval($filter_sem);
    if ($filter_sec !== '') $where .= " AND s.section_name='$filter_sec'";
    
    $sq = mysqli_query($conn, "SELECT s.section_id, s.section_name, s.year, s.semester, r.room_number 
                               FROM sections s 
                               LEFT JOIN rooms r ON s.room_id = r.room_id
                               WHERE $where ORDER BY s.year, s.semester, s.section_name");
    if ($sq) while ($r = mysqli_fetch_assoc($sq)) $sections[] = $r;
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>View Timetables</title>
    <style>
    body { background: #f4f6f8; margin: 0; padding: 0; }
    .page-container { padding: 30px 20px; min-height: calc(100vh - 80px); }
    .controls-box { max-width: 1200px; margin: 0 auto 20px auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .controls { display: flex; gap: 15px; align-items: center; background: #f8fafc; padding: 15px 20px; border-radius: 8px; border: 1px solid #e2e8f0; flex-wrap: wrap; margin-bottom: 0; }
    .controls label { font-weight: 600; color: #475569; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    select, input[type=submit] { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }
    
    /* PDF Format Styles */
    .tt-wrapper { width: 100%; max-width: 1200px; margin: 0 auto 40px auto; background: #fff; padding: 30px; font-family: 'Times New Roman', Times, serif; color: #000; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .tt-header { text-align: center; margin-bottom: 15px; position: relative; border: 2px solid #000; padding: 15px; border-radius: 10px; }
    .tt-logo { position: absolute; left: 20px; top: 10px; max-height: 80px; max-width: 100px; }
    .tt-college-name { font-size: 26px; font-weight: bold; margin: 0; }
    .tt-college-address { font-size: 13px; margin: 5px 0 0 0; font-weight: bold; }
    
    .tt-dept { text-align: center; font-size: 20px; font-weight: bold; font-style: italic; margin-bottom: 5px; text-decoration: underline; text-transform: uppercase; }
    .tt-title { text-align: center; font-size: 18px; margin-bottom: 15px; font-weight: bold; }
    
    .tt-info-row { display: flex; justify-content: center; gap: 40px; font-size: 16px; font-weight: bold; margin-bottom: 10px; flex-wrap: wrap; }
    
    .tt-main-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; text-align: center; font-size: 14px; border: 2px solid #000; }
    .tt-main-table th, .tt-main-table td { border: 1px solid #000; padding: 8px 5px; vertical-align: middle; }
    .tt-main-table th { font-weight: bold; background-color: #fcfcfc; }
    .tt-main-table .day-col { font-weight: bold; width: 100px; }
    
    .tt-bottom-container { display: flex; justify-content: space-between; gap: 20px; align-items: stretch; }
    .tt-sub-table-container { flex: 1; }
    .tt-sub-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: center; border: 2px solid #000; }
    .tt-sub-table th, .tt-sub-table td { border: 1px solid #000; padding: 6px; }
    .tt-sub-table th { font-weight: bold; }
    
    .tt-coord-box { width: 250px; border: 4px double #000; padding: 20px; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; font-weight: bold; text-align: center; font-size: 15px; }
    
    /* Print override */
    @media print {
        @page { size: A4 landscape; margin: 8mm; }
        body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-family: 'Times New Roman', Times, serif; }
        header, footer, .controls-box { display: none !important; }
        .page-container { padding: 0; min-height: auto; }
        .tt-wrapper { box-shadow: none; margin: 0 auto; padding: 0; max-width: 100%; width: 100%; page-break-inside: avoid; page-break-after: always; }
        .tt-wrapper:last-child { page-break-after: avoid; }
        
        /* Scale down elements to fit perfectly on 1 portrait or landscape A4 page */
        .tt-main-table { font-size: 12px; margin-bottom: 15px; }
        .tt-main-table th, .tt-main-table td { padding: 5px 3px; }
        .tt-sub-table { font-size: 11px; }
        .tt-sub-table th, .tt-sub-table td { padding: 4px; }
        .tt-info-row { font-size: 14px; gap: 20px; margin-bottom: 8px; }
        .tt-title { margin-bottom: 8px; font-size: 16px; }
        .tt-college-name { font-size: 22px; }
        .tt-dept { font-size: 18px; }
        .tt-coord-box { padding: 10px; width: 200px; font-size: 13px; }
    }
    </style>
</head>
<body>
<div class="page-container">

    <div class="controls-box">
        <h2 style="margin-top:0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 24px;">View Timetables</h2>
        <form method="get" class="controls">
            <label>Year:
                <select name="year" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php
                    $yrq = mysqli_query($conn, "SELECT DISTINCT year FROM sections WHERE branch_id=".intval($branch_id)." ORDER BY year");
                    if($yrq) {
                        while ($yr = mysqli_fetch_assoc($yrq)) {
                            $yv = intval($yr['year']);
                            echo '<option value="'.$yv.'"'.($yv===$filter_year? ' selected':'').'>Year '.$yv.'</option>';
                        }
                    }
                    ?>
                </select>
            </label>
            <label>Semester:
                <select name="semester" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php for ($s=1;$s<=8;$s++){ echo '<option value="'.$s.'"'.($s===$filter_sem? ' selected':'').'>Sem '.$s.'</option>'; } ?>
                </select>
            </label>
            <label>Section:
                <select name="section" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php
                    $secq = mysqli_query($conn, "SELECT DISTINCT section_name FROM sections WHERE branch_id=".intval($branch_id)." ORDER BY section_name");
                    if($secq) {
                        while ($sec = mysqli_fetch_assoc($secq)) {
                            $sv = htmlspecialchars($sec['section_name']);
                            echo '<option value="'.$sv.'"'.($sv===$filter_sec? ' selected':'').'>Sec '.$sv.'</option>';
                        }
                    }
                    ?>
                </select>
            </label>
            <label><input type="checkbox" id="show_all" <?php echo $show_all ? 'checked' : ''; ?> onclick="if(this.checked){location.href='view_timetable.php?show_all=1';}else{location.href='view_timetable.php';}"> Show all timetables (Slow)</label>
            <button type="button" onclick="window.print()" style="margin-left:auto; padding:8px 15px; background:#0f172a; color:#fff; border:none; border-radius:6px; cursor:pointer;">Print Timetable</button>
        </form>
    </div>

    <?php if (empty($sections)): ?>
        <div style="text-align:center; padding:50px; background:#fff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.05); max-width:1200px; margin:0 auto;">
            <svg style="width: 64px; height: 64px; color: #cbd5e1; margin-bottom: 15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            <h3 style="margin:0; font-family:'Segoe UI',sans-serif; color:#475569;">Timetables hidden to save load time</h3>
            <p style="color:#64748b; font-family:'Segoe UI',sans-serif;">Please select a Year and Semester from the filters above, or check "Show all timetables" to view.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($sections as $sec):
        $section_id = intval($sec['section_id']);
        $section_name = $sec['section_name'];
        $sec_year = intval($sec['year']);
        $sec_sem = intval($sec['semester']);
        $room = htmlspecialchars($sec['room_number'] ?? 'N/A');
        
        $coord_name = '';
        $cr = mysqli_query($conn, "SELECT faculty_name FROM faculty WHERE branch_id=".intval($branch_id)." AND is_coordinator=1 LIMIT 1");
        if ($cr && mysqli_num_rows($cr)) $coord_name = mysqli_fetch_assoc($cr)['faculty_name'];

        // fetch slots for this section
        $tsq = "SELECT ts.*, s.subject_code, s.subject_name, s.type as subject_type, f.faculty_name, r.room_number as slot_room_number 
                FROM timetable_slots ts 
                LEFT JOIN subjects s ON ts.subject_id=s.subject_id
                LEFT JOIN faculty f ON ts.faculty_id=f.faculty_id
                LEFT JOIN rooms r ON ts.room_id=r.room_id
                WHERE ts.section_id=".intval($section_id)." 
                ORDER BY ts.start_time";
        $tr = mysqli_query($conn, $tsq);
        
        $slots = [];
        $time_points = [];
        $subject_stats = []; // For the bottom table
        
        while ($row = mysqli_fetch_assoc($tr)) {
            $d = $row['day_of_week'];
            if (!isset($slots[$d])) $slots[$d] = [];
            $slots[$d][] = $row;
            
            $time_points[$row['start_time']] = true;
            $time_points[$row['end_time']] = true;
            
            // Statistics for bottom table
            $sid = $row['subject_id'];
            if (!isset($subject_stats[$sid])) {
                $subject_stats[$sid] = [
                    'name' => $row['subject_name'] ?? 'Unknown',
                    'code' => $row['subject_code'] ?? '-',
                    'type' => $row['subject_type'] ?? 'T',
                    'faculty' => $row['faculty_name'] ?? '-',
                    'faculty_code' => get_initials($row['faculty_name']),
                    'location' => $row['slot_room_number'] ?? 'ROOM NO:'.$room
                ];
                $subject_stats[$sid]['lectures'] = 0;
            }
            $subject_stats[$sid]['lectures']++;
        }
        
        $tp = array_keys($time_points);
        sort($tp);
        $time_cols = [];
        for ($i = 0; $i < count($tp) - 1; $i++) {
            $time_cols[] = [
                'start' => $tp[$i], 
                'end' => $tp[$i+1], 
                'label' => fmt_time_ampm($tp[$i]) . " -\n" . fmt_time_ampm($tp[$i+1])
            ];
        }

        // Determine if there's a lunch/gap column
        // A column is a break ONLY if no slot STARTS within that time window
        $is_break_col = [];
        foreach ($time_cols as $idx => $col) {
            $has_slot = false;
            foreach ($days as $d) {
                foreach ($slots[$d] ?? [] as $s) {
                    $s_start = strtotime($s['start_time']);
                    $c_start = strtotime($col['start']);
                    $c_end   = strtotime($col['end']);
                    // Slot counts for this column only if it STARTS within the column window
                    if ($s_start >= $c_start && $s_start < $c_end) {
                        $has_slot = true; break;
                    }
                }
                if ($has_slot) break;
            }
            $is_break_col[$idx] = !$has_slot;
        }
        
        // Semester Text
        $sem_text = ($sec_sem % 2 !== 0) ? '(ODD SEMESTER)' : '(EVEN SEMESTER)';
        
    ?>
        <div class="tt-wrapper">
            <div class="tt-header">
                <?php if ($college_logo): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($college_logo); ?>" alt="Logo" class="tt-logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <h1 class="tt-college-name"><?php echo htmlspecialchars($college_name); ?></h1>
                <p class="tt-college-address">(<?php echo htmlspecialchars($address); ?>)</p>
            </div>
            
            <div class="tt-dept">DEPARTMENT OF <?php echo htmlspecialchars($branch_name); ?></div>
            <div class="tt-title">CLASS/ROOM TIME TABLE: 2025-26 <?php echo $sem_text; ?></div>
            
            <div class="tt-info-row">
                <span>B.Tech CSE</span>
                <span>Year : <?php echo $sec_year; ?></span>
                <span>SEC-<?php echo htmlspecialchars($section_name); ?></span>
                <span>Class Room : <?php echo $room; ?></span>
                <span>UPDATED ON <?php echo date('d/m/Y'); ?></span>
            </div>
            
            <?php if (empty($time_cols)): ?>
                <div style="text-align:center; padding:30px; border:1px solid #ccc;">No timetable slots mapped yet.</div>
            <?php else: ?>
            
                <table class="tt-main-table">
                    <thead>
                        <tr>
                            <th>Day/Time</th>
                            <?php foreach ($time_cols as $col): ?>
                                <th><?php echo nl2br(htmlspecialchars($col['label'])); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day_index => $d): ?>
                            <tr>
                                <td class="day-col"><?php echo $d; ?></td>
                                <?php 
                                $skip_cols = 0;
                                foreach ($time_cols as $idx => $col): 
                                    if ($skip_cols > 0) {
                                        $skip_cols--;
                                        continue;
                                    }
                                    if ($is_break_col[$idx]) {
                                        if ($day_index === 0) {
                                            echo '<td rowspan="6" style="font-weight:bold; font-size:16px; letter-spacing:4px; text-align:center; vertical-align:middle; writing-mode:vertical-rl; text-orientation:mixed; transform:rotate(180deg); white-space:nowrap;">LUNCH</td>';
                                        }
                                    } else {
                                        $cell = null;
                                        foreach ($slots[$d] ?? [] as $s) {
                                            $s_start = strtotime($s['start_time']);
                                            $c_start = strtotime($col['start']);
                                            $c_end   = strtotime($col['end']);
                                            // Match only if slot STARTS in this column (not just overlaps)
                                            if ($s_start >= $c_start && $s_start < $c_end) {
                                                $cell = $s; break;
                                            }
                                        }
                                        if ($cell) {
                                            // Colspan calculation for labs or double blocks
                                            $colspan = 1;
                                            for ($j = $idx + 1; $j < count($time_cols); $j++) {
                                                if ($is_break_col[$j]) break;
                                                if (strtotime($cell['start_time']) < strtotime($time_cols[$j]['end']) && strtotime($cell['end_time']) > strtotime($time_cols[$j]['start'])) {
                                                    $colspan++;
                                                } else {
                                                    break;
                                                }
                                            }
                                            $skip_cols = $colspan - 1;
                                            
                                            $fac_code = get_initials($cell['faculty_name'] ?? '');
                                            $subj_code = htmlspecialchars($cell['subject_code'] ?? '');
                                            $type = $cell['subject_type'] ?? 'T';
                                            
                                            if ($type === 'P' || stripos(strtolower($cell['subject_name']), 'lab') !== false) {
                                                // format like: DS-LAB B1 2A LAB-2 3rd FLOOR
                                                $txt = htmlspecialchars($cell['subject_name'] ?? $subj_code) . '<br>';
                                                $txt .= '<span style="font-size:11px;">[' . htmlspecialchars($cell['slot_room_number'] ?? $room) . ']</span>';
                                                echo '<td '.($colspan>1?'colspan="'.$colspan.'"':'').' style="font-weight:bold;">' . $txt . '</td>';
                                            } else {
                                                echo '<td '.($colspan>1?'colspan="'.$colspan.'"':'').'><strong>' . $subj_code . '</strong><br>[' . $fac_code . ']</td>';
                                            }
                                        } else {
                                            echo '<td></td>';
                                        }
                                    }
                                endforeach; 
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="tt-bottom-container">
                    <div class="tt-sub-table-container">
                        <table class="tt-sub-table">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>No. of lectures</th>
                                    <th>Subject Code</th>
                                    <th>Type</th>
                                    <th>Faculty Name</th>
                                    <th>Faculty Code</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($subject_stats)): ?>
                                    <tr><td colspan="7">No subjects.</td></tr>
                                <?php else: foreach ($subject_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                        <td><?php echo $stat['lectures']; ?></td>
                                        <td><?php echo htmlspecialchars($stat['code']); ?></td>
                                        <td><?php echo htmlspecialchars($stat['type']); ?></td>
                                        <td><?php echo htmlspecialchars($stat['faculty']); ?></td>
                                        <td><?php echo htmlspecialchars($stat['faculty_code']); ?></td>
                                        <td><?php echo htmlspecialchars($stat['location']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="tt-coord-box">
                        <div>CLASS-COORDINATOR</div>
                        <div style="margin-top:10px; font-weight:normal;">
                            <?php 
                            if ($coord_name) {
                                echo 'DR. ' . htmlspecialchars($coord_name);
                            } else {
                                echo 'NOT ASSIGNED';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</div>

<?php include 'footer.php'; ?>
</body>
</html>