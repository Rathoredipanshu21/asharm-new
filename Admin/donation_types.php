<?php
// --- Include Database Connection ---
include '../config/db.php';

// --- Check Connection ---
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- (C)RUD: Add New Donation Type ---
if (isset($_POST['add_donation'])) {
    $donation_name = $_POST['donation_name'];
    $name_hindi = $_POST['name_hindi']; // Specific Hindi name field

    if (!empty($donation_name)) {
        // Updated INSERT for the specific Hindi name column
        $stmt = $conn->prepare("INSERT INTO donation_types (name, name_hindi, amount) VALUES (?, ?, 0.00)");
        // Bind two strings ('ss')
        $stmt->bind_param("ss", $donation_name, $name_hindi);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- CR(U)D: Update Full Donation Details ---
if (isset($_POST['update_donation'])) {
    $donation_id = $_POST['donation_id'];
    $name = $_POST['name'];
    $name_hindi = $_POST['name_hindi']; // Specific Hindi name field
    $amount = $_POST['amount'];

    if (is_numeric($amount) && !empty($name)) {
        // Updated UPDATE for the specific Hindi name column
        $stmt = $conn->prepare("UPDATE donation_types SET name = ?, name_hindi = ?, amount = ? WHERE id = ?");
        // Bind params: two strings (s), one double (d), one integer (i)
        $stmt->bind_param("ssdi", $name, $name_hindi, $amount, $donation_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- CRU(D): Delete Donation Type ---
if (isset($_POST['delete_donation'])) {
    $donation_id = $_POST['donation_id'];
    $stmt = $conn->prepare("DELETE FROM donation_types WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashram Donation Management</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        input:focus, button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
        }
    </style>
</head>
<body class="text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-6xl">

        <header class="mb-10 text-center" data-aos="fade-down">
            <div class="inline-flex items-center justify-center bg-indigo-100 text-indigo-600 rounded-full p-3 mb-4">
                 <i data-feather="gift" class="w-10 h-10"></i>
            </div>
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Ashram Donation Management</h1>
            <p class="text-gray-500 mt-2 text-lg">Admin Panel to add, update, or delete donation types.</p>
        </header>

        <div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg mb-10" data-aos="fade-up">
            <h2 class="text-2xl font-bold mb-5 flex items-center text-gray-800">
                <i data-feather="plus-circle" class="w-6 h-6 mr-3 text-indigo-500"></i>
                Add New Donation Type
            </h2>
            <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label for="donation_name" class="block text-sm font-medium text-gray-700 mb-1">Name (English)</label>
                    <input type="text" id="donation_name" name="donation_name" required class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g., Bhog Seva">
                </div>
                
                <div>
                    <label for="name_hindi" class="block text-sm font-medium text-gray-700 mb-1">Name (Hindi)</label>
                    <input type="text" id="name_hindi" name="name_hindi" class="w-full px-3 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g., भोग सेवा">
                </div>
                
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" name="add_donation" class="w-full sm:w-auto flex items-center justify-center gap-2 bg-indigo-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-indigo-700 focus:outline-none transition-transform transform hover:scale-105 duration-300 ease-in-out shadow-md">
                        <i data-feather="plus" class="w-5 h-5"></i>
                        <span>Add Type</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden" data-aos="fade-up" data-aos-delay="200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name (English)</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name (Hindi)</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Amount (₹)</th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        // Updated SELECT for the specific Hindi name column
                        $sql = "SELECT id, name, name_hindi, amount FROM donation_types ORDER BY id ASC";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='hover:bg-gray-50 transition-colors duration-200'>";
                                echo "<form action='' method='POST'>";
                                echo "<input type='hidden' name='donation_id' value='" . $row["id"] . "'>";
                                
                                // Editable Name (English)
                                echo "<td class='px-6 py-4 whitespace-nowrap'><input type='text' name='name' value='" . htmlspecialchars($row["name"]) . "' class='w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500'></td>";
                                
                                // Editable Name (Hindi)
                                echo "<td class='px-6 py-4 whitespace-nowrap'><input type='text' name='name_hindi' value='" . htmlspecialchars($row["name_hindi"]) . "' class='w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500'></td>";
                                
                                // Editable Amount
                                echo "<td class='px-6 py-4 whitespace-nowrap'><input type='number' step='0.01' name='amount' value='" . number_format($row["amount"], 2, '.', '') . "' class='w-32 px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500' placeholder='0.00'></td>";
                                
                                // Actions Cell (Update and Delete)
                                echo "<td class='px-6 py-4 whitespace-nowrap text-center text-sm font-medium'>";
                                echo "<div class='flex justify-center items-center gap-4'>";
                                echo "<button type='submit' name='update_donation' class='flex items-center gap-1 text-blue-600 hover:text-blue-800 font-semibold'><i data-feather='save' class='w-4 h-4'></i>Save</button>";
                                echo "</form>"; // End update form
                                
                                echo "<form action='' method='POST' onsubmit='return confirm(\"Are you sure you want to delete this donation type?\");'>";
                                echo "<input type='hidden' name='donation_id' value='" . $row["id"] . "'>";
                                echo "<button type='submit' name='delete_donation' class='flex items-center gap-1 text-red-600 hover:text-red-800 font-semibold'><i data-feather='trash-2' class='w-4 h-4'></i>Delete</button>";
                                echo "</form>"; // End delete form
                                echo "</div>";
                                echo "</td>";

                                echo "</tr>";
                            }
                        } else {
                            // Updated colspan to 4 for the new layout
                            echo "<tr><td colspan='4' class='text-center px-6 py-10 text-gray-500'><div class='flex flex-col items-center gap-2'><i data-feather='info' class='w-8 h-8 text-gray-400'></i><span>No donation types found. Add one using the form above.</span></div></td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 700,
            once: true,
            easing: 'ease-in-out-cubic'
        });
        feather.replace();
    </script>
</body>
</html>