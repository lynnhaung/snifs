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
$sql_node_person = "select user_id, user_account, name, team, number, teamn from `snifs_p_node_person` group by user_id order by user_id";
$result_node_person = mysql_query($sql_node_person) or die ('Invalid query: '.mysql_error());
$num_node_person = mysql_num_rows($result_node_person);
for($i = 0; $i<$num_node_person; $i++)
{
    $row_node_person[$i] = mysql_fetch_row($result_node_person);
};
/***************************************************/
//data transmit
$data_node_person = array(

    row_node_person => $row_node_person
);

echo json_encode($data_node_person);
/***************************************************/
