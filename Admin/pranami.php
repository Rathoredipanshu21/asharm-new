<?php

session_start();
// Make sure this path is correct and points to your database connection file.
require_once '../config/db.php';

// --- Handle "Change Family" or "Change Period" action ---
if (isset($_GET['action']) && in_array($_GET['action'], ['change_family', 'change_period'])) {
    unset($_SESSION['family_code']);
    unset($_SESSION['pranami_period']); // Clear both session variables
    header('Location: pranami.php');
    exit();
}

// --- Store selected period in session from GET parameter ---
if (isset($_GET['period'])) {
    $allowed_periods = ['Weekly', 'Monthly', 'Yearly'];
    // Sanitize by capitalizing the first letter
    $period = ucfirst(strtolower(trim($_GET['period']))); 
    if (in_array($period, $allowed_periods)) {
        $_SESSION['pranami_period'] = $period;
        // Redirect to clean the URL (removes the ?period=... part)
        header('Location: pranami.php');
        exit();
    }
}

// --- Helper function to generate a unique invoice number ---
function generateUniqueInvoiceNo($pdo) {
    do {
        // Using a different prefix for Pranami invoices, e.g., 'PNI-'
        $invoice_no = 'PNI-' . date('Ymd') . '-' . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM donation_invoices WHERE invoice_no = ?");
        $stmt->execute([$invoice_no]);
    } while ($stmt->fetchColumn());
    return $invoice_no;
}

// --- Helper function to convert a number to words (for integers) ---
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

// --- Variables Initialization ---
$pranami_period = $_SESSION['pranami_period'] ?? null;
$family_code = $_POST['family_code'] ?? $_SESSION['family_code'] ?? null;
$family_data = null;
$pranami_type = null;
$formMessage = '';
$formMessageType = '';
$initial_grand_total = 0;


// --- Main Logic ---

// Fetch the Pranami donation type. This is required for the main form.
if ($pranami_period) {
    $stmt_pranami = $pdo->prepare("SELECT id, name, amount, name_hindi FROM donation_types WHERE name = 'PRANAMI' LIMIT 1");
    $stmt_pranami->execute();
    $pranami_type = $stmt_pranami->fetch(PDO::FETCH_ASSOC);
    if (!$pranami_type) {
        // Critical error if PRANAMI is not in the database.
        die("<div style='font-family: sans-serif; padding: 2rem; background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;'>Error: Donation type 'PRANAMI' not found in the database. Please add it to the `donation_types` table.</div>");
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ACTION: Fetch Family Data ---
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_family') {
        if (!empty($family_code)) {
            $stmt = $pdo->prepare("
                SELECT f.id as family_id, f.family_code, f.head_of_family_id, fm_head.name as head_name
                FROM families f
                JOIN family_members fm_head ON f.head_of_family_id = fm_head.id
                WHERE f.family_code = ?
            ");
            $stmt->execute([$family_code]);
            $family_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($family_data) {
                $_SESSION['family_code'] = $family_code;
                // Fetch members, ensuring Head of Family is first
                $stmt_members = $pdo->prepare("
                    SELECT fm.id, fm.name, r.name as ritwik_name
                    FROM family_members fm
                    LEFT JOIN ritwiks r ON fm.ritwik_id = r.id
                    WHERE fm.family_id = ? ORDER BY fm.id = ? DESC, fm.id ASC
                ");
                $stmt_members->execute([$family_data['family_id'], $family_data['head_of_family_id']]);
                $family_data['members'] = $stmt_members->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $formMessage = "Family Code not found. Please try again.";
                $formMessageType = 'error';
                unset($_SESSION['family_code']);
            }
        } else {
            $formMessage = "Please enter a Family Code.";
            $formMessageType = 'error';
        }
    }

    // --- ACTION: Save Pranami Donation ---
    if (isset($_POST['action']) && $_POST['action'] === 'save_donation') {
        $family_id = $_POST['family_id'];
        $donations = $_POST['donations'] ?? [];
        $total_amount = 0;

        if (empty($donations)) {
            $formMessage = "No donations to save. Please select at least one devotee and enter an amount.";
            $formMessageType = 'error';
        } else {
            $pdo->beginTransaction();
            try {
                // Calculate total amount from submitted donations
                foreach ($donations as $member_id => $types) {
                    foreach ($types as $type_id => $amount) {
                        if (!empty($amount) && is_numeric($amount)) {
                            $total_amount += round((float)$amount);
                        }
                    }
                }
                
                if ($total_amount > 0) {
                    $invoice_no = generateUniqueInvoiceNo($pdo);
                    // Save invoice with period. NOTE: Assumes a 'period' column exists in 'donation_invoices' table.
                    $stmt_invoice = $pdo->prepare("INSERT INTO donation_invoices (invoice_no, family_id, total_amount, period) VALUES (?, ?, ?, ?)");
                    $stmt_invoice->execute([$invoice_no, $family_id, $total_amount, $pranami_period]);
                    $invoice_id = $pdo->lastInsertId();

                    $stmt_items = $pdo->prepare("INSERT INTO donation_items (invoice_id, member_id, donation_type_id, amount) VALUES (?, ?, ?, ?)");
                    foreach ($donations as $member_id => $types) {
                        foreach ($types as $type_id => $amount) {
                            if (!empty($amount) && is_numeric($amount)) {
                                $stmt_items->execute([$invoice_id, $member_id, $type_id, round((float)$amount)]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    // Redirect to a new view page for Pranami invoices
                    header("Location: view_pranami_invoice.php?id=" . $invoice_id);
                    exit();

                } else {
                    $formMessage = "Total donation amount is zero. Nothing was saved.";
                    $formMessageType = 'error';
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                // Check for SQL error related to 'period' column
                if (strpos($e->getMessage(), "Unknown column 'period'") !== false) {
                     $formMessage = "Database Error: The 'period' column is missing from the 'donation_invoices' table. Please ask your administrator to add it.";
                } else {
                     $formMessage = "Error saving donation: " . $e->getMessage();
                }
                $formMessageType = 'error';
            }
        }
    }
}

// --- Load family data if code exists in session (for page reloads after POST) ---
if (!empty($family_code) && !$family_data) {
    $stmt = $pdo->prepare("
        SELECT f.id as family_id, f.family_code, f.head_of_family_id, fm_head.name as head_name
        FROM families f
        JOIN family_members fm_head ON f.head_of_family_id = fm_head.id
        WHERE f.family_code = ?
    ");
    $stmt->execute([$family_code]);
    $family_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($family_data) {
        $stmt_members = $pdo->prepare("
            SELECT fm.id, fm.name, r.name as ritwik_name
            FROM family_members fm
            LEFT JOIN ritwiks r ON fm.ritwik_id = r.id
            WHERE fm.family_id = ? ORDER BY fm.id = ? DESC, fm.id ASC
        ");
        $stmt_members->execute([$family_data['family_id'], $family_data['head_of_family_id']]);
        $family_data['members'] = $stmt_members->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Calculate initial total for "Amount in Words" if family is loaded
if ($family_data && $pranami_type) {
    foreach ($family_data['members'] as $member) {
        $initial_grand_total += round((float)($pranami_type['amount'] ?? 0));
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pranami Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #e5e7eb; /* gray-200 */
        }
        .form-input {
            border: 1px solid #d1d5db; /* gray-300 */
            text-align: center;
            width: 100%;
            padding: 6px 4px;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-input:focus {
            outline: 2px solid #4f46e5; /* indigo-600 */
            border-color: transparent;
        }
        .row-disabled {
            opacity: 0.5;
            background-color: #f9fafb; /* gray-50 */
        }
        .row-disabled input[type="number"] {
            background-color: #e5e7eb; /* gray-200 */
            pointer-events: none;
        }
        .period-button {
            transition: all 0.3s ease;
        }
        .period-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="flex flex-col">

    <?php if (!$pranami_period): // --- STEP 1: Show Period Selection --- ?>
        <div class="flex-grow flex items-center justify-center p-4">
            <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg text-center p-8">
                <i class="fas fa-calendar-alt fa-3x text-indigo-500 mb-4"></i>
                <h1 class="text-3xl font-bold text-gray-800">Select Pranami Period</h1>
                <p class="text-gray-600 mt-2 mb-8">Choose the time period for which you are collecting the Pranami.</p>
                <div class="flex flex-col md:flex-row justify-center items-center gap-6">
                    <a href="pranami.php?period=weekly" class="period-button w-full md:w-48 bg-teal-500 text-white font-bold py-6 px-4 rounded-lg text-2xl">
                        <i class="fas fa-calendar-week mr-2"></i>Weekly
                    </a>
                    <a href="pranami.php?period=monthly" class="period-button w-full md:w-48 bg-sky-500 text-white font-bold py-6 px-4 rounded-lg text-2xl">
                        <i class="fas fa-calendar-days mr-2"></i>Monthly
                    </a>
                    <a href="pranami.php?period=yearly" class="period-button w-full md:w-48 bg-purple-500 text-white font-bold py-6 px-4 rounded-lg text-2xl">
                        <i class="fas fa-calendar-check mr-2"></i>Yearly
                    </a>
                </div>
            </div>
        </div>
    <?php elseif (empty($family_data)): // --- STEP 2: Show Family Code Form --- ?>
        <div class="flex-grow flex items-center justify-center p-4">
            <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg overflow-hidden md:max-w-2xl w-full">
                <div class="md:flex">
                    <div class="md:flex-shrink-0 bg-indigo-600 p-8 flex items-center justify-center">
                        <i class="fas fa-users fa-4x text-white"></i>
                    </div>
                    <div class="p-8 flex-grow">
                        <div class="uppercase tracking-wide text-sm text-indigo-500 font-semibold">Pranami - <?= htmlspecialchars($pranami_period) ?></div>
                        <h2 class="block mt-1 text-lg leading-tight font-medium text-black">Find Family to Proceed</h2>
                        <p class="mt-2 text-gray-500">Please enter the Family Code to begin.</p>
                        <form action="pranami.php" method="POST" class="mt-4">
                            <input type="hidden" name="action" value="fetch_family">
                            <input type="text" name="family_code" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., FAM1234" required autofocus>
                            <button type="submit" class="mt-4 w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-300">
                                <i class="fas fa-arrow-right mr-2"></i>Proceed
                            </button>
                             <a href="pranami.php?action=change_period" class="mt-2 block text-center text-sm text-gray-500 hover:text-indigo-600">Change Period</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: // --- STEP 3: Show Main Donation Form --- ?>
        <div class="w-full h-full flex flex-col p-4">
            <form action="pranami.php" method="POST" id="donation-form" class="flex-grow flex flex-col">
                <input type="hidden" name="action" value="save_donation">
                <input type="hidden" name="family_id" value="<?= $family_data['family_id'] ?>">
                
                <div id="print-area" class="bg-white p-6 rounded-lg shadow-lg flex-grow flex flex-col">
                    <header class="mb-4">
                        <div class="flex justify-between items-start">
                            <div class="w-24">
                                 <img src="Assets/satsang.png" alt="Logo" class="h-24 w-24">
                            </div>
                            <div class="text-center flex-grow px-4">
                                <h1 class="text-2xl font-bold text-gray-800">R. S. रा. स्वा.</h1>
                                <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM</h1>
                                <h2 class="text-xl font-semibold text-gray-700">SURYADIH</h2>
                                <p class="text-base">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                                <p class="text-xl font-bold mt-2 text-indigo-700 border-2 border-indigo-700 inline-block px-4 py-1">PRANAMI</p>
                            </div>
                            <div class="w-48 text-right text-sm p-2 font-semibold">
                                Reg. No. 2024/GOV/6510/BK4/437
                            </div>
                        </div>
                        <div class="grid grid-cols-4 gap-4 text-base mt-4 border-t-2 border-b-2 border-gray-400 py-2 header-details">
                            <p><strong>Family Head:</strong> <?= htmlspecialchars($family_data['head_name']) ?></p>
                            <p><strong>Family Code:</strong> <?= htmlspecialchars($family_data['family_code']) ?></p>
                            <p><strong>Period:</strong> <span class="font-bold text-indigo-600"><?= htmlspecialchars($pranami_period) ?></span></p>
                            <p><strong>Date:</strong> <?= date('d-m-Y') ?></p>
                        </div>
                    </header>

                    <div class="flex-grow overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-base" id="donation-table">
                            <thead class="bg-gray-100 sticky top-0">
                                <tr>
                                    <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider no-print">
                                        <input type="checkbox" id="select-all-checkbox" checked title="Select/Deselect All">
                                    </th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Devotee<br>नाम / शिष्य</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">RITWIK<br>ऋत्विक</th>
                                    <th class="px-2 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">
                                        <?= htmlspecialchars($pranami_type['name']) ?>
                                        <?php if (!empty($pranami_type['name_hindi'])): ?>
                                            <br><span class="normal-case"><?= htmlspecialchars($pranami_type['name_hindi']) ?></span>
                                        <?php endif; ?>
                                    </th>
                                    <th class="px-3 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($family_data['members'] as $member): ?>
                                    <tr class="member-row">
                                        <td class="px-3 py-2 whitespace-nowrap no-print">
                                            <input type="checkbox" class="member-checkbox" checked>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap font-medium"><?= htmlspecialchars($member['name']) ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($member['ritwik_name'] ?? 'N/A') ?></td>
                                        <td class="px-1 py-1">
                                            <input type="number" step="1" name="donations[<?= $member['id'] ?>][<?= $pranami_type['id'] ?>]" class="form-input donation-amount" data-type-id="<?= $pranami_type['id'] ?>" value="<?= htmlspecialchars(round($pranami_type['amount'] ?? 0)) ?>">
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold member-total text-lg">0</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-200 font-bold sticky bottom-0">
                                <tr class="bg-gray-300">
                                    <td colspan="3" class="px-3 py-3 text-right text-lg no-print">
                                        Persons: <span id="person-count">0</span> / Column Total:
                                    </td>
                                    <td class="px-2 py-3 text-center type-total" id="total-type-<?= $pranami_type['id'] ?>">0</td>
                                    <td class="px-3 py-3 text-center text-2xl" id="grand-total-footer">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-6 pt-4 border-t-2 border-dashed border-gray-400">
                        <div class="flex justify-between items-start">
                            <div class="w-2/3">
                                 <p class="font-semibold">In Words: <span class="font-normal" id="grand-total-words"><?= numberToWords($initial_grand_total); ?></span></p>
                            </div>
                            <div class="w-1/3 text-right">
                                 <p class="text-lg font-bold">Grand Total: <span class="text-2xl">₹<span id="final-grand-total">0</span></span></p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto pt-12">
                        <div class="flex justify-between items-end">
                             <div class="text-left">
                                 <p>TO</p>
                                <p><?= htmlspecialchars($family_data['head_name']) ?></p>
                             </div>
                             <div class="text-right text-sm font-semibold">
                                 <p>FOR YOUR BELOVED DIETY FATHER</p>
                                 <p>'I' SRI SRI THAKUR</p>
                                
                             </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-between items-center space-x-4 no-print flex-shrink-0">
                    <a href="pranami.php?action=change_period" class="bg-yellow-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-yellow-600 transition text-lg">
                        <i class="fas fa-calendar-alt mr-2"></i>Change Period
                    </a>
                    <div>
                        <a href="pranami.php?action=change_family" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg">
                            <i class="fas fa-search mr-2"></i>Change Family
                        </a>
                        <button type="submit" class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition text-lg">
                            <i class="fas fa-save mr-2"></i>Save & Generate Invoice
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($formMessage): ?>
    <div id="alert-message" class="fixed top-5 right-5 max-w-sm p-4 rounded-lg shadow-lg text-lg z-50 <?= $formMessageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
        <?= htmlspecialchars($formMessage) ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('alert-message')?.remove();
        }, 5000);
    </script>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('donation-table');
        if (!table) return;

        const grandTotalFooterEl = document.getElementById('grand-total-footer');
        const finalGrandTotalEl = document.getElementById('final-grand-total');
        const personCountEl = document.getElementById('person-count');
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const grandTotalWordsEl = document.getElementById('grand-total-words');

        // --- JavaScript Number to Words (Integer Only) ---
        const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        function convertToWords(n) {
            if (n < 20) return ones[n];
            if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 !== 0 ? ' ' + ones[n % 10] : '');
            if (n < 1000) return ones[Math.floor(n / 100)] + ' Hundred' + (n % 100 !== 0 ? ' and ' + convertToWords(n % 100) : '');
            if (n < 100000) return convertToWords(Math.floor(n / 1000)) + ' Thousand' + (n % 1000 !== 0 ? ' ' + convertToWords(n % 1000) : '');
            if (n < 10000000) return convertToWords(Math.floor(n / 100000)) + ' Lakh' + (n % 100000 !== 0 ? ' ' + convertToWords(n % 100000) : '');
            return convertToWords(Math.floor(n / 10000000)) + ' Crore' + (n % 10000000 !== 0 ? ' ' + convertToWords(n % 10000000) : '');
        }

        function numberToWordsJS(num) {
            const roundedNum = Math.round(num);
            if (roundedNum === 0 || !roundedNum) return 'Zero Rupees Only';
            let rupeesWords = convertToWords(roundedNum).trim().replace(/\s+/g, ' ');
            return rupeesWords.charAt(0).toUpperCase() + rupeesWords.slice(1) + ' Rupees Only';
        }

        function toggleRow(row, isEnabled) {
            const inputs = row.querySelectorAll('.donation-amount');
            if (isEnabled) {
                row.classList.remove('row-disabled');
                inputs.forEach(input => input.disabled = false);
            } else {
                row.classList.add('row-disabled');
                inputs.forEach(input => input.disabled = true);
            }
        }

        function calculateTotals() {
            let grandTotal = 0;
            let activePersonCount = 0;
            const memberRows = table.querySelectorAll('.member-row');

            memberRows.forEach(row => {
                const isChecked = row.querySelector('.member-checkbox').checked;
                let memberTotal = 0;
                if (isChecked) {
                    activePersonCount++;
                    row.querySelectorAll('.donation-amount').forEach(input => {
                        memberTotal += parseFloat(input.value) || 0;
                    });
                }
                row.querySelector('.member-total').textContent = Math.round(memberTotal);
                grandTotal += memberTotal;
            });
            
            personCountEl.textContent = activePersonCount;

            // This will now calculate for the single Pranami column
            table.querySelectorAll('.type-total').forEach(totalCell => {
                const typeId = totalCell.id.split('-')[2];
                let typeTotal = 0;
                table.querySelectorAll(`.donation-amount[data-type-id='${typeId}']`).forEach(input => {
                    if (!input.disabled) {
                        typeTotal += parseFloat(input.value) || 0;
                    }
                });
                totalCell.textContent = Math.round(typeTotal);
            });

            const roundedGrandTotal = Math.round(grandTotal);
            grandTotalFooterEl.textContent = roundedGrandTotal;
            finalGrandTotalEl.textContent = roundedGrandTotal;
            grandTotalWordsEl.textContent = numberToWordsJS(roundedGrandTotal);
        }

        // --- Event Listeners ---
        table.addEventListener('change', function(e) {
            if (e.g.classList.contains('member-checkbox')) {
                toggleRow(e.target.closest('.member-row'), e.target.checked);
                calculateTotals();
            }
        });
        
        table.addEventListener('input', function(e) {
            if (e.target.classList.contains('donation-amount')) {
                calculateTotals();
            }
        });

        selectAllCheckbox.addEventListener('change', function() {
            table.querySelectorAll('.member-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                toggleRow(checkbox.closest('.member-row'), this.checked);
            });
            calculateTotals();
        });
        
        // Initial calculation on page load
        calculateTotals();
    });
    </script>
</body>
</html>