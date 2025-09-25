<?php
include '../include/db.php';
include 'header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Determine accessible sections based on role
$sections = [];
if ($_SESSION['role'] === 'hod') {
    $query = "SELECT branch_id FROM hod WHERE user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    $hod = mysqli_fetch_assoc($result);
    $branch_id = $hod['branch_id'];
    $query = "SELECT section_id, section_name, year, semester, branch_name 
              FROM sections s 
              JOIN branches b ON s.branch_id = b.branch_id 
              WHERE s.branch_id = $branch_id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
} elseif ($_SESSION['role'] === 'faculty') {
    $query = "SELECT faculty_id FROM faculty WHERE user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    $faculty = mysqli_fetch_assoc($result);
    $faculty_id = $faculty['faculty_id'];
    $query = "SELECT DISTINCT s.section_id, s.section_name, s.year, s.semester, b.branch_name 
              FROM timetable_slots ts 
              JOIN sections s ON ts.section_id = s.section_id 
              JOIN branches b ON s.branch_id = b.branch_id 
              WHERE ts.faculty_id = $faculty_id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
}

// Fetch timetable for selected section
$timetable = [];
$selected_section_id = (int)($_GET['section_id'] ?? 0);
if ($selected_section_id) {
    $query = "SELECT ts.*, s.section_name, sub.subject_name, f.faculty_name, r.room_name 
              FROM timetable_slots ts 
              JOIN sections s ON ts.section_id = s.section_id 
              JOIN subjects sub ON ts.subject_id = sub.subject_id 
              JOIN faculty f ON ts.faculty_id = f.faculty_id 
              JOIN rooms r ON ts.room_id = r.room_id 
              WHERE ts.section_id = $selected_section_id 
              ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), ts.start_time";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $timetable[] = $row;
    }
}
?>

<div class="space-y-6">
    <div class="text-center">
        <h2 class="text-3xl font-extrabold text-gray-900">View Timetable</h2>
        <p class="mt-2 text-sm text-gray-600">Select a section to view its timetable.</p>
    </div>

    <form id="viewForm" method="get" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-4 md:space-y-0">
            <div class="flex-1">
                <label for="section_id" class="block text-sm font-medium text-gray-700 mb-1">Select Section</label>
                <select id="section_id" name="section_id" onchange="submitForm()" class="w-full p-2 border rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Select a section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['section_id']; ?>" <?php echo $selected_section_id == $section['section_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['branch_name'] . ' - ' . $section['section_name'] . ' (Year ' . $section['year'] . ', Sem ' . $section['semester'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_section_id): ?>
                <a href="export_pdf.php?section_id=<?php echo $selected_section_id; ?>" class="p-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors duration-200 text-center">Export to PDF</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($timetable): ?>
        <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
            <table class="min-w-full text-left table-auto">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2 font-medium text-gray-700">Day</th>
                        <th class="p-2 font-medium text-gray-700">Time</th>
                        <th class="p-2 font-medium text-gray-700">Subject</th>
                        <th class="p-2 font-medium text-gray-700">Faculty</th>
                        <th class="p-2 font-medium text-gray-700">Room</th>
                        <!-- Add actions for HOD to edit/delete -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timetable as $slot): ?>
                        <tr class="border-t border-gray-200 hover:bg-gray-100 transition-colors duration-150">
                            <td class="p-2"><?php echo htmlspecialchars($slot['day_of_week']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars(date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time']))); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($slot['subject_name']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($slot['faculty_name']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($slot['room_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($selected_section_id): ?>
        <div class="bg-yellow-50 p-4 rounded-md text-yellow-800 border border-yellow-200">
            <p>No timetable available for this section.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function submitForm() {
        document.getElementById('viewForm').submit();
    }
</script>

<?php include 'footer.php'; ?>
