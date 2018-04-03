<?php
error_reporting(0);
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = 'la2391';
$dbname = 'moodle';
$conn = mysql_connect($dbhost, $dbuser, $dbpass);
if(!$conn){
    die('Could not connect: ' . mysql_error());
}
//echo 'Connected successfully';
mysql_query("Set names 'utf8'");
mysql_select_db($dbname);
/***************************************************/
//圈外詞連線(一人最多10個)
$sql_link_outwords = "SELECT user_account, name, team, number, teamn, words
FROM (
      SELECT  @row_numbers := IF(@var_user_account = user_account, @row_numbers+ 1, 1) AS row_numbers, @var_user_account := user_account as user_account,  name, team, number, teamn, words
      FROM
          (SELECT @row_numbers := 1) x,
          (SELECT words, teamn, number,  team, name, @var_user_account := user_account as user_account FROM snifs_p_link_outwords  ORDER BY user_account) y
    ) z
WHERE row_numbers <= 10;";
$result_link_outwords = mysql_query($sql_link_outwords) or die ('Invalid query: '.mysql_error());
$num_link_outwords = mysql_num_rows($result_link_outwords);
for($i = 0; $i<$num_link_outwords; $i++)
{
    $row_link_outwords[$i] = mysql_fetch_row($result_link_outwords);
};
/***************************************************/
//data transmit
$data_link_outwords = array(

    row_link_outwords => url_encode($row_link_outwords)
);

echo urldecode(json_encode($data_link_outwords));
/***************************************************/
//中文編碼function
function url_encode($str) {
    if(is_array($str)) {
        foreach($str as $key=>$value) {
            $str[urlencode($key)] = url_encode($value);
        }
    } else {
        $str = urlencode($str);
    }

    return $str;
}
/***************************************************/
mysql_close($conn);
?>
