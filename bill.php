<?php
include("header.php");
include("sidebar.php");

$conn = new mysqli("localhost","root","","medivault_db");
if($conn->connect_error) die("Connection failed");

$bill_generated = false;
$error = "";

// Get last bill number properly (numeric order)
$result = $conn->query("
    SELECT MAX(CAST(SUBSTRING(bill_id,5) AS UNSIGNED)) AS max_id 
    FROM bill
");

$row = $result->fetch_assoc();
$number = ($row['max_id'] !== NULL) ? $row['max_id'] + 1 : 1;

$bill_id = "BILL" . str_pad($number, 3, "0", STR_PAD_LEFT);

if($_SERVER["REQUEST_METHOD"]=="POST"){

    $customer_name = $_POST['customer_name'] ?? '';
    $customer_contact = $_POST['customer_contact'] ?? '';
    $emp_id = $_POST['emp_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $medicine_id = $_POST['medicine_id'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $give_discount = $_POST['give_discount'] ?? 'no';
    $discount = $_POST['discount'] ?? 0;

    // Get stock details
    $stock_query = $conn->query("
        SELECT quantity, selling_price, expiry_date 
        FROM stock 
        WHERE medicine_id='$medicine_id'
    ");

    $stock = $stock_query->fetch_assoc();

    $available_quantity = $stock['quantity'] ?? 0;
    $selling_price = $stock['selling_price'] ?? 0;
    $expiry_date = $stock['expiry_date'] ?? '';
    $current_date = date("Y-m-d");

    // Check Expiry
    if($expiry_date < $current_date){
        $error = "Medicine is expired! Billing not allowed.";
    }
    // Check Low Stock
    elseif($quantity > $available_quantity){
        $error = "Insufficient stock! Only $available_quantity available.";
    }
    else{

        // Simple calculation
        $subtotal = $quantity * $selling_price;
        $tax = $subtotal * 0.05;
        $total_before_discount = $subtotal + $tax;

        if($give_discount == "yes"){
            $total_amount = $total_before_discount - $discount;
        }else{
            $discount = 0;
            $total_amount = $total_before_discount;
        }

        if($total_amount < 0){
            $total_amount = 0;
        }

        $bill_date = date("d-m-Y");

        // Insert Bill
        $conn->query("INSERT INTO bill 
        (bill_id, emp_id, bill_date, total_amount, payment_method, customer_name, customer_contact)
        VALUES 
        ('$bill_id','$emp_id',NOW(),'$total_amount','$payment_method','$customer_name','$customer_contact')");

        // Insert Bill Detail
        $bill_detail_id = "BD" . time();

        $conn->query("INSERT INTO bill_details 
        (bill_detail_id,bill_id,medicine_id,quantity,selling_price)
        VALUES
        ('$bill_detail_id','$bill_id','$medicine_id','$quantity','$selling_price')");

        // Reduce stock
        $conn->query("UPDATE stock 
                      SET quantity = quantity - $quantity 
                      WHERE medicine_id='$medicine_id'");

        $bill_generated = true;

        // Fetch items
        $items = $conn->query("
            SELECT p.medicine_name, bd.quantity, bd.selling_price
            FROM bill_details bd
            JOIN product p ON bd.medicine_id = p.medicine_id
            WHERE bd.bill_id='$bill_id'
        ");
    }
}

// Load medicines
$medicines = $conn->query("
SELECT s.medicine_id, p.medicine_name 
FROM stock s
JOIN product p ON s.medicine_id = p.medicine_id
");
?>

<style>
.bill-container{
    width:700px;
    margin:auto;
    background:white;
    padding:25px;
}
.flex{
    display:flex;
    justify-content:space-between;
}
table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}
table,th,td{
    border:1px solid #aaa;
}
th{
    background:#1a5dab;
    color:white;
    padding:8px;
}
td{
    padding:8px;
    text-align:center;
}
.total-box{
    margin-top:20px;
    width:40%;
    float:right;
}
.total-box table{
    border:none;
}
.total-box td{
    border:none;
    text-align:right;
}
.final{
    background:#333;
    color:white;
    padding:8px;
    font-weight:bold;
}
input,select{
    width:100%;
    padding:7px;
    margin:5px 0;
}
button{
    padding:10px;
    background:green;
    color:white;
    border:none;
    width:100%;
}
.error{
    color:red;
    font-weight:bold;
}
</style>

<div class="bill-container">

<?php if(!$bill_generated){ ?>

<h3>BILL INVOICE</h3>

<?php if($error != ""){ ?>
<p class="error"><?php echo $error; ?></p>
<?php } ?>

<form method="POST">

Customer Name:
<input type="text" name="customer_name" required>

Customer Contact:
<input type="text" name="customer_contact" required>

Employee ID:
<input type="text" name="emp_id" required>

Payment Method:
<select name="payment_method">
<option>Cash</option>
<option>UPI</option>
<option>Card</option>
</select>

Medicine:
<select name="medicine_id">
<?php while($row=$medicines->fetch_assoc()){ ?>
<option value="<?php echo $row['medicine_id']; ?>">
<?php echo $row['medicine_name']; ?>
</option>
<?php } ?>
</select>

Quantity:
<input type="number" name="quantity" required>

Give Discount?
<select name="give_discount">
<option value="no">No</option>
<option value="yes">Yes</option>
</select>

Discount (₹):
<input type="number" name="discount" value="0">

<br>
<button type="submit">Generate Bill</button>

</form>

<?php } ?>

<?php if($bill_generated){ ?>

<h2 style="text-align:center;color:#1a5dab;">MEDIVAULT PHARMACY</h2>
<hr>

<div class="flex">
<div>
<b>Bill No:</b> <?php echo $bill_id; ?><br>
<b>Date:</b> <?php echo $bill_date; ?>
</div>

<div>
<b>Employee:</b> <?php echo $emp_id; ?><br>
<b>Payment:</b> <?php echo $payment_method; ?>
</div>
</div>

<hr>

<p>
<b>Customer:</b> <?php echo $customer_name; ?><br>
<b>Contact:</b> <?php echo $customer_contact; ?>
</p>

<table>
<tr>
<th>No.</th>
<th>Medicine</th>
<th>Qty</th>
<th>Price</th>
<th>Subtotal</th>
</tr>

<?php
$no=1;
while($row=$items->fetch_assoc()){
$sub=$row['quantity']*$row['selling_price'];
?>

<tr>
<td><?php echo $no++; ?></td>
<td><?php echo $row['medicine_name']; ?></td>
<td><?php echo $row['quantity']; ?></td>
<td><?php echo $row['selling_price']; ?></td>
<td><?php echo $sub; ?></td>
</tr>

<?php } ?>
</table>

<div class="total-box">
<table>
<tr>
<td>Subtotal:</td>
<td>₹ <?php echo $subtotal; ?></td>
</tr>
<tr>
<td>Tax (5%):</td>
<td>₹ <?php echo $tax; ?></td>
</tr>
<tr>
<td>Discount:</td>
<td>₹ <?php echo $discount; ?></td>
</tr>
<tr class="final">
<td>Total:</td>
<td>₹ <?php echo $total_amount; ?></td>
</tr>
</table>
</div>

<div style="clear:both;"></div>

<br><br>
<a href="bill.php"><button>New Bill</button></a>

<?php } ?>

</div>

<?php include("footer.php"); ?>