<?php
// Include the database configuration file.
// This file provides the $pdo connection object.
include '../config/db.php';

// --- INITIALIZE VARIABLES ---
$message = '';
$message_type = '';

// --- HANDLE FORM SUBMISSION (CREATE EXPENSE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_expense'])) {
    // Sanitize and retrieve form data
    $item_name = trim(filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING));
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $category = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING));
    $expense_date = trim(filter_input(INPUT_POST, 'expense_date', FILTER_SANITIZE_STRING));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));

    // Basic validation
    if (empty($item_name) || $amount === false || $amount <= 0 || empty($category) || empty($expense_date)) {
        $message = "Please fill in all required fields with valid data.";
        $message_type = 'error';
    } else {
        try {
            // Use the $pdo connection object from db.php
            $sql = "INSERT INTO expenses (item_name, amount, category, expense_date, description) VALUES (:item_name, :amount, :category, :expense_date, :description)";
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':item_name', $item_name);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':expense_date', $expense_date);
            $stmt->bindParam(':description', $description);

            $stmt->execute();
            $message = "Expense added successfully!";
            $message_type = 'success';
        } catch(PDOException $e) {
            $message = "Error adding expense: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- FETCH DATA FOR DISPLAY ---
try {
    // Use the $pdo connection object from db.php
    // Fetch all expenses for the history table
    $stmt_select = $pdo->prepare("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
    $stmt_select->execute();
    $expenses = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

    // Fetch total expenses for the summary card
    $stmt_total = $pdo->prepare("SELECT SUM(amount) as total FROM expenses");
    $stmt_total->execute();
    $total_expenses = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Fetch expenses from the last 30 days
    $stmt_monthly = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt_monthly->execute();
    $monthly_expenses = $stmt_monthly->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Fetch total number of entries
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as count FROM expenses");
    $stmt_count->execute();
    $total_entries = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    // A single catch block to handle any database query failures
    die("<div style='font-family: Arial, sans-serif; text-align: center; padding: 50px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;'>
            <h2>Data Fetching Failed</h2>
            <p>Could not retrieve data from the database.</p>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashram Expense Management</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- AOS Animation Library CDN -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <style>
        /* Custom styles to enhance Tailwind */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8; /* Light blue-gray background */
        }
        .title-font {
            font-family: 'Playfair Display', serif;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #0d9488; /* Teal-600 */
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #0f766e; /* Teal-700 */
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="container mx-auto p-4 md:p-8">

        <!-- Header -->
        <header class="text-center mb-10" data-aos="fade-down">
            <h1 class="text-4xl md:text-5xl font-bold text-teal-700 title-font">Ashram Expense Management</h1>
            <p class="text-gray-500 mt-2 text-lg">A centralized dashboard to track and manage all ashram expenditures.</p>
        </header>

        <!-- Notification Message -->
        <?php if ($message): ?>
        <div data-aos="fade-in" class="mb-6 p-4 rounded-lg text-white <?php echo $message_type === 'success' ? 'bg-teal-500' : 'bg-red-500'; ?>">
            <i class="fa-solid <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-teal-500" data-aos="fade-up">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Expenses</p>
                        <p class="text-3xl font-bold text-gray-800">₹<?php echo number_format($total_expenses, 2); ?></p>
                    </div>
                    <div class="bg-teal-100 text-teal-600 p-4 rounded-full">
                        <i class="fa-solid fa-wallet fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-cyan-500" data-aos="fade-up" data-aos-delay="100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Last 30 Days</p>
                        <p class="text-3xl font-bold text-gray-800">₹<?php echo number_format($monthly_expenses, 2); ?></p>
                    </div>
                    <div class="bg-cyan-100 text-cyan-600 p-4 rounded-full">
                        <i class="fa-solid fa-calendar-days fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-amber-500" data-aos="fade-up" data-aos-delay="200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Entries</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_entries; ?></p>
                    </div>
                    <div class="bg-amber-100 text-amber-600 p-4 rounded-full">
                        <i class="fa-solid fa-file-invoice fa-2x"></i>
                    </div>
                </div>
            </div>
        </section>


        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Add Expense Form -->
            <div class="lg:col-span-1" data-aos="fade-right">
                <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl sticky top-8">
                    <h2 class="text-2xl font-bold mb-6 text-teal-700 flex items-center">
                        <i class="fa-solid fa-plus-circle mr-3"></i> Add New Expense
                    </h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="POST" class="space-y-5">
                        <div>
                            <label for="item_name" class="block text-sm font-medium text-gray-600 mb-1">Item/Service Name</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <i class="fa-solid fa-tag"></i>
                                </span>
                                <input type="text" id="item_name" name="item_name" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition" placeholder="e.g., Groceries" required>
                            </div>
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-600 mb-1">Amount (₹)</label>
                             <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <i class="fa-solid fa-indian-rupee-sign"></i>
                                </span>
                                <input type="number" id="amount" name="amount" step="0.01" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition" placeholder="e.g., 1500.50" required>
                            </div>
                        </div>
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-600 mb-1">Category</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <i class="fa-solid fa-layer-group"></i>
                                </span>
                                <select id="category" name="category" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition appearance-none" required>
                                    <option value="" disabled selected>Select a category</option>
                                    <option value="Food & Groceries">Food & Groceries</option>
                                    <option value="Utilities">Utilities (Electricity, Water)</option>
                                    <option value="Maintenance & Repairs">Maintenance & Repairs</option>
                                    <option value="Religious Ceremonies">Religious Ceremonies</option>
                                    <option value="Salaries & Wages">Salaries & Wages</option>
                                    <option value="Health & Medical">Health & Medical</option>
                                    <option value="Travel">Travel</option>
                                    <option value="Office Supplies">Office Supplies</option>
                                    <option value="Miscellaneous">Miscellaneous</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label for="expense_date" class="block text-sm font-medium text-gray-600 mb-1">Date of Expense</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <i class="fa-solid fa-calendar"></i>
                                </span>
                                <input type="date" id="expense_date" name="expense_date" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition" required>
                            </div>
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-600 mb-1">Description (Optional)</label>
                            <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition" placeholder="Add a short note..."></textarea>
                        </div>
                        <button type="submit" name="add_expense" class="w-full bg-teal-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-transform transform hover:scale-105 flex items-center justify-center">
                            <i class="fa-solid fa-paper-plane mr-2"></i> Submit Expense
                        </button>
                    </form>
                </div>
            </div>

            <!-- Expense History Table -->
            <div class="lg:col-span-2" data-aos="fade-left">
                <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl">
                    <h2 class="text-2xl font-bold mb-6 text-teal-700 flex items-center">
                        <i class="fa-solid fa-history mr-3"></i> Expense History
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-600">
                            <thead class="text-xs text-teal-800 uppercase bg-teal-100 rounded-t-lg">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Item</th>
                                    <th scope="col" class="px-6 py-3">Amount</th>
                                    <th scope="col" class="px-6 py-3">Category</th>
                                    <th scope="col" class="px-6 py-3">Date</th>
                                    <th scope="col" class="px-6 py-3">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenses)): ?>
                                    <tr class="bg-white border-b">
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            <i class="fa-solid fa-folder-open fa-2x mb-2"></i><br>
                                            No expenses recorded yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenses as $expense): ?>
                                    <tr class="bg-white border-b hover:bg-gray-50 transition">
                                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($expense['item_name']); ?>
                                        </th>
                                        <td class="px-6 py-4 font-semibold text-red-600">
                                            ₹<?php echo number_format($expense['amount'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-medium text-cyan-800 bg-cyan-100 rounded-full">
                                                <?php echo htmlspecialchars($expense['category']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php echo date("d M, Y", strtotime($expense['expense_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 max-w-xs truncate" title="<?php echo htmlspecialchars($expense['description']); ?>">
                                            <?php echo htmlspecialchars($expense['description']) ?: '<span class="text-gray-400">N/A</span>'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AOS Animation Library JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800, // animation duration
            once: true,    // whether animation should happen only once - while scrolling down
        });
    </script>
</body>
</html>
