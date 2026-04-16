<?php
session_start();

$sID="Andy";
$sPWD="123";

$aID="admin";
$aPWD="456";

$tID="teacher";
$tPWD="789";



$uID=$_POST["uID"];
$uPWD=$_POST["uPWD"];


$date = strtotime("+60 seconds",time());

if(isset($_POST["uID"])&&isset($_POST["uPWD"])){

    if($sID == $uID && $sPWD == $uPWD){
        $_SESSION["login"]="user";
        setcookie("uName",$uID,$date);
        header("Refresh:0;url=student.php");
    }elseif($aID == $uID && $aPWD == $uPWD){
        $_SESSION["login"]="admin";
        setcookie("uName",$uID,$date);
        header("Refresh:0;url=admin.php");
    }elseif($tID == $uID && $tPWD == $uPWD){
        $_SESSION["login"]="teacher";
        setcookie("uName",$uID,$date);
        header("Refresh:0;url=teacher.php");
    }else{
        echo "<h1>登入失敗,3秒後回到登入畫面</h1>";
        header("Refresh:3;url=index.php");
    }
}


?>