<?php

session_start();
// Make sure this path is correct and points to your database connection file.
require_once '../config/db.php'; 

// --- Helper Functions ---
function generateUniqueInvoiceNo($pdo) {
    do {
        $invoice_no = 'INV-' . date('Ymd') . '-' . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM donation_invoices WHERE invoice_no = ?");
        $stmt->execute([$invoice_no]);
    } while ($stmt->fetchColumn());
    return $invoice_no;
}

// --- Variables ---
$family_code = $_POST['family_code'] ?? $_SESSION['family_code'] ?? null;
$family_data = null;
$donation_types = [];
$formMessage = '';
$formMessageType = '';

// --- Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ACTION: Fetch Family Data ---
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_family') {
        if (!empty($family_code)) {
            $stmt = $pdo->prepare("
                SELECT f.id as family_id, f.family_code, f.head_of_family_id, fm_head.name as head_name, r.name as ritwik_name
                FROM families f
                LEFT JOIN family_members fm_head ON f.head_of_family_id = fm_head.id
                LEFT JOIN ritwiks r ON fm_head.ritwik_id = r.id
                WHERE f.family_code = ?
            ");
            $stmt->execute([$family_code]);
            $family_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($family_data) {
                $_SESSION['family_code'] = $family_code;
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

    // --- ACTION: Save Donation ---
    if (isset($_POST['action']) && $_POST['action'] === 'save_donation') {
        $family_id = $_POST['family_id'];
        $donations = $_POST['donations'];
        $total_amount = 0;

        $pdo->beginTransaction();
        try {
            foreach ($donations as $member_id => $types) {
                foreach ($types as $type_id => $amount) {
                    if (!empty($amount) && is_numeric($amount)) {
                        $total_amount += (float)$amount;
                    }
                }
            }

            $invoice_no = generateUniqueInvoiceNo($pdo);
            $stmt_invoice = $pdo->prepare("INSERT INTO donation_invoices (invoice_no, family_id, total_amount) VALUES (?, ?, ?)");
            $stmt_invoice->execute([$invoice_no, $family_id, $total_amount]);
            $invoice_id = $pdo->lastInsertId();

            $stmt_items = $pdo->prepare("INSERT INTO donation_items (invoice_id, member_id, donation_type_id, amount) VALUES (?, ?, ?, ?)");
            foreach ($donations as $member_id => $types) {
                foreach ($types as $type_id => $amount) {
                    if (!empty($amount) && is_numeric($amount)) {
                        $stmt_items->execute([$invoice_id, $member_id, $type_id, (float)$amount]);
                    }
                }
            }
            
            $pdo->commit();
            $formMessage = "Donation saved successfully! Invoice No: " . $invoice_no;
            $formMessageType = 'success';
            unset($_SESSION['family_code']);
            $family_code = null;
        } catch (Exception $e) {
            $pdo->rollBack();
            $formMessage = "Error saving donation: " . $e->getMessage();
            $formMessageType = 'error';
        }
    }
}

if (!empty($family_code) && !$family_data) {
    $stmt = $pdo->prepare("
        SELECT f.id as family_id, f.family_code, f.head_of_family_id, fm_head.name as head_name
        FROM families f
        LEFT JOIN family_members fm_head ON f.head_of_family_id = fm_head.id
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

// UPDATED QUERY: Fetches 'amount' as well
$donation_types = $pdo->query("SELECT id, name, amount FROM donation_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arghya Pradan Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #e5e7eb; /* A slightly darker gray for contrast */
        }
        .form-input {
            border: 1px solid #d1d5db;
            text-align: center;
            width: 100%;
            padding: 6px 4px; /* Increased padding */
            border-radius: 4px;
            font-size: 1rem; /* Increased font size */
        }
        .form-input:focus {
            outline: 2px solid #4f46e5;
            border-color: transparent;
        }
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: auto;
                padding: 0;
                margin: 0;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="flex flex-col">

    <?php if (empty($family_data)): ?>
        <!-- STEP 1: FAMILY CODE ENTRY FORM (Centered on page) -->
        <div class="flex-grow flex items-center justify-center">
            <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg overflow-hidden md:max-w-2xl w-full">
                <div class="md:flex">
                    <div class="md:flex-shrink-0 bg-indigo-600 p-8 flex items-center justify-center">
                        <i class="fas fa-search-location fa-4x text-white"></i>
                    </div>
                    <div class="p-8 flex-grow">
                        <div class="uppercase tracking-wide text-sm text-indigo-500 font-semibold">Arghya Pradan</div>
                        <h2 class="block mt-1 text-lg leading-tight font-medium text-black">Find Family to Proceed</h2>
                        <p class="mt-2 text-gray-500">Please enter the Family Code to begin.</p>
                        <form action="arghya_pradan.php" method="POST" class="mt-4">
                            <input type="hidden" name="action" value="fetch_family">
                            <input type="text" name="family_code" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., FAM1234" required>
                            <button type="submit" class="mt-4 w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-300">
                                <i class="fas fa-arrow-right mr-2"></i>Proceed
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- STEP 2: DONATION ENTRY FORM (Full screen layout) -->
        <div class="w-full h-full flex flex-col p-4">
            <form action="arghya_pradan.php" method="POST" id="donation-form" class="flex-grow flex flex-col">
                <input type="hidden" name="action" value="save_donation">
                <input type="hidden" name="family_id" value="<?= $family_data['family_id'] ?>">
                
                <div id="print-area" class="bg-white p-6 rounded-lg shadow-lg flex-grow flex flex-col">
                    <!-- Header -->
                    <header class="text-center mb-4 border-b pb-4">
                         <div class="flex items-center justify-between">
                            <img src="path/to/your/logo.png" alt="Logo" class="h-24 w-24" onerror="this.style.display='none'">
                            <div class="flex-grow">
                                <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM SURYADIH</h1>
                                <p class="text-base">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                                <p class="text-xl font-semibold mt-1 text-indigo-700">ARGHYA PRADAN</p>
                            </div>
                            <div class="text-sm text-right w-56">
                                <p class="font-bold">YOUR BELOVED DIETY FATHER 'I' SRI SRI THAKUR</p>
                                <p>SSTACSA SURYADIH</p>
                            </div>
                        </div>
                        <div class="flex justify-between text-base mt-4">
                            <p><strong>Family Head :</strong> <?= htmlspecialchars($family_data['head_name']) ?></p>
                            <p><strong>Family Code :</strong> <?= htmlspecialchars($family_data['family_code']) ?></p>
                            <p><strong>Date:</strong> <?= date('d-m-Y') ?></p>
                        </div>
                    </header>

                    <!-- Scrollable Donation Table -->
                    <div class="flex-grow overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-base" id="donation-table">
                            <thead class="bg-gray-100 sticky top-0">
                                <tr>
                                    <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Devotee</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Ritwik</th>
                                    <?php foreach ($donation_types as $type): ?>
                                        <th class="px-2 py-3 text-center font-medium text-gray-600 uppercase tracking-wider"><?= htmlspecialchars($type['name']) ?></th>
                                    <?php endforeach; ?>
                                    <th class="px-3 py-3 text-center font-medium text-gray-600 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($family_data['members'] as $member): ?>
                                    <tr class="member-row">
                                        <td class="px-3 py-2 whitespace-nowrap font-medium"><?= htmlspecialchars($member['name']) ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($member['ritwik_name'] ?? 'N/A') ?></td>
                                        <?php foreach ($donation_types as $type): ?>
                                            <td class="px-1 py-1">
                                                <input type="number" step="0.01" name="donations[<?= $member['id'] ?>][<?= $type['id'] ?>]" class="form-input donation-amount" data-member-id="<?= $member['id'] ?>" data-type-id="<?= $type['id'] ?>" value="<?= htmlspecialchars($type['amount'] ?? '') ?>">
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="px-3 py-2 text-center font-bold member-total text-lg">0.00</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-200 font-bold sticky bottom-0">
                                <tr>
                                    <td colspan="2" class="px-3 py-3 text-right">Column Totals</td>
                                    <?php foreach ($donation_types as $type): ?>
                                        <td class="px-2 py-3 text-center type-total" id="total-type-<?= $type['id'] ?>">0.00</td>
                                    <?php endforeach; ?>
                                    <td class="px-3 py-3 text-center text-xl" id="grand-total">0.00</td>
                                </tr>
                                <tr class="bg-gray-300">
                                    <td colspan="2" class="px-3 py-3 text-right text-lg">
                                        No. of Persons: <span id="person-count">0</span>
                                    </td>
                                    <td colspan="<?= count($donation_types) ?>" class="px-3 py-3 text-right text-lg">Grand Total</td>
                                    <td class="px-3 py-3 text-center text-2xl" id="grand-total-footer">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="mt-4 flex justify-end space-x-4 no-print flex-shrink-0">
                    <a href="arghya_pradan.php" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg">
                        <i class="fas fa-search mr-2"></i>Change Family
                    </a>
                    <button type="button" onclick="window.print()" class="bg-blue-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition text-lg">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                    <button type="submit" class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition text-lg">
                        <i class="fas fa-save mr-2"></i>Save Donation
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Message Display -->
    <?php if ($formMessage): ?>
    <div class="fixed top-5 right-5 max-w-md p-4 rounded-md shadow-lg text-center <?= $formMessageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
        <?= htmlspecialchars($formMessage) ?>
    </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('donation-table');
            if (!table) return;

            const grandTotalEl = document.getElementById('grand-total');
            const grandTotalFooterEl = document.getElementById('grand-total-footer');
            const personCountEl = document.getElementById('person-count');

            function calculateTotals() {
                let grandTotal = 0;
                const memberRows = table.querySelectorAll('.member-row');
                personCountEl.textContent = memberRows.length;
                
                memberRows.forEach(row => {
                    let memberTotal = 0;
                    row.querySelectorAll('.donation-amount').forEach(input => {
                        const value = parseFloat(input.value) || 0;
                        memberTotal += value;
                    });
                    row.querySelector('.member-total').textContent = memberTotal.toFixed(2);
                    grandTotal += memberTotal;
                });

                table.querySelectorAll('.type-total').forEach(totalCell => {
                    const typeId = totalCell.id.split('-')[2];
                    let typeTotal = 0;
                    table.querySelectorAll(`.donation-amount[data-type-id='${typeId}']`).forEach(input => {
                        typeTotal += parseFloat(input.value) || 0;
                    });
                    totalCell.textContent = typeTotal.toFixed(2);
                });

                grandTotalEl.textContent = grandTotal.toFixed(2);
                grandTotalFooterEl.textContent = grandTotal.toFixed(2);
            }

            table.addEventListener('input', function(e) {
                if (e.target.classList.contains('donation-amount')) {
                    calculateTotals();
                }
            });

            calculateTotals();
        });
    </script>

</body>
</html>
