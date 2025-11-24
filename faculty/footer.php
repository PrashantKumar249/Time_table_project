<?php
// This is a simple footer file to include on your pages.
include '../include/db.php';

// Fetch branch and college details for the current HOD
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'hod') {
    $query = "SELECT b.branch_name, h.college_name, h.address FROM hod h JOIN branches b ON h.branch_id = b.branch_id WHERE h.user_id = " . (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $hod_data = mysqli_fetch_assoc($result);
        $branch_name = $hod_data['branch_name'];
        $college_name = $hod_data['college_name'] ?? 'Unknown College';
        $college_address = $hod_data['address'] ?? 'No address available';
    } else {
        $branch_name = 'Unknown Branch';
        $college_name = 'Unknown College';
        $college_address = 'No address available';
    }
} else {
    $branch_name = 'Timetable Management System';
    $college_name = 'Timetable Management System';
    $college_address = '';
}
?>
<style>
footer {
    background-color: #1f2937;
    color: #ffffff;
    padding: 2rem 0;
    margin-top: 2rem;
}
.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}
.footer-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}
.footer-section {
    text-align: center;
}
.footer-section h3 {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}
.footer-section h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
}
.footer-section p {
    color: #d1d5db;
    margin-bottom: 0.5rem;
}
.footer-section ul {
    list-style: none;
    padding: 0;
}
.footer-section li {
    margin-bottom: 0.5rem;
}
.footer-section a {
    color: #d1d5db;
    text-decoration: none;
    transition: color 0.2s ease;
}
.footer-section a:hover {
    color: #ffffff;
}
.footer-bottom {
    border-top: 1px solid #374151;
    padding-top: 1rem;
    text-align: center;
}
.footer-bottom p {
    color: #9ca3af;
    font-size: 0.875rem;
    margin: 0;
}
@media (min-width: 768px) {
    .footer-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }
    .footer-section {
        text-align: left;
    }
}
</style>
</main>
<footer>
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-section">
                <h3><?php echo htmlspecialchars($college_name); ?></h3>
                <p><strong>Branch:</strong> <?php echo htmlspecialchars($branch_name); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($college_address); ?></p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="hod_dashboard.php">Home</a></li>
                    <li><a href="hod_dashboard.php">Dashboard</a></li>
                    <li><a href="#">Timetable</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>Streamlining academic excellence through innovative scheduling solutions. © <?php echo date("Y"); ?> All rights reserved.</p>
        </div>
    </div>
</footer>
</body>
</html>