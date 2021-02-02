<?php
// debug execution time
if (isset($_GET['timing'])) {
    $script_timing = TRUE;
    $script_time_start = hrtime(true);
} else {
    $script_timing = FALSE;
}

// Load Water Meter InfluxDB Parameter
$influx_wm_ip = getenv('wmdb_ip');
$influx_wm_port = getenv('wmdb_port');
$influx_wm_db = getenv('wmdb_db');
$influx_wm_user = getenv('wmdb_user');
$influx_wm_pw = getenv('wmdb_pw');
if ($influx_wm_ip == "192.168.1.3" && $influx_wm_db == "measurements" && $influx_wm_port == "8086" && $influx_wm_user == "user" && $influx_wm_pw == "pw") {
    exit("Missing Water Meter InfluxDB Parameters! Default values used!");
}

// load php influxdb plugin
require __DIR__ . '/vendor/autoload.php';
// connect to Electric Meter InfluxDB
$client_wm = new InfluxDB\Client($influx_wm_ip, $influx_wm_port, $influx_wm_user, $influx_wm_pw);
$database_wm = $client_wm->selectDB($influx_wm_db);

// language definition and value check
$dict['de'] = array(1 => 'Jahr', 2 => 'Wasser', 3 => 'Wasserverbrauch pro Jahr', 4 => 'Grafik', 5 => 'Generierungzeit Jahres Tabelle', 6 => 'Generierungzeit Monats Tabelle', 7 => 'Generierungzeit Tages Tabelle', 8 => 'Gesamt Generierungzeit', 9 => 'Monat', 10 => 'Wasserverbrauch je Monat', 11 => 'Tag', 12 => 'Wasserverbrauch je Tag', 13 => '\'Jan\', \'Feb\', \'Mär\', \'Apr\', \'Mai\', \'Jun\', \'Jul\', \'Aug\', \'Sep\', \'Okt\', \'Nov\', \'Dez\'');
$dict['en'] = array(1 => 'Year', 2 => 'Water', 3 => 'Water usage per Year', 4 => 'Chart', 5 => 'Year Table Generation Time', 6 => 'Month Table Generation Time', 7 => 'Day Table Generation Time', 8 => 'Total Generation Time', 9 => 'Month', 10 => 'Water usage per Month', 11 => 'Day', 12 => 'Water usage per Day',  13 => '\'Jan\', \'Feb\', \'Mar\', \'Apr\', \'May\', \'Jun\', \'Jul\', \'Aug\', \'Sep\', \'Oct\', \'Nov\', \'Dec\'');
switch(getenv('wmlang')) {
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
switch(getenv('wmtable_borders')) {
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

// chart value check
$script_chart = getenv('wmchart');
if (isset($_GET['chart'])) {
    $script_chart = $_GET['chart'];
}

// only chart output value check
if (isset($_GET['onlychart'])) {
    $script_onlychart = TRUE;
} else {
    $script_onlychart = FALSE;
}

// days for day table
$script_days = 0;
if (getenv('wmdays') > 0) {
    $script_days = getenv('wmdays');
}
if (isset($_GET['days'])) {
    if ($_GET['days'] >= 0) {
        $script_days = $_GET['days'];
    }
}

// actual dates
$year_act = date("Y");
$month_act = date("m");

// html header
print("<!DOCTYPE html>
<html>
<head>
  <title>sma-stats - Water Meter Statistics</title>
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
  <script src=\"echarts.js\"></script>\n");

// query first entry in database_wm$database_wm
$result_em = $database_wm->query('SELECT first(l) FROM water_meter tz(\'Europe/Berlin\')');
$points_em = $result_em->getPoints();
if (isset($points_em[0])) {
    $year_first = explode("-", $points_em[0]['time'])[0];
    $month_first = explode("-", $points_em[0]['time'])[1];
}

// year chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'year') !== false) {
    // only continue if really data is available in database_wm$database_wm
    if (isset($year_first)) {
        // start debug timing for year chart
        $year_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $year_chart = "";
        $year_table = "";
        $year_array = array();
        $year_water = array();
        // loop for first to actual year
        while ($year <= $year_act) {
            // define start and end time of the year to query
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // Water Meter InfluxDB query
            $result_wm = $database_wm->query('SELECT sum(l) AS water FROM water_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
            $points_wm = $result_wm->getPoints();
            // extract queried values
            $water = $points_wm[0]['water']/1000;
            // save values into array for chart
            $year_array[] = $year;
            $year_water[] = $water;
            // generate table rows
            $year_table = "    <tr>
      <td>".$year."</td>
      <td>".d($water)." m³</td>
    </tr>\n".$year_table;
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $year_html_table = "  <table>
    <tr>
      <th style=\"width: 70px\">".t(1)."</th>
      <th style=\"width: 90px\">".t(2)."</th>
      <th style=\"width: 710px\">".t(4)."</th>
    </tr>\n".$year_table."  </table>\n";
  $year_html_script = "  <script type=\"text/javascript\">
  var myChart = echarts.init(document.getElementById('div_years'));
  var option = {
      title: {
          text: '".t(3)."',
          textStyle: {
              fontSize: 14
          },
          left: 'center'
      },
      tooltip: {
          trigger: 'axis'
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
      series: [{
          data: ".json_encode(array_values($year_water)).",
          type: 'bar',
          itemStyle: {
                  color: '#0000ff'
          }
      }]
  };
  myChart.setOption(option);
</script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"div_years\" style=\"width: 650px; height: 320px\"></div>\n".$year_html_script);
    } else {
        $tr1 = strpos($year_html_table, "</tr>");
        $tr2 = strpos($year_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"100\" style=\"vertical-align: top\">
        <div id=\"div_years\" style=\"width: 650px; height: 320px\"></div>
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
    // only continue if really data is available in database_wm$database_wm
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
            $month_water = array();
            // define start and end time of the loop year
            $month = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year+1);
            // loop througl all month of the loop year
            while ($month < $end) {
                $water = 0;
                // define start and end time of the month to query
                $start_time = mktime(0, 0, 0, date("m", $month), 1, date("Y", $month));
                $end_time = mktime(0, 0, 0, date("m", $month)+1, 1, date("Y", $month));
                // Water Meter InfluxDB query
                $result_wm = $database_wm->query('SELECT sum(l) AS water FROM water_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
                $points_wm = $result_wm->getPoints();
                // extract queried values
                if (isset($points_wm[0]['water'])) {
                    $water = $points_wm[0]['water']/1000;
                }
                // save values into array for chart
                $year_array[] = $year;
                // generate table rows
                if ($water > 0) {
                    $month_water[] = $water;
                    $month_table = "    <tr>
      <td>".date("m/Y", $month)."</td>
      <td>".d($water)." m³</td>
    </tr>\n".$month_table;
                } else {
                    $month_water[] = "";
                }
                $month = strtotime("+1 month", $month);
            }
            if ($month_chart != "") {
                $month_chart = $month_chart.",";
            }
            $month_chart = $month_chart."
            {
              name: '".$year."',
              data: ".json_encode(array_values($month_water)).",
              type: 'line',
              smooth: true,
              itemStyle: {
                  color: '#0000".dechex(255-($year-$year_first)*7)."'
              },
              emphasis: {
                  focus: 'series'
              }
            }";
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $month_html_table = "  <table>
    <tr>
      <th style=\"width: 70px\">".t(9)."</th>
      <th style=\"width: 90px\">".t(2)."</th>
      <th style=\"width: 710px\">".t(4)."</th>
    </tr>\n".$month_table."  </table>\n";
    $month_html_script = "  <script type=\"text/javascript\">
  var myChart = echarts.init(document.getElementById('div_months'));
  var option = {
      title: {
          text: '".t(10)."',
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
                if (item.value > 0) {
                    tooltipString = `\${tooltipString}<br /><span style=\"float: left;\">\${item.marker} \${item.seriesName}:</span>&emsp;<span style=\"float: right;\">\${item.value}</span>`
                }
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
          data: [".t(13)."]
      },
      yAxis: {
          type: 'value'
      },
      series: [".$month_chart."]
  };
  myChart.setOption(option);
</script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
      print("  <div id=\"div_months\" style=\"width: 650px; height: 320px\"></div>\n".$month_html_script);
    } else {
        $tr1 = strpos($month_html_table, "</tr>");
        $tr2 = strpos($month_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"1000\" style=\"vertical-align: top\">
        <div id=\"div_months\" style=\"width: 650px; height: 320px\"></div>
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
        $day_count = 0;
        $day_actual = new DateTime();
        // loop for first to actual year
        while ($year <= $year_act) {
            // variable initialization
            $day_water = array();
            // define start and end time of the loop year
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // Water Meter InfluxDB query
            $result_wm = $database_wm->query('SELECT sum(l) AS water FROM water_meter WHERE time >='.$start_time.'s and time<='.$end_time.'s GROUP BY time(1d) tz(\'Europe/Berlin\')');
            $points_wm = $result_wm->getPoints();
            foreach ($points_wm as $day) {
                // extract queried values
                $water = $day['water']/1000;
                // save values into array for chart & generate table rows
                if ($water > 0) {
                    $day_count++;
                    $day_water[] = [date("2020-m-d", strtotime($day['time'])), $water];
                    $day_current_item = new DateTime($day['time']);
                    $day_difference = $day_current_item->diff($day_actual);
                    if ($day_difference->days < $script_days or $script_days == 0) {
                      $day_table = "    <tr>
      <td>".date("d.m.Y", strtotime($day['time']))."</td>
      <td>".d($water)." m³</td>
    </tr>\n".$day_table;
                    }
                } else {
                    $day_water[] = [date("2020-01-z", strtotime($day['time'])), ""];
                }
            }
            if ($day_chart != "") {
                $day_chart = $day_chart.",";
            }
            $day_chart = $day_chart."
            {
              name: '".$year."',
              data: ".json_encode(array_values($day_water)).",
              type: 'line',
              smooth: true,
                  itemStyle: {
                      color: '#0000".dechex(255-($year-$year_first)*7)."'
                  },
                  emphasis: {
                      focus: 'series'
                  }
          }";
            $year = $year + 1;
        }
    // after looping through all years, generate the table
    $day_html_table = "  <table>
    <tr>
      <th style=\"width: 70px\">".t(11)."</th>
      <th style=\"width: 90px\">".t(2)."</th>
      <th style=\"width: 710px\">".t(4)."</th>
    </tr>\n".$day_table."  </table>";
  $day_html_script = "\n  <script type=\"text/javascript\">
  var myChart = echarts.init(document.getElementById('div_days'));
  var option = {
      title: {
          text: '".t(12)."',
          textStyle: {
              fontSize: 14
          },
          left: 'center'
      },
      tooltip: {
          trigger: 'axis',
          formatter: function (params) {
              var date = new Date(params[0].value[0]);
              var tooltipString = date.getDate() + '.' + (date.getMonth() + 1) + '. :' 
              params.forEach(function (item, index) {
                if (item.value[1] > 0) {
                    tooltipString = `\${tooltipString}<br /><span style=\"float: left;\">\${item.marker} \${item.seriesName}:</span>&emsp;<span style=\"float: right;\">\${item.value[1]}</span>`
                }
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
          type: 'time',
          splitArea: {
              show: true
          }
      },
      yAxis: {
          type: 'value'
      },
      series: [".$day_chart."]
  };
  myChart.setOption(option);
</script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
      print("  <div id=\"div_days\" style=\"width: 650px; height: 320px\"></div>\n".$day_html_script);
    } else {
        $tr1 = strpos($day_html_table, "</tr>");
        $tr2 = strpos($day_html_table, "</tr>", $tr1 + 5);
        $chart_row = "  <td rowspan=\"100000\" style=\"vertical-align: top\">
        <div id=\"div_days\" style=\"width: 650px; height: 320px\"></div>
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
        print("  ".t(5).": ".$year_runtime."ms\n  <br>\n");
    }
    if (isset($month_time_start) && isset($month_time_end)) {
        $month_runtime = round(($month_time_end-$month_time_start)/1e+6, 0);
        print("  ".t(6).": ".$month_runtime."ms\n  <br>\n");
    }
    if (isset($day_time_start) && isset($day_time_end)) {
        $day_runtime = round(($day_time_end-$day_time_start)/1e+6, 0);
        print("  ".t(7).": ".$day_runtime."ms\n  <br>\n");
    }
    $script_time_end = hrtime(true);
    $script_runtime = round(($script_time_end-$script_time_start)/1e+6, 0);
    print("  ".t(8).": ".$script_runtime."ms\n  <br>\n");
}

// html footer
print("</body>\n</html>");
?>
