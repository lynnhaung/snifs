<!DOCTYPE html>
<html>
    <head>
    <meta charset="UTF-8">
    <script src="/iframe/jquery-3.3.1.min.js" type="text/javascript"></script>
    <script src="../google-analytics/config/exp-snifs-2018.js" type="text/javascript"></script>
    <script src="./release/go.js" type="text/javascript"></script>

    <!-- <script src="test.js"></script> -->
    <link rel="stylesheet" type="text/css" href="snifs-personal-layout.css">
    <link rel="stylesheet" type="text/css" href="./semantic-ui/semantic.min.css">


    <title>SNIFS Personal Layout</title>
    </head>

    <body>
<!--抓使用者帳號-->
    <?php
    require('../config.php');
    $userid = $USER->username;
    //echo $userid;
    echo '<script>';
    echo 'var userid = ' . json_encode($userid) . ';';
    echo '</script>';
     ?>
<!--判定抓個人或小組資料-->
        <script type="text/javascript">
         layout = "<?php echo $_GET["layout"]; ?>";
        </script>
        <div id ="myDiagramDiv">

        </div>
        <div id ="inputEventsMsg">

            <table class="ui blue table tbspan" id="htmltable" border="1" cellpadding="2" style="border-collapse: collapse;">
            <table class="ui orange table" id="htmltable1" border="1" cellpadding="2" style="border-collapse: collapse;">
            </table>

        </div>

        <script src="snifs-personal-layout.js" type="text/javascript"></script>
    </body>
</html>
