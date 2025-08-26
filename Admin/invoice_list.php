<?php
/*
================================================================================
 INVOICE LIST - BROWSE & SEARCH DONATIONS
================================================================================
*/
require_once '../config/db.php'; // Make sure this path is correct

// Fetch all invoices with family details
$invoices = $pdo->query("
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
    ORDER BY di.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Invoices</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background-color: #f3f4f6; }
        .invoice-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .invoice-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-file-invoice-dollar text-indigo-600 mr-4"></i>Donation Invoices
            </h1>
            <p class="text-gray-500 mt-1">Browse and search all saved donation records.</p>
        </header>

        <!-- Search Bar -->
        <div class="mb-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Search by Invoice No, Family Code, or Name...">
            </div>
        </div>

        <!-- Invoices Grid -->
        <div id="invoiceGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if (empty($invoices)): ?>
                <div class="col-span-full text-center text-gray-500 py-10">
                    <i class="fas fa-box-open fa-3x mb-4"></i>
                    <p class="text-xl">No invoices found.</p>
                </div>
            <?php else: foreach ($invoices as $invoice): ?>
                <a href="view_invoice.php?id=<?= $invoice['id'] ?>" class="invoice-card bg-white rounded-xl shadow-md overflow-hidden block" data-search-term="<?= strtolower(htmlspecialchars($invoice['invoice_no'] . ' ' . $invoice['family_code'] . ' ' . $invoice['head_name'])) ?>">
                    <div class="p-5">
                        <div class="flex justify-between items-center">
                            <p class="text-sm font-medium text-indigo-600"><?= htmlspecialchars($invoice['invoice_no']) ?></p>
                            <p class="text-xs text-gray-500"><?= date('d M, Y', strtotime($invoice['created_at'])) ?></p>
                        </div>
                        <p class="mt-2 text-lg font-bold text-gray-900">₹<?= number_format($invoice['total_amount'], 2) ?></p>
                        <div class="mt-3 text-sm text-gray-600">
                            <p class="font-semibold"><?= htmlspecialchars($invoice['head_name']) ?></p>
                            <p class="text-gray-500"><?= htmlspecialchars($invoice['family_code']) ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-2 text-xs font-semibold text-indigo-700 text-right">
                        View Details <i class="fas fa-arrow-right ml-1"></i>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let grid = document.getElementById('invoiceGrid');
            let cards = grid.getElementsByClassName('invoice-card');

            for (let i = 0; i < cards.length; i++) {
                let searchTerm = cards[i].getAttribute('data-search-term');
                if (searchTerm.indexOf(filter) > -1) {
                    cards[i].style.display = "";
                } else {
                    cards[i].style.display = "none";
                }
            }
        });
    </script>
</body>
</html>
