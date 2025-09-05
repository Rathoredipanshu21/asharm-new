<?php
/*
================================================================================
 ASHRAM FAMILY MANAGEMENT - (REVISED V8 - Address Field Added)
================================================================================
 This file handles:
 1. Database Connection.
 2. Adding Families with REAL-TIME duplicate checking to prevent user frustration.
 3. A comprehensive Edit modal.
 4. Deleting Families.
 5. Displaying all Families.
*/

// --- SECTION 1: DATABASE & INITIAL SETUP ---
require_once '../config/db.php'; // Make sure this path is correct

// --- Helper Function to generate a unique Family Code ---
function generateUniqueFamilyCode($pdo) {
    do {
        $family_code = 'FAM' . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM families WHERE family_code = ?");
        $stmt->execute([$family_code]);
    } while ($stmt->fetchColumn());
    return $family_code;
}

// --- Helper Function to check if a member exists anywhere in the database (Server-Side Fallback) ---
function checkMemberExistsInDB($pdo, $name, $father_name, $exclude_member_id = null) {
    $name_clean = trim($name);
    $father_name_clean = trim($father_name);
    $father_name_clean = $father_name_clean === '' ? null : $father_name_clean;

    $sql = "SELECT id FROM family_members WHERE LOWER(name) = :name";
    $params = [':name' => strtolower($name_clean)];

    if ($father_name_clean === null) {
        $sql .= " AND (father_name IS NULL OR father_name = '')";
    } else {
        $sql .= " AND LOWER(father_name) = :father_name";
        $params[':father_name'] = strtolower($father_name_clean);
    }

    if ($exclude_member_id !== null) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $exclude_member_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() !== false;
}


// --- SECTION 2: FORM PROCESSING (ADD, UPDATE, DELETE) ---
$formMessage = '';
$formMessageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ACTION: ADD NEW FAMILY ---
    if ($action === 'add_family') {
        $head_name = trim($_POST['member_name'][0] ?? '');
        $member_names = $_POST['member_name'] ?? [];
        $father_names = $_POST['father_name'] ?? [];
        $addresses = $_POST['address'] ?? []; // MODIFIED: Get addresses

        if (empty($head_name)) {
            $formMessage = "Head of Family name is required.";
            $formMessageType = 'error';
        } else {
            // --- SERVER-SIDE DUPLICATE CHECK LOGIC (IMPORTANT FALLBACK) ---
            $isDuplicate = false;
            
            // Phase 1: Check for duplicates within the form itself
            $seenMembers = [];
            for ($i = 0; $i < count($member_names); $i++) {
                $name_raw = trim($member_names[$i]);
                $father_raw = trim($father_names[$i] ?? '');
                if (empty($name_raw)) continue;
                $memberIdentifier = strtolower($name_raw) . '|' . strtolower($father_raw);
                if (isset($seenMembers[$memberIdentifier])) {
                    $isDuplicate = true;
                    $formMessage = "Duplicate entry in form: '" . htmlspecialchars($name_raw) . "' with father '" . htmlspecialchars($father_raw) . "'.";
                    $formMessageType = 'error';
                    break;
                }
                $seenMembers[$memberIdentifier] = true;
            }

            // Phase 2: Check against the entire database if no in-form duplicates were found
            if (!$isDuplicate) {
                for ($i = 0; $i < count($member_names); $i++) {
                    $name_raw = trim($member_names[$i]);
                    $father_raw = trim($father_names[$i] ?? '');
                    if (empty($name_raw)) continue;

                    if (checkMemberExistsInDB($pdo, $name_raw, $father_raw)) {
                        $isDuplicate = true;
                        $formMessage = "This family member already exists in the database: '" . htmlspecialchars($name_raw) . "' with father '" . htmlspecialchars($father_raw) . "'.";
                        $formMessageType = 'error';
                        break;
                    }
                }
            }
            // --- END SERVER-SIDE DUPLICATE CHECK ---

            if (!$isDuplicate) {
                $pdo->beginTransaction();
                try {
                    // 1. Create family record
                    $family_code = generateUniqueFamilyCode($pdo);
                    $stmtFamily = $pdo->prepare("INSERT INTO families (family_code) VALUES (?)");
                    $stmtFamily->execute([$family_code]);
                    $family_id = $pdo->lastInsertId();

                    // 2. Add members
                    // MODIFIED: Added 'address' to SQL
                    $sqlMember = "INSERT INTO family_members (family_id, name, father_name, address, relation_to_head, ritwik_id) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmtMember = $pdo->prepare($sqlMember);

                    $head_ritwik_id = !empty($_POST['ritwik_id'][0]) ? $_POST['ritwik_id'][0] : null;
                    $apply_ritwik_to_all = isset($_POST['apply_ritwik_to_all']);
                    $head_father_name = !empty(trim($_POST['father_name'][0])) ? trim($_POST['father_name'][0]) : null;
                    $head_address = !empty(trim($_POST['address'][0])) ? trim($_POST['address'][0]) : null; // MODIFIED: Get head's address

                    // Add Head of Family
                    // MODIFIED: Added $head_address to execute array
                    $stmtMember->execute([$family_id, $head_name, $head_father_name, $head_address, 'Head of Family', $head_ritwik_id]);
                    $head_of_family_id = $pdo->lastInsertId();

                    // Add other members
                    for ($i = 1; $i < count($_POST['member_name']); $i++) {
                        $member_name = trim($_POST['member_name'][$i]);
                        if (!empty($member_name)) {
                            $father_name = !empty(trim($_POST['father_name'][$i])) ? trim($_POST['father_name'][$i]) : null;
                            $address = !empty(trim($_POST['address'][$i])) ? trim($_POST['address'][$i]) : null; // MODIFIED: Get member's address
                            $relation = trim($_POST['relation_to_head'][$i]);
                            $ritwik_id = $apply_ritwik_to_all ? $head_ritwik_id : (!empty($_POST['ritwik_id'][$i]) ? $_POST['ritwik_id'][$i] : null);
                            // MODIFIED: Added $address to execute array
                            $stmtMember->execute([$family_id, $member_name, $father_name, $address, $relation, $ritwik_id]);
                        }
                    }

                    // 3. Update family with Head ID
                    $stmtHeadUpdate = $pdo->prepare("UPDATE families SET head_of_family_id = ? WHERE id = ?");
                    $stmtHeadUpdate->execute([$head_of_family_id, $family_id]);

                    $pdo->commit();
                    $formMessage = "Family ($family_code) created successfully!";
                    $formMessageType = 'success';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $formMessage = "Error creating family: " . $e->getMessage();
                    $formMessageType = 'error';
                }
            }
        }
    }
    
    // --- ACTION: UPDATE ENTIRE FAMILY (Server-side checks remain) ---
    if ($action === 'update_family') {
        $family_id = $_POST['family_id'];
        $head_id = $_POST['head_of_family_id'];
        $delete_member_ids = $_POST['delete_member_ids'] ?? [];

        $isDuplicate = false;
        $finalMemberDetails = [];
        if (isset($_POST['member_name'])) {
            foreach ($_POST['member_name'] as $member_id => $member_name) {
                if (in_array($member_id, $delete_member_ids)) continue;
                $finalMemberDetails[] = strtolower(trim($member_name)) . '|' . strtolower(trim($_POST['member_father_name'][$member_id] ?? ''));
            }
        }
        if (isset($_POST['new_member_name'])) {
            for ($i = 0; $i < count($_POST['new_member_name']); $i++) {
                if (!empty(trim($_POST['new_member_name'][$i]))) {
                     $finalMemberDetails[] = strtolower(trim($_POST['new_member_name'][$i])) . '|' . strtolower(trim($_POST['new_member_father_name'][$i] ?? ''));
                }
            }
        }
        if (count($finalMemberDetails) !== count(array_unique($finalMemberDetails))) {
            $isDuplicate = true;
            $formMessage = "Update failed. The form contains duplicate members (same name and father's name).";
            $formMessageType = 'error';
        }

        if (!$isDuplicate) {
            if (isset($_POST['member_name'])) {
                foreach ($_POST['member_name'] as $member_id => $member_name) {
                    if (in_array($member_id, $delete_member_ids)) continue;
                    if (checkMemberExistsInDB($pdo, $member_name, $_POST['member_father_name'][$member_id] ?? '', $member_id)) {
                        $isDuplicate = true;
                        $formMessage = "Update failed. A member named '" . htmlspecialchars($member_name) . "' already exists in the database.";
                        $formMessageType = 'error';
                        break;
                    }
                }
            }
            if (!$isDuplicate && isset($_POST['new_member_name'])) {
                for ($i = 0; $i < count($_POST['new_member_name']); $i++) {
                    $name = trim($_POST['new_member_name'][$i]);
                    if (empty($name)) continue;
                    if (checkMemberExistsInDB($pdo, $name, $_POST['new_member_father_name'][$i] ?? '')) {
                        $isDuplicate = true;
                        $formMessage = "Update failed. A new member named '" . htmlspecialchars($name) . "' already exists in the database.";
                        $formMessageType = 'error';
                        break;
                    }
                }
            }
        }

        if (!$isDuplicate) {
            $pdo->beginTransaction();
            try {
                if (!empty($delete_member_ids)) {
                    $in_clause = implode(',', array_fill(0, count($delete_member_ids), '?'));
                    $stmt = $pdo->prepare("DELETE FROM family_members WHERE id IN ($in_clause) AND family_id = ?");
                    $stmt->execute(array_merge($delete_member_ids, [$family_id]));
                }
                if (isset($_POST['member_name'])) {
                    foreach ($_POST['member_name'] as $member_id => $member_name) {
                        if (in_array($member_id, $delete_member_ids)) continue;
                        $father_name = !empty(trim($_POST['member_father_name'][$member_id])) ? trim($_POST['member_father_name'][$member_id]) : null;
                        // MODIFIED: Get member address from POST
                        $address = !empty(trim($_POST['member_address'][$member_id])) ? trim($_POST['member_address'][$member_id]) : null;
                        $relation = ($member_id == $head_id) ? 'Head of Family' : $_POST['member_relation'][$member_id];
                        $ritwik_id = !empty($_POST['member_ritwik_id'][$member_id]) ? $_POST['member_ritwik_id'][$member_id] : null;
                        // MODIFIED: Added address to SQL query
                        $stmt = $pdo->prepare("UPDATE family_members SET name = ?, father_name = ?, address = ?, relation_to_head = ?, ritwik_id = ? WHERE id = ?");
                        // MODIFIED: Added $address to execute array
                        $stmt->execute([trim($member_name), $father_name, $address, trim($relation), $ritwik_id, $member_id]);
                    }
                }
                if (isset($_POST['new_member_name'])) {
                    // MODIFIED: Added address to SQL query
                    $stmt = $pdo->prepare("INSERT INTO family_members (family_id, name, father_name, address, relation_to_head, ritwik_id) VALUES (?, ?, ?, ?, ?, ?)");
                    for ($i = 0; $i < count($_POST['new_member_name']); $i++) {
                        $name = trim($_POST['new_member_name'][$i]);
                        if (!empty($name)) {
                            $father_name = !empty(trim($_POST['new_member_father_name'][$i])) ? trim($_POST['new_member_father_name'][$i]) : null;
                            // MODIFIED: Get new member address
                            $address = !empty(trim($_POST['new_member_address'][$i])) ? trim($_POST['new_member_address'][$i]) : null;
                            $relation = trim($_POST['new_member_relation'][$i]);
                            $ritwik_id = !empty($_POST['new_member_ritwik_id'][$i]) ? $_POST['new_member_ritwik_id'][$i] : null;
                            // MODIFIED: Added $address to execute array
                            $stmt->execute([$family_id, $name, $father_name, $address, $relation, $ritwik_id]);
                        }
                    }
                }
                $stmt = $pdo->prepare("UPDATE families SET head_of_family_id = ? WHERE id = ?");
                $stmt->execute([$head_id, $family_id]);
                $pdo->commit();
                $formMessage = "Family details updated successfully!";
                $formMessageType = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $formMessage = "Error updating family: " . $e->getMessage();
                $formMessageType = 'error';
            }
        }
    }


    // --- ACTION: DELETE FAMILY ---
    if ($action === 'delete_family') {
        $family_id = $_POST['family_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM families WHERE id = ?");
            $stmt->execute([$family_id]);
            $formMessage = "Family and all its members have been deleted.";
            $formMessageType = 'success';
        } catch (PDOException $e) {
            $formMessage = "Error deleting family: " . $e->getMessage();
            $formMessageType = 'error';
        }
    }
}

// --- SECTION 3: DATA FETCHING ---
// MODIFIED: Added head.address to select statement
$sql_families = "SELECT 
                    f.id as family_id, f.family_code, f.head_of_family_id,
                    (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as member_count,
                    head.name as head_name,
                    head.address as head_address
                 FROM families f
                 LEFT JOIN family_members head ON f.head_of_family_id = head.id
                 ORDER BY f.id DESC";
$families = $pdo->query($sql_families)->fetchAll(PDO::FETCH_ASSOC);

// MODIFIED: Added fm.address to select statement
$sql_members = "SELECT fm.id, fm.family_id, fm.name, fm.father_name, fm.address, fm.relation_to_head, fm.ritwik_id, r.name as ritwik_name
                FROM family_members fm
                LEFT JOIN ritwiks r ON fm.ritwik_id = r.id";
$all_members = $pdo->query($sql_members)->fetchAll(PDO::FETCH_ASSOC);

$all_ritwiks = $pdo->query("SELECT id, name FROM ritwiks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Family Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .modal-backdrop { transition: opacity 0.3s ease-in-out; }
        .modal-content { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .validation-message { min-height: 16px; }
        input.border-red-500 { border-color: #ef4444; }
    </style>
</head>
<body class="font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="flex justify-between items-center mb-8" data-aos="fade-down">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-800">Family Management</h1>
            <button id="addFamilyBtn" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg shadow-md hover:bg-indigo-700 transition duration-300 flex items-center space-x-2">
                <i class="fas fa-users"></i><span>Add New Family</span>
            </button>
        </header>

        <?php if ($formMessage): ?>
        <div data-aos="fade-in" class="p-4 mb-4 text-sm rounded-lg <?= $formMessageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>" role="alert">
            <span class="font-medium"><?= htmlspecialchars($formMessage) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($families)): ?>
                <div class="col-span-full text-center text-gray-500 py-10" data-aos="zoom-in"><i class="fas fa-house-user fa-3x mb-4"></i><p class="text-xl">No families found. Click "Add New Family" to begin.</p></div>
            <?php else: foreach ($families as $index => $family): ?>
                <div class="bg-white rounded-xl shadow-lg flex flex-col" data-aos="fade-up" data-aos-delay="<?= $index * 50 ?>">
                    <div class="p-6 flex-grow">
                         <div class="flex justify-between items-start">
                            <div>
                                <p class="text-indigo-500 font-bold text-lg"><?= htmlspecialchars($family['family_code']) ?></p>
                                <h2 class="text-2xl font-bold text-gray-800 mt-1 flex items-center"><i class="fas fa-crown text-yellow-500 mr-2"></i><?= htmlspecialchars($family['head_name'] ?: 'N/A') ?></h2>
                                <p class="text-sm text-gray-500 mt-1 truncate"><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i><?= htmlspecialchars($family['head_address'] ?: 'Address not provided') ?></p>
                            </div>
                            <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-3 py-1 rounded-full flex-shrink-0"><i class="fas fa-users mr-1"></i> <?= $family['member_count'] ?></span>
                        </div>
                        <div class="mt-4 border-t pt-4">
                            <h3 class="text-sm font-semibold text-gray-500 mb-2">Members:</h3>
                            <ul class="space-y-2 text-gray-700">
                                <?php
                                $current_family_members = array_filter($all_members, fn($m) => $m['family_id'] == $family['family_id'] && $m['id'] != $family['head_of_family_id']);
                                if (empty($current_family_members)): ?>
                                    <li class="text-gray-400 text-sm">No other members.</li>
                                <?php else: foreach ($current_family_members as $member): ?>
                                    <li class="text-sm">
                                        <div class="font-semibold">
                                            <?= htmlspecialchars($member['name']) ?> 
                                            <span class="text-gray-500 font-normal">- <?= htmlspecialchars($member['relation_to_head'] ?: 'N/A') ?></span>
                                        </div>
                                        <div class="text-xs text-indigo-600 pl-1">Ritwik: <?= htmlspecialchars($member['ritwik_name'] ?? 'Not Assigned') ?></div>
                                    </li>
                                <?php endforeach; endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 flex justify-end items-center rounded-b-xl space-x-3">
                        <button class="edit-family-btn text-indigo-600 hover:text-indigo-800 font-semibold" data-family-id="<?= $family['family_id'] ?>"><i class="fas fa-edit mr-1"></i> Edit Family</button>
                        <button class="delete-family-btn text-gray-400 hover:text-red-500 transition duration-300" title="Delete Family" data-family-id="<?= $family['family_id'] ?>" data-family-code="<?= htmlspecialchars($family['family_code']) ?>"><i class="fas fa-trash-alt fa-lg"></i></button>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div id="addFamilyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-5xl p-8 modal-content transform scale-95 opacity-0"> <div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold text-gray-800">Add New Family</h2><button class="close-modal-btn text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form action="add_family.php" method="POST">
                <input type="hidden" name="action" value="add_family">
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-4">
                    <p class="text-sm text-gray-600 bg-gray-100 p-3 rounded-lg"><i class="fas fa-info-circle mr-2"></i>The first member is the 'Head of Family'. The system will check for duplicates as you type.</p>
                    <div id="members-container">
                        <div class="member-entry grid grid-cols-12 gap-x-2 items-start mb-3">
                            <i class="fas fa-crown text-yellow-500 fa-lg col-span-1 text-center pt-2"></i>
                            <div class="col-span-2"><input type="text" name="member_name[]" placeholder="Head Name" class="p-2 border rounded w-full member-name-input" required></div>
                            <div class="col-span-2"><input type="text" name="father_name[]" placeholder="Father's Name" class="p-2 border rounded w-full member-father-input"></div>
                            <div class="col-span-3"><input type="text" name="address[]" placeholder="Address (Optional)" class="p-2 border rounded w-full"></div>
                            <div class="col-span-2"><input type="text" value="Head of Family" name="relation_to_head[]" class="p-2 bg-gray-100 border rounded w-full" readonly></div>
                            <div class="col-span-2">
                                <select name="ritwik_id[]" class="p-2 border rounded w-full ritwik-select">
                                    <option value="">Select Ritwik</option>
                                    <?php foreach($all_ritwiks as $ritwik): ?><option value="<?= $ritwik['id'] ?>"><?= htmlspecialchars($ritwik['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-start-2 col-span-11 text-red-500 text-xs validation-message"></div>
                        </div>
                    </div>
                     <div class="pl-12 flex items-center">
                        <input type="checkbox" id="apply_ritwik_to_all" name="apply_ritwik_to_all" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="apply_ritwik_to_all" class="ml-2 block text-sm text-gray-900">Apply Head's Ritwik to all new members</label>
                    </div>
                    <button type="button" id="add-member-btn" class="w-full border-2 border-dashed border-gray-300 text-gray-500 hover:bg-gray-100 hover:border-gray-400 rounded-lg py-2 transition duration-300"><i class="fas fa-plus mr-2"></i>Add Another Member</button>
                </div>
                <div class="mt-8 text-right"><button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-indigo-700 transition"><i class="fas fa-save mr-2"></i>Create Family</button></div>
            </form>
        </div>
    </div>
    
    <div id="editFamilyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-6xl p-8 modal-content transform scale-95 opacity-0"> <div class="flex justify-between items-center mb-6"><h2 id="edit-modal-title" class="text-2xl font-bold text-gray-800">Edit Family</h2><button class="close-modal-btn text-gray-400 hover:text-gray-600 text-3xl">&times;</button></div>
            <form id="edit-family-form" action="add_family.php" method="POST">
                <div class="max-h-[60vh] overflow-y-auto pr-4 space-y-4"><div id="edit-form-container"></div></div>
                <div class="mt-8 text-right">
                    <button type="button" class="close-modal-btn bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-indigo-700 transition"><i class="fas fa-save mr-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-8 modal-content transform scale-95 opacity-0 text-center">
            <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Are you sure?</h2>
            <p class="text-gray-600 mb-6">Do you really want to delete family <strong id="delete-family-code"></strong>? This cannot be undone.</p>
            <form action="add_family.php" method="POST">
                <input type="hidden" name="action" value="delete_family"><input type="hidden" id="delete-family-id" name="family_id">
                <div class="flex justify-center space-x-4">
                    <button type="button" class="close-modal-btn bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="bg-red-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-red-700">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 600, once: true });

        // --- Data from PHP ---
        const allMembers = <?= json_encode($all_members) ?>;
        const allFamilies = <?= json_encode($families) ?>;
        const allRitwiks = <?= json_encode($all_ritwiks) ?>;

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
        const buildRitwikOptions = (selectedValue = '') => {
            let options = '<option value="">Select Ritwik</option>';
            allRitwiks.forEach(r => {
                options += `<option value="${r.id}" ${r.id == selectedValue ? 'selected' : ''}>${r.name}</option>`;
            });
            return options;
        }
        
        // --- Add Family Modal Logic ---
        const addFamilyModal = document.getElementById('addFamilyModal');
        const membersContainer = document.getElementById('members-container');
        document.getElementById('addFamilyBtn').addEventListener('click', () => openModal(addFamilyModal));
        
        document.getElementById('add-member-btn').addEventListener('click', () => {
            const newMemberEntry = document.createElement('div');
            // MODIFIED: gap-x-3 to gap-x-2
            newMemberEntry.className = 'member-entry grid grid-cols-12 gap-x-2 items-start mb-3';
            const headRitwikValue = document.querySelector('#addFamilyModal .ritwik-select').value;
            const applyToAll = document.getElementById('apply_ritwik_to_all').checked;
            const selectedRitwik = applyToAll ? headRitwikValue : '';

            // MODIFIED: Added address field and adjusted grid layout (col-span values)
            newMemberEntry.innerHTML = `
                <i class="fas fa-user text-gray-400 fa-lg col-span-1 text-center pt-2"></i>
                <div class="col-span-2"><input type="text" name="member_name[]" placeholder="Member Name" class="p-2 border rounded w-full member-name-input" required></div>
                <div class="col-span-2"><input type="text" name="father_name[]" placeholder="Father's Name" class="p-2 border rounded w-full member-father-input"></div>
                <div class="col-span-3"><input type="text" name="address[]" placeholder="Address (Optional)" class="p-2 border rounded w-full"></div>
                <div class="col-span-1"><input type="text" name="relation_to_head[]" placeholder="Relation" class="p-2 border rounded w-full"></div>
                <div class="col-span-2"><select name="ritwik_id[]" class="p-2 border rounded w-full ritwik-select">${buildRitwikOptions(selectedRitwik)}</select></div>
                <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 col-span-1 pt-2"><i class="fas fa-trash"></i></button>
                <div class="col-start-2 col-span-11 text-red-500 text-xs validation-message"></div>
            `;
            membersContainer.appendChild(newMemberEntry);
        });

        membersContainer.addEventListener('click', e => {
            if (e.target.closest('.remove-member-btn')) {
                e.target.closest('.member-entry').remove();
                validateFormState(); // Re-check form state after removing a row
            }
        });

        // --- NEW: REAL-TIME DUPLICATE CHECK LOGIC ---
        const addFamilyForm = document.querySelector('#addFamilyModal form');
        const addFamilySubmitBtn = addFamilyForm.querySelector('button[type="submit"]');

        function debounce(func, delay = 400) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => { func.apply(this, args); }, delay);
            };
        }

        function validateFormState() {
            const errorMessages = addFamilyForm.querySelectorAll('.validation-message:not(:empty)');
            const isDisabled = errorMessages.length > 0;
            addFamilySubmitBtn.disabled = isDisabled;
            if (isDisabled) {
                addFamilySubmitBtn.classList.add('bg-indigo-300', 'cursor-not-allowed');
                addFamilySubmitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
            } else {
                addFamilySubmitBtn.classList.remove('bg-indigo-300', 'cursor-not-allowed');
                addFamilySubmitBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            }
        }

        async function performDuplicateCheck(nameInput, fatherNameInput, errorContainer) {
            const name = nameInput.value.trim();
            const fatherName = fatherNameInput.value.trim();

            errorContainer.textContent = '';
            nameInput.classList.remove('border-red-500');
            fatherNameInput.classList.remove('border-red-500');

            if (name === '') {
                validateFormState();
                return;
            }

            try {
                const response = await fetch(`get_family_information.php?name=${encodeURIComponent(name)}&father_name=${encodeURIComponent(fatherName)}`);
                if (!response.ok) throw new Error('Network response error');
                const data = await response.json();

                if (data.exists) {
                    errorContainer.textContent = data.message;
                    nameInput.classList.add('border-red-500');
                    fatherNameInput.classList.add('border-red-500');
                }
            } catch (error) {
                console.error('Error checking for duplicates:', error);
            } finally {
                validateFormState();
            }
        }

        const debouncedCheck = debounce(performDuplicateCheck);

        membersContainer.addEventListener('input', (e) => {
            if (e.target.matches('.member-name-input, .member-father-input')) {
                const memberEntry = e.target.closest('.member-entry');
                if (memberEntry) {
                    const nameInput = memberEntry.querySelector('.member-name-input');
                    const fatherNameInput = memberEntry.querySelector('.member-father-input');
                    const errorContainer = memberEntry.querySelector('.validation-message');
                    debouncedCheck(nameInput, fatherNameInput, errorContainer);
                }
            }
        });
        // --- END OF REAL-TIME CHECK LOGIC ---


        // --- Edit Family Modal Logic (MODIFIED) ---
        const editFamilyModal = document.getElementById('editFamilyModal');
        const editFormContainer = document.getElementById('edit-form-container');
        document.querySelectorAll('.edit-family-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const familyId = btn.dataset.familyId;
                const family = allFamilies.find(f => f.family_id == familyId);
                const members = allMembers.filter(m => m.family_id == familyId);
                
                // MODIFIED: Updated grid headers for new layout
                let formContent = `<input type="hidden" name="action" value="update_family"><input type="hidden" name="family_id" value="${familyId}"><div id="existing-members-list" class="space-y-3"><label class="font-semibold text-gray-700">Existing Members</label><div class="grid grid-cols-12 gap-2 items-center text-xs text-gray-500 font-bold px-2"><span class="col-span-1 text-center">Head</span><span class="col-span-2">Member Name</span><span class="col-span-2">Father's Name</span><span class="col-span-3">Address</span><span class="col-span-1">Relation</span><span class="col-span-2">Ritwik</span><span class="col-span-1"></span></div>`;
                
                members.forEach(member => {
                    const isHead = member.id == family.head_of_family_id;
                    // MODIFIED: Added address input and adjusted col-span for all fields
                    formContent += `<div class="member-edit-row grid grid-cols-12 gap-2 items-center" data-member-id="${member.id}"><div class="col-span-1 text-center"><input type="radio" name="head_of_family_id" value="${member.id}" class="h-4 w-4" ${isHead ? 'checked' : ''}></div><input type="text" name="member_name[${member.id}]" value="${member.name}" class="col-span-2 p-2 border rounded"><input type="text" name="member_father_name[${member.id}]" value="${member.father_name || ''}" placeholder="Father's Name" class="col-span-2 p-2 border rounded"><input type="text" name="member_address[${member.id}]" value="${member.address || ''}" placeholder="Address" class="col-span-3 p-2 border rounded"><input type="text" name="member_relation[${member.id}]" value="${member.relation_to_head}" placeholder="Relation" class="col-span-1 p-2 border rounded member-relation-input" ${isHead ? 'readonly' : ''}><select name="member_ritwik_id[${member.id}]" class="col-span-2 p-2 border rounded">${buildRitwikOptions(member.ritwik_id)}</select><button type="button" class="remove-member-btn text-red-500 hover:text-red-700 col-span-1"><i class="fas fa-trash"></i></button></div>`;
                });
                
                formContent += `</div><div id="new-members-container" class="mt-6 pt-4 border-t space-y-3"></div><button type="button" id="add-new-member-in-modal-btn" class="w-full mt-2 border-2 border-dashed border-gray-300 text-gray-500 hover:bg-gray-100 rounded-lg py-2 transition"><i class="fas fa-plus mr-2"></i>Add New Member to Family</button>`;
                editFormContainer.innerHTML = formContent;
                document.getElementById('edit-modal-title').textContent = `Edit Family ${family.family_code}`;
                openModal(editFamilyModal);
            });
        });
        editFamilyModal.addEventListener('change', e => {
            if (e.target.name === 'head_of_family_id') {
                editFamilyModal.querySelectorAll('.member-relation-input').forEach(input => input.readOnly = false);
                const newHeadRow = e.target.closest('.member-edit-row');
                const relationInput = newHeadRow.querySelector('.member-relation-input');
                relationInput.readOnly = true;
                relationInput.value = 'Head of Family';
            }
        });
        editFamilyModal.addEventListener('click', e => {
            const removeBtn = e.target.closest('.remove-member-btn');
            if (removeBtn) {
                const row = removeBtn.closest('.member-edit-row');
                row.style.display = 'none';
                editFormContainer.insertAdjacentHTML('beforeend', `<input type="hidden" name="delete_member_ids[]" value="${row.dataset.memberId}">`);
            }
            if (e.target.closest('#add-new-member-in-modal-btn')) {
                // MODIFIED: Added address field and adjusted grid for new members in edit modal
                document.getElementById('new-members-container').insertAdjacentHTML('beforeend', `<div class="new-member-row grid grid-cols-12 gap-2 items-center"><i class="fas fa-user-plus text-gray-400 col-span-1 text-center"></i><input type="text" name="new_member_name[]" placeholder="New Member Name" class="col-span-2 p-2 border rounded" required><input type="text" name="new_member_father_name[]" placeholder="Father's Name" class="col-span-2 p-2 border rounded"><input type="text" name="new_member_address[]" placeholder="Address" class="col-span-3 p-2 border rounded"><input type="text" name="new_member_relation[]" placeholder="Relation" class="col-span-1 p-2 border rounded"><select name="new_member_ritwik_id[]" class="col-span-2 p-2 border rounded">${buildRitwikOptions()}</select><button type="button" class="remove-new-row-btn text-red-500 col-span-1"><i class="fas fa-times-circle"></i></button></div>`);
            }
            if (e.target.closest('.remove-new-row-btn')) {
                e.target.closest('.new-member-row').remove();
            }
        });

        // --- Delete Family Modal Logic ---
        const deleteModal = document.getElementById('deleteConfirmModal');
        document.querySelectorAll('.delete-family-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('delete-family-id').value = btn.dataset.familyId;
                document.getElementById('delete-family-code').textContent = btn.dataset.familyCode;
                openModal(deleteModal);
            });
        });
        
        // --- General Close Modal Events ---
        document.querySelectorAll('.modal-backdrop').forEach(m => m.addEventListener('click', (e) => { if (e.target === m) closeModal(m); }));
        document.querySelectorAll('.close-modal-btn').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal-backdrop'))));
    </script>
</body>
</html>