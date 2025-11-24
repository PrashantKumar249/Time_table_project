<?php
include '../include/db.php';

// Default values
$branch_name = "Department";
$user_name = "User Profile";
$college_name = "College Name";
$college_address = "College Address";
$college_logo = "https://placehold.co/100x100/A0AEC0/4A5563?text=U"; // Default placeholder

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'faculty') {
    // Fetch faculty name from session or database
    $user_name = htmlspecialchars($_SESSION['faculty_name'] ?? 'Faculty Member');
    
    // Fetch college details via HOD for the faculty's branch
    $branch_id = (int)$_SESSION['branch_id'];
    $query = "SELECT h.college_name, h.address, h.college_logo 
              FROM hod h 
              WHERE h.branch_id = $branch_id 
              LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $hod_data = mysqli_fetch_assoc($result);
        $college_name = htmlspecialchars($hod_data['college_name']);
        $college_address = htmlspecialchars($hod_data['address']);
        $college_logo = !empty($hod_data['college_logo']) ? '../logo/' . htmlspecialchars($hod_data['college_logo']) : $college_logo;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_name; ?> - Faculty Panel</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .header-container {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 3px solid #a78bfa;
        }
        .header-left {
            display: flex;
            align-items: center;
            flex-grow: 1;
            flex-basis: 0;
            min-width: 0;
            max-width: 75%;
        }
        .college-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 3px solid #a78bfa;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .college-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            min-width: 0;
            flex-grow: 1;
        }
        .college-name {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-left: 1rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        .college-address {
            font-size: 1rem;
            color: #e9d5ff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-left: 1rem;
            font-style: italic;
        }
        .header-right {
            position: relative;
            display: flex;
            align-items: center;
            margin-left: 1rem;
            flex-shrink: 0;
        }
        .profile-button {
            cursor: pointer;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%);
            border: 2px solid #a78bfa;
            border-radius: 25px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease-in-out;
            min-width: 140px;
            justify-content: flex-end;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .profile-button:hover {
            background: linear-gradient(135deg, #f3f4f6 0%, #ffffff 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .profile-name {
            font-size: 1rem;
            font-weight: 600;
            color: #4f46e5;
            margin-right: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-arrow {
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #4f46e5;
            margin-left: 0.5rem;
            transition: transform 0.3s ease-in-out;
        }
        .profile-menu {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%);
            border-radius: 0.75rem;
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.2);
            min-width: 160px;
            overflow: hidden;
            z-index: 10;
            display: none;
            border: 2px solid #a78bfa;
        }
        .profile-menu-item {
            display: block;
            padding: 1rem 1.25rem;
            font-size: 0.95rem;
            color: #4f46e5;
            text-decoration: none;
            transition: all 0.3s ease-in-out;
            border-bottom: 1px solid #a78bfa;
        }
        .profile-menu-item:last-child {
            border-bottom: none;
        }
        .profile-menu-item:hover {
            background-color: #a78bfa;
            color: #ffffff;
            transform: translateX(5px);
        }
        
        @media (max-width: 640px) {
            .header-container {
                padding: 1rem;
                flex-direction: column;
                align-items: center;
            }
            .header-left {
                width: 100%;
                justify-content: center;
                margin-bottom: 1rem;
                max-width: 100%;
            }
            .header-right {
                width: 100%;
                justify-content: center;
                margin-left: 0;
            }
            .college-logo {
                width: 50px;
                height: 50px;
                margin-right: 0.75rem;
            }
            .college-name {
                font-size: 1.5rem;
                padding-left: 0.5rem;
            }
            .college-address {
                font-size: 0.875rem;
                padding-left: 0.5rem;
            }
            .profile-button {
                padding: 0.75rem;
                justify-content: center;
                width: 100%;
                min-width: auto;
            }
            .profile-name {
                display: initial; 
                margin-right: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header-container">
        <div class="header-left">
            <img class="college-logo" src="<?php echo htmlspecialchars($college_logo); ?>" alt="College Logo">
            <div class="college-info">
                <span class="college-name"><?php echo $college_name; ?></span>
                <span class="college-address"><?php echo $college_address; ?></span>
            </div>
        </div>
        <div class="header-right">
            <div id="profile-menu-button" class="profile-button">
                <span class="profile-name"><?php echo $user_name; ?></span>
                <div class="profile-arrow"></div>
            </div>
            <div id="profile-menu" class="profile-menu">
                <a href="faculty_dashboard.php" class="profile-menu-item">Home</a>
                <a href="faculty_profile.php" class="profile-menu-item">Faculty Profile</a>
                <a href="logout.php" class="profile-menu-item">Logout</a>
            </div>
        </div>
    </header>
    <script>
        document.getElementById('profile-menu-button').addEventListener('click', function() {
            var menu = document.getElementById('profile-menu');
            var arrow = this.querySelector('.profile-arrow');
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            } else {
                menu.style.display = 'block';
                arrow.style.transform = 'rotate(180deg)';
            }
        });
        document.addEventListener('click', function(event) {
            if (!event.target.closest('#profile-menu-button') && !event.target.closest('#profile-menu')) {
                var menu = document.getElementById('profile-menu');
                var arrow = document.getElementById('profile-menu-button').querySelector('.profile-arrow');
                menu.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            }
        });
    </script>
</body>
</html>