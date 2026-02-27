<?php
session_start();
require_once ("config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: mlogin.php");
    exit();
}

$bills = [];
$sql = "
    SELECT b.bill_id,
           b.bill_date,
           b.customer_name,
           b.total_amount,
           e.username AS employee_name
    FROM bill b
    LEFT JOIN employee e ON e.emp_id = b.emp_id
    ORDER BY b.bill_date DESC
    LIMIT 50
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
    }
}
?>

<?php include("header.php"); ?>
<?php include("sidebar.php"); ?>

<div class="main">
    <div class="topbar">
        <div class="topbar-text">
            <h2>Billing</h2>
        </div>
        <div class="top-actions">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="box">
        <div class="table-wrap">
            <table class="leaderboard-table transactions-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Employee</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)) { ?>
                        <tr>
                            <td colspan="5">No bills found.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($bills as $bill) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$bill['bill_id'], ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo date("d M Y, h:i A", strtotime($bill['bill_date'])); ?></td>
                                <td><?php echo htmlspecialchars($bill['customer_name'] ?? '-', ENT_QUOTES, "UTF-8"); ?></td>
                                <td><?php echo htmlspecialchars($bill['employee_name'] ?? '-', ENT_QUOTES, "UTF-8"); ?></td>
                                <td>&#8377; <?php echo number_format((float)$bill['total_amount'], 2); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>
