<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashram Welcome Portal</title>
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Basic Setup for Fullscreen Experience */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden; /* Prevents scrollbars */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Background with a black color */
        .main-container {
            width: 100vw;
            height: 100vh;
            background-color: #3b3939ff; /* Black background */
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column; /* Stack title and buttons vertically */
        }

        /* Styling for the main title */
        h1 {
            color: #f0e68c; /* Khaki/Soft Gold for good contrast on black */
            font-size: 4rem;
            margin-bottom: 60px;
            text-shadow: 0 0 15px rgba(240, 230, 140, 0.5);
            /* Set initial state for GSAP animation */
            opacity: 0;
        }

        .button-group {
            display: flex;
            gap: 40px;
        }

        /* Styling for the role selection buttons - Glassmorphism effect */
        .role-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 180px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1); /* Transparent background */
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            color: #fafad2; /* LightGoldenRodYellow for text */
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 8px 32px 0 rgba(240, 230, 140, 0.1);
            transition: all 0.3s ease-in-out;
            /* Set initial state for GSAP animation */
            opacity: 0; 
        }

        .role-button i {
            font-size: 3rem;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        /* Hover effect for buttons */
        .role-button:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(240, 230, 140, 0.3);
            color: #ffffff;
        }

        .role-button:hover i {
            transform: scale(1.1);
        }

        /* Styling for the moving icons */
        .floating-icon {
            position: absolute;
            color: rgba(240, 230, 140, 0.15); /* Soft, transparent gold color */
            font-size: 50px;
            pointer-events: none; /* Icons won't interfere with mouse clicks */
            text-shadow: 0 0 10px rgba(240, 230, 140, 0.2);
        }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- This is where the floating icons will be dynamically added by GSAP -->

        <h1>Welcome Portal</h1>
        <div class="button-group">
            <!-- Admin Button -->
            <a href="Admin/index.php" class="role-button">
                <i class="fas fa-user-shield"></i>
                <span>Admin</span>
            </a>
            
            <!-- Sub-Admin Button -->
            <a href="#" class="role-button">
                <i class="fas fa-user-cog"></i>
                <span>Sub-Admin</span>
            </a>
            
            <!-- Customers/Devotees Button -->
            <a href="#" class="role-button">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </div>
    </div>

    <!-- GSAP (GreenSock Animation Platform) Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.querySelector('.main-container');
            const iconTypes = ['ॐ', '🙏', '<i class="fas fa-user-friends"></i>']; // Om, Pranam, Devotee icon
            const iconCount = 30; // Number of icons to animate

            // Create and animate multiple background icons
            for (let i = 0; i < iconCount; i++) {
                const icon = document.createElement('div');
                icon.classList.add('floating-icon');
                icon.innerHTML = iconTypes[Math.floor(Math.random() * iconTypes.length)];
                container.appendChild(icon);

                // Set initial random properties
                gsap.set(icon, {
                    x: Math.random() * window.innerWidth,
                    y: Math.random() * window.innerHeight,
                    scale: Math.random() * 0.8 + 0.2,
                    opacity: Math.random() * 0.5 + 0.1
                });

                // Animate the icon
                animateBackgroundIcon(icon);
            }

            function animateBackgroundIcon(element) {
                gsap.to(element, {
                    x: Math.random() * window.innerWidth,
                    y: Math.random() * window.innerHeight,
                    duration: Math.random() * 30 + 20, // Slower, more graceful movement
                    ease: 'none',
                    onComplete: () => animateBackgroundIcon(element) // Loop the animation
                });
            }

          
            gsap.to('h1', {
                duration: 1.5,
                opacity: 1,
                y: 20, // Settle slightly lower than start
                ease: 'bounce.out'
            });

            // 2. Staggered entrance for buttons
            gsap.to('.role-button', {
                duration: 1,
                opacity: 1,
                y: 0,
                stagger: 0.2,
                ease: 'power3.out',
                delay: 0.5 // Start after the title begins to appear
            });

            // 3. Continuous floating animation for the buttons
            gsap.to('.role-button', {
                y: '-=15', // Move up 15px
                duration: 2.5,
                repeat: -1, // Infinite loop
                yoyo: true, // Animate back and forth
                ease: 'sine.inOut',
                stagger: {
                    each: 0.5,
                    from: 'center'
                }
            });
        });
    </script>

</body>
</html>
