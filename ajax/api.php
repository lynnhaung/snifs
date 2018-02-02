<?php 
//$_POST['user_account'] = "a01"; //test
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

  $sql_data = "SELECT user_account, source, words, tag FROM jiebacut1 WHERE tag = 'n' AND user_account like '".$_POST["user_account"]."'";  //只篩選名詞
  $result = mysql_query($sql_data);          //query
  $amount = mysql_num_rows($result); 
  
  if($amount > 0){
//       echo "[";
//       for($i = 0; $i < $amount; $i++){
//        while($userData[$i] = mysql_fetch_assoc($result))
//        {
    while($userData = mysql_fetch_assoc($result))
  {
        //$data['status'] = 'ok';
        $data[] = $userData;
        //$rows[]= $row;
  }
//echo json_encode($rows);
//        $userData= mysql_fetch_assoc($result);
//        $data['status'] = 'ok';
//        $data['result'] = $userData;
////        echo json_encode($data);
//        if($i < $amount)
//        {echo ","; }
//       }
//    }
//      echo "]";
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
