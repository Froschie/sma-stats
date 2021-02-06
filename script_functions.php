<?php

// output function
function output($script_onlychart, $script_onlytable, $div, $html_script, $html_table) {
    if ($script_onlychart) {
        if ($div != "") {
            print("  <div id=\"".$div."\" style=\"width: 650px; height: 320px\"></div>\n".$html_script);
        } else {
            print($html_script);
        }
    } elseif ($script_onlytable) {
        print($html_table.$html_script."  <br>\n");
    } else {
        $tr1 = strpos($html_table, "</tr>");
        $tr2 = strpos($html_table, "</tr>", $tr1 + 5);
        $chart_row = "";
        if ($div != "") {
            $chart_row = "  <td rowspan=\"1000\" style=\"vertical-align: top\">
            <div id=\"".$div."\" style=\"width: 650px; height: 320px\"></div>
        </td>\n    ";
        }
        $html_table = substr_replace($html_table, $chart_row, $tr2, 0);
        print($html_table.$html_script."  <br>\n");
    }
}

// query first entry in database
$year_first_var = 0;
if (getenv('startyear') == "actual") {
    $year_first_var = date("Y");
} elseif (getenv('startyear') > 0) {
    $year_first_var = getenv('startyear');
}
if (isset($_GET['startyear'])) {
    if ($_GET['startyear'] == "actual") {
        $year_first_var = date("Y");
    } elseif ($_GET['startyear'] > 0) {
        $year_first_var = $_GET['startyear'];
    }
}
$year_first = $year_first_var;
$result = $database->query('SELECT first(solar_total) FROM totals tz(\'Europe/Berlin\')');
$points = $result->getPoints();
if (isset($points[0])) {
    $f_year = explode("-", $points[0]['time'])[0];
    if ($f_year > $year_first_var) {
        $year_first = $f_year;
    }
    $month_first = explode("-", $points[0]['time'])[1];
}
$result = $database->query('SELECT last(solar_total) FROM totals tz(\'Europe/Berlin\')');
$points = $result->getPoints();
if (isset($points[0])) {
    $l_year = explode("-", $points[0]['time'])[0];
    if ($l_year < $year_first_var) {
        $year_first = $f_year;
    }
}

?>