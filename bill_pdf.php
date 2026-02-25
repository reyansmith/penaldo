<?php
require('fpdf.php');

$conn = new mysqli("localhost", "root", "", "medivault_db");

if (!isset($_GET['bill_id'])) die("Bill ID missing");

$bill_id = $_GET['bill_id'];

$bill = $conn->query("SELECT * FROM bill WHERE bill_id='$bill_id'")->fetch_assoc();

$items = $conn->query("
    SELECT bd.quantity,
           bd.selling_price,
           p.medicine_name
    FROM bill_detail bd
    JOIN stock s ON bd.medicine_id = s.medicine_id
    JOIN product p ON s.medicine_id = p.medicine_id
    WHERE bd.bill_id='$bill_id'
");

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont("Arial","B",16);
$pdf->Cell(190,10,"MEDIVAULT PHARMACY",0,1,"C");

$pdf->SetFont("Arial","",12);
$pdf->Cell(100,8,"Bill No: ".$bill['bill_id'],0,1);
$pdf->Cell(100,8,"Date: ".$bill['bill_date'],0,1);
$pdf->Cell(100,8,"Customer: ".$bill['customer_name'],0,1);
$pdf->Cell(100,8,"Contact: ".$bill['customer_contact'],0,1);
$pdf->Cell(100,8,"Employee ID: ".$bill['emp_id'],0,1);
$pdf->Cell(100,8,"Payment: ".$bill['payment_method'],0,1);

$pdf->Ln(5);

$pdf->Cell(60,10,"Medicine",1);
$pdf->Cell(30,10,"Qty",1);
$pdf->Cell(40,10,"Price",1);
$pdf->Cell(40,10,"Total",1);
$pdf->Ln();

$grand = 0;

while($row = $items->fetch_assoc()) {

    $total = $row['quantity'] * $row['selling_price'];
    $grand += $total;

    $pdf->Cell(60,10,$row['medicine_name'],1);
    $pdf->Cell(30,10,$row['quantity'],1);
    $pdf->Cell(40,10,$row['selling_price'],1);
    $pdf->Cell(40,10,$total,1);
    $pdf->Ln();
}

$pdf->Cell(130,10,"Grand Total",1);
$pdf->Cell(40,10,$grand,1);

$pdf->Output();
?>