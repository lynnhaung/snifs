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
            });
    // define the Node template
    myDiagram.nodeTemplate =
    $(go.Node, "Auto",
        {locationSpot: go.Spot.Center,},
        $(go.Shape, "Circle",
        {fill: "white",
         stroke: "#D8D8D8"},
         new go.Binding("fill","color"),// Shape.fill is bound to Node.data.color
         new go.Binding("figure","figure"),
         new go.Binding("stroke","border_color"),
         new go.Binding("strokeWidth", "border_width"),
     ),
     // define the node's text
     $(go.TextBlock,
         {margin: 5, font: "bold 11px Helvetica, bold Arial, sans-serif"},
         new go.Binding("text","key"))// TextBlock.text is bound to Node.data.key
     );
    //define the Link template
    myDiagram.linkTemplate =
    $(go.Link,
        {selectable: false},
        $(go.Shape,
        {stroke: "black",
         strokeWidth: 2},
        new go.Binding("stroke","line_color"),
        new go.Binding("strokeWidth","line_width"),),
     );
     //define the Click Listener template
     myDiagram.addDiagramListener("ObjectSingleClicked",
      function(e) {
        var part = e.subject.part;
        if (!(part instanceof go.Link))
        {
        // alert("Clicked on " + part.data.key);
        document.getElementById("inputEventsMsg").style.display = "block";

        }
      });
     //Nodes' array
     var p_node =[];
     var inwords =["討論","核能","污染","輻射","污水","嗨嗨","測試","加油"];
     var students = ["A5","B5","C3","C5","D5","D4","C4","D1","D2","C1","D3","A4","A2","A1","A3","B1","B4","B2","C2","B3"];
     var outwords = ["太陽能","水力","火力","風力","再生能源","地熱","生質能","核廢料","發電廠","電費","產能","度電","鈾235","燃料"];

     var outwords_data = [
         {
             key: "solar enery",
             color: "#fff5566"
         },

     ];
     //new varible to control properties
     var border_color, border_width, inborder_color, inborder_width, color, outborder_color, outborder_width;
     //for loop to add values
     //inwords[]
     for(var i = 0; i < inwords.length; i++)
     {
         if(inwords[i].indexOf("污水")>-1||inwords[i].indexOf("污染")>-1)
         {
             inborder_color = "rgb(217, 31, 25)";
         }else if (inwords[i].indexOf("測試")>-1||inwords[i].indexOf("加油")>-1)
         {
             inborder_color = "rgb(240, 162, 29)";
         }else if (inwords[i].indexOf("嗨嗨")>-1)
         {
             inborder_color = "rgb(39, 132, 254)";
         }else if (inwords[i].indexOf("核能")>-1)
         {
             inborder_color = "rgb(52, 138, 30)";
         }
         else {
             inborder_color = "black";
         }
         p_node.push({layer: 1, key: inwords[i], border_color: inborder_color, border_width: 3, figure: "RoundedRectangle" });
     }
     //students[]
     for(var i = 0; i < students.length; i++)
     {
         if (students[i].indexOf("A")>-1)
         {
         color = "rgb(39, 132, 254)";
         }
         else if (students[i].indexOf("B")>-1)
         {
         color = "rgb(240, 162, 29)";
         }
         else if (students[i].indexOf("C")>-1)
         {
         color = "rgb(52, 138, 30)";
         }
         else if (students[i].indexOf("D")>-1)
         {
         color = "rgb(217, 31, 25)";
         }

         p_node.push({layer: 2, key: students[i], color: color});
     }
     //outwords[]
     for(var i = 0; i < outwords.length; i++)
     {
         if(outwords[i].indexOf("電費")>-1||outwords[i].indexOf("發電廠")>-1||outwords[i].indexOf("太陽能")>-1)
         {
             outborder_color = "rgb(39, 132, 254)";
         }else if (outwords[i].indexOf("產能")>-1||outwords[i].indexOf("度電")>-1||outwords[i].indexOf("鈾235")>-1)
         {
             outborder_color = "rgb(240, 162, 29)";
         }else if (outwords[i].indexOf("核廢料")>-1||outwords[i].indexOf("生質能")>-1||outwords[i].indexOf("燃料")>-1||outwords[i].indexOf("水力")>-1)
         {
             outborder_color = "rgb(52, 138, 30)";
         }else if (outwords[i].indexOf("地熱")>-1||outwords[i].indexOf("再生能源")>-1||outwords[i].indexOf("風力")>-1||outwords[i].indexOf("火力")>-1)
         {
             outborder_color = "rgb(217, 31, 25)";
         }
         p_node.push({layer: 3, key: outwords[i], border_color: outborder_color, border_width: outborder_width, figure: "RoundedRectangle" });
     }
     //link's array
     var p_link = [
         {from: "D2", to: "污水", line_color: "rgb(217, 31, 25)"},
         {from: "D2", to: "污染", line_color: "rgb(217, 31, 25)"},
         {from: "D2", to: "地熱", line_color: "rgb(217, 31, 25)"},
         {from: "D1", to: "污水", line_color: "rgb(217, 31, 25)"},
         {from: "D1", to: "再生能源", line_color: "rgb(217, 31, 25)"},
         {from: "D3", to: "討論", line_color: "rgb(217, 31, 25)"},
         {from: "D5", to: "污染", line_color: "rgb(217, 31, 25)"},
         {from: "D5", to: "風力", line_color: "rgb(217, 31, 25)"},
         {from: "D5", to: "火力", line_color: "rgb(217, 31, 25)"},
         {from: "D1", to: "輻射", line_color: "rgb(217, 31, 25)"},
         {from: "C3", to: "核能", line_color: "rgb(52, 138, 30)"},
         {from: "C3", to: "水力", line_color: "rgb(52, 138, 30)"},
         {from: "C2", to: "燃料", line_color: "rgb(52, 138, 30)"},
         {from: "C1", to: "核廢料", line_color: "rgb(52, 138, 30)"},
         {from: "C1", to: "生質能", line_color: "rgb(52, 138, 30)"},
         {from: "C1", to: "輻射", line_color: "rgb(52, 138, 30)"},
         {from: "C1", to: "討論", line_color: "rgb(52, 138, 30)"},
         {from: "B5", to: "加油", line_color: "rgb(240, 162, 29)"},
         {from: "B5", to: "討論", line_color: "rgb(240, 162, 29)"},
         {from: "B5", to: "輻射", line_color: "rgb(240, 162, 29)"},
         {from: "B3", to: "加油", line_color: "rgb(240, 162, 29)"},
         {from: "B3", to: "測試", line_color: "rgb(240, 162, 29)"},
         {from: "B2", to: "鈾235", line_color: "rgb(240, 162, 29)"},
         {from: "B2", to: "測試", line_color: "rgb(240, 162, 29)"},
         {from: "B4", to: "度電", line_color: "rgb(240, 162, 29)"},
         {from: "B4", to: "測試", line_color: "rgb(240, 162, 29)"},
         {from: "B1", to: "產能", line_color: "rgb(240, 162, 29)"},
         {from: "B1", to: "嗨嗨", line_color: "rgb(240, 162, 29)"},
         {from: "A1", to: "電費", line_color: "rgb(39, 132, 254)"},
         {from: "A1", to: "發電廠", line_color: "rgb(39, 132, 254)"},
         {from: "A1", to: "污水", line_color: "rgb(39, 132, 254)"},
         {from: "A1", to: "嗨嗨", line_color: "rgb(39, 132, 254)"},
         {from: "A5", to: "討論", line_color: "rgb(39, 132, 254)"},
         {from: "A5", to: "輻射", line_color: "rgb(39, 132, 254)"},
         {from: "A5", to: "太陽能", line_color: "rgb(39, 132, 254)"},
         {from: "A4", to: "嗨嗨", line_color: "rgb(39, 132, 254)"},

     ];
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
    radius += 100;
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

 function showMessage(s) {
     document.getElementById("inputEventsMsg").textContent = s;
   }
