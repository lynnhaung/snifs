console.log("userid="+userid);

/*data section*/
    //define color variable
    var A_blue = "#2185d0";
    var B_yellow = "#fbbd08";
    var C_green = "#21ba45";
    var D_red = "#db2828";
    var Black = "black";
    var person_color, person_border_color, person_border_width, inwords_color, inwords_bg_color, outwords_color, outwords_bg_color, link_color;
    //define Node array
    var p_node =[];
    // define Link array
    var p_link = [];
    // var row_node_inwords;

/*logic section*/
/*連接資料庫*/
$(document).ready(function()
{
//節點(人)
    $.post('./personal_get_data/personal_node_person.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_node_person = Object.keys(obj["row_node_person"]).map(function(key)
            {
            return obj["row_node_person"][key];
            });
            // console.log(row_node_person);
    // });
//節點(圈內詞)
    $.post('./personal_get_data/personal_node_inwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_node_inwords = Object.keys(obj["row_node_inwords"]).map(function(key)
            {
            return obj["row_node_inwords"][key];
            });
            // console.log(row_node_inwords);
    // });
//節點(圈外詞)
    $.post('./personal_get_data/personal_node_outwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_node_outwords = Object.keys(obj["row_node_outwords"]).map(function(key)
            {
            return obj["row_node_outwords"][key];
            });
            // console.log(row_node_outwords);
    // });
//連線(圈內詞)
    $.post('./personal_get_data/personal_link_inwords.php', function(result, status, xhr)
    {
        obj = JSON.parse(result);
        row_link_inwords = Object.keys(obj["row_link_inwords"]).map(function(key)
            {
            return obj["row_link_inwords"][key];
            });
            // console.log(row_link_inwords);
    // });
//連線(圈外詞)
    $.post('./personal_get_data/personal_link_outwords.php', function(result, status, xhr)
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
         1 : 0.3; }).ofObject(),
     ),
     // define the node's text
     $(go.TextBlock,
         {margin: 5,
          // font: "bold 11px Helvetica, bold Arial, sans-serif",
          font: "bold 14px 微軟正黑體, bold 微軟正黑體, 微軟正黑體",
      },
         new go.Binding("text","key"),
         new go.Binding("stroke","font_color"),
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
        new go.Binding("opacity", "isHighlighted", function(h) { return h ? 1 : 0.3; }).ofObject(),
     )
     );

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
            jQuery.post('personal_table_get_data.php',sendPerson, function(result, status, xhr)
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
            jQuery.post('personal_table_get_data.php',sendWord, function(result, status, xhr)
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
             person_border_width = 4;
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
     jQuery.post('personal_table_get_data.php',selfInwords, function(result, status, xhr)
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
         p_node.push({layer: 1, key: row_node_inwords[i][0], border_color: inwords_color, border_width: row_node_inwords[i][2], figure: "RoundedRectangle", color: inwords_bg_color
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
         p_node.push({layer: 3, key: row_node_outwords[i][0], border_color: outwords_color, border_width: row_node_outwords[i][2], figure: "RoundedRectangle", color: outwords_bg_color
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

/*SNIFS Layout Build End*/
// }
    });//post:row_self_inwords
    });//post:row_link_outwords
    });//post:row_link_inwords
    });//post:row_node_outwords
    });//post:row_node_inwords
    });//post:row_node_person
});//document.ready

//Functions
function TripleCircleLayout(diagram) {
    var $ = go.GraphObject.make;  // for conciseness in defining templates
    diagram.startTransaction("Multi Circle Layout");

    var radius = 50;
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
    radius += 120;
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
              var table_body = '<thead><tr><th colspan = "2">'+data[0][0]+'('+data[0][1]+')'+'</th></tr><tr><th>搜尋[詞彙]</th><th>次數</th></tr></thead><tbody>';
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
              var table_body = '<thead><tr><th colspan = "3">搜尋['+'<a>'+data[0][0]+'</a>]</th></tr><tr><th>組別編號</th><th>搜尋[姓名+詞彙]</th><th>次數</th></tr></thead><tbody>';
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
//移過去標亮Function
function highlightLink(link, show) {
    link.isHighlighted = show;
    link.fromNode.isHighlighted = show;
    link.toNode.isHighlighted = show;
}
