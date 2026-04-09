<?php


$nName=$_POST["nName"];
$nPhone=$_POST["nPhone"];
$nID=$_POST["nID"];
$nemail = $_POST["email"];
$nAge=$_POST["age"];
$nPlace =$_POST["place"];
$comment = $_POST["SpecialSituation"];

echo "名字:".$nName."<br/>";
echo "電話:".$nPhone."<br/>";
echo "身分證字號:".$nID."<br/>";

if($nAge == "j"){
    echo "年級:國中<br/>";
}else if($nAge == "s"){
    echo "年級:高中<br/>";
}else if($nAge == "c"){
    echo "年級:大學<br/>";
}


echo "偏好位置:".$nPlace."</br>";
echo"特殊病例:<br>";
echo stripslashes(nl2br($comment));


?>
