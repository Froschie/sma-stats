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

// load function file
require __DIR__ . '/script_functions.php';

// language definition and value check
$dict['de'] = array(1 => 'Jahr', 2 => 'Solar', 3 => 'Netzbezug', 4 => 'Verbrauch', 5 => 'Einspeisung', 6 => 'Eigen- verbrauch', 7 => 'Eigen- verbrauchsquote', 8 => 'Autarkie- grad', 9 => 'Grafik', 10 => "Monat", 11 => 'Solar Erzeugung pro Jahr', 12 => 'Solar Erzeugung pro Monat', 13 => 'Solar Erzeugung pro Tag des Jahres', 14 => 'Generierungzeit Jahres Tabelle', 15 => 'Generierungzeit Monats Tabelle', 16 => 'Generierungzeit Tages Tabelle', 17 => 'Gesamt Generierungzeit', 18 => 'Tag', 19 => 'Max. 5min Solar', 20 => 'Erste Zeit >', 21 => 'Letzte Zeit >', 22 => 'Minimalster Strom Verbrauch', 23 => 'Zeit ohne Netzbezg', 24 => 'Ladeleistung Auto >', 25 => 'Einspeisung über ', 26 => 'Generierungzeit Stunden Tabelle', 27 => 'Monat / Stunde', 28 => '\'Jan\', \'Feb\', \'Mär\', \'Apr\', \'Mai\', \'Jun\', \'Jul\', \'Aug\', \'Sep\', \'Okt\', \'Nov\', \'Dez\'', 29 => 'Solar Erzeugung pro Stunde jedes Monats', 30 => 'Generierungzeit Breakdown Tabelle', 31 => 'Tage mit ', 32 => 'Solar Erzeugung pro Tag');
$dict['en'] = array(1 => 'Year', 2 => 'Solar', 3 => 'Grid', 4 => 'Consumption', 5 => 'Supply', 6 => 'Own Consumption', 7 => 'Self Consumption', 8 => 'Self Sufficiency', 9 => 'Chart', 10 => "Month", 11 => 'Solar Energy Generation per Year', 12 => 'Solar Energy per Months', 13 => 'Solar Energy per Day of the Year', 14 => 'Year Table Generation Time', 15 => 'Month Table Generation Time', 16 => 'Day Table Generation Time', 17 => 'Total Generation Time', 18 => 'Day', 19 => 'Peak 5min Solar', 20 => 'First time >', 21 => 'Last time >', 22 => 'Minium Power Consumption', 23 => 'Time without grid power', 24 => 'Car charging power >', 25 => 'Grid supply over ', 26 => 'Hour Table Generation Time', 27 => 'Month / Hour', 28 => '\'Jan\', \'Feb\', \'Mar\', \'Apr\', \'May\', \'Jun\', \'Jul\', \'Aug\', \'Sep\', \'Oct\', \'Nov\', \'Dec\'', 29 => 'Solar Energy per Hour of each Month', 30 => 'Breakdown Table Generation Time', 31 => 'Days with ', 32 => 'Solar Energy per Day');
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

// function for chart colors
$color_default = "#ffff00";
$color_array = array();
if (getenv('color') != "") {
    $colors = explode(";", getenv('color'));
    foreach ($colors as $values) {
        $value = explode(",", $values);
        if (count($value) == 2) {
            $color_array[$value[0]] = $value[1];
        }
    }
}
if (isset($_GET['color'])) {
    $colors = explode(";", $_GET['color']);
    foreach ($colors as $values) {
        $value = explode(",", $values);
        if (count($value) == 2) {
            $color_array[$value[0]] = $value[1];
        }
    }
}
function c($year) {
    global $color_default;
    global $color_array;
    if (array_key_exists($year, $color_array)) {
        return "#".$color_array[$year];
    } else {
        return $color_default;
    }
}

// function to replace "." with "," for german output
function d($value) {
  global $script_lang;
  if ($script_lang == "de") {
      $value = str_replace(".", ",", $value);
  }
  return $value;
}

// max solar production value check
$script_max_solar = check_input_bool("max_solar", "max_solar", FALSE);

// solar times
$script_time_solar = check_input_int("time_solar", "time_solar", 0);

// supply times
$script_time_supply = check_input_int("time_supply", "time_supply", 0);

// days for day table
$script_days = check_input_int("days", "days", 0);

// car charging
$script_car_charging = check_input_int("car_charging", "car_charging", 0);

// over supply
$script_over_supply = check_input_int("over_supply", "over_supply", 0);

// time without power from grid
$script_nogrid_time = check_input_bool("nogrid_time", "nogrid_time", FALSE);

// only table output value check
$breakdown_step = check_input_int("breakstep", "breakstep", 5);

// base line power (lowest power consumption during 5min)
$script_base_line = check_input_bool("baseline", "baseline", FALSE);

// hide unit description in table
$unit_kwh = " kWh";
$unit_w = " W";
switch(getenv('nounits')) {
    case "yes":
        $unit_kwh = "";
        $unit_w = "";
}
if (isset($_GET['nounits'])) {
    $unit_kwh = "";
    $unit_w = "";
}

// chart value check
$script_chart = getenv('chart');
if (isset($_GET['chart'])) {
    $script_chart = $_GET['chart'];
}

// only chart output value check
$script_onlychart = check_input_bool("onlychart", "onlychart", FALSE);

// only table output value check
$script_onlytable = check_input_bool("onlytable", "onlytable", FALSE);

// check for dark mode
$return_array = dark_mode("dark", "dark", FALSE);
$script_darkmode = $return_array[0];
$color_chart = $return_array[1];
$color_text = $return_array[2];
$color_bg = $return_array[3];

// table border value check
$table_border = table_border_code(check_input_bool("table_borders", "table_borders", TRUE), $script_darkmode);

// set first and last year for query
$f_year = inf_query_year('SELECT first(solar_total) FROM totals tz(\'Europe/Berlin\')');
$l_year = inf_query_year('SELECT last(solar_total) FROM totals tz(\'Europe/Berlin\')');
$year_first = year_first($f_year, $l_year);
$year_act = year_act($year_act, $f_year, $l_year);

// html header
print("<!DOCTYPE html>
<html>
<head>
  <title>sma-stats - SMA Statistics</title>
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
            $year_solar_max_html = "";
            $year_solar_max_header = "";
            // InfluxDB query
            $result = $database->query('SELECT sum(solar_daily) AS solar, sum(bezug_daily) AS grid, sum(consumption_daily) AS consumption, sum(einspeisung_daily) AS supply FROM totals_daily  WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
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
                $result = $database->query('SELECT max(solar_max) AS solar FROM totals_daily  WHERE time >='.$start_time.'s and time<='.$end_time.'s tz(\'Europe/Berlin\')');
                $points = $result->getPoints();
                $year_solar_max = 0;
                foreach ($points as $value) {
                    if ($value['solar'] > $year_solar_max) {
                        $year_solar_max = $value['solar'];
                    }
                }
                $year_solar_max_html = "\n      <td>".round($year_solar_max, 0).$unit_w."</td>";
                $year_solar_max_header = "\n      <th style=\"width: 90px\">".t(19)."</th>";
            }
            // save values into array for chart
            $year_array[] = strval($year);
            $year_solar[] = $solar;
            // generate table rows
            $year_table = "    <tr>
      <td>".$year."</td>
      <td>".$solar.$unit_kwh."</td>
      <td>".$grid.$unit_kwh."</td>
      <td>".$consumption.$unit_kwh."</td>
      <td>".$supply.$unit_kwh."</td>
      <td>".$own_consumption.$unit_kwh."</td>
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
    $year_html_script = "  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('div_years')".$color_chart.");
    var option = {
      title: {
        text: '".t(11)."',
        textStyle: {
          fontSize: 14
        },
        left: 'left'
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
        data: ".json_encode(array_values($year_solar)).",
        type: 'bar',
        itemStyle: {
          color: '".$color_default."'
        }
      }]
    };
    myChart.setOption(option);
  </script>\n";
    // output selection of table or only chart
    output($script_onlychart, $script_onlytable, "div_years", $year_html_script, $year_html_table);
    // end debug timing for year chart
    $year_time_end = hrtime(true);
    }
}

// breakdown chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'breakdown') !== false) {
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for day chart
        $breakdown_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $year_array = array();
        $breakdown_table = array();
        $breakdown_array = array();
        // query highest solar value
        $result = $database->query('SELECT max(solar_daily) as solar_max FROM totals_daily');
        $points = $result->getPoints();
        $breakdown_steps = ceil($points[0]['solar_max']/1000/$breakdown_step);
        // html table generation
        $breakdown_html_table = array();
        $breakdown_html_table[] = "  <table>\n    <tr>\n      <th style=\"width: 70px\">".t(1)."</th>";
        for ($i = 0; $i < ($breakdown_steps*$breakdown_step); ) {
            $breakdown_html_table[] = "      <th style=\"width: 90px\">".t(31).$i."-".$i+$breakdown_step." kWh</th>";
            $i = $i+$breakdown_step;
        }
        if (!$script_onlytable) {
            $breakdown_html_table[] = "      <th style=\"width: 710px\">".t(9)."</th>";
        }
        // loop for first to actual year
        while ($year <= $year_act) {
            // variable initialization
            for ($i = 0; $i < ($breakdown_steps*$breakdown_step); ) {
                $breakdown_array[$year][$i] = 0;
                $i = $i+$breakdown_step;
            }
            // define start and end time of the loop year
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // InfluxDB query for whole year incl. Timezone setting!
            $result = $database->query('SELECT solar_daily AS solar FROM totals_daily WHERE time>='.$start_time.'s and time<'.$end_time.'s tz(\'Europe/Berlin\')');
            $points = $result->getPoints();
            foreach ($points as $day) {
                $solar = round($day['solar']/1000, 2);
                $breakdown_field = floor($solar/$breakdown_step)*$breakdown_step;
                $breakdown_array[$year][$breakdown_field] = $breakdown_array[$year][$breakdown_field] + 1;
            }
            // generate table entries
            $temp_table = array();
            foreach ($breakdown_array[$year] as $value) {
                $temp_table[] = "      <td>".$value."</td>";
            }
            $breakdown_table[] = "    <tr>\n      <td>".$year."</td>\n".join("\n", $temp_table)."\n    </tr>";
            $year_array[] = strval($year);
            $year = $year + 1;
        }
        // generate JSON for chart series
        $breakdown_series = array();
        for ($i = 0; $i < ($breakdown_steps*$breakdown_step); ) {
            $breakdown_serie = array();
            foreach ($breakdown_array as $year) {
                $breakdown_serie[] = $year[$i];
            }
            $breakdown_series[] = "        {
          name: '".$i."-".$i+$breakdown_step."',
          type: 'bar',
          stack: 'one',
          emphasis: {
            focus: 'series'
          },
          data: ".json_encode(array_values($breakdown_serie))."
        }";
            $i = $i+$breakdown_step;
        }
    // after looping through all years, generate the table
    $breakdown_html_table[] = "    </tr>\n".join("\n", array_reverse($breakdown_table))."\n  </table>\n";
    $breakdown_html_table = join("\n", $breakdown_html_table);
    $breakdown_html_script = "  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('div_breakdown')".$color_chart.");
    var option = {
      title: {
        text: '".t(32)."',
        textStyle: {
          fontSize: 14
        },
        left: 'left'
      },
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var tooltipString = `<b>\${params[0].axisValue}</b>`;
          total = 0;
          params.forEach(function (item, index) {
            total = total + item.value;                    
          });
          params.forEach(function (item, index) {
            if (item.value > 0) {
              percent = ((item.value/total)*100).toFixed(1);
              tooltipString = `\${tooltipString}<br /><span style=\"float: left;\">\${item.marker} \${item.seriesName}:</span>&emsp;<span style=\"float: right;\">\${item.value} (\${percent}%)</span>`
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
        data: ".json_encode(array_values($year_array))."
      },
      yAxis: {
        type: 'value'
      },
      series: [\n".join(",\n", $breakdown_series)."\n      ]
    };
    myChart.setOption(option);
  </script>\n";
    // output selection of table or only chart
    output($script_onlychart, $script_onlytable, "div_breakdown", $breakdown_html_script, $breakdown_html_table);
    // end debug timing for day chart
    $breakdown_time_end = hrtime(true);
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
        $year_array = array();
        $month_chart = "";
        $month_table = "";
        $month_solar_max_header = "";
        // loop for first to actual year
        while ($year <= $year_act) {
            // variable initialization
            $month_solar = array();
            $year_array[] = strval($year);
            // define start and end time of the loop year
            $month = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year+1);
            // loop througl all month of the loop year
            while ($month < $end) {
                // define start and end time of the month to query
                $start_time = mktime(0, 0, 0, date("m", $month), 1, date("Y", $month));
                $end_time = mktime(0, 0, 0, date("m", $month)+1, 1, date("Y", $month));
                $month_solar_max_html = "";
                // InfluxDB query
                $result = $database->query('SELECT sum(solar_daily) AS solar, max(solar_max) AS solar_max, sum(bezug_daily) AS grid, sum(consumption_daily) AS consumption, sum(einspeisung_daily) AS supply FROM totals_daily WHERE time >='.$start_time.'s and time<'.$end_time.'s tz(\'Europe/Berlin\')');
                $points = $result->getPoints();
                // extract queried values and round them to full kWh and calculate usage quotas
                if (isset($points[0]['solar'])) {
                    $solar = round($points[0]['solar']/1000, 0);
                    $grid = round($points[0]['grid']/1000, 0);
                    $consumption = round($points[0]['consumption']/1000, 0);
                    $supply = round($points[0]['supply']/1000, 0);
                    $own_consumption = $solar-$supply;
                    $month_solar_max = $points[0]['solar_max'];
                } else {
                    $solar = 0;
                }
                if ($solar > 0) {
                    // check for maximul solar generation during 5min in whole month
                    if ($script_max_solar) {
                        $month_solar_max_html = "\n      <td>".round($month_solar_max, 0).$unit_w."</td>";
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
      <td>".$solar.$unit_kwh."</td>
      <td>".$grid.$unit_kwh."</td>
      <td>".$consumption.$unit_kwh."</td>
      <td>".$supply.$unit_kwh."</td>
      <td>".$own_consumption.$unit_kwh."</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>".$month_solar_max_html."
    </tr>\n".$month_table;
                } else {
                    $month_solar[] = "";
                }
                $month = strtotime("+1 month", $month);
            }
            if ($month_chart != "") {
                $month_chart = $month_chart.",";
            }
            $month_chart = $month_chart."
        {
          name: '".$year."',
          data: ".json_encode(array_values($month_solar)).",
          type: 'line',
          smooth: true,
          itemStyle: {
            color: '".c($year)."'
          },
          emphasis: {
            focus: 'series'
          },
          markPoint: {
            data: [
              {
                type: 'max'
              },
              {
                type: 'min'
              }
            ]
          },
          markLine: {
            data: [
              {
                type: 'average'
              }
            ],
            precision: 0
          }
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
    $month_html_script = "  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('div_months')".$color_chart.");
    var option = {
      title: {
        text: '".t(12)."',
        textStyle: {
          fontSize: 14
        },
        left: 'left'
      },
      legend: {
        data: ".json_encode(array_values($year_array)).",
        left: 'right'
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
        top: '40px',
        left: '5px',
        right: '30px',
        bottom: '5px',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: [".t(28)."]
      },
      yAxis: {
        type: 'value'
      },
      series: [".$month_chart."
      ]
    };
    myChart.setOption(option);
  </script>\n";
    // output selection of table or only chart
    output($script_onlychart, $script_onlytable, "div_months", $month_html_script, $month_html_table);
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
        if ($script_days > 0) {
            $time_in_days = strtotime("-".$script_days." day", time());
        } else {
            $time_in_days = mktime(0, 0, 0, 1, 1, $year);
        }
        $day_in_days = date("z", $time_in_days);
        $year_in_days = date('Y', $time_in_days);
        $year_array = array();
        $day_chart = "";
        $day_table = "";
        $day_actual = new DateTime();
        // loop for first to actual year
        while ($year <= $year_act) {
            // variable initialization
            $day_solar = array();
            $year_array[] = strval($year);
            // define start and end time of the loop year
            $start_time = mktime(0, 0, 0, 1, 1, $year);
            $end_time = mktime(0, 0, 0, 1, 1, $year+1);
            // InfluxDB query for whole year incl. Timezone setting!
            $result = $database->query('SELECT solar_daily AS solar, bezug_daily AS grid, consumption_daily AS consumption, einspeisung_daily AS supply, solar_max AS solar_max, consumption_min AS consumption_min FROM totals_daily WHERE time>='.$start_time.'s and time<'.$end_time.'s tz(\'Europe/Berlin\')');
            $points = $result->getPoints();
            $day_of_year = 0;
            $day_count = 0;
            foreach ($points as $day) {
                $day_no = date("z", strtotime($day['time']));
                while ($day_no > $day_of_year) {
                    $day_solar[] = [date("2020-01-z", strtotime($day['time'])), ""];
                    $day_of_year = $day_of_year + 1;
                }
                $day_of_year = $day_of_year + 1;
                $solar = round($day['solar']/1000, 1);
                $grid = round($day['grid']/1000, 1);
                $consumption = round($day['consumption']/1000, 1);
                $supply = round($day['supply']/1000, 1);
                $own_consumption = $solar-$supply;
                $day_solar_max_html = "";
                $day_solar_max_header = "";
                if ($solar > 0) {
                    if ($script_max_solar) {
                        $day_solar_max_html = "\n      <td>".round($day['solar_max'], 0).$unit_w."</td>";
                        $day_solar_max_header = "\n      <th style=\"width: 90px\">".t(19)."</th>";
                    }
                    if ($script_base_line) {
                        $day_solar_max_html = $day_solar_max_html."\n      <td>".round($day['consumption_min'], 0).$unit_w."</td>";
                        $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(22)."</th>";
                    }
                    // check for maximul solar generation during 5min in day
                    if (($script_time_solar > 0 or $script_time_supply > 0 or $script_nogrid_time or $script_car_charging > 0 or $script_over_supply > 0) and $year >= $year_in_days and (($year == $year_in_days and $day_no >= $day_in_days) or ($year > $year_in_days))) {
                        // InfluxDB query
                        $start_time = strtotime($day['time']);
                        $end_time = strtotime("+1 day", $start_time);
                        $result = $database->query('SELECT solar_5min AS solar, bezug_5min as grid, einspeisung_5min as supply FROM actuals_5min WHERE time >='.$start_time.'s and time <='.$end_time.'s tz(\'Europe/Berlin\')');
                        $points = $result->getPoints();
                        $day_nogrid_time = 0;
                        $day_car_charging_time = 0;
                        $day_car_charging_kwh = 0;
                        $day_over_supply = 0;
                        $day_first_solar_time = "";
                        $day_last_solar_time = "";
                        $day_first_supply_time = "";
                        $day_last_supply_time = "";
                        foreach ($points as $value) {
                            if ($value['solar'] > $script_time_solar && $day_first_solar_time == "") {
                                $day_first_solar_time = $value['time'];
                            }
                            if ($value['solar'] > $script_time_solar) {
                                $day_last_solar_time = $value['time'];
                            }
                            if ($value['supply'] > $script_time_supply && $day_first_supply_time == "") {
                                $day_first_supply_time = $value['time'];
                            }
                            if ($value['supply'] > $script_time_supply) {
                                $day_last_supply_time = $value['time'];
                            }
                            if (intval($value['grid']) == 0 && is_numeric($value['grid'])) {
                                $day_nogrid_time = $day_nogrid_time + 5;
                            }
                            if ($value['supply'] >= $script_car_charging) {
                                $day_car_charging_time = $day_car_charging_time + 5;
                                $day_car_charging_kwh = $day_car_charging_kwh + ($value['supply']/12/1000);
                            }
                            if ($value['supply'] > $script_over_supply) {
                                $day_over_supply = $day_over_supply + (($value['supply']-$script_over_supply)/12/1000);
                          }
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
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(20).$script_time_solar."W ".t(2)."</th>
      <th style=\"width: 90px\">".t(21).$script_time_solar."W ".t(2)."</th>";
                        }
                        if ($script_time_supply > 0) {
                          if ($day_first_supply_time != "") {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>".date("H:i", strtotime($day_first_supply_time))."</td>";
                          } else {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>---</td>";
                          }
                          if ($day_last_supply_time != "") {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>".date("H:i", strtotime($day_last_supply_time))."</td>";
                          } else {
                              $day_solar_max_html = $day_solar_max_html."\n      <td>---</td>";
                          }
                          $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(20).$script_time_supply."W ".t(5)."</th>
                          <th style=\"width: 90px\">".t(21).$script_time_supply."W ".t(5)."</th>";
                        }
                        if ($script_nogrid_time) {
                            $day_solar_max_html = $day_solar_max_html."\n      <td>".$day_nogrid_time." min</td>";
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(23)."</th>";
                        }
                        if ($script_car_charging > 0) {
                            $day_solar_max_html = $day_solar_max_html."\n      <td>".d(round($day_car_charging_kwh, 1)).$unit_kwh."</td>";
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(24).$script_car_charging.$unit_w."</th>";
                        }
                        if ($script_over_supply > 0) {
                            $day_solar_max_html = $day_solar_max_html."\n      <td>".d(round($day_over_supply, 1)).$unit_kwh."</td>";
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(25).$script_over_supply.$unit_w."</th>";
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
                    $day_count++;
                    $day_solar[] = [date("2020-m-d", strtotime($day['time'])), $solar];
                    $day_current_item = new DateTime($day['time']);
                    $day_difference = $day_current_item->diff($day_actual);
                    if ($day_difference->days < $script_days or $script_days == 0) {
                        $day_table = "    <tr>
      <td>".date("d.m.Y", strtotime($day['time']))."</td>
      <td>".d($solar).$unit_kwh."</td>
      <td>".d($grid).$unit_kwh."</td>
      <td>".d($consumption).$unit_kwh."</td>
      <td>".d($supply).$unit_kwh."</td>
      <td>".d($own_consumption).$unit_kwh."</td>
      <td>".$self_consumption." %</td>
      <td>".$self_sufficiency." %</td>".$day_solar_max_html."
    </tr>\n".$day_table;
                    }
                }
            }
            if ($day_chart != "") {
                $day_chart = $day_chart.",";
            }
            $day_chart = $day_chart."
        {
          name: '".$year."',
          data: ".json_encode(array_values($day_solar)).",
          type: 'line',
          smooth: true,
          itemStyle: {
            color: '".c($year)."'
          },
          emphasis: {
            focus: 'series'
          },
          markPoint: {
            data: [
              {
                type: 'max'
              },
              {
                type: 'min'
              }
            ]
          },
          markLine: {
            data: [
              {
                type: 'average'
              }
            ],
            precision: 1
          }
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
    if ($day_table == "") {
        if ($script_max_solar) {
            $day_solar_max_html = "\n      <td></td>";
        }
        if ($script_base_line) {
            $day_solar_max_html = $day_solar_max_html."\n      <td></td>";
        }
        if ($script_time_solar) {
            $day_solar_max_html = $day_solar_max_html."\n      <td></td>\n      <td></td>";
        }
        if ($script_time_supply) {
            $day_solar_max_html = $day_solar_max_html."\n      <td></td>\n      <td></td>";
        }
        if ($script_nogrid_time) {
            $day_solar_max_html = $day_solar_max_html."\n      <td></td>";
        }
        if ($script_car_charging > 0) {
                $day_solar_max_html = $day_solar_max_html."\n      <td></td>";
        }
        if ($script_over_supply > 0) {
            $day_solar_max_html = $day_solar_max_html."\n      <td></td>";
        }
        $day_table = "    <tr>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>".$day_solar_max_html."
    </tr>\n";
    }
    $day_html_table = $day_html_table."\n    </tr>\n".$day_table."  </table>";
    $day_html_script = "\n  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('div_days')".$color_chart.");
    var option = {
      title: {
        text: '".t(13)."',
        textStyle: {
          fontSize: 14
        },
        left: 'left'
      },
      legend: {
        data: ".json_encode(array_values($year_array)).",
        left: 'right'
      },
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var date = new Date(params[0].value[0]);
          var tooltipString = date.getDate() + '.' + (date.getMonth() + 1) + '. :' 
          params.forEach(function (item, index) {
            tooltipString = `\${tooltipString}<br /><span style=\"float: left;\">\${item.marker} \${item.seriesName}:</span>&emsp;<span style=\"float: right;\">\${item.value[1]}</span>`
          });
          return tooltipString;
        },
        axisPointer: {
          animation: false
        }
      },
      grid: {
        top: '40px',
        left: '5px',
        right: '32px',
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
      series: [".$day_chart."
      ]
    };
    myChart.setOption(option);
  </script>\n";
    // output selection of table or only chart
    output($script_onlychart, $script_onlytable, "div_days", $day_html_script, $day_html_table);
    // end debug timing for day chart
    $day_time_end = hrtime(true);
    }
}

// hour chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'hour') !== false) {
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for month chart
        $hour_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $hour_array = array();
        $hour_html_table = array();
        // loop for first to actual year
        while ($year <= $year_act) {
            // define start and end time of the loop year
            $month = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year+1);
            // loop througl all month of the loop year
            while ($month < $end) {
                // define start and end time of the month to query
                $start_time = mktime(0, 0, 0, date("m", $month), 1, date("Y", $month));
                $end_time = mktime(0, 0, 0, date("m", $month)+1, 1, date("Y", $month));
                // InfluxDB query
                $result = $database->query('SELECT solar_60min AS solar FROM actuals_60min WHERE time >='.$start_time.'s and time <='.$end_time.'s tz(\'Europe/Berlin\')');
                $points = $result->getPoints();
                foreach ($points as $value) {
                    $temp_month = date("n", strtotime($value['time']));
                    $temp_hour = date("G", strtotime($value['time']));
                    if (isset($hour_array[$temp_month][$temp_hour]['no_values'])) {
                        $hour_array[$temp_month][$temp_hour]['no_values']++;
                        $hour_array[$temp_month][$temp_hour]['value'] += $value['solar'];
                    } else {
                        $hour_array[$temp_month][$temp_hour]['no_values'] = 1;
                        $hour_array[$temp_month][$temp_hour]['value'] = $value['solar'];
                    }
                }
                $month = strtotime("+1 month", $month);
            }
            $year = $year + 1;
        }
        $hour_html_table[] = "  <table>
    <tr>
      <th style=\"width: 60px\">".t(27)."</th>";
        for ($i = 0; $i < 24; $i++) {
            $hour_html_table[] = "      <th style=\"width: 40px\">".str_pad($i, 2, '0', STR_PAD_LEFT)."</th>";
        }
        if (!$script_onlytable) {
            $hour_html_table[] = "      <th style=\"width: 710px\">".t(9)."</th>";
        }
        $hour_html_table[] = "    </tr>";
        $hour_chart_array = array();
        $hour_first = 23;
        $hour_last = 0;
        $hour_min = 10000;
        $hour_max = 0;
        for($month = 1; $month<=12; $month++) {
            for ($hour = 0; $hour <= 23; $hour++) {
                if (isset($hour_array[$month][$hour])) {
                    if ($hour_array[$month][$hour]['no_values'] > 0 && $hour_array[$month][$hour]['value'] > 0) {
                        if ($hour > $hour_last) {
                            $hour_last = $hour;
                        }
                        if ($hour < $hour_first) {
                            $hour_first = $hour;
                        }
                    }
                }
              }
        }
        $hour_list = array();
        for($hour = $hour_first; $hour<=$hour_last; $hour++) {
            $hour_list[] = $hour;
        }
        for($month = 1; $month<=12; $month++) {
            $hour_html_table[] = "    <tr>
      <td>".$month."</td>";
            for ($hour = 0; $hour <= 23; $hour++) {
                $hour_no_value = 0;
                $hour_value = 0;
                if (isset($hour_array[$month][$hour])) {
                    if ($hour_array[$month][$hour]['no_values'] > 0 && $hour_array[$month][$hour]['value'] > 0) {
                        $hour_value = round($hour_array[$month][$hour]['value']/$hour_array[$month][$hour]['no_values'], 0);
                        $hour_chart_array[] = [12-$month, $hour-$hour_first, $hour_value];
                        if ($hour_value > $hour_max) {
                            $hour_max = $hour_value;
                        }
                        if ($hour_value < $hour_min) {
                            $hour_min = $hour_value;
                        }
                    }
                } else {
                    $hour_value = 0;
                }
                $hour_html_table[] = "      <td>".$hour_value."</td>";
              }
              $hour_html_table[] = "    </tr>";
        }
        $hour_html_table[] = "  </table>";
        $hour_html_table = join("\n", $hour_html_table);
      if ($hour_min == 10000) { $hour_min = 0; }
      $hour_html_script = "\n  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('div_hours')".$color_chart.");
    var data = ".json_encode(array_values($hour_chart_array)).";
    data = data.map(function (item) {
      return [item[1], item[0], item[2] || '-'];
    });
    option = {
      title: {
        text: '".t(29)."',
        textStyle: {
          fontSize: 14
        },
        left: 'left'
      },
      tooltip: {
        position: 'top'
      },
      grid: {
        top: '30px',
        left: '5px',
        right: '5px',
        bottom: '5px',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        data: ".json_encode(array_values($hour_list)).",
        splitArea: {
          show: true
        }
      },
      yAxis: {
        type: 'category',
        data: ['12', '11', '10', '9', '8', '7', '6','5', '4', '3', '2','1'],
        splitArea: {
          show: true
        }
      },
      visualMap: {
        min: ".$hour_min.",
        max: ".$hour_max.",
        show: false
      },
      series: [
        {
          type: 'heatmap',
          data: data,
          label: {
            show: true
          }
        }
      ]
    };
    myChart.setOption(option);
  </script>\n";
        // output selection of table or only chart
        output($script_onlychart, $script_onlytable, "div_hours", $hour_html_script, $hour_html_table);
    }
    // end debug timing for day chart
    $hour_time_end = hrtime(true);
}

// function for evaluating runtime
if ($script_timing) {
    if (isset($year_time_start) && isset($year_time_end)) {
        $year_runtime = round(($year_time_end-$year_time_start)/1e+6, 0);
        print("  ".t(14).": ".$year_runtime."ms\n  <br>\n");
    }
    if (isset($breakdown_time_start) && isset($breakdown_time_end)) {
        $breakdown_runtime = round(($breakdown_time_end-$breakdown_time_start)/1e+6, 0);
        print("  ".t(30).": ".$breakdown_runtime."ms\n  <br>\n");
    }
    if (isset($month_time_start) && isset($month_time_end)) {
        $month_runtime = round(($month_time_end-$month_time_start)/1e+6, 0);
        print("  ".t(15).": ".$month_runtime."ms\n  <br>\n");
    }
    if (isset($day_time_start) && isset($day_time_end)) {
        $day_runtime = round(($day_time_end-$day_time_start)/1e+6, 0);
        print("  ".t(16).": ".$day_runtime."ms\n  <br>\n");
    }
    if (isset($hour_time_start) && isset($hour_time_end)) {
      $hour_runtime = round(($hour_time_end-$hour_time_start)/1e+6, 0);
      print("  ".t(26).": ".$hour_runtime."ms\n  <br>\n");
    }
    $script_time_end = hrtime(true);
    $script_runtime = round(($script_time_end-$script_time_start)/1e+6, 0);
    print("  ".t(17).": ".$script_runtime."ms\n  <br>\n");
}

// html footer
print("</body>\n</html>");
?>