<?php
// Make sure this path is correct and points to your database connection file.
require_once '../config/db.php';

// --- Initialize filter variables ---
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$month = $_GET['month'] ?? null;
$year = $_GET['year'] ?? date('Y'); // Default to current year
$search_query = $_GET['search'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';

// --- Build the base SQL query ---
$sql = "SELECT id, invoice_no, devotee_name, donation_type, amount, created_at FROM general_donations WHERE 1=1";
$params = [];
$report_description = 'Showing all donation records.';

// --- Append conditions based on filters ---
if ($filter_type === 'date_range' && !empty($start_date) && !empty($end_date)) {
    $sql .= " AND created_at >= ? AND created_at < ?";
    $params[] = $start_date;
    $params[] = date('Y-m-d', strtotime($end_date . ' +1 day'));
    $report_description = "Showing donations from <strong>" . htmlspecialchars($start_date) . "</strong> to <strong>" . htmlspecialchars($end_date) . "</strong>.";
} elseif ($filter_type === 'criteria_search') {
    $report_parts = [];
    if (!empty($month)) {
        $sql .= " AND MONTH(created_at) = ?";
        $params[] = $month;
        $report_parts[] = "<strong>" . date("F", mktime(0, 0, 0, $month, 10)) . "</strong>";
    }
    if (!empty($year)) {
        $sql .= " AND YEAR(created_at) = ?";
        $params[] = $year;
        $report_parts[] = "<strong>" . htmlspecialchars($year) . "</strong>";
    }
    if (!empty($search_query)) {
        $sql .= " AND (invoice_no LIKE ? OR devotee_name LIKE ? OR donation_type LIKE ?)";
        $search_param = "%" . $search_query . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $report_parts[] = "for search '<strong>" . htmlspecialchars($search_query) . "</strong>'";
    }
    if (!empty($report_parts)) {
        $report_description = "Showing donations for " . implode(', ', $report_parts) . ".";
    } else {
        // Default to current month if no specific criteria is selected in this form
        $sql .= " AND MONTH(created_at) = ? AND YEAR(created_at) = ?";
        $params[] = date('m');
        $params[] = date('Y');
        $report_description = "Showing donations for <strong>" . date('F, Y') . "</strong>.";
        $month = date('m'); // Set for dropdown selection
    }
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching donations: " . $e->getMessage());
}

// Calculate the total amount for the filtered results
$total_amount = array_sum(array_column($donations, 'amount'));

// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "general-donations_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice No', 'Date', 'Devotee Name', 'Donation Type', 'Amount']);

    if (!empty($donations)) {
        foreach ($donations as $donation) {
            fputcsv($output, [
                $donation['invoice_no'],
                date('d-m-Y', strtotime($donation['created_at'])),
                $donation['devotee_name'],
                $donation['donation_type'],
                $donation['amount']
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
    <title>General Donation History & Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
<body class="bg-gray-100 min-h-screen p-4 sm:p-6 md:p-8">

    <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 border-b pb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">General Donation History</h1>
                <p class="text-gray-500 mt-1"><?= $report_description ?></p>
            </div>
            <div class="flex items-center space-x-3 mt-4 md:mt-0">
                 <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition duration-300 flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export CSV
                </a>
                <a href="utsav_donation.php" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition duration-300 flex items-center">
                    <i class="fas fa-plus mr-2"></i>Add Donation
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
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase">Invoice No</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase">Devotee Name</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase">Donation Type</th>
                        <th class="px-3 py-2 border-b-2 border-x border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase">Amount (₹)</th>
                        <th class="px-3 py-2 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($donations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-gray-500 border-t">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <p>No donations found for the selected criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($donations as $i => $donation): ?>
                            <tr class="<?= ($i % 2 == 0) ? 'bg-white' : 'bg-slate-50' ?>">
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm font-medium text-gray-800 border-t border-x border-gray-200"><?= htmlspecialchars($donation['invoice_no']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= date('d-m-Y', strtotime($donation['created_at'])) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= htmlspecialchars($donation['devotee_name']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-left text-sm text-gray-600 border-t border-x border-gray-200"><?= htmlspecialchars($donation['donation_type']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap font-semibold text-gray-800 text-right border-t border-x border-gray-200">₹<?= number_format($donation['amount'], 2) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-center border-t border-gray-200">
                                    <a href="utsav_donation.php?view_invoice=1&id=<?= $donation['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
    </div>
</body>
</html>