<!DOCTYPE html>
<html>

<head>
    <title>
        SNIFS-iframe
    </title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

    <!-- Script Here -->
    <script src="jquery-3.3.1.min.js" type="text/javascript"></script>
    <script src="control.js" type="text/javascript"></script>
    <script src="/snifs-personal-layout/semantic-ui/semantic.min.js" type="text/javascript"></script>
    <script src="/google-analytics/config/exp-snifs-2018.js" type="text/javascript"></script>

    <!-- CSS Here -->
    <link rel="stylesheet" href="main.css" />
    <link rel="stylesheet" type="text/css" href="/snifs-personal-layout/semantic-ui/semantic.min.css">
    <!-- bootstrap Here -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
      <a class="navbar-brand" ondblclick="show_shubmit_report();">SNIFS</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <?php
            if (!(isset($_GET["mode"]) && $_GET["mode"] === "ctl")) {
                ?>
                <li>
                    <button id="btn_snifs_switch_person" class="ui button" onClick="personURL()">個人</button>
                </li>
                <li>
                    <button id="btn_snifs_switch_group" class="ui button" onClick="groupURL()">小組</button>
                </li>
                <?php
            }
            ?>

            <li>
                <button id="btnArticles" class="ui button">閱讀教材</button>
            </li>
            <li>
                <button id="btnDiscuss" class="ui button">回討論區</button>
            </li>
            <li>
                <button  id="btnTop" class="ui button">回到頁首</button>
            </li>
            <li id="submit_report_li" style="display: none;">
                <a href="/" target="submit_report">
                    <button  id="btnWiki" class="ui primary button">繳交報告</button>
                </a>
            </li>
        </ul>

    <div id="external_search">
        <script>
  (function() {
    var cx = '012091320069094462577:_4-dlv4dpxm';
    var gcse = document.createElement('script');
    gcse.type = 'text/javascript';
    gcse.async = true;
    gcse.src = 'https://cse.google.com/cse.js?cx=' + cx;
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(gcse, s);
  })();
</script>
<gcse:search></gcse:search>
    </div>
      </div>
    </nav>

    <div class="MainContainer">
        <div class="row">
            <?php
            if (isset($_GET["mode"]) && $_GET["mode"] === "ctl") {
                ?>
                <div class="col-lg">
                    <iframe id="discuss" name="discuss" class="frameBox" src="/mod/hsuforum/view.php?id=564&group=0" scrolling="yes"></iframe>
                </div>
                <?php
            }
            else {
                ?>

                <div class="col-lg">
                    <iframe name="snifs" class="frameBox" id = "snifs" src="/snifs-personal-layout/snifs-personal-layout.php?layout=personal" scrolling="yes"></iframe>
                </div>
                <div class="col-lg">
                    <iframe id="discuss" name="discuss" class="frameBox" src="/mod/hsuforum/view.php?id=564&group=0" scrolling="yes"></iframe>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="popupArticle" id="articleCard">
        <div class="article-content">
            <span id="btnClose">&times;</span>
            <h3>思索深澳／燃煤問題解決之道　竟是重啟核電？</h3>
            <br>
            <hr>
            <br>
            <img src="./image001.png" width="70%" height="70%">
            <br>
            <br>
            <span>台電深澳電廠的環評案在一片爭議聲中通過，引發雙北民眾對於汙染加劇的恐慌，空氣汙染成為外界高度質疑的問題。（圖／台電提供）</span>
            <hr>
            <p id="paragraph_1" class="content">　深澳電廠環差案過關，引起外界關注，環團19日表示，我國 2025 年基本上不會缺電，深澳燃煤電廠擴建後，也要到 2025 年才能發電，看不出必要性。不過台電人士指出，若工程順利當然不會缺電，但這其中變數很大，若沒有如期達標，仍難逃缺電夢魘，認為解決缺電危機，最佳的解答是重啟核電。</p>
            <p id="paragraph_2" class="content">　綠色公民行動聯盟副祕書長洪申翰表示，深澳燃煤電廠擴建後，到 2025 年以後才會開始供電，不過從台電的電源開發計畫來看，2025 年基本上不會缺電，因此沒有什麼必要性，再來，雖然深澳燃煤電廠採用超超臨界機組，但整個空汙排放量，問題還是滿大的。權衡之下，如果台灣要面對 2025 年以後電力系統的挑戰，深澳電廠並不是最佳的解答，尤其深澳燃煤電廠非常花錢，預算 1000 多億元。</p>
            <p id="paragraph_3" class="content">　洪申翰強調，從台電公開的電源開發計畫來看，2025 年以後，備用容量都在 15% 以上，備用容量 15% 就是供電穩定的重要指標，「台電如果現在還在說 2025 會缺電，是非常糟糕的事情，真得了解電力系統規畫的，都了解我們 2025 備用容量是非常足夠的。」</p>
            <p id="paragraph_4" class="content">　洪申翰指出，政府目標 2025 再生能源發電達 20%「並非絕對達不到的目標」，但確實是一個非常需要全力衝刺的挑戰。而擴建深澳燃煤電廠到底會不會造成嚴重的空汙，「不是台電自己說的算」，可能用的煤，比過去燃煤電廠汙染少一些，但仍會造成汙染，這是很簡單的邏輯，「也許比以前汙染少一點，但還是會造成影響。」</p>
            <p id="paragraph_5" class="content">　洪申翰表示，大家也都了解燃煤電廠的狀況，他並不是絕對反對蓋燃煤電廠，但要蓋，首先要有強烈的必要性，且扮演的功能是其他做法「完全無法取代」才行，但現在的深澳燃煤電廠根本沒達到這種狀況。</p>
            <p id="paragraph_6" class="content">　不過有台電內部人士表示，如果一切工程順利，那台灣 2025 年當然不會缺電，不過這期間的變數很大，只要瓦斯天然氣的接受站、電廠擴建等工程碰到抗爭，讓工程目標無法如期達標，台灣仍難逃缺電夢魘。台電人士強調，要解決台灣缺電危機，最佳的解答就是重啟核電，畢竟核能沒有碳排放或空汙的問題，況且又是早已蓋好的東西，可省下政府一筆不小的經費。不過政府希望 2025 年非核家園，因此解決缺電的方式只有燒煤或天然氣。如果煤炭因為空汙問題不能使用，就只剩下天然氣一途。至於政府 2025 年希望再生能源發電量達20%，該人士坦言「有很大的難度」。</p>
            <p id="paragraph_7" class="content">　此外，台電人士也指出，燃煤電廠並非主要汙染源，台灣空汙有三分之一來自境外，三分之二來自境內，這其中又以汽機車排放、工業汙染最多，燃煤電廠製造的汙染不到境內汙染的 10%。況且，深澳燃煤電廠使用的是超超臨界機組，較現行機組減少 75% 的空汙，並沒有外界想像的嚴重。</p>
            <p id="subtitle_header">記者林仕祥／台北報導 2018/03/20 06:00</p>
        </div>
    </div>

    <script>
        var aCard = document.getElementById('articleCard');
        var btnAs = document.getElementById('btnArticles');
        var btnC = document.getElementById('btnClose');
        var btnD = document.getElementById('btnDiscuss');

        btnAs.onclick = function () {
            aCard.style.display = 'block';
        };

        btnC.onclick = function () {
            aCard.style.display = 'none';
        };

        btnD.onclick = function() {
            $("#discuss:first").attr("src", "/mod/hsuforum/view.php?id=564&group=0");
        };

        $("#btnTop").click(function () {
            var _url = $("#discuss:first").attr("src");
            if (_url.indexOf("#") > -1) {
                _url = _url.substr(0, _url.lastIndexOf("#") );
            }
            _url = _url + "#page-mod-hsuforum-view";
            $("#discuss:first").attr("src", _url);
        });

        var _shift_pressed = false;
        var show_shubmit_report = function () {
            if (_shift_pressed === true) {
                $("#submit_report_li").show();
            }
        };
        $("body").keydown(function (_e) {
            if (_e.keyCode === 16) {
                _shift_pressed = true;
                //console.log(true);
            }
            //console.log(_e.keyCode);
        });
        $("body").keyup(function (_e) {
            if (_e.keyCode === 16) {
                _shift_pressed = false;
                //console.log(false);
            }
        });

    </script>



    <!-- bootstrap js Here -->
    <!-- <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous" type="text/javascript"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js" integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ" crossorigin="anonymous" type="text/javascript"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous" type="text/javascript"></script>
</body>

</html>
