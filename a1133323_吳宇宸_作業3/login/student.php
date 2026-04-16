<html>
<header><title>學生校務系統</title></header>
<body style = "background-color: lightblue">

<?php
session_start();

if(isset($_SESSION["login"])){
    if($_SESSION["login"]=="user"){
        if(isset($_COOKIE["uName"])){
            echo "<h1>歡迎登入!".$_COOKIE['uName']."同學</h1>";
            echo "<a href='logout.php'>登出</a>";
        }
    }else{
        echo "<h1>非法網站進入你會看不到,3秒後回到登入頁面</h1>";
        header("Refresh:3;url=index.php");
        exit();
    }
}else{
    echo "<h1>非法網站進入你會看不到,3秒後回到登入頁面</h1>";
    header("Refresh:3;url=index.php");
    exit();
}

?>
<hr width = "100%" color = "black">
<center><font size = "16" color ="red"><B>你的資料</B></font><br>
<img src ="學生照片.jpeg" width = 400><br>
<font size = "5">
<B>學系: </B> 資訊管理學系<br>
<B>年級: </B> 二年級<br>
<B>學號: </B>a1133323    <br>
<B>姓名: </B>Andy        <br>

</font>

</center>

</body>
</html>