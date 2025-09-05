<?php
session_start();
require_once '../config/db.php';

// --- AJAX HANDLER: Get Ritwik History ---
if (isset($_GET['action']) && $_GET['action'] === 'get_ritwik_history') {
    header('Content-Type: application/json');
    // ... (existing history code remains unchanged)
    $ritwik_id = $_GET['id'] ?? 0;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $family_code = $_GET['family_code'] ?? null;

    if (!$ritwik_id) {
        echo json_encode(['error' => 'No Ritwik ID provided.']);
        exit;
    }

    $params = [$ritwik_id];
    $sql = "
        SELECT fm.name AS devotee_name, f.family_code, inv.invoice_no, inv.created_at AS donation_date, di.amount
        FROM donation_items AS di
        JOIN donation_invoices AS inv ON di.invoice_id = inv.id
        JOIN family_members AS fm ON di.member_id = fm.id
        JOIN families AS f ON fm.family_id = f.id
        JOIN donation_types AS dt ON di.donation_type_id = dt.id
        WHERE fm.ritwik_id = ? AND dt.name = 'DAKSHINA'";
    if ($start_date) { $sql .= " AND DATE(inv.created_at) >= ?"; $params[] = $start_date; }
    if ($end_date) { $sql .= " AND DATE(inv.created_at) <= ?"; $params[] = $end_date; }
    if ($family_code) { $sql .= " AND f.family_code LIKE ?"; $params[] = "%" . $family_code . "%"; }
    $sql .= " ORDER BY f.family_code, inv.created_at DESC";

    try {
        $stmt_details = $pdo->prepare($sql);
        $stmt_details->execute($params);
        $history_data = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
        $grouped_history = [];
        foreach ($history_data as $item) { $grouped_history[$item['family_code']][] = $item; }
        echo json_encode($grouped_history);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => 'Database error: ' . $e->getMessage()]); }
    exit;
}

// --- AJAX HANDLER: Process Payment ---
if (isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    header('Content-Type: application/json');
    $ritwik_id = $_POST['ritwik_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $method = $_POST['payment_method'] ?? 'Cash';
    $notes = $_POST['notes'] ?? '';

    if (!$ritwik_id || !is_numeric($amount) || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data provided.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO ritwik_payments (ritwik_id, amount_paid, payment_method, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ritwik_id, $amount, $method, $notes]);
        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// --- AJAX HANDLER: Get Payment History ---
if (isset($_GET['action']) && $_GET['action'] === 'get_payment_history') {
    header('Content-Type: application/json');
    $ritwik_id = $_GET['id'] ?? 0;
    if (!$ritwik_id) {
        echo json_encode(['error' => 'No Ritwik ID provided.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT amount_paid, payment_date, payment_method, notes FROM ritwik_payments WHERE ritwik_id = ? ORDER BY payment_date DESC");
        $stmt->execute([$ritwik_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($payments);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// --- MAIN PAGE LOGIC ---
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$ritwik_id_filter = $_GET['ritwik_id'] ?? null;
$family_code_filter = $_GET['family_code'] ?? null;

$ritwiks_list = $pdo->query("SELECT id, name FROM ritwiks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- Build the Main SQL Query ---
$params = [];
$sql = "
    SELECT
        r.id AS ritwik_id,
        r.name AS ritwik_name,
        SUM(di.amount) AS total_dakshina,
        (SELECT SUM(amount_paid) FROM ritwik_payments WHERE ritwik_id = r.id) as total_paid
    FROM donation_items AS di
    JOIN family_members AS fm ON di.member_id = fm.id
    JOIN ritwiks AS r ON fm.ritwik_id = r.id
    JOIN donation_types AS dt ON di.donation_type_id = dt.id
    JOIN donation_invoices AS inv ON di.invoice_id = inv.id
    JOIN families AS f ON fm.family_id = f.id
    WHERE dt.name = 'DAKSHINA'
";

if ($start_date) { $sql .= " AND DATE(inv.created_at) >= ?"; $params[] = $start_date; }
if ($end_date) { $sql .= " AND DATE(inv.created_at) <= ?"; $params[] = $end_date; }
if ($ritwik_id_filter) { $sql .= " AND r.id = ?"; $params[] = $ritwik_id_filter; }
if ($family_code_filter) { $sql .= " AND f.family_code LIKE ?"; $params[] = "%" . $family_code_filter . "%"; }

$sql .= " GROUP BY r.id, r.name ORDER BY total_dakshina DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dakshina_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grand_total_earned = array_sum(array_column($dakshina_data, 'total_dakshina'));
$grand_total_paid = array_sum(array_column($dakshina_data, 'total_paid'));
$grand_total_pending = $grand_total_earned - $grand_total_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ritwik Dakshina & Payment Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        /* Custom styles from previous version */
        @media print { body * { visibility: hidden; } #print-area, #print-area * { visibility: visible; } #print-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; } .no-print { display: none !important; } }
        .modal-overlay { transition: opacity 0.3s ease; }
        .modal-container { transition: all 0.3s ease; }
        .family-group { border-left: 4px solid; transition: all 0.3s ease-in-out; }
        .family-group:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="bg-white">
    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        <!-- Filter Form -->
        <div data-aos="fade-down" class="bg-gray-50 border border-gray-200 p-6 rounded-xl shadow-sm mb-8 no-print">
            <h2 class="text-2xl font-bold text-gray-800 mb-5 flex items-center"><i class="fas fa-filter text-indigo-500 mr-3"></i>Filter Report</h2>
            <form action="ritwik_dakshina_report.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 items-end">
                <!-- Filter inputs from previous version -->
                <div><label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label><input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"></div>
                <div><label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label><input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"></div>
                <div><label for="ritwik_id_filter" class="block text-sm font-medium text-gray-700">Ritwik</label><select id="ritwik_id_filter" name="ritwik_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"><option value="">All Ritwiks</option><?php foreach($ritwiks_list as $ritwik): ?><option value="<?= $ritwik['id'] ?>" <?= ($ritwik_id_filter == $ritwik['id']) ? 'selected' : '' ?>><?= htmlspecialchars($ritwik['name']) ?></option><?php endforeach; ?></select></div>
                <div><label for="family_code_filter" class="block text-sm font-medium text-gray-700">Family Code</label><input type="text" id="family_code_filter" name="family_code" placeholder="e.g. FAM123" value="<?= htmlspecialchars($family_code_filter ?? '')?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"></div>
                <div class="flex space-x-2"><button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="fas fa-search mr-2"></i>Filter</button><a href="ritwik_dakshina_report.php" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50" title="Reset Filters"><i class="fas fa-sync-alt"></i></a></div>
            </form>
        </div>

        <!-- Report Display Area -->
        <div id="print-area" data-aos="fade-up" class="bg-white p-8 rounded-xl shadow-md border border-gray-200">
            <header class="mb-6 border-b pb-4 text-center">
                <h1 class="text-3xl font-bold text-gray-800">Ritwik Dakshina & Payment Report</h1>
                <p class="text-gray-600 mt-1"><?php if ($start_date || $end_date): ?>For the period: <strong><?= htmlspecialchars($start_date ?: 'Start') ?></strong> to <strong><?= htmlspecialchars($end_date ?: 'Today') ?></strong><?php else: ?>Showing all records<?php endif; ?></p>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ritwik Name</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Earned (₹)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid (₹)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pending Amount (₹)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($dakshina_data)): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500"><i class="fas fa-exclamation-circle fa-3x mb-2 text-yellow-400"></i><p>No records found.</p></td></tr>
                        <?php else: ?>
                            <?php foreach ($dakshina_data as $row):
                                $pending = ($row['total_dakshina'] ?? 0) - ($row['total_paid'] ?? 0);
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['ritwik_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right font-semibold"><?= number_format($row['total_dakshina'] ?? 0, 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 text-right font-semibold"><?= number_format($row['total_paid'] ?? 0, 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right font-bold"><?= number_format($pending, 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center no-print space-x-2">
                                        <button class="pay-btn text-white bg-green-500 hover:bg-green-600 font-medium rounded-lg text-xs px-3 py-2" data-ritwik-id="<?= $row['ritwik_id'] ?>" data-ritwik-name="<?= htmlspecialchars($row['ritwik_name']) ?>" data-pending="<?= $pending ?>"><i class="fas fa-hand-holding-usd mr-1"></i>Pay</button>
                                        <button class="view-donation-history-btn text-indigo-600 hover:text-indigo-900 font-medium" data-ritwik-id="<?= $row['ritwik_id'] ?>" data-ritwik-name="<?= htmlspecialchars($row['ritwik_name']) ?>"><i class="fas fa-eye"></i> Donations</button>
                                        <button class="view-payment-history-btn text-blue-600 hover:text-blue-900 font-medium" data-ritwik-id="<?= $row['ritwik_id'] ?>" data-ritwik-name="<?= htmlspecialchars($row['ritwik_name']) ?>"><i class="fas fa-receipt"></i> Payment History</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold">
                        <tr>
                            <td class="px-6 py-4 text-right text-lg text-gray-800">Grand Total</td>
                            <td class="px-6 py-4 text-right text-lg text-green-700">₹<?= number_format($grand_total_earned, 2) ?></td>
                            <td class="px-6 py-4 text-right text-lg text-blue-700">₹<?= number_format($grand_total_paid, 2) ?></td>
                            <td class="px-6 py-4 text-right text-lg text-red-700">₹<?= number_format($grand_total_pending, 2) ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="mt-8 flex justify-end no-print"><button onclick="window.print()" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"><i class="fas fa-print mr-3"></i>Print Report</button></div>
    </div>
    
    <!-- All Modals: Donation History, Payment, Payment History -->
    <div id="donation-history-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full flex items-center justify-center p-4 modal-overlay opacity-0 hidden z-50">
        <div class="relative bg-white w-full max-w-4xl mx-auto rounded-lg shadow-xl modal-container transform scale-95">
            <div class="flex justify-between items-center p-5 border-b"><div id="donation-modal-header"></div><button type="button" data-action="close-modal" class="text-gray-400 bg-transparent hover:bg-gray-200 rounded-lg p-1.5 ml-auto inline-flex items-center"><i class="fas fa-times fa-lg"></i></button></div>
            <div class="p-6"><div id="donation-modal-loader" class="text-center py-10"></div><div id="donation-modal-content" class="hidden max-h-[60vh] overflow-y-auto space-y-4 pr-2"></div></div>
        </div>
    </div>
    
    <div id="payment-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full flex items-center justify-center p-4 modal-overlay opacity-0 hidden z-50">
        <div class="relative bg-white w-full max-w-md mx-auto rounded-lg shadow-xl modal-container transform scale-95">
             <form id="payment-form">
                <div class="flex justify-between items-center p-5 border-b"><h3 class="text-2xl font-semibold text-gray-900 flex items-center" id="payment-modal-title"></h3><button type="button" data-action="close-modal" class="text-gray-400 bg-transparent hover:bg-gray-200 rounded-lg p-1.5 ml-auto inline-flex items-center"><i class="fas fa-times fa-lg"></i></button></div>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="ritwik_id" id="payment-ritwik-id">
                    <input type="hidden" name="action" value="process_payment">
                    <div><label for="amount" class="block mb-2 text-sm font-medium text-gray-900">Amount to Pay (Max: <span id="max-payable"></span>)</label><input type="number" name="amount" id="payment-amount" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" step="0.01" required></div>
                    <div><label for="payment_method" class="block mb-2 text-sm font-medium text-gray-900">Payment Method</label><select id="payment_method" name="payment_method" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"><option>Cash</option><option>Bank Transfer</option><option>Cheque</option><option>Other</option></select></div>
                    <div><label for="notes" class="block mb-2 text-sm font-medium text-gray-900">Notes (Optional)</label><textarea id="notes" name="notes" rows="3" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea></div>
                </div>
                <div class="flex items-center p-6 border-t rounded-b"><button type="submit" class="text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Submit Payment</button></div>
             </form>
        </div>
    </div>

    <div id="payment-history-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full flex items-center justify-center p-4 modal-overlay opacity-0 hidden z-50">
        <div class="relative bg-white w-full max-w-2xl mx-auto rounded-lg shadow-xl modal-container transform scale-95">
            <div class="flex justify-between items-center p-5 border-b"><div id="payment-history-modal-header"></div><button type="button" data-action="close-modal" class="text-gray-400 bg-transparent hover:bg-gray-200 rounded-lg p-1.5 ml-auto inline-flex items-center"><i class="fas fa-times fa-lg"></i></button></div>
            <div class="p-6"><div id="payment-history-modal-loader" class="text-center py-10"></div><div id="payment-history-modal-content" class="hidden max-h-[60vh] overflow-y-auto pr-2"></div></div>
        </div>
    </div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    AOS.init({ once: true, duration: 600 });
    
    const modals = {
        donation: { el: document.getElementById('donation-history-modal'), container: document.querySelector('#donation-history-modal .modal-container'), loader: document.getElementById('donation-modal-loader'), content: document.getElementById('donation-modal-content'), header: document.getElementById('donation-modal-header') },
        payment: { el: document.getElementById('payment-modal'), container: document.querySelector('#payment-modal .modal-container') },
        paymentHistory: { el: document.getElementById('payment-history-modal'), container: document.querySelector('#payment-history-modal .modal-container'), loader: document.getElementById('payment-history-modal-loader'), content: document.getElementById('payment-history-modal-content'), header: document.getElementById('payment-history-modal-header') }
    };
    
    function showModal(modal) {
        modal.el.classList.remove('hidden');
        setTimeout(() => { modal.el.classList.remove('opacity-0'); modal.container.classList.remove('scale-95'); }, 10);
    }

    function hideModal(modal) {
        modal.el.classList.add('opacity-0'); modal.container.classList.add('scale-95');
        setTimeout(() => { modal.el.classList.add('hidden'); }, 300);
    }
    
    document.body.addEventListener('click', e => {
        const modalOverlay = e.target.closest('.modal-overlay');
        if (!modalOverlay) return; // Click was not in a modal context

        // Check if the click was on the overlay background itself, or on an explicit close button
        if (e.target === modalOverlay || e.target.closest('[data-action="close-modal"]')) {
            const modalKey = Object.keys(modals).find(key => modals[key].el === modalOverlay);
            if (modalKey) hideModal(modals[modalKey]);
        }
    });

    // Donation History Logic
    document.querySelectorAll('.view-donation-history-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const ritwikId = button.dataset.ritwikId;
            const ritwikName = button.dataset.ritwikName;
            const modal = modals.donation;
            
            modal.header.innerHTML = `<h3 class="text-2xl font-semibold text-gray-900 flex items-center"><i class="fas fa-history text-indigo-500 mr-3"></i>Donation History</h3><p class="text-sm text-gray-500 ml-9">For Ritwik: ${ritwikName}</p>`;
            modal.loader.innerHTML = `<i class="fas fa-spinner fa-spin fa-3x text-indigo-500"></i><p class="mt-2 text-gray-600">Loading History...</p>`;
            modal.loader.style.display = 'block';
            modal.content.classList.add('hidden');
            modal.content.innerHTML = '';
            showModal(modal);

            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const familyCode = document.getElementById('family_code_filter').value;
            
            try {
                const response = await fetch(`?action=get_ritwik_history&id=${ritwikId}&start_date=${startDate}&end_date=${endDate}&family_code=${familyCode}`);
                const groupedData = await response.json();
                if (groupedData.error) throw new Error(groupedData.error);
                
                if (Object.keys(groupedData).length > 0) {
                    let colorIndex = 0;
                    const familyColors = ['border-blue-500', 'border-green-500', 'border-purple-500', 'border-yellow-500', 'border-red-500', 'border-pink-500'];
                    for (const familyCode in groupedData) {
                        const items = groupedData[familyCode];
                        const familyTotal = items.reduce((sum, item) => sum + parseFloat(item.amount), 0);
                        const colorClass = familyColors[colorIndex % familyColors.length];
                        let tableRows = items.map(item => `
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 text-sm text-gray-800">${item.devotee_name}</td>
                                <td class="p-3 text-sm text-gray-500">${item.invoice_no}</td>
                                <td class="p-3 text-sm text-gray-500">${new Date(item.donation_date).toLocaleDateString('en-IN')}</td>
                                <td class="p-3 text-sm text-gray-800 font-medium text-right">${parseFloat(item.amount).toFixed(2)}</td>
                            </tr>`).join('');
                        
                        modal.content.innerHTML += `<div class="family-group bg-gray-50 rounded-lg p-4 shadow-sm ${colorClass}" data-aos="fade-up" data-aos-delay="${colorIndex * 50}"><div class="flex justify-between items-center mb-2"><h4 class="font-bold text-lg text-gray-800 flex items-center"><i class="fas fa-users mr-2"></i>Family: ${familyCode}</h4><span class="text-base font-bold text-gray-700">Total: ₹${familyTotal.toFixed(2)}</span></div><div class="overflow-x-auto"><table class="min-w-full"><thead class="bg-gray-100"><tr><th class="p-2 text-left text-xs font-medium text-gray-500 uppercase">Devotee</th><th class="p-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th><th class="p-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="p-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (₹)</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">${tableRows}</tbody></table></div></div>`;
                        colorIndex++;
                    }
                } else {
                    modal.content.innerHTML = `<div class="text-center py-10 text-gray-500"><i class="fas fa-info-circle fa-2x mb-2"></i><p>No donation history found.</p></div>`;
                }
            } catch (error) { modal.content.innerHTML = `<div class="text-center py-10 text-red-500"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><p>Failed to load data. ${error.message}</p></div>`; }
            finally { modal.loader.style.display = 'none'; modal.content.classList.remove('hidden'); AOS.refresh(); }
        });
    });

    // Payment Modal Logic
    document.querySelectorAll('.pay-btn').forEach(button => {
        button.addEventListener('click', () => {
            const ritwikId = button.dataset.ritwikId;
            const ritwikName = button.dataset.ritwikName;
            const pending = parseFloat(button.dataset.pending);
            
            if (pending <= 0) { alert('No pending amount to pay.'); return; }

            const modal = modals.payment;
            document.getElementById('payment-modal-title').innerHTML = `<i class="fas fa-hand-holding-usd text-green-500 mr-3"></i>Pay ${ritwikName}`;
            document.getElementById('payment-ritwik-id').value = ritwikId;
            const amountInput = document.getElementById('payment-amount');
            
            // Set placeholder and clear value for partial payments
            amountInput.value = '';
            amountInput.placeholder = `e.g., 1500.00`;
            amountInput.max = pending.toFixed(2);
            document.getElementById('max-payable').textContent = pending.toFixed(2);
            showModal(modal);
        });
    });

    document.getElementById('payment-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const amountToPay = parseFloat(formData.get('amount'));
        const maxPayable = parseFloat(document.getElementById('payment-amount').max);

        if (isNaN(amountToPay) || amountToPay <= 0 || amountToPay > maxPayable) {
            alert(`Invalid amount. Please enter a value between 0.01 and ${maxPayable.toFixed(2)}.`);
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

        try {
            const response = await fetch('?', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Something went wrong');
            
            alert(result.message);
            window.location.reload();
        } catch (error) {
            alert('Error: ' + error.message);
            submitButton.disabled = false;
            submitButton.textContent = 'Submit Payment';
        }
    });

    // Payment History Logic
    document.querySelectorAll('.view-payment-history-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const ritwikId = button.dataset.ritwikId;
            const ritwikName = button.dataset.ritwikName;
            const modal = modals.paymentHistory;
            
            modal.header.innerHTML = `<h3 class="text-2xl font-semibold text-gray-900 flex items-center"><i class="fas fa-receipt text-blue-500 mr-3"></i>Payment History</h3><p class="text-sm text-gray-500 ml-9">For Ritwik: ${ritwikName}</p>`;
            modal.loader.innerHTML = `<i class="fas fa-spinner fa-spin fa-3x text-blue-500"></i><p class="mt-2 text-gray-600">Loading Payment History...</p>`;
            modal.loader.style.display = 'block';
            modal.content.classList.add('hidden');
            modal.content.innerHTML = '';
            showModal(modal);

            try {
                const response = await fetch(`?action=get_payment_history&id=${ritwikId}`);
                const payments = await response.json();
                if (payments.error) throw new Error(payments.error);

                if (payments.length > 0) {
                    let tableRows = payments.map(p => `
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 text-sm text-gray-800">${new Date(p.payment_date).toLocaleString('en-IN')}</td>
                            <td class="p-3 text-sm font-bold text-gray-900 text-right">${parseFloat(p.amount_paid).toFixed(2)}</td>
                            <td class="p-3 text-sm text-gray-500">${p.payment_method}</td>
                            <td class="p-3 text-sm text-gray-500">${p.notes || '-'}</td>
                        </tr>`).join('');
                    
                    modal.content.innerHTML = `<table class="min-w-full"><thead class="bg-gray-100"><tr><th class="p-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="p-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (₹)</th><th class="p-2 text-left text-xs font-medium text-gray-500 uppercase">Method</th><th class="p-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">${tableRows}</tbody></table>`;
                } else {
                    modal.content.innerHTML = `<div class="text-center py-10 text-gray-500"><i class="fas fa-info-circle fa-2x mb-2"></i><p>No payment history found for this Ritwik.</p></div>`;
                }
            } catch (error) { modal.content.innerHTML = `<div class="text-center py-10 text-red-500"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><p>Failed to load data. ${error.message}</p></div>`; }
            finally { modal.loader.style.display = 'none'; modal.content.classList.remove('hidden'); }
        });
    });
});
</script>
</body>
</html>

