<?php
// Initialize the session
session_start();

// Check if the user is logged in. If not, redirect them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-bg: #111827; /* Dark Gray */
            --sidebar-link-color: #9ca3af; /* Lighter Gray */
            --sidebar-link-hover-bg: rgba(255, 255, 255, 0.05);
            --sidebar-link-active-bg: #4f46e5; /* Indigo */
            --main-bg: #f3f4f6; /* Light Gray */
            --text-light: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--main-bg);
        }

        /* --- Stylish Scrollable Sidebar --- */
        .sidebar {
            background: var(--sidebar-bg);
            transition: min-width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Navigation container that will scroll */
        .sidebar-nav {
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Custom Scrollbar Styling */
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }


        .sidebar-header .logo-text {
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.85rem 1rem;
            color: var(--sidebar-link-color);
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            margin: 0.25rem 0;
            white-space: nowrap;
        }

        .sidebar-link:hover {
            background-color: var(--sidebar-link-hover-bg);
            color: var(--text-light);
            transform: translateX(4px);
        }

        .sidebar-link.active {
            background: var(--sidebar-link-active-bg);
            color: var(--text-light);
            font-weight: 600;
            box-shadow: 0 4px 15px -3px rgba(79, 70, 229, 0.4);
        }
        
        .sidebar-link .link-text {
            /* Removed transition from here to make disappearance instant */
        }

        .sidebar-link i {
            width: 2.5rem;
            text-align: center;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .sidebar-link:hover i {
            transform: scale(1.1);
        }

        /* --- Collapsed Sidebar State --- */
        .sidebar.collapsed {
            min-width: 5.5rem;
            width: 5.5rem;
        }
        
        /* --- EDITED THIS SECTION --- */
        /* This rule now completely hides the text spans when the sidebar is collapsed */
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .link-text,
        .sidebar.collapsed .sidebar-footer p {
            display: none; /* This instantly hides the text */
        }

        .sidebar.collapsed .sidebar-header {
            justify-content: center;
        }
        
        .sidebar.collapsed .sidebar-link {
            justify-content: center;
        }

        /* --- Main Content Area --- */
        #main-content-wrapper {
            height: 100vh;
            overflow-y: auto;
        }
        
        #content-frame {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 0.75rem;
            background-color: white;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }
        
        /* --- Header Buttons --- */
        .header-btn {
            background-color: #fff;
            color: #1f2937;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .header-btn:hover {
            transform: translateY(-2px) scale(1.05);
            background-color: var(--sidebar-link-active-bg);
            color: #fff;
            box-shadow: 0 7px 20px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>
<body class="flex h-screen bg-gray-100">

    <aside id="sidebar" class="sidebar w-64 min-w-[16rem] flex-shrink-0 p-4">
        <div class="sidebar-header flex items-center justify-start py-4 mb-4 flex-shrink-0">
            <a href="#" class="flex items-center text-2xl font-bold text-white">
                <i class="fas fa-rocket text-indigo-400 mr-3 text-3xl"></i>
                <span class="logo-text">Admin</span>
            </a>
        </div>

        <nav class="sidebar-nav">
           <ul>
            <li>
                <a href="Dashboard.php" class="sidebar-link active" target="content-frame">
                    <i class="fas fa-tachometer-alt"></i><span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="add_family.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-users"></i><span class="link-text">Family Records</span>
                </a>
            </li>
            <li>
                <a href="arghya_pradan.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-praying-hands"></i><span class="link-text">Record Arghya</span>
                </a>
            </li>
            <li>
                <a href="pranami.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-hand-holding-dollar"></i><span class="link-text">Record Pranami</span>
                </a>
            </li>
            <li>
                <a href="utsav_donation.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-donate"></i><span class="link-text">Record Donation</span>
                </a>
            </li>
            <li>
                <a href="expense_management.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-file-invoice-dollar"></i><span class="link-text">Expenses</span>
                </a>
            </li>
            <li>
                <a href="invoice_list.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-receipt"></i><span class="link-text">Arghya History</span>
                </a>
            </li>
            <li>
                <a href="view_recent_invoices.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-history"></i><span class="link-text">Pranami History</span>
                </a>
            </li>
            <li>
                <a href="view_donations_invoice.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-receipt"></i><span class="link-text">Donation History</span>
                </a>
            </li>
            <li>
                <a href="add_ritwik.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-user-tie"></i><span class="link-text">Manage Ritwiks</span>
                </a>
            </li>
            <li>
                <a href="add_employee.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-id-badge"></i><span class="link-text">Employee Management</span>
                </a>
            </li>
             <li>
                <a href="stock_management.php" class="sidebar-link" target="content-frame">
                    <i class="fas fa-boxes-stacked"></i><span class="link-text">Inventory</span>
                </a>
            </li>
           <!-- For Managing the types of donations -->
<li>
    <a href="donation_types.php" class="sidebar-link" target="content-frame">
        <i class="fas fa-sitemap"></i><span class="link-text">Offering Categories</span>
    </a>
</li>

<!-- For the detailed report on Ritwik earnings and payments -->
<li>
    <a href="ritwik_dakshina_report.php" class="sidebar-link" target="content-frame">
        <i class="fas fa-file-invoice-dollar"></i><span class="link-text">Ritwik Accounts</span>
    </a>
</li>

            <li>
                <a href="logout.php" class="sidebar-link bg-red-500 hover:bg-red-600 text-white font-bold">
                    <i class="fas fa-sign-out-alt"></i><span class="link-text">Log Out</span>
                </a>
            </li>
        </ul>
        </nav>

        <div class="sidebar-footer mt-auto text-center text-gray-400 text-xs flex-shrink-0 pt-4">
            <p>&copy; <?php echo date("Y"); ?> Your Company</p>
        </div>
    </aside>

    <div id="main-content-wrapper" class="flex-1 flex flex-col">
        <header class="p-4 flex items-center justify-between space-x-3 bg-gray-100/80 backdrop-blur-sm sticky top-0 z-10">
            <div class="flex items-center space-x-3">
                <button id="menu-toggle" class="header-btn flex items-center justify-center">
                   <i class="fas fa-bars"></i>
               </button>
                <button id="fullscreen-toggle" class="header-btn flex items-center justify-center">
                   <i id="fullscreen-icon" class="fas fa-expand"></i>
               </button>
            </div>

            <div class="flex items-center space-x-2 text-gray-600">
                <i class="fas fa-user-circle text-xl"></i>
                <span class="font-semibold text-sm">Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?></span>
            </div>
        </header>

        <main class="flex-1 p-6 pt-2">
             <div class="h-full">
                <iframe id="content-frame" name="content-frame" src="Dashboard.php"></iframe>
            </div>
        </main>
    </div>

    <script>
        // --- DOM Elements ---
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menu-toggle');
        const fullscreenToggle = document.getElementById('fullscreen-toggle');
        const fullscreenIcon = document.getElementById('fullscreen-icon');
        const links = document.querySelectorAll('.sidebar-link');

        // --- Sidebar Toggle Handling ---
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });

        // --- Active Link Handling ---
        links.forEach(link => {
            link.addEventListener('click', function() {
                // Do not remove active class from the logout button
                if(this.href.includes('logout.php')) return;

                links.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // --- Fullscreen Mode Handling ---
        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch((err) => {
                    console.error(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        function updateFullscreenIcon() {
            if (document.fullscreenElement) {
                fullscreenIcon.classList.remove('fa-expand');
                fullscreenIcon.classList.add('fa-compress');
            } else {
                fullscreenIcon.classList.remove('fa-compress');
                fullscreenIcon.classList.add('fa-expand');
            }
        }

        fullscreenToggle.addEventListener('click', toggleFullScreen);
        document.addEventListener('fullscreenchange', updateFullscreenIcon);

    </script>
</body>
</html>