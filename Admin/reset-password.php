<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Include your database connection file
require_once "../config/db.php";

// Define variables and initialize with empty values
$current_password = $new_password = $confirm_password = "";
$current_password_err = $new_password_err = $confirm_password_err = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate current password
    if (empty(trim($_POST["current_password"]))) {
        $current_password_err = "Please enter your current password.";
    } else {
        $current_password = trim($_POST["current_password"]);
    }

    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter the new password.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Password must have at least 6 characters.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Check input errors before processing
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare a select statement
        $sql = "SELECT password FROM admins WHERE id = ?";

        // Note the change from $link to $conn
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "i", $param_id);

            // Set parameters from session
            $param_id = $_SESSION["id"];

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);

                // Check if account exists
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $hashed_password);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($current_password, $hashed_password)) {
                            // Current password is correct, proceed to update
                            $sql_update = "UPDATE admins SET password = ? WHERE id = ?";

                            // Note the change from $link to $conn
                            if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
                                mysqli_stmt_bind_param($stmt_update, "si", $param_password, $param_id);

                                // Set parameters
                                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $param_id = $_SESSION["id"];

                                // Attempt to execute the prepared statement
                                if (mysqli_stmt_execute($stmt_update)) {
                                    $success_msg = "Password updated successfully! You will be redirected to the login page shortly.";
                                    
                                    // Destroy session and redirect to login page
                                    session_destroy();
                                    header("refresh:3;url=login.php");
                                    exit; // IMPORTANT: Stop script execution after redirect
                                } else {
                                    echo "Oops! Something went wrong. Please try again later.";
                                }
                                mysqli_stmt_close($stmt_update);
                            }
                        } else {
                            $current_password_err = "The password you entered was not valid.";
                        }
                    }
                } else {
                     echo "No account found.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .form-icon { transition: all 0.3s ease; }
        .form-input:focus ~ .form-icon { color: #4f46e5; transform: scale(1.1); }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md mx-auto p-4">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-8">
                <div class="text-center mb-8">
                    <div class="inline-block bg-indigo-100 p-4 rounded-full">
                        <i class="fas fa-key text-4xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mt-4">Change Password</h1>
                    <p class="text-gray-500 mt-2">Update your password for enhanced security.</p>
                </div>

                <?php
                if (!empty($success_msg)) {
                    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert"><p class="font-bold"><i class="fas fa-check-circle mr-2"></i>Success</p><p>' . htmlspecialchars($success_msg) . '</p></div>';
                }
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                    <!-- Current Password -->
                    <div class="relative mb-6">
                        <label for="current_password" class="sr-only">Current Password</label>
                        <input id="current_password" name="current_password" type="password" class="form-input w-full pl-12 pr-4 py-3 border-2 <?php echo (!empty($current_password_err)) ? 'border-red-500' : 'border-gray-200'; ?> rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="Current Password">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400 form-icon"></i>
                        </div>
                    </div>
                     <?php if(!empty($current_password_err)): ?>
                        <p class="text-red-500 text-xs italic -mt-4 mb-4 ml-2"><?php echo $current_password_err; ?></p>
                    <?php endif; ?>

                    <!-- New Password -->
                    <div class="relative mb-6">
                         <label for="new_password" class="sr-only">New Password</label>
                        <input id="new_password" name="new_password" type="password" class="form-input w-full pl-12 pr-4 py-3 border-2 <?php echo (!empty($new_password_err)) ? 'border-red-500' : 'border-gray-200'; ?> rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="New Password">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                           <i class="fas fa-shield-alt text-gray-400 form-icon"></i>
                        </div>
                    </div>
                     <?php if(!empty($new_password_err)): ?>
                        <p class="text-red-500 text-xs italic -mt-4 mb-4 ml-2"><?php echo $new_password_err; ?></p>
                    <?php endif; ?>

                    <!-- Confirm New Password -->
                    <div class="relative mb-6">
                         <label for="confirm_password" class="sr-only">Confirm New Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" class="form-input w-full pl-12 pr-4 py-3 border-2 <?php echo (!empty($confirm_password_err)) ? 'border-red-500' : 'border-gray-200'; ?> rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="Confirm New Password">
                         <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                           <i class="fas fa-check-circle text-gray-400 form-icon"></i>
                        </div>
                    </div>
                     <?php if(!empty($confirm_password_err)): ?>
                        <p class="text-red-500 text-xs italic -mt-4 mb-4 ml-2"><?php echo $confirm_password_err; ?></p>
                    <?php endif; ?>

                    <div>
                        <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-transform transform hover:scale-105 shadow-lg">
                            <i class="fas fa-save mr-2"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
             <div class="bg-gray-50 px-8 py-4 text-center">
                <a href="index.php" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

</body>
</html>

