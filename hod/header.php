<?php
// This is a simple header file to include on your pages.
// It includes basic HTML structure, a title, and a modern-looking navigation bar.

// Get HOD's branch and name for the header.
$branch_name = "Department"; // Default
$hod_name = "HOD Profile"; // Default
$branch_id = null;

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'hod' && isset($conn)) {
    $query = "SELECT hod_name, branch_name, h.branch_id FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $hod_data = mysqli_fetch_assoc($result);
        $hod_name = htmlspecialchars($hod_data['hod_name']);
        $branch_name = htmlspecialchars($hod_data['branch_name']);
        $branch_id = $hod_data['branch_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - Timetable Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <header class="bg-white shadow-md p-4 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-bold text-gray-800">Timetable Management System</h1>
            <span class="text-gray-500 text-sm">| <?php echo $branch_name; ?> Department</span>
        </div>
        <div class="relative">
            <div id="profile-menu-button" class="cursor-pointer flex items-center space-x-2 p-2 rounded-full hover:bg-gray-200 transition-colors duration-200">
                <span class="text-gray-700 font-medium"><?php echo $hod_name; ?></span>
                <img class="w-8 h-8 rounded-full" src="https://placehold.co/100x100/A0AEC0/4A5568?text=H" alt="Profile icon">
            </div>
            <div id="profile-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                <a href="hod_dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Dashboard</a>
                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
            </div>
        </div>
    </header>
    <main class="p-6 max-w-7xl mx-auto">
    <script>
        document.getElementById('profile-menu-button').addEventListener('click', function() {
            document.getElementById('profile-menu').classList.toggle('hidden');
        });
        document.addEventListener('click', function(event) {
            if (!event.target.closest('#profile-menu-button') && !event.target.closest('#profile-menu')) {
                document.getElementById('profile-menu').classList.add('hidden');
            }
        });
    </script>
