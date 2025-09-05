<?php
// Make sure this path is correct and points to your database connection file.
require_once '../config/db.php';

// Fetch all donations from the 'general_donations' table
try {
    $stmt = $pdo->query("SELECT id, invoice_no, devotee_name, donation_type, amount, created_at FROM general_donations ORDER BY created_at DESC");
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // A simple way to handle DB error, you might want a more robust solution
    die("Error fetching donations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All General Donations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen p-4 sm:p-6 md:p-8">

    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6" data-aos="fade-down">
            <h1 class="text-3xl font-bold text-gray-800">Donation Records</h1>
            <a href="utsav_donation.php" class="mt-4 sm:mt-0 bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition text-lg">
                <i class="fas fa-plus mr-2"></i>Add New Donation
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-700 uppercase">Invoice No</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-700 uppercase">Date</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-700 uppercase">Devotee Name</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-700 uppercase">Donation Type</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-700 uppercase text-right">Amount (₹)</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-700 uppercase text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($donations)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-10 text-gray-500">
                                    <i class="fas fa-folder-open fa-3x mb-3"></i>
                                    <p>No donations have been recorded yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($donations as $donation): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800"><?= htmlspecialchars($donation['invoice_no']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= date('d-m-Y', strtotime($donation['created_at'])) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= htmlspecialchars($donation['devotee_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= htmlspecialchars($donation['donation_type']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-800 text-right"><?= number_format($donation['amount'], 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <a href="utsav_donation.php?view_invoice=1&id=<?= $donation['id'] ?>" class="bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition text-sm">
                                            <i class="fas fa-eye mr-1"></i> View Invoice
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init({
          duration: 800,
          once: true
      });
    </script>
</body>
</html>
