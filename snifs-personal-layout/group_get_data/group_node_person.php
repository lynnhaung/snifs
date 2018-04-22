<?php
include '../db/config.php';
include '../db/customer_function.php';
/***************************************************/
//人節點
$sql_node_person = "select team from `snifs_g_node_team` order by team";
$result_node_person = mysql_query($sql_node_person) or die ('Invalid query: '.mysql_error());
$num_node_person = mysql_num_rows($result_node_person);
for($i = 0; $i<$num_node_person; $i++)
{
    $row_node_person[$i] = mysql_fetch_row($result_node_person);
};
/***************************************************/
//data transmit
$data_node_person = array(

    row_node_person => url_encode($row_node_person)
);

echo urldecode(json_encode($data_node_person));
/***************************************************/

mysql_close($conn);
?>
