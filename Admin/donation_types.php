<?php
// --- Include Database Connection ---
// This file should contain your database connection logic (the mysqli object named $conn)
include '../config/db.php';

// --- Check Connection ---
// Assuming your db.php creates a $conn object
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- (C)RUD: Add New Donation Type ---
if (isset($_POST['add_donation'])) {
    $donation_name = $_POST['donation_name'];
    // Basic validation to prevent empty names
    if (!empty($donation_name)) {
        // Updated table name from 'donations' to 'donation_types'
        $stmt = $conn->prepare("INSERT INTO donation_types (name, amount) VALUES (?, 0.00)");
        $stmt->bind_param("s", $donation_name);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh to show the new type
        exit();
    }
}

// --- CR(U)D: Update Donation Amount ---
if (isset($_POST['update_amount'])) {
    $donation_id = $_POST['donation_id'];
    $new_amount = $_POST['amount'];

    // Validate that the amount is a valid float
    if (is_numeric($new_amount)) {
        // Updated table name from 'donations' to 'donation_types'
        $stmt = $conn->prepare("UPDATE donation_types SET amount = ? WHERE id = ?");
        $stmt->bind_param("di", $new_amount, $donation_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh page
        exit();
    }
}

// --- CRU(D): Delete Donation Type ---
if (isset($_POST['delete_donation'])) {
    $donation_id = $_POST['donation_id'];
    // Updated table name from 'donations' to 'donation_types'
    $stmt = $conn->prepare("DELETE FROM donation_types WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']); // Refresh page
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashram Donation Management</title>
    
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* A slightly off-white for a softer look */
        }
        /* Custom focus styles for better accessibility and aesthetics */
        input:focus, button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-5xl">

        <header class="mb-10 text-center" data-aos="fade-down">
            <div class="inline-flex items-center justify-center bg-indigo-100 text-indigo-600 rounded-full p-3 mb-4">
                 <i data-feather="gift" class="w-10 h-10"></i>
            </div>
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Ashram Donation Management</h1>
            <p class="text-gray-500 mt-2 text-lg">Admin Panel to add, update, or delete donation types.</p>
        </header>

        <!-- Add New Donation Type Form -->
        <div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg mb-10" data-aos="fade-up">
            <h2 class="text-2xl font-bold mb-5 flex items-center text-gray-800">
                <i data-feather="plus-circle" class="w-6 h-6 mr-3 text-indigo-500"></i>
                Add New Donation Type
            </h2>
            <form action="" method="POST" class="flex flex-col sm:flex-row items-center gap-4">
                <div class="w-full">
                    <label for="donation_name" class="sr-only">Donation Name</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                           <i data-feather="edit-3" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input type="text" id="donation_name" name="donation_name" required class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g., Bhog Seva, Gau Seva, etc.">
                    </div>
                </div>
                <button type="submit" name="add_donation" class="w-full sm:w-auto flex items-center justify-center gap-2 bg-indigo-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-indigo-700 focus:outline-none transition-transform transform hover:scale-105 duration-300 ease-in-out shadow-md">
                    <i data-feather="plus" class="w-5 h-5"></i>
                    <span>Add Type</span>
                </button>
            </form>
        </div>

        <!-- Donations Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden" data-aos="fade-up" data-aos-delay="200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Donation Type</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Amount (₹)</th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        // --- C(R)UD: Fetch and Display Donations ---
                        // Updated table name from 'donations' to 'donation_types'
                        $sql = "SELECT id, name, amount FROM donation_types";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='hover:bg-gray-50 transition-colors duration-200'>";
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>" . htmlspecialchars($row["name"]) . "</td>";
                                
                                // Form for updating the amount
                                echo "<td class='px-6 py-4 whitespace-nowrap text-sm'>";
                                echo "<form action='' method='POST' class='flex items-center gap-2'>";
                                echo "<input type='hidden' name='donation_id' value='" . $row["id"] . "'>";
                                echo "<input type='number' step='0.01' name='amount' value='" . number_format($row["amount"], 2, '.', '') . "' class='w-36 px-2 py-2 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500' placeholder='0.00'>";
                                echo "<button type='submit' name='update_amount' class='flex items-center gap-1 bg-blue-500 text-white text-xs font-semibold py-2 px-3 rounded-md hover:bg-blue-600 transition-transform transform hover:scale-105 duration-200'><i data-feather='refresh-cw' class='w-3 h-3'></i>Update</button>";
                                echo "</form>";
                                echo "</td>";

                                // Form for deleting the donation type
                                echo "<td class='px-6 py-4 whitespace-nowrap text-center text-sm font-medium'>";
                                echo "<form action='' method='POST' onsubmit='return confirm(\"Are you sure you want to delete this donation type?\");'>";
                                echo "<input type='hidden' name='donation_id' value='" . $row["id"] . "'>";
                                echo "<button type='submit' name='delete_donation' class='flex items-center gap-2 mx-auto text-red-600 hover:text-red-800 font-semibold transition-colors duration-200'><i data-feather='trash-2' class='w-4 h-4'></i>Delete</button>";
                                echo "</form>";
                                echo "</td>";

                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' class='text-center px-6 py-10 text-gray-500'><div class='flex flex-col items-center gap-2'><i data-feather='info' class='w-8 h-8 text-gray-400'></i><span>No donation types found. Please add one using the form above.</span></div></td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- AOS Animation Library JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 700,
            once: true,
            easing: 'ease-in-out-cubic'
        });

        // Initialize Feather Icons
        feather.replace();
    </script>
</body>
</html>
