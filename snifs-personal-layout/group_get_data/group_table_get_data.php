<?php
include '../db/config.php';
include '../db/customer_function.php';
/***************************************************/
//傳值
$team = $_POST['team'];
$words = $_POST['words'];
$self = $_POST['user'];
$self_team = $_POST['user_team'];
/***************************************************/
//點人表格(一人最多呈現10個詞)
$sql_table_person = "select team, words, team_countof from snifs_g_table_team where cast(team as char character set utf8)  = '$team' limit 10";
$result_table_person = mysql_query($sql_table_person) or die ('Invalid query: '.mysql_error());
$num_table_person = mysql_num_rows($result_table_person);
for($i = 0; $i<$num_table_person; $i++)
{
    $row_table_person[$i] = mysql_fetch_row($result_table_person);
};
/***************************************************/
//點詞表格(一詞最多呈現10個人)
$sql_table_words = "select words, team, name, teamn ,team_countof from snifs_g_table_words where words = '$words' limit 10";
$result_table_words = mysql_query($sql_table_words) or die ('Invalid query: '.mysql_error());
$num_table_words = mysql_num_rows($result_table_words);
for($i = 0; $i<$num_table_words; $i++)
{
    $row_table_words[$i] = mysql_fetch_row($result_table_words);
};
/***************************************************/
//利用user帳號去搜尋組別
$sql_self_team = "select team from snifs_p_node_person where user_account = '$self';";
$result_self_team = mysql_query($sql_self_team) or die ('Invalid query: '.mysql_error());
$num_self_team = mysql_num_rows($result_self_team);
for($i = 0; $i<$num_self_team; $i++)
{
    $row_self_team[$i]= mysql_fetch_row($result_self_team);
};
/***************************************************/
//利用user組別去搜尋該組的圈內詞
$sql_self_inwords = "select team, words from snifs_g_self_inwords where team = '$self_team';";
$result_self_inwords = mysql_query($sql_self_inwords) or die ('Invalid query: '.mysql_error());
$num_self_inwords = mysql_num_rows($result_self_inwords);
for($i = 0; $i<$num_self_inwords; $i++)
{
    $row_self_inwords[$i]= mysql_fetch_row($result_self_inwords);
};
/***************************************************/
//data transmit
$data_set = array(

    row_table_person => url_encode($row_table_person),
    row_table_words => url_encode($row_table_words),
    row_self_team => url_encode($row_self_team),
    row_self_inwords => url_encode($row_self_inwords)
);

echo urldecode(json_encode($data_set));
/***************************************************/

mysql_close($conn);
?>
