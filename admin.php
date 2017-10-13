<?php
include 'func.inc';
PageHeader("Admin Interface");
?>

<?php
// LOGIN CHECK
if ($_REQUEST['func'] != "login") {
	$loginuser = $_COOKIE["loginuser"];
	$loginpass = $_COOKIE["loginpass"];

	$login_query = mysqli_query("SELECT * FROM users WHERE username = '" . $loginuser . "' AND password = '" . $loginpass . "';");

	if (mysqli_num_rows($login_query)!="1") {
		header('Location: /admin.php?func=login&sysmsg=invalid');
	} else {
		$loginresult = mysqli_fetch_assoc($login_query);
		$loginlevel = $loginresult['superuser'];
	}
}

echo "<body><table border=\"1\" cellpadding=\"3\" width=\"700\" align=\"center\"><tr><td>"
.    "<center><a href=\"admin.php?func=system\">Edit System Settings</a> | <a href=\"admin.php?func=aircraft\">Edit Aircraft</a> | <a href=\"admin.php?func=users\">Edit Users</a> | "
.    "<a href=\"admin.php?func=audit\">Audit Log</a> | <a href=\"admin.php?func=logout\">Logout</a></center>\n\n";

if ($_REQUEST['sysmsg']=="logout") { echo "<p style=\"color: #00AA00; text-align: center;\">You have been logged out.</p>\n\n";
} elseif ($_REQUEST['sysmsg']=="login") { echo "<p style=\"color: #00AA00; text-align: center;\">You have been logged in.  Select a function above.</p>\n\n";
} elseif ($_REQUEST['sysmsg']=="unauthorized") { echo "<p style=\"color: #00AA00; text-align: center;\">Sorry, you are not allowed to access that module.</p>\n\n";
} elseif ($_REQUEST['sysmsg']=="invalid") { echo "<p style=\"color: #00AA00; text-align: center;\">You have entered an invalid username/password combination.</p>\n\n";
} elseif ($_REQUEST['sysmsg']=="acdeleted") { echo "<p style=\"color: #00AA00; text-align: center;\">The aircraft has been deleted.</p>\n\n"; }

switch ($_REQUEST["func"]) {
    case "login":
    	if ($_REQUEST['username']!="") {
    		// login validation code here - stay logged in for a week
    		setcookie("loginuser", $_REQUEST['username'], time()+604800);
    		setcookie("loginpass", PassEncode($_REQUEST['password']), time()+604800);
    		header('Location: http://' . $_ENV['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?sysmsg=login');
    	} else {
    		// print login form
    		echo "<form method=\"post\" action=\"admin.php\">\n";
    		echo "<input type=\"hidden\" name=\"func\" value=\"login\">\n";
    		echo "<table align=\"center\">\n";
    		echo "<tr><td align=\"right\">Username</td><td><input type=\"text\" name=\"username\"></td></td>\n";
    		echo "<tr><td align=\"right\">Password</td><td><input type=\"password\" name=\"password\"></td><td>\n";
    		echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Login\"></td></tr>\n";
    		echo "</table></form>\n";
    	}
    	break;

    case "logout":
    	setcookie("loginuser", "", time()-3600);
    	setcookie("loginpass", "", time()-3600);
    	header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=login&sysmsg=logout');
    	break;

    case "system":
	if ($loginlevel!="1") {
		header('Location: http://' . $_ENV['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?sysmsg=unauthorized');
	}

        echo "<h2>System Module</h2>";
	if ($_REQUEST['message']=="updated") {echo "<p style=\"color: #00AA00; text-align: center;\">Settings Updated.</p>\n\n";}
    	switch ($_REQUEST["func_do"]) {
    		case "update":
    			// SQL query to update system settings
			foreach ($_POST as $k=>$v) {
				if ($k!="func" && $k!="func_do") {
					$sql_query = "UPDATE configuration SET `value` = '" . $v . "' WHERE `item` = '" . $k . "';";
					mysqli_query($sql_query);
					// Enter audit log
					mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'SYSTEM: " . addslashes($sql_query) . "');");
				}
			}
    			header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=system&message=updated');
    			break;
		default:
        		echo "<p>This module adjusts settings that affect the entire software package.</p>";
        		echo "<form method=\"post\" action=\"admin.php\"><input type=\"hidden\" name=\"func\" value=\"system\"><input type=\"hidden\" name=\"func_do\" value=\"update\">";
	        	echo "<table align=\"center\">"
		        .    "<tr><td align=\"right\">Site/Organization Name</td><td><input type=\"text\" name=\"site_name\" value=\"" . $config['site_name'] . "\"></td></tr>"
		        .    "<tr><td align=\"right\">Administrator E-mail Address</td><td><input type=\"email\" name=\"administrator\" value=\"" . $config['administrator'] . "\"></td></tr>"
		        .    "<tr><td align=\"right\">Local Time Zone</td><td>";
		             TimeZoneList($config['timezone']);
	        	echo "</td></tr>"
		        .    "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Save\"></td></tr></table></form>";
	}
        break;

    case "aircraft":
        echo "<h2>Aircraft Module</h2>";
	switch ($_REQUEST["func_do"]) {
		case "add":
			switch ($_REQUEST["step"]) {
				case "2":
					// SQL query to add a new aircraft
					$sql_query = "INSERT INTO `polaris_wtbal`.`aircraft` (`active`, `tailnumber`, `makemodel`, `emptywt`, `emptycg`, `maxwt`, `cglimits`, `cgwarnfwd`, `cgwarnaft`) VALUES ('0', "
					.           "'" . $_REQUEST['tailnumber'] . "', '" . $_REQUEST['makemodel'] . "', '" . $_REQUEST['emptywt'] . "', '" . $_REQUEST['emptycg'] . "', '" . $_REQUEST['maxwt'] . "', "
					.           "'" . $_REQUEST['cglimits'] . "', '" . $_REQUEST['cgwarnfwd'] . "', '" . $_REQUEST['cgwarnaft'] . "');";
					mysqli_query($sql_query);
					$aircraft_result = mysqli_query("SELECT * FROM aircraft WHERE tailnumber='" . $_REQUEST['tailnumber'] . "'");
					$aircraft = mysqli_fetch_assoc($aircraft_result);
					// Enter in the audit log
					mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
					echo "<p>Aircraft " . $aircraft['tailnumber'] . " added successfully.  Now go to the <a href=\"admin.php?func=aircraft&amp;func_do=edit&amp;tailnumber=" . $aircraft['id'] . "\">aircraft editor</a> to complete the CG envelope and loading zones.</p>\n";
					break;
				default:
					echo "<p>To add a new aircraft, we will first define the basics about the aircraft.</p>\n";
					echo "<p style=\"font-size:11px; font-style:italic\">Default values are included to help you know what to fill in to each field.  When you click in the field, it will be cleared so you can fill in your data. \n";
					echo "Some fields have an underline, if you mouse over them, a help pop-up will appear.</p>\n";
					echo "<form method=\"post\" action=\"admin.php\">\n";
					echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
					echo "<input type=\"hidden\" name=\"func_do\" value=\"add\">\n";
					echo "<input type=\"hidden\" name=\"step\" value=\"2\">\n";
					echo "<table align=\"center\" width=\"100%\" border=\"0\">\n";
					echo "<tr><td align=\"right\" width=\"50%\">Tail Number</td><td width=\"50%\"><input type=\"text\" name=\"tailnumber\" value=\"N123AB\" onfocus=\"javascript:if(this.value=='N123AB') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='N123AB'}\"></td></tr>\n";
					echo "<tr><td align=\"right\">Make and Model</td><td><input type=\"text\" name=\"makemodel\" value=\"Cessna Skyhawk\" onfocus=\"javascript:if(this.value=='Cessna Skyhawk') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='Cessna Skyhawk'}\"></td></tr>\n";
					echo "<tr><td align=\"right\">Empty Weight</td><td><input type=\"number\" step=\"any\" name=\"emptywt\" class=\"numbers\" value=\"1556.3\" onfocus=\"javascript:if(this.value=='1556.3') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='1556.3'}\"></td></tr>\n";
					echo "<tr><td align=\"right\">Empty CG</td><td><input type=\"number\" step=\"any\" name=\"emptycg\" class=\"numbers\" value=\"38.78\" onfocus=\"javascript:if(this.value=='38.78') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='38.78'}\"></td></tr>\n";
					echo "<tr><td align=\"right\">Maximum Gross Weight</td><td><input type=\"number\" step=\"any\" name=\"maxwt\" class=\"numbers\" value=\"2550\" onfocus=\"javascript:if(this.value=='2550') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='2550'}\"></td></tr>\n";
					echo "<tr><td align=\"right\"><abbr title=\"This is a text description of the center of gravity limits, it is not used in part of the validation/warning process.\">Textual CG Limits</abbr></td><td><input type=\"text\" name=\"cglimits\" value=\"FWD 35 @ 1600 - 35 @ 1950 - 39.5 @ 2550, AFT 47.3\" onfocus=\"javascript:if(this.value=='FWD 35 @ 1600 - 35 @ 1950 - 39.5 @ 2550, AFT 47.3') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='FWD 35 @ 1600 - 35 @ 1950 - 39.5 @ 2550, AFT 47.3'}\"></td></tr>\n";
					echo "<tr><td align=\"right\"><abbr title=\"This value will be used to pop up a warning if the calculated CG is less than this value.\">Forward CG Warning</abbr></td><td><input type=\"number\" step=\"any\" name=\"cgwarnfwd\" class=\"numbers\" value=\"35\" onfocus=\"javascript:if(this.value=='35') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='35'}\"></td></tr>\n";
					echo "<tr><td align=\"right\"><abbr title=\"This value will be used to pop up a warning if the calculated CG is greater than this value.\">Aft CG Warning</abbr></td><td><input type=\"number\" step=\"any\" name=\"cgwarnaft\" class=\"numbers\" value=\"47.3\" onfocus=\"javascript:if(this.value=='47.3') {this.value='';}\" onblur=\"javascript:if(this.value=='') {this.value='47.3'}\"></td></tr>\n";
					echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Step 2\"></td></tr>\n";
					echo "</table></form>\n";
			}
			break;
		case "delete":
			if ($_REQUEST['tailnumber']!="") {
				if ($_REQUEST['confirm']=="DELETE FOREVER") {
					$sql_query1 = "DELETE FROM aircraft_cg WHERE `tailnumber` = " . $_REQUEST['tailnumber'] . ";";
					$sql_query2 = "DELETE FROM aircraft_weights WHERE `tailnumber` = " . $_REQUEST['tailnumber'] . ";";
					$sql_query3 = "DELETE FROM aircraft WHERE `id` = " . $_REQUEST['tailnumber'] . ";";
					mysqli_query($sql_query1);
					mysqli_query($sql_query2);
					mysqli_query($sql_query3);
					// Enter in the audit log
					mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'ACDELETE: " . addslashes($sql_query1) . " " . addslashes($sql_query2) . " " . addslashes($sql_query3) . "');");
					header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&sysmsg=acdeleted');
				} else {
					echo "<p>Aircraft NOT deleted.</p><p>In the confirmation box, you must type the words \"DELETE FOREVER\", in all caps.  Use \n"
					.    "your browser's back button to try again.\n";
				}
			} else {
				echo "<p>Aircraft deletion is a permanant action, the aircraft and all of it's associated data will be gone forever.  If you wish to temporarily \n"
				.    "deactivate an aircraft profile, use the <a href=\"admin.php?func=aircraft&func_do=edit\">edit</a> screen.  This is useful for a single "
				.    "aircraft with multiple configurations, ie: wheels/skis/floats.</p>\n\n";
				echo "<div style=\"color:red; font-weight: bold; text-align: center\">*** WARNING: This is permanant, and CANNOT be undone! ***</div>\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"delete\">\n";
				echo "<table align=\"center\" border=\"0\">\n";
				echo "<tr><td align=\"right\">Choose an aircraft to delete:</td><td>\n";
				AircraftListAll();
				echo "</td></tr><tr><td align=\"right\">Type the words \"DELETE FOREVER\":</td><td><input type=\"text\" name=\"confirm\"></td></tr>\n";
				echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Delete\" onClick=\"return window.confirm('Are you REALLY sure you want to PERMANENTLY delete this aircraft?');\"></td></tr>";
				echo "</table></form>\n";
			}
			break;
		case "edit":
			if ($_REQUEST['tailnumber']!="") {
				$aircraft_result = mysqli_query("SELECT * FROM aircraft WHERE id='" . $_REQUEST['tailnumber'] . "'");
				$aircraft = mysqli_fetch_assoc($aircraft_result);

				echo "<p>Editing aircraft " . $aircraft['tailnumber'] . ".</p>\n";

				if ($_REQUEST['message']=="updated") {echo "<p style=\"color: #00AA00; text-align: center;\">Aircraft Updated</p>\n\n";}

				// Aircraft basic information
				echo "<hr noshade/><h3 style=\"text-align: center\">Aircraft Basic Information</h3>\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"id\" value=\"" . $aircraft['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
				echo "<input type=\"hidden\" name=\"what\" value=\"basics\">\n";
				echo "<table align=\"center\" width=\"100%\" border=\"0\">\n";
				echo "<tr><td align=\"right\" width=\"50%\"><abbr title=\"Should this aircraft show up in the list to be able to run weight and balance?\">Active</abbr></td><td width=\"50%\"><input type=\"checkbox\" name=\"active\" value=\"1\"";
					if ($aircraft['active']==1) {echo" checked";}
					echo "></td></tr>\n";
				echo "<tr><td align=\"right\">Tail Number</td><td><input type=\"text\" name=\"tailnumber\" value=\"" . $aircraft['tailnumber'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\">Make and Model</td><td><input type=\"text\" name=\"makemodel\" value=\"" . $aircraft['makemodel'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\">Empty Weight</td><td><input type=\"number\" step=\"any\" name=\"emptywt\" class=\"numbers\" value=\"" . $aircraft['emptywt'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\">Empty CG</td><td><input type=\"number\" step=\"any\" name=\"emptycg\" class=\"numbers\" value=\"" . $aircraft['emptycg'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\">Maximum Gross Weight</td><td><input type=\"number\" step=\"any\" name=\"maxwt\" class=\"numbers\" value=\"" . $aircraft['maxwt'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\"><abbr title=\"This is a text description of the center of gravity limits, it is not used in part of the validation/warning process.\">Textual CG Limits</abbr></td><td><input type=\"text\" name=\"cglimits\" value=\"" . $aircraft['cglimits'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\"><abbr title=\"This value will be used to pop up a warning if the calculated CG is less than this value.\">Forward CG Warning</abbr></td><td><input type=\"number\" step=\"any\" name=\"cgwarnfwd\" class=\"numbers\" value=\"" . $aircraft['cgwarnfwd'] . "\"></td></tr>\n";
				echo "<tr><td align=\"right\"><abbr title=\"This value will be used to pop up a warning if the calculated CG is greater than this value.\">Aft CG Warning</abbr></td><td><input type=\"number\" step=\"any\" name=\"cgwarnaft\" class=\"numbers\" value=\"" . $aircraft['cgwarnaft'] . "\"></td></tr>\n";
				echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Edit\"></td></tr>\n";
				echo "</table></form><hr noshade/>\n\n";

				// Aicraft CG envelope
				echo "<h3 style=\"text-align: center\">Center of Gravity Envelope</h3>\n";
				echo "<p style=\"text-align: center; font-size: 12px\">Enter the data points for the CG envelope.  It does not matter which point you start with or if you go clockwise or counter-clockwise, but they must be entered in order.  "
				.    "The last point will automatically be connected back to the first.  The graph below will update as you go.</p>\n";
				$cg_result = mysqli_query("SELECT * FROM aircraft_cg WHERE tailnumber=" . $aircraft['id']);
				echo "<form method=\"post\" action=\"admin.php\" name=\"cg\">\n";
				echo "<input type=\"hidden\" name=\"tailnumber\" value=\"" . $aircraft['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
				echo "<input type=\"hidden\" name=\"what\" value=\"cg\">\n";
				echo "<table align=\"center\" border=\"0\">\n";
				echo "<tr><th>Arm</th><th>Weight</th><th>&nbsp;</th></tr>\n";
				while($cg = mysqli_fetch_assoc($cg_result)) {
					echo "<tr><td align=\"center\"><input type=\"number\" step=\"any\" name=\"cgarm" . $cg['id'] . "\" value=\"" . $cg['arm'] . "\" class=\"numbers\"></td>\n"
					.    "<td align=\"center\"><input type=\"number\" step=\"any\" name=\"cgweight" . $cg['id'] . "\" value=\"" . $cg['weight'] . "\" class=\"numbers\"></td><td>\n"
					.    "<input type=\"button\" value=\"Edit\" onClick=\"parent.location='http://" . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=cg&amp;id=" . $cg['id'] . "&amp;cgarm=' + document.cg.cgarm" . $cg['id'] . ".value + '&amp;cgweight=' + document.cg.cgweight" . $cg['id'] . ".value + '&amp;tailnumber=" . $aircraft['id'] . "'\">\n"
					.    "<input type=\"button\" value=\"Delete\" onClick=\"parent.location='http://" . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=cg_del&amp;id=" . $cg['id'] . "&amp;tailnumber=" . $aircraft['id'] . "'\"></td></tr>\n";
				}
				echo "<tr><td align=\"center\"><input type=\"number\" step=\"any\" name=\"new_arm\" class=\"numbers\"></td><td align=\"center\"><input type=\"number\" step=\"any\" name=\"new_weight\" class=\"numbers\"></td><td align=\"center\"><input type=\"submit\" value=\"Add\"></td></tr>\n";
				echo "</table></form>\n";
				echo "<center><img src=\"scatter.php?size=small&amp;tailnumber=" . $aircraft['id'] . "\"></center><hr noshade/>\n\n";

				// Aicraft loading zones
				echo "<h3 style=\"text-align: center\">Loading Zones</h3>\n";
				echo "<p style=\"text-align: center; font-size: 12px\">Enter the data for each reference datum.  A description of what should be entered in each field is available by hovering over the column name.</p>\n";
				$weights_result = mysqli_query("SELECT * FROM aircraft_weights WHERE tailnumber = " . $aircraft['id'] . " ORDER BY  `aircraft_weights`.`order` ASC");
				echo "<form method=\"post\" action=\"admin.php\" name=\"loading\">\n";
				echo "<input type=\"hidden\" name=\"tailnumber\" value=\"" . $aircraft['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
				echo "<input type=\"hidden\" name=\"what\" value=\"loading\">\n";
				echo "<table align=\"center\" border=\"0\">\n";
				echo "<tr><th><abbr title=\"This is a number which determines the vertical listing order of the row.\">Order</abbr></th><th><abbr title=\"A short textual description of the row.\">Item</abbr></th>"
				.    "<th><abbr title=\"Checking this box will cause the weight column to be locked on the spreadsheet so it cannot be changed.\">Empty Weight</abbr></th>"
				.    "<th><abbr title=\"Checking this box causes the spreadsheet to take it's entry in gallons and automatically compute the weight.\">Fuel</abbr></th>"
				.    "<th><abbr title=\"If this row is used for fuel, specify how much a gallon weighs (ie: 6 for AVGAS)\">Fuel Unit Weight</abbr></th>"
				.    "<th><abbr title=\"The default weight to be used for a row.  If this is a fuel row, the default number of gallons.\">Weight or Gallons</abbr></th>"
				.    "<th><abbr title=\"The number of inches from the reference datum for the row.\">Arm</abbr></th><th width=\"100\">&nbsp;</th></tr>\n";
				while ($weights = mysqli_fetch_assoc($weights_result)) {
					echo "<tr><td align=\"center\"><input type=\"number\" name=\"order" . $weights['id'] . "\" value=\"" . $weights['order'] . "\" class=\"numbers\" style=\"width: 35px;\"></td>\n"
					.    "<td align=\"center\"><input type=\"text\" name=\"item" . $weights['id'] . "\" value=\"" . $weights['item'] . "\" style=\"width: 125px;\"></td>\n"
					.    "<td align=\"center\"><input type=\"checkbox\" name=\"emptyweight" . $weights['id'] . "\" value=\"true\"";
						if ($weights['emptyweight']=="true") { echo(" checked"); }
					echo "></td>\n<td align=\"center\"><input type=\"checkbox\" name=\"fuel" . $weights['id'] . "\" value=\"true\" onclick=\"if(document.loading.fuel" . $weights['id'] . ".checked==false) {document.loading.fuelwt" . $weights['id'] . ".disabled=true;} else {document.loading.fuelwt" . $weights['id'] . ".disabled=false;}\"";
						if ($weights['fuel']=="true") { echo(" checked"); }
					echo "></td>\n<td align=\"center\"><input type=\"number\" step=\"any\" name=\"fuelwt" . $weights['id'] . "\" value=\"" . $weights['fuelwt'] . "\" class=\"numbers\"";
						if ($weights['fuel']=="false") { echo(" disabled"); }
					echo "></td>\n<td align=\"center\"><input type=\"number\" step=\"any\" name=\"weight" . $weights['id'] . "\" value=\"" . $weights['weight'] . "\" class=\"numbers\"></td>\n"
					.    "<td align=\"center\"><input type=\"number\" step=\"any\" name=\"arm" . $weights['id'] . "\" value=\"" . $weights['arm'] . "\" class=\"numbers\"></td>\n"
					.    "<td align=\"center\"><input type=\"button\" value=\"Edit\" onClick=\"parent.location='http://" . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=loading&amp;id=" . $weights['id'] . "&amp;"
					.    "order=' + document.loading.order" . $weights['id'] . ".value + '&amp;item=' + document.loading.item" . $weights['id'] . ".value + '&amp;emptyweight=' + document.loading.emptyweight" . $weights['id'] . ".checked + '&amp;"
					.    "fuel=' + document.loading.fuel" . $weights['id'] . ".checked + '&amp;fuelwt=' + document.loading.fuelwt" . $weights['id'] . ".value + '&amp;weight=' + document.loading.weight" . $weights['id'] . ".value + '&amp;"
					.    "arm=' + document.loading.arm" . $weights['id'] . ".value + '&amp;tailnumber=" . $aircraft['id'] . "'\">\n"
					.    "<input type=\"button\" value=\"Delete\" onClick=\"parent.location='http://" . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=loading_del&amp;id=" . $weights['id'] . "&amp;tailnumber=" . $aircraft['id'] . "'\"></td></tr>\n\n";
				}
				echo "<tr><td align=\"center\"><input type=\"number\" step=\"any\" name=\"new_order\" class=\"numbers\" style=\"width: 35px;\"></td><td align=\"center\"><input type=\"text\" name=\"new_item\" style=\"width: 125px;\"></td><td align=\"center\"><input type=\"checkbox\" name=\"new_emptyweight\" value=\"true\"></td>"
				.    "<td align=\"center\"><input type=\"checkbox\" name=\"new_fuel\" value=\"true\" onclick=\"if(document.loading.new_fuel.checked==false) {document.loading.new_fuelwt.disabled=true;} else {document.loading.new_fuelwt.disabled=false;}\"></td>"
				.    "<td align=\"center\"><input type=\"number\" step=\"any\" name=\"new_fuelwt\" class=\"numbers\" disabled></td>"
				.    "<td align=\"center\"><input type=\"number\" step=\"any\" name=\"new_weight\" class=\"numbers\"></td><td align=\"center\"><input type=\"number\" step=\"any\" name=\"new_arm\" class=\"numbers\"></td><td align=\"center\"><input type=\"submit\" value=\"Add\"></td></tr>\n";
				echo "</table></form>\n\n";

			} else {
				echo "<p>Choose an aircraft to modify.</p>\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit\">\n";
				AircraftListAll();
				echo "<input type=\"submit\" value=\"Edit\"></form>\n\n";
			}
			break;
		case "edit_do":
			switch ($_REQUEST["what"]) {
				case "basics":
					// SQL query to edit basic aircraft information
					$sql_query = "UPDATE aircraft SET active = '" . $_REQUEST['active'] . "', tailnumber = '" . $_REQUEST['tailnumber'] . "', makemodel = '"
					. $_REQUEST['makemodel'] . "', emptywt = '" . $_REQUEST['emptywt'] . "', emptycg = '" . $_REQUEST['emptycg'] . "', maxwt = '" . $_REQUEST['maxwt']
					. "', cglimits = '" . $_REQUEST['cglimits'] . "', cgwarnfwd = '" . $_REQUEST['cgwarnfwd'] . "', cgwarnaft = '" . $_REQUEST['cgwarnaft'] . "' WHERE id = "
					. $_REQUEST['id'];
					mysqli_query($sql_query);
					// Enter in the audit log
					mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $_REQUEST['tailnumber'] . ": " . addslashes($sql_query) . "');");
					header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['id'] . '&message=updated');
					break;
				case "cg":
					if ($_REQUEST['new_arm'] != "" && $_REQUEST['new_weight'] != "") {
						// SQL query to add a new CG line
						$sql_query = "INSERT INTO aircraft_cg (`id`, `tailnumber`, `arm`, `weight`) VALUES (NULL, '" . $_REQUEST['tailnumber'] . "', '" . $_REQUEST['new_arm'] . "', '" . $_REQUEST['new_weight'] . "');";
						mysqli_query($sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query("SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					} else {
						// SQL query to edit CG information
						$sql_query = "UPDATE aircraft_cg SET arm = '" . $_REQUEST['cgarm'] . "', weight = '" . $_REQUEST['cgweight'] . "' WHERE id = '" . $_REQUEST['id'] . "';";
						mysqli_query($sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query("SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					}
					break;
				case "cg_del":
					// SQL query to delete CG information
					$sql_query = "DELETE FROM aircraft_cg WHERE id = " . $_REQUEST['id'];
					mysqli_query($sql_query);
					// Enter in the audit log
					$aircraft_query = mysqli_query("SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
					$aircraft = mysqli_fetch_assoc($aircraft_query);
					mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
					header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
				case "loading":
					if ($_REQUEST['new_item'] && $_REQUEST['new_arm'] != "") {
						// SQL query to add a new loading line
						$sql_query = "INSERT INTO aircraft_weights (`id`, `tailnumber`, `order`, `item`, `weight`, `arm`, `fuelwt`";
						if ($_REQUEST['new_fuel']=="true") { $sql_query = $sql_query . ", `fuel`"; }
						if ($_REQUEST['new_emptyweight']=="true") { $sql_query = $sql_query . ", `emptyweight`"; }
						$sql_query = $sql_query . ") VALUES (NULL, '" . $_REQUEST['tailnumber'] . "', '" . $_REQUEST['new_order'] . "', '" . $_REQUEST['new_item'] . "', '" . $_REQUEST['new_weight'] . "', '" . $_REQUEST['new_arm']
						.    "', '" . $_REQUEST['new_fuelwt'] . "'";
						if ($_REQUEST['new_fuel']=="true") { $sql_query = $sql_query . ", 'true'"; }
						if ($_REQUEST['new_emptyweight']=="true") { $sql_query = $sql_query . ", 'true'"; }
						$sql_query = $sql_query . ");";
						mysqli_query($sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query("SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					} else {
						// SQL query to edit loading zones
						$sql_query = "UPDATE aircraft_weights SET `order` = '" . $_REQUEST['order'] . "', `item` = '" . $_REQUEST['item'] . "', `weight` = '" . $_REQUEST['weight'] . "', `arm` = '" . $_REQUEST['arm']
						.            "', `emptyweight` = '" . $_REQUEST['emptyweight'] . "', `fuel` = '" . $_REQUEST['fuel'] . "', `fuelwt` = '" . $_REQUEST['fuelwt'] . "' WHERE id = '" . $_REQUEST['id'] . "';";
						mysqli_query($sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query("SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					}
					break;
				case "loading_del":
					// SQL query to delete loading information
					$sql_query = "DELETE FROM aircraft_weights WHERE id = " . $_REQUEST['id'];
					mysqli_query($sql_query);
					// Enter in the audit log
					$aircraft_query = mysqli_query("SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
					$aircraft = mysqli_fetch_assoc($aircraft_query);
					mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
					header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
			}

			break;
		default:
		        echo "<p>This module edits aircraft weight and balance templates.</p>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=add\">Add Aicraft</a><br>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=delete\">Delete Aicraft</a><br>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=edit\">Edit Aicraft</a>\n";
	}
        break;

    case "users":
        echo "<h2>Users Module</h2>";
        if ($_REQUEST['message']=="added") { echo "<p style=\"color: #00AA00; text-align: center;\">User account added.</p>\n\n";
        } elseif ($_REQUEST['message']=="edited") { echo "<p style=\"color: #00AA00; text-align: center;\">User account edited.</p>\n\n";
        } elseif ($_REQUEST['message']=="deleted") { echo "<p style=\"color: #00AA00; text-align: center;\">User account deleted.</p>\n\n"; }
	switch ($_REQUEST["func_do"]) {
		case "add":
			if ($_REQUEST['what']=="Add") {
				// SQL query to add a new user
				$sql_query = "INSERT INTO users (`username`, `password`, `name`, `email`, `superuser`) "
				.            "VALUES ('" . $_REQUEST['username'] . "', '" . $_REQUEST['password'] . "', '" . $_REQUEST['name'] . "', '" . $_REQUEST['email'] . "', '" . $_REQUEST['superuser'] . "');";
				mysqli_query($sql_query);
				// Enter in the audit log
				mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'USERS: " . addslashes($sql_query) . "');");
				header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=added');
			} else {
				echo "<form method=\"post\" action=\"admin.php\">\n"
				.    "<input type=\"hidden\" name=\"func\" value=\"users\">\n"
				.    "<input type=\"hidden\" name=\"func_do\" value=\"add\">\n"
				.    "<table align=\"center\" width=\"100%\" border=\"0\">\n"
				.    "<tr><td align=\"right\" width=\"50%\">Username</td><td width=\"50%\"><input type=\"text\" name=\"username\"></td></tr>\n"
				.    "<tr><td align=\"right\">Password</td><td><input type=\"password\" name=\"password\"></td></tr>\n"
				.    "<tr><td align=\"right\">Name</td><td><input type=\"text\" name=\"name\"></td></tr>\n"
				.    "<tr><td align=\"right\">E-mail</td><td><input type=\"email\" name=\"email\"></td></tr>\n"
				.    "<tr><td align=\"right\"><abbr title=\"Administrative users can edit system settings, all users, and view the audit log.\">Admin User</abbr></td><td><input type=\"checkbox\" name=\"superuser\" value=\"1\"></td></tr>\n"
				.    "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"what\" value=\"Add\"></td></tr>\n"
				.    "</table></form>\n";
			}
			break;
		case "edit":
			if ($_REQUEST['what']=="Edit") {
				// SQL query to edit a user
				$sql_query = "UPDATE users SET `username` = '" . $_REQUEST['username'] . "', ";
				if ($_REQUEST['password']!="") { $sql_query = $sql_query . "`password` = '" . PassEncode($_REQUEST['password']) . "', "; }
				$sql_query = $sql_query . "`name` = '" . $_REQUEST['name'] . "', `email` = '" . $_REQUEST['email'] . "', `superuser` = '" . $_REQUEST['superuser']
				. "' WHERE id = '" . $_REQUEST['id'] . "';";
				mysqli_query($sql_query);
				// Enter in the audit log
				mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'USERS: " . addslashes($sql_query) . "');");
				header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=edited');
			} elseif ($_REQUEST['what']=="Delete") {
				$sql_query = "DELETE FROM users WHERE id = '" . $_REQUEST['id'] . "';";
				mysqli_query($sql_query);
				// Enter in the audit log
				mysqli_query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'USERS: " . addslashes($sql_query) . "');");
				header('Location: http://' . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=deleted');
			} else {
				$result = mysqli_query("SELECT * FROM users WHERE id = " . $_REQUEST['id']);
				$row = mysqli_fetch_assoc($result);
				echo "<form method=\"post\" action=\"admin.php\">\n"
				.    "<input type=\"hidden\" name=\"id\" value=\"" . $row['id'] . "\">\n"
				.    "<input type=\"hidden\" name=\"func\" value=\"users\">\n"
				.    "<input type=\"hidden\" name=\"func_do\" value=\"edit\">\n"
				.    "<table align=\"center\" width=\"100%\" border=\"0\">\n"
				.    "<tr><td align=\"right\" width=\"50%\">Username</td><td width=\"50%\"><input type=\"text\" name=\"username\" value=\"" . $row['username'] . "\"></td></tr>\n"
				.    "<tr><td align=\"right\">Password</td><td><input type=\"password\" name=\"password\"></td></tr>\n"
				.    "<tr><td align=\"right\">Name</td><td><input type=\"text\" name=\"name\" value=\"" . $row['name'] . "\"></td></tr>\n"
				.    "<tr><td align=\"right\">E-mail</td><td><input type=\"email\" name=\"email\" value=\"" . $row['email'] . "\"></td></tr>\n"
				.    "<tr><td align=\"right\"><abbr title=\"Administrative users can edit system settings, all users, and view the audit log.\">Admin User</abbr></td><td><input type=\"checkbox\" name=\"superuser\" value=\"1\"";
				if ($row['superuser']=="1") { echo " checked"; }
				echo "></td></tr>\n"
				.    "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"what\" value=\"Edit\">"
				.    "<input type=\"submit\" name=\"what\" value=\"Delete\" onClick=\"return window.confirm('Are you REALLY sure you want to PERMANENTLY delete this account?');\"></td></tr>\n"
				.    "</table></form>\n";
			}
			break;
		default:
		        echo "<p>This module edits system users.</p>\n";
		        echo "<form method=\"post\" action=\"admin.php\">\n";
		        echo "<input type=\"hidden\" name=\"func\" value=\"users\">\n";
		        echo "<input type=\"hidden\" name=\"func_do\" value=\"add\">\n";
			echo "<table align=\"center\" border=\"1\">\n";
			echo "<tr><th>Username</th><th>Name</th><th>Admin</th><th>&nbsp;</th></tr>\n";
			$result = mysqli_query("SELECT * FROM users ORDER BY `name`");
			while($row = mysqli_fetch_array($result)) {
				echo "<tr><td>" . $row['username'] . "</td><td>" . $row['name'] . "</td><td>";
				if ($row['superuser']=="1") { echo "Yes"; } else { echo "No"; }
				echo "</td><td>\n";
				if ($loginuser==$row['username'] || $loginlevel=="1") {
					echo "<input type=\"button\" name=\"edit\" value=\"Edit\" onClick=\"parent.location='http://" . $_ENV["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=users&amp;func_do=edit&amp;id=" . $row['id'] . "'\">\n";
				} else { echo "&nbsp;"; }
				echo "</td></tr>\n";
			}
			echo "</table></form>\n";
			if ($loginlevel=="1") {
				echo "<center><a href=\"admin.php?func=users&amp;func_do=add\">Add New User</a></center>\n";
			}
	}
        break;

    case "audit":
	if ($loginlevel!="1") {
		header('Location: http://' . $_ENV['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?sysmsg=unauthorized');
	}

    	echo "<h2>Audit Log Module</h2>";
    	echo "<div style=\"font-family: courier; font-size: 10px;\">";
    	if ($_REQUEST['offset']=="") { $lower=0;
    	} else { $lower=$_REQUEST['offset']; } $upper=($lower+100);
    	$result = mysqli_query("SELECT * FROM audit ORDER BY timestamp DESC LIMIT " . $lower . " , " . $upper);
    	$rowcount = mysqli_num_rows(mysqli_query("SELECT * FROM audit"));
    	while ($row = mysqli_fetch_array($result)) {
    		echo $row['timestamp'] . " | " . $row['who'] . " | " . $row['what'] . "<br><br>\n";
    	}
    	echo "</div><center>";
    	if ($lower!=0) {
	    	echo "<a href=\"admin.php?func=audit&amp;offset=" . ($lower-100) . "\">Previous Page</a> ";
    	}
    	if ($rowcount>($upper+1)) {
    		echo " <a href=\"admin.php?func=audit&amp;offset=" . ($upper) . "\">Next Page</a>";
    	}
    	echo "</center>\n\n";
    	break;

}

echo "</td></tr></table>";
?>

<?php PageFooter($config['administrator'],$ver); ?>