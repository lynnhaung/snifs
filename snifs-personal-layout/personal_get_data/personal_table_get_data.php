<?php
include '../db/config.php';
include '../db/customer_function.php';
/***************************************************/
//傳值
$person = $_POST['person'];
$words = $_POST['words'];
$self = $_POST['user'];
/***************************************************/
//點人表格(一人最多呈現10個詞)
$sql_table_person = "select teamn, name, words, countof from snifs_p_table_person where cast(teamn as char character set utf8)  = '$person' limit 10";
$result_table_person = mysql_query($sql_table_person) or die ('Invalid query: '.mysql_error());
$num_table_person = mysql_num_rows($result_table_person);
for($i = 0; $i<$num_table_person; $i++)
{
    $row_table_person[$i] = mysql_fetch_row($result_table_person);
};
/***************************************************/
//點詞表格(一詞最多呈現10個人)
$sql_table_words = "select words, teamn, name, countof from snifs_p_table_words where words = '$words' limit 10";
$result_table_words = mysql_query($sql_table_words) or die ('Invalid query: '.mysql_error());
$num_table_words = mysql_num_rows($result_table_words);
for($i = 0; $i<$num_table_words; $i++)
{
    $row_table_words[$i] = mysql_fetch_row($result_table_words);
};
/***************************************************/
//利用詞(words)+組別編號(teamn)去搜尋user帳號
$sql_self_inwords = "select user_account, words from snifs_p_self_inwords where user_account = '$self';";
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
    row_self_inwords => url_encode($row_self_inwords)
);

echo urldecode(json_encode($data_set));
/***************************************************/

mysql_close($conn);
?>
