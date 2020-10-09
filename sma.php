<?php
// debug execution time
if (isset($_GET['timing'])) {
    $script_timing = TRUE;
    $script_time_start = hrtime(true);
} else {
    $script_timing = FALSE;
}

// Load SMA InfluxDB Parameter
$influx_sma_ip = getenv('smadb_ip');
$influx_sma_port = getenv('smadb_port');
$influx_sma_db = getenv('smadb_db');
$influx_sma_user = getenv('smadb_user');
$influx_sma_pw = getenv('smadb_pw');
if ($influx_sma_ip == "192.168.1.3" && $influx_sma_db == "SMA" && $influx_sma_port == "8086" && $influx_sma_user == "user" && $influx_sma_pw == "pw") {
  exit("Missing SMA InfluxDB Parameters! Default values used!");
}

// load php influxdb plugin
require __DIR__ . '/vendor/autoload.php';
$client = new InfluxDB\Client($influx_sma_ip, $influx_sma_port, $influx_sma_user, $influx_sma_pw);
$database = $client->selectDB($influx_sma_db);

// language definition and value check
$dict['de'] = array(1 => 'Jahr', 2 => 'Solar', 3 => 'Netzbezug', 4 => 'Verbrauch', 5 => 'Einspeisung', 6 => 'Eigen- verbrauch', 7 => 'Eigen- verbrauchsquote', 8 => 'Autarkie- grad', 9 => 'Grafik', 10 => "Monat", 11 => 'Solar Erzeugung pro Jahr', 12 => 'Solar Erzeugung pro Monat', 12 => 'Solar Erzeugung pro Tag des Jahres', 14 => 'Generierungzeit Jahres Tabelle', 15 => 'Generierungzeit Monats Tabelle', 16 => 'Generierungzeit Tages Tabelle', 17 => 'Gesamt Generierungzeit', 18 => 'Tag', 19 => 'Max. 5min Solar', 20 => 'Erste Zeit >', 21 => 'Letzte Zeit >', 22 => 'Minimalster Strom Verbrauch');
$dict['en'] = array(1 => 'Year', 2 => 'Solar', 3 => 'Grid', 4 => 'Consumption', 5 => 'Supply', 6 => 'Own Consumption', 7 => 'Self Consumption', 8 => 'Self Sufficiency', 9 => 'Chart', 10 => "Month", 11 => 'Solar Energy Generation per Year', 12 => 'Solar Energy per Months', 13 => 'Solar Energy per Day of the Year', 14 => 'Year Table Generation Time', 15 => 'Month Table Generation Time', 16 => 'Day Table Generation Time', 17 => 'Total Generation Time', 18 => 'Day', 19 => 'Peak 5min Solar', 20 => 'First time >', 21 => 'Last time >', 22 => 'Minium Power Consumption');
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

// table border value check
switch(getenv('table_borders')) {
    case "no":
        $script_table_borders = FALSE;
        break;
    default:
        $script_table_borders = TRUE;
}
if (isset($_GET['table_borders'])) {
    switch($_GET['table_borders']) {
        case "no":
            $script_table_borders = FALSE;
            break;
        default:
            $script_table_borders = TRUE;
    }
}
if ($script_table_borders) {
    $table_border = "\n    table, th, td {
      border: 1px solid black;
    }";
} else {
    $table_border = "";
}

// max solar production value check
switch(getenv('max_solar')) {
    case "yes":
        $script_max_solar = TRUE;
        break;
    default:
        $script_max_solar = FALSE;
}
if (isset($_GET['max_solar'])) {
    $script_max_solar = TRUE;
} else {
    $script_max_solar = FALSE;
}

// solar times
if (isset($_GET['time_solar'])) {
    if ($_GET['time_solar'] > 0) {
        $script_time_solar = $_GET['time_solar'];
    } else {
        $script_time_solar = 100;
    }
} else {
    $script_time_solar = 0;
}

// base line power (lowest power consumption during 5min)
if (isset($_GET['baseline'])) {
  $script_base_line = TRUE;
} else {
  $script_base_line = FALSE;
}

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

// only table output value check
if (isset($_GET['onlytable'])) {
  $script_onlytable = TRUE;
} else {
  $script_onlytable = FALSE;
}

// actual dates
$year_act = date("Y");
$month_act = date("m");

// html header
print("<!DOCTYPE html>
<html>
<head>
  <style>
    td {
      text-align: right;
    }".$table_border."
    th, td {
      padding: 3px;
    }
  </style>
</head>
<body>
  <script src=\"charts.js\"></script>\n");

// query first entry in database
$result = $database->query('SELECT first(solar_total) FROM totals tz(\'Europe/Berlin\')');
$points = $result->getPoints();
if (isset($points[0])) {
    $year_first = explode("-", $points[0]['time'])[0];
    $month_first = explode("-", $points[0]['time'])[1];
}

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
        $year_solar = array();
        // loop for first to actual year
        while ($year <= $year_act) {
            // define start and end time of the year to query
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // InfluxDB query
            $result = $database->query('SELECT spread(solar_total) AS solar, spread(bezug_total) AS grid, spread(consumption_total) AS consumption, spread(einspeisung_total) AS supply FROM totals WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
            $points = $result->getPoints();
            // extract queried values and round them to full kWh and calculate usage quotas
            $solar = round($points[0]['solar']/1000, 0);
            $grid = round($points[0]['grid']/1000, 0);
            $consumption = round($points[0]['consumption']/1000, 0);
            $supply = round($points[0]['supply']/1000, 0);
            $own_consumption = $solar-$supply;
            $self_consumption = round(($own_consumption/$solar)*100, 0);
            $self_sufficiency = round(($own_consumption/$consumption)*100, 0);
            // check for maximul solar generation during 5min in whole year
            if ($script_max_solar) {
                // InfluxDB query
                $result = $database->query('SELECT mean(solar_act) AS solar FROM actuals WHERE time >='.$start_time.'s and time<='.$end_time.'s GROUP BY time(5m) tz(\'Europe/Berlin\')');
                $points = $result->getPoints();
                $year_solar_max = 0;
                foreach ($points as $value) {
                    if ($value['solar'] > $year_solar_max) {
                        $year_solar_max = $value['solar'];
                    }
                }
                $year_solar_max_html = "\n      <td>".round($year_solar_max, 0)." W</td>";
                $year_solar_max_header = "\n      <th style=\"width: 90px\">".t(19)."</th>";
            }
            // save values into array for chart
            $year_array[] = $year;
            $year_solar[] = $solar;
            // generate table rows
            $year_table = "    <tr>
      <td>".$year."</td>
      <td>".$solar." kWh</td>
      <td>".$grid." kWh</td>
      <td>".$consumption." kWh</td>
      <td>".$supply." kWh</td>
      <td>".$own_consumption." kWh</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>".$year_solar_max_html."
    </tr>\n".$year_table;
            // debug line
            //print("-solar:-".$solar."kWh--grid:-".$grid."kWh--consumption:-".$consumption."kWh--supply:-".$supply."kWh--own-consumption:-".$own_consumption."kWh--self-consumption:-".$self_consumption."%--self-sufficiency:-".$self_sufficiency."%--\n");
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $year_html_table = "  <table>
    <tr>
      <th style=\"width: 70px\">".t(1)."</th>
      <th style=\"width: 90px\">".t(2)."</th>
      <th style=\"width: 90px\">".t(3)."</th>
      <th style=\"width: 90px\">".t(4)."</th>
      <th style=\"width: 90px\">".t(5)."</th>
      <th style=\"width: 90px\">".t(6)."</th>
      <th style=\"width: 90px\">".t(7)."</th>
      <th style=\"width: 90px\">".t(8)."</th>".$year_solar_max_header;
    if (!$script_onlytable) {
        $year_html_table = $year_html_table."\n      <th style=\"width: 710px\">".t(9)."</th>";
    }
    $year_html_table = $year_html_table."\n    </tr>\n".$year_table."  </table>\n";
    $year_html_script = "  <script>
    new Chart(document.getElementById('chart_years'), {
      type: 'bar',
      data: {
        labels: ".json_encode(array_values($year_array)).",
        datasets: [
          {
            label: 'Solar kWh',
            backgroundColor: '#ffff00',
            fill: true,
            data: ".json_encode(array_values($year_solar))."
          }
        ]
      },
      options: {
        scales: { yAxes: [ { ticks: { beginAtZero: true } } ] },
        legend: { display: false },
        title: {
          display: true,
          text: '".t(11)."'
        }
      }
    });
  </script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"div_years\" style=\"width: 650px; height: 320px\">
    <canvas id=\"chart_years\"></canvas>
  </div>\n".$year_html_script);
    } elseif ($script_onlytable) {
        print($year_html_table.$year_html_script."  <br>\n");
    } else {
        $tr1 = strpos($year_html_table, "</tr>");
        $tr2 = strpos($year_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"100\" style=\"vertical-align: top\">
        <div id=\"div_years\" style=\"width: 650px; height: 320px\">
          <canvas id=\"chart_years\"></canvas>
        </div>
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
        $month_chart = "";
        $month_table = "";
        // loop for first to actual year
        while ($year <= $year_act) {
            // variable initialization
            $month_solar = array();
            // define start and end time of the loop year
            $month = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year+1);
            // loop througl all month of the loop year
            while ($month < $end) {
                // define start and end time of the month to query
                $start_time = mktime(0, 0, 0, date("m", $month), 1, date("Y", $month));
                $end_time = mktime(0, 0, 0, date("m", $month)+1, 1, date("Y", $month));
                // InfluxDB query
                $result = $database->query('SELECT spread(solar_total) AS solar, spread(bezug_total) AS grid, spread(consumption_total) AS consumption, spread(einspeisung_total) AS supply FROM totals WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
                $points = $result->getPoints();
                // extract queried values and round them to full kWh and calculate usage quotas
                $solar = round($points[0]['solar']/1000, 0);
                $grid = round($points[0]['grid']/1000, 0);
                $consumption = round($points[0]['consumption']/1000, 0);
                $supply = round($points[0]['supply']/1000, 0);
                $own_consumption = $solar-$supply;
                if ($solar > 0) {
                    // check for maximul solar generation during 5min in whole month
                    if ($script_max_solar) {
                        // InfluxDB query
                        $result = $database->query('SELECT mean(solar_act) AS solar FROM actuals WHERE time >='.$start_time.'s and time<='.$end_time.'s GROUP BY time(5m) tz(\'Europe/Berlin\')');
                        $points = $result->getPoints();
                        $month_solar_max = 0;
                        foreach ($points as $value) {
                            if ($value['solar'] > $month_solar_max) {
                                $month_solar_max = $value['solar'];
                            }
                        }
                        $month_solar_max_html = "\n      <td>".round($month_solar_max, 0)." W</td>";
                        $month_solar_max_header = "\n      <th style=\"width: 90px\">".t(19)."</th>";
                    }
                    $self_consumption = round(($own_consumption/$solar)*100, 0);
                    $self_sufficiency = round(($own_consumption/$consumption)*100, 0);
                } else {
                    $self_consumption = "-";
                    $self_sufficiency = "-";    
                }
                // save values into array for chart & generate table rows
                if ($solar > 0) {
                    $month_solar[] = $solar;
                    $month_table = "    <tr>
      <td>".date("m/Y", $month)."</td>
      <td>".$solar." kWh</td>
      <td>".$grid." kWh</td>
      <td>".$consumption." kWh</td>
      <td>".$supply." kWh</td>
      <td>".$own_consumption." kWh</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>".$month_solar_max_html."
    </tr>\n".$month_table;
                } else {
                    $month_solar[] = "NaN";
                }
                $month = strtotime("+1 month", $month);
            }
            if ($month_chart != "") {
                $month_chart = $month_chart.",";
            }
            $month_chart = $month_chart."
          {
            label: '".$year."',
            data: ".json_encode(array_values($month_solar)).",
            fill: false,
            borderColor: '#".dechex(255-($year-$year_first)*7).dechex(255-($year-$year_first)*10)."00',
            backgroundColor: '#".dechex(255-($year-$year_first)*7).dechex(255-($year-$year_first)*10)."00'
          }";
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $month_html_table = "  <table>
    <tr>
      <th style=\"width: 70px\">".t(10)."</th>
      <th style=\"width: 90px\">".t(2)."</th>
      <th style=\"width: 90px\">".t(3)."</th>
      <th style=\"width: 90px\">".t(4)."</th>
      <th style=\"width: 90px\">".t(5)."</th>
      <th style=\"width: 90px\">".t(6)."</th>
      <th style=\"width: 90px\">".t(7)."</th>
      <th style=\"width: 90px\">".t(8)."</th>".$month_solar_max_header;
    if (!$script_onlytable) {
        $month_html_table = $month_html_table."\n      <th style=\"width: 710px\">".t(9)."</th>";
    }
    $month_html_table = $month_html_table."\n    </tr>\n".$month_table."  </table>\n";
    $month_html_script = "  <script>
    new Chart(document.getElementById('chart_months'), {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [".$month_chart."
        ]
      },
      options: {
        scales: { yAxes: [ { ticks: { beginAtZero: true } } ] },
        legend: { display: true },
        title: {
          display: true,
          text: '".t(12)."'
        },
        spanGaps: false
      }
    });
  </script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"div_months\" style=\"width: 650px; height: 320px\">
    <canvas id=\"chart_months\"></canvas>
    </div>\n".$month_html_script);
    } elseif ($script_onlytable) {
        print($month_html_table.$month_html_script."  <br>\n");
    } else {
        $tr1 = strpos($month_html_table, "</tr>");
        $tr2 = strpos($month_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"1000\" style=\"vertical-align: top\">
        <div id=\"div_months\" style=\"width: 650px; height: 320px\">
          <canvas id=\"chart_months\"></canvas>
        </div>
      </td>\n    ";
        $month_html_table = substr_replace($month_html_table, $chart_row, $tr2, 0);
        print($month_html_table.$month_html_script."  <br>\n");
    }
    // end debug timing for month chart
    $month_time_end = hrtime(true);
    }
}

// day chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'day') !== false) {
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for day chart
        $day_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $day_chart = "";
        $day_table = "";
        // loop for first to actual year
        while ($year <= $year_act) {
            // variable initialization
            $day_solar = array();
            // define start and end time of the loop year
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // InfluxDB query for whole year incl. Timezone setting!
            $result = $database->query('SELECT spread(solar_total) AS solar, spread(bezug_total) AS grid, spread(consumption_total) AS consumption, spread(einspeisung_total) AS supply FROM totals WHERE time>='.$start_time.'s and time<'.$end_time.'s GROUP BY time(1d) tz(\'Europe/Berlin\')');
            $points = $result->getPoints();
            foreach ($points as $day) {
                $solar = round($day['solar']/1000, 1);
                $grid = round($day['grid']/1000, 1);
                $consumption = round($day['consumption']/1000, 1);
                $supply = round($day['supply']/1000, 1);
                $own_consumption = $solar-$supply;
                if ($solar > 0) {
                    // check for maximul solar generation during 5min in day
                    if ($script_max_solar or $script_base_line or $script_time_solar > 0) {
                        // InfluxDB query
                        $start_time = strtotime($day['time']);
                        $end_time = strtotime("+1 day", $start_time);
                        $result = $database->query('SELECT mean(solar_act) AS solar, mean(consumption_act) AS consumption FROM actuals WHERE time >='.$start_time.'s and time<='.$end_time.'s GROUP BY time(5m) tz(\'Europe/Berlin\')');
                        $points = $result->getPoints();
                        $day_solar_max = 0;
                        $day_first_solar_time = "";
                        $day_last_solar_time = "";
                        $day_base_line = 100000;
                        $day_solar_max_html = "";
                        $day_solar_max_header = "";
                        foreach ($points as $value) {
                            if ($value['solar'] > $day_solar_max) {
                                $day_solar_max = $value['solar'];
                            }
                            if ($value['consumption'] < $day_base_line && $value['consumption'] > 0 && $value['consumption'] != "") {
                                $day_base_line = round($value['consumption'], 0);
                            }
                            if ($value['solar'] > $script_time_solar && $day_first_solar_time == "") {
                                $day_first_solar_time = $value['time'];
                            }
                            if ($value['solar'] > $script_time_solar) {
                                $day_last_solar_time = $value['time'];
                            }
                        }
                        if ($script_max_solar) {
                            $day_solar_max_html = "\n      <td>".round($day_solar_max, 0)." W</td>";
                            $day_solar_max_header = "\n      <th style=\"width: 90px\">".t(19)."</th>";
                        }
                        if ($script_time_solar > 0) {
                            if ($day_first_solar_time != "") {
                                $day_solar_max_html = $day_solar_max_html."\n      <td>".date("H:i", strtotime($day_first_solar_time))."</td>";
                            } else {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>---</td>";
                            }
                            if ($day_last_solar_time != "") {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>".date("H:i", strtotime($day_last_solar_time))."</td>";
                            } else {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>---</td>";
                            }
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(20).$script_time_solar."W</th>
      <th style=\"width: 90px\">".t(21).$script_time_solar."W</th>";
                        }
                        if ($script_base_line) {
                            if ($day_base_line != 100000) {
                                $day_solar_max_html = $day_solar_max_html."\n      <td>".$day_base_line." W</td>";
                            } else {
                                $day_solar_max_html = $day_solar_max_html."\n      <td>---</td>";
                            }
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(22)."</th>";
                        }
                    }
                    $self_consumption = round(($own_consumption/$solar)*100, 0);
                    $self_sufficiency = round(($own_consumption/$consumption)*100, 0);
                } else {
                    $self_consumption = "-";
                    $self_sufficiency = "-";    
                }
                // save values into array for chart & generate table rows
                if ($solar > 0) {
                    $day_solar[] = $solar;
                    $day_table = "    <tr>
      <td>".date("d.m.Y", strtotime($day['time']))."</td>
      <td>".d($solar)." kWh</td>
      <td>".d($grid)." kWh</td>
      <td>".d($consumption)." kWh</td>
      <td>".d($supply)." kWh</td>
      <td>".d($own_consumption)." kWh</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>".$day_solar_max_html."
    </tr>\n".$day_table;
                } else {
                    $day_solar[] = "NaN";
                }
            }
            if ($day_chart != "") {
                $day_chart = $day_chart.",";
            }
            $day_chart = $day_chart."
          {
            label: '".$year."',
            data: ".json_encode(array_values($day_solar)).",
            fill: false,
            borderColor: '#".dechex(255-($year-$year_first)*7).dechex(255-($year-$year_first)*10)."00',
            backgroundColor: '#".dechex(255-($year-$year_first)*7).dechex(255-($year-$year_first)*10)."00'
          }";
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $day_html_table = "  <table>
    <tr>
      <th style=\"width: 70px\">".t(18)."</th>
      <th style=\"width: 90px\">".t(2)."</th>
      <th style=\"width: 90px\">".t(3)."</th>
      <th style=\"width: 90px\">".t(4)."</th>
      <th style=\"width: 90px\">".t(5)."</th>
      <th style=\"width: 90px\">".t(6)."</th>
      <th style=\"width: 90px\">".t(7)."</th>
      <th style=\"width: 90px\">".t(8)."</th>".$day_solar_max_header;
    if (!$script_onlytable) {
        $day_html_table = $day_html_table."\n      <th style=\"width: 710px\">".t(9)."</th>";
    }
    $day_html_table = $day_html_table."\n    </tr>\n".$day_table."  </table>";
    $day_html_script = "\n  <script>
    new Chart(document.getElementById('chart_days'), {
      type: 'line',
      data: {
        labels: ['1'";
    for ($i = 2 ; $i < 367; $i++){ $day_html_script = $day_html_script.",'".$i."'"; }
    $day_html_script = $day_html_script."],
        datasets: [".$day_chart."
        ]
      },
      options: {
        scales: { 
          yAxes: [ { ticks: { beginAtZero: true } } ]
        },
        legend: { display: true },
        title: {
          display: true,
          text: '".t(13)."'
        },
        elements: {
          point:{
            radius: 0
          }
        },
        spanGaps: false
      }
    });
  </script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"div_days\" style=\"width: 650px; height: 320px\">
    <canvas id=\"chart_days\"></canvas>
    </div>\n".$day_html_script);
    } elseif ($script_onlytable) {
        print($day_html_table.$day_html_script."  <br>\n");
    } else {
        $tr1 = strpos($day_html_table, "</tr>");
        $tr2 = strpos($day_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"100000\" style=\"vertical-align: top\">
        <div id=\"div_days\" style=\"width: 650px; height: 320px\">
          <canvas id=\"chart_days\"></canvas>
        </div>
      </td>\n    ";
        $day_html_table = substr_replace($day_html_table, $chart_row, $tr2, 0);
        print($day_html_table.$day_html_script."  <br>\n");
    }
    // end debug timing for day chart
    $day_time_end = hrtime(true);
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
    if (isset($day_time_start) && isset($day_time_end)) {
        $day_runtime = round(($day_time_end-$day_time_start)/1e+6, 0);
        print("  ".t(16).": ".$day_runtime."ms\n  <br>\n");
    }
    $script_time_end = hrtime(true);
    $script_runtime = round(($script_time_end-$script_time_start)/1e+6, 0);
    print("  ".t(17).": ".$script_runtime."ms\n  <br>\n");
}

// html footer
print("</body>\n</html>");
?>
