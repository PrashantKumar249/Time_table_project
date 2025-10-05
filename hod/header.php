<?php
session_start();
include '../include/db.php';

// Default values
$branch_name = "Department";
$user_name = "User Profile";
$college_name = "College Name";
$college_address = "College Address";
$college_logo = "https://placehold.co/100x100/A0AEC0/4A5563?text=U"; // Default placeholder

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'hod') {
        $query = "SELECT hod_name, college_name, address, college_logo FROM hod WHERE user_id = " . (int)$_SESSION['user_id'];
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            $user_name = htmlspecialchars($user_data['hod_name']);
            $college_name = htmlspecialchars($user_data['college_name']);
            $college_address = htmlspecialchars($user_data['address']);
            $college_logo = !empty($user_data['college_logo']) ? '../logo/' . htmlspecialchars($user_data['college_logo']) : $college_logo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_name; ?> - HOD Panel</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #821d07ff;
            margin: 0;
            padding: 0;
        }
        .header-container {
            background-color: #0c3f66b7;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-left {
            display: flex;
            align-items: center;
            flex-grow: 1;
            flex-basis: 0;
            min-width: 0;
            max-width: 75%; /* Increased maximum width */
        }
        .college-logo {
            width: 60px;
            height: 60px;
        
            object-fit: cover;
            margin-right: 1rem;
            
            flex-shrink: 0;
        }
        .college-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            min-width: 0;
            flex-grow: 1;
        }
        .college-name {
            font-size: 2.7rem;
            font-weight: 650;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-left: 1 rem;
        }
        .college-address {
            font-size: 1rem;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
            padding: 0.5rem 1rem;
            background-color: #fff;
            border: 1px solid #d1d5db;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease-in-out;
            min-width: 120px;
            justify-content: flex-end;
            position: relative;
        }
        .profile-button:hover {
            background-color: #f3f4f6;
        }
        .profile-name {
            font-size: 1rem;
            font-weight: 500;
            color: #1f2937;
            margin-right: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-arrow {
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid #6b7280;
            margin-left: 0.5rem;
            transition: transform 0.2s ease-in-out;
        }
        .profile-menu {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            min-width: 150px;
            overflow: hidden;
            z-index: 10;
            display: none;
        }
        .profile-menu-item {
            display: block;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: #4b5563;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out;
        }
        .profile-menu-item:hover {
            background-color: #f3f4f6;
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
                font-size: 1rem;
            }
            .college-address {
                font-size: 0.75rem;
            }
            .profile-button {
                padding: 0.5rem;
                justify-content: center;
                width: 100%;
            }
            .profile-name {
                display: initial; 
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
                <a href="hod_profile.php" class="profile-menu-item">HOD Profile</a>
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
