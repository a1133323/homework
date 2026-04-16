<?php
session_start();
if(isset($_SESSION["ID"])){
    $id = $_SESSION["ID"];
    $name = $_SESSION["Name"];
    $price = $_SESSION["Price"];
    $new_quantity = $_SESSION["Quantity"];


    if (isset($_COOKIE[$id]["Quantity"])) {
        $old_quantity = $_COOKIE[$id]["Quantity"];
        $total_quantity = $old_quantity + $new_quantity;
    } else {
        $total_quantity = $new_quantity;
    }


    setcookie($id."[ID]",$id,time()+3600);
    setcookie($id."[Name]",$name,time()+3600);
    setcookie($id."[Price]",$price,time()+3600);
    setcookie($id."[Quantity]",$total_quantity,time()+3600);
}

header("Location:shoppingcart.php");

?>