<?php
session_start();

require_once '../config/db.php';

// --- HELPER FUNCTIONS (Adapted from your files) ---

function generateUniqueInvoiceNo($pdo) {
    do {
        // Using a different prefix for these donations
        $invoice_no = 'GEN-' . date('Ymd') . '-' . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM general_donations WHERE invoice_no = ?");
        $stmt->execute([$invoice_no]);
    } while ($stmt->fetchColumn());
    return $invoice_no;
}

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
    if (empty($result)) {
        return "Zero Rupees Only";
    }
    return ucwords($result) . " Rupees Only";
}

// --- PAGE LOGIC ---

$page_mode = 'selection'; // 'selection', 'form', or 'invoice'
$donation_type = $_GET['donation_type'] ?? null;
$invoice_id = $_GET['id'] ?? null;
$view_invoice_flag = isset($_GET['view_invoice']);
$invoice_data = null;
$formMessage = '';
$formMessageType = '';


// --- FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_donation') {
    $devotee_name = trim($_POST['devotee_name']);
    $address = trim($_POST['address']);
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $donation_type_submitted = trim($_POST['donation_type']);

    if (empty($devotee_name) || empty($address) || $amount === false || $amount <= 0 || empty($donation_type_submitted)) {
        $formMessage = "Please fill all fields with valid data.";
        $formMessageType = 'error';
        $donation_type = $donation_type_submitted; // Keep the form visible
        $page_mode = 'form';
    } else {
        try {
            $invoice_no = generateUniqueInvoiceNo($pdo);
            $sql = "INSERT INTO general_donations (invoice_no, devotee_name, address, donation_type, amount) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$invoice_no, $devotee_name, $address, $donation_type_submitted, $amount]);
            $new_id = $pdo->lastInsertId();
            
            // Redirect to the invoice view
            header("Location: utsav_donation.php?view_invoice=1&id=" . $new_id);
            exit();

        } catch (PDOException $e) {
            $formMessage = "Database error: Could not save donation. " . $e->getMessage();
            $formMessageType = 'error';
            $donation_type = $donation_type_submitted;
            $page_mode = 'form';
        }
    }
}

// Determine page mode based on GET parameters
if ($view_invoice_flag && $invoice_id) {
    $page_mode = 'invoice';
    $stmt = $pdo->prepare("SELECT * FROM general_donations WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice_data) {
        die("Invoice not found.");
    }
} elseif ($donation_type) {
    $page_mode = 'form';
} else {
    $page_mode = 'selection';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utsav & General Donation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        @media print {
            body { background-color: white !important; }
            .no-print { display: none !important; }
            #print-area {
                position: absolute; left: 0; top: 0;
                margin: 0; padding: 1cm;
                width: 100%; height: auto;
                box-shadow: none; border-radius: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">

    <?php if ($formMessage): ?>
    <div id="alert-message" class="fixed top-5 right-5 max-w-sm p-4 rounded-lg shadow-lg text-lg z-50 <?= $formMessageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
        <?= htmlspecialchars($formMessage) ?>
        <script>setTimeout(() => document.getElementById('alert-message')?.remove(), 5000);</script>
    </div>
    <?php endif; ?>

    <?php if ($page_mode === 'selection'): ?>
    <div class="text-center">
        <img src="Assets/satsang.png" alt="Logo" class="mx-auto mb-4 h-24 w-24" data-aos="zoom-in" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'h-24 w-24 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm\'>Logo</div>';">
        <h1 class="text-4xl font-bold text-gray-800 mb-2" data-aos="fade-down">Select Donation Type</h1>
        <p class="text-gray-600 mb-8" data-aos="fade-down" data-aos-delay="100">Choose a category to continue with your donation.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="?donation_type=Donation" class="bg-white p-8 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transform transition-all duration-300" data-aos="fade-up" data-aos-delay="200">
                <i class="fas fa-hand-holding-heart text-5xl text-purple-500 mb-4"></i>
                <h2 class="text-2xl font-semibold text-gray-700">Donation</h2>
            </a>
            <a href="?donation_type=Utsav Donation" class="bg-white p-8 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transform transition-all duration-300" data-aos="fade-up" data-aos-delay="300">
                <i class="fas fa-om text-5xl text-orange-500 mb-4"></i>
                <h2 class="text-2xl font-semibold text-gray-700">Utsav Donation</h2>
            </a>
            <a href="?donation_type=Miscellaneous Donation" class="bg-white p-8 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transform transition-all duration-300" data-aos="fade-up" data-aos-delay="400">
                <i class="fas fa-gifts text-5xl text-teal-500 mb-4"></i>
                <h2 class="text-2xl font-semibold text-gray-700">Miscellaneous</h2>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($page_mode === 'form'): ?>
    <div class="w-full max-w-7xl">
        <form action="utsav_donation.php" method="POST" id="donation-form" class="flex flex-col">
            <input type="hidden" name="action" value="save_donation">
            <input type="hidden" name="donation_type" value="<?= htmlspecialchars($donation_type) ?>">
            
            <div id="form-content" class="bg-white p-6 rounded-lg shadow-lg flex-grow flex flex-col">
                <!-- Header -->
                <header class="mb-4">
                    <div class="flex justify-between items-start">
                        <div class="w-24">
                            <img src="Assets/satsang.png" alt="Logo" class="h-24 w-24" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'h-24 w-24 flex items-center justify-center bg-gray-200 text-gray-500 text-sm\'>Logo</div>';">
                        </div>
                        <div class="text-center flex-grow px-4">
                            <h1 class="text-2xl font-bold text-gray-800">R. S. रा. स्वा.</h1>
                            <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM</h1>
                            <h2 class="text-xl font-semibold text-gray-700">SURYADIH</h2>
                            <p class="text-base">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                            <p class="text-xl font-bold mt-2 text-indigo-700 border-2 border-indigo-700 inline-block px-4 py-1 uppercase"><?= htmlspecialchars($donation_type) ?></p>
                        </div>
                        <div class="w-48 text-right text-sm p-2 font-semibold">
                            Reg. No. 2024/GOV/6510/BK4/437
                        </div>
                    </div>
                </header>
                
                <!-- Form Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 border-t-2 pt-6">
                    <div>
                        <label for="devotee_name" class="block text-sm font-medium text-gray-700">Devotee Name</label>
                        <input type="text" name="devotee_name" id="devotee_name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?= htmlspecialchars($_POST['devotee_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Donation Amount (₹)</label>
                        <input type="number" name="amount" id="amount" required step="1" min="1" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700">Full Address</label>
                        <textarea name="address" id="address" rows="3" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Grand Total and Words Section -->
                <div class="mt-6 pt-4 border-t-2 border-dashed border-gray-400">
                    <div class="flex justify-between items-start">
                        <div class="w-2/3">
                             <p class="font-semibold">In Words: <span class="font-normal" id="grand-total-words">Zero Rupees Only</span></p>
                        </div>
                        <div class="w-1/3 text-right">
                             <p class="text-lg font-bold">Grand Total: <span class="text-2xl">₹<span id="final-grand-total">0</span></span></p>
                        </div>
                    </div>
                </div>

                <!-- Signature Footer Section -->
                <div class="mt-auto pt-12">
                    <div class="flex justify-between items-end">
                         <div class="text-left">
                            <p>TO</p>
                            <p id="to-devotee-name" class="font-bold min-h-[1.5rem]"><?= htmlspecialchars($_POST['devotee_name'] ?? '') ?></p>
                         </div>
                         <div class="text-right text-sm font-semibold">
                             <p>FOR YOUR BELOVED DIETY FATHER</p>
                             <p>'I' SRI SRI THAKUR</p>
                         </div>
                    </div>
                </div>
            </div>
             <!-- Actions -->
            <div class="mt-4 flex justify-between items-center no-print flex-shrink-0">
                <a href="utsav_donation.php" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg"><i class="fas fa-arrow-left mr-2"></i>Back to Selection</a>
                <button type="submit" class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition text-lg">
                    <i class="fas fa-save mr-2"></i>Save & Generate Invoice
                </button>
            </div>
        </form>
        <script>
            const amountInput = document.getElementById('amount');
            const totalDisplay = document.getElementById('final-grand-total');
            const totalWordsDisplay = document.getElementById('grand-total-words');
            const devoteeNameInput = document.getElementById('devotee_name');
            const toDevoteeName = document.getElementById('to-devotee-name');

            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

            function convertToWords(n) {
                if (n < 0 || n === undefined) return '';
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
                let rupeesWords = convertToWords(roundedNum).trim();
                if(rupeesWords === '') return 'Zero Rupees Only';
                return rupeesWords.charAt(0).toUpperCase() + rupeesWords.slice(1) + ' Rupees Only';
            }

            amountInput.addEventListener('input', () => {
                let amount = parseFloat(amountInput.value) || 0;
                totalDisplay.textContent = Math.round(amount);
                totalWordsDisplay.textContent = numberToWordsJS(amount);
            });
            devoteeNameInput.addEventListener('input', () => {
                toDevoteeName.textContent = devoteeNameInput.value;
            });

            // Trigger on page load in case of form error
            if(amountInput.value) amountInput.dispatchEvent(new Event('input'));
        </script>
    </div>
    <?php endif; ?>

    <?php if ($page_mode === 'invoice' && $invoice_data): ?>
    <div class="w-full max-w-7xl">
        <div id="print-area" class="w-full bg-white p-6 rounded-lg shadow-lg flex flex-col">
            <header class="mb-4">
                <div class="flex justify-between items-start">
                    <div class="w-24">
                        <img src="Assets/satsang.png" alt="Logo" class="h-24 w-24" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'h-24 w-24 flex items-center justify-center bg-gray-200 text-gray-500 text-sm\'>Logo</div>';">
                    </div>
                    <div class="text-center flex-grow px-4">
                        <h1 class="text-2xl font-bold text-gray-800">R. S. रा. स्वा.</h1>
                        <h1 class="text-2xl font-bold text-gray-800">SHREE SHREE THAKUR ANUKUL CHANDRA SATSANG ASHRAM</h1>
                        <h2 class="text-xl font-semibold text-gray-700">SURYADIH</h2>
                        <p class="text-base">P.O. - PINDRAHAT, DIST-DHANBAD(JHARKHAND) PIN-828201</p>
                        <p class="text-xl font-bold mt-2 text-indigo-700 border-2 border-indigo-700 inline-block px-4 py-1 uppercase"><?= htmlspecialchars($invoice_data['donation_type']) ?></p>
                    </div>
                    <div class="w-48 text-right text-sm p-2 font-semibold">
                        Reg. No. 2024/GOV/6510/BK4/437
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 text-base mt-4 border-t-2 border-b-2 border-gray-400 py-2">
                    <div>
                        <p><strong>Devotee:</strong> <?= htmlspecialchars($invoice_data['devotee_name']) ?></p>
                        <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($invoice_data['address'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p><strong>Invoice No:</strong> <?= htmlspecialchars($invoice_data['invoice_no']) ?></p>
                        <p><strong>Date:</strong> <?= date('d-m-Y', strtotime($invoice_data['created_at'])) ?></p>
                    </div>
                </div>
            </header>

            <div class="flex-grow mt-6">
                <table class="min-w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-600 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-right font-medium text-gray-600 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium"><?= htmlspecialchars($invoice_data['donation_type']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">₹<?= number_format($invoice_data['amount'], 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pt-4 border-t-2 border-dashed border-gray-400">
                <div class="flex justify-between items-start">
                    <div class="w-2/3">
                         <p class="font-semibold">In Words: <span class="font-normal"><?= numberToWords($invoice_data['amount']); ?></span></p>
                    </div>
                    <div class="w-1/3 text-right">
                         <p class="text-lg font-bold">Grand Total: <span class="text-2xl">₹<?= number_format($invoice_data['amount'], 2) ?></span></p>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-12">
                <div class="flex justify-between items-end text-sm">
                     <div class="text-left">
                        <p>TO</p>
                        <p class="font-bold mt-1"><?= htmlspecialchars($invoice_data['devotee_name']) ?></p>
                     </div>
                     <div class="text-right text-sm font-semibold">
                         <p>FOR YOUR BELOVED DIETY FATHER</p>
                         <p>'I' SRI SRI THAKUR</p>
                     </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 w-full flex justify-end space-x-4 no-print">
            <a href="utsav_donation.php" class="bg-gray-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-gray-600 transition text-lg">
                <i class="fas fa-plus mr-2"></i>New Donation
            </a>
            <button type="button" onclick="window.print()" class="bg-blue-500 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-600 transition text-lg">
                <i class="fas fa-print mr-2"></i>Print Invoice
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init({
          duration: 800,
          once: true
      });
    </script>
</body>
</html>

