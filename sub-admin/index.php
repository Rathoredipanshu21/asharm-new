<?php
session_start();
// Redirect to login if the user is not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$full_name = $_SESSION['user'];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* --- NEW: Modern & Clean Color Palette --- */
        :root {
            --sidebar-bg: #ffffff;
            --sidebar-link-color: #555e71;
            --sidebar-link-hover-bg: #f3f4f6; /* Light Gray */
            --sidebar-link-active-bg: #4f46e5; /* Indigo */
            --main-bg: #f9fafb; /* Very Light Gray */
            --text-dark: #111827;
            --text-light: #ffffff;
            --border-color: #e5e7eb;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--main-bg);
            color: var(--text-dark);
            overflow: hidden; /* Prevent body scroll */
        }

        /* --- Stylish Scrollable Sidebar --- */
        .sidebar {
            background: var(--sidebar-bg);
            transition: min-width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-nav {
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Custom Scrollbar Styling */
        .sidebar-nav::-webkit-scrollbar { width: 6px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background-color: rgba(0, 0, 0, 0.2); border-radius: 10px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background-color: rgba(0, 0, 0, 0.4); }

        .sidebar-header .logo-text {
            transition: opacity 0.3s ease-in-out;
            color: var(--text-dark);
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
            position: relative; /* NEW: Needed for tooltip positioning */
        }

        .sidebar-link:hover {
            background-color: var(--sidebar-link-hover-bg);
            color: var(--text-dark);
            transform: translateX(4px);
        }

        .sidebar-link.active {
            background: var(--sidebar-link-active-bg);
            color: var(--text-light);
            font-weight: 600;
            box-shadow: 0 4px 15px -3px rgba(79, 70, 229, 0.4);
        }
        
        .sidebar-link i {
            width: 2.5rem;
            text-align: center;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }
        
        .sidebar-link.active i { color: var(--text-light); }
        .sidebar-link:hover i { transform: scale(1.1); }

        /* --- Collapsed Sidebar State --- */
        .sidebar.collapsed {
            min-width: 5.5rem;
            width: 5.5rem;
        }
        
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .link-text,
        .sidebar.collapsed .sidebar-footer p {
            display: none;
        }

        .sidebar.collapsed .sidebar-header,
        .sidebar.collapsed .sidebar-link {
            justify-content: center;
        }
        
        /* --- NEW: Tooltip for Collapsed Sidebar --- */
        .sidebar.collapsed .sidebar-link:hover::after {
            content: attr(data-title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 15px;
            padding: 6px 12px;
            background-color: var(--text-dark);
            color: var(--text-light);
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* --- Main Content Area --- */
        #main-content-wrapper {
            height: 100vh;
            overflow-y: auto;
            width: calc(100% - 16rem); /* 16rem is the initial sidebar width */
            margin-left: 16rem;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* NEW: Smooth transition */
        }
        
        /* NEW: Adjust margin when sidebar is collapsed */
        .sidebar.collapsed + #main-content-wrapper {
            margin-left: 5.5rem;
            width: calc(100% - 5.5rem);
        }
        
        #content-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* --- Header Styling --- */
        .header-btn {
            background-color: #fff;
            color: #4b5563; /* Gray-600 */
            border-radius: 50%;
            width: 40px;
            height: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
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
<body class="flex h-screen overflow-hidden">

    <aside id="sidebar" class="sidebar w-64 min-w-[16rem] flex-shrink-0 p-4 fixed top-0 left-0 h-full">
        <div class="sidebar-header flex items-center justify-start py-4 mb-4 flex-shrink-0">
            <a href="#" class="flex items-center text-2xl font-bold">
                <i class="fas fa-rocket text-indigo-500 mr-3 text-3xl"></i>
                <span class="logo-text">Admin</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
    <li><a href="../Admin/Dashboard.php" class="sidebar-link active" target="content-frame"><i class="fas fa-tachometer-alt"></i><span class="link-text">Dashboard</span></a></li>
    
    <li><a href="../Admin/add_ritwik.php" class="sidebar-link" target="content-frame"><i class="fas fa-user-tie"></i><span class="link-text">Manage Ritwiks</span></a></li>
    <li><a href="../Admin/add_family.php" class="sidebar-link" target="content-frame"><i class="fas fa-users"></i><span class="link-text">Family Directory</span></a></li>
    <li><a href="../Admin/donation_types.php" class="sidebar-link" target="content-frame"><i class="fas fa-tags"></i><span class="link-text">Offering Categories</span></a></li>
    
    <li><a href="../Admin/arghya_pradan.php" class="sidebar-link" target="content-frame"><i class="fas fa-praying-hands"></i><span class="link-text">Record Arghya</span></a></li>
    <li><a href="../Admin/pranami.php" class="sidebar-link" target="content-frame"><i class="fas fa-hand-holding-dollar"></i><span class="link-text">Record Pranami</span></a></li>
    <li>
        <a href="../Admin/utsav_donation.php" class="sidebar-link" target="content-frame">
            <i class="fas fa-donate"></i><span class="link-text">Record Donation</span>
        </a>
    </li>
    
    <li><a href="../Admin/invoice_list.php" class="sidebar-link" target="content-frame"><i class="fas fa-receipt"></i><span class="link-text">Arghya History</span></a></li>
    <li><a href="../Admin/view_recent_invoices.php" class="sidebar-link" target="content-frame"><i class="fas fa-history"></i><span class="link-text">Pranami History</span></a></li>
    <li>
        <a href="../Admin/view_donations_invoice.php" class="sidebar-link" target="content-frame">
            <i class="fas fa-receipt"></i><span class="link-text">Donation History</span>
        </a>
    </li>
    
    <li><a href="logout.php" class="sidebar-link mt-4 !text-red-500 hover:!bg-red-50 hover:!text-red-600"><i class="fas fa-sign-out-alt"></i><span class="link-text">Log Out</span></a></li>
</ul>
        </nav>

        <div class="sidebar-footer mt-auto text-center text-gray-500 text-xs flex-shrink-0 pt-4">
            <p>&copy; <?php echo date("Y"); ?> Your Company</p>
        </div>
    </aside>

    <div id="main-content-wrapper" class="flex flex-col">
        <header class="p-4 flex items-center justify-between bg-white/80 backdrop-blur-sm sticky top-0 z-10 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                 <button id="menu-toggle" class="header-btn flex items-center justify-center">
                    <i class="fas fa-bars"></i>
                </button>
                 <button id="fullscreen-toggle" class="header-btn flex items-center justify-center">
                    <i id="fullscreen-icon" class="fas fa-expand"></i>
                </button>
            </div>
            <div class="text-gray-700 font-semibold text-lg">
                <i class="fas fa-smile text-indigo-500"></i> Welcome, <?= htmlspecialchars($full_name) ?>!
            </div>
        </header>

        <main class="flex-1 p-6">
            <div class="h-full bg-white rounded-xl shadow-md overflow-hidden">
                <iframe id="content-frame" name="content-frame" src="../Admin/Dashboard.php"></iframe>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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

            // --- Active Link & Tooltip Handling ---
            links.forEach(link => {
                // NEW: Add data-title attribute for tooltip
                const linkText = link.querySelector('.link-text');
                if (linkText) {
                    link.setAttribute('data-title', linkText.textContent.trim());
                }
                
                link.addEventListener('click', function(e) {
                    // Prevent changing active class for logout button
                    if(this.href.includes('logout.php')) return;

                    // Remove active class from all other links
                    links.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to the clicked link
                    this.classList.add('active');
                });
            });

            // --- Fullscreen Mode Handling ---
            function toggleFullScreen() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(err => {
                        console.error(`Fullscreen request failed: ${err.message} (${err.name})`);
                    });
                } else if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }

            function updateFullscreenIcon() {
                if (document.fullscreenElement) {
                    fullscreenIcon.classList.replace('fa-expand', 'fa-compress');
                } else {
                    fullscreenIcon.classList.replace('fa-compress', 'fa-expand');
                }
            }

            fullscreenToggle.addEventListener('click', toggleFullScreen);
            document.addEventListener('fullscreenchange', updateFullscreenIcon);
        });
    </script>
</body>
</html>