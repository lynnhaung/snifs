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
//人節點
$sql_node_person = "select `user_id`, `user_account`, `name`, `team`, `number` from `snifs_p_node_person` group by `user_id` order by `user_id`";
$result_node_person = mysql_query($sql_node_person) or die ('Invalid query: '.mysql_error());
$num_node_person = mysql_num_rows($result_node_person);
for($i = 0; $i<$num_node_person; $i++)
{
    $row_node_person[$i] = mysql_fetch_row($result_node_person);
};
/***************************************************/
//圈內詞節點
$sql_node_inwords = "select words, WordTeam, Total from snifs_p_node_inwords";
$result_node_inwords = mysql_query($sql_node_inwords) or die ('Invalid query: '.mysql_error());
$num_node_inwords = mysql_num_rows($result_node_inwords);
for($i = 0; $i<$num_node_inwords; $i++)
{
    $row_node_inwords[$i] = mysql_fetch_row($result_node_inwords);
};
/***************************************************/
//圈外詞節點
$sql_node_outwords = "select words, team, countof from snifs_p_node_outwords";
$result_node_outwords = mysql_query($sql_node_outwords) or die ('Invalid query: '.mysql_error());
$num_node_outwords = mysql_num_rows($result_node_outwords);
for($i = 0; $i<$num_node_outwords; $i++)
{
    $row_node_outwords[$i] = mysql_fetch_row($result_node_outwords);
};
/***************************************************/
//連線
$sql_link = "select user_account, name, team, number, words from snifs_p_link";
$result_link = mysql_query($sql_link) or die ('Invalid query: '.mysql_error());
$num_link = mysql_num_rows($result_link);
for($i = 0; $i<$num_link; $i++)
{
    $row_link[$i] = mysql_fetch_row($result_link);
};
/***************************************************/
//data transmit
$data_set = array(

    row_node_person => $row_node_person,
    row_node_inwords => $row_node_inwords,
    row_node_outwords => $row_node_outwords,
    row_link => $row_link,
    row_table_person => $row_table_person
);

echo json_encode($data_set);
/***************************************************/

mysql_close($conn);
?>
