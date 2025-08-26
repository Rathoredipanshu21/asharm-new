<?php
// new_dashboard.php
// Ensure you have a database connection file at this path
include '../config/db.php'; 

// --- (1) FETCH STATISTICS CARDS DATA ---

// Total Earnings from donations
$total_earnings = 0;
$earnings_result = $conn->query("SELECT SUM(total_amount) as total FROM donation_invoices");
if ($earnings_result) {
    $total_earnings = $earnings_result->fetch_assoc()['total'] ?? 0;
}

// Total Families
$total_families = 0;
$families_result = $conn->query("SELECT COUNT(id) as total FROM families");
if ($families_result) {
    $total_families = $families_result->fetch_assoc()['total'] ?? 0;
}

// Total Donations (Invoices)
$total_donations = 0;
$donations_result = $conn->query("SELECT COUNT(id) as total FROM donation_invoices");
if ($donations_result) {
    $total_donations = $donations_result->fetch_assoc()['total'] ?? 0;
}

// Total Ritwiks
$total_ritwiks = 0;
$ritwiks_result = $conn->query("SELECT COUNT(id) as total FROM ritwiks");
if ($ritwiks_result) {
    $total_ritwiks = $ritwiks_result->fetch_assoc()['total'] ?? 0;
}


// --- (2) FETCH CHART DATA ---

// A. Monthly Donations (Bar Chart)
$monthly_donations_data = array_fill(0, 12, 0);
$current_year = date('Y');
$monthly_sql = "SELECT MONTH(created_at) as month, SUM(total_amount) as total FROM donation_invoices WHERE YEAR(created_at) = ? GROUP BY MONTH(created_at)";
$stmt_monthly = $conn->prepare($monthly_sql);
if ($stmt_monthly) {
    $stmt_monthly->bind_param("i", $current_year);
    $stmt_monthly->execute();
    $monthly_result = $stmt_monthly->get_result();
    if ($monthly_result) {
        while($row = $monthly_result->fetch_assoc()){
            // Adjust month index (result is 1-12, array is 0-11)
            $monthly_donations_data[$row['month'] - 1] = round($row['total']);
        }
    }
    $stmt_monthly->close();
}

// B. Donation Types Distribution (Doughnut Chart)
$donation_type_labels = [];
$donation_type_data = [];
$donation_type_sql = "
    SELECT dt.name, SUM(di.amount) as total 
    FROM donation_items di 
    JOIN donation_types dt ON di.donation_type_id = dt.id 
    GROUP BY dt.name
";
$donation_type_result = $conn->query($donation_type_sql);
if ($donation_type_result) {
    while($row = $donation_type_result->fetch_assoc()){
        $donation_type_labels[] = $row['name'];
        $donation_type_data[] = $row['total'];
    }
}

// C. Top Donating Families (Horizontal Bar Chart)
$top_families_labels = [];
$top_families_data = [];
$top_families_sql = "
    SELECT f.family_code, SUM(di.total_amount) as total 
    FROM donation_invoices di 
    JOIN families f ON di.family_id = f.id 
    GROUP BY f.family_code 
    ORDER BY total DESC 
    LIMIT 5
";
$top_families_result = $conn->query($top_families_sql);
if ($top_families_result) {
    while($row = $top_families_result->fetch_assoc()){
        $top_families_labels[] = 'Family #' . $row['family_code'];
        $top_families_data[] = round($row['total']);
    }
}


// --- (3) FETCH RECENT DONATIONS ---
$recent_donations_sql = "
    SELECT di.total_amount, di.created_at, f.family_code 
    FROM donation_invoices di 
    JOIN families f ON di.family_id = f.id
    ORDER BY di.id DESC 
    LIMIT 5
";
$recent_donations_result = $conn->query($recent_donations_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ritwik & Donation Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f7f8fc; }
        .stat-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .chart-card { background: white; border-radius: 1.5rem; padding: 2rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07); }
    </style>
</head>
<body class="p-4 md:p-6">

    <div class="container mx-auto">
        <div data-aos="fade-down" class="mb-8">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-800">Donation & Family Dashboard</h1>
            <p class="mt-2 text-lg text-gray-500">Welcome back! Here's your community overview.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card border-l-8 border-green-500" data-aos="fade-up" data-aos-delay="100">
                <i class="fas fa-wallet fa-3x absolute -right-2 -bottom-2 text-green-500 opacity-70"></i>
                <p class="text-sm font-medium text-gray-500">Total Earnings</p>
                <p class="text-4xl font-bold text-gray-900 mt-1">₹<?php echo number_format($total_earnings, 0); ?></p>
            </div>
            <div class="stat-card border-l-8 border-blue-500" data-aos="fade-up" data-aos-delay="200">
                <i class="fas fa-users fa-3x absolute -right-2 -bottom-2 text-blue-500 opacity-70"></i>
                <p class="text-sm font-medium text-gray-500">Total Families</p>
                <p class="text-4xl font-bold text-gray-900 mt-1"><?php echo $total_families; ?></p>
            </div>
            <div class="stat-card border-l-8 border-purple-500" data-aos="fade-up" data-aos-delay="300">
                <i class="fas fa-receipt fa-3x absolute -right-2 -bottom-2 text-purple-500 opacity-70"></i>
                <p class="text-sm font-medium text-gray-500">Total Donations</p>
                <p class="text-4xl font-bold text-gray-900 mt-1"><?php echo $total_donations; ?></p>
            </div>
            <div class="stat-card border-l-8 border-teal-500" data-aos="fade-up" data-aos-delay="400">
                <i class="fas fa-user-tie fa-3x absolute -right-2 -bottom-2 text-teal-500 opacity-70"></i>
                <p class="text-sm font-medium text-gray-500">Total Ritwiks</p>
                <p class="text-4xl font-bold text-gray-900 mt-1"><?php echo $total_ritwiks; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">
            <div class="lg:col-span-3 chart-card" data-aos="fade-right">
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Monthly Donations (<?php echo $current_year; ?>)</h3>
                <div class="h-96"><canvas id="monthlyDonationsChart"></canvas></div>
            </div>
            <div class="lg:col-span-2 space-y-6">
                <div class="chart-card h-60" data-aos="fade-left">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Donation Types</h3>
                    <div class="h-40"><canvas id="donationTypesChart"></canvas></div>
                </div>
                 <div class="chart-card" data-aos="fade-left" data-aos-delay="100">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Top Donating Families</h3>
                    <div class="h-64"><canvas id="topFamiliesChart"></canvas></div>
                </div>
            </div>
        </div>
        
        <div class="chart-card" data-aos="fade-up">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Donations</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($recent_donations_result && $recent_donations_result->num_rows > 0): ?>
                            <?php while($row = $recent_donations_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-4 px-2"><div class="p-3 rounded-full bg-green-100"><i class="fas fa-donate text-green-600"></i></div></td>
                                    <td class="py-4 px-2"><p class="font-semibold text-gray-800">Family #<?php echo htmlspecialchars($row['family_code']); ?></p></td>
                                    <td class="py-4 px-2"><p class="text-sm text-gray-500">made a donation.</p></td>
                                    <td class="py-4 px-2 text-right"><p class="font-bold text-gray-800">₹<?php echo number_format($row['total_amount'], 0); ?></p></td>
                                    <td class="py-4 px-2 text-right"><p class="text-sm text-gray-500"><?php echo date("d M, Y", strtotime($row['created_at'])); ?></p></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-gray-500 py-8">No recent donations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });

        const formatCurrency = (value) => `₹${parseInt(value).toLocaleString('en-IN')}`;
        const formatK = (value) => `₹${(value / 1000).toFixed(1)}k`;

        // 1. Monthly Donations Bar Chart
        const monthlyCtx = document.getElementById('monthlyDonationsChart').getContext('2d');
        const revenueGradient = monthlyCtx.createLinearGradient(0, 0, 0, 400);
        revenueGradient.addColorStop(0, 'rgba(79, 70, 229, 0.8)');
        revenueGradient.addColorStop(1, 'rgba(129, 140, 248, 0.5)');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Donations',
                    data: <?php echo json_encode($monthly_donations_data); ?>,
                    backgroundColor: revenueGradient,
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 2, borderRadius: 8, borderSkipped: false,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: formatK } }, x: { grid: { display: false } } },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` Donations: ${formatCurrency(c.parsed.y)}` } } }
            }
        });

        // 2. Donation Types Doughnut Chart
        const donationTypesCtx = document.getElementById('donationTypesChart').getContext('2d');
        new Chart(donationTypesCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($donation_type_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($donation_type_data); ?>,
                    backgroundColor: ['#10B981', '#3B82F6', '#A855F7', '#F97316', '#F59E0B'],
                    borderColor: '#ffffff',
                    borderWidth: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ` ${c.label}: ${formatCurrency(c.parsed)}` } } }
            }
        });

        // 3. Top Donating Families Horizontal Bar Chart
        const familiesCtx = document.getElementById('topFamiliesChart').getContext('2d');
        const familiesGradient = familiesCtx.createLinearGradient(0, 0, 500, 0);
        familiesGradient.addColorStop(0, 'rgba(239, 68, 68, 0.5)');
        familiesGradient.addColorStop(1, 'rgba(248, 113, 113, 0.8)');
        new Chart(familiesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($top_families_labels); ?>,
                datasets: [{
                    label: 'Total Donated',
                    data: <?php echo json_encode($top_families_data); ?>,
                    backgroundColor: familiesGradient,
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: { y: { grid: { display: false } }, x: { ticks: { callback: formatK } } },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` Donated: ${formatCurrency(c.parsed.x)}` } } }
            }
        });
    </script>
</body>
</html>