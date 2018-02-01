<?php 
//$_POST['user_post_id'] = 10; test
if(!empty($_POST['user_account'])){
  $data = array();
  
  //--------------------------------------------------------------------------
  // Example php script for fetching data from mysql database
  //--------------------------------------------------------------------------

  $dbhost = 'localhost:3306';
  $dbuser = 'root';
  $dbpass = 'la2391';
  $dbname = 'moodle';


  //--------------------------------------------------------------------------
  // 1) Connect to mysql database
  //--------------------------------------------------------------------------

  $conn = mysql_connect($dbhost, $dbuser, $dbpass) ; 
  mysql_query("SET NAMES 'UTF8'"); 
  mysql_select_db($dbname); 

  if (!$conn) {
	  die(' 連線失敗，輸出錯誤訊息 : ' . mysql_error());
  }

  //--------------------------------------------------------------------------
  // 2) Query database for data
  //--------------------------------------------------------------------------

  $sql_data = "SELECT user_account, source, words, tag FROM jiebacut1 WHERE user_account like '".$_POST["user_account"]."'";
  $result = mysql_query($sql_data);          //query
  $array = mysql_num_rows($result); 
  
  if($array > 0){
        $userData = mysql_fetch_assoc($result);
        $data['status'] = 'ok';
        $data['result'] = $userData;
    }else{
        $data['status'] = 'err';
        $data['result'] = '';
    }
  //--------------------------------------------------------------------------
  // 3) echo result as json 
  //--------------------------------------------------------------------------
    
  echo json_encode($data);
}

?>
