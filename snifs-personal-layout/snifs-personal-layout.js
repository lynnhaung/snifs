/*data section*/
    //define color variable
    var A_blue = "#2185d0";
    var B_yellow = "#fbbd08";
    var C_green = "#21ba45";
    var D_red = "#db2828";
    var Black = "black";
    //define Node array
    var p_node =[];
    var inwords =
    [
        {key: "討論", border_color: Black, border_width: 3, color: "yellow",title: "123"},
        {key: "核能", border_color: C_green, border_width: 3},
        {key: "污染", border_color: D_red, border_width: 3},
        {key: "輻射", border_color: Black, border_width: 3, color: "yellow"},
        {key: "污水", border_color: D_red, border_width: 3},
        {key: "嗨嗨", border_color: A_blue, border_width: 3},
        {key: "測試", border_color: B_yellow, border_width: 3},
        {key: "加油", border_color: B_yellow, border_width: 3},
    ];
    var students = [
        {key: "A5", color: A_blue, font_color: "black", border_color: "yellow",border_width: 6},
        {key: "B5", color: B_yellow, border_color: B_yellow},
        {key: "C3", color: C_green, border_color: C_green},
        {key: "C5", color: C_green, border_color: C_green},
        {key: "D5", color: D_red, border_color: D_red},
        {key: "D4", color: D_red, border_color: D_red},
        {key: "C4", color: C_green, border_color: C_green},
        {key: "D1", color: D_red, border_color: D_red},
        {key: "D2", color: D_red, border_color: D_red},
        {key: "C1", color: C_green, border_color: C_green},
        {key: "D3", color: D_red, border_color: D_red},
        {key: "A4", color: A_blue, border_color: A_blue},
        {key: "A2", color: A_blue, border_color: A_blue},
        {key: "A1", color: A_blue, border_color: A_blue},
        {key: "A3", color: A_blue, border_color: A_blue},
        {key: "B1", color: B_yellow, border_color: B_yellow},
        {key: "B4", color: B_yellow, border_color: B_yellow},
        {key: "B2", color: B_yellow, border_color: B_yellow},
        {key: "C2", color: C_green, border_color: C_green},
        {key: "B3", color: B_yellow, border_color: B_yellow},
    ];
    var outwords =
    [
        {key: "太陽能", border_color: A_blue , border_width: 1, color: "yellow"},
        {key: "水力", border_color: C_green, border_width: 1},
        {key: "火力", border_color: D_red, border_width: 1},
        {key: "風力", border_color: D_red, border_width: 1},
        {key: "再生能源", border_color: D_red, border_width: 1},
        {key: "地熱", border_color: D_red, border_width: 1},
        {key: "生質能", border_color: C_green, border_width: 1},
        {key: "核廢料", border_color: C_green, border_width: 1},
        {key: "發電廠", border_color: A_blue, border_width: 1},
        {key: "電費", border_color: A_blue, border_width: 1},
        {key: "產能", border_color: B_yellow, border_width: 1},
        {key: "度電", border_color: B_yellow, border_width: 1},
        {key: "鈾235", border_color: B_yellow, border_width: 1},
        {key: "燃料", border_color: C_green, border_width: 1},
    ];
    // define Link array
    var p_link = [];
    var linknodes =
    [
        {from: "D2", to: "污水", line_color: D_red},
        {from: "D2", to: "污染", line_color: D_red},
        {from: "D2", to: "地熱", line_color: D_red},
        {from: "D1", to: "污水", line_color: D_red},
        {from: "D1", to: "再生能源", line_color: D_red},
        {from: "D3", to: "討論", line_color: D_red},
        {from: "D5", to: "污染", line_color: D_red},
        {from: "D5", to: "風力", line_color: D_red},
        {from: "D5", to: "火力", line_color: D_red},
        {from: "D1", to: "輻射", line_color: D_red},
        {from: "C3", to: "核能", line_color: C_green},
        {from: "C5", to: "核能", line_color: C_green},
        {from: "C3", to: "水力", line_color: C_green},
        {from: "C2", to: "燃料", line_color: C_green},
        {from: "C1", to: "核廢料", line_color: C_green},
        {from: "C1", to: "生質能", line_color: C_green},
        {from: "C1", to: "輻射", line_color: C_green},
        {from: "C1", to: "討論", line_color: C_green},
        {from: "B5", to: "加油", line_color: B_yellow},
        {from: "B5", to: "討論", line_color: B_yellow},
        {from: "B5", to: "輻射", line_color: B_yellow},
        {from: "B3", to: "加油", line_color: B_yellow},
        {from: "B3", to: "測試", line_color: B_yellow},
        {from: "B2", to: "鈾235", line_color: B_yellow},
        {from: "B2", to: "測試", line_color: B_yellow},
        {from: "B4", to: "度電", line_color: B_yellow},
        {from: "B4", to: "測試", line_color: B_yellow},
        {from: "B1", to: "產能", line_color: B_yellow},
        {from: "B1", to: "嗨嗨", line_color: B_yellow},
        {from: "A1", to: "電費", line_color: A_blue},
        {from: "A1", to: "發電廠", line_color: A_blue},
        {from: "A1", to: "污水", line_color: A_blue},
        {from: "A1", to: "嗨嗨", line_color: A_blue},
        {from: "A5", to: "討論", line_color: A_blue},
        {from: "A5", to: "輻射", line_color: A_blue},
        {from: "A5", to: "太陽能", line_color: A_blue},
        {from: "A4", to: "嗨嗨", line_color: A_blue},
    ]
    //define table json
    var myList =
    [
        // http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?search=%E8%A8%8E%E8%AB%96&id=73
         { "詞彙": '<a href="http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?search='+ encodeURI("討論")+'&id=73">討論</a>', "次數": "3"},
         { "詞彙": '<a href="">輻射</a>', "次數": "2"},
         { "詞彙": '<a href="">太陽能</a>', "次數": "1"},
    ]
/*logic section*/
function init(){
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
            // a Changed listener on the Diagram.model
            // "ModelChanged": function(e) { if (e.isTransactionFinished) saveModel(); }
          //   click: function(e) {  // background click clears any remaining highlighteds
          //   e.diagram.startTransaction("clear");
          //   e.diagram.clearHighlighteds();
          //   e.diagram.commitTransaction("clear");
          // }
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
             jQuery.getJSON("user_keywords_mock.json").done(function(data_person) {

        //Bindhtmltable(data);
        create_Persontable(data_person);
        jQuery("a").click(function() {
            var _word = jQuery(this).text();
            jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_word));
        });
    });
        }
        else if(part.data.figure == "RoundedRectangle"){  //點擊詞節點
            var _word = part.data.key;
            jQuery("#htmltable").empty();
             jQuery.getJSON("userword_keywords_mock.json").done(function(data_word) {

        create_Wordtable(data_word);
        jQuery("a").click(function() {
            var _user = jQuery(this).text();
            if(_user == _word){
            jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_word));
        }else {
            {
            jQuery(this).attr("href", "http://exp-snifs-2018.dlll.nccu.edu.tw/mod/hsuforum/search.php?id=73&words="+encodeURI(_word)+"&user="+encodeURI(_user));
            }
        }
        });
    });
        }
        }
      });

     //for loop to add values
     //inwords[]
     for(var i = 0; i < inwords.length; i++)
     {
         p_node.push({layer: 1, key: inwords[i].key, border_color: inwords[i].border_color, border_width: inwords[i].border_width, figure: "RoundedRectangle", color: inwords[i].color, title: inwords[i].title });
     }
     //students[]
     for(var i = 0; i < students.length; i++)
     {
         p_node.push({layer: 2, key: students[i].key, color: students[i].color, font_color: students[i].font_color, border_color: students[i].border_color, border_width: students[i].border_width});
     }
     //outwords[]
     for(var i = 0; i < outwords.length; i++)
     {
         p_node.push({layer: 3, key: outwords[i].key, border_color: outwords[i].border_color, border_width: outwords[i].border_width, figure: "RoundedRectangle", color: outwords[i].color});
     }
     for(var i = 0; i < linknodes.length; i++)
     {
         p_link.push({from: linknodes[i].from, to: linknodes[i].to, line_color: linknodes[i].line_color});
     }

     // create the model data that will be represented by Nodes and Links
     myDiagram.model = new go.GraphLinksModel(p_node,p_link);

     TripleCircleLayout(myDiagram);
}

    //functions
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


function create_Persontable(data){
    var number_of_rows = data.length;

              var table_body = '<thead><tr><th colspan = "2">'+data[0].fname+'</th></tr><tr><th>詞彙</th><th>次數</th></tr></thead><tbody>';
              for(var i =0;i<number_of_rows;i++){
                    table_body+='<tr>';
                    table_body +='<td>';
                    table_body +=data[i].word;
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i].count;
                    table_body +='</td>';
                    table_body+='</tr>';
              }
                table_body+='</tbody>';
               $('#htmltable').html(table_body);
}
function create_Wordtable(data){
    var number_of_rows = data.length;

              var table_body = '<thead><tr><th colspan = "3">'+data[0].word+'</th></tr><tr><th>組別編號</th><th>姓名</th><th>次數</th></tr></thead><tbody>';
              for(var i =0;i<number_of_rows;i++){
                    table_body+='<tr>';
                    table_body +='<td>';
                    table_body +=data[i].groupn;
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i].name;
                    table_body +='</td>';
                    table_body +='<td>';
                    table_body +=data[i].count;
                    table_body +='</td>';
                    table_body+='</tr>';
              }
                table_body+='</tbody>';
               $('#htmltable').html(table_body);
}

    function highlightLink(link, show) {
         link.isHighlighted = show;
         link.fromNode.isHighlighted = show;
         link.toNode.isHighlighted = show;
       }
