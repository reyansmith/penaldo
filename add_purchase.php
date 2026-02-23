<?php
session_start();
require_once("config.php");

if(!isset($_SESSION['role']) || $_SESSION['role'] !== "admin"){
    header("Location: ../mlogin.php");
    exit();
}

$message = "";

<<<<<<< HEAD
=======
// Handle form submit
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $purchase_id = $_POST['purchase_id'];   
    $vendor_id = $_POST['vendor_id'];       
    $purchase_date = $_POST['purchase_date'];

<<<<<<< HEAD
=======
    // Step 1: insert purchase with total_amount = 0 first
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
    $stmt = $conn->prepare("INSERT INTO purchase (purchase_id, vendor_id, purchase_date, total_amount) VALUES (?,?,?,0)");
    $stmt->bind_param("sss", $purchase_id, $vendor_id, $purchase_date);
    $stmt->execute();
    $stmt->close();

    $total_amount = 0;

<<<<<<< HEAD
    foreach($_POST['medicine_id'] as $i => $med_id){

=======
    // Step 2: Insert purchase_details (arrays)
    foreach($_POST['medicine_id'] as $i => $med_id){
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
        $admin_id = $_POST['admin_id'][$i];
        $quantity = $_POST['quantity'][$i];
        $cost_price = $_POST['cost_price'][$i];

        $subtotal = $quantity * $cost_price;
        $total_amount += $subtotal;

        $detail_id = $_POST['purchase_detail_id'][$i];

        $stmt2 = $conn->prepare("INSERT INTO purchase_details (purchase_detail_id, purchase_id, medicine_id, admin_id, quantity, cost_price) VALUES (?,?,?,?,?,?)");
        $stmt2->bind_param("sssiii", $detail_id, $purchase_id, $med_id, $admin_id, $quantity, $cost_price);
        $stmt2->execute();
        $stmt2->close();
<<<<<<< HEAD

        /* 🔥 STOCK UPDATE (INCREASE INVENTORY) */

        $check_stock = $conn->query("SELECT stock_id FROM stock WHERE medicine_id='$med_id' LIMIT 1");

        if($check_stock->num_rows > 0){
            $conn->query("UPDATE stock 
                          SET quantity = quantity + $quantity 
                          WHERE medicine_id='$med_id'");
        } else {
            $new_stock_id = uniqid("STK");
            $conn->query("INSERT INTO stock 
                (stock_id, medicine_id, batch_no, expiry_date, quantity, selling_price)
                VALUES 
                ('$new_stock_id', '$med_id', 'NEW', CURDATE(), $quantity, 0)");
        }
    }

=======
    }

    // Step 3: Update purchase total_amount
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
    $stmt3 = $conn->prepare("UPDATE purchase SET total_amount=? WHERE purchase_id=?");
    $stmt3->bind_param("ds", $total_amount, $purchase_id);
    $stmt3->execute();
    $stmt3->close();

    $message = "Purchase added successfully! Total Amount: ₹" . $total_amount;
}

<<<<<<< HEAD
=======
// Fetch existing purchase_details with medicine & admin names
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
$sql = "
SELECT pd.purchase_detail_id, pd.purchase_id, pd.medicine_id, pr.medicine_name, pd.admin_id, a.username AS admin_name, pd.quantity, pd.cost_price
FROM purchase_details pd
LEFT JOIN product pr ON pr.medicine_id = pd.medicine_id
LEFT JOIN admin a ON a.admin_id = pd.admin_id
ORDER BY pd.purchase_detail_id DESC
";
$result = $conn->query($sql);
$details = [];
if($result){
    while($row = $result->fetch_assoc()){
        $details[] = $row;
    }
}

<<<<<<< HEAD
=======
// Fetch vendors, admins, and products for dropdowns
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
$vendor_result = $conn->query("SELECT vendor_id, name FROM vendor");
$vendors = $vendor_result->fetch_all(MYSQLI_ASSOC);

$admin_result = $conn->query("SELECT admin_id, username FROM admin");
$admins = $admin_result->fetch_all(MYSQLI_ASSOC);

$product_result = $conn->query("SELECT medicine_id, medicine_name FROM product");
$products = $product_result->fetch_all(MYSQLI_ASSOC);

include("header.php");
include("sidebar.php");
?>

<<<<<<< HEAD
<!-- REST OF YOUR HTML CODE REMAINS EXACTLY SAME -->
=======
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
<div class="main">
    <div class="topbar">
        <h2>Add Purchase & Details</h2>
        <div class="top-actions">
            <a href="purchases.php" class="btn">Back to Purchases</a>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="box">
        <?php 
        if($message) {
            echo "<p style='color:green;'>" . $message . "</p>";
        }
        ?>

        <form method="POST">
            <h3>Purchase Info</h3>
            <input type="text" name="purchase_id" placeholder="Purchase ID" required>
            <select name="vendor_id" required>
                <option value="">Select Vendor</option>
                <?php
                foreach($vendors as $v){
                    echo "<option value='" . $v['vendor_id'] . "'>" . $v['vendor_id'] . " - " . $v['name'] . "</option>";
                }
                ?>
            </select>
            <input type="date" name="purchase_date" required>

            <h3>Purchase Details</h3>
            <table border="1" style="width:100%; margin-bottom:20px;">
                <tr>
                    <th>Detail ID</th>
                    <th>Medicine</th>
                    <th>Admin</th>
                    <th>Quantity</th>
                    <th>Cost Price</th>
                </tr>
                <!-- Default single row -->
                <tr>
                    <td><input type="text" name="purchase_detail_id[]" required></td>
                    <td>
                        <select name="medicine_id[]" required>
                            <option value="">Select Medicine</option>
                            <?php
                            foreach($products as $p){
                                echo "<option value='" . $p['medicine_id'] . "'>" . $p['medicine_id'] . " - " . $p['medicine_name'] . "</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <select name="admin_id[]" required>
                            <option value="">Select Admin</option>
                            <?php
                            foreach($admins as $a){
                                echo "<option value='" . $a['admin_id'] . "'>" . $a['username'] . "</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td><input type="number" name="quantity[]" value="1" min="1" required></td>
                    <td><input type="number" step="0.01" name="cost_price[]" value="0" required></td>
                </tr>
            </table>

            <button type="submit">Add Purchase + Details</button>
        </form>

        <h3>Existing Purchase Details</h3>
        <table border="1" style="width:100%; border-collapse:collapse;">
            <tr>
                <th>Detail ID</th>
                <th>Purchase ID</th>
                <th>Medicine ID</th>
                <th>Medicine Name</th>
                <th>Admin Name</th>
                <th>Quantity</th>
                <th>Cost Price</th>
                <th>Subtotal</th>
            </tr>
            <?php
            if(empty($details)){
                echo "<tr><td colspan='8' style='text-align:center;'>No details found</td></tr>";
            } else {
                foreach($details as $d){
                    echo "<tr>";
                    echo "<td>" . $d['purchase_detail_id'] . "</td>";
                    echo "<td>" . $d['purchase_id'] . "</td>";
                    echo "<td>" . $d['medicine_id'] . "</td>";
                    echo "<td>" . ($d['medicine_name'] ?? '-') . "</td>";
                    echo "<td>" . ($d['admin_name'] ?? '-') . "</td>";
                    echo "<td>" . (int)$d['quantity'] . "</td>";
                    echo "<td>₹ " . number_format($d['cost_price'],2) . "</td>";
                    echo "<td>₹ " . number_format($d['quantity'] * $d['cost_price'],2) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </table>
    </div>
</div>

<?php include("footer.php"); ?>