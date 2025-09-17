<?php

require_once '../config/db.php'; // Make sure this path is correct

// --- Initialize filter variables ---
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$month = $_GET['month'] ?? null;
$year = $_GET['year'] ?? date('Y'); // Default to current year
$search_query = $_GET['search'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';

// --- Build the base SQL query ---
$sql = "
    SELECT 
        di.id,
        di.invoice_no,
        di.total_amount,
        di.created_at,
        f.family_code,
        fm.name as head_name
    FROM donation_invoices di
    JOIN families f ON di.family_id = f.id
    LEFT JOIN family_members fm ON f.head_of_family_id = fm.id
    WHERE 1=1
";
$params = [];
$report_description = 'Showing all donation invoices.';

// --- Append conditions based on filters ---
if ($filter_type === 'date_range' && !empty($start_date) && !empty($end_date)) {
    $sql .= " AND di.created_at >= ? AND di.created_at < ?";
    $params[] = $start_date;
    $params[] = date('Y-m-d', strtotime($end_date . ' +1 day'));
    $report_description = "Showing invoices from <strong>" . htmlspecialchars($start_date) . "</strong> to <strong>" . htmlspecialchars($end_date) . "</strong>.";
} elseif ($filter_type === 'criteria_search') {
    $report_parts = [];
    if (!empty($month)) {
        $sql .= " AND MONTH(di.created_at) = ?";
        $params[] = $month;
        $report_parts[] = "<strong>" . date("F", mktime(0, 0, 0, $month, 10)) . "</strong>";
    }
    if (!empty($year)) {
        $sql .= " AND YEAR(di.created_at) = ?";
        $params[] = $year;
        $report_parts[] = "<strong>" . htmlspecialchars($year) . "</strong>";
    }
    if (!empty($search_query)) {
        $sql .= " AND (di.invoice_no LIKE ? OR fm.name LIKE ? OR f.family_code LIKE ?)";
        $search_param = "%" . $search_query . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $report_parts[] = "for search '<strong>" . htmlspecialchars($search_query) . "</strong>'";
    }
    if (!empty($report_parts)) {
        $report_description = "Showing invoices for " . implode(', ', $report_parts) . ".";
    } else {
        // Default to current month if no specific criteria is selected in this form
        $sql .= " AND MONTH(di.created_at) = ? AND YEAR(di.created_at) = ?";
        $params[] = date('m');
        $params[] = date('Y');
        $report_description = "Showing invoices for <strong>" . date('F, Y') . "</strong>.";
        $month = date('m'); // Set for dropdown selection
    }
}

$sql .= " ORDER BY di.created_at DESC";

// --- Execute the query to get invoice list ---
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calculate Total Collection for the filtered period ---
// Re-build the query for SUM without the SELECT columns and ORDER BY
$where_clause_pos = strpos($sql, 'WHERE');
$where_clause = substr($sql, $where_clause_pos);
$total_sql = "SELECT SUM(di.total_amount) as total FROM donation_invoices di JOIN families f ON di.family_id = f.id LEFT JOIN family_members fm ON f.head_of_family_id = fm.id " . $where_clause;

$stmt_total = $pdo->prepare($total_sql);
$stmt_total->execute($params);
$total_collection = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "invoices_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice No', 'Date', 'Time', 'Family Head', 'Family Code', 'Amount']);

    if (!empty($invoices)) {
        foreach ($invoices as $invoice) {
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
    <title>Donation Invoice History</title>
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
                <h1 class="text-3xl font-bold text-gray-800">Donation Invoice History</h1>
                <p class="text-gray-500 mt-1"><?= $report_description ?></p>
            </div>
            <div class="flex items-center space-x-3 mt-4 md:mt-0">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition duration-300 flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export CSV
                </a>
                <a href="arghya_pradan.php" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition duration-300 flex items-center">
                    <i class="fas fa-plus mr-2"></i>New Donation
                </a>
            </div>
        </div>

        <div class="filter-section">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Filter Reports</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                 <a href="?filter_type=date_range&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="text-center bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition">Today's Report</a>
                <a href="?filter_type=criteria_search&month=<?= date('m') ?>&year=<?= date('Y') ?>" class="text-center bg-purple-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-purple-600 transition">This Month</a>
                <a href="?filter_type=criteria_search&year=<?= date('Y') ?>" class="text-center bg-teal-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-teal-600 transition">This Year</a>
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="text-center bg-gray-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-gray-600 transition">Reset Filters</a>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-8 gap-y-6">
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
                    <input type="hidden" name="filter_type" value="criteria_search">
                    <h3 class="font-semibold text-gray-600">By Month, Year & Search:</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                        <div class="md:col-span-1">
                            <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                            <select name="month" id="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Any</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= ($month == $m) ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="md:col-span-1">
                            <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" id="year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                               <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= ($year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2 md:col-span-1">
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_query) ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md py-1.5 px-3" placeholder="Invoice, Name...">
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition w-full">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Invoice No</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Date & Time</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Family Head</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Family Code</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                        <th class="px-3 py-2 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 border-t">No invoices found for the selected criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $i => $invoice): ?>
                            <tr class="<?= ($i % 2 == 0) ? 'bg-white' : 'bg-slate-50' ?>">
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm font-medium text-gray-800 border-t border-x border-gray-200"><?= htmlspecialchars($invoice['invoice_no']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= date('d-m-Y h:i A', strtotime($invoice['created_at'])) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= htmlspecialchars($invoice['head_name']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= htmlspecialchars($invoice['family_code']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-right text-sm text-gray-800 font-semibold border-t border-x border-gray-200">₹<?= number_format($invoice['total_amount'], 2) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium border-t border-gray-200">
                                    <a href="view_invoice.php?id=<?= $invoice['id'] ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-100">
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-right font-bold text-gray-700 text-sm border-t-2 border-gray-300">Filtered Total:</td>
                        <td class="px-3 py-3 text-right font-bold text-gray-900 text-lg border-t-2 border-x border-gray-300">₹<?= number_format($total_collection, 2) ?></td>
                        <td class="border-t-2 border-gray-300"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>