
<html>
<head><title>高雄大學校務系統</title></head>

<body style = "background-color: #04cbf8;">

<center>
<font size = "20" color = "#ffe600"><B>高雄大學校務系統</B></br></font>
</br><hr width = "80%"  size = "5%" color = "black">
<?php

if(isset($_COOKIE["uName"])){
    echo $_COOKIE['uName']."歡迎回來!!!";
    echo "<a href='cookiedel.php'>刪除COOKIE</a>";
}

?>
</br></br><img src = "登入圖片.png" width = 400></br></br></br>

<form action = "logincheck.php" method="POST">
帳號: <input type = "text" name ="uID"><br>
密碼: <input type = "password" name ="uPWD"><br><br>
<input type = "submit"><input type = "reset">

<?php
echo time();
?>

</form>
</center>
</body>
</html>