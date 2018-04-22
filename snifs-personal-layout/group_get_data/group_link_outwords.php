<?php
include '../db/config.php';
include '../db/customer_function.php';
/***************************************************/
//圈外詞連線(一組最多10個)
$sql_link_outwords = "SELECT user_account, team, words
FROM (
      SELECT  @row_numbers := IF(@var_user_account = user_account, @row_numbers+ 1, 1) AS row_numbers, @var_user_account := user_account as user_account, team, words
      FROM
          (SELECT @row_numbers := 1) x,
          (SELECT words, team, @var_user_account := user_account as user_account FROM snifs_g_link_outwords  ORDER BY team,countof desc) y
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

mysql_close($conn);
?>
