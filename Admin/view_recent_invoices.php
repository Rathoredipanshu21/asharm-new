<?php
/*
================================================================================
 VIEW RECENT INVOICES - DISPLAY A LIST OF INVOICES FROM THE LAST 3 DAYS
================================================================================
*/
session_start();
require_once '../config/db.php'; // Ensure this path is correct

// --- Calculate the date 3 days ago from the current time ---
$three_days_ago = date('Y-m-d H:i:s', strtotime('-3 days'));

// --- Fetch all invoices created within the last 3 days ---
$stmt = $pdo->prepare("
    SELECT
        di.id,
        di.invoice_no,
        di.total_amount,
        di.created_at,
        di.period,
        f.family_code,
        fm_head.name as head_name
    FROM donation_invoices di
    JOIN families f ON di.family_id = f.id
    LEFT JOIN family_members fm_head ON f.head_of_family_id = fm_head.id
    WHERE di.created_at >= ? AND di.invoice_no LIKE 'PNI-%'
    ORDER BY di.created_at DESC
");
$stmt->execute([$three_days_ago]);
$recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Pranami Invoices (Last 3 Days)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6; /* A light gray background */
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 border-b pb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Recent Pranami Invoices</h1>
                <p class="text-gray-500">Showing all Pranami invoices generated in the last 3 days.</p>
            </div>
            <a href="pranami.php" class="mt-4 md:mt-0 bg-gray-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Entry Page
            </a>
        </div>

        <?php if (empty($recent_invoices)): ?>
            <div class="text-center py-12">
                <i class="fas fa-file-invoice-dollar fa-4x text-gray-300"></i>
                <h2 class="mt-4 text-xl font-semibold text-gray-700">No Pranami Invoices Found</h2>
                <p class="text-gray-500 mt-1">There have been no Pranami invoices generated in the last 3 days.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Family Head</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Family Code</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-semibold text-gray-900"><?= htmlspecialchars($invoice['invoice_no']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?= date('d-m-Y h:i A', strtotime($invoice['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    <?= htmlspecialchars($invoice['head_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($invoice['family_code']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-lg">
                                    ₹<?= number_format($invoice['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="view_pranami_invoice.php?id=<?= $invoice['id'] ?>" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-md hover:bg-indigo-700 transition text-sm">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>