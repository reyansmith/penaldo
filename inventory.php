<?php
session_start();
<<<<<<< HEAD
require_once "config.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: mlogin.php");
    exit();
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $action = $_POST['action'];

    // ADD PRODUCT
    if ($action == "add_product") {
        $id   = $_POST['medicine_id'];   // VARCHAR
        $name = $_POST['medicine_name'];
        $desc = $_POST['description'];

        $check = $conn->query("SELECT * FROM product WHERE medicine_id='$id'");
        if ($check->num_rows > 0) {
            $msg = "Medicine ID already exists";
        } else {
            $conn->query("INSERT INTO product (medicine_id,medicine_name,description)
                          VALUES ('$id','$name','$desc')");
            $msg = "Medicine added successfully";
        }
    }

    // DELETE PRODUCT
    if ($action == "delete_product") {
        $id = $_POST['medicine_id'];  // VARCHAR
        $conn->query("DELETE FROM product WHERE medicine_id='$id'");
        $msg = "Medicine deleted successfully";
    }

    // ADD STOCK
    if ($action == "add_stock") {

        $stock_id = $_POST['stock_id'];   // VARCHAR
        $med      = $_POST['medicine_id'];
        $batch    = $_POST['batch_no'];
        $exp      = $_POST['expiry_date'];
        $qty      = $_POST['quantity'];
        $price    = $_POST['selling_price'];

        $conn->query("INSERT INTO stock 
            (stock_id, medicine_id, batch_no, expiry_date, quantity, selling_price)
            VALUES 
            ('$stock_id','$med','$batch','$exp','$qty','$price')");

        $msg = "Stock added successfully";
    }

    // DELETE STOCK
    if ($action == "delete_stock") {
        $id = $_POST['stock_id'];   // VARCHAR
        $conn->query("DELETE FROM stock WHERE stock_id='$id'");
        $msg = "Stock deleted successfully";
    }
}

$section = isset($_GET['section']) ? $_GET['section'] : "products";


$search = "";
if(isset($_GET['search']) && $_GET['search'] != ""){
    $search = $conn->real_escape_string($_GET['search']);
    $products = $conn->query("
        SELECT p.*, IFNULL(SUM(s.quantity),0) AS total_stock
        FROM product p
        LEFT JOIN stock s ON s.medicine_id=p.medicine_id
        WHERE p.medicine_name LIKE '%$search%' 
           OR p.medicine_id LIKE '%$search%'
        GROUP BY p.medicine_id
    ");
} else {
    $products = $conn->query("
        SELECT p.*, IFNULL(SUM(s.quantity),0) AS total_stock
        FROM product p
        LEFT JOIN stock s ON s.medicine_id=p.medicine_id
        GROUP BY p.medicine_id
    ");
}

$stocks = $conn->query("
    SELECT s.*, p.medicine_name
    FROM stock s
    JOIN product p ON p.medicine_id=s.medicine_id
");
=======
require_once ("config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: ../mlogin.php");
    exit();
}

$inventory = [];
$sql = "
SELECT p.medicine_name,
       s.batch_no,
       s.expiry_date,
       s.quantity,
       pd.cost_price,
       s.selling_price
FROM stock s
INNER JOIN product p ON p.medicine_id = s.medicine_id
LEFT JOIN purchase_details pd 
       ON pd.medicine_id = s.medicine_id
          AND pd.purchase_detail_id = (
              SELECT purchase_detail_id 
              FROM purchase_details 
              WHERE medicine_id = s.medicine_id 
              ORDER BY purchase_detail_id DESC 
              LIMIT 1
          )
ORDER BY p.medicine_name ASC, s.expiry_date ASC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
}
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
?>

<?php include("header.php"); ?>
<?php include("sidebar.php"); ?>

<<<<<<< HEAD
<style>

/* NAVIGATION */
.inv-nav {
    display: inline-flex;
    gap: 6px;
    margin-bottom: 20px;
    background: #f1f5f9;
    padding: 6px;
    border-radius: 12px;
}

.inv-nav a {
    padding: 8px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    color: #334155;
    transition: 0.2s;
}

.inv-nav a.on {
    background: #2563eb;
    color: #fff;
}

/* ALERT */
.inv-alert {
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    background: #dcfce7;
    color: #166534;
    font-size: 14px;
}

/* GRID */
.inv-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
}

@media (max-width: 992px) {
    .inv-grid {
        grid-template-columns: 1fr;
    }
}

/* BOX */
.box {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* FORM */
.box form {
    display: flex;
    flex-direction: column;
}

.form-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-group label {
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
    color: #374151;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    width: 100%;
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

/* BUTTONS */
.inv-btn {
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    margin-top: 5px;
}

.inv-btn.primary {
    background: #2563eb;
    color: #fff;
    width: 100px;
    margin-right: 0px;
}

.inv-btn.danger {
    background: #dc2626;
    color: #fff;
}

/* TABLE */
.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}

.leaderboard-table th,
.leaderboard-table td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

.leaderboard-table th {
    background: #f9fafb;
}

.leaderboard-table tr:hover {
    background: #f3f4f6;
}

</style>

<div class="main">

    <div class="topbar">
        <h2>Inventory</h2>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if($msg!=""){ ?>
        <div class="inv-alert"><?php echo $msg; ?></div>
    <?php } ?>

    <div class="inv-nav">
        <a href="?section=products" class="<?php echo ($section=='products')?'on':''; ?>">Products</a>
        <a href="?section=stock" class="<?php echo ($section=='stock')?'on':''; ?>">Stock</a>
    </div>

    <!-- PRODUCTS -->
    <?php if($section=="products"){ ?>
    <div class="inv-grid">

        <div class="box">
            <h3>Add Medicine</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_product">

                <div class="form-group">
                    <label>Medicine ID</label>
                    <input type="text" name="medicine_id" required>
                </div>

                <div class="form-group">
                    <label>Medicine Name</label>
                    <input type="text" name="medicine_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>

                <button class="inv-btn primary">Save</button>
            </form>
        </div>

        <div class="box">
            <h3>Medicine List</h3>
            <form method="GET" style="margin-bottom:10px;">
    <input type="hidden" name="section" value="products">
    <input type="text" name="search" 
           placeholder="Search Medicine..."
           value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>"
           style="padding:8px; border:1px solid #d1d5db; border-radius:8px; width:200px;">
    <button class="inv-btn primary" type="submit">Search</button>
</form>
            <table class="leaderboard-table">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Total Stock</th>
                    <th>Action</th>
                </tr>
                <?php while($row=$products->fetch_assoc()){ ?>
                <tr>
                    <td><?php echo $row['medicine_id']; ?></td>
                    <td><?php echo $row['medicine_name']; ?></td>
                    <td><?php echo $row['description']; ?></td>
                    <td><?php echo $row['total_stock']; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="medicine_id" value="<?php echo $row['medicine_id']; ?>">
                            <button class="inv-btn danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </table>
        </div>

    </div>
    <?php } ?>

    <!-- STOCK -->
    <?php if($section=="stock"){ ?>
<div class="inv-grid">

    <div class="box">
        <h3>Add Stock</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_stock">

            <div class="form-group">
                <label>Stock ID</label>
                <input type="text" name="stock_id" required>
            </div>

            <div class="form-group">
                <label>Medicine</label>
                <select name="medicine_id" required>
                    <?php
                    $list = $conn->query("SELECT * FROM product");
                    while($p=$list->fetch_assoc()){
                        echo "<option value='".$p['medicine_id']."'>".$p['medicine_name']."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Batch No</label>
                <input type="text" name="batch_no" required>
            </div>

            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" required>
            </div>

            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" required>
            </div>

            <div class="form-group">
                <label>Selling Price</label>
                <input type="number" step="0.01" name="selling_price" required>
            </div>

            <button class="inv-btn primary">Add Stock</button>
        </form>
    </div>

    <div class="box">
        <h3>Stock List</h3>
        <table class="leaderboard-table">
            <tr>
                <th>Stock ID</th>
                <th>Medicine</th>
                <th>Batch</th>
                <th>Expiry</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Action</th>
            </tr>
            <?php while($s=$stocks->fetch_assoc()){ ?>
            <tr>
                <td><?php echo $s['stock_id']; ?></td>
                <td><?php echo $s['medicine_name']; ?></td>
                <td><?php echo $s['batch_no']; ?></td>
                <td><?php echo $s['expiry_date']; ?></td>
                <td><?php echo $s['quantity']; ?></td>
                <td><?php echo $s['selling_price']; ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="delete_stock">
                        <input type="hidden" name="stock_id" value="<?php echo $s['stock_id']; ?>">
                        <button class="inv-btn danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>
<?php } ?>
=======
<div class="main">
    <div class="topbar">
        <div class="topbar-text">
            <h2>Inventory</h2>
        </div>
        <div class="top-actions">
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="box">
        <div class="table-wrap">
            <table class="leaderboard-table transactions-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)) { ?>
                        <tr>
                            <td colspan="6">No inventory data found.</td>
                        </tr>
                    <?php } else { ?>
                        <?php
                        foreach ($inventory as $item) {
                            echo "<tr>";
                            echo "<td>" . $item['medicine_name'] . "</td>";
                            echo "<td>" . $item['batch_no'] . "</td>";
                            echo "<td>" . $item['expiry_date'] . "</td>";
                            echo "<td>" . (int)$item['quantity'] . "</td>";
                            echo "<td>&#8377; " . number_format((float)$item['cost_price'], 2) . "</td>";
                            echo "<td>&#8377; " . number_format((float)$item['selling_price'], 2) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
