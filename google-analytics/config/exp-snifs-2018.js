/**
 * 適用網頁：http://vinson.rd.ssic.nccu.edu.tw/
 * 事件查詢表：https://docs.google.com/spreadsheets/d/1MtMtw9lKLDTUzfBd6Ld0fAe_FGe5u-Mlkh5WfZiH5qM/edit
 * @author Pudding 20170203
 */

GA_TRACE_CODE = "UA-118020470-1";
        
var _local_debug = false;


    CSS_URL = "/google-analytics/config/exp-snifs-2018.css";
    LIB_URL = "/google-analytics/ga_inject_lib.js";


var exec = function () {

    $.get("/username.php", function (username) {
        set_user_id(username);   

        //按鈕
        ga_submit_event("#external_search","external_search");
        
        ga_mouse_click_event("#snifs_person", "snifs_switch_person");
        ga_mouse_click_event("#snifs_group", "snifs_switch_group");
    
    });
    
    
};


// --------------------------------------

$(function () {
    $.getScript(LIB_URL, function () {
        ga_setup(function () {
            exec();
        });
    });
});

function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) return parts.pop().split(";").shift();
}
