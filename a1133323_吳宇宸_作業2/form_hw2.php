<html>
<head><title>籃球夏令營報名表</title></head>
<body><body style = "background-color: #00ccff;">
<meta charset="utf-8">
<form action = "result_hw2.php" method = "POST">

<center><font size = "15" color ="#ff6200">報名資料<br></font>
<hr width ="100%" color = "black"></hr>
<font size = "5">
姓名: <input type = "text" name="nName" value = ""></br>
電話: <input type = "text" name="nPhone" value = ""></br>
身分證字號: <input type = "text" name="nID" value = ""></br>
電子郵件:<input type="email" name="email" value=""><br>
年級: 國中<input type="radio" name="age" value="j">
高中<input type="radio" name="age" value="s">
大學<input type="radio" name="age" value="c"></br>
偏好位置: <select name="place">
<option value="PF">大前鋒</option>
<option value="SF">小前鋒</option>
<option value="PG">控球後衛</option>
<option value="SG">得分後衛</option>
<option value="C">中鋒</option>
</select></br>
特殊疾病:</br> <textarea name = "SpecialSituation" rows="10" col ="10"></textarea></br>
</br><input type = "submit"><input type = "reset">


</font></center>

</form>
</body>
</html>