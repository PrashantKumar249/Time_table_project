<?php
// session_start();
include '../include/db.php';
include 'header.php';

// Check if user is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit;
}

$query = "SELECT h.branch_id, h.hod_name, h.college_name, h.address, h.college_logo, b.branch_name FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header('Location: login.php');
    exit;
}
$hod_data = mysqli_fetch_assoc($result);
$branch_id = $hod_data['branch_id'];

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $export_query = "
        SELECT 
            f.faculty_name,
            'Asst. Prof.' AS desig,
            fld.year,
            fld.section,
            fld.branch,
            s.subject_code,
            fld.theory_lab,
            fld.l_hours AS l,
            fld.p_hours AS p,
            fld.theory_load,
            fld.lab_load,
            fld.total_load
        FROM faculty_load_details fld
        JOIN faculty f ON fld.faculty_id = f.faculty_id
        LEFT JOIN subjects s ON fld.subject_id = s.subject_id
        WHERE f.branch_id = $branch_id
        ORDER BY f.faculty_name, fld.year, fld.section
    ";
    $export_result = mysqli_query($conn, $export_query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teaching_load_' . $branch_id . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Output BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['S.No.', 'NAME OF FACULTY', 'DESIG.', 'YEAR', 'SECTION', 'BRANCH', 'SUB. CODE', 'THEORY / LAB', 'L', 'P', 'Theory Load', 'Lab Load', 'TOTAL LOAD']);
    
    $current_faculty = '';
    $sno = 1;
    while ($row = mysqli_fetch_assoc($export_result)) {
        if ($row['faculty_name'] !== $current_faculty) {
            $current_faculty = $row['faculty_name'];
            $sno = 1;
        }
        fputcsv($output, [
            $sno++,
            $row['faculty_name'],
            $row['desig'],
            $row['year'],
            $row['section'],
            $row['branch'],
            $row['subject_code'],
            $row['theory_lab'],
            $row['l'],
            $row['p'],
            $row['theory_load'],
            $row['lab_load'],
            $row['total_load']
        ]);
    }
    
    fclose($output);
    exit;
}

// Fetch load data
$query = "
    SELECT 
        f.faculty_name,
        'Asst. Prof.' AS desig,
        fld.year,
        fld.section,
        fld.branch,
        s.subject_code,
        fld.theory_lab,
        fld.l_hours AS l,
        fld.p_hours AS p,
        fld.theory_load,
        fld.lab_load,
        fld.total_load
    FROM faculty_load_details fld
    JOIN faculty f ON fld.faculty_id = f.faculty_id
    LEFT JOIN subjects s ON fld.subject_id = s.subject_id
    WHERE f.branch_id = $branch_id
    ORDER BY f.faculty_name, fld.year, fld.section
";
$result = mysqli_query($conn, $query);

$load_data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $load_data[] = $row;
    }
}

// Group data for rowspan
$grouped_data = [];
$current_faculty = '';
foreach ($load_data as $row) {
    if ($row['faculty_name'] !== $current_faculty) {
        $current_faculty = $row['faculty_name'];
        $grouped_data[$current_faculty] = ['rows' => [], 'rowspan' => 0];
    }
    $grouped_data[$current_faculty]['rows'][] = $row;
    $grouped_data[$current_faculty]['rowspan']++;
}
// Build summary per faculty: total L, total P, total load, years and sections
$summary_query = "
    SELECT f.faculty_id, f.faculty_name, 'Asst. Prof.' AS desig,
        COALESCE(SUM(fld.l_hours),0) AS total_l,
        COALESCE(SUM(fld.p_hours),0) AS total_p,
        COALESCE(SUM(fld.total_load),0) AS total_load,
        GROUP_CONCAT(DISTINCT fld.year ORDER BY fld.year SEPARATOR ', ') AS years,
        GROUP_CONCAT(DISTINCT fld.section ORDER BY fld.section SEPARATOR ', ') AS sections
    FROM faculty f
    LEFT JOIN faculty_load_details fld ON f.faculty_id = fld.faculty_id
    WHERE f.branch_id = $branch_id
    GROUP BY f.faculty_id
    ORDER BY f.faculty_name
";
$summary_res = mysqli_query($conn, $summary_query);
$faculty_summary = [];
if ($summary_res) {
    while ($r = mysqli_fetch_assoc($summary_res)) {
        $faculty_summary[] = $r;
    }
}
// -- Instead of using planned load details, compute actual loads from generated timetable slots
// Aggregate per-faculty minutes and slots from timetable_slots
$timetable_summary = [];
$tsq = "SELECT f.faculty_id, f.faculty_name,
    COALESCE(SUM(CASE WHEN s.type='T' THEN TIME_TO_SEC(TIMEDIFF(ts.end_time,ts.start_time))/60 ELSE 0 END),0) AS theory_minutes,
    COALESCE(SUM(CASE WHEN s.type='P' THEN TIME_TO_SEC(TIMEDIFF(ts.end_time,ts.start_time))/60 ELSE 0 END),0) AS lab_minutes,
    COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(ts.end_time,ts.start_time))/60),0) AS total_minutes,
    COALESCE(SUM(CASE WHEN s.type='T' THEN 1 ELSE 0 END),0) AS theory_slots,
    COALESCE(SUM(CASE WHEN s.type='P' THEN 1 ELSE 0 END),0) AS lab_slots
    FROM timetable_slots ts
    JOIN faculty f ON ts.faculty_id=f.faculty_id
    LEFT JOIN subjects s ON ts.subject_id=s.subject_id
    WHERE f.branch_id = " . intval($branch_id) . "
    GROUP BY f.faculty_id
    ORDER BY f.faculty_name";
$tsr = mysqli_query($conn, $tsq);
if ($tsr) {
    while ($trr = mysqli_fetch_assoc($tsr)) {
        // Convert minutes into 50-minute-slot-equivalents for display (approximate)
        $theory_slots_est = round($trr['theory_minutes'] / 50, 2);
        $lab_slots_est = round($trr['lab_minutes'] / 50, 2);
        $total_slots_est = round($trr['total_minutes'] / 50, 2);
        $timetable_summary[] = [
            'faculty_id' => $trr['faculty_id'],
            'faculty_name' => $trr['faculty_name'],
            'desig' => 'Asst. Prof.',
            'total_l' => $theory_slots_est,
            'total_p' => $lab_slots_est,
            'total_load' => $total_slots_est,
            'theory_minutes' => $trr['theory_minutes'],
            'lab_minutes' => $trr['lab_minutes'],
            'total_minutes' => $trr['total_minutes'],
            'theory_slots' => intval($trr['theory_slots']),
            'lab_slots' => intval($trr['lab_slots'])
        ];
    }
}

// Per-faculty per-section breakdown from timetable
$timetable_grouped = [];
$psq = "SELECT f.faculty_id, sec.section_id, sec.section_name, sec.year, s.subject_code, s.type,
    COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(ts.end_time,ts.start_time))/60),0) AS minutes,
    COALESCE(SUM(CASE WHEN s.type='T' THEN 1 ELSE 0 END),0) AS theory_slots,
    COALESCE(SUM(CASE WHEN s.type='P' THEN 1 ELSE 0 END),0) AS lab_slots
    FROM timetable_slots ts
    JOIN faculty f ON ts.faculty_id=f.faculty_id
    JOIN sections sec ON ts.section_id=sec.section_id
    LEFT JOIN subjects s ON ts.subject_id=s.subject_id
    WHERE f.branch_id = " . intval($branch_id) . "
    GROUP BY f.faculty_id, sec.section_id, s.subject_id
    ORDER BY f.faculty_id, sec.year, sec.section_name";
$psr = mysqli_query($conn, $psq);
if ($psr) {
    while ($pr = mysqli_fetch_assoc($psr)) {
        $fid = intval($pr['faculty_id']);
        if (!isset($timetable_grouped[$fid])) $timetable_grouped[$fid] = [];
        $timetable_grouped[$fid][] = $pr;
    }
}

// Use timetable summary (if available) as primary summary
if (!empty($timetable_summary)) {
    $faculty_summary = $timetable_summary;
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
    max-width: 1400px;
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
.export-buttons {
    text-align: right;
    margin-bottom: 1rem;
}
.export-btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    background-color: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 0.375rem;
    margin-left: 0.5rem;
    transition: background-color 0.2s;
}
.export-btn:hover {
    background-color: #1d4ed8;
}
.print-btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    background-color: #6b7280;
    color: white;
    text-decoration: none;
    border-radius: 0.375rem;
    margin-left: 0.5rem;
    transition: background-color 0.2s;
    cursor: pointer;
}
.print-btn:hover {
    background-color: #4b5563;
}
.section-container {
    margin-top: 2rem;
    background-color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    overflow-x: auto;
}
.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
}
.load-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    min-width: 1200px;
}
.load-table th,
.load-table td {
    border: 1px solid #d1d5db;
    padding: 0.5rem;
    text-align: left;
    vertical-align: top;
    white-space: pre-wrap;
}
.load-table thead th {
    background-color: #e5e7eb;
    font-weight: 600;
    color: #374151;
}
.load-table tbody tr:nth-child(even) {
    background-color: #f9fafb;
}
.no-data-message {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
    font-size: 1rem;
}
.header-row {
    background-color: #f3f4f6;
    font-weight: bold;
    text-align: center;
}
@media print {
    body * {
        visibility: hidden;
    }
    .section-container, .load-table {
        visibility: visible;
    }
    .export-buttons {
        display: none;
    }
}
footer {
    background-color: #1f2937;
    color: #d1d6e0;
    text-align: center;
    padding: 2rem 0;
    margin-top: 4rem;
}
.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}
.footer-links {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.footer-links a {
    color: #d1d6e0;
    text-decoration: none;
    transition: color 0.2s ease;
}
.footer-links a:hover {
    color: #fff;
}
.footer-bottom {
    border-top: 1px solid #374151;
    padding-top: 1rem;
    font-size: 0.875rem;
}
@media (max-width: 768px) {
    .load-table {
        font-size: 0.75rem;
    }
    .load-table th,
    .load-table td {
        padding: 0.3rem;
    }
}
</style>
<div class="dashboard-container">
    <div class="text-center-container">
        <h2 class="title">Teaching Load</h2>
        <p class="subtitle"><?php echo htmlspecialchars($hod_data['branch_name']); ?></p>
    </div>

    <section class="section-container">
        <div class="export-buttons">
            <a href="?export=excel" class="export-btn">Export to Excel</a>
            <button onclick="window.print()" class="print-btn">Print/PDF</button>
        </div>
            <h3 class="section-title">FACULTY LOAD SUMMARY</h3>
            <?php if (empty($faculty_summary)): ?>
                <div class="no-data-message">No faculty load summary available.</div>
            <?php else: ?>
                <table class="load-table" style="min-width:800px;margin-bottom:1.25rem">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Desig.</th>
                            <th>Total L</th>
                            <th>Total P</th>
                            <th>Total Load</th>
                            <th>Years</th>
                            <th>Sections</th>
                            <th>Section Loads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($faculty_summary as $fs): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td><?php echo htmlspecialchars($fs['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($fs['desig']); ?></td>
                                <td><?php echo isset($fs['total_l']) ? htmlspecialchars($fs['total_l']) : '-'; ?></td>
                                <td><?php echo isset($fs['total_p']) ? htmlspecialchars($fs['total_p']) : '-'; ?></td>
                                <td><?php echo isset($fs['total_load']) ? htmlspecialchars($fs['total_load']) : '-'; ?></td>
                                <td><?php echo isset($fs['years']) ? htmlspecialchars($fs['years']) : '-'; ?></td>
                                <td><?php echo isset($fs['sections']) ? htmlspecialchars($fs['sections']) : '-'; ?></td>
                                <td>
                                    <?php
                                    $fid = intval($fs['faculty_id']);
                                    if (!empty($timetable_grouped[$fid])) {
                                        $parts = [];
                                        foreach ($timetable_grouped[$fid] as $b) {
                                            $sec = htmlspecialchars($b['section_name']);
                                            $yr = intval($b['year']);
                                            $type = htmlspecialchars($b['type'] ?: '-');
                                            $sub = htmlspecialchars($b['subject_code'] ?: '-');
                                            $mins = intval($b['minutes']);
                                            $theory_slots = intval($b['theory_slots']);
                                            $lab_slots = intval($b['lab_slots']);
                                            $slots_equiv = round($mins / 50, 2);
                                            $parts[] = "Y$yr S$sec ($sub, $type, Slots:$slots_equiv)";
                                        }
                                        echo implode('; ', $parts);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <h3 class="section-title">LOAD CHART</h3>
        <?php if (empty($timetable_grouped) && empty($grouped_data)): ?>
            <div class="no-data-message">No teaching load data available.</div>
        <?php else: ?>
            <?php foreach ($faculty_summary as $fs): ?>
                <?php $fid = intval($fs['faculty_id']); $fname = $fs['faculty_name']; ?>
                <div style="margin-bottom:1.5rem; padding:1rem; border:1px solid #e5e7eb; border-radius:8px; background:#ffffff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <div>
                            <div style="font-weight:700;font-size:1.05rem;"><?php echo htmlspecialchars($fname); ?></div>
                            <div style="color:#6b7280;font-size:0.9rem;"><?php echo htmlspecialchars($fs['desig']); ?></div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-weight:700;color:#111827">Total L: <?php echo isset($fs['total_l']) ? htmlspecialchars($fs['total_l']) : 0; ?> &nbsp;|&nbsp; Total P: <?php echo isset($fs['total_p']) ? htmlspecialchars($fs['total_p']) : 0; ?></div>
                            <div style="color:#6b7280">Total Load: <?php echo isset($fs['total_load']) ? htmlspecialchars($fs['total_load']) : 0; ?></div>
                        </div>
                    </div>

                    <table class="load-table" style="margin-top:8px;">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Section</th>
                                <th>Branch</th>
                                <th>Subject Code</th>
                                <th>Theory / Lab</th>
                                <th>L</th>
                                <th>P</th>
                                <th>Theory Load</th>
                                <th>Lab Load</th>
                                <th>Total Load</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($timetable_grouped[$fid])): ?>
                                <?php foreach ($timetable_grouped[$fid] as $r): ?>
                                    <?php
                                        $yr = intval($r['year']);
                                        $sec = htmlspecialchars($r['section_name']);
                                        $branch_val = '-';
                                        $sub = htmlspecialchars($r['subject_code'] ?: '-');
                                        $type = htmlspecialchars($r['type'] ?: '-');
                                        $mins = intval($r['minutes']);
                                        $l_slots = intval($r['theory_slots']);
                                        $p_slots = intval($r['lab_slots']);
                                        $theory_load = $type === 'T' ? round($mins / 50, 2) : 0;
                                        $lab_load = $type === 'P' ? round($mins / 50, 2) : 0;
                                        $total_load = round($mins / 50, 2);
                                    ?>
                                    <tr>
                                        <td><?php echo $yr; ?></td>
                                        <td><?php echo $sec; ?></td>
                                        <td><?php echo $branch_val; ?></td>
                                        <td><?php echo $sub; ?></td>
                                        <td><?php echo $type; ?></td>
                                        <td><?php echo $l_slots; ?></td>
                                        <td><?php echo $p_slots; ?></td>
                                        <td><?php echo $theory_load; ?></td>
                                        <td><?php echo $lab_load; ?></td>
                                        <td><?php echo $total_load; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="no-data-message">No detailed load entries for this faculty.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?php include 'footer.php'; ?>