<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: mlogin.php");
    exit();
}

include("header.php");
include("sidebar.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "root", "", "medivault_db");
$conn->set_charset("utf8mb4");

$bill_generated = false;
$error = "";
$items = null;
$subtotal = 0.0;
$total_amount = 0.0;
$bill_date = date("d-m-Y");
$customer_name = "";
$customer_contact = "";
$emp_id = "";
$payment_method = "Cash";
$medicine_id = "";
$medicine_query = "";
$quantity = "";

function generateNextBillId(mysqli $conn): string {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(bill_id,5) AS UNSIGNED)) AS max_id FROM bill");
    $row = $result->fetch_assoc();
    $number = ($row && $row['max_id'] !== null) ? ((int)$row['max_id'] + 1) : 1;
    return "BILL" . str_pad((string)$number, 3, "0", STR_PAD_LEFT);
}

$bill_id = generateNextBillId($conn);

$employees = [];
$empResult = $conn->query("SELECT emp_id, username FROM employee ORDER BY username ASC");
while ($empRow = $empResult->fetch_assoc()) {
    $employees[] = $empRow;
}

$medicines = [];
$medResult = $conn->query("
SELECT p.medicine_id, p.medicine_name
FROM product p
ORDER BY p.medicine_name ASC
");
while ($medRow = $medResult->fetch_assoc()) {
    $medicines[] = $medRow;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_name = trim((string)($_POST['customer_name'] ?? ''));
    $customer_contact = trim((string)($_POST['customer_contact'] ?? ''));
    $emp_id = trim((string)($_POST['emp_id'] ?? ''));
    $payment_method = trim((string)($_POST['payment_method'] ?? ''));
    $medicine_id = trim((string)($_POST['medicine_id'] ?? ''));
    $medicine_query = trim((string)($_POST['medicine_query'] ?? ''));
    $quantity = trim((string)($_POST['quantity'] ?? ''));

    $allowedPayments = ["Cash", "UPI", "Card"];

    if ($customer_name === '' || strlen($customer_name) > 100) {
        $error = "Enter a valid customer name (1-100 characters).";
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $customer_contact)) {
        $error = "Enter a valid customer contact number.";
    } elseif ($emp_id === '') {
        $error = "Select a valid employee.";
    } elseif (!in_array($payment_method, $allowedPayments, true)) {
        $error = "Invalid payment method selected.";
    } elseif ($medicine_id === '') {
        $error = "Select a valid medicine.";
    } elseif (!ctype_digit($quantity) || (int)$quantity <= 0 || (int)$quantity > 10000) {
        $error = "Quantity must be a number between 1 and 10000.";
    } else {
        try {
            $conn->begin_transaction();

            $empStmt = $conn->prepare("SELECT 1 FROM employee WHERE emp_id = ? LIMIT 1");
            $empStmt->bind_param("s", $emp_id);
            $empStmt->execute();
            $empExists = $empStmt->get_result()->fetch_row();
            if (!$empExists) {
                throw new RuntimeException("Selected employee does not exist.");
            }

            $stockStmt = $conn->prepare("
                SELECT stock_id, quantity, selling_price
                FROM stock
                WHERE medicine_id = ?
                AND quantity > 0
                AND expiry_date IS NOT NULL
                AND expiry_date != '0000-00-00'
                AND expiry_date >= CURDATE()
                ORDER BY expiry_date ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stockStmt->bind_param("s", $medicine_id);
            $stockStmt->execute();
            $stockResult = $stockStmt->get_result();

            if ($stockResult->num_rows === 0) {
                throw new RuntimeException("No valid (non-expired) stock available.");
            }

            $stock = $stockResult->fetch_assoc();
            $available_quantity = (int)$stock['quantity'];
            $selling_price = (float)$stock['selling_price'];
            $stock_id = (string)$stock['stock_id'];
            $qty = (int)$quantity;

            if ($qty > $available_quantity) {
                throw new RuntimeException("Insufficient stock. Only $available_quantity available in earliest batch.");
            }

            $subtotal = $qty * $selling_price;
            $total_amount = $subtotal;
            $bill_id = generateNextBillId($conn);
            $bill_detail_id = "BD" . date("YmdHis") . random_int(100, 999);

            $billStmt = $conn->prepare("
                INSERT INTO bill (bill_id, emp_id, bill_date, total_amount, payment_method, customer_name, customer_contact)
                VALUES (?, ?, NOW(), ?, ?, ?, ?)
            ");
            $billStmt->bind_param("ssdsss", $bill_id, $emp_id, $total_amount, $payment_method, $customer_name, $customer_contact);
            $billStmt->execute();

            $detailStmt = $conn->prepare("
                INSERT INTO bill_details (bill_detail_id, bill_id, medicine_id, quantity, selling_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $detailStmt->bind_param("sssii", $bill_detail_id, $bill_id, $medicine_id, $qty, $selling_price);
            $detailStmt->execute();

            $updateStmt = $conn->prepare("UPDATE stock SET quantity = quantity - ? WHERE stock_id = ?");
            $updateStmt->bind_param("is", $qty, $stock_id);
            $updateStmt->execute();

            $conn->commit();

            $bill_generated = true;
            $bill_date = date("d-m-Y");

            $itemsStmt = $conn->prepare("
                SELECT p.medicine_name, bd.quantity, bd.selling_price
                FROM bill_details bd
                JOIN product p ON bd.medicine_id = p.medicine_id
                WHERE bd.bill_id = ?
            ");
            $itemsStmt->bind_param("s", $bill_id);
            $itemsStmt->execute();
            $items = $itemsStmt->get_result();
        } catch (Throwable $e) {
            if ($conn->errno || $conn->error) {
                $conn->rollback();
            }
            $error = $e->getMessage();
            $bill_generated = false;
            $bill_id = generateNextBillId($conn);
        }
    }
}
?>
<style>
.bill-container.billing-page{
    width:min(920px, 100%);
    margin: 8px auto 24px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    padding: 26px;
    border-radius: 16px;
    border: 1px solid #dbe7f5;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.billing-page .bill-title{
    margin:0 0 16px;
    font-size: 28px;
    letter-spacing: 0.02em;
    color:#0f172a;
}

.billing-page .bill-form-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.billing-page .field{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.billing-page .hint{
    margin-top: 2px;
    font-size: 12px;
    color: #64748b;
}

.billing-page .medicine-search{
    position: relative;
}

.billing-page .medicine-results{
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 25;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
    max-height: 220px;
    overflow-y: auto;
}

.billing-page .medicine-option{
    width: 100%;
    border: 0;
    background: #fff;
    text-align: left;
    padding: 10px 12px;
    font-size: 14px;
    cursor: pointer;
}

.billing-page .medicine-option:hover,
.billing-page .medicine-option.active{
    background: #eff6ff;
}

.billing-page .medicine-empty{
    margin: 0;
    padding: 10px 12px;
    color: #64748b;
    font-size: 13px;
}

.billing-page label{
    font-size:13px;
    font-weight:600;
    color:#334155;
    letter-spacing: 0.02em;
}

.billing-page input,
.billing-page select{
    width:100%;
    min-height:42px;
    padding:10px 12px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    font-size:14px;
    background: #fff;
    transition: border-color .2s ease, box-shadow .2s ease;
}

.billing-page input:focus,
.billing-page select:focus{
    outline:none;
    border-color:#3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

.billing-page .bill-submit{
    margin-top: 16px;
    width:100%;
    padding:12px 16px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(120deg, #0f172a, #1d4ed8);
    color:#fff;
    font-weight:700;
    font-size:15px;
    cursor:pointer;
}

.billing-page .bill-submit:hover{
    filter: brightness(1.04);
}

.billing-page .error{
    color:#b91c1c;
    background:#fee2e2;
    border:1px solid #fecaca;
    border-radius:8px;
    padding:10px 12px;
    margin-bottom:14px;
    font-size:14px;
    font-weight:600;
}

.billing-page .invoice-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    margin-bottom: 14px;
}

.billing-page .invoice-subhead{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin: 14px 0 10px;
}

.billing-page .invoice-card{
    border: 1px solid #dbe7f5;
    border-radius: 12px;
    background: #ffffff;
    padding: 12px 14px;
}

.billing-page .invoice-card p{
    margin: 2px 0;
}

.billing-page .pay-badge{
    display:inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    background: #e0e7ff;
    color: #1e3a8a;
}

.billing-page .invoice-head h2{
    margin:0;
    color:#0f172a;
}

.billing-page .invoice-head p{
    margin:4px 0 0;
    color:#475569;
}

.billing-page .invoice-table{
    width:100%;
    border-collapse:collapse;
    margin-top:18px;
    font-size:14px;
    background:#fff;
    border-radius:10px;
    overflow:hidden;
}

.billing-page .invoice-table th{
    background:#0f172a;
    color:#fff;
    padding:10px;
    text-align:center;
    font-weight:600;
}

.billing-page .invoice-table td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid #e2e8f0;
}

.billing-page .invoice-table tfoot td{
    font-weight: 700;
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
}

.billing-page .invoice-table tfoot td:first-child{
    text-align: right;
}

.billing-page .invoice-table tbody tr:hover{
    background:#f8fafc;
}

.billing-page .total-box{
    margin-top:18px;
    margin-left:auto;
    width:min(310px, 100%);
    background:#ffffff;
    border: 1px solid #dbe7f5;
    padding:12px;
    border-radius:10px;
}

.billing-page .total-box table{
    width:100%;
    border-collapse:collapse;
}

.billing-page .total-box td{
    padding:8px 0;
    border:none;
}

.billing-page .total-box .final td{
    border-top:1px dashed #cbd5e1;
    font-weight:700;
    color:#0f172a;
}

.billing-page .bill-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:20px;
}

.billing-page .btn{
    padding:10px 14px;
    border-radius:10px;
    font-weight:600;
}

.billing-page .btn-primary{
    background:#1d4ed8;
    border-color:#1d4ed8;
}

.billing-page .btn-primary:hover{
    background:#1e40af;
}

@media (max-width: 768px){
    .bill-container.billing-page{
        padding:18px;
    }

    .billing-page .bill-form-grid{
        grid-template-columns: 1fr;
    }

    .billing-page .invoice-subhead{
        grid-template-columns: 1fr;
    }
}

@media print{
    .sidebar,
    .topbar,
    .bill-actions {
        display: none !important;
    }

    .main {
        padding: 0 !important;
    }

    .bill-container.billing-page{
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
    }
}
</style>

<div class="main">
    <div class="topbar">
        <div class="topbar-text">
            <h2>Billing</h2>
        </div>
        <div class="top-actions">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

<div class="bill-container billing-page">

<?php if(!$bill_generated){ ?>

<h3 class="bill-title">Bill Invoice</h3>

<?php if($error !== ""){ ?>
<p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
<?php } ?>

<form method="POST" novalidate>
<div class="bill-form-grid">
    <div class="field">
        <label for="customer_name">Customer Name</label>
        <input type="text" id="customer_name" name="customer_name" maxlength="100" value="<?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="field">
        <label for="customer_contact">Customer Contact</label>
        <input type="text" id="customer_contact" name="customer_contact" maxlength="20" value="<?php echo htmlspecialchars($customer_contact, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="field">
        <label for="emp_id">Employee</label>
        <select id="emp_id" name="emp_id" required>
            <option value="">Select Employee</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?php echo htmlspecialchars($emp['emp_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($emp_id === (string)$emp['emp_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['emp_id'] . ' - ' . ($emp['username'] ?? 'Employee'), ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="payment_method">Payment Method</label>
        <select id="payment_method" name="payment_method">
            <option value="Cash" <?php echo $payment_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="UPI" <?php echo $payment_method === 'UPI' ? 'selected' : ''; ?>>UPI</option>
            <option value="Card" <?php echo $payment_method === 'Card' ? 'selected' : ''; ?>>Card</option>
        </select>
    </div>

    <div class="field">
        <label for="medicine_query">Medicine</label>
        <div class="medicine-search" id="medicine_search_wrap">
            <input
                type="text"
                id="medicine_query"
                name="medicine_query"
                placeholder="Type medicine name or code"
                value="<?php echo htmlspecialchars($medicine_query, ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="off"
                required
            >
            <div id="medicine_results" class="medicine-results" hidden></div>
        </div>
        <input type="hidden" id="medicine_id" name="medicine_id" value="<?php echo htmlspecialchars($medicine_id, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="field">
        <label for="quantity">Quantity</label>
        <input type="number" id="quantity" name="quantity" min="1" max="10000" value="<?php echo htmlspecialchars((string)$quantity, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
</div>

<button type="submit" class="bill-submit">Generate Bill</button>

</form>

<?php } ?>

<?php if($bill_generated){ ?>

<div class="invoice-head">
    <div>
        <h2>MEDIVAULT PHARMACY</h2>
        <p>Billing Invoice</p>
    </div>
    <div>
        <p><strong>Bill No:</strong> <?php echo htmlspecialchars($bill_id, ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Date:</strong> <?php echo htmlspecialchars($bill_date, ENT_QUOTES, "UTF-8"); ?></p>
    </div>
</div>
<hr>

<div class="invoice-subhead">
    <div class="invoice-card">
        <p><b>Employee:</b> <?php echo htmlspecialchars($emp_id, ENT_QUOTES, "UTF-8"); ?></p>
        <p><b>Payment:</b> <span class="pay-badge"><?php echo htmlspecialchars($payment_method, ENT_QUOTES, "UTF-8"); ?></span></p>
    </div>
    <div class="invoice-card">
        <p><b>Customer:</b> <?php echo htmlspecialchars($customer_name, ENT_QUOTES, "UTF-8"); ?></p>
        <p><b>Contact:</b> <?php echo htmlspecialchars($customer_contact, ENT_QUOTES, "UTF-8"); ?></p>
    </div>
</div>

<hr>

<table class="invoice-table">
<thead>
<tr>
    <th>No.</th>
    <th>Medicine</th>
    <th>Qty</th>
    <th>Price</th>
    <th>Total</th>
</tr>
</thead>
<tbody>

<?php
$no = 1;
if ($items) {
    while($row = $items->fetch_assoc()){
        $sub = (float)$row['quantity'] * (float)$row['selling_price'];
?>

<tr>
<td><?php echo $no++; ?></td>
<td><?php echo htmlspecialchars($row['medicine_name'], ENT_QUOTES, "UTF-8"); ?></td>
<td><?php echo (int)$row['quantity']; ?></td>
<td><?php echo number_format((float)$row['selling_price'], 2); ?></td>
<td><?php echo number_format((float)$sub, 2); ?></td>
</tr>

<?php
    }
}
?>
</tbody>
<tfoot>
<tr>
    <td colspan="4">Grand Total</td>
    <td>&#8377; <?php echo number_format((float)$total_amount, 2); ?></td>
</tr>
</tfoot>
</table>

<div class="bill-actions">
    <button type="button" class="btn btn-primary" onclick="window.print()">Save / Print Invoice</button>
    <a class="btn btn-secondary" href="bill.php" target="_self">New Bill</a>
</div>

<?php } ?>

</div>
</div>

<?php include("footer.php"); ?>

<script>
(function () {
    var medicineInput = document.getElementById("medicine_query");
    var medicineHidden = document.getElementById("medicine_id");
    var form = medicineInput ? medicineInput.closest("form") : null;
    if (!medicineInput || !medicineHidden || !form) return;

    var options = <?php
        $medicineOptions = [];
        foreach ($medicines as $m) {
            $medicineOptions[] = [
                'id' => (string)$m['medicine_id'],
                'name' => (string)$m['medicine_name'],
            ];
        }
        echo json_encode($medicineOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    ?>;

    function normalize(value) {
        return String(value || "").trim().toLowerCase();
    }

    var resultsBox = document.getElementById("medicine_results");
    if (!resultsBox) return;
    var activeIndex = -1;
    var currentList = [];

    function renderResults(list) {
        currentList = list;
        activeIndex = -1;
        if (!list.length) {
            resultsBox.innerHTML = '<p class="medicine-empty">No matching medicine found.</p>';
            resultsBox.hidden = false;
            return;
        }

        var html = "";
        for (var i = 0; i < list.length; i++) {
            var label = String(list[i].name || "") + " | " + String(list[i].id || "");
            html += '<button type="button" class="medicine-option" data-index="' + i + '">' + label + '</button>';
        }
        resultsBox.innerHTML = html;
        resultsBox.hidden = false;
    }

    function filterMedicines(query) {
        var q = normalize(query);
        if (!q) return options.slice(0, 12);
        var out = [];
        for (var i = 0; i < options.length; i++) {
            var name = normalize(options[i].name);
            var id = normalize(options[i].id);
            if (name.indexOf(q) > -1 || id.indexOf(q) > -1) {
                out.push(options[i]);
            }
            if (out.length >= 20) break;
        }
        return out;
    }

    function selectMedicine(item) {
        medicineInput.value = item.name + " | " + item.id;
        medicineHidden.value = item.id;
        resultsBox.hidden = true;
    }

    function tryResolveExact(rawValue) {
        var q = normalize(rawValue);
        if (!q) return "";
        for (var i = 0; i < options.length; i++) {
            if (normalize(options[i].name) === q || normalize(options[i].id) === q || normalize(options[i].name + " | " + options[i].id) === q) {
                return options[i].id;
            }
        }
        return "";
    }

    function refreshResults() {
        medicineHidden.value = "";
        renderResults(filterMedicines(medicineInput.value));
    }

    function setActiveOption(index) {
        var buttons = resultsBox.querySelectorAll(".medicine-option");
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].classList.toggle("active", i === index);
        }
    }

    medicineInput.addEventListener("focus", refreshResults);
    medicineInput.addEventListener("input", refreshResults);

    medicineInput.addEventListener("keydown", function (event) {
        if (resultsBox.hidden || !currentList.length) return;

        if (event.key === "ArrowDown") {
            event.preventDefault();
            activeIndex = Math.min(activeIndex + 1, currentList.length - 1);
            setActiveOption(activeIndex);
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            setActiveOption(activeIndex);
        } else if (event.key === "Enter") {
            if (activeIndex >= 0 && currentList[activeIndex]) {
                event.preventDefault();
                selectMedicine(currentList[activeIndex]);
            }
        } else if (event.key === "Escape") {
            resultsBox.hidden = true;
        }
    });

    resultsBox.addEventListener("click", function (event) {
        var target = event.target;
        if (!target || !target.classList.contains("medicine-option")) return;
        var index = Number(target.getAttribute("data-index"));
        if (!Number.isNaN(index) && currentList[index]) {
            selectMedicine(currentList[index]);
        }
    });

    document.addEventListener("click", function (event) {
        var wrap = document.getElementById("medicine_search_wrap");
        if (!wrap) return;
        if (!wrap.contains(event.target)) {
            resultsBox.hidden = true;
        }
    });

    if (!medicineInput.value && medicineHidden.value) {
        for (var k = 0; k < options.length; k++) {
            if (normalize(options[k].id) === normalize(medicineHidden.value)) {
                medicineInput.value = options[k].name + " | " + options[k].id;
                break;
            }
        }
    }

    form.addEventListener("submit", function (event) {
        var exactId = tryResolveExact(medicineInput.value);
        if (exactId) {
            medicineHidden.value = exactId;
        }
        if (!medicineHidden.value) {
            event.preventDefault();
            alert("Please select a medicine from suggestions.");
            medicineInput.focus();
            refreshResults();
        }
    });
})();
</script>
