<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include __DIR__ . '/../include/db.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hod') {
    header('Location: login.php');
    exit;
}

$branch_id = 0;
$hq = mysqli_query($conn, "SELECT branch_id FROM hod WHERE user_id = " . intval($_SESSION['user_id']) . " LIMIT 1");
if ($hq && mysqli_num_rows($hq)) { $hr = mysqli_fetch_assoc($hq); $branch_id = intval($hr['branch_id']); }

$filter_year = intval($_GET['year'] ?? 0);
$filter_sem = intval($_GET['semester'] ?? 0);

// fetch sections
$where = "branch_id=".intval($branch_id);
if ($filter_year) $where .= " AND year=".intval($filter_year);
if ($filter_sem) $where .= " AND semester=".intval($filter_sem);
$sections = [];
$sq = mysqli_query($conn, "SELECT section_id, section_name, year, semester FROM sections WHERE $where ORDER BY year, semester, section_name");
if ($sq) while ($r = mysqli_fetch_assoc($sq)) $sections[] = $r;

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function fmt_time($t){ return date('H:i', strtotime($t)); }

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>View Timetables</title>
    <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;margin:20px}
    .box{max-width:1200px;margin:0 auto;background:#fff;padding:18px;border-radius:8px}
    .controls{display:flex;gap:10px;align-items:center;margin-bottom:14px}
    select,input[type=submit]{padding:8px;border-radius:6px;border:1px solid #ccc}
    .section-title{margin-top:18px;padding:10px 12px;background:#2c3e50;color:#fff;border-radius:6px;display:flex;justify-content:space-between;align-items:center}
    .timetable{width:100%;border-collapse:collapse;margin-top:8px}
    .timetable th{background:#34495e;color:#fff;padding:10px;border:1px solid #ccc}
    .timetable td{border:1px solid #ddd;padding:8px;vertical-align:top;height:70px}
    .time-col{background:#ecf0f1;font-weight:700;width:80px}
    .lab{background:#fff3cd}
    .theory{background:#e8f4f8}
    .small{font-size:12px;color:#555}
    .badge{background:#f39c12;color:#fff;padding:4px 8px;border-radius:4px}
    </style>
</head>
<body>
<div class="box">
    <h2>View Timetables for Sections</h2>
    <form method="get" class="controls">
        <label>Year:
            <select name="year" onchange="this.form.submit()">
                <option value="">All</option>
                <?php
                $yrq = mysqli_query($conn, "SELECT DISTINCT year FROM sections WHERE branch_id=".intval($branch_id)." ORDER BY year");
                while ($yr = mysqli_fetch_assoc($yrq)) {
                    $yv = intval($yr['year']);
                    echo '<option value="'.$yv.'"'.($yv===$filter_year? ' selected':'').'>Year '.$yv.'</option>';
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
        <label><input type="checkbox" id="show_all" onclick="location.href='view_timetable.php'"> Show all (clear filters)</label>
    </form>

    <?php if (empty($sections)): ?>
        <p class="small">No sections found for the selected filters.</p>
    <?php endif; ?>

    <?php foreach ($sections as $sec):
        $section_id = intval($sec['section_id']);
        $section_name = $sec['section_name'];
        $coord = 0;
        if (isset($sec['coordinator_id']) && intval($sec['coordinator_id']) > 0) $coord = intval($sec['coordinator_id']);
        $coord_name = '';
        if ($coord) {
            $cr = mysqli_query($conn, "SELECT faculty_name FROM faculty WHERE faculty_id=".intval($coord)." LIMIT 1");
            if ($cr && mysqli_num_rows($cr)) $coord_name = mysqli_fetch_assoc($cr)['faculty_name'];
        }

        // fetch slots for this section
        $tsq = "SELECT ts.*, s.subject_code, s.subject_name, f.faculty_name, r.room_number FROM timetable_slots ts 
                LEFT JOIN subjects s ON ts.subject_id=s.subject_id
                LEFT JOIN faculty f ON ts.faculty_id=f.faculty_id
                LEFT JOIN rooms r ON ts.room_id=r.room_id
                WHERE ts.section_id=".intval($section_id)." 
                ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), ts.start_time";
        $tr = mysqli_query($conn, $tsq);
        $slots = [];
        $times = [];
        while ($row = mysqli_fetch_assoc($tr)) {
            $d = $row['day_of_week'];
            $st = $row['start_time'];
            $times[$st] = true;
            if (!isset($slots[$d])) $slots[$d] = [];
            $slots[$d][$st] = $row;
        }
        // sort times
        $time_rows = array_keys($times);
        sort($time_rows);
    ?>
        <div class="section-area">
            <div class="section-title">
                <div>Section <?php echo htmlspecialchars($section_name); ?> (Year <?php echo intval($sec['year']); ?> - Sem <?php echo intval($sec['semester']); ?>)</div>
                <div><?php if ($coord_name) echo '<span class="badge">Coordinator: '.htmlspecialchars($coord_name).'</span>'; ?></div>
            </div>

            <?php if (empty($time_rows)): ?>
                <p class="small" style="padding:12px">No timetable slots saved for this section.</p>
            <?php else: ?>
                <table class="timetable">
                    <thead>
                        <tr>
                            <th class="time-col">Time</th>
                            <?php foreach ($days as $d) echo '<th>'.htmlspecialchars($d).'</th>'; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_rows as $start): ?>
                            <tr>
                                <td class="time-col"><?php echo fmt_time($start); ?></td>
                                <?php foreach ($days as $d):
                                    $cell = $slots[$d][$start] ?? null;
                                    if (!$cell) { echo '<td class="empty-cell">-</td>'; continue; }
                                    $is_lab = (isset($cell['is_lab']) && $cell['is_lab']);
                                    $cls = $is_lab ? 'lab' : 'theory';
                                    $subject = htmlspecialchars($cell['subject_code'].' - '.$cell['subject_name']);
                                    $faculty = htmlspecialchars($cell['faculty_name'] ?? '');
                                    $room = htmlspecialchars($cell['room_number'] ?? '');
                                    echo "<td class='".$cls."'><div style='font-weight:700;'>$subject</div><div class='small'>". $faculty ."</div><div class='small'>Room: ".$room."</div></td>";
                                endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</div>
</body>
</html>
