<?php
include '../db/config.php';
include '../db/customer_function.php';
/***************************************************/
//圈外詞節點(一人最多10個)
$sql_node_outwords = "SELECT words, team, countof,user_account, row_numbers
FROM (
      SELECT  @row_numbers := IF(@var_user_account = user_account, @row_numbers + 1, 1) AS row_numbers, @var_user_account := user_account as user_account, team, words,countof
      FROM
          (SELECT @row_numbers := 1) x,
          (SELECT countof, words, team, @var_user_account := user_account as user_account FROM snifs_p_node_outwords ORDER BY user_account, countof desc) y
    ) z
WHERE row_numbers <= 5;";
$result_node_outwords = mysql_query($sql_node_outwords) or die ('Invalid query: '.mysql_error());
$num_node_outwords = mysql_num_rows($result_node_outwords);
for($i = 0; $i<$num_node_outwords; $i++)
{
    $row_node_outwords[$i] = mysql_fetch_row($result_node_outwords);
};
/***************************************************/
//data transmit
$data_node_outwords = array(

    row_node_outwords => url_encode($row_node_outwords)

);

echo urldecode(json_encode($data_node_outwords));
/***************************************************/

mysql_close($conn);
?>
