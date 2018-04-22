<?php
include '../db/config.php';
include '../db/customer_function.php';
/***************************************************/
//圈內詞連線(最多30個 資料表snifs_g_link_inwords limit 30)
$sql_link_inwords = "SELECT user_account, team, words
FROM snifs_g_link_inwords limit 30";
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

mysql_close($conn);
?>
