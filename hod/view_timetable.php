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
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
    .page-container { padding: 30px 20px; min-height: calc(100vh - 80px); }
    .box { max-width: 1300px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .controls { display: flex; gap: 15px; align-items: center; margin-bottom: 25px; background: #f8fafc; padding: 15px 20px; border-radius: 8px; border: 1px solid #e2e8f0; flex-wrap: wrap; }
    .controls label { font-weight: 600; color: #475569; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    select, input[type=submit] { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; transition: border-color 0.2s; }
    select:focus { border-color: #3b82f6; }
    .section-title { margin-top: 30px; padding: 15px 20px; background: #1e293b; color: #fff; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 600; }
    .timetable { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; background: #fff; box-shadow: 0 0 0 1px #e2e8f0; border-radius: 0 0 8px 8px; overflow: hidden; }
    .timetable th { background: #f1f5f9; color: #334155; padding: 12px; border: 1px solid #e2e8f0; text-transform: uppercase; font-size: 13px; font-weight: 700; border-top: none; }
    .timetable td { border: 1px solid #e2e8f0; padding: 12px; vertical-align: top; height: 90px; transition: background 0.2s; }
    .timetable td:hover { background: #f8fafc; }
    .time-col { background: #f8fafc; font-weight: 700; width: 110px; color: #475569; text-align: center; vertical-align: middle !important; }
    .lab { background: #fffbeb; border-left: 4px solid #f59e0b; }
    .theory { background: #f0fdf4; border-left: 4px solid #22c55e; }
    .empty-cell { background: #fafafa; color: #94a3b8; text-align: center; vertical-align: middle !important; }
    .small { font-size: 12px; color: #64748b; margin-top: 5px; }
    .subject-title { font-weight: 700; color: #1e293b; font-size: 14px; margin-bottom: 4px; }
    .badge { background: #f59e0b; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; }
    h2 { color: #0f172a; margin-top: 0; margin-bottom: 20px; font-size: 24px; font-weight: 700; }
    </style>
</head>
<body>
<div class="page-container">
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
        $time_points = []; // collect all start and end times
        while ($row = mysqli_fetch_assoc($tr)) {
            $d = $row['day_of_week'];
            if (!isset($slots[$d])) $slots[$d] = [];
            $slots[$d][] = $row;
            // collect time points
            $time_points[$row['start_time']] = true;
            $time_points[$row['end_time']] = true;
        }
        // build sorted time points and intervals
        $tp = array_keys($time_points);
        sort($tp);
        $time_rows = [];
        for ($i = 0; $i < count($tp) - 1; $i++) {
            $time_rows[] = ['start' => $tp[$i], 'end' => $tp[$i+1], 'label' => fmt_time($tp[$i]) . '-' . fmt_time($tp[$i+1])];
        }
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
                        <?php foreach ($time_rows as $interval): ?>
                            <tr>
                                <td class="time-col"><?php echo htmlspecialchars($interval['label']); ?></td>
                                <?php foreach ($days as $d):
                                    $cell = null;
                                    if (!empty($slots[$d])) {
                                        foreach ($slots[$d] as $r) {
                                            // overlap check: row.start < interval.end AND row.end > interval.start
                                            if (strtotime($r['start_time']) < strtotime($interval['end']) && strtotime($r['end_time']) > strtotime($interval['start'])) {
                                                $cell = $r; break;
                                            }
                                        }
                                    }
                                    if (!$cell) { echo '<td class="empty-cell">-</td>'; continue; }
                                    $is_lab = (isset($cell['is_lab']) && $cell['is_lab']);
                                    $cls = $is_lab ? 'lab' : 'theory';
                                    $subject = htmlspecialchars(($cell['subject_code'] ?? '') . ' - ' . ($cell['subject_name'] ?? ''));
                                    $faculty = htmlspecialchars($cell['faculty_name'] ?? '');
                                    $room = htmlspecialchars($cell['room_number'] ?? '');
                                    echo "<td class='".$cls."'><div class='subject-title'>$subject</div><div class='small'>". $faculty ."</div><div class='small'><b>Room:</b> ".$room."</div></td>";
                                endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>