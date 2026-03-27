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
$psq = "SELECT f.faculty_id, sec.section_id, sec.section_name, sec.year, s.subject_code, COALESCE(s.subject_name, '') AS subject_name, s.type,
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
    margin-bottom: 0.5rem;
}
.subtitle {
    font-size: 1rem;
    color: #6b7280;
    margin-top: 0;
}
.flex-controls {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}
.search-box {
    position: relative;
    max-width: 400px;
    width: 100%;
}
.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}
.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}
.export-buttons {
    display: flex;
    gap: 0.75rem;
}
.export-btn, .print-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.export-btn {
    background-color: #2563eb;
    color: white;
}
.export-btn:hover {
    background-color: #1d4ed8;
}
.print-btn {
    background-color: #ffffff;
    color: #374151;
    border: 1px solid #d1d5db;
}
.print-btn:hover {
    background-color: #f3f4f6;
}
.section-container {
    background-color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    margin-bottom: 2rem;
    overflow-x: auto;
}
.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}
.load-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
    min-width: 1000px;
}
.load-table th,
.load-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    white-space: nowrap;
}
.load-table thead th {
    background-color: #f8fafc;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}
.load-table tbody tr:hover {
    background-color: #f8fafc;
}
.load-table tbody tr:last-child td {
    border-bottom: none;
}
.faculty-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    overflow: hidden;
}
.faculty-card-header {
    background-color: #f8fafc;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.faculty-info h4 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #0f172a;
}
.faculty-info p {
    margin: 0.25rem 0 0 0;
    font-size: 0.875rem;
    color: #64748b;
}
.faculty-stats {
    display: flex;
    gap: 2rem;
    text-align: right;
}
.stat-item {
    display: flex;
    flex-direction: column;
}
.stat-value {
    font-weight: 700;
    color: #0f172a;
    font-size: 1.125rem;
}
.stat-label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.125rem;
}
.faculty-card-body {
    padding: 0;
    overflow-x: auto;
}
.no-data {
    padding: 3rem;
    text-align: center;
    color: #64748b;
    background: #fff;
    border-radius: 0.75rem;
    border: 1px dashed #cbd5e1;
}
.badge {
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge-theory {
    background-color: #eff6ff;
    color: #1d4ed8;
}
.badge-practical {
    background-color: #f0fdf4;
    color: #15803d;
}
@media print {
    body { background-color: #fff; }
    .export-buttons, .search-box, .navbar, .sidebar, footer { display: none !important; }
    .dashboard-container { padding: 0; margin: 0; max-width: 100%; }
    .faculty-card { break-inside: avoid; border: 1px solid #ccc; box-shadow: none; margin-bottom: 1rem; }
    .section-container { box-shadow: none; border: 1px solid #ccc; }
footer {
    background-color: #1f2937;
    color: #d1d6e0;
    text-align: center;
    padding: 2rem 0;
    margin-top: 4rem;
}
.footer-links {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.footer-links a { color: #d1d6e0; text-decoration: none; }
.footer-links a:hover { color: #fff; }
.footer-bottom { border-top: 1px solid #374151; padding-top: 1rem; font-size: 0.875rem; }
@media (max-width: 768px) {
    .flex-controls { flex-direction: column; align-items: stretch; }
    .search-box { max-width: none; }
    .export-buttons { justify-content: flex-start; }
    .faculty-card-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .faculty-stats { width: 100%; justify-content: space-between; text-align: left; gap: 0.5rem; }
}
</style>

<div class="dashboard-container">
    <div class="text-center-container">
        <h2 class="title">Teaching Load Dashboard</h2>
        <p class="subtitle"><?php echo htmlspecialchars($hod_data['branch_name'] ?? ''); ?> Department</p>
    </div>

    <div class="flex-controls" style="justify-content: center; margin-bottom: 2rem;">
        <div class="search-box" style="max-width: 600px;">
            <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by faculty name, subject code, or subject name..." style="padding: 1rem 1rem 1rem 3rem; font-size: 1rem; border-radius: 0.75rem;">
        </div>
    </div>

    <section class="section-container" id="summarySection">
        <h3 class="section-title">Department Faculty Load Summary</h3>
        <?php if (empty($faculty_summary)): ?>
            <div class="no-data">No faculty load summary available for this department.</div>
        <?php else: ?>
            <table class="load-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Faculty Name</th>
                        <th>Designation</th>
                        <th>Total L</th>
                        <th>Total P</th>
                        <th>Total Load</th>
                        <th>Section Assignments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($faculty_summary as $fs): ?>
                        <tr class="summary-row" data-faculty="<?php echo htmlspecialchars(strtolower($fs['faculty_name'] ?? '')); ?>">
                            <td><?php echo $idx++; ?></td>
                            <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($fs['faculty_name'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($fs['desig'] ?? ''); ?></td>
                            <td><?php echo isset($fs['total_l']) ? htmlspecialchars($fs['total_l']) : '-'; ?></td>
                            <td><?php echo isset($fs['total_p']) ? htmlspecialchars($fs['total_p']) : '-'; ?></td>
                            <td><strong style="color: #2563eb;"><?php echo isset($fs['total_load']) ? htmlspecialchars($fs['total_load']) : '-'; ?></strong></td>
                            <td style="white-space: normal; line-height: 1.6;">
                                <?php
                                $fid = intval($fs['faculty_id'] ?? 0);
                                if (!empty($timetable_grouped[$fid])) {
                                    $parts = [];
                                    foreach ($timetable_grouped[$fid] as $b) {
                                        $sec = htmlspecialchars($b['section_name'] ?? '');
                                        $yr = intval($b['year'] ?? 0);
                                        $type = htmlspecialchars($b['type'] ?: '-');
                                        $sub = htmlspecialchars($b['subject_code'] ?: '-');
                                        $slots_equiv = round((intval($b['minutes'] ?? 0)) / 50, 2);
                                        $badgeClass = $type === 'T' ? 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;' : 'background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;';
                                        $parts[] = "<div style='display:inline-flex; align-items:center; margin: 2px; padding: 2px 6px; border-radius: 6px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.8rem;'><span style='$badgeClass border-radius: 4px; padding: 1px 4px; margin-right: 6px; font-weight: 600;'>Y$yr $sec</span><strong style='color:#334155; margin-right: 4px;'>$sub</strong> <span style='color:#64748b; font-size: 0.75rem;'>($type, L:$slots_equiv)</span></div>";
                                    }
                                    echo implode(' ', $parts);
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
    </section>

    <div class="text-center-container" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem;">
        <h3 class="section-title" style="margin-bottom: 0; border: none; padding-bottom: 0;">Detailed Teaching Load Format</h3>
        <div class="export-buttons" style="margin-bottom: 0;">
            <a href="?export=excel" class="export-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:0.5rem"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Export Excel
            </a>
            <button onclick="window.print()" class="print-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:0.5rem"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Print PDF
            </button>
        </div>
    </div>

    <div class="section-container" style="padding: 0; overflow-x: auto; background: #fff;">
        <table class="excel-table" id="excelTable">
            <thead>
                <tr>
                    <th colspan="13" class="college-header">
                        <div class="header-line1"><?php echo htmlspecialchars(strtoupper($hod_data['college_name'] ?? 'AMBALIKA INSTITUTE OF MANAGEMENT & TECHNOLOGY')); ?></div>
                        <div class="header-line2">TEACHING LOAD DEPARTMENT - <?php echo htmlspecialchars(strtoupper($hod_data['branch_name'] ?? '')); ?></div>
                        <div class="header-line3">SESSION 2025-26 ODD SEM</div>
                    </th>
                </tr>
                <tr class="col-headers">
                    <th width="4%">S.No.</th>
                    <th width="15%">NAME OF FACULTY</th>
                    <th width="8%">DESIG.</th>
                    <th width="4%">YEAR</th>
                    <th width="4%">SECTION</th>
                    <th width="6%">BRANCH</th>
                    <th width="8%">SUB. CODE</th>
                    <th width="26%">THEORY / LAB</th>
                    <th width="3%">L</th>
                    <th width="3%">P</th>
                    <th width="5%">Theory Load</th>
                    <th width="5%">Lab Load</th>
                    <th width="6%">TOTAL LOAD</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($timetable_grouped)): ?>
                    <tr><td colspan="13" class="no-data">No detailed teaching load data available.</td></tr>
                <?php else: ?>
                    <?php 
                    $sno = 1; 
                    foreach ($faculty_summary as $fs): 
                        $fid = intval($fs['faculty_id'] ?? 0);
                        if (empty($timetable_grouped[$fid])) continue;
                        
                        $rows = $timetable_grouped[$fid];
                        $rowspan = count($rows);
                        $fname = htmlspecialchars($fs['faculty_name'] ?? '');
                        $desig = htmlspecialchars($fs['desig'] ?? '');
                        
                        $total_theory_load = isset($fs['total_l']) ? floatval($fs['total_l']) : 0;
                        $total_lab_load = isset($fs['total_p']) ? floatval($fs['total_p']) : 0;
                        $grand_total = isset($fs['total_load']) ? floatval($fs['total_load']) : 0;
                        
                        $first = true;
                        foreach ($rows as $idx_row => $r):
                            $yr = intval($r['year'] ?? 0);
                            $sec = htmlspecialchars($r['section_name'] ?? '');
                            $branch = htmlspecialchars($hod_data['branch_name'] ?? ''); 
                            $sub = htmlspecialchars($r['subject_code'] ?: '-');
                            $sub_name = htmlspecialchars($r['subject_name'] ?? '-'); 
                            $type = htmlspecialchars($r['type'] ?: '-');
                            
                            $l_val = $type === 'T' ? intval($r['theory_slots'] ?? 0) : 0;
                            $p_val = $type === 'P' ? intval($r['lab_slots'] ?? 0) : 0;
                            
                            $display_name = $sub_name;
                            if($type === 'P' && stripos($display_name, 'lab') === false) {
                                $display_name .= ' LAB';
                            }
                    ?>
                        <tr class="faculty-group-row" data-faculty="<?php echo htmlspecialchars(strtolower($fname)); ?>" data-subcode="<?php echo htmlspecialchars(strtolower($sub)); ?>" data-subname="<?php echo htmlspecialchars(strtolower($display_name)); ?>" data-first="<?php echo $first ? 'true' : 'false'; ?>" data-fid="<?php echo $fid; ?>">
                            <?php if($first): ?>
                                <td rowspan="<?php echo $rowspan; ?>" class="center-text"><?php echo $sno++; ?></td>
                                <td rowspan="<?php echo $rowspan; ?>"><b><?php echo $fname; ?></b></td>
                                <td rowspan="<?php echo $rowspan; ?>"><?php echo $desig; ?></td>
                            <?php endif; ?>
                            
                            <td class="center-text"><?php echo $yr > 0 ? $yr : '-'; ?></td>
                            <td class="center-text"><?php echo $sec; ?></td>
                            <td class="center-text"><?php echo $branch; ?></td>
                            <td><?php echo $sub; ?></td>
                            <td><?php echo $display_name; ?></td>
                            <td class="center-text"><b><?php echo $l_val > 0 ? $l_val : '0'; ?></b></td>
                            <td class="center-text"><b><?php echo $p_val > 0 ? $p_val : '0'; ?></b></td>
                            
                            <?php if($first): ?>
                                <td rowspan="<?php echo $rowspan; ?>" class="center-text total-col"><?php echo $total_theory_load > 0 ? $total_theory_load : '0'; ?></td>
                                <td rowspan="<?php echo $rowspan; ?>" class="center-text total-col"><?php echo $total_lab_load > 0 ? $total_lab_load : '0'; ?></td>
                                <td rowspan="<?php echo $rowspan; ?>" class="center-text grand-total-col"><b><?php echo $grand_total > 0 ? $grand_total : '0'; ?></b></td>
                            <?php $first = false; endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div id="noResultsMsg" class="no-data" style="display: none; padding: 2rem;">
            No records match your search criteria.
        </div>
    </div>
</div>

<style>
.excel-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background: #fff;
    border: 2px solid #000;
}
.excel-table th, .excel-table td {
    border: 1px solid #000;
    padding: 0.4rem 0.5rem;
    vertical-align: middle;
}
.excel-table thead th {
    color: #000;
}
.college-header {
    text-align: center;
    padding: 1.5rem !important;
    background-color: #fff !important;
    border-bottom: 2px solid #000 !important;
}
.header-line1 {
    font-size: 1.25rem;
    font-weight: 700;
    text-decoration: underline;
}
.header-line2 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-top: 0.3rem;
    text-decoration: underline;
}
.header-line3 {
    font-size: 0.95rem;
    font-weight: 700;
    margin-top: 0.3rem;
}
.col-headers th {
    text-align: center;
    font-weight: 700;
    font-size: 0.8rem;
    background-color: #fff !important;
    text-transform: uppercase;
    border-bottom: 2px solid #000 !important;
}
.center-text {
    text-align: center;
}
.total-col {
    background-color: #fff;
}
.grand-total-col {
    background-color: #fff;
}
.excel-table tbody tr:hover td {
    background-color: #f8fafc;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('excelTable');
    const tbody = table ? table.querySelector('tbody') : null;
    const rows = tbody ? tbody.querySelectorAll('.faculty-group-row') : [];
    const noResultsMsg = document.getElementById('noResultsMsg');
    const summaryRows = document.querySelectorAll('.summary-row');
    const summarySection = document.getElementById('summarySection');
    
    if(searchInput && table) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            // First, filter summary
            let visibleSummaryRows = 0;
            summaryRows.forEach(row => {
                const facultyName = row.getAttribute('data-faculty') || '';
                const textContent = row.textContent.toLowerCase();
                
                if (searchTerm === '' || textContent.includes(searchTerm)) {
                    row.style.display = '';
                    visibleSummaryRows++;
                } else {
                    row.style.display = 'none';
                }
            });
            if (visibleSummaryRows === 0 && searchTerm !== '') {
                summarySection.style.display = 'none';
            } else {
                summarySection.style.display = 'block';
            }

            // Next, filter the excel table rows
            if (searchTerm === '') {
                rows.forEach(r => r.style.display = '');
                noResultsMsg.style.display = 'none';
                table.style.display = '';
                return;
            }
            
            let visibleDataRows = 0;
            
            // Group rows by faculty ID mapping
            let currentGroup = [];
            let currentFid = null;
            
            let allGroups = [];
            
            rows.forEach((row, index) => {
                const fid = row.getAttribute('data-fid');
                if(fid !== currentFid) {
                    if(currentGroup.length > 0) allGroups.push(currentGroup);
                    currentGroup = [];
                    currentFid = fid;
                }
                currentGroup.push(row);
                
                if (index === rows.length - 1) {
                    allGroups.push(currentGroup);
                }
            });
            
            // Apply filtering per group
            allGroups.forEach(groupRows => {
                let facultyMatched = groupRows[0].getAttribute('data-faculty').includes(searchTerm);
                let anySubjectMatched = false;
                
                groupRows.forEach(r => {
                    const subCode = r.getAttribute('data-subcode') || '';
                    const subName = r.getAttribute('data-subname') || '';
                    if (subCode.includes(searchTerm) || subName.includes(searchTerm)) {
                        anySubjectMatched = true;
                    }
                });
                
                if (facultyMatched || anySubjectMatched) {
                    // Show entire group to maintain rowspan layout correctly
                    groupRows.forEach(r => {
                        r.style.display = '';
                        visibleDataRows++;
                    });
                } else {
                    groupRows.forEach(r => r.style.display = 'none');
                }
            });
            
            if (visibleDataRows === 0) {
                table.style.display = 'none';
                noResultsMsg.style.display = 'block';
            } else {
                table.style.display = '';
                noResultsMsg.style.display = 'none';
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>