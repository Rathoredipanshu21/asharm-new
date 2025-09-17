<?php
session_start();
require_once '../config/db.php'; // Ensure this path is correct

// --- Initialize filter variables ---
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$month = $_GET['month'] ?? null;
$year = $_GET['year'] ?? null;
$filter_type = $_GET['filter_type'] ?? '';

// --- Build the base SQL query ---
$sql = "
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
    WHERE di.invoice_no LIKE 'PNI-%'
";
$params = [];
$report_description = 'Showing all Pranami invoices.';

// --- Append conditions based on filters ---
if ($filter_type === 'date_range' && !empty($start_date) && !empty($end_date)) {
    $sql .= " AND di.created_at >= ? AND di.created_at < ?";
    $params[] = $start_date;
    $params[] = date('Y-m-d', strtotime($end_date . ' +1 day')); // To include the whole end day
    $report_description = "Showing invoices from <strong>" . htmlspecialchars($start_date) . "</strong> to <strong>" . htmlspecialchars($end_date) . "</strong>.";
} elseif ($filter_type === 'month_year' && !empty($month) && !empty($year)) {
    $sql .= " AND MONTH(di.created_at) = ? AND YEAR(di.created_at) = ?";
    $params[] = $month;
    $params[] = $year;
    $month_name = date("F", mktime(0, 0, 0, $month, 10));
    $report_description = "Showing invoices for <strong>" . $month_name . ", " . htmlspecialchars($year) . "</strong>.";
}

$sql .= " ORDER BY di.created_at DESC";

// --- Execute the query ---
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filtered_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calculate the total amount for the footer ---
$total_amount = array_sum(array_column($filtered_invoices, 'total_amount'));

// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    $filename = "pranami_history_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice No', 'Date', 'Time', 'Family Head', 'Family Code', 'Amount']);

    if (!empty($filtered_invoices)) {
        foreach ($filtered_invoices as $invoice) {
            fputcsv($output, [
                $invoice['invoice_no'],
                date('d-m-Y', strtotime($invoice['created_at'])),
                date('h:i A', strtotime($invoice['created_at'])),
                $invoice['head_name'],
                $invoice['family_code'],
                $invoice['total_amount']
            ]);
        }
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pranami History & Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background-color: #f3f4f6; }
        .filter-section {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 border-b pb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Pranami Invoice History</h1>
                <p class="text-gray-500 mt-1"><?= $report_description ?></p>
            </div>
            <div class="flex items-center space-x-3 mt-4 md:mt-0">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition duration-300 flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export CSV
                </a>
                <a href="pranami.php" class="bg-gray-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Entry
                </a>
            </div>
        </div>

        <div class="filter-section">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Filter Reports</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                 <a href="?filter_type=date_range&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="text-center bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition">Today's Report</a>
                <a href="?filter_type=month_year&month=<?= date('m') ?>&year=<?= date('Y') ?>" class="text-center bg-purple-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-purple-600 transition">This Month</a>
                <a href="?filter_type=month_year&year=<?= date('Y') ?>" class="text-center bg-teal-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-teal-600 transition">This Year</a>
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="text-center bg-gray-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-gray-600 transition">Reset Filters</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                <form method="GET" class="space-y-4">
                    <input type="hidden" name="filter_type" value="date_range">
                    <h3 class="font-semibold text-gray-600">By Custom Date Range:</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition w-full">Show by Date</button>
                    </div>
                </form>

                <form method="GET" class="space-y-4">
                    <input type="hidden" name="filter_type" value="month_year">
                    <h3 class="font-semibold text-gray-600">By Month & Year:</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div>
                            <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                            <select name="month" id="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= (isset($month) && $month == $m) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" id="year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= (isset($year) && $year == $y) ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition w-full">Show by Month</button>
                    </div>
                </form>
            </div>
        </div>


        <?php if (empty($filtered_invoices)): ?>
            <div class="text-center py-12">
                <i class="fas fa-file-invoice-dollar fa-4x text-gray-300"></i>
                <h2 class="mt-4 text-xl font-semibold text-gray-700">No Pranami Invoices Found</h2>
                <p class="text-gray-500 mt-1">There are no invoices matching your selected criteria.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Invoice No</th>
                            <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Date & Time</th>
                            <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Family Head</th>
                            <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Family Code</th>
                            <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                            <th class="px-3 py-2 border-b-2 border-x-0 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_invoices as $i => $invoice): ?>
                            <tr class="<?= ($i % 2 == 0) ? 'bg-white' : 'bg-slate-50' ?>">
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm font-medium text-gray-800 border-t border-x border-gray-200"><?= htmlspecialchars($invoice['invoice_no']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= date('d-m-Y h:i A', strtotime($invoice['created_at'])) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= htmlspecialchars($invoice['head_name']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= htmlspecialchars($invoice['family_code']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-right text-sm text-gray-800 font-semibold border-t border-x border-gray-200">₹<?= number_format($invoice['total_amount'], 2) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium border-t border-x-0 border-gray-200">
                                    <a href="view_pranami_invoice.php?id=<?= $invoice['id'] ?>" class="inline-block bg-indigo-600 text-white font-bold py-1 px-3 rounded-md hover:bg-indigo-700 transition-colors text-xs shadow-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-100">
                        <tr>
                            <td colspan="4" class="px-3 py-3 text-right font-bold text-gray-700 text-sm border-t-2 border-gray-300">Filtered Total:</td>
                            <td class="px-3 py-3 text-right font-bold text-gray-900 text-lg border-t-2 border-x border-gray-300">₹<?= number_format($total_amount, 2) ?></td>
                            <td class="border-t-2 border-gray-300"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>