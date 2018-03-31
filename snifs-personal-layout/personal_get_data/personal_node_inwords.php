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
//圈內詞節點(最多20個 snifs_p_node_inwords limit 20)
$sql_node_inwords = "select words, WordTeam, Total from snifs_p_node_inwords";
$result_node_inwords = mysql_query($sql_node_inwords) or die ('Invalid query: '.mysql_error());
$num_node_inwords = mysql_num_rows($result_node_inwords);
for($i = 0; $i<$num_node_inwords; $i++)
{
    $row_node_inwords[$i] = mysql_fetch_row($result_node_inwords);
};
/***************************************************/
//data transmit
$data_node_inwords = array(

    row_node_inwords => $row_node_inwords

);

echo json_encode($data_node_inwords);
/***************************************************/
