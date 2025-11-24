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
        <p class="subtitle">SESSION 2025-26 ODD SEM - <?php echo htmlspecialchars($hod_data['branch_name']); ?></p>
    </div>

    <section class="section-container">
        <div class="export-buttons">
            <a href="?export=excel" class="export-btn">Export to Excel</a>
            <button onclick="window.print()" class="print-btn">Print/PDF</button>
        </div>
        <h3 class="section-title">LOAD CHART</h3>
        <?php if (empty($load_data)): ?>
            <div class="no-data-message">
                No teaching load data available.
            </div>
        <?php else: ?>
            <table class="load-table">
                <thead>
                    <tr class="header-row">
                        <td colspan="13" style="text-align: center; font-size: 1.2em;">AMBALIKA INSTITUTE OF MANAGEMENT & TECHNOLOGY</td>
                    </tr>
                    <tr class="header-row">
                        <td colspan="13" style="text-align: center;">TEACHING LOAD DEPARTMENT - <?php echo strtoupper($hod_data['branch_name']); ?></td>
                    </tr>
                    <tr class="header-row">
                        <td colspan="13" style="text-align: center;">SESSION 2025-26 ODD SEM</td>
                    </tr>
                    <tr>
                        <th>S.No.</th>
                        <th>NAME OF FACULTY</th>
                        <th>DESIG.</th>
                        <th>YEAR</th>
                        <th>SECTION</th>
                        <th>BRANCH</th>
                        <th>SUB. CODE</th>
                        <th>THEORY / LAB</th>
                        <th>L</th>
                        <th>P</th>
                        <th>Theory Load</th>
                        <th>Lab Load</th>
                        <th>TOTAL LOAD</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $faculty_sno = 1; ?>
                    <?php foreach ($grouped_data as $faculty_name => $group): ?>
                        <?php $rows = $group['rows']; ?>
                        <?php $rowspan = $group['rowspan']; ?>
                        <?php $local_sno = 1; ?>
                        <?php for ($i = 0; $i < count($rows); $i++): ?>
                            <?php $row = $rows[$i]; ?>
                            <?php if ($i == 0): ?>
                                <tr>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo $local_sno++; ?></td>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                    <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($row['desig']); ?></td>
                                    <td><?php echo $row['year']; ?></td>
                                    <td><?php echo htmlspecialchars($row['section']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['theory_lab']); ?></td>
                                    <td><?php echo $row['l']; ?></td>
                                    <td><?php echo $row['p']; ?></td>
                                    <td><?php echo $row['theory_load']; ?></td>
                                    <td><?php echo $row['lab_load']; ?></td>
                                    <td><?php echo $row['total_load']; ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td><?php echo $local_sno++; ?></td>
                                    <td></td>
                                    <td></td>
                                    <td><?php echo $row['year']; ?></td>
                                    <td><?php echo htmlspecialchars($row['section']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['theory_lab']); ?></td>
                                    <td><?php echo $row['l']; ?></td>
                                    <td><?php echo $row['p']; ?></td>
                                    <td><?php echo $row['theory_load']; ?></td>
                                    <td><?php echo $row['lab_load']; ?></td>
                                    <td><?php echo $row['total_load']; ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
<?php include 'footer.php'; ?>