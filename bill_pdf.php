<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: mlogin.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "medivault_db");
if ($conn->connect_error) {
    die("Database connection failed");
}
$conn->set_charset("utf8mb4");

if (!isset($_GET['bill_id']) || $_GET['bill_id'] === '') {
    die("Bill ID missing");
}

$bill_id = trim((string)$_GET['bill_id']);

$bill_stmt = $conn->prepare("SELECT * FROM bill WHERE bill_id = ? LIMIT 1");
$bill_stmt->bind_param("s", $bill_id);
$bill_stmt->execute();
$bill_result = $bill_stmt->get_result();
if (!$bill_result || $bill_result->num_rows === 0) {
    die("Bill not found");
}

$bill = $bill_result->fetch_assoc();

$items_stmt = $conn->prepare("
    SELECT bd.quantity,
           bd.selling_price,
           p.medicine_name
    FROM bill_details bd
    JOIN product p ON bd.medicine_id = p.medicine_id
    WHERE bd.bill_id=?
");
$items_stmt->bind_param("s", $bill_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$rows = [];
$grand = 0.0;
while ($r = $items_result->fetch_assoc()) {
    $lineTotal = (float)$r['quantity'] * (float)$r['selling_price'];
    $r['line_total'] = $lineTotal;
    $rows[] = $r;
    $grand += $lineTotal;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$fpdfCandidates = [
    __DIR__ . DIRECTORY_SEPARATOR . 'fpdf.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'fpdf' . DIRECTORY_SEPARATOR . 'fpdf.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'fpdf.php',
];

$fpdfPath = null;
foreach ($fpdfCandidates as $candidate) {
    if (is_file($candidate)) {
        $fpdfPath = $candidate;
        break;
    }
}

if ($fpdfPath !== null) {
    require_once $fpdfPath;

    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont("Arial", "B", 16);
    $pdf->Cell(190, 10, "MEDIVAULT PHARMACY", 0, 1, "C");

    $pdf->SetFont("Arial", "", 12);
    $pdf->Cell(100, 8, "Bill No: " . $bill['bill_id'], 0, 1);
    $pdf->Cell(100, 8, "Date: " . $bill['bill_date'], 0, 1);
    $pdf->Cell(100, 8, "Customer: " . $bill['customer_name'], 0, 1);
    $pdf->Cell(100, 8, "Contact: " . $bill['customer_contact'], 0, 1);
    $pdf->Cell(100, 8, "Employee ID: " . $bill['emp_id'], 0, 1);
    $pdf->Cell(100, 8, "Payment: " . $bill['payment_method'], 0, 1);

    $pdf->Ln(5);

    $pdf->Cell(70, 10, "Medicine", 1);
    $pdf->Cell(25, 10, "Qty", 1);
    $pdf->Cell(45, 10, "Price", 1);
    $pdf->Cell(50, 10, "Total", 1);
    $pdf->Ln();

    foreach ($rows as $row) {
        $pdf->Cell(70, 10, (string)$row['medicine_name'], 1);
        $pdf->Cell(25, 10, (string)$row['quantity'], 1);
        $pdf->Cell(45, 10, number_format((float)$row['selling_price'], 2), 1);
        $pdf->Cell(50, 10, number_format((float)$row['line_total'], 2), 1);
        $pdf->Ln();
    }

    $pdf->Cell(140, 10, "Grand Total", 1);
    $pdf->Cell(50, 10, number_format($grand, 2), 1);

    $pdf->Output('I', 'invoice_' . $bill['bill_id'] . '.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo e((string)$bill['bill_id']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 24px; color: #0f172a; }
        .wrap { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #dbe7f5; border-radius: 12px; padding: 20px; }
        .top { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #dbe7f5; padding: 8px 10px; }
        th { background: #0f172a; color: #fff; }
        .actions { margin-bottom: 12px; display: flex; gap: 8px; }
        .btn { border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; padding: 8px 12px; border-radius: 8px; cursor: pointer; text-decoration: none; }
        @media print { .actions { display: none; } body { padding: 0; background: #fff; } .wrap { border: 0; } }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="actions">
            <button class="btn" onclick="window.print()">Save as PDF</button>
            <a class="btn" href="bill.php" target="_self">Back</a>
        </div>

        <div class="top">
            <div>
                <h2 style="margin:0 0 6px 0;">MEDIVAULT PHARMACY</h2>
                <div><strong>Bill No:</strong> <?php echo e((string)$bill['bill_id']); ?></div>
                <div><strong>Date:</strong> <?php echo e((string)$bill['bill_date']); ?></div>
            </div>
            <div>
                <div><strong>Customer:</strong> <?php echo e((string)($bill['customer_name'] ?? '')); ?></div>
                <div><strong>Contact:</strong> <?php echo e((string)($bill['customer_contact'] ?? '')); ?></div>
                <div><strong>Payment:</strong> <?php echo e((string)($bill['payment_method'] ?? '')); ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) { ?>
                    <tr><td colspan="4">No items found.</td></tr>
                <?php } else { ?>
                    <?php foreach ($rows as $row) { ?>
                        <tr>
                            <td><?php echo e((string)$row['medicine_name']); ?></td>
                            <td><?php echo (int)$row['quantity']; ?></td>
                            <td>Rs <?php echo number_format((float)$row['selling_price'], 2); ?></td>
                            <td>Rs <?php echo number_format((float)$row['line_total'], 2); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>

        <p style="text-align:right; margin-top:14px;"><strong>Grand Total: Rs <?php echo number_format($grand, 2); ?></strong></p>
    </div>
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
