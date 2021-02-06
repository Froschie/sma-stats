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

?>