<?php
session_start();
$conn = mysqli_connect("localhost","root","","medivault_db");

if(isset($_POST['login']))
{
    $id = $_POST['id'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 🔎 CHECK ADMIN
    $admin_query = mysqli_query($conn,
        "SELECT * FROM admin 
         WHERE admin_id='$id' 
         AND username='$username'"
    );

    if(mysqli_num_rows($admin_query) > 0)
    {
        $row = mysqli_fetch_assoc($admin_query);

        if(password_verify($password, $row['password']))
        {
            $_SESSION['role'] = "admin";
            $_SESSION['id'] = $row['admin_id'];
            $_SESSION['username'] = $row['username'];

            header("Location: dashboard.php");
            exit();
        }
    }

    // 🔎 CHECK EMPLOYEE
    $emp_query = mysqli_query($conn,
        "SELECT * FROM employee 
         WHERE emp_id='$id' 
         AND username='$username'"
    );

    if(mysqli_num_rows($emp_query) > 0)
    {
        $row = mysqli_fetch_assoc($emp_query);

        if(password_verify($password, $row['password']))
        {
            $_SESSION['role'] = "employee";
            $_SESSION['id'] = $row['emp_id'];
            $_SESSION['username'] = $row['username'];

            header("Location: dashboard.php");
            exit();
        }
    }

    echo "<script>alert('Invalid Details');</script>";
}
?>
   

<!DOCTYPE html>
<html>
<head>
<title>Medivault Login</title>
<style>
body{
    margin:0;
    font-family:Arial;
    background:#0f9d9a;
}
.box{
    width:400px;
    margin:120px auto;
    background:white;
    padding:40px;
    text-align:center;
    border-radius:10px;
    box-shadow:0px 0px 15px gray;
}
input{
    width:90%;
    padding:12px;
    margin:10px 0;
}
button{
    width:95%;
    padding:12px;
    background:navy;
    color:white;
    border:none;
}
button:hover{
    background:darkblue;
}
a{
    text-decoration:none;
    color:navy;
    font-weight:bold;
}
</style>
</head>
<body>

<div class="box">
<h2>MEDIVAULT LOGIN</h2>

<form method="POST">

<<<<<<< HEAD
<input type="text" name="id" placeholder="Enter ID" required>
=======
<input type="number" name="id" placeholder="Enter ID" required>
>>>>>>> 1c4873777fa8a1ed238614dc3b9c96119edb2241
<input type="text" name="username" placeholder="Enter Username" required>
<input type="password" name="password" placeholder="Enter Password" required>

<button type="submit" name="login">Login</button>

</form>

<br>
Don't have an account?
<a href="mregistration.php">Register Here</a>

</div>

</body>
</html>