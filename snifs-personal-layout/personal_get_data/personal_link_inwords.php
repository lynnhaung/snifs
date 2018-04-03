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
//圈內詞連線(最多20個 snifs_p_node_inwords limit 20)
$sql_link_inwords = "SELECT user_account, name, team, number, teamn, words
FROM snifs_p_link_inwords";
$result_link_inwords = mysql_query($sql_link_inwords) or die ('Invalid query: '.mysql_error());
$num_link_inwords = mysql_num_rows($result_link_inwords);
for($i = 0; $i<$num_link_inwords; $i++)
{
    $row_link_inwords[$i] = mysql_fetch_row($result_link_inwords);
};
/***************************************************/
//data transmit
$data_link_inwords = array(

    row_link_inwords => url_encode($row_link_inwords)

);

echo urldecode(json_encode($data_link_inwords));
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
