<!DOCTYPE html>
<html>
    <head>
    <meta charset="UTF-8">
    <script src="./assets/js/jQuery3.3.1.min.js"></script>
    <script src="./release/go.js"></script>


    <!-- <script src="test.js"></script> -->
    <link rel="stylesheet" type="text/css" href="snifs-personal-layout.css">
    <link rel="stylesheet" type="text/css" href="./semantic-ui/semantic.min.css">


    <title>SNIFS Personal Layout</title>
    </head>

    <!-- <body onload="init()"> -->
        <header>
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
        <script>
         layout = "<?php echo $_GET["layout"]; ?>";
        </script>
        </header>
        <div id ="myDiagramDiv">

        </div>
        <div id ="inputEventsMsg">

            <table class="ui blue table tbspan" id="htmltable" border="1" cellpadding="2" style="border-collapse: collapse;">
            <table class="ui orange table" id="htmltable1" border="1" cellpadding="2" style="border-collapse: collapse;">
            </table>

        </div>
        <footer>
        </footer>

        <script src="./assets/js/goSamples.js"></script><!-- this is only for the GoJS Samples framework -->
        <script src="snifs-personal-layout.js"></script>
    </body>
</html>
