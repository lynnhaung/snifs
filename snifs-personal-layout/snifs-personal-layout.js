if (typeof(userid) === "undefined") {
	userid = "a01";
}
var userteam;
//console.log("userid=" + userid);
//console.log("layout=" + layout);

/*data section*/
//define color variable
var A_blue = "#2185d0";
var B_yellow = "#fbbd08";
var C_green = "#21ba45";
var D_red = "#db2828";
var Black = "black";
var person_color, person_border_color, person_border_width, inwords_border_width, inwords_color, inwords_bg_color, outwords_color, outwords_bg_color, link_color;

//define get data path
var path;
if (layout == 'personal') {
	path = './personal_get_data/personal';
} else if (layout == 'group') {
	path = './group_get_data/group';
}
else {
	path = './personal_get_data/personal';
}
//define table #inputEventsMsg
var table = document.getElementById("inputEventsMsg");
/*logic section*/
/*連接資料庫*/
$(document).ready(function () {

	$("body").click(closeTable);
	$("#inputEventsMsg > table").click(function (event) {
		event.stopPropagation();
	});

	_draw_go_graph();

}); //document.ready


// -------------------------------

/**
 *
 */
var _draw_go_graph = function () {
	var _diagram_id = "myDiagramDiv";
	_reset_go_graph(_diagram_id);

	var myDiagram = _buile_diagram_template(_diagram_id);
	if (layout == 'personal') {
		$.post("personal_get_data/personal_graph.php", function (_graph_data) {
			_graph_data = JSON.parse(_graph_data);
			_make_go_graph_object_perosnal(_graph_data, myDiagram);
		});

	}
	else {
		$.post("group_get_data/group_graph.php", function (_graph_data) {
			_graph_data = JSON.parse(_graph_data);
			_make_go_graph_object_group(_graph_data, myDiagram);
		});
	}
};

var _reset_go_graph = function (_diagram_id) {
	var _diagram_id = "myDiagramDiv";
	var _container = $("#" + _diagram_id).parent();
	_container.empty();
	_container.append('<div id="' + _diagram_id +'"></div>');
	return true;
};

// --------------------------


/**
 * _draw_go_graph_links_modes(p_node, p_link);
 */
var _draw_go_graph_links_modes = function (myDiagram, _p_node, _p_link, _graph_data) {
	// create the model data that will be represented by Nodes and Links

	//console.log("開始畫");
	//var seconds1 = new Date().getTime() / 1000;
	myDiagram.model = new go.GraphLinksModel(_p_node, _p_link);
	TripleCircleLayout(myDiagram, _graph_data);
	//var seconds2 = new Date().getTime() / 1000;
	//console.log("繪圖花費時間：" + (seconds2 - seconds1));
};


/*SNIFS Layout Build End*/
//Functions
function TripleCircleLayout(diagram, _graph_data) {
	var $ = go.GraphObject.make; // for conciseness in defining templates
	diagram.startTransaction("Multi Circle Layout");
	var layer = 1;
	var radius = 50; //layer 1的半徑
	//console.log("layer1:"+radius);

	var row_node_person = _graph_data.node_person;
	var row_node_inwords = _graph_data.node_inwords;

	var nodes = null;
	while (nodes = nodesByLayer(diagram, layer), nodes.count > 0) {

		// next layout uses a larger radius
		if (layer == 2 & row_node_person.length >= row_node_inwords.length) //人大於詞
		{
			radius = 100; //layer 2的半徑 = 80+180 = 260
			//console.log("layer2:"+radius);
		} else if (layer == 2 & row_node_person.length < row_node_inwords.length) //人小於詞
		{
			radius = 300; //layer 2的半徑 = 50+120 = 170
			//console.log("layer2:"+radius);
		}
		if (layer == 3) {
			radius = 400; //layer 3的半徑
			//console.log("layer3:"+radius);
		}

		var layout = $(go.CircularLayout, {
				radius: radius
			});
		layout.doLayout(nodes);
		// recenter at (0, 0)
		var cntr = layout.actualCenter;
		diagram.moveParts(nodes, new go.Point(-cntr.x, -cntr.y));
		layer++;
	}

	nodesByLayer(diagram, 0).each(function (n) {
		n.location = new go.Point(0, 0);
	});

	diagram.commitTransaction("Multi Circle Layout");
}

function nodesByLayer(diagram, layer) {
	var set = new go.Set(go.Node);
	diagram.nodes.each(function (part) {
		if (part instanceof go.Node && part.data.layer === layer) {
			set.add(part);
		}
	});
	return set;
}

// -----------------

//建立人、詞表格Function
function create_Persontable(data, _person) {
	if (typeof(data) === "undefined" || typeof(data[0]) === "undefined") {
		closeTable();
		jQuery(".fullscreen-mask").hide();
		return;
	}

	$("#inputEventsMsg").show();
	var number_of_rows = data.length;
	var _word = "";
	var _count = "";

	var table_body = '<thead><tr><th colspan = "3">' + data[0][0] + '(' + data[0][1] + ')' + '<button type="button" class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>詞彙</th><th>次數</th><th>搜尋</th></tr></thead>'
		 + '<tbody>';
	for (var i = 0; i < number_of_rows; i++) {
		table_body += '<tr>';
		table_body += '<td>';
		table_body += data[i][2];
		table_body += '</td>';
		table_body += '<td>';
		table_body += data[i][3];
		table_body += '</td>';
		table_body += '<td>';
		table_body += '<a word="' + data[i][2] + '" person="' + _person + '" count="' + data[i][3] + '" href="/mod/hsuforum/search.php?id=73&words=' + encodeURI(data[i][2]) + '&user=' + encodeURI(data[0][1]) + '" target="search_discuss">'
			+ '<i class="circular search link icon"></i>'
			+ '</a>';
		table_body += '</td>';
		table_body += '</tr>';
		_word = _word + data[i][2] + ",";
		_count = _count + data[i][3] + ",";
	}
	table_body += '</tbody>';
	var _dataPerson = data[0][0];
	var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _dataPerson + ": " + number_of_rows + ": " + _word.slice(0, -1) + ": " + _count.slice(0, -1);
	var _stmt = {
		hitType: 'event',
		eventCategory: 'click_snifs_p_node_person',
		eventAction: _dataset,
		eventLabel: _dataPerson
	};
	ga("send", _stmt);
	console.log("click_snifs_p_node_person", _stmt);
	$('#keyword_table').html(table_body);
	//init_search_event();

	$('#keyword_table').find("a").click(function () {
		// var _word = jQuery(this).text();
		var _word = jQuery(this).attr("word");
		var _person = this.person;
		_searchCount = jQuery(this).attr("count");
		//console.log(_word);
		var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _word + ": " + _searchCount;
		var _stmt = {
			hitType: 'event',
			eventCategory: 'click_serach_p_person_table',
			eventAction: _dataset,
			eventLabel: _word
		};
		ga("send", _stmt);
		console.log("click_serach_p_person_table", _stmt);
		_show_search_result();
	});
}

// -----------------

function create_Wordtable(data) {
	if (typeof(data) === "undefined" || typeof(data[0]) === "undefined") {
		closeTable();
		jQuery(".fullscreen-mask").hide();
		return;
	}

	$("#inputEventsMsg").show();
	var number_of_rows = data.length;
	var table_body = '<thead><tr><th colspan = "4">搜尋['
		+ '<a class="search_word" href="/mod/hsuforum/search.php?id=73&words=' + encodeURI(data[0][0]) + '" target="search_discuss" >'
			+ data[0][0]
		+ '</a>'
		+ ']<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>組別編號</th><th>姓名</th><th>次數</th><th>搜尋</th></tr></thead><tbody>';
	var _teamn = "";
	var _name = "";
	var _count = "";
	for (var i = 0; i < number_of_rows; i++) {
		table_body += '<tr>';
		table_body += '<td>';
		table_body += data[i][1];
		table_body += '</td>';
		table_body += '<td>';
		table_body += data[i][2];
		table_body += '</td>';
		table_body += '<td>';
		table_body += data[i][3];
		table_body += '</td>';
		table_body += '<td>';
		table_body += '<a teamn=' + data[i][1] + ' user=' + data[i][2] + ' count=' + data[i][3] + ' href="/mod/hsuforum/search.php?id=73&words=' + encodeURI(data[0][0]) + '&user=' + data[i][2] + '" target="search_discuss" >'
				+ '<i class="circular search link icon"></i>'
			+ '</a>';
		table_body += '</td>';
		table_body += '</tr>';

		_teamn = _teamn + data[i][1] + ",";
		_name = _name + data[i][2] + ",";
		_count = _count + data[i][3] + ",";

	}
	var _dataWord = data[0][0];
	var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _dataWord + ": " + number_of_rows + ": " + _teamn.slice(0, -1) + ": " + _name.slice(0, -1) + ": " + _count.slice(0, -1);
	var _stmt = {
		hitType: 'event',
		eventCategory: 'click_snifs_p_node_term',
		eventAction: _dataset,
		eventLabel: _dataWord
	};
	ga("send", _stmt);
	console.log("click_snifs_p_node_term", _stmt);
	table_body += '</tbody>';
	$('#keyword_table').html(table_body);

	$('#keyword_table').find("a").click(function () {
		//var _word = jQuery(this).text();
		var _user = jQuery(this).attr("user");
		if (_user == undefined) {
			_user = '';
		}

		var _teamn = jQuery(this).attr("teamn");
		var _count = jQuery(this).attr("count");
		var _words = $(this).text();
		var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _teamn + ": " + _user + ": " + _count;
		var _stmt = {
			hitType: 'event',
			eventCategory: 'click_serach_p_word_table',
			eventAction: _dataset,
			eventLabel: _words
		};
		ga("send", _stmt);
		console.log("click_serach_p_word_table", _stmt);

		_show_search_result();
	});
}

var _show_search_result = function () {
	enable_loading_message();
	setTimeout(function () {
		disable_loading_message();
		$("#search_result").show();
		closeTable();
		$(".fullscreen-mask").show();
	}, 500);
};

// -----------------

//建立組、組詞表格Function
function create_Teamtable(data, _team) {
	if (typeof(data) === "undefined" || typeof(data[0]) === "undefined") {
		closeTable();
		jQuery(".fullscreen-mask").hide();
		return;
	}

	$("#inputEventsMsg").show();
	var number_of_rows = data.length;
	var _team = "";
	var _teamcount = "";
	var table_body = '<thead><tr><th colspan = "3">' + data[0][0] + '組' + '<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>詞彙</th><th>總次數</th><th>搜尋</th></tr></thead><tbody>';
	for (var i = 0; i < number_of_rows; i++) {
		table_body += '<tr>';
		table_body += '<td>';
		table_body += data[i][1];
		table_body += '</td>';
		table_body += '<td>';
		table_body += data[i][2];
		table_body += '</td>';
		table_body += '<td>';
		table_body += '<a team="' + _team + '" word="' + data[i][1] + '" teamcount="' + data[i][2] + '" href="/mod/hsuforum/search.php?id=73&words=' + encodeURI(data[i][1]) + '&subject=' + encodeURI(_team) + '組討論區" target="search_discuss">'
			+ '<i class="circular search link icon"></i>'
			+ '</a>';
		table_body += '</td>';
		table_body += '</tr>';
		_team = _team + data[i][1] + ",";
		_teamcount = _teamcount + data[i][2] + ",";
	}
	table_body += '</tbody>';

	var _dataTeam = data[0][0];
	var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _dataTeam + ": " + number_of_rows + ": " + _team.slice(0, -1) + ": " + _teamcount.slice(0, -1);
	var _stmt = {
		hitType: 'event',
		eventCategory: 'click_snifs_g_node_team',
		eventAction: _dataset,
		eventLabel: _dataTeam
	};
	ga("send", _stmt);
	console.log("click_snifs_g_node_team", _stmt);
	$('#keyword_table').html(table_body);
	//init_search_event();

	$('#keyword_table a').click(function () {
		//var _word = jQuery(this).text();
		var _word = jQuery(this).attr("word");
		var _team = this.team;
		var _teamcount = jQuery(this).attr("teamcount");
		//console.log(_word);
		var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _word + ": " + _teamcount;
		var _stmt = {
			hitType: 'event',
			eventCategory: 'click_serach_g_team_table',
			eventAction: _dataset,
			eventLabel: _word
		};
		ga("send", _stmt);
		console.log("click_serach_g_team_table", _stmt);
		//jQuery(this).attr("href", "/mod/hsuforum/search.php?id=73&words=" + encodeURI(_word) + "&subject=" + encodeURI(_team) + "組討論區").attr('target', 'discuss');
		_show_search_result();
	});
}

// -----------------

function create_Team_Wordtable(data, _words) {
	if (typeof(data) === "undefined" || typeof(data[0]) === "undefined") {
		closeTable();
		jQuery(".fullscreen-mask").hide();
		return;
	}

	$("#inputEventsMsg").show();
	var number_of_rows = data.length;
	var _team = "";
	var _name = "";
	var _teamcount = "";
	var table_body = '<thead><tr><th colspan = "4">搜尋['
		+ '<a>'
			+ data[0][0]
		+ '</a>'
		+ ']<button class="ui blue basic button" onclick="closeTable()" style="float: right;">關閉</button></th></tr><tr><th>組別</th><th>姓名(編號)</th><th>總次數</th><th>搜尋</th></tr></thead><tbody>';
	for (var i = 0; i < number_of_rows; i++) {
		table_body += '<tr>';
		table_body += '<td>';
		table_body += data[i][1] + '組';
		table_body += '</td>';
		table_body += '<td>';
		table_body += data[i][2] + '(' + data[i][3] + ')';
		table_body += '</td>';
		table_body += '<td>';
		table_body += data[i][4];
		table_body += '</td>';
		table_body += '<td>';
		table_body += '<a words="' + _words +'" group="' + data[i][1] + '" user="' + data[i][2] + '" teamcount="' + data[i][4] + '" href="/mod/hsuforum/search.php?id=73&words=' + encodeURI(_words) + '&subject=' + encodeURI(data[i][1]) + '組討論區" target="search_discuss">'
			+ '<i class="circular search link icon"></i></a>';
		table_body += '</td>';
		table_body += '</tr>';

		_team = _team + data[i][1] + ",";
		_name = _name + data[i][2] + ",";
		_teamcount = _teamcount + data[i][4] + ",";

	}

	var _dataWord = data[0][0];
	var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _dataWord + ": " + number_of_rows + ": " + _team.slice(0, -1) + ": " + _name.slice(0, -1) + ": " + _teamcount.slice(0, -1);
	var _stmt = {
		hitType: 'event',
		eventCategory: 'click_snifs_g_node_term',
		eventAction: _dataset,
		eventLabel: _dataWord
	};
	ga("send", _stmt);
	console.log("click_snifs_g_node_term", _stmt);
	table_body += '</tbody>';

	//合併相同組別欄位(start)
	$(function () {
		$('#keyword_table').rowspan(2, 0); //'組'相同時合併'總次數'欄位
		//$('#keyword_table').rowspan(3, 0); //'組'相同時合併'總次數'欄位
		$('#keyword_table').rowspan(0); //合併相同的'組'欄位
	});
	//合併相同組別欄位(end)
	$('#keyword_table').html(table_body);


	$('#keyword_table').find("a").click(function () {
		var _words = this.words;
		var _group = jQuery(this).attr("group");
		var _user = jQuery(this).attr("user");
		var _teamcount = jQuery(this).attr("teamcount");
		//console.log(_word);
		var _dataset = get_user_id() + ": " + get_timestamp() + ": " + _group + ": " + _user + ": " + _teamcount;
		var _stmt = {
			hitType: 'event',
			eventCategory: 'click_serach_g_word_table',
			eventAction: _dataset,
			eventLabel: _group
		};
		ga("send", _stmt);
		console.log("click_serach_g_word_table", _stmt);
		if (_group == undefined) {
			_group = '';
		}
		//jQuery(this).attr("href", "/mod/hsuforum/search.php?id=73&words=" + encodeURI(_words) + "&subject=" + encodeURI(_group) + "組討論區").attr('target', 'discuss');

		_show_search_result();
	});
}

var _determine_color = function (_keyword) {
	switch (_keyword) {
	case 'A':
		return A_blue;
	case 'B':
		return B_yellow;
	case 'C':
		return C_green;
	case 'D':
		return D_red;
	case 'X':
		return Black;
	default:
		return A_blue;
	}
};

var _determine_user_team_color = function (userteam, team) {
	if (userteam === team) {
		return "yellow";
	} else {
		return "white";
	}
};

var _make_go_graph_object_perosnal = function (_graph_data, myDiagram) {
	var p_link = [], p_node = [];
	//define the Click Listener template
	_init_personal_object_click_event(myDiagram);

	//for loop to add values
	//students[]
	var row_node_person = _graph_data.node_person;
	for (var i = 0; i < row_node_person.length; i++) {
		//判斷登入的使用者
		if (userid == row_node_person[i][1]) {
			person_border_color = "yellow";
			person_border_width = 10;
		} else {
			person_border_width = 0;
		}
		p_node.push({
			layer: 2,
			key: row_node_person[i][5],
			color: _determine_color(row_node_person[i][3]),
			border_color: person_border_color,
			border_width: person_border_width
			// , font_color: , border_width:
		});
	}

	var row_node_inwords = _graph_data.node_inwords;
	var row_self_inwords = _graph_data.self_inwords;
	for (var i = 0; i < row_node_inwords.length; i++) {
		//標亮登入使用者用的圈內詞
		inwords_bg_color = "white";
		for (j = 0; j < row_self_inwords.length; j++) {
			if (row_node_inwords[i][0] == row_self_inwords[j][1]) {
				inwords_bg_color = "yellow";
				break;
			}
		}
		// 判斷該詞人使用次數>4將點度固定為5
		if (row_node_inwords[i][2] > 4) {
			inwords_border_width = 5;
		} else {
			inwords_border_width = row_node_inwords[i][2];
		}

		p_node.push({
			layer: 1,
			key: row_node_inwords[i][0],
			border_color: _determine_color(row_node_inwords[i][1]),
			border_width: inwords_border_width,
			figure: "RoundedRectangle",
			color: inwords_bg_color,
			degree: row_node_inwords[i][2]
			// , color:
		});
	}

	//outwords[]
	var row_node_outwords = _graph_data.node_outwords;
	for (var i = 0; i < row_node_outwords.length; i++) {
		//判斷登入的使用者
		if (userid == row_node_outwords[i][3]) {
			outwords_bg_color = "yellow";
		} else {
			outwords_bg_color = "white";
		}
		p_node.push({
			layer: 3,
			key: row_node_outwords[i][0],
			border_color: _determine_color(row_node_outwords[i][1]),
			border_width: 1,
			figure: "RoundedRectangle",
			color: outwords_bg_color,
			degree: row_node_outwords[i][2]
			// , color:
		});
	}
	//link_inwords[]
	var row_link_inwords = _graph_data.link_inwords;
	for (var i = 0; i < row_link_inwords.length; i++) {
		p_link.push({
			from: row_link_inwords[i][4],
			to: row_link_inwords[i][5],
			line_color: _determine_color(row_link_inwords[i][2])
		});
	}
	//link_outwords[]
	var row_link_outwords = _graph_data.link_outwords;
	for (var i = 0; i < row_link_outwords.length; i++) {
		p_link.push({
			from: row_link_outwords[i][4],
			to: row_link_outwords[i][5],
			line_color: _determine_color(row_link_outwords[i][2])
		});
	}

	_draw_go_graph_links_modes(myDiagram, p_node, p_link, _graph_data);
	disable_loading_message();
	_graph_ready();
};

var _make_go_graph_object_group = function (_graph_data, myDiagram) {
	var p_link = [], p_node = [];

	//define the Click Listener template
	_init_group_object_click_event(myDiagram);

	//for loop to add values
	//students[]
	var row_node_person = _graph_data.node_person;
	for (var i = 0; i < row_node_person.length; i++) {
		//判斷登入的使用者的組別
		if (userteam == row_node_person[i][0]) {
			person_border_color = "yellow";
			person_border_width = 10;
		} else {
			person_border_width = 0;
		}
		p_node.push({
			layer: 2,
			key: row_node_person[i][0],
			color: _determine_color(row_node_person[i][0]),
			border_color: person_border_color,
			border_width: person_border_width,
			font_margin: 6,
			font_style: "bold 25px 微軟正黑體, bold 微軟正黑體, 微軟正黑體"
			// , font_color: , border_width:
		});
	}

	var row_node_inwords = _graph_data.node_inwords;
	var row_self_inwords = _graph_data.self_inwords;
	for (var i = 0; i < row_node_inwords.length; i++) {
		//標亮登入使用者所屬組別用的圈內詞
		inwords_bg_color = "white";
		for (j = 0; j < row_self_inwords.length; j++) {
			if (row_node_inwords[i][0] == row_self_inwords[j][1]) {
				inwords_bg_color = "yellow";
				break;
			}
		}
		// 判斷該詞組使用次數>4將點度固定為5
		if (row_node_inwords[i][2] > 4) {
			inwords_border_width = 5;
		} else {
			inwords_border_width = row_node_inwords[i][2];
		}

		p_node.push({
			layer: 1,
			key: row_node_inwords[i][0],
			border_color: _determine_color(row_node_inwords[i][1]),
			border_width: inwords_border_width,
			figure: "RoundedRectangle",
			color: inwords_bg_color,
			degree: row_node_inwords[i][2]
			// , color:
		});
	}

	var row_node_outwords = _graph_data.node_outwords;
	//outwords[]
	for (var i = 0; i < row_node_outwords.length; i++) {
		//     //判斷登入的使用者
		p_node.push({
			layer: 3,
			key: row_node_outwords[i][0],
			border_color: _determine_color(row_node_outwords[i][1]),
			border_width: 1,
			figure: "RoundedRectangle",
			color: _determine_user_team_color(userteam, row_node_outwords[i][1]),
			degree: row_node_outwords[i][2]
			// , color:
		});
	}

	//link_inwords[]
	var row_link_inwords = _graph_data.link_inwords;
	for (var i = 0; i < row_link_inwords.length; i++) {
		p_link.push({
			from: row_link_inwords[i][1],
			to: row_link_inwords[i][2],
			line_color: _determine_color(row_link_inwords[i][1])
		});
	}

	//link_outwords[]
	var row_link_outwords = _graph_data.link_outwords;
	for (var i = 0; i < row_link_outwords.length; i++) {
		p_link.push({
			from: row_link_outwords[i][1],
			to: row_link_outwords[i][2],
			line_color: _determine_color(row_link_outwords[i][1])
		});
	}

	// create the model data that will be represented by Nodes and Links
	_draw_go_graph_links_modes(myDiagram, p_node, p_link, _graph_data);
	disable_loading_message();
	_graph_ready();
};

test_node = null;
var _buile_diagram_template = function (_diagram_id) {

	var $ = go.GraphObject.make; //to build GoJS objects
	// create a Diagram for the DIV HTML element
	var myDiagram =
		$(go.Diagram, _diagram_id, // must be the ID or reference to div
		{
			// start everything in the middle of the viewport
			// center the content
			"initialContentAlignment": go.Spot.Center,
			"initialAutoScale": go.Diagram.Uniform,
			"animationManager.isEnabled": false, //turn off automatic animations
			"undoManager.isEnabled": false, // enable undo & redo
			"allowZoom": true,
			"panningTool.isEnabled": false,
			"mouseWheelBehavior": go.ToolManager.WheelZoom,
			//"panningTool.bubbles": true,
			//"selectable": false
			//"allowSelect": true
			"dragSelectingTool.isEnabled": false
		});
	// define the Node template
	myDiagram.nodeTemplate =
		$(go.Node, "Auto", {
			locationSpot: go.Spot.Center,

			mouseEnter: function (e, node) {
				node.diagram.clearHighlighteds();
				node.linksConnected.each(function (l) {
					highlightLink(l, true);
				});
				node.isHighlighted = true;
				test_node = node;
			},
			mouseLeave: function (e, node) {
				node.diagram.clearHighlighteds();
			}
		}, // defined below},
			$(go.Shape, "Circle", {
				fill: "white",
			},
				new go.Binding("fill", "color"), // Shape.fill is bound to Node.data.color
				new go.Binding("figure", "figure"),
				new go.Binding("stroke", "border_color"),
				new go.Binding("strokeWidth", "border_width"),
				new go.Binding("opacity", "isHighlighted", function (h) {
					return h ?
					1 : 0.5;
				}).ofObject(), ),
			// define the node's text
			$(go.TextBlock, {
				margin: 5,
				// font: "bold 11px Helvetica, bold Arial, sans-serif",
				font: "bold 24px 微軟正黑體, bold 微軟正黑體, 微軟正黑體",
			},
				new go.Binding("text", "key"),
				new go.Binding("stroke", "font_color"),
				new go.Binding("margin", "font_margin"),
				new go.Binding("font", "font_style")) // TextBlock.text is bound to Node.data.key
		);
	//define the Link template
	myDiagram.linkTemplate =
		$(go.Link, {
			selectable: false,
			mouseEnter: function (e, link) {
				highlightLink(link, true);
			},
			mouseLeave: function (e, link) {
				highlightLink(link, false);
			}
		},
			$(go.Shape, {
				stroke: "black",
				strokeWidth: 2
			},
				new go.Binding("stroke", "line_color"),
				new go.Binding("strokeWidth", "line_width"),
				new go.Binding("opacity", "isHighlighted", function (h) {
					return h ? 1 : 0.5;
				}).ofObject(), ));

	return myDiagram;
};

var _init_personal_object_click_event = function (myDiagram) {
	jQuery("#keyword_table").empty();
	myDiagram.addDiagramListener("ObjectSingleClicked", function (e) {
		var part = e.subject.part;
		if (!(part instanceof go.Link)) {
			enable_loading_message();
			//show_keyword_table(); //點擊節點調整表格z-index
			if (part.data.figure != "RoundedRectangle") //  點擊人節點
			{
				var _person = part.data.key;
				// ga("send", "event", "click_snifs_p_node_person", get_user_id(), _person, 100);
				// console.log(["click_snifs_p_node_person", get_user_id(), _person]);
				// alert(_person);
				var sendPerson = {
					person: _person,
				};
				var _searchCount = "";
				jQuery.post(path + '_table_get_data_person.php', sendPerson, function (result, status, xhr) {
					obj = JSON.parse(result);
					var row_table_person = [];
					if (typeof(obj["row_table_person"]) !== "undefined") {
						row_table_person = Object.keys(obj["row_table_person"]).map(function (key) {
							return obj["row_table_person"][key];
						});
					}
					//Bindhtmltable(data);

					create_Persontable(row_table_person, _person);
					disable_loading_message();
					show_keyword_table(); //點擊節點調整表格z-index
				});
			} else if (part.data.figure == "RoundedRectangle") { //點擊詞節點
				var _words = part.data.key;
				//console.log(part.data);
				//alert(_words);
				// ga("send", "event", "click_snifs_p_node_term", get_user_id(), _words, 100);
				// console.log(["click_snifs_p_node_term", get_user_id(), _words]);

				jQuery.post(path + '_table_get_data_words.php', {
					words: _words,
				}, function (result, status, xhr) {
					obj = JSON.parse(result);
					row_table_words = Object.keys(obj["row_table_words"]).map(function (key) {
							return obj["row_table_words"][key];
						});
					//Bindhtmltable(data)
					create_Wordtable(row_table_words);

					disable_loading_message();
					show_keyword_table(); //點擊節點調整表格z-index
				});
			}
		}
	});
};

var _init_group_object_click_event = function (myDiagram) {
	jQuery("#keyword_table").empty();
	myDiagram.addDiagramListener("ObjectSingleClicked", function (e) {
		var part = e.subject.part;
		if (!(part instanceof go.Link)) {
			enable_loading_message();
			if (part.data.figure != "RoundedRectangle") //  點擊組節點
			{
				jQuery.post(path + '_table_get_data_team.php', {
					team: part.data.key,
				}, function (result, status, xhr) {
					obj = JSON.parse(result);
					row_table_person = Object.keys(obj["row_table_person"]).map(function (key) {
						return obj["row_table_person"][key];
					});
					//console.log(row_table_person);
					//Bindhtmltable(data);
					create_Teamtable(row_table_person, part.data.key);
					disable_loading_message();
					show_keyword_table(); //點擊節點調整表格z-index
				});
			} else if (part.data.figure == "RoundedRectangle") { //點擊組詞節點
				var _words = part.data.key;
				jQuery.post(path + '_table_get_data_words.php', {
					words: _words,
				}, function (result, status, xhr) {
					obj = JSON.parse(result);
					row_table_words = Object.keys(obj["row_table_words"]).map(function (key) {
						return obj["row_table_words"][key];
					});
					//Bindhtmltable(data)
					create_Team_Wordtable(row_table_words, _words);
					disable_loading_message();
					show_keyword_table(); //點擊節點調整表格z-index
				});
			}
		}
	});
};

//移過去標亮Function
highlightLink = function (link, show) {
	link.isHighlighted = show;
	if (link.fromNode !== null) {
		link.fromNode.isHighlighted = show;
	}
	link.toNode.isHighlighted = show;
}

////合併上下欄位(colIdx)
jQuery.fn.rowspan = function (colIdx) {
	return this.each(function () {
		var that;
		$('tr', this).each(function (row) {
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
		//alert('1');
	});
}
////當指定欄位(colDepend)值相同時，才合併欄位(colIdx)
jQuery.fn.rowspan = function (colIdx, colDepend) {
	return this.each(function () {
		var that;
		var depend;
		$('tr', this).each(function (row) {
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
	if (jQuery("#keyword_table:visible").length > 0) {
		jQuery("#keyword_table").empty();
		jQuery("#inputEventsMsg").hide();
		jQuery(".fullscreen-mask").hide();
	}
}
//調整表格z-Index
function show_keyword_table() {
	//$("body").addClass("show-keyword-table");
	//table.style.zIndex = 5;

	jQuery("#inputEventsMsg").show();
}

// ---------------------


var _graph_ready = function () {
	jQuery(".fullscreen-mask").hide();
};

// ------------------

function get_timestamp() {
	var date = new Date();
	var hh = date.getHours();
	var minutes = date.getMinutes();
	var ss = date.getSeconds();

	date = [(hh > 9 ? '' : '0') + hh,
		(minutes > 9 ? '' : '0') + minutes,
		(ss > 9 ? '' : '0') + ss
	].join('');

	return date;
}

var enable_loading_message = function () {
	$(".fullscreen-mask").show();
	$(".loading").show();
	$("body").addClass("loading-status");
};

var disable_loading_message = function () {
	jQuery(".loading").hide();
	jQuery("body").removeClass("loading-status");
};
