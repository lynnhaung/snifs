<?php
function check_user_logined() {
	require( '../config.php' );

	if (FALSE === isloggedin()) {
		setcookie("login_redirect", $CFG->wwwroot . "/iframe", time() + (86400 * 30), "/");
		header("Location: /login/index.php");
	}

}

function get_username_from_cookie() {
	$username = NULL;
	if (isset($_COOKIE["moodle_user"])) {
		$username = $_COOKIE["moodle_user"];
		if (strpos($username, "@") !== FALSE) {
			$username = substr($username, 0,  strpos($username, "@"));
		}
	}
	return $username;
}

function get_user_team($user) {
	include '../snifs-personal-layout/db/config.php';
	include '../snifs-personal-layout/db/customer_function.php';

	$sql_self_team = "select team from snifs_p_node_person where user_account = '$user';";
	$result_self_team = mysql_query($sql_self_team) or die ('Invalid query: '.mysql_error());
	$num_self_team = mysql_num_rows($result_self_team);
	$user_team = mysql_fetch_row($result_self_team);
	$user_team = $user_team[0];
	return $user_team;
}
