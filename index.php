<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Select Your Role</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        /* Custom Styles */
        body {
            font-family: 'Poppins', sans-serif;
            overflow: hidden; /* Prevents scrollbars from showing */
            background-color: #0c0a1f; /* Fallback background */
        }

        #particles-js {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background-image: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
        }

        .content-container {
            position: relative;
            z-index: 1;
        }

        .main-title {
            font-family: 'Orbitron', sans-serif;
            text-shadow: 0 0 10px rgba(173, 216, 230, 0.7), 0 0 20px rgba(173, 216, 230, 0.5);
        }

        .role-button {
            background: #1a1a3a; /* Darker, more solid background */
            border: 2px solid #4a4a7f;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
        }

        .role-button:hover {
            transform: scale(1.08);
            border-color: #93c5fd;
        }

        /* Specific glows for each button on hover */
        .admin-button:hover {
            box-shadow: 0 0 25px #2dd4bf, 0 0 50px #2dd4bf;
        }

        .sub-admin-button:hover {
            box-shadow: 0 0 25px #a78bfa, 0 0 50px #a78bfa;
        }

        .role-button i {
            transition: all 0.3s ease;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }

        .role-button:hover i {
            transform: scale(1.1);
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
        }

        /* Simple CSS fade-in animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Applying the animation */
        .main-title, .subtitle, .role-button {
            opacity: 0; /* Start hidden */
            animation: fadeIn 0.8s ease-out forwards;
        }

        /* Animation delays for a staggered effect */
        .subtitle {
            animation-delay: 0.2s;
        }
        .admin-button {
            animation-delay: 0.4s;
        }
        .sub-admin-button {
            animation-delay: 0.6s;
        }

    </style>
</head>
<body class="h-screen flex items-center justify-center p-4">

    <!-- Particle background -->
    <div id="particles-js"></div>

    <div 
        class="text-center text-white max-w-4xl w-full content-container">
        
        <!-- Main Title -->
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold mb-3 tracking-wider main-title">
            ACCESS PORTAL
        </h1>
        
        <!-- Subtitle -->
        <p class="text-lg md:text-xl mb-12 text-blue-200 subtitle">
            Please select your designated role to proceed.
        </p>

        <!-- Role Selection Container -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-8 md:gap-12 button-container">
            
            <!-- Admin Button -->
            <a href="Admin/index.php" class="role-button admin-button flex flex-col items-center justify-center w-56 h-56 rounded-full shadow-2xl">
                <i class="fas fa-user-shield text-6xl mb-3 text-cyan-300"></i>
                <span class="text-2xl font-semibold">Admin</span>
            </a>
            
            <!-- Sub-Admin Button -->
            <a href="sub-admin/index.php" class="role-button sub-admin-button flex flex-col items-center justify-center w-56 h-56 rounded-full shadow-2xl">
                <i class="fas fa-user-cog text-6xl mb-3 text-purple-300"></i>
                <span class="text-2xl font-semibold">Sub-Admin</span>
            </a>
            
        </div>
    </div>

    <!-- Particle.js -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

    <script>
        // Initialize Particles.js
        particlesJS('particles-js', {
          "particles": {
            "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#ffffff" },
            "shape": { "type": "circle", "stroke": { "width": 0, "color": "#000000" } },
            "opacity": { "value": 0.5, "random": true, "anim": { "enable": true, "speed": 1, "opacity_min": 0.1, "sync": false } },
            "size": { "value": 3, "random": true, "anim": { "enable": false } },
            "line_linked": { "enable": true, "distance": 150, "color": "#ffffff", "opacity": 0.4, "width": 1 },
            "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false }
          },
          "interactivity": {
            "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "repulse" }, "onclick": { "enable": true, "mode": "push" }, "resize": true },
            "modes": { "repulse": { "distance": 100, "duration": 0.4 }, "push": { "particles_nb": 4 } }
          }, "retina_detect": true
        });
    </script>
</body>
</html>

