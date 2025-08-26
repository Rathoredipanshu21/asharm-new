<?php
/*
================================================================================
 VIEW INVOICE - DISPLAY & PRINT
================================================================================
*/
require_once '../config/db.php'; // Make sure this path is correct

$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    die("No invoice ID provided.");
}

// Fetch Invoice and Family Data
$stmt = $pdo->prepare("
    SELECT 
        di.invoice_no, di.total_amount, di.created_at,
        f.family_code, fm_head.name as head_name
    FROM donation_invoices di
    JOIN families f ON di.family_id = f.id
    LEFT JOIN family_members fm_head ON f.head_of_family_id = fm_head.id
    WHERE di.id = ?
");
$stmt->execute([$invoice_id]);
$invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice_data) {
    die("Invoice not found.");
}

// Fetch Donation Items for this invoice
$stmt_items = $pdo->prepare("
    SELECT 
        fm.name as member_name,
        r.name as ritwik_name,
        dt.name as donation_type_name,
        di.amount
    FROM donation_items di
    JOIN family_members fm ON di.member_id = fm.id
    JOIN donation_types dt ON di.donation_type_id = dt.id
    LEFT JOIN ritwiks r ON fm.ritwik_id = r.id
    WHERE di.invoice_id = ?
");
$stmt_items->execute([$invoice_id]);
$donation_items_raw = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Fetch all donation types to build the table structure
$donation_types = $pdo->query("SELECT name FROM donation_types ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

// Process items into a structured array for easy display
$structured_donations = [];
foreach ($donation_items_raw as $item) {
    $structured_donations[$item['member_name']]['ritwik_name'] = $item['ritwik_name'];
    $structured_donations[$item['member_name']]['donations'][$item['donation_type_name']] = $item['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice_data['invoice_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background-color: #e5e7eb; }
        @media print {
            body { background-color: white; }
            .no-print { display: none; }
            #print-area {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body class="flex flex-col items-center p-4">

    <div id="print-area" class="w-full max-w-5xl bg-white p-6 sm:p-8 rounded-lg shadow-lg">
        <!-- Header -->
        <header class="text-center mb-6 border-b pb-4">
            <div class="flex items-center justify-between">
                <img src="path/to/your/logo.png" alt="Logo" class="h-24 w-24" onerror="this.style.display='none'">
                <div class="flex-grow">
                    <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM SURYADIH</h1>
                    <p class="text-base">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                    <p class="text-xl font-semibold mt-2 text-indigo-700">ARGHYA PRADAN</p>
                </div>
                <div class="text-sm text-right w-56">
                    <p class="font-bold">YOUR BELOVED DIETY FATHER 'I' SRI SRI THAKUR</p>
                    <p>SSTACSA SURYADIH</p>
                </div>
            </div>
            <div class="flex justify-between text-base mt-6">
                <p><strong>Family Name:</strong> <?= htmlspecialchars($invoice_data['head_name']) ?></p>
                <p><strong>Family Code:</strong> <?= htmlspecialchars($invoice_data['family_code']) ?></p>
                <p><strong>Invoice No:</strong> <?= htmlspecialchars($invoice_data['invoice_no']) ?></p>
                <p><strong>Date:</strong> <?= date('d-m-Y', strtotime($invoice_data['created_at'])) ?></p>
            </div>
        </header>

        <!-- Donation Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-base">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Devotee</th>
                        <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Ritwik</th>
                        <?php foreach ($donation_types as $type_name): ?>
                            <th class="px-2 py-3 text-center font-medium text-gray-600 uppercase tracking-wider"><?= htmlspecialchars($type_name) ?></th>
                        <?php endforeach; ?>
                        <th class="px-3 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $column_totals = array_fill_keys($donation_types, 0);
                    foreach ($structured_donations as $member_name => $data): 
                        $member_total = 0;
                    ?>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap font-medium"><?= htmlspecialchars($member_name) ?></td>
                            <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($data['ritwik_name'] ?? 'N/A') ?></td>
                            <?php foreach ($donation_types as $type_name): 
                                $amount = $data['donations'][$type_name] ?? 0;
                                $member_total += $amount;
                                $column_totals[$type_name] += $amount;
                            ?>
                                <td class="px-2 py-2 text-center"><?= $amount > 0 ? number_format($amount, 2) : '-' ?></td>
                            <?php endforeach; ?>
                            <td class="px-3 py-2 text-center font-bold"><?= number_format($member_total, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-200 font-bold">
                    <tr>
                        <td colspan="2" class="px-3 py-3 text-right">Column Totals</td>
                        <?php foreach ($donation_types as $type_name): ?>
                            <td class="px-2 py-3 text-center"><?= number_format($column_totals[$type_name], 2) ?></td>
                        <?php endforeach; ?>
                        <td class="px-3 py-3 text-center text-xl"><?= number_format($invoice_data['total_amount'], 2) ?></td>
                    </tr>
                     <tr class="bg-gray-300">
                        <td colspan="2" class="px-3 py-3 text-right text-lg">
                            No. of Persons: <span><?= count($structured_donations) ?></span>
                        </td>
                        <td colspan="<?= count($donation_types) ?>" class="px-3 py-3 text-right text-lg">Grand Total</td>
                        <td class="px-3 py-3 text-center text-2xl">₹<?= number_format($invoice_data['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-6 w-full max-w-5xl flex justify-end space-x-4 no-print">
        <a href="invoice_list.php" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
        <button type="button" onclick="window.print()" class="bg-blue-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition text-lg">
            <i class="fas fa-print mr-2"></i>Print Invoice
        </button>
    </div>

</body>
</html>
