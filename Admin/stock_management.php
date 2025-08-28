<?php
// -----------------------------------------------------------------------------
// --- ASHRAM STOCK MANAGEMENT (ALL-IN-ONE V2) ---
// -----------------------------------------------------------------------------
// This file now includes item-specific history links and a 'purpose' field for transactions.

require_once '../config/db.php';
session_start();

// Determine which view to show.
$view = isset($_GET['view']) && $_GET['view'] === 'history' ? 'history' : 'stock';
$product_filter_id = isset($_GET['product_id']) ? filter_var($_GET['product_id'], FILTER_VALIDATE_INT) : null;

// --- FORM HANDLING LOGIC ---
if ($view === 'stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ADD NEW PRODUCT ---
    if (isset($_POST['add_product'])) {
        // (This logic remains the same)
        $name = htmlspecialchars(strip_tags(trim($_POST['name'])));
        $category = htmlspecialchars(strip_tags(trim($_POST['category'])));
        $quantity = filter_var(trim($_POST['quantity']), FILTER_VALIDATE_FLOAT);
        $unit = htmlspecialchars(strip_tags(trim($_POST['unit'])));

        if (!empty($name) && !empty($category) && $quantity !== false && $quantity >= 0 && !empty($unit)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO products (name, category, quantity, unit) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssds", $name, $category, $quantity, $unit);
                $stmt->execute();
                $product_id = $stmt->insert_id;
                $stmt->close();

                $history_stmt = $conn->prepare("INSERT INTO stock_history (product_id, product_name, action, quantity_change, new_quantity, purpose) VALUES (?, ?, 'Product Added', ?, ?, 'Initial stock')");
                $history_stmt->bind_param("isdd", $product_id, $name, $quantity, $quantity);
                $history_stmt->execute();
                $history_stmt->close();

                $conn->commit();
                $_SESSION['message'] = ['text' => 'Product added successfully!', 'type' => 'success'];
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = ['text' => 'Error adding product: ' . $e->getMessage(), 'type' => 'error'];
            }
        } else {
            $_SESSION['message'] = ['text' => 'Invalid input. Please fill all fields correctly.', 'type' => 'error'];
        }
    }

    // --- TAKE OUT STOCK (SUBTRACT) ---
    if (isset($_POST['take_out_stock'])) {
        $product_id = filter_var(trim($_POST['product_id']), FILTER_VALIDATE_INT);
        $quantity_to_take = filter_var(trim($_POST['quantity_to_take']), FILTER_VALIDATE_FLOAT);
        // **NEW**: Get the purpose, or set to null if empty
        $purpose = !empty(trim($_POST['purpose'])) ? htmlspecialchars(strip_tags(trim($_POST['purpose']))) : null;

        if ($product_id !== false && $quantity_to_take !== false && $quantity_to_take > 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT name, quantity FROM products WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $current_quantity = $product['quantity'];
                $product_name = $product['name'];
                $stmt->close();

                if ($current_quantity >= $quantity_to_take) {
                    $new_quantity = $current_quantity - $quantity_to_take;
                    
                    $update_stmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
                    $update_stmt->bind_param("di", $new_quantity, $product_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // **NEW**: Save the purpose into the history log
                    $history_stmt = $conn->prepare("INSERT INTO stock_history (product_id, product_name, action, quantity_change, new_quantity, purpose) VALUES (?, ?, 'Stock Taken Out', ?, ?, ?)");
                    $history_stmt->bind_param("isdds", $product_id, $product_name, $quantity_to_take, $new_quantity, $purpose);
                    $history_stmt->execute();
                    $history_stmt->close();
                    
                    $conn->commit();
                    $_SESSION['message'] = ['text' => 'Stock updated successfully!', 'type' => 'success'];
                } else {
                    throw new Exception('Not enough stock available.');
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = ['text' => 'Error updating stock: ' . $e->getMessage(), 'type' => 'error'];
            }
        } else {
            $_SESSION['message'] = ['text' => 'Invalid input for taking out stock.', 'type' => 'error'];
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- DATA FETCHING LOGIC ---
if ($view === 'history') {
    $history_logs = [];
    $product_filter_name = null;
    $sql = "SELECT id, product_name, action, quantity_change, new_quantity, purpose, timestamp FROM stock_history";
    if ($product_filter_id) {
        // **NEW**: Filter history by a specific product ID
        $sql .= " WHERE product_id = ? ORDER BY timestamp DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_filter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        // Get product name for the header
        $product_name_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        $product_name_stmt->bind_param("i", $product_filter_id);
        $product_name_stmt->execute();
        $product_filter_name = $product_name_stmt->get_result()->fetch_assoc()['name'];
    } else {
        $sql .= " ORDER BY timestamp DESC";
        $result = $conn->query($sql);
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history_logs[] = $row;
        }
    }
} else {
    $products = [];
    $result = $conn->query("SELECT id, name, category, quantity, unit FROM products ORDER BY category, name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashram Stock Management</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="antialiased text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8" x-data="{ addModal: false, takeOutModal: false, selectedProduct: {}, search: '' }">

        <!-- HEADER -->
        <header class="mb-8" data-aos="fade-down">
            <?php if ($view === 'history'): ?>
                <h1 class="text-4xl font-bold text-gray-800 flex items-center"><i class="fas fa-history mr-4 text-gray-600"></i>Stock Transaction History</h1>
                <?php if ($product_filter_name): ?>
                    <p class="text-gray-600 mt-2 text-xl">Showing history for: <strong class="text-orange-600"><?= htmlspecialchars($product_filter_name) ?></strong></p>
                <?php else: ?>
                    <p class="text-gray-500 mt-2">A complete log of all inventory movements.</p>
                <?php endif; ?>
            <?php else: ?>
                <h1 class="text-4xl font-bold text-gray-800 flex items-center"><i class="fas fa-warehouse mr-4 text-orange-600"></i>Ashram Stock Management</h1>
                <p class="text-gray-500 mt-2">Manage all inventory for the Ashram, from groceries to furniture.</p>
            <?php endif; ?>
        </header>

        <!-- FLASH MESSAGE -->
        <?php if (isset($_SESSION['message'])): ?>
            <?php $message = $_SESSION['message']; unset($_SESSION['message']); ?>
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="mb-6 p-4 rounded-lg shadow-md flex items-center justify-between <?= $message['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                <div><i class="fas <?= $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-3"></i><span><?= htmlspecialchars($message['text']) ?></span></div>
                <button @click="show = false" class="text-xl font-bold">&times;</button>
            </div>
        <?php endif; ?>

        <main>
            <!-- ACTION BAR -->
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4" data-aos="fade-left">
                <div class="relative w-full sm:w-1/3">
                    <input type="text" x-model="search" placeholder="<?= $view === 'history' ? 'Search by purpose...' : 'Search by name or category...' ?>" class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <?php if ($view === 'history'): ?>
                        <a href="stock_management.php" class="w-full sm:w-auto bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg shadow-lg transform hover:scale-105 transition-transform duration-300 flex items-center justify-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Stock
                        </a>
                    <?php else: ?>
                        <a href="stock_management.php?view=history" class="w-1/2 sm:w-auto bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg shadow-lg transform hover:scale-105 transition-transform duration-300 flex items-center justify-center">
                            <i class="fas fa-history mr-2"></i> All History
                        </a>
                        <button @click="addModal = true" class="w-1/2 sm:w-auto bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg shadow-lg transform hover:scale-105 transition-transform duration-300 flex items-center justify-center">
                            <i class="fas fa-plus mr-2"></i> Add Product
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DYNAMIC CONTENT (TABLES) -->
            <div class="bg-white rounded-xl shadow-xl overflow-hidden" data-aos="fade-up" data-aos-delay="200">
                <div class="overflow-x-auto">
                    <?php if ($view === 'history'): ?>
                        <!-- HISTORY TABLE -->
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-6 py-3"><i class="fas fa-calendar-alt mr-2"></i>Date</th>
                                    <?php if (!$product_filter_id): ?><th class="px-6 py-3"><i class="fas fa-box mr-2"></i>Product</th><?php endif; ?>
                                    <th class="px-6 py-3"><i class="fas fa-info-circle mr-2"></i>Action</th>
                                    <th class="px-6 py-3"><i class="fas fa-tasks mr-2"></i>Purpose</th>
                                    <th class="px-6 py-3"><i class="fas fa-exchange-alt mr-2"></i>Change</th>
                                    <th class="px-6 py-3"><i class="fas fa-clipboard-check mr-2"></i>New Qty</th>
                                </tr>
                            </thead>
                            <tbody x-cloak>
                                <?php if (empty($history_logs)): ?>
                                    <tr><td colspan="6" class="text-center py-10 text-gray-500"><i class="fas fa-file-alt fa-3x mb-3"></i><p class="text-lg">No transaction history found.</p></td></tr>
                                <?php else: ?>
                                    <?php foreach ($history_logs as $log): ?>
                                        <tr class="bg-white border-b hover:bg-gray-50" x-show="search === '' || '<?= strtolower(htmlspecialchars($log['purpose'] ?? '')) ?>'.includes(search.toLowerCase())" x-transition>
                                            <td class="px-6 py-4"><?= date('M d, Y, g:i A', strtotime($log['timestamp'])) ?></td>
                                            <?php if (!$product_filter_id): ?><th class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($log['product_name']) ?></th><?php endif; ?>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-medium rounded-full flex items-center gap-1 w-max <?= $log['action'] === 'Product Added' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>"><i class="fas <?= $log['action'] === 'Product Added' ? 'fa-plus' : 'fa-minus' ?>"></i> <?= htmlspecialchars($log['action']) ?></span></td>
                                            <td class="px-6 py-4 text-gray-600 italic"><?= htmlspecialchars($log['purpose'] ?? 'N/A') ?></td>
                                            <td class="px-6 py-4 font-semibold <?= $log['action'] === 'Product Added' ? 'text-green-600' : 'text-red-500' ?>"><?= ($log['action'] === 'Product Added' ? '+' : '-') . number_format($log['quantity_change'], 2) ?></td>
                                            <td class="px-6 py-4 font-bold text-gray-700"><?= number_format($log['new_quantity'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <!-- STOCK TABLE -->
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-6 py-3">Product Name</th>
                                    <th class="px-6 py-3">Category</th>
                                    <th class="px-6 py-3">Quantity</th>
                                    <th class="px-6 py-3">Unit</th>
                                    <th class="px-6 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody x-cloak>
                                <?php if (empty($products)): ?>
                                    <tr><td colspan="5" class="text-center py-10 text-gray-500"><i class="fas fa-box-open fa-3x mb-3"></i><p class="text-lg">No products found.</p></td></tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr class="bg-white border-b hover:bg-gray-50" x-show="search === '' || '<?= strtolower(htmlspecialchars($product['name'])) ?>'.includes(search.toLowerCase()) || '<?= strtolower(htmlspecialchars($product['category'])) ?>'.includes(search.toLowerCase())" x-transition>
                                            <th class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></th>
                                            <td class="px-6 py-4"><span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs font-medium rounded-full"><?= htmlspecialchars($product['category']) ?></span></td>
                                            <td class="px-6 py-4"><span class="font-semibold text-lg <?= $product['quantity'] < 10 ? 'text-red-500' : ($product['quantity'] < 50 ? 'text-yellow-600' : 'text-green-600') ?>"><?= number_format($product['quantity'], 2) ?></span></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($product['unit']) ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="flex justify-center items-center gap-4">
                                                    <button @click="takeOutModal = true; selectedProduct = { id: <?= $product['id'] ?>, name: '<?= htmlspecialchars(addslashes($product['name'])) ?>', quantity: <?= $product['quantity'] ?>, unit: '<?= htmlspecialchars($product['unit']) ?>' }" class="font-medium text-blue-600 hover:underline">Take Out</button>
                                                    <a href="stock_management.php?view=history&product_id=<?= $product['id'] ?>" title="View History for <?= htmlspecialchars($product['name']) ?>" class="text-gray-500 hover:text-orange-600">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- MODALS -->
        <!-- Add Product Modal -->
        <div x-show="addModal" x-cloak @keydown.escape.window="addModal = false" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div @click.outside="addModal = false" class="bg-white rounded-lg shadow-2xl w-full max-w-md" x-show="addModal" x-transition>
                <form method="POST" action="stock_management.php">
                    <div class="p-6">
                        <h3 class="text-2xl font-semibold mb-4">Add New Product</h3>
                        <div class="space-y-4">
                            <div><label class="block mb-1 font-medium">Product Name</label><input type="text" name="name" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-orange-500"></div>
                            <div><label class="block mb-1 font-medium">Category</label><input type="text" name="category" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-orange-500"></div>
                            <div class="flex gap-4">
                                <div class="w-1/2"><label class="block mb-1 font-medium">Quantity</label><input type="number" step="0.01" name="quantity" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-orange-500"></div>
                                <div class="w-1/2"><label class="block mb-1 font-medium">Unit</label><input type="text" name="unit" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-orange-500"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-100 px-6 py-4 flex justify-end gap-4 rounded-b-lg">
                        <button type="button" @click="addModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Cancel</button>
                        <button type="submit" name="add_product" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Take Out Stock Modal -->
        <div x-show="takeOutModal" x-cloak @keydown.escape.window="takeOutModal = false" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div @click.outside="takeOutModal = false" class="bg-white rounded-lg shadow-2xl w-full max-w-md" x-show="takeOutModal" x-transition>
                <form method="POST" action="stock_management.php">
                    <input type="hidden" name="product_id" :value="selectedProduct.id">
                    <div class="p-6">
                        <h3 class="text-2xl font-semibold mb-2">Take Out Stock</h3>
                        <p class="mb-4">Product: <strong x-text="selectedProduct.name"></strong> (<span x-text="selectedProduct.quantity"></span> <span x-text="selectedProduct.unit"></span> available)</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block mb-1 font-medium">Quantity to Take</label>
                                <input type="number" step="0.01" name="quantity_to_take" required class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500" :max="selectedProduct.quantity">
                            </div>
                            <div>
                                <label class="block mb-1 font-medium">Purpose (Optional)</label>
                                <input type="text" name="purpose" placeholder="e.g., For kitchen use, event donation" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-100 px-6 py-4 flex justify-end gap-4 rounded-b-lg">
                        <button type="button" @click="takeOutModal = false" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Cancel</button>
                        <button type="submit" name="take_out_stock" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Confirm & Update</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script> AOS.init({ duration: 600, once: true }); </script>
</body>
</html>
