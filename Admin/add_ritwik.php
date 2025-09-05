<?php

// Include the database connection file
require_once '../config/db.php';

// --- Helper Function ---
function getNextRitwikId($pdo) {
    $stmt = $pdo->query("SELECT unique_id FROM ritwiks ORDER BY id DESC LIMIT 1");
    $lastRitwik = $stmt->fetch();
    if ($lastRitwik) {
        $lastIdNum = (int) substr($lastRitwik['unique_id'], 3);
        $newIdNum = $lastIdNum + 1;
        return 'RIT' . str_pad($newIdNum, 3, '0', STR_PAD_LEFT);
    }
    return 'RIT001';
}

// --- SECTION 2: FORM PROCESSING (ADD, UPDATE, DELETE) ---
$formMessage = '';
$formMessageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ACTION: ADD RITWIK ---
    if ($action === 'add') {
        // Validation check updated: 'details' is no longer required.
        if (empty($_POST['unique_id']) || empty($_POST['name']) || empty($_POST['password']) || !isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
            $formMessage = "Please fill all required fields and upload an image.";
            $formMessageType = 'error';
        } else {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $image_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $target_file = $target_dir . uniqid('ritwik_', true) . '.' . $image_extension;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                try {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $sql = "INSERT INTO ritwiks (unique_id, name, details, password, image_path) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    // Use trim on details, but it's okay if it's empty
                    $stmt->execute([$_POST['unique_id'], $_POST['name'], trim($_POST['details']), $hashed_password, $target_file]);
                    $formMessage = "New RITWIK added successfully!";
                    $formMessageType = 'success';
                } catch (PDOException $e) {
                    $formMessage = "Database error: " . $e->getMessage();
                    $formMessageType = 'error';
                }
            } else {
                $formMessage = "Sorry, there was an error uploading your file.";
                $formMessageType = 'error';
            }
        }
    }

    // --- ACTION: UPDATE RITWIK ---
    if ($action === 'update') {
        $ritwik_id = $_POST['ritwik_id'];
        $name = trim($_POST['name']);
        
        $sql = "UPDATE ritwiks SET name = ?";
        $params = [$name];

        // Handle optional details update (This part is already correct)
        // It will only update details if the field is not empty.
        // If you want to allow clearing the field, we should change this.
        // For now, it will update with the submitted value.
        if (isset($_POST['details'])) {
            $sql .= ", details = ?";
            $params[] = trim($_POST['details']);
        }

        // Handle optional password update
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        // Handle optional image update
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $oldImagePath = $pdo->prepare("SELECT image_path FROM ritwiks WHERE id = ?");
            $oldImagePath->execute([$ritwik_id]);
            $oldImage = $oldImagePath->fetchColumn();

            $target_dir = "uploads/";
            $image_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $new_target_file = $target_dir . uniqid('ritwik_', true) . '.' . $image_extension;
            
            if(move_uploaded_file($_FILES["image"]["tmp_name"], $new_target_file)) {
                $sql .= ", image_path = ?";
                $params[] = $new_target_file;
                if ($oldImage && file_exists($oldImage)) {
                    unlink($oldImage);
                }
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $ritwik_id;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $formMessage = "RITWIK details updated successfully!";
            $formMessageType = 'success';
        } catch (PDOException $e) {
            $formMessage = "Database error on update: " . $e->getMessage();
            $formMessageType = 'error';
        }
    }

    // --- ACTION: DELETE RITWIK ---
    if ($action === 'delete') {
        $ritwik_id = $_POST['ritwik_id'];
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM ritwiks WHERE id = ?");
            $stmt->execute([$ritwik_id]);
            $image_path = $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM ritwiks WHERE id = ?");
            $stmt->execute([$ritwik_id]);

            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
            $formMessage = "RITWIK deleted successfully.";
            $formMessageType = 'success';
        } catch (PDOException $e) {
            $formMessage = "Database error on delete: " . $e->getMessage();
            $formMessageType = 'error';
        }
    }
}

// --- SECTION 3: DATA FETCHING & PAGINATION ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total_results = $pdo->query("SELECT COUNT(*) FROM ritwiks")->fetchColumn();
$total_pages = ceil($total_results / $limit);
$stmt = $pdo->prepare("SELECT * FROM ritwiks ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$ritwiks = $stmt->fetchAll();
$nextUniqueId = getNextRitwikId($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage RITWIKs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .modal-backdrop { transition: opacity 0.3s ease-in-out; }
        .modal-content { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .details-scroll::-webkit-scrollbar { width: 5px; }
        .details-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .details-scroll::-webkit-scrollbar-thumb { background: #888; border-radius: 5px;}
        .details-scroll::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>
</head>
<body class="font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="flex justify-between items-center mb-8" data-aos="fade-down">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-800">RITWIK Management</h1>
            <button id="addRitwikBtn" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg shadow-md hover:bg-indigo-700 transition duration-300 flex items-center space-x-2">
                <i class="fas fa-plus"></i><span>Add RITWIK</span>
            </button>
        </header>

        <?php if ($formMessage): ?>
        <div data-aos="fade-in" class="p-4 mb-4 text-sm rounded-lg <?= $formMessageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>" role="alert">
            <span class="font-medium"><?= htmlspecialchars($formMessage) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($ritwiks)): ?>
                <div class="col-span-full text-center text-gray-500 py-10" data-aos="zoom-in"><i class="fas fa-users-slash fa-3x mb-4"></i><p class="text-xl">No RITWIKs found.</p></div>
            <?php else: ?>
                <?php foreach ($ritwiks as $index => $ritwik): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden" data-aos="fade-up" data-aos-delay="<?= $index * 50 ?>">
                        <img src="<?= htmlspecialchars($ritwik['image_path']) ?>" alt="<?= htmlspecialchars($ritwik['name']) ?>" class="w-full h-56 object-cover" onerror="this.onerror=null;this.src='https://placehold.co/600x400/EFEFEF/AAAAAA?text=Image';">
                        <div class="p-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($ritwik['name']) ?></h2>
                            <p class="text-sm font-semibold text-indigo-500 mb-4"><?= htmlspecialchars($ritwik['unique_id']) ?></p>
                            <p class="text-gray-600 text-base mb-4 h-24 overflow-y-auto details-scroll"><?= htmlspecialchars($ritwik['details']) ?></p>
                            <div class="flex justify-end space-x-3">
                                <button class="edit-btn text-gray-400 hover:text-blue-500 transition duration-300" title="Edit"
                                    data-id="<?= $ritwik['id'] ?>"
                                    data-name="<?= htmlspecialchars($ritwik['name']) ?>"
                                    data-details="<?= htmlspecialchars($ritwik['details']) ?>"
                                    data-image="<?= htmlspecialchars($ritwik['image_path']) ?>">
                                    <i class="fas fa-edit fa-lg"></i>
                                </button>
                                <button class="delete-btn text-gray-400 hover:text-red-500 transition duration-300" title="Delete"
                                    data-id="<?= $ritwik['id'] ?>"
                                    data-name="<?= htmlspecialchars($ritwik['name']) ?>">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav class="mt-12 flex justify-center" data-aos="fade-up">
            <ul class="inline-flex items-center -space-x-px">
                <li><a href="?page=<?= max(1, $page - 1) ?>" class="py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"><i class="fas fa-chevron-left mr-1"></i> Previous</a></li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li><a href="?page=<?= $i ?>" class="py-2 px-3 leading-tight <?= $i == $page ? 'text-indigo-600 bg-indigo-50 border border-indigo-300' : 'text-gray-500 bg-white border border-gray-300' ?> hover:bg-gray-100 hover:text-gray-700"><?= $i ?></a></li>
                <?php endfor; ?>
                <li><a href="?page=<?= min($total_pages, $page + 1) ?>" class="py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">Next <i class="fas fa-chevron-right ml-1"></i></a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <div id="addRitwikModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-8 modal-content transform scale-95 opacity-0">
            <div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold text-gray-800">Add New RITWIK</h2><button class="close-modal-btn text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form action="add_ritwik.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-gray-700">Unique ID</label><input type="text" name="unique_id" value="<?= htmlspecialchars($nextUniqueId) ?>" readonly class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm"></div>
                    <div><label for="add-name" class="block text-sm font-medium text-gray-700">Full Name</label><input type="text" id="add-name" name="name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></div>
                    <div><label for="add-details" class="block text-sm font-medium text-gray-700">Details / Bio (optional)</label><textarea id="add-details" name="details" rows="4" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea></div>
                    <div><label for="add-password" class="block text-sm font-medium text-gray-700">Password</label><input type="password" id="add-password" name="password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Profile Image</label><div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md"><div class="space-y-1 text-center"><i class="fas fa-image fa-3x text-gray-400 mx-auto"></i><div class="flex text-sm text-gray-600"><label for="add-image" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500"><span>Upload a file</span><input id="add-image" name="image" type="file" class="sr-only" required></label><p class="pl-1">or drag and drop</p></div><p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p></div></div></div>
                </div>
                <div class="mt-8 text-right"><button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-indigo-700 transition duration-300"><i class="fas fa-save mr-2"></i>Save RITWIK</button></div>
            </form>
        </div>
    </div>
    
    <div id="editRitwikModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-8 modal-content transform scale-95 opacity-0">
            <div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold text-gray-800">Edit RITWIK Details</h2><button class="close-modal-btn text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form action="add_ritwik.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit-ritwik-id" name="ritwik_id">
                <div class="space-y-4">
                    <div class="text-center"><img id="edit-image-preview" src="" alt="Current Image" class="w-32 h-32 rounded-full object-cover mx-auto mb-4 border-4 border-gray-200"></div>
                    <div><label for="edit-name" class="block text-sm font-medium text-gray-700">Full Name</label><input type="text" id="edit-name" name="name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></div>
                    <div><label for="edit-details" class="block text-sm font-medium text-gray-700">Details / Bio (optional)</label><textarea id="edit-details" name="details" rows="4" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea></div>
                    <div><label for="edit-password" class="block text-sm font-medium text-gray-700">New Password (optional)</label><input type="password" id="edit-password" name="password" placeholder="Leave blank to keep current password" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Change Profile Image (optional)</label><input id="edit-image" name="image" type="file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"></div>
                </div>
                <div class="mt-8 text-right"><button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-blue-700 transition duration-300"><i class="fas fa-sync-alt mr-2"></i>Update Details</button></div>
            </form>
        </div>
    </div>

    <div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-8 modal-content transform scale-95 opacity-0 text-center">
            <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Are you sure?</h2>
            <p class="text-gray-600 mb-6">Do you really want to delete <strong id="delete-ritwik-name"></strong>? This process cannot be undone.</p>
            <form action="add_ritwik.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete-ritwik-id" name="ritwik_id">
                <div class="flex justify-center space-x-4">
                    <button type="button" class="close-modal-btn bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-400 transition duration-300">Cancel</button>
                    <button type="submit" class="bg-red-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-red-700 transition duration-300">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });

        // --- Generic Modal Handling ---
        const openModal = (modal) => {
            modal.classList.remove('pointer-events-none', 'opacity-0');
            modal.querySelector('.modal-content').classList.remove('scale-95', 'opacity-0');
        };
        const closeModal = (modal) => {
            modal.querySelector('.modal-content').classList.add('scale-95', 'opacity-0');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('pointer-events-none'), 300);
        };

        // --- Add Modal ---
        const addModal = document.getElementById('addRitwikModal');
        document.getElementById('addRitwikBtn').addEventListener('click', () => openModal(addModal));

        // --- Edit and Delete Modal Logic ---
        const editModal = document.getElementById('editRitwikModal');
        const deleteModal = document.getElementById('deleteConfirmModal');

        document.body.addEventListener('click', function(e) {
            // --- Edit Button Click ---
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) {
                document.getElementById('edit-ritwik-id').value = editBtn.dataset.id;
                document.getElementById('edit-name').value = editBtn.dataset.name;
                document.getElementById('edit-details').value = editBtn.dataset.details;
                document.getElementById('edit-image-preview').src = editBtn.dataset.image;
                openModal(editModal);
            }

            // --- Delete Button Click ---
            const deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) {
                document.getElementById('delete-ritwik-id').value = deleteBtn.dataset.id;
                document.getElementById('delete-ritwik-name').textContent = deleteBtn.dataset.name;
                openModal(deleteModal);
            }

            // --- Close Button Click ---
            const closeBtn = e.target.closest('.close-modal-btn');
            if (closeBtn) {
                closeModal(closeBtn.closest('.modal-backdrop'));
            }

            // --- Backdrop Click to Close ---
            if (e.target.classList.contains('modal-backdrop')) {
                closeModal(e.target);
            }
        });
    </script>
</body>
</html>