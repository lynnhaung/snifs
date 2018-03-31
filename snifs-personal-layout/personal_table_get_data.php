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
//傳值
$person = $_POST['person'];
$words = $_POST['words'];
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
//點詞表格
$sql_table_words = "select words, teamn, name, countof from snifs_p_table_words where words = '$words'";
$result_table_words = mysql_query($sql_table_words) or die ('Invalid query: '.mysql_error());
$num_table_words = mysql_num_rows($result_table_words);
for($i = 0; $i<$num_table_words; $i++)
{
    $row_table_words[$i] = mysql_fetch_row($result_table_words);
};
/***************************************************/
//利用詞(words)+組別編號(teamn)去搜尋user帳號
// $sql_table_words_teamn = "select words, teamn, name, countof from snifs_p_table_words where words = '$words'";
// $result_table_words_teamn = mysql_query($sql_table_words_teamn) or die ('Invalid query: '.mysql_error());
// $num_table_words_teamn = mysql_num_rows($result_table_words_teamn);
// for($i = 0; $i<$num_table_words_teamn; $i++)
// {
//     $row_table_words_teamn[$i] = mysql_fetch_row($result_table_words_teamn);
// };
/***************************************************/
//data transmit
$data_set = array(

    row_table_person => $row_table_person,
    row_table_words => $row_table_words
);

echo json_encode($data_set);
/***************************************************/

mysql_close($conn);
?>
