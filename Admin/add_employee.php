<?php
// --- DATABASE CONNECTION AND DATA HANDLING ---

// Include the database connection file
include '../config/db.php';

// Initialize variables for messages
$success_message = '';
$error_message = '';
$fetch_error = '';
$employees = [];

// Check for a valid database connection object ($conn)
if (!isset($conn) || $conn->connect_error) {
    // Set a general error message if connection fails
    $error_message = "Error connecting to the database. Please try again later.";
    // For debugging only: if(isset($conn)) { die("Connection failed: " . $conn->connect_error); } else { die("Connection object not found."); }
} else {

    // --- HANDLE ADD EMPLOYEE FORM SUBMISSION (POST REQUEST) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // Get form data and sanitize it
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $phone = htmlspecialchars(trim($_POST['phone']));
        $job_title = htmlspecialchars(trim($_POST['job_title']));
        $department = htmlspecialchars(trim($_POST['department']));
        $emp_password = $_POST['password']; // Storing password as plain text as requested

        // Basic validation
        if (empty($full_name) || empty($email) || empty($job_title) || empty($department) || empty($emp_password)) {
            $error_message = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Prepare an SQL statement to prevent SQL injection
            $stmt = $conn->prepare("INSERT INTO employees (full_name, email, phone, job_title, department, password) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt === false) {
                $error_message = "Error preparing the database query.";
            } else {
                // Bind parameters to the prepared statement
                $stmt->bind_param("ssssss", $full_name, $email, $phone, $job_title, $department, $emp_password);

                // Execute the statement
                if ($stmt->execute()) {
                    $success_message = "New employee record created successfully!";
                } else {
                    $error_message = "Error executing the query. The email or phone might already exist.";
                }
                // Close the statement
                $stmt->close();
            }
        }
    }

    // --- FETCH ALL EMPLOYEES FOR DISPLAY ---
    // This runs on every page load (both GET and POST requests)
    $sql = "SELECT id, full_name, email, phone, job_title, department FROM employees ORDER BY id DESC";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    } else {
        $fetch_error = "Error fetching employee data: " . $conn->error;
    }

    // Close the connection at the end of all database operations
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Employee Management</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom focus styles for better accessibility and design */
        .form-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px #3b82f6; /* blue-500 */
            border-color: #3b82f6;
        }
        .form-input-icon {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            color: #6b7280; /* gray-500 */
        }
        /* Ensure modal is on top */
        .modal {
            z-index: 50;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4 sm:mb-0">Employee Management</h1>
            <button id="openModalBtn" class="w-full sm:w-auto bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition duration-300 ease-in-out transform hover:-translate-y-1 flex items-center justify-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Add New Employee</span>
            </button>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert" data-aos="fade-left">
                <p class="font-bold">Success</p>
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert" data-aos="fade-left">
                <p class="font-bold">Error</p>
                <p><?php echo $error_message; ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($fetch_error)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg mb-6" role="alert" data-aos="fade-left">
                <p class="font-bold">Warning</p>
                <p><?php echo $fetch_error; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden" data-aos="fade-up">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($employees) && empty($fetch_error)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    No employees found. Click "Add New Employee" to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($employee['email']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($employee['job_title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($employee['department']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="employeeModal" class="modal fixed inset-0 bg-gray-900 bg-opacity-60 overflow-y-auto h-full w-full hidden flex items-center justify-center p-4">
        <div class="w-full max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-2xl p-8 md:p-12 relative" data-aos="fade-up">
                
                <button id="closeModalBtn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>

                <div class="text-center mb-10">
                    <h1 class="text-4xl font-bold text-gray-800">Add New Employee</h1>
                    <p class="text-gray-500 mt-2">Enter the details below to create a new employee profile.</p>
                </div>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                    <div class="relative">
                        <span class="form-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                        <input type="text" id="full_name" name="full_name" required class="form-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg transition duration-300" placeholder="Full Name">
                    </div>
                    <div class="relative">
                        <span class="form-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                        <input type="email" id="email" name="email" required class="form-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg transition duration-300" placeholder="Email Address">
                    </div>
                    <div class="relative">
                        <span class="form-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                        <input type="tel" id="phone" name="phone" class="form-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg transition duration-300" placeholder="Phone Number (Optional)">
                    </div>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="relative">
                            <span class="form-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg></span>
                            <input type="text" id="job_title" name="job_title" required class="form-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg transition duration-300" placeholder="Job Title">
                        </div>
                        <div class="relative">
                            <span class="form-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/><path d="m3 9-1.5 3a2 2 0 0 0 2 2.5h17a2 2 0 0 0 2-2.5L21 9"/><path d="M12 13v-1a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v1"/></svg></span>
                            <input type="text" id="department" name="department" required class="form-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg transition duration-300" placeholder="Department">
                        </div>
                    </div>
                    <div class="relative">
                        <span class="form-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                        <input type="password" id="password" name="password" required class="form-input w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg transition duration-300" placeholder="Create a Password">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition duration-300 ease-in-out transform hover:-translate-y-1">
                            Add Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800, // animation duration
            once: true,    // whether animation should happen only once
        });

        // Modal Handling Script
        const modal = document.getElementById('employeeModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');

        const openModal = () => modal.classList.remove('hidden');
        const closeModal = () => modal.classList.add('hidden');

        openModalBtn.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal);

        // Close modal if user clicks outside of the modal content
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>

</body>
</html>