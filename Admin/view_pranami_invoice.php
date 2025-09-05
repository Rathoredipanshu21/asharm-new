<?php
/*
================================================================================
 VIEW PRANAMI INVOICE - DISPLAY & PRINT
================================================================================
*/
session_start();
require_once '../config/db.php'; // Ensure this path is correct

// --- Fetch Logged-in Employee Data ---
$employee_data = null;
if (isset($_SESSION['user'])) {
    $loggedInUserName = $_SESSION['user'];
    $stmt_emp = $pdo->prepare("SELECT full_name, job_title, department FROM employees WHERE full_name = ?");
    $stmt_emp->execute([$loggedInUserName]);
    $employee_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);
}
// Fallback data if no employee is logged in or found.
if (!$employee_data) {
    $employee_data = [
        'full_name' => isset($_SESSION['user']) ? $_SESSION['user'] : 'System',
        'job_title' => 'N/A',
        'department' => ''
    ];
}

$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    die("No invoice ID provided.");
}

// Helper function to convert a number to its word representation (integers only).
function numberToWords($number) {
    $no = floor($number);
    $hundred = null;
    $digits_1 = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        '0' => '', '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
        '7' => 'Seven', '8' => 'Eight', '9' => 'Nine', '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
        '13' => 'Thirteen', '14' => 'Fourteen', '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
        '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty', '30' => 'Thirty', '40' => 'Forty',
        '50' => 'Fifty', '60' => 'Sixty', '70' => 'Seventy', '80' => 'Eighty', '90' => 'Ninety'
    );
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    while ($i < $digits_1) {
        $divider = ($i == 2) ? 10 : 100;
        $number_part = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number_part) {
            $counter = count($str);
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str[] = ($number_part < 21) ? $words[$number_part] . " " . $digits[$counter] . $hundred : $words[floor($number_part / 10) * 10] . " " . $words[$number_part % 10] . " " . $digits[$counter] . $hundred;
        } else {
            $str[] = null;
        }
    }
    $rupees = implode('', array_reverse($str));
    $result = trim(preg_replace('/\s+/', ' ', $rupees));
    return empty($result) ? "Zero Rupees Only" : ucwords($result) . " Rupees Only";
}


// --- Fetch Invoice, Period, and Family Data ---
$stmt = $pdo->prepare("
    SELECT
        di.invoice_no, di.total_amount, di.created_at, di.period,
        f.family_code,
        fm_head.name as head_name,
        fm_head.address as head_address
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

// --- Fetch Donation Items for this invoice ---
$stmt_items = $pdo->prepare("
    SELECT
        fm.name as member_name,
        r.name as ritwik_name,
        di.amount
    FROM donation_items di
    JOIN family_members fm ON di.member_id = fm.id
    LEFT JOIN ritwiks r ON fm.ritwik_id = r.id
    WHERE di.invoice_id = ?
");
$stmt_items->execute([$invoice_id]);
$donation_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pranami Invoice <?= htmlspecialchars($invoice_data['invoice_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* General Styles */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #e5e7eb;
        }
        /* Print Styles */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0 !important;
            }
            html, body {
                width: 100%;
                height: 100%;
                margin: 0 !important;
                padding: 0 !important;
                background-color: white;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area {
                position: absolute;
                left: 0; top: 0;
                width: 100%; height: auto; min-height: 100%;
                padding: 1cm; margin: 0;
                font-size: 9pt;
                box-sizing: border-box;
                box-shadow: none;
                border-radius: 0;
                display: flex;
                flex-direction: column;
            }
            .no-print { display: none !important; }
            #print-area header h1 { font-size: 13pt; }
            #print-area header h2 { font-size: 11pt; }
            #print-area table { font-size: 8pt; width: 100%; }
            #print-area th, #print-area td { padding: 3px 4px; }
            #print-area tfoot { background-color: #f3f4f6 !important; }
        }
    </style>
</head>
<body class="flex flex-col items-center p-4">

    <div id="print-area" class="w-full max-w-7xl bg-white p-6 rounded-lg shadow-lg flex flex-col flex-grow">
        <header class="mb-4">
            <div class="flex justify-between items-start">
                <div class="w-28"> <img src="Assets/satsang.png" alt="Logo" class="h-28 w-28">
                </div>
                <div class="text-center flex-grow px-4">
                    <h1 class="text-2xl font-bold text-gray-800">R. S. रा. स्वा.</h1>
                    <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM</h1>
                    <h2 class="text-xl font-semibold text-gray-700">SURYADIH</h2>
                    <p class="text-base">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                    <div class="w-full text-center text-sm font-semibold py-1">
                        Reg. No. 2024/GOV/6510/BK4/437
                    </div>
                    <p class="text-xl font-bold mt-2 text-indigo-700 border-2 border-indigo-700 inline-block px-4 py-1">PRANAMI INVOICE</p>
                </div>
                <div class="w-28"></div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-base mt-4 border-t-2 border-b-2 border-gray-400 py-2">
                <div>
                    <p><strong>Family Head:</strong> <?= htmlspecialchars($invoice_data['head_name']) ?></p>
                    <p><strong>Family Code:</strong> <?= htmlspecialchars($invoice_data['family_code']) ?></p>
                </div>
                <div class="text-right">
                    <p><strong>Invoice No:</strong> <?= htmlspecialchars($invoice_data['invoice_no']) ?></p>
                    <p><strong>Date:</strong> <?= date('d-m-Y', strtotime($invoice_data['created_at'])) ?></p>
                    <p><strong>Period:</strong> <span class="font-bold text-indigo-600"><?= htmlspecialchars($invoice_data['period']) ?></span></p>
                </div>
            </div>
        </header>

        <div class="overflow-x-auto flex-grow">
            <table class="min-w-full divide-y divide-gray-200 text-base">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Devotee</th>
                        <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Ritwik</th>
                        <th class="px-3 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">Pranami Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($donation_items as $item): ?>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap font-medium"><?= htmlspecialchars($item['member_name']) ?></td>
                            <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($item['ritwik_name'] ?? 'N/A') ?></td>
                            <td class="px-2 py-2 text-center"><?= number_format($item['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-200 font-bold">
                    <tr>
                        <td colspan="2" class="px-3 py-3 text-right text-lg">Grand Total</td>
                        <td class="px-3 py-3 text-center text-lg"><?= number_format($invoice_data['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6 pt-4 border-t-2 border-dashed border-gray-400">
            <div class="flex justify-between items-start">
                <div class="w-2/3">
                     <p class="font-semibold">In Words: <span class="font-normal">
                          <?= numberToWords($invoice_data['total_amount']); ?>
                     </span></p>
                </div>
                <div class="w-1/3 text-right">
                     <p class="text-lg font-bold">Grand Total: <span class="text-2xl">₹<?= number_format($invoice_data['total_amount'], 2) ?></span></p>
                </div>
            </div>
        </div>

        <div class="mt-auto pt-12">
            <div class="flex justify-between items-end text-sm">
                 <div class="w-1/3 text-left">
                    <p>TO</p>
                    <p class="font-bold mt-1"><?= htmlspecialchars($invoice_data['head_name']) ?></p>
                 </div>
                 <div class="w-1/3 text-center">
                     <?php if ($employee_data): ?>
                        <p class="font-semibold">Prepared by:</p>
                        <p class="font-bold"><?= htmlspecialchars($employee_data['full_name']) ?></p>
                        <p><?= htmlspecialchars($employee_data['job_title']) ?></p>
                     <?php endif; ?>
                 </div>
                 <div class="w-1/3 text-center font-semibold">
                     <p>FOR YOUR BELOVED DIETY FATHER</p>
                     <p>'I' </p>                    
                     <p>SRI SRI THAKUR </p>                    
                 </div>
            </div>
        </div>
    </div>

    <div class="mt-6 w-full max-w-7xl flex justify-end space-x-4 no-print">
        <a href="pranami.php" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg">
            <i class="fas fa-arrow-left mr-2"></i>New Entry
        </a>
        <button type="button" onclick="window.print()" class="bg-blue-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition text-lg">
            <i class="fas fa-print mr-2"></i>Print Invoice
        </button>
    </div>

</body>
</html>
