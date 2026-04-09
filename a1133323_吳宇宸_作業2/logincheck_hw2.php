<?php
$fID="Andy";
$fPWD="123";


if(isset($_POST["uID"])&&isset($_POST["uPWD"])){
    $uID=$_POST["uID"];
    $uPWD=$_POST["uPWD"];

    if($fID == $uID && $fPWD == $uPWD){
        header("Location: a1133323_hw2.php");
    }else{
        echo "失敗";
        header("Refresh:2;url=login_hw2.php");
    }
}





?>