<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: mlogin.php");
    exit();
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));

$salesTotal = 0.0;
$billCount = 0;
$avgBill = 0.0;
$purchaseTotal = 0.0;
$purchaseCount = 0;
$lowStockCount = 0;
$expiredStockCount = 0;
$outStockCount = 0;

$summaryStmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS sales_total,
           COUNT(*) AS bill_count,
           COALESCE(AVG(total_amount), 0) AS avg_bill
    FROM bill
    WHERE DATE(bill_date) BETWEEN ? AND ?
");
$summaryStmt->bind_param("ss", $monthStart, $monthEnd);
$summaryStmt->execute();
$summaryRow = $summaryStmt->get_result()->fetch_assoc();
if ($summaryRow) {
    $salesTotal = (float)$summaryRow['sales_total'];
    $billCount = (int)$summaryRow['bill_count'];
    $avgBill = (float)$summaryRow['avg_bill'];
}
$summaryStmt->close();

$purchaseStmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS purchase_total,
           COUNT(*) AS purchase_count
    FROM purchase
    WHERE DATE(purchase_date) BETWEEN ? AND ?
");
$purchaseStmt->bind_param("ss", $monthStart, $monthEnd);
$purchaseStmt->execute();
$purchaseRow = $purchaseStmt->get_result()->fetch_assoc();
if ($purchaseRow) {
    $purchaseTotal = (float)$purchaseRow['purchase_total'];
    $purchaseCount = (int)$purchaseRow['purchase_count'];
}
$purchaseStmt->close();

$stockStmt = $conn->prepare("
    SELECT
      SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_stock,
      SUM(CASE WHEN quantity > 0 AND quantity < 10 THEN 1 ELSE 0 END) AS low_stock,
      SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_stock
    FROM stock
");
$stockStmt->execute();
$stockRow = $stockStmt->get_result()->fetch_assoc();
if ($stockRow) {
    $outStockCount = (int)($stockRow['out_stock'] ?? 0);
    $lowStockCount = (int)($stockRow['low_stock'] ?? 0);
    $expiredStockCount = (int)($stockRow['expired_stock'] ?? 0);
}
$stockStmt->close();

$grossDelta = $salesTotal - $purchaseTotal;

$dailyLabels = [];
$salesSeriesByDate = [];
$purchaseSeriesByDate = [];

$cursor = new DateTimeImmutable($monthStart);
$end = new DateTimeImmutable($monthEnd);
while ($cursor <= $end) {
    $key = $cursor->format('Y-m-d');
    $dailyLabels[] = $cursor->format('d M');
    $salesSeriesByDate[$key] = 0.0;
    $purchaseSeriesByDate[$key] = 0.0;
    $cursor = $cursor->modify('+1 day');
}

$dailySalesStmt = $conn->prepare("
    SELECT DATE(bill_date) AS day, COALESCE(SUM(total_amount), 0) AS total
    FROM bill
    WHERE DATE(bill_date) BETWEEN ? AND ?
    GROUP BY DATE(bill_date)
");
$dailySalesStmt->bind_param("ss", $monthStart, $monthEnd);
$dailySalesStmt->execute();
$dailySalesRes = $dailySalesStmt->get_result();
while ($row = $dailySalesRes->fetch_assoc()) {
    $dateKey = (string)$row['day'];
    if (isset($salesSeriesByDate[$dateKey])) {
        $salesSeriesByDate[$dateKey] = (float)$row['total'];
    }
}
$dailySalesStmt->close();

$dailyPurchaseStmt = $conn->prepare("
    SELECT DATE(purchase_date) AS day, COALESCE(SUM(total_amount), 0) AS total
    FROM purchase
    WHERE DATE(purchase_date) BETWEEN ? AND ?
    GROUP BY DATE(purchase_date)
");
$dailyPurchaseStmt->bind_param("ss", $monthStart, $monthEnd);
$dailyPurchaseStmt->execute();
$dailyPurchaseRes = $dailyPurchaseStmt->get_result();
while ($row = $dailyPurchaseRes->fetch_assoc()) {
    $dateKey = (string)$row['day'];
    if (isset($purchaseSeriesByDate[$dateKey])) {
        $purchaseSeriesByDate[$dateKey] = (float)$row['total'];
    }
}
$dailyPurchaseStmt->close();

$salesSeries = array_values($salesSeriesByDate);
$purchaseSeries = array_values($purchaseSeriesByDate);

$paymentRows = [];
$paymentStmt = $conn->prepare("
    SELECT COALESCE(payment_method, 'Unknown') AS payment_method,
           COUNT(*) AS bill_count,
           COALESCE(SUM(total_amount), 0) AS total_amount
    FROM bill
    WHERE DATE(bill_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total_amount DESC
");
$paymentStmt->bind_param("ss", $monthStart, $monthEnd);
$paymentStmt->execute();
$paymentRes = $paymentStmt->get_result();
while ($row = $paymentRes->fetch_assoc()) {
    $paymentRows[] = $row;
}
$paymentStmt->close();

$paymentLabels = [];
$paymentValues = [];
foreach ($paymentRows as $row) {
    $paymentLabels[] = (string)$row['payment_method'];
    $paymentValues[] = (float)$row['total_amount'];
}
if (empty($paymentLabels)) {
    $paymentLabels = ['No Data'];
    $paymentValues = [0];
}

$topMedicineRows = [];
$topMedicineStmt = $conn->prepare("
    SELECT p.medicine_name,
           SUM(bd.quantity) AS qty_sold,
           COALESCE(SUM(bd.quantity * bd.selling_price), 0) AS revenue
    FROM bill_details bd
    JOIN bill b ON b.bill_id = bd.bill_id
    JOIN product p ON p.medicine_id = bd.medicine_id
    WHERE DATE(b.bill_date) BETWEEN ? AND ?
    GROUP BY p.medicine_id, p.medicine_name
    ORDER BY qty_sold DESC, revenue DESC
    LIMIT 8
");
$topMedicineStmt->bind_param("ss", $monthStart, $monthEnd);
$topMedicineStmt->execute();
$topMedicineRes = $topMedicineStmt->get_result();
while ($row = $topMedicineRes->fetch_assoc()) {
    $topMedicineRows[] = $row;
}
$topMedicineStmt->close();

$topMedicineLabels = [];
$topMedicineValues = [];
foreach ($topMedicineRows as $row) {
    $topMedicineLabels[] = (string)$row['medicine_name'];
    $topMedicineValues[] = (int)$row['qty_sold'];
}
if (empty($topMedicineLabels)) {
    $topMedicineLabels = ['No Data'];
    $topMedicineValues = [0];
}

$lowStockRows = [];
$lowStockSql = "
    SELECT s.medicine_id, p.medicine_name, s.batch_no, s.quantity, s.expiry_date
    FROM stock s
    JOIN product p ON p.medicine_id = s.medicine_id
    WHERE s.quantity < 10
       OR (s.expiry_date IS NOT NULL AND s.expiry_date != '0000-00-00' AND s.expiry_date < CURDATE())
    ORDER BY
        CASE
            WHEN s.expiry_date IS NOT NULL AND s.expiry_date != '0000-00-00' AND s.expiry_date < CURDATE() THEN 1
            WHEN s.quantity = 0 THEN 2
            WHEN s.quantity < 10 THEN 3
            ELSE 4
        END,
        s.quantity ASC,
        s.expiry_date ASC
    LIMIT 10
";
$lowStockResult = $conn->query($lowStockSql);
if ($lowStockResult) {
    while ($row = $lowStockResult->fetch_assoc()) {
        $lowStockRows[] = $row;
    }
}

$recentBillRows = [];
$recentBillStmt = $conn->prepare("
    SELECT b.bill_id, b.bill_date, b.customer_name, b.payment_method, b.total_amount
    FROM bill b
    WHERE DATE(b.bill_date) BETWEEN ? AND ?
    ORDER BY b.bill_date DESC
    LIMIT 8
");
$recentBillStmt->bind_param("ss", $monthStart, $monthEnd);
$recentBillStmt->execute();
$recentBillRes = $recentBillStmt->get_result();
while ($row = $recentBillRes->fetch_assoc()) {
    $recentBillRows[] = $row;
}
$recentBillStmt->close();
?>

<?php include("header.php"); ?>
<?php include("sidebar.php"); ?>

<div class="main reports-page">
    <div class="topbar">
        <div class="topbar-text">
            <h2>Reports</h2>
            <p><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="top-actions">
            <form method="GET" class="reports-filter-form">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </form>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="cards reports-cards">
        <div class="card">
            <h4>Total Sales</h4>
            <h2>&#8377; <?php echo number_format($salesTotal, 2); ?></h2>
        </div>

        <div class="card">
            <h4>Total Purchases</h4>
            <h2>&#8377; <?php echo number_format($purchaseTotal, 2); ?></h2>
        </div>

        <div class="card">
            <h4>Purchase Entries</h4>
            <h2><?php echo (int)$purchaseCount; ?></h2>
        </div>
    </div>

    <div class="reports-grid">
        <div class="box">
            <h3>Daily Sales vs Purchases</h3>
            <div class="chart-compact reports-chart">
                <canvas id="reportsTrendChart"></canvas>
            </div>
        </div>

        <div class="box">
            <h3>Payment Mix (Amount)</h3>
            <div class="chart-compact reports-chart">
                <canvas id="reportsPaymentChart"></canvas>
            </div>
        </div>
    </div>

    <div class="reports-grid">
        <div class="box">
            <h3>Top Medicines (Qty Sold)</h3>
            <div class="chart-compact reports-chart">
                <canvas id="reportsTopMedicineChart"></canvas>
            </div>
        </div>

        <div class="box">
            <h3>Inventory Alerts</h3>
            <div class="reports-alerts">
                <span class="report-chip chip-warn">Low Stock: <?php echo $lowStockCount; ?></span>
                <span class="report-chip chip-danger">Expired: <?php echo $expiredStockCount; ?></span>
                <span class="report-chip chip-muted">Out of Stock: <?php echo $outStockCount; ?></span>
            </div>
            <div class="table-wrap">
                <table class="leaderboard-table reports-table reports-alert-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Batch</th>
                            <th>Qty</th>
                            <th>Expiry</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lowStockRows)) { ?>
                            <tr>
                                <td colspan="5">No low stock or expired items.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($lowStockRows as $row) { ?>
                                <?php
                                $qty = (int)$row['quantity'];
                                $isValidExpiry = !empty($row['expiry_date']) && $row['expiry_date'] !== '0000-00-00';
                                $isExpired = $isValidExpiry && strtotime($row['expiry_date']) < strtotime(date('Y-m-d'));

                                $statusText = 'In Stock';
                                $statusClass = 'status-in-stock';
                                if ($isExpired) {
                                    $statusText = 'Expired';
                                    $statusClass = 'status-expired';
                                } elseif ($qty <= 0) {
                                    $statusText = 'Out of Stock';
                                    $statusClass = 'status-out-of-stock';
                                } elseif ($qty < 10) {
                                    $statusText = 'Low Stock';
                                    $statusClass = 'status-low-stock';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['medicine_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['batch_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $qty; ?></td>
                                    <td>
                                        <?php
                                        if ($isValidExpiry) {
                                            echo date('d M Y', strtotime($row['expiry_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><span class="stock-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="box recent-box">
        <h3>Recent Bills (<?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>)</h3>
        <div class="table-wrap">
            <table class="leaderboard-table transactions-table reports-table reports-bills-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentBillRows)) { ?>
                        <tr>
                            <td colspan="5">No bills found for this month.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($recentBillRows as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row['bill_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['bill_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>&#8377; <?php echo number_format((float)$row['total_amount'], 2); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
(function () {
    if (typeof Chart === 'undefined') return;

    var labels = <?php echo json_encode($dailyLabels, JSON_UNESCAPED_UNICODE); ?>;
    var salesSeries = <?php echo json_encode($salesSeries, JSON_NUMERIC_CHECK); ?>;
    var purchaseSeries = <?php echo json_encode($purchaseSeries, JSON_NUMERIC_CHECK); ?>;
    var paymentLabels = <?php echo json_encode($paymentLabels, JSON_UNESCAPED_UNICODE); ?>;
    var paymentValues = <?php echo json_encode($paymentValues, JSON_NUMERIC_CHECK); ?>;
    var medicineLabels = <?php echo json_encode($topMedicineLabels, JSON_UNESCAPED_UNICODE); ?>;
    var medicineValues = <?php echo json_encode($topMedicineValues, JSON_NUMERIC_CHECK); ?>;

    var moneyFormat = function (value) {
        return 'Rs ' + Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    var trendCanvas = document.getElementById('reportsTrendChart');
    if (trendCanvas) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sales',
                        data: salesSeries,
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: 'Purchases',
                        data: purchaseSeries,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + moneyFormat(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }

    var paymentCanvas = document.getElementById('reportsPaymentChart');
    if (paymentCanvas) {
        new Chart(paymentCanvas, {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    data: paymentValues,
                    backgroundColor: ['#1d4ed8', '#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var label = context.label || '';
                                return label + ': ' + moneyFormat(context.parsed);
                            }
                        }
                    }
                }
            }
        });
    }

    var medicineCanvas = document.getElementById('reportsTopMedicineChart');
    if (medicineCanvas) {
        new Chart(medicineCanvas, {
            type: 'bar',
            data: {
                labels: medicineLabels,
                datasets: [{
                    label: 'Qty Sold',
                    data: medicineValues,
                    backgroundColor: '#2563eb',
                    borderRadius: 8,
                    maxBarThickness: 22
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    y: {
                        ticks: {
                            autoSkip: false
                        }
                    }
                }
            }
        });
    }
})();
</script>
