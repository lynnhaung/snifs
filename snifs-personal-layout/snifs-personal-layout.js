console.log("userid="+userid);
console.log("layout="+layout);

/*data section*/
    //define color variable
    var A_blue = "#2185d0";
    var B_yellow = "#fbbd08";
    var C_green = "#21ba45";
    var D_red = "#db2828";
    var Black = "black";
    var person_color, person_border_color, person_border_width, inwords_border_width, inwords_color, inwords_bg_color, outwords_color, outwords_bg_color, link_color;
    //define Node array
    var p_node =[];
    //define Link array
    var p_link = [];
    //define get data path
    var path;
    if(layout == 'personal')
    {
    path = './personal_get_data/personal';
    }
    else if(layout == 'group')
    {
    path = './group_get_data/group';
    }
/*logic section*/
/*連接資料庫*/
$(document).ready(function()
{
//節點(人)  //網址變數 團體個人
    $.post(path+'_node_person.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_node_person = Object.keys(obj["row_node_person"]).map(function(key)
            {
            return obj["row_node_person"][key];
            });
            // console.log(row_node_person);
    // });
//節點(圈內詞)
    $.post(path+'_node_inwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_node_inwords = Object.keys(obj["row_node_inwords"]).map(function(key)
            {
            return obj["row_node_inwords"][key];
            });
            // console.log(row_node_inwords);
    // });
//節點(圈外詞)
    $.post(path+'_node_outwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_node_outwords = Object.keys(obj["row_node_outwords"]).map(function(key)
            {
            return obj["row_node_outwords"][key];
            });
            // console.log(row_node_outwords);
    // });
//連線(圈內詞)
    $.post(path+'_link_inwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_link_inwords = Object.keys(obj["row_link_inwords"]).map(function(key)
            {
            return obj["row_link_inwords"][key];
            });
            // console.log(row_link_inwords);
    // });
//連線(圈外詞)
    $.post(path+'_link_outwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_link_outwords = Object.keys(obj["row_link_outwords"]).map(function(key)
            {
            return obj["row_link_outwords"][key];
            });
            // console.log(row_link_outwords);
    // });

/*SNIFS Layout Build Start*/
// function init(){
    // for conciseness in defining templates in this function
    var $ = go.GraphObject.make; //to build GoJS objects
    // create a Diagram for the DIV HTML element
    myDiagram =
        $(go.Diagram, "myDiagramDiv", // must be the ID or reference to div
            {
            // start everything in the middle of the viewport
            // center the content
            initialContentAlignment: go.Spot.Center,
            initialAutoScale: go.Diagram.Uniform,
            "animationManager.isEnabled": false,  //turn off automatic animations
            "undoManager.isEnabled": true,  // enable undo & redo
            });
    // define the Node template
    myDiagram.nodeTemplate =
    $(go.Node, "Auto",
        {locationSpot: go.Spot.Center,

        mouseEnter: function(e, node) {
                node.diagram.clearHighlighteds();
                node.linksConnected.each(function(l) { highlightLink(l, true); });
                node.isHighlighted = true;
              },
              mouseLeave: function(e, node) {
                node.diagram.clearHighlighteds();
              }},  // defined below},
        $(go.Shape, "Circle",
        {fill: "white",
        },
         new go.Binding("fill","color"),// Shape.fill is bound to Node.data.color
         new go.Binding("figure","figure"),
         new go.Binding("stroke","border_color"),
         new go.Binding("strokeWidth","border_width"),
         new go.Binding("opacity", "isHighlighted", function(h) { return h ?
         1 : 0.5; }).ofObject(),
     ),
     // define the node's text
     $(go.TextBlock,
         {margin: 5,
          // font: "bold 11px Helvetica, bold Arial, sans-serif",
          font: "bold 14px 微軟正黑體, bold 微軟正黑體, 微軟正黑體",
      },
         new go.Binding("text","key"),
         new go.Binding("stroke","font_color"),
         new go.Binding("margin","font_margin"),
         new go.Binding("font","font_style")
     )// TextBlock.text is bound to Node.data.key
     );
    //define the Link template
    myDiagram.linkTemplate =
    $(go.Link,
        {selectable: false,
            mouseEnter: function(e, link) { highlightLink(link, true);},
            mouseLeave: function(e, link) { highlightLink(link, false);}
            },
        $(go.Shape,
        {stroke: "black",
         strokeWidth: 2},
        new go.Binding("stroke","line_color"),
        new go.Binding("strokeWidth","line_width"),
        new go.Binding("opacity", "isHighlighted", function(h) { return h ? 1 : 0.5; }).ofObject(),
     )
     );
//個人
if(layout == 'personal')
{
     //define the Click Listener template
     myDiagram.addDiagramListener("ObjectSingleClicked",
      function(e) {
        var part = e.subject.part;

        if (!(part instanceof go.Link))
        {

        if(part.data.figure != "RoundedRectangle")  //  點擊人節點
        {
            jQuery("#htmltable").empty();
            var _person = part.data.key;
            var sendPerson = {
                person: _person,
            };
            jQuery.post(path+'_table_get_data.php',sendPerson, function(result, status, xhr)
            {
                obj = JSON.parse(result);
                row_table_person = Object.keys(obj["row_table_person"]).map(function(key)
                    {
                    return obj["row_table_person"][key];
                    });
                //Bindhtmltable(data);
                create_Persontable(row_table_person);
                jQuery("a").click(function() {
                    var _word = jQuery(this).text();
                    jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_word)+"&user="+encodeURI(row_table_person[0][1])).attr('target','_blank');
                });
            });
        }
        else if(part.data.figure == "RoundedRectangle"){  //點擊詞節點
            var _words = part.data.key;
            jQuery("#htmltable").empty();
            var sendWord = {
                words: _words,
            };
            jQuery.post(path+'_table_get_data.php',sendWord, function(result, status, xhr)
            {
                obj = JSON.parse(result);
                row_table_words= Object.keys(obj["row_table_words"]).map(function(key)
                    {
                    return obj["row_table_words"][key];
                    });
                //Bindhtmltable(data)
                create_Wordtable(row_table_words);
                jQuery("a").click(function() {
                    var _word = jQuery(this).text();
                    if(_word == _words)
                    {
                        _word = '';
                    }
                    jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_words)+"&user="+encodeURI(_word)).attr('target','_blank');;
                });
            });
        }
        }
      });

     //for loop to add values
     //students[]
     for(var i = 0; i < row_node_person.length; i++)
     {
         //color or color[i]
         if(row_node_person[i][3]  == "A")
         {
             person_color = A_blue;
         }
         else if(row_node_person[i][3] == "B")
         {
             person_color= B_yellow;
         }
         else if(row_node_person[i][3] == "C")
         {
             person_color = C_green;
         }
         else if(row_node_person[i][3] == "D")
         {
             person_color = D_red;
         }
         //判斷登入的使用者
         if(userid == row_node_person[i][1])
         {
             person_border_color = "yellow";
             person_border_width = 10;
         }else
         {
             person_border_width = 0;
         }
         p_node.push({layer: 2, key: row_node_person[i][5], color: person_color, border_color: person_border_color, border_width: person_border_width
         // , font_color: , border_width:
         });
     }
     //利用登入帳號去找該帳號使用的圈內詞
     var selfInwords = {
         user: userid
     };
     jQuery.post(path+'_table_get_data.php',selfInwords, function(result, status, xhr)
     {
         obj = JSON.parse(result);
         row_self_inwords= Object.keys(obj["row_self_inwords"]).map(function(key)
             {
             return obj["row_self_inwords"][key];
             });
     //inwords[]
     for(var i = 0; i < row_node_inwords.length; i++)
     {
         if(row_node_inwords[i][1]  == "A")
         {
             inwords_color = A_blue;
         }
         else if(row_node_inwords[i][1] == "B")
         {
             inwords_color= B_yellow;
         }
         else if(row_node_inwords[i][1] == "C")
         {
             inwords_color = C_green;
         }
         else if(row_node_inwords[i][1] == "D")
         {
             inwords_color = D_red;
         }
         else if(row_node_inwords[i][1] == "X")
         {
             inwords_color = Black;
         }
          //標亮登入使用者用的圈內詞
         for(j=0; j< row_self_inwords.length; j++){

         if(row_node_inwords[i][0] == row_self_inwords[j][1])
         {
             inwords_bg_color = "yellow";
             break;
         }
         else
         {
             inwords_bg_color = "white";
         }
         }
         // 判斷該詞人使用次數>4將點度固定為5
         if(row_node_inwords[i][2] > 4)
         {
         inwords_border_width = 5;
         }else {
         inwords_border_width = row_node_inwords[i][2];
         }

         p_node.push({layer: 1, key: row_node_inwords[i][0], border_color: inwords_color, border_width: inwords_border_width, figure: "RoundedRectangle", color: inwords_bg_color
         // , color:
     });
     }

     //outwords[]
     for(var i = 0; i < row_node_outwords.length; i++)
     {
         if(row_node_outwords[i][1] == "A")
         {
             outwords_color = A_blue;
         }
         else if(row_node_outwords[i][1] == "B")
         {
             outwords_color= B_yellow;
         }
         else if(row_node_outwords[i][1] == "C")
         {
             outwords_color = C_green;
         }
         else if(row_node_outwords[i][1] == "D")
         {
             outwords_color = D_red;
         }
         //判斷登入的使用者
         if(userid == row_node_outwords[i][3])
         {
             outwords_bg_color = "yellow";
         }else
         {
             outwords_bg_color = "white";
         }
         p_node.push({layer: 3, key: row_node_outwords[i][0], border_color: outwords_color, border_width: 1, figure: "RoundedRectangle", color: outwords_bg_color
         // , color:
     });
     }
     //link_inwords[]
     for(var i = 0; i < row_link_inwords.length; i++)
     {
         if(row_link_inwords[i][2]  == "A")
         {
             link_color = A_blue;
         }
         else if(row_link_inwords[i][2] == "B")
         {
             link_color= B_yellow;
         }
         else if(row_link_inwords[i][2] == "C")
         {
             link_color = C_green;
         }
         else if(row_link_inwords[i][2] == "D")
         {
             link_color = D_red;
         }
         p_link.push({from: row_link_inwords[i][4], to: row_link_inwords[i][5], line_color: link_color});
     }
     //link_outwords[]
     for(var i = 0; i < row_link_outwords.length; i++)
     {
         if(row_link_outwords[i][2]  == "A")
         {
             link_color = A_blue;
         }
         else if(row_link_outwords[i][2] == "B")
         {
             link_color= B_yellow;
         }
         else if(row_link_outwords[i][2] == "C")
         {
             link_color = C_green;
         }
         else if(row_link_outwords[i][2] == "D")
         {
             link_color = D_red;
         }
         p_link.push({from: row_link_outwords[i][4], to: row_link_outwords[i][5], line_color: link_color});
     }

     // create the model data that will be represented by Nodes and Links
     myDiagram.model = new go.GraphLinksModel(p_node,p_link);

     TripleCircleLayout(myDiagram);

    });//post:row_self_inwords
}
//小組
else if(layout == 'group')
{
    //define the Click Listener template
    myDiagram.addDiagramListener("ObjectSingleClicked",
     function(e) {
       var part = e.subject.part;

       if (!(part instanceof go.Link))
       {

       if(part.data.figure != "RoundedRectangle")  //  點擊人節點
       {
           jQuery("#htmltable").empty();
           var _team = part.data.key;
           var sendTeam = {
               team: _team,
           };
           jQuery.post(path+'_table_get_data.php',sendTeam, function(result, status, xhr)
           {
               obj = JSON.parse(result);
               row_table_person = Object.keys(obj["row_table_person"]).map(function(key)
                   {
                   return obj["row_table_person"][key];
                   });
                console.log(row_table_person);
               //Bindhtmltable(data);
               create_Teamtable(row_table_person);
               jQuery("a").click(function() {
                   var _word = jQuery(this).text();
                   jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_word)+"&subject="+encodeURI(row_table_person[0][0])+"組討論區").attr('target','_blank');
               });
           });
       }
       else if(part.data.figure == "RoundedRectangle"){  //點擊詞節點
           var _words = part.data.key;
           jQuery("#htmltable").empty();
           var sendWord = {
               words: _words,
           };
           jQuery.post(path+'_table_get_data.php',sendWord, function(result, status, xhr)
           {
               obj = JSON.parse(result);
               row_table_words= Object.keys(obj["row_table_words"]).map(function(key)
                   {
                   return obj["row_table_words"][key];
                   });
               //Bindhtmltable(data)
               create_Team_Wordtable(row_table_words);
               jQuery("a").click(function() {
                   var _word = jQuery(this).text();
                   if(_word == _words)
                   {
                       _word = '';
                   }
                   jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_words)+"&subject="+encodeURI(_word)+"組討論區").attr('target','_blank');;
               });
           });
       }
       }
     });
     //利用登入帳號去找該帳號的組別
     var selfTeam = {
         user: userid
     };
     jQuery.post(path+'_table_get_data.php',selfTeam, function(result, status, xhr)
     {
         obj = JSON.parse(result);
         row_self_team= Object.keys(obj["row_self_team"]).map(function(key)
             {
             return obj["row_self_team"][key];
             });
    var userteam = row_self_team[0][0];
    console.log("userteam="+userteam);
    //for loop to add values
    //students[]
    for(var i = 0; i < row_node_person.length; i++)
    {
        //color or color[i]
        if(row_node_person[i][0]  == "A")
        {
            person_color = A_blue;
        }
        else if(row_node_person[i][0] == "B")
        {
            person_color= B_yellow;
        }
        else if(row_node_person[i][0] == "C")
        {
            person_color = C_green;
        }
        else if(row_node_person[i][0] == "D")
        {
            person_color = D_red;
        }
        //判斷登入的使用者的組別
        if(userteam == row_node_person[i][0])
        {
            person_border_color = "yellow";
            person_border_width = 10;
        }else
        {
            person_border_width = 0;
        }
        p_node.push({layer: 2, key: row_node_person[i][0], color: person_color, border_color: person_border_color, border_width: person_border_width, font_margin:6, font_style: "bold 25px 微軟正黑體, bold 微軟正黑體, 微軟正黑體"
        // , font_color: , border_width:
        });
    }
    //利用登入帳號去找該帳號所屬組別使用的圈內詞
    var selfInwords = {
        user_team: userteam
    };
    jQuery.post(path+'_table_get_data.php',selfInwords, function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_self_inwords= Object.keys(obj["row_self_inwords"]).map(function(key)
            {
            return obj["row_self_inwords"][key];
            });
        //console.log(row_self_inwords);
    //inwords[]
    for(var i = 0; i < row_node_inwords.length; i++)
    {
        if(row_node_inwords[i][1]  == "A")
        {
            inwords_color = A_blue;
        }
        else if(row_node_inwords[i][1] == "B")
        {
            inwords_color= B_yellow;
        }
        else if(row_node_inwords[i][1] == "C")
        {
            inwords_color = C_green;
        }
        else if(row_node_inwords[i][1] == "D")
        {
            inwords_color = D_red;
        }
        else if(row_node_inwords[i][1] == "X")
        {
            inwords_color = Black;
        }
         //標亮登入使用者所屬組別用的圈內詞
        for(j=0; j< row_self_inwords.length; j++){

        if(row_node_inwords[i][0] == row_self_inwords[j][1])
        {
            inwords_bg_color = "yellow";
            break;
        }
        else
        {
            inwords_bg_color = "white";
        }
        }
        // 判斷該詞組使用次數>4將點度固定為5
        if(row_node_inwords[i][2] > 4)
        {
        inwords_border_width = 5;
        }else {
        inwords_border_width = row_node_inwords[i][2];
        }

        p_node.push({layer: 1, key: row_node_inwords[i][0], border_color: inwords_color, border_width: inwords_border_width, figure: "RoundedRectangle", color: inwords_bg_color
        // , color:
    });
    }

    //outwords[]
    for(var i = 0; i < row_node_outwords.length; i++)
    {
        if(row_node_outwords[i][1] == "A")
        {
            outwords_color = A_blue;
        }
        else if(row_node_outwords[i][1] == "B")
        {
            outwords_color= B_yellow;
        }
        else if(row_node_outwords[i][1] == "C")
        {
            outwords_color = C_green;
        }
        else if(row_node_outwords[i][1] == "D")
        {
            outwords_color = D_red;
        }
    //     //判斷登入的使用者
        if(userteam == row_node_outwords[i][1])
        {
            outwords_bg_color = "yellow";
        }else
        {
            outwords_bg_color = "white";
        }
        p_node.push({layer: 3, key: row_node_outwords[i][0], border_color: outwords_color, border_width: 1, figure: "RoundedRectangle", color: outwords_bg_color
        // , color:
    });
    }
    //link_inwords[]
    for(var i = 0; i < row_link_inwords.length; i++)
    {
        if(row_link_inwords[i][1]  == "A")
        {
            link_color = A_blue;
        }
        else if(row_link_inwords[i][1] == "B")
        {
            link_color= B_yellow;
        }
        else if(row_link_inwords[i][1] == "C")
        {
            link_color = C_green;
        }
        else if(row_link_inwords[i][1] == "D")
        {
            link_color = D_red;
        }
        p_link.push({from: row_link_inwords[i][1], to: row_link_inwords[i][2], line_color: link_color});
    }
    //link_outwords[]
    for(var i = 0; i < row_link_outwords.length; i++)
    {
        if(row_link_outwords[i][1]  == "A")
        {
            link_color = A_blue;
        }
        else if(row_link_outwords[i][1] == "B")
        {
            link_color= B_yellow;
        }
        else if(row_link_outwords[i][1] == "C")
        {
            link_color = C_green;
        }
        else if(row_link_outwords[i][1] == "D")
        {
            link_color = D_red;
        }
        p_link.push({from: row_link_outwords[i][1], to: row_link_outwords[i][2], line_color: link_color});
    }

    // create the model data that will be represented by Nodes and Links
    myDiagram.model = new go.GraphLinksModel(p_node,p_link);

    TripleCircleLayout(myDiagram);

    });//post:row_self_inwords
    });//post:row_self_team
}
    });//post:row_link_outwords
    });//post:row_link_inwords
    });//post:row_node_outwords
    });//post:row_node_inwords
    });//post:row_node_person
});//document.ready
/*SNIFS Layout Build End*/
//Functions
function TripleCircleLayout(diagram) {
    var $ = go.GraphObject.make;  // for conciseness in defining templates
    diagram.startTransaction("Multi Circle Layout");

    var radius = 50; //layer 1的半徑
    if(row_node_inwords.length>15) //圈內詞超過15個加大半徑
    {
    radius=80;
    }
    var layer = 1;
    var nodes = null;
    while (nodes = nodesByLayer(diagram, layer), nodes.count > 0) {
    var layout = $(go.CircularLayout,
        { radius: radius });
        layout.doLayout(nodes);
    // recenter at (0, 0)
    var cntr = layout.actualCenter;
    diagram.moveParts(nodes, new go.Point(-cntr.x, -cntr.y));
    // next layout uses a larger radius
    if(layer==1 & row_node_inwords.length>15) //圈內詞超過15個加大半徑
    {
    radius += 180; //layer 2的半徑 = 80+180 = 260
    }
    else //圈內詞未超過15個
    {
    radius += 120; //layer 2的半徑 = 50+120 = 170
    }
    if(layer==2){
    radius += 20; //layer 3的半徑
    }
    layer++;
   }

   nodesByLayer(diagram, 0).each(function(n) { n.location = new go.Point(0, 0); });

   diagram.commitTransaction("Multi Circle Layout");
 }

 function nodesByLayer(diagram, layer) {
    var set = new go.Set(go.Node);
    diagram.nodes.each(function(part) {
    if (part instanceof go.Node && part.data.layer === layer) set.add(part);
    });
    return set;
 }
//建立人、詞表格Function
function create_Persontable(data){
    var number_of_rows = data.length;
              var table_body = '<thead><tr><th colspan = "2">'+data[0][0]+'('+data[0][1]+')'+'<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>搜尋[詞彙]</th><th>次數</th></tr></thead><tbody>';
              for(var i =0;i<number_of_rows;i++){
                    table_body+='<tr>';
                    table_body +='<td>';
                    table_body +='<a>'+data[i][2]+'</a>';
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i][3];
                    table_body +='</td>';
                    table_body+='</tr>';
              }
                table_body+='</tbody>';
               $('#htmltable').html(table_body);
}
function create_Wordtable(data){
    var number_of_rows = data.length;
              var table_body = '<thead><tr><th colspan = "3">搜尋['+'<a>'+data[0][0]+'</a>]<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>組別編號</th><th>搜尋[姓名+詞彙]</th><th>次數</th></tr></thead><tbody>';
              for(var i =0;i<number_of_rows;i++){
                    table_body+='<tr>';
                    table_body +='<td>';
                    table_body +=data[i][1];
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +='<a>'+data[i][2]+'</a>';
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i][3];
                    table_body +='</td>';
                    table_body+='</tr>';
              }
                table_body+='</tbody>';
               $('#htmltable').html(table_body);
}
//建立組、組詞表格Function
function create_Teamtable(data){
    var number_of_rows = data.length;
              var table_body = '<thead><tr><th colspan = "2">'+data[0][0]+'(組別)'+'<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>搜尋[詞彙]</th><th>總次數</th></tr></thead><tbody>';
              for(var i =0;i<number_of_rows;i++){
                    table_body+='<tr>';
                    table_body +='<td>';
                    table_body +='<a>'+data[i][1]+'</a>';
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i][2];
                    table_body +='</td>';
                    table_body+='</tr>';
              }
                table_body+='</tbody>';
               $('#htmltable').html(table_body);
}
function create_Team_Wordtable(data){
    var number_of_rows = data.length;
              var table_body = '<thead><tr><th colspan = "3">搜尋['+'<a>'+data[0][0]+'</a>]<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>搜尋[組別+詞彙]</th><th>姓名(編號)</th><th>總次數</th></tr></thead><tbody>';
              for(var i =0;i<number_of_rows;i++){
                    table_body+='<tr>';
                    table_body +='<td>';
                    table_body +='<a>'+data[i][1]+'</a>';
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i][2]+'('+data[i][3]+')';
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i][4];
                    table_body +='</td>';
                    table_body+='</tr>';
              }
                table_body+='</tbody>';
                //合併相同組別欄位(start)
                $(function() {
                $('.tbspan').rowspan(2,0); //'組'相同時合併'總次數'欄位
                $('.tbspan').rowspan(0); //合併相同的'組'欄位
                });
                //合併相同組別欄位(end)
               $('#htmltable').html(table_body);
}
//測試合併欄位用_假data
// function create_Team_Wordtable(data){
//     var number_of_rows = data.length;
//               var table_body = '<thead><tr><th colspan = "3">搜尋['+'<a>'+data[0][0]+'</a>]</th></tr><tr><th>搜尋[組別+詞彙]</th><th>姓名(編號)</th><th>總次數</th></tr></thead><tbody>';
//               for(var i =0;i<number_of_rows;i++){
//                     table_body+='<tr>';
//                     table_body +='<td>';
//                     table_body +='<a>A</a>';
//                     table_body +='</td>';
//                     table_body +='<td>';
//                     table_body +='姓名(編號)';
//                     table_body +='</td>';
//                     table_body +='<td>';
//                     table_body +='3';
//                     table_body +='</td>';
//                     table_body+='</tr>';
//               }
//                 table_body+='</tbody>';
//                 //合併相同組別欄位(start)
//                 $(function() {
//                 $('.tbspan').rowspan(2,0);
//                 $('.tbspan').rowspan(0);
//                 });
//                 //合併相同組別欄位(end)
//                $('#htmltable').html(table_body);
// }
//移過去標亮Function
function highlightLink(link, show) {
    link.isHighlighted = show;
    link.fromNode.isHighlighted = show;
    link.toNode.isHighlighted = show;
}

////合併上下欄位(colIdx)
jQuery.fn.rowspan = function(colIdx) {
    return this.each(function() {
        var that;
        $('tr', this).each(function(row) {
            var thisRow = $('td:eq(' + colIdx + '),th:eq(' + colIdx + ')', this);
            if ((that != null) && ($(thisRow).html() == $(that).html())) {
                rowspan = $(that).attr("rowSpan");
                if (rowspan == undefined) {
                    $(that).attr("rowSpan", 1);
                    rowspan = $(that).attr("rowSpan");
                }
                rowspan = Number(rowspan) + 1;
                $(that).attr("rowSpan", rowspan);
                $(thisRow).remove(); ////$(thisRow).hide();
            } else {
                that = thisRow;
            }
            that = (that == null) ? thisRow : that;
        });
        alert('1');
    });
}

////當指定欄位(colDepend)值相同時，才合併欄位(colIdx)
jQuery.fn.rowspan = function(colIdx, colDepend) {
    return this.each(function() {
        var that;
        var depend;
        $('tr', this).each(function(row) {
            var thisRow = $('td:eq(' + colIdx + '),th:eq(' + colIdx + ')', this);
            var dependCol = $('td:eq(' + colDepend + '),th:eq(' + colDepend + ')', this);
            if ((that != null) && (depend != null) && ($(thisRow).html() == $(that).html()) && ($(depend).html() == $(dependCol).html())) {
                rowspan = $(that).attr("rowSpan");
                if (rowspan == undefined) {
                    $(that).attr("rowSpan", 1);
                    rowspan = $(that).attr("rowSpan");
                }
                rowspan = Number(rowspan) + 1;
                $(that).attr("rowSpan", rowspan);
                $(thisRow).remove(); ////$(thisRow).hide();

            } else {
                that = thisRow;
                depend = dependCol;
            }
            that = (that == null) ? thisRow : that;
            depend = (depend == null) ? dependCol : depend;
        });
    });
}
//關閉表格
function closeTable() {
    jQuery("#htmltable").empty();
}
