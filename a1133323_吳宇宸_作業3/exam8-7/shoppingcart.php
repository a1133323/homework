<?php
function each(&$array){
    if (!is_array($array)) return false;
    $res = array();
    $key = key($array);
    if($key !== null){
        $val = current($array); 
        next($array);
        $res[1] = $res["value"] = $val;
        $res[0] = $res["key"] = $key;
        return $res; 
    } else {
        return false;
    }
}
?>
<html>
<head><title>我的購物車清單</title></head>
<body>
    <h1>購物車內容</h1>
    <table border="1" width="80%">
        <tr bgcolor="#CCCCCC">
            <th>功能</th>
            <th>編號</th>
            <th>名稱</th>
            <th>單價</th>
            <th>數量</th>
        </tr>

        <?php
        $total = 0;  
        $flag = true; 

        while (list($arr, $value) = each($_COOKIE)){
           
            if(isset($_COOKIE[$arr]) && is_array($_COOKIE[$arr])){
                if ($flag){
                    $flag = false;
                    $color = "#FF99CC";
                } else {
                    $flag = true;
                    $color = "#99FFFC";
                }

            
                echo "<tr bgcolor='".$color."'><td>";
                echo "<a href='delete.php?Id=".$arr."'>刪除</a></td>";

                $price = 0;
                $quantity = 0;
            
                while(list($name, $val2) = each($_COOKIE[$arr])){
                    echo "<td>" . $val2 . "</td>";
                    if($name == "Price") $price = (int)$val2;
                    if($name == "Quantity") $quantity = (int)$val2;
                }
                $total += $price * $quantity;
                echo "</tr>";
            }
        }
        ?>

        <tr>
            <td colspan="4" align="right"><strong>總金額</strong></td>
            <td><b><?php echo $total; ?> 元</b></td>
        </tr>
    </table>
    <br>
    <a href="catalog.php">繼續購物</a>
</body>
</html>