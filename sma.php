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
$dict['de'] = array(1 => 'Jahr', 2 => 'Solar', 3 => 'Netzbezug', 4 => 'Verbrauch', 5 => 'Einspeisung', 6 => 'Eigen- verbrauch', 7 => 'Eigen- verbrauchsquote', 8 => 'Autarkie- grad', 9 => 'Grafik', 10 => "Monat", 11 => 'Solar Erzeugung pro Jahr', 12 => 'Solar Erzeugung pro Monat', 13 => 'Solar Erzeugung pro Tag des Jahres', 14 => 'Generierungzeit Jahres Tabelle', 15 => 'Generierungzeit Monats Tabelle', 16 => 'Generierungzeit Tages Tabelle', 17 => 'Gesamt Generierungzeit', 18 => 'Tag', 19 => 'Max. 5min Solar', 20 => 'Erste Zeit >', 21 => 'Letzte Zeit >', 22 => 'Minimalster Strom Verbrauch', 23 => 'Zeit ohne Netzbezg', 24 => 'Ladeleistung Auto >', 25 => 'Einspeisung über ', 26 => 'Generierungzeit Stunden Tabelle', 27 => 'Monat / Stunde', 28 => '\'Jan\', \'Feb\', \'Mär\', \'Apr\', \'Mai\', \'Jun\', \'Jul\', \'Aug\', \'Sep\', \'Okt\', \'Nov\', \'Dez\'');
$dict['en'] = array(1 => 'Year', 2 => 'Solar', 3 => 'Grid', 4 => 'Consumption', 5 => 'Supply', 6 => 'Own Consumption', 7 => 'Self Consumption', 8 => 'Self Sufficiency', 9 => 'Chart', 10 => "Month", 11 => 'Solar Energy Generation per Year', 12 => 'Solar Energy per Months', 13 => 'Solar Energy per Day of the Year', 14 => 'Year Table Generation Time', 15 => 'Month Table Generation Time', 16 => 'Day Table Generation Time', 17 => 'Total Generation Time', 18 => 'Day', 19 => 'Peak 5min Solar', 20 => 'First time >', 21 => 'Last time >', 22 => 'Minium Power Consumption', 23 => 'Time without grid power', 24 => 'Car charging power >', 25 => 'Grid supply over ', 26 => 'Hour Table Generation Time', 27 => 'Month / Hour', 28 => '\'Jan\', \'Feb\', \'Mar\', \'Apr\', \'May\', \'Jun\', \'Jul\', \'Aug\', \'Sep\', \'Oct\', \'Nov\', \'Dec\'');
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
$script_max_solar = FALSE;
switch(getenv('max_solar')) {
    case "yes":
        $script_max_solar = TRUE;
}
if (isset($_GET['max_solar'])) {
    if ($_GET['max_solar'] != "no") {
        $script_max_solar = TRUE;
    } else {
        $script_max_solar = FALSE;
    }
}

// solar times
$script_time_solar = 0;
if (getenv('time_solar') > 0) {
    $script_time_solar = getenv('time_solar');
}
if (isset($_GET['time_solar'])) {
    if ($_GET['time_solar'] > 0) {
        $script_time_solar = $_GET['time_solar'];
    } elseif ($_GET['time_solar'] == 0) {
        $script_time_solar = 0;
    } else {
        $script_time_solar = 100;
    }
}

// days for day table
$script_days = 0;
if (getenv('days') > 0) {
    $script_days = getenv('days');
}
if (isset($_GET['days'])) {
    if ($_GET['days'] >= 0) {
        $script_days = $_GET['days'];
    }
}

// car charging
$script_car_charging = 0;
if (getenv('car_charging') > 0) {
    $script_car_charging = getenv('car_charging');
}
if (isset($_GET['car_charging'])) {
    if ($_GET['car_charging'] > 0) {
        $script_car_charging = $_GET['car_charging'];
    }
}

// over supply
$script_over_supply = 0;
if (getenv('over_supply') > 0) {
    $script_over_supply = getenv('over_supply');
}
if (isset($_GET['over_supply'])) {
    if ($_GET['over_supply'] > 0) {
        $script_over_supply = $_GET['over_supply'];
    }
}

// time without power from grid
$script_nogrid_time = FALSE;
switch(getenv('nogrid_time')) {
    case "yes":
        $script_nogrid_time = TRUE;
}
if (isset($_GET['nogrid_time'])) {
    if ($_GET['nogrid_time'] != "no") {
        $script_nogrid_time = TRUE;
    } else {
        $script_nogrid_time = FALSE;
    }
}

// base line power (lowest power consumption during 5min)
$script_base_line = FALSE;
switch(getenv('baseline')) {
    case "yes":
        $script_base_line = TRUE;
}
if (isset($_GET['baseline'])) {
    if ($_GET['baseline'] != "no") {
        $script_base_line = TRUE;
    } else {
        $script_base_line = FALSE;
    }
}

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
$script_onlychart = FALSE;
switch(getenv('onlychart')) {
  case "yes":
      $script_onlychart = TRUE;
}
if (isset($_GET['onlychart'])) {
  if ($_GET['onlychart'] != "no") {
      $script_onlychart = TRUE;
  } else {
      $script_onlychart = FALSE;
  }
}

// only table output value check
$script_onlytable = FALSE;
switch(getenv('onlytable')) {
  case "yes":
      $script_onlytable = TRUE;
}
if (isset($_GET['onlytable'])) {
  if ($_GET['onlytable'] != "no") {
      $script_onlytable = TRUE;
  } else {
      $script_onlytable = FALSE;
  }
}

// actual dates
$year_act = date("Y");
$month_act = date("m");

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
<body>
  <script src=\"echarts.js\"></script>\n");

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
            $year_array[] = $year;
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
    var myChart = echarts.init(document.getElementById('div_years'));
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
    if ($script_onlychart) {
        print("  <div id=\"div_years\" style=\"width: 650px; height: 320px\"></div>\n".$year_html_script);
    } elseif ($script_onlytable) {
        print($year_html_table.$year_html_script."  <br>\n");
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
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for month chart
        $month_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $year_array = array();
        $month_chart = "";
        $month_table = "";
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
                $month_solar_max_header = "";
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
                        {type: 'max'},
                        {type: 'min'}
                    ]
                },
                markLine: {
                    data: [
                        {type: 'average'}
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
    var myChart = echarts.init(document.getElementById('div_months'));
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
        series: [".$month_chart."]
    };
    myChart.setOption(option);
</script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"div_months\" style=\"width: 650px; height: 320px\"></div>\n".$month_html_script);
    } elseif ($script_onlytable) {
        print($month_html_table.$month_html_script."  <br>\n");
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
            $result = $database->query('SELECT solar_daily AS solar, bezug_daily AS grid, consumption_daily AS consumption, einspeisung_daily AS supply, solar_max AS solar_max FROM totals_daily WHERE time>='.$start_time.'s and time<'.$end_time.'s tz(\'Europe/Berlin\')');
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
                $day_solar_max = $day['solar_max'];
                $own_consumption = $solar-$supply;
                $day_solar_max_html = "";
                $day_solar_max_header = "";
                if ($solar > 0) {
                    if ($script_max_solar) {
                        $day_solar_max_html = "\n      <td>".round($day_solar_max, 0).$unit_w."</td>";
                        $day_solar_max_header = "\n      <th style=\"width: 90px\">".t(19)."</th>";
                    }
                    // check for maximul solar generation during 5min in day
                    if ($script_base_line or $script_time_solar > 0 or $script_nogrid_time or $script_car_charging > 0 or $script_over_supply > 0) {
                        // InfluxDB query
                        $start_time = strtotime($day['time']);
                        $end_time = strtotime("+1 day", $start_time);
                        $result = $database->query('SELECT solar_5min AS solar, consumption_5min AS consumption, bezug_5min as grid, einspeisung_5min as supply FROM actuals_5min WHERE time >='.$start_time.'s and time <='.$end_time.'s tz(\'Europe/Berlin\')');
                        $points = $result->getPoints();
                        $day_nogrid_time = 0;
                        $day_car_charging_time = 0;
                        $day_car_charging_kwh = 0;
                        $day_over_supply = 0;
                        $day_first_solar_time = "";
                        $day_last_solar_time = "";
                        $day_base_line = 100000;
                        foreach ($points as $value) {
                            if ($value['consumption'] < $day_base_line && $value['consumption'] > 0 && $value['consumption'] != "") {
                                $day_base_line = round($value['consumption'], 0);
                            }
                            if ($value['solar'] > $script_time_solar && $day_first_solar_time == "") {
                                $day_first_solar_time = $value['time'];
                            }
                            if ($value['solar'] > $script_time_solar) {
                                $day_last_solar_time = $value['time'];
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
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(20).$script_time_solar."W</th>
      <th style=\"width: 90px\">".t(21).$script_time_solar."W</th>";
                        }
                        if ($script_base_line) {
                            if ($day_base_line != 100000) {
                                $day_solar_max_html = $day_solar_max_html."\n      <td>".$day_base_line.$unit_w."</td>";
                            } else {
                                $day_solar_max_html = $day_solar_max_html."\n      <td>---</td>";
                            }
                            $day_solar_max_header = $day_solar_max_header."\n      <th style=\"width: 90px\">".t(22)."</th>";
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
                        {type: 'max'},
                        {type: 'min'}
                    ]
                },
                markLine: {
                    data: [
                        {type: 'average'}
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
    $day_html_table = $day_html_table."\n    </tr>\n".$day_table."  </table>";
    $day_html_script = "\n  <script type=\"text/javascript\">
    var myChart = echarts.init(document.getElementById('div_days'));
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
        series: [".$day_chart."]
    };
    myChart.setOption(option);
</script>\n";
    // output selection of table or only chart
    if ($script_onlychart) {
        print("  <div id=\"div_days\" style=\"width: 650px; height: 320px\"></div>\n".$day_html_script);
    } elseif ($script_onlytable) {
        print($day_html_table.$day_html_script."  <br>\n");
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

// hour chart
if (strpos($script_chart, 'all') !== false or strpos($script_chart, 'hour') !== false) {
    // only continue if really data is available in database
    if (isset($year_first)) {
        // start debug timing for month chart
        $hour_time_start = hrtime(true);
        // variable initialization
        $year = $year_first;
        $hour_array = array();
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
        $hour_html_table = "  <table>
        <tr>
          <th style=\"width: 60px\">".t(27)."</th>
          <th style=\"width: 40px\">00</th>
          <th style=\"width: 40px\">01</th>
          <th style=\"width: 40px\">02</th>
          <th style=\"width: 40px\">03</th>
          <th style=\"width: 40px\">04</th>
          <th style=\"width: 40px\">05</th>
          <th style=\"width: 40px\">06</th>
          <th style=\"width: 40px\">07</th>
          <th style=\"width: 40px\">08</th>
          <th style=\"width: 40px\">09</th>
          <th style=\"width: 40px\">10</th>
          <th style=\"width: 40px\">11</th>
          <th style=\"width: 40px\">12</th>
          <th style=\"width: 40px\">13</th>
          <th style=\"width: 40px\">14</th>
          <th style=\"width: 40px\">15</th>
          <th style=\"width: 40px\">16</th>
          <th style=\"width: 40px\">17</th>
          <th style=\"width: 40px\">18</th>
          <th style=\"width: 40px\">19</th>
          <th style=\"width: 40px\">20</th>
          <th style=\"width: 40px\">21</th>
          <th style=\"width: 40px\">22</th>
          <th style=\"width: 40px\">23</th>
        <tr>\n";
        ksort($hour_array);
        foreach (array_keys($hour_array) as $month) {
            $hour_html_table = $hour_html_table."    <tr>
          <td>".$month."</td>\n";
            for ($hour = 0; $hour <= 23; $hour++) {
                $hour_no_value = 0;
                $hour_value = 0;
                if (isset($hour_array[$month][$hour])) {
                    if ($hour_array[$month][$hour]['no_values'] > 0 && $hour_array[$month][$hour]['value'] > 0) {
                        $hour_value = round($hour_array[$month][$hour]['value']/$hour_array[$month][$hour]['no_values'], 0);
                    }
                } else {
                    $hour_value = 0;
                }
                $hour_html_table = $hour_html_table."            <td>".$hour_value."</td>\n";
              }
            $hour_html_table = $hour_html_table."    </tr>\n";
        }
        $hour_html_table = $hour_html_table."        </tr>
      </table>\n";
        print($hour_html_table."  <br>\n");
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