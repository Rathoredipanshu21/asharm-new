<?php
/*
================================================================================
 VIEW INVOICE - DISPLAY & PRINT (REVISED)
================================================================================
*/
session_start(); // Start the session to access logged-in user data
require_once '../config/db.php'; // Make sure this path is correct

// --- Fetch Logged-in Employee Data ---
// CORRECTED: Fetches details using the 'full_name' stored in $_SESSION['user'] from the login process.
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

// This function converts a number to its word representation (integers only).
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
    $rupees = implode(' ', array_reverse($str));
    $result = trim(preg_replace('/\s+/', ' ', $rupees));
    if (empty($result)) {
        return "Zero Rupees Only";
    }
    return ucwords($result) . " Rupees Only";
}


// Fetch Invoice and Family Data
$stmt = $pdo->prepare("
    SELECT
        di.invoice_no, di.total_amount, di.created_at,
        f.family_code,
        fm_head.name as head_name,
        fm_head.father_name as head_father_name,
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
$donation_types = $pdo->query("SELECT name, name_hindi FROM donation_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$donation_type_names = array_column($donation_types, 'name'); // Helper array for keys

// Process items into a structured array for easy display
$structured_donations = [];
foreach ($donation_items_raw as $item) {
    $member_name = $item['member_name'];
    if (!isset($structured_donations[$member_name])) {
        $structured_donations[$member_name] = [
            'ritwik_name' => $item['ritwik_name'],
            'donations' => array_fill_keys($donation_type_names, 0) // Initialize all donation types with 0
        ];
    }
    $structured_donations[$member_name]['donations'][$item['donation_type_name']] = $item['amount'];
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
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #e5e7eb;
        }

        /* --- Logo Styles (Adjusted) --- */
        .logo-container {
            width: 112px; /* Fixed width for the container, matches h-28 w-28 */
            height: 112px; /* Fixed height for the container */
            flex-shrink: 0; /* Prevent it from shrinking */
            display: flex; /* Use flex to center image if it's smaller */
            align-items: center;
            justify-content: center;
        }
        .logo-container img {
            max-width: 100%; /* Ensure it fits within its container */
            max-height: 100%; /* Ensure it fits within its container */
            width: auto; /* Allow image to use its original width */
            height: auto; /* Allow image to use its original height */
            object-fit: contain; /* Ensure the image is contained within the element's box while maintaining its aspect ratio */
        }
        /* Fallback for broken image */
        .logo-fallback {
            background-color: #e2e8f0; /* gray-200 */
            color: #64748b; /* gray-500 */
            font-size: 0.875rem; /* text-sm */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* --- PRINT STYLES (REVISED FOR SINGLE PAGE) --- */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0 !important; /* Remove all printer margins */
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
            body * { visibility: hidden; } /* Hide everything by default */
            #print-area, #print-area * { visibility: visible; } /* Then, show only the print area and its children */

            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: auto;
                min-height: 100%;
                padding: 1cm; /* Add internal padding so content isn't cut off by printer hardware margins */
                margin: 0;
                font-size: 9pt;
                box-sizing: border-box;
                box-shadow: none;
                border-radius: 0;
                display: flex;
                flex-direction: column;
            }

            #print-area .overflow-x-auto {
                overflow: visible !important;
            }

            /* Hide elements not meant for printing */
            .no-print { display: none !important; }

            /* Font and layout adjustments for print */
            #print-area header { margin-bottom: 0.5rem !important; } /* NEW: Reduced space after header */
            #print-area header h1 { font-size: 13pt; }
            #print-area header h2 { font-size: 11pt; }
            #print-area header p { font-size: 9pt; line-height: 1.2; margin-bottom: 2px; } /* NEW: Tightened paragraph spacing */
            #print-area .header-details { font-size: 9pt; margin-top: 0.5rem !important; padding: 0.25rem 0 !important; } /* NEW: Condensed header details section */
            #print-area table { font-size: 8pt; width: 100%; }
            #print-area th, #print-area td { padding: 3px 4px; }
            #print-area tfoot { background-color: #f3f4f6 !important; }

            /* NEW: Reduce space before summary section */
            #summary-section {
                margin-top: 1rem !important;
                padding-top: 0.5rem !important;
            }

            /* NEW: Drastically reduce space before the footer to pull it up */
            #footer-section {
                margin-top: auto; /* This keeps it at the bottom of the content flow */
                padding-top: 1.5rem !important; /* Reduced from pt-12 (3rem) */
            }

            /* Ensure logo prints correctly */
            #print-area .logo-container {
                width: 112px;
                height: 112px;
            }
            #print-area .logo-container img {
                width: auto;
                height: auto;
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
            }
            #print-area .logo-fallback {
                width: 112px;
                height: 112px;
            }
        }
    </style>
</head>
<body class="flex flex-col items-center p-4">

    <div id="print-area" class="w-full max-w-7xl bg-white p-6 rounded-lg shadow-lg flex flex-col flex-grow">
        <header class="mb-4">
            <div class="flex justify-between items-start">
                <div class="logo-container">
                    <img src="Assets/satsang.png" alt="Logo" class="block" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'logo-fallback\'>Logo</div>';">
                </div>
                <div class="text-center flex-grow px-4">
                    <h1 class="text-2xl font-bold text-gray-800">R. S. रा. स्वा.</h1>
                    <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM</h1>
                    <h2 class="text-xl font-semibold text-gray-700">SURYADIH</h2>
                    <p class="text-xs">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                    <p class="text-xs">পরম প্রেমময় শ্রী শ্রী ঠাকুর অনুকূলচন্দ্রের ইষ্টপ্রাণ গণসংহতি উম্মাদনাকে পোষণ ও পরিবন্ধনে বাস্তবে পরিণত করিয়া কৃতার্থ হইবার আগ্রহে তৎপ্রীত্যর্থে তদীলিত পনসেবাপ্রস্ প্রতিষ্ঠানাদি গঠনকল্পে আপনার স্বতঃস্বেচ্ছ অর্ঘ্য-অবদান পরম শ্রদ্ধায় ও সাদরে গৃহীত হইল</p>
                    <p class="text-xs">Your autoinitiative voluntary, reverential offering to the Love-lord Sree Sree Thakur Anukulchandra's mission with the intent of being blessed for materialisation of exuberance of concentric integrated people in their being and becoming by setting up philanthropic institutions of His desire for his pleasure is received with esteem and acquiesence.</p>
                    <div class="w-full text-center text-sm font-semibold py-1">
                        Reg. No. 2024/GOV/6510/BK4/437
                    </div>
                    <p class="text-xl font-bold mt-2 text-indigo-700 border-2 border-indigo-700 inline-block px-4 py-1">ARGHYA AWADHAN</p>
                </div>
                <div class="w-28"> </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-base mt-4 border-t-2 border-b-2 border-gray-400 py-2 header-details">
                <div>
                    <p><strong>Family Head:</strong> <?= htmlspecialchars($invoice_data['head_name']) ?></p>

                    <p><strong>Family Code:</strong> <?= htmlspecialchars($invoice_data['family_code']) ?></p>
                </div>
                <div class="text-right">
                    <p><strong>Invoice No:</strong> <?= htmlspecialchars($invoice_data['invoice_no']) ?></p>
                    <p><strong>Date:</strong> <?= date('d-m-Y', strtotime($invoice_data['created_at'])) ?></p>
                </div>
            </div>
        </header>

        <div class="overflow-x-auto flex-grow">
            <table class="min-w-full divide-y divide-gray-200 text-base">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Devotee<br>नाम / शिष्य</th>
                        <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">RITWIK<br>ऋत्विक</th>
                        <?php foreach ($donation_types as $type): ?>
                            <th class="px-2 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">
                                <?= htmlspecialchars($type['name']) ?>
                                <?php if (!empty($type['name_hindi'])): ?>
                                    <br><span class="normal-case"><?= htmlspecialchars($type['name_hindi']) ?></span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th class="px-3 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $column_totals = array_fill_keys($donation_type_names, 0);
                    foreach ($structured_donations as $member_name => $data):
                        $member_total = 0;
                    ?>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap font-medium"><?= htmlspecialchars($member_name) ?></td>
                            <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($data['ritwik_name'] ?? 'N/A') ?></td>
                            <?php foreach ($donation_types as $type):
                                $type_name = $type['name'];
                                $amount = $data['donations'][$type_name] ?? 0;
                                $member_total += $amount;
                                $column_totals[$type_name] += $amount;
                            ?>
                                <td class="px-2 py-2 text-center"><?= $amount > 0 ? number_format(round($amount), 2) : '-' ?></td>
                            <?php endforeach; ?>
                            <td class="px-3 py-2 text-center font-bold"><?= number_format(round($member_total), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-200 font-bold">
                    <tr>
                        <td colspan="2" class="px-3 py-3 text-right">Column Totals</td>
                        <?php foreach ($donation_types as $type): ?>
                            <td class="px-2 py-3 text-center"><?= number_format(round($column_totals[$type['name']]), 2) ?></td>
                        <?php endforeach; ?>
                        <td class="px-3 py-3 text-center text-lg"><?= number_format(round(array_sum($column_totals)), 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="summary-section" class="mt-6 pt-4 border-t-2 border-dashed border-gray-400">
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

        <div id="footer-section" class="mt-auto pt-12">
            <div class="flex justify-between items-end text-sm">
                 <div class="w-1/3 text-left">
                     <p>TO</p>
                     <p class="font-bold mt-1"><?= htmlspecialchars($invoice_data['head_name']) ?></p>
                     <p class="mt-1"><?= nl2br(htmlspecialchars($invoice_data['head_address'] ?? 'Address not available')) ?></p>
                 </div>

                 <div class="w-1/3 text-center">
                       <?php if ($employee_data): ?>
                           <p class="font-semibold">Prepared by:</p>
                           <p class="font-bold"><?= htmlspecialchars($employee_data['full_name']) ?></p>
                           <p><?= htmlspecialchars($employee_data['job_title']) ?></p>
                           <p><?= htmlspecialchars($employee_data['department']) ?></p>
                       <?php endif; ?>
                 </div>

                 <div class="w-1/3 text-center font-semibold">
                       <p>YOUR BELOVED DIETY FATHER</p>
                       <p>'I'</p>
                       <p>SRI SRI THAKUR</p>
                    
                 </div>
            </div>
        </div>
        </div>

    <div class="mt-6 w-full max-w-7xl flex justify-end space-x-4 no-print">
        <a href="arghya_pradan.php?action=change_family" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
        <button type="button" onclick="window.print()" class="bg-blue-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition text-lg">
            <i class="fas fa-print mr-2"></i>Print Invoice
        </button>
    </div>

</body>
</html>