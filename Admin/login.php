<?php
// ----- DATABASE & SESSION INITIALIZATION -----

// Start the session to manage user login state
session_start();

// If the user is already logged in, redirect them to the index page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// ----- DATABASE CONNECTION -----
// Include your database connection file.
// This file must create a mysqli connection object named $conn.
require_once "../config/db.php";

// Check if the connection object was created and is valid
if (!isset($conn) || $conn->connect_error) {
    // For debugging, we'll stop the script with a clear error message.
    die("ERROR: Database connection failed. Please check your db.php file. Error: " . (isset($conn) ? $conn->connect_error : 'The $conn connection object was not created.'));
}


// ----- LOGIN LOGIC -----

// Define variables and initialize with empty values
$username = $password = "";
$error_message = "";

// Processing form data when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $error_message = "Please enter your username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $error_message = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials if there are no input errors
    if (empty($error_message)) {
        // Prepare a select statement to prevent SQL injection
        $sql = "SELECT id, username, password FROM admins WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();

                // Check if username exists, if yes then verify password
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $username, $db_password);
                    if ($stmt->fetch()) {
                        // NOTE: For this example, we do a simple password check.
                        // In a REAL application, you MUST use password_verify() to check hashed passwords.
                        // Example: if(password_verify($password, $db_password)) { ... }
                        if ($password == $db_password) {
                            // Password is correct, so start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;

                            // Redirect user to the index page
                            header("location: index.php");
                            exit;
                        } else {
                            // Password is not valid
                            $error_message = "The password you entered was not valid.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $error_message = "No account found with that username.";
                }
            } else {
                $error_message = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="flex items-center justify-center min-h-screen">
        <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-lg dark:bg-gray-800">
            <!-- Header Section -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-blue-100 rounded-full dark:bg-blue-900">
                    <i data-lucide="shield-check" class="w-8 h-8 text-blue-600 dark:text-blue-400"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Admin Access</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Please sign in to your database-powered account</p>
            </div>

            <!-- Error Message Display -->
            <?php 
            if(!empty($error_message)){
                echo '<div class="flex items-center p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-700 dark:text-red-400" role="alert">';
                echo '<i data-lucide="alert-triangle" class="w-5 h-5 mr-3"></i>';
                echo '<div><span class="font-medium">Login Error:</span> ' . htmlspecialchars($error_message) . '</div>';
                echo '</div>';
            }
            ?>

            <!-- Login Form -->
            <form class="space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <!-- Username Input -->
                <div>
                    <label for="username" class="text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input id="username" name="username" type="text" autocomplete="username" required 
                               class="block w-full py-3 pl-10 pr-3 border border-gray-300 rounded-md shadow-sm appearance-none placeholder:text-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                               placeholder="admin">
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                               class="block w-full py-3 pl-10 pr-3 border border-gray-300 rounded-md shadow-sm appearance-none placeholder:text-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                               placeholder="password123">
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="flex items-center justify-center w-full px-4 py-3 text-sm font-semibold text-white bg-blue-600 border border-transparent rounded-md shadow-sm group hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 ease-in-out">
                        <i data-lucide="log-in" class="w-5 h-5 mr-2 -ml-1 transition-transform duration-200 ease-in-out group-hover:translate-x-1"></i>
                        Sign In
                    </button>
                </div>
            </form>
            
            <p class="text-xs text-center text-gray-500 dark:text-gray-400">
                &copy; <?php echo date("Y"); ?> Secure Systems Inc. All rights reserved.
            </p>
        </div>
    </div>

    <!-- Script to initialize Lucide icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

