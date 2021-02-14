<?php

// actual dates
$year_act = date("Y");
$month_act = date("m");

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

// function to query first / last year
function inf_query_year($query) {
    global $database;
    $result = $database->query($query);
    $points = $result->getPoints();
    if (isset($points[0])) {
        return explode("-", $points[0]['time'])[0];
    }
}

// check first year for query
function year_first($f_year, $l_year) {
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
    if ($f_year > $year_first_var) {
        $year_first = $f_year;
    }
    if ($l_year < $year_first_var) {
        $year_first = $f_year;
    }
    return $year_first;
}

// check last year for query
function year_act($year_act, $f_year, $l_year) {
    $year_last_var = $year_act;
    if (getenv('endyear') == "actual") {
        $year_last_var = date("Y");
    } elseif (getenv('endyear') > 0) {
        $year_last_var = getenv('lastyear');
    }
    if (isset($_GET['endyear'])) {
        if ($_GET['endyear'] == "actual") {
            $year_last_var = date("Y");
        } elseif ($_GET['endyear'] > 0) {
            $year_last_var = $_GET['endyear'];
        }
    }
    $year_act = $year_last_var;
    if ($f_year > $year_act) {
        $year_act = $f_year;
    }
    if ($l_year < $year_last_var) {
        $year_act = $f_year;
    }
    return $year_act;
}

// variable check type bool
function check_input_bool($env_variable, $get_variable, $default) {
    $value = $default;
    switch(getenv($env_variable)) {
    case "yes":
        $value = TRUE;
    }
    if (isset($_GET[$get_variable])) {
        if ($_GET[$get_variable] != "no") {
            $value = TRUE;
        } else {
            $value = FALSE;
        }
    }
    return $value;
}

// variable check type int
function check_input_int($env_variable, $get_variable, $default) {
    $value = $default;
    if (getenv($env_variable) > 0) {
        $value = getenv($env_variable);
    }
    if (isset($_GET[$get_variable])) {
        if ($_GET[$get_variable] >= 0) {
            $value = $_GET[$get_variable];
        }
    }
    return $value;
}

// table border html code
function table_border_code($border) {
    if ($border) {
        return "\n    table, th, td {
          border: 1px solid black;
        }";
    } else {
        return "";
    }
}
?>