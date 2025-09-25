<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../include/db.php';

// Check if TCPDF is available
if (!file_exists(__DIR__ . '/../vendor/tcpdf/tcpdf.php')) {
    die('TCPDF library not found. Please download and place it in vendor/tcpdf/');
}
include . '/../vendor/tcpdf/tcpdf.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Get section_id
$section_id = (int)($_GET['section_id'] ?? 0);
if (!$section_id) {
    die('Section ID is required');
}

// Verify section exists
$query = "SELECT s.section_name, s.year, s.semester, b.branch_name, s.branch_id 
          FROM sections s 
          JOIN branches b ON s.branch_id = b.branch_id 
          WHERE s.section_id = $section_id";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    die('Invalid section: ' . mysqli_error($conn));
}
$section = mysqli_fetch_assoc($result);

// Restrict access based on role
if ($_SESSION['role'] === 'hod') {
    $query = "SELECT branch_id FROM hod WHERE user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        die('HOD not found: ' . mysqli_error($conn));
    }
    $hod = mysqli_fetch_assoc($result);
    if ($hod['branch_id'] != $section['branch_id']) {
        die('Unauthorized access: You do not have permission to view this section');
    }
} elseif ($_SESSION['role'] === 'faculty') {
    $query = "SELECT faculty_id FROM faculty WHERE user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        die('Faculty not found: ' . mysqli_error($conn));
    }
    $faculty = mysqli_fetch_assoc($result);
    $faculty_id = $faculty['faculty_id'];
    $query = "SELECT COUNT(*) FROM timetable_slots WHERE section_id = $section_id AND faculty_id = $faculty_id";
    $result = mysqli_query($conn, $query);
    if (!$result || mysqli_fetch_array($result)[0] == 0) {
        die('Unauthorized access: You are not assigned to this section');
    }
}

// Fetch timetable
$query = "SELECT ts.day_of_week, ts.start_time, ts.end_time, s.section_name, sub.subject_name, f.faculty_name, r.room_name 
          FROM timetable_slots ts 
          JOIN sections s ON ts.section_id = s.section_id 
          JOIN subjects sub ON ts.subject_id = sub.subject_id 
          JOIN faculty f ON ts.faculty_id = f.faculty_id 
          JOIN rooms r ON ts.room_id = r.room_id 
          WHERE ts.section_id = $section_id 
          ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), ts.start_time";
$result = mysqli_query($conn, $query);
if (!$result) {
    die('Error fetching timetable: ' . mysqli_error($conn));
}
$timetable = [];
while ($row = mysqli_fetch_assoc($result)) {
    $timetable[] = $row;
}

// Create PDF
try {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Timetable Management System');
    $pdf->SetTitle('Timetable');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    // Header
    $header = "Timetable for {$section['branch_name']} - {$section['section_name']} (Year {$section['year']}, Semester {$section['semester']})\n\n";
    $pdf->Write(0, $header, '', 0, 'C');

    // Simplified HTML table (avoiding unsupported CSS)
    $html = '<table border="1" cellpadding="4">
        <tr>
            <th><b>Day</b></th>
            <th><b>Time</b></th>
            <th><b>Subject</b></th>
            <th><b>Faculty</b></th>
            <th><b>Room</b></th>
        </tr>';
    foreach ($timetable as $slot) {
        $html .= '<tr>
            <td>' . htmlspecialchars($slot['day_of_week']) . '</td>
            <td>' . htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']) . '</td>
            <td>' . htmlspecialchars($slot['subject_name']) . '</td>
            <td>' . htmlspecialchars($slot['faculty_name']) . '</td>
            <td>' . htmlspecialchars($slot['room_name']) . '</td>
        </tr>';
    }
    $html .= '</table>';

    // Write HTML to PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // Output PDF
    $pdf->Output('timetable.pdf', 'I');
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>