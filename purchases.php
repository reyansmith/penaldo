<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: ../mlogin.php");
    exit();
}

<<<<<<< HEAD
=======
// Fetch purchases with dynamic total from purchase_details
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
$sql = "
SELECT p.purchase_id, p.vendor_id, p.purchase_date, 
       IFNULL(SUM(pd.quantity * pd.cost_price),0) AS total_amount
FROM purchase p
LEFT JOIN purchase_details pd ON pd.purchase_id = p.purchase_id
GROUP BY p.purchase_id, p.vendor_id, p.purchase_date
ORDER BY p.purchase_date DESC, p.purchase_id DESC
";

$result = $conn->query($sql);
$purchases = [];
if($result){
    while($row = $result->fetch_assoc()){
        $purchases[] = $row;
    }
}

<<<<<<< HEAD
=======
// Fetch vendors for name display
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
$vendor_result = $conn->query("SELECT vendor_id, name FROM vendor");
$vendors = [];
if($vendor_result){
    while($v = $vendor_result->fetch_assoc()){
        $vendors[$v['vendor_id']] = $v['name'];
    }
}

include("header.php");
include("sidebar.php");
?>

<div class="main">
    <div class="topbar">
        <h2>Purchases</h2>
        <div class="top-actions">
            <a href="add_purchase.php" class="btn">Add Purchase</a>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="box">
        <table class="leaderboard-table" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="background:#eee;">
                    <th>Purchase ID</th>
                    <th>Vendor</th>
                    <th>Purchase Date</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(empty($purchases)){
                    echo "<tr><td colspan='4' style='text-align:center;'>No purchases found.</td></tr>";
                } else {
                    foreach($purchases as $p){
                        echo "<tr>";
                        echo "<td>" . $p['purchase_id'] . "</td>";
                        echo "<td>" . ($vendors[$p['vendor_id']] ?? '-') . "</td>";
                        echo "<td>" . $p['purchase_date'] . "</td>";
                        echo "<td>₹ " . number_format($p['total_amount'],2) . "</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("footer.php"); ?>