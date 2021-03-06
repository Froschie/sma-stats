<?php
// debug execution time
if (isset($_GET['timing'])) {
    $script_timing = TRUE;
    $script_time_start = hrtime(true);
} else {
    $script_timing = FALSE;
}

// Load Electric Meter InfluxDB Parameter
$influx_em_ip = getenv('emdb_ip');
$influx_em_port = getenv('emdb_port');
$influx_em_db = getenv('emdb_db');
$influx_em_user = getenv('emdb_user');
$influx_em_pw = getenv('emdb_pw');
if ($influx_em_ip == "192.168.1.3" && $influx_em_db == "measurements" && $influx_em_port == "8086" && $influx_em_user == "user" && $influx_em_pw == "pw") {
    exit("Missing SMA InfluxDB Parameters! Default values used!");
}

// Load SMA InfluxDB Parameter
$influx_sma_ip = getenv('smadb_ip');
$influx_sma_port = getenv('smadb_port');
$influx_sma_db = getenv('smadb_db');
$influx_sma_user = getenv('smadb_user');
$influx_sma_pw = getenv('smadb_pw');

// load php influxdb plugin
require __DIR__ . '/vendor/autoload.php';
// connect to Electric Meter InfluxDB
$client_em = new InfluxDB\Client($influx_em_ip, $influx_em_port, $influx_em_user, $influx_em_pw);
$database = $client_em->selectDB($influx_em_db);
// connect to SMA InfluxDB
if ($influx_sma_ip == "192.168.1.3" && $influx_sma_db == "SMA" && $influx_sma_port == "8086" && $influx_sma_user == "user" && $influx_sma_pw == "pw") {
    $sma_query = FALSE;
} else {
    $client_sma = new InfluxDB\Client($influx_sma_ip, $influx_sma_port, $influx_sma_user, $influx_sma_pw);
    $database_sma = $client_sma->selectDB($influx_sma_db);
    $sma_query = TRUE;    
}

// load function file
require __DIR__ . '/script_functions.php';

// language definition and value check
$dict['de'] = array(1 => 'Jahr', 2 => 'Netzbezug', 3 => 'Einspeisung', 4 => 'Verbrauch', 5 => 'Solar', 6 => 'Werte pro Jahr', 7 => 'Grafik', 8 => 'EM', 9 => 'SMA', 10 => 'Eigen- verbrauch', 11 => 'Eigen- verbrauchsquote', 12 => 'Autarkie- grad', 13 => 'Eigenverbrauch', 14 => 'Generierungzeit Jahres Tabelle', 15 => 'Generierungzeit Monats Tabelle', 16 => 'Gesamt Generierungzeit', 17 => 'Monat', 18 => 'Zähler Stand');
$dict['en'] = array(1 => 'Year', 2 => 'Grid', 3 => 'Supply', 4 => 'Consumption', 5 => 'Solar', 6 => 'Values per Year', 7 => 'Chart', 8 => 'EM', 9 => 'SMA', 10 => 'Own Consumption', 11 => 'Self Consumption', 12 => 'Self Sufficiency', 13 => 'Own Consumption', 14 => 'Year Table Generation Time', 15 => 'Month Table Generation Time', 16 => 'Total Generation Time', 17 => 'Month', 18 => 'Meter Value');
switch(getenv('lang')) {
    case "en":
        $script_lang = "en";
        break;
    default:
        $script_lang = "de";
}
if (isset($_GET['lang'])) {
    switch($_GET['lang']) {
        case "en":
            $script_lang = "en";
            break;
        default:
            $script_lang = "de";
    }
}

// function to return the correct translation
function t($id) {
    global $dict;
    global $script_lang;
    return $dict[$script_lang][$id];
}

// function to replace "." with "," for german output
function d($value) {
    global $script_lang;
    if ($script_lang == "de") {
        $value = str_replace(".", ",", $value);
    }
    return $value;
}

// check for dark mode
$return_array = dark_mode("emdark", "dark", FALSE);
$script_darkmode = $return_array[0];
$color_chart = $return_array[1];
$color_text = $return_array[2];
$color_bg = $return_array[3];

// table border value check
$table_border = table_border_code(check_input_bool("table_borders", "table_borders", TRUE), $script_darkmode);

// chart value check
$script_chart = getenv('chart');
if (isset($_GET['chart'])) {
    $script_chart = $_GET['chart'];
}

// only chart output value check
if (isset($_GET['onlychart'])) {
    $script_onlychart = TRUE;
} else {
    $script_onlychart = FALSE;
}

// html header
print("<!DOCTYPE html>
<html>
<head>
  <title>sma-stats - Electric Meter Statistics</title>
  <style>
    td {
      text-align: right;
    }".$table_border."
    th, td {
      padding: 3px;
    }
  </style>
</head>
<body text=\"#".$color_text."\" bgcolor=\"#".$color_bg."\">
  <script src=\"echarts.js\"></script>\n");

// set first and last year for query
$f_year = inf_query_year('SELECT first(consumption) FROM electric_meter tz(\'Europe/Berlin\')');
$l_year = inf_query_year('SELECT last(consumption) FROM electric_meter tz(\'Europe/Berlin\')');
$year_first = year_first($f_year, $l_year);
$year_act = year_act($year_act, $f_year, $l_year);

// year chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'year') !== false) {
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for year chart
        $year_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $year_chart = "";
        $year_table = "";
        $year_array = array();
        $year_grid_em = array();
        $year_supply_em = array();
        $year_solar_sma = array();
        $year_consumption_sma = array();
        $year_own_consumption = array();
        // loop for first to actual year
        while ($year <= $year_act) {
            // define start and end time of the year to query
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // Electric Meter InfluxDB query
            $result_em = $database->query('SELECT spread(consumption) AS grid, spread(supply) AS supply FROM electric_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
            $points_em = $result_em->getPoints();
            // extract queried values
            $grid_em = round($points_em[0]['grid']/1000, 0);
            $supply_em = round($points_em[0]['supply']/1000, 0);
            // save values into array for chart
            $year_array[] = strval($year);
            $year_grid_em[] = $grid_em;
            $year_supply_em[] = $supply_em;
            // SMA InfluxDB query
            if ($sma_query) {
                $result_sma = $database_sma->query('SELECT sum(solar_daily) AS solar, sum(bezug_daily) AS grid, sum(consumption_daily) AS consumption, sum(einspeisung_daily) AS supply FROM totals_daily WHERE time >='.$start_time.'s and time<'.$end_time.'s tz(\'Europe/Berlin\')');
                $points_sma = $result_sma->getPoints();
                // save values into array for chart
                $solar_sma = round($points_sma[0]['solar']/1000, 0);
                $grid_sma = round($points_sma[0]['grid']/1000, 0);
                $consumption_sma = round($points_sma[0]['consumption']/1000, 0);
                $supply_sma = round($points_sma[0]['supply']/1000, 0);
                $year_solar_sma[] = $solar_sma;
                $year_consumption_sma[] = $consumption_sma;
                $own_consumption = $solar_sma-$supply_sma;
                $year_own_consumption[] = $own_consumption;
                $self_consumption = round(($own_consumption/$solar_sma)*100, 0);
                $self_sufficiency = round(($own_consumption/$consumption_sma)*100, 0);
                $year_table_temp_sma = "      <td>".$solar_sma." kWh</td>
      <td>".$grid_sma." kWh</td>
      <td>".$consumption_sma." kWh</td>
      <td>".$supply_sma." kWh</td>
      <td>".$own_consumption." kWh</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>\n";
            }
            // generate table rows
            $year_table = "    <tr>
      <td>".$year."</td>
      <td>".$grid_em." kWh</td>
      <td>".$supply_em." kWh</td>\n".$year_table_temp_sma."    </tr>\n".$year_table;
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $year_html_table = "  <table>
    <tr>
      <th style=\"width: 6%\">".t(1)."</th>
      <th style=\"width: 6%\">".t(2)." (".t(8).")</th>
      <th style=\"width: 6%\">".t(3)." (".t(8).")</th>\n";
    if ($sma_query) {
        $year_html_table = $year_html_table."      <th style=\"width: 6%\">".t(5)." (".t(9).")</th>
      <th style=\"width: 6%\">".t(2)." (".t(9).")</th>
      <th style=\"width: 6%\">".t(4)." (".t(9).")</th>
      <th style=\"width: 6%\">".t(3)." (".t(9).")</th>
      <th style=\"width: 6%\">".t(10)." (".t(9).")</th>
      <th style=\"width: 6%\">".t(11)." (".t(9).")</th>
      <th style=\"width: 6%\">".t(12)." (".t(9).")</th>\n";
        $chart_solar_sma = ",
        {
          name: '".t(5)." kWh',
          type: 'bar',
          barGap: 0,
          emphasis: {
            focus: 'series'
          },
          itemStyle: {
            color: '#ffff00'
          },
          data: ".json_encode(array_values($year_solar_sma))."
        }\n";
        $chart_consumption_sma = ",
        {
          name: '".t(4)." kWh',
          type: 'bar',
          barGap: 0,
          emphasis: {
            focus: 'series'
          },
          itemStyle: {
            color: '#ffaa00'
          },
          data: ".json_encode(array_values($year_consumption_sma))."
        },
        {
          name: '".t(13)." kWh',
          type: 'bar',
          barGap: 0,
          emphasis: {
            focus: 'series'
          },
          itemStyle: {
            color: '#ffcc00'
          },
          data: ".json_encode(array_values($year_own_consumption))."
        }";
    }
    $year_html_table = $year_html_table."      <th style=\"width: 40%\">".t(7)."</th>
    </tr>\n".$year_table."  </table>\n";
    $year_html_script = "  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('chart_years')".$color_chart.");
    var option = {
      title: {
        text: '".t(6)."',
        textStyle: {
          fontSize: 14
        },
        left: 'center'
      },
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var tooltipString = params[0].axisValue
          params.forEach(function (item, index) {
            tooltipString = `\${tooltipString}<br /><span style=\"float: left;\">\${item.marker} \${item.seriesName}:</span>&emsp;<span style=\"float: right;\">\${item.value}</span>`
          });
          return tooltipString;
        },
        axisPointer: {
          animation: false
        }
      },
      grid: {
        top: '35px',
        left: '5px',
        right: '5px',
        bottom: '5px',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: ".json_encode(array_values($year_array))."
      },
      yAxis: {
        type: 'value'
      },
      series: [
        {
          name: '".t(2)." kWh',
          type: 'bar',
          barGap: 0,
          emphasis: {
            focus: 'series'
          },
          itemStyle: {
            color: '#ff2200'
          },
          data: ".json_encode(array_values($year_grid_em))."
        }".$chart_consumption_sma.",
        {
          name: '".t(3)." kWh',
          type: 'bar',
          barGap: 0,
          emphasis: {
            focus: 'series'
          },
          itemStyle: {
            color: '#22ff00'
          },
          data: ".json_encode(array_values($year_supply_em))."
        }".$chart_solar_sma."
      ]
    };
    myChart.setOption(option);
  </script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"chart_years\" style=\"width: 650px; height: 320px\"></div>\n".$year_html_script);
    } else {
        $tr1 = strpos($year_html_table, "</tr>");
        $tr2 = strpos($year_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"100\" style=\"vertical-align: top\">
        <div id=\"chart_years\" style=\"width: 650px; height: 320px\"></div>
      </td>\n    ";
        $year_html_table = substr_replace($year_html_table, $chart_row, $tr2, 0);
        print($year_html_table.$year_html_script."  <br>\n");
    }
    // end debug timing for year chart
    $year_time_end = hrtime(true);
    }
}

// month chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'month') !== false) {
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for month chart
        $month_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $month_table = "";
        // loop for first to actual year
        // define start and end time of the loop year
        $month = mktime(0, 0, 0, 1, 1, $year);
        $end = mktime(0, 0, 0, $month_act+1, 1, $year_act);
        // loop througl all month of the loop year
        while ($month < $end) {
            // define start and end time of the month to query
            $start_time = mktime(0, 0, 0, date("m", $month), 1, date("Y", $month));
            $end_time = mktime(0, 0, 0, date("m", $month)+1, 1, date("Y", $month));
            // Electric Meter InfluxDB query
            $result_em = $database->query('SELECT spread(consumption) AS grid, spread(supply) AS supply, last(consumption) as last_grid, last(supply) as last_supply FROM electric_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
            $points_em = $result_em->getPoints();
            // extract queried values
            if (isset($points_em[0])) {
                $grid_em = round($points_em[0]['grid']/1000, 0);
                $supply_em = round($points_em[0]['supply']/1000, 0);
            } else {
                $grid_em = 0;
                $supply_em = 0;
            }
            // Electric Meter InfluxDB query
            $result_em = $database->query('SELECT last(consumption) as last_grid FROM electric_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
            $points_em = $result_em->getPoints();
            if (isset($points_em[0])) {
                $last_grid_em = round($points_em[0]['last_grid']/1000, 1);
                $last_grid_em_time = date("d.m.Y H:i", strtotime($points_em[0]['time']));
            } else {
                $last_grid_em = 0;
                $last_grid_em_time = 0;
            }
            // Electric Meter InfluxDB query
            $result_em = $database->query('SELECT last(supply) as last_supply FROM electric_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
            $points_em = $result_em->getPoints();
            if (isset($points_em[0])) {
                $last_supply_em = round($points_em[0]['last_supply']/1000, 1);
                $last_supply_em_time = date("d.m.Y H:i", strtotime($points_em[0]['time']));
            } else {
                $last_supply_em = 0;
                $last_supply_em_time = 0;
            }
            // SMA InfluxDB query
            if ($sma_query) {
                $result_sma = $database_sma->query('SELECT sum(solar_daily) AS solar, sum(bezug_daily) AS grid, sum(consumption_daily) AS consumption, sum(einspeisung_daily) AS supply FROM totals_daily WHERE time >='.$start_time.'s and time<'.$end_time.'s tz(\'Europe/Berlin\')');
                $points_sma = $result_sma->getPoints();
                // save values into array for chart
                if (isset($points_sma[0])) {
                    $solar_sma = round($points_sma[0]['solar']/1000, 0);
                    $grid_sma = round($points_sma[0]['grid']/1000, 0);
                    $consumption_sma = round($points_sma[0]['consumption']/1000, 0);
                    $supply_sma = round($points_sma[0]['supply']/1000, 0);
                    $own_consumption = $solar_sma-$supply_sma;
                } else {
                  $solar_sma = 0;
                  $grid_sma = 0;
                  $consumption_sma = 0;
                  $supply_sma = 0;
                  $own_consumption = 0;
                }
                if ($solar_sma > 0) {
                    $self_consumption = round(($own_consumption/$solar_sma)*100, 0);
                    $self_sufficiency = round(($own_consumption/$consumption_sma)*100, 0);
                } else {
                    $self_consumption = "-";
                    $self_sufficiency = "-";    
                }
                $month_table_temp_sma = "      <td>".$solar_sma." kWh</td>
      <td>".$grid_sma." kWh</td>
      <td>".$consumption_sma." kWh</td>
      <td>".$supply_sma." kWh</td>
      <td>".$own_consumption." kWh</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>\n";
            }
            // generate table rows
            if ($grid_em > 0) {
                $month_table = "    <tr>
      <td>".date("m/Y", $month)."</td>
      <td>".$grid_em." kWh</td>
      <td>".d($last_grid_em)." kWh (".$last_grid_em_time.")</td>
      <td>".$supply_em." kWh</td>
      <td>".d($last_supply_em)." kWh (".$last_supply_em_time.")</td>\n".$month_table_temp_sma."    </tr>\n".$month_table;
            }
            $month = strtotime("+1 month", $month);
        }
    // after looping through all years, generate the table
    $month_html_table = "  <table>
    <tr>
      <th style=\"width: 8%\">".t(17)."</th>
      <th style=\"width: 8%\">".t(2)." (".t(8).")</th>
      <th style=\"width: 9%\">".t(18)." ".t(2)." (".t(8).")</th>
      <th style=\"width: 8%\">".t(3)." (".t(8).")</th>
      <th style=\"width: 9%\">".t(18)." ".t(3)." (".t(8).")</th>\n";
    if ($sma_query) {
        $month_html_table = $month_html_table."      <th style=\"width: 8%\">".t(5)." (".t(9).")</th>
      <th style=\"width: 8%\">".t(2)." (".t(9).")</th>
      <th style=\"width: 8%\">".t(4)." (".t(9).")</th>
      <th style=\"width: 8%\">".t(3)." (".t(9).")</th>
      <th style=\"width: 8%\">".t(10)." (".t(9).")</th>
      <th style=\"width: 8%\">".t(11)." (".t(9).")</th>
      <th style=\"width: 8%\">".t(12)." (".t(9).")</th>\n";
    }
    $month_html_table = $month_html_table."    </tr>\n".$month_table."  </table>\n";
    print($month_html_table."  <br>\n");
    // end debug timing for month table
    $month_time_end = hrtime(true);
    }
}

// function for evaluating runtime
if ($script_timing) {
    if (isset($year_time_start) && isset($year_time_end)) {
        $year_runtime = round(($year_time_end-$year_time_start)/1e+6, 0);
        print("  ".t(14).": ".$year_runtime."ms\n  <br>\n");
    }
    if (isset($month_time_start) && isset($month_time_end)) {
        $month_runtime = round(($month_time_end-$month_time_start)/1e+6, 0);
        print("  ".t(15).": ".$month_runtime."ms\n  <br>\n");
    }
    $script_time_end = hrtime(true);
    $script_runtime = round(($script_time_end-$script_time_start)/1e+6, 0);
    print("  ".t(16).": ".$script_runtime."ms\n  <br>\n");
}


// html footer
print("</body>\n</html>");
?>
