# sma-stats
Script to visualize statistics about SMA Inverter and other meters.


## SMA Statistics

The "sma.php" script list and visualizes the [SMA Query](https://github.com/Froschie/sma-query) data from the SMA Inverter for year, month and daily statistics.

<img src="https://raw.githubusercontent.com/Froschie/sma-stats/master/sma-stats.png" width="840" height="410" alt="SMA Statistics Screenshot">

| Option | Docker Env | URL Param | Description |
| --- | --- | --- | --- |
| smadb_ip | x | - | InfluxDB IP |
| smadb_port | x | - | InfluxDB Port |
| smadb_db | x | - | InfluxDB DB Name |
| smadb_user | x | - | InfluxDB User |
| smadb_pw | x | - | InfluxDB Password |
| lang | x | x | Script output language selection. Possible values: "de" or "en". |
| table_borders | x | x | Hides table borders in HTML by setting it to "no". |
| chart | x | x | Chart selection. Multiple values possible: "all", "year", "month" or "day". |
| onlychart | x | x | Show only Chart graph(s) but no tables. |
| onlytable | x | x | Show only HTML table and no graphs. |
| max_solar | x | x | Shows max. peak solar generation within 5min periode from year/month/day if set to "yes". |
| time_solar | x | x | Shows column with first and last time solar generation was over specified value within 5min periode of the day. Only for day table! Default value = 100W. |
| baseline | x | x | Shows colum for the smallest power consumption within 5min periode of the day. Only for day table! |
| nogrid_time | x | x | Shows colum for the time where no power from grid is consumed. |
| car_charging | x | x | Shows colum for possible kWh which could be used to charge a electric car having a definable minimum charging Watt level. |
| over_supply | x | x | Shows colum for power supply to grid which is above a defined limit. |
| timing | - | x | Debug option to show script runtimes |

Example request: `http://192.168.1.1:8080/sma.php?lang=de&table_borders=no&chart=monthday&timing&max_solar&baseline&nogrid_time&car_charging=1400&over_supply=3000`


## Electric Meter Statistics

The "em.php" script list and visualizes the [Electric Meter](https://github.com/Froschie/electrical-meter) data from an Electric Meter Reader for yearly and monthly statistics.

<img src="https://raw.githubusercontent.com/Froschie/sma-stats/master/em-stats.png" width="840" height="337" alt="EM Statistics Screenshot">

| Option | Docker Env | URL Param | Description |
| --- | --- | --- | --- |
| emdb_ip | x | - | InfluxDB IP |
| emdb_port | x | - | InfluxDB Port |
| emdb_db | x | - | InfluxDB DB Name |
| emdb_user | x | - | InfluxDB User |
| emdb_pw | x | - | InfluxDB Password |
| lang | x | x | Script output language selection. Possible values: "de" or "en". |
| table_borders | x | x | Hides table borders in HTML by setting it to "no". |
| chart | x | x | Chart selection. Multiple values possible: "all", "year" or "month". |
| timing | - | x | Debug option to show script runtimes |

Example request: `http://192.168.1.1:8080/em.php?lang=de&table_borders=no&chart=month&timing`


## Water Meter Statistics

The "em.php" script list and visualizes the [Water Meter](https://github.com/Froschie/water-meter) data from an Water Meter Reader for yearly, monthly and daily statistics.

<img src="https://raw.githubusercontent.com/Froschie/sma-stats/master/wm-stats.png" width="498" height="410" alt="WM Statistics Screenshot">

| Option | Docker Env | URL Param | Description |
| --- | --- | --- | --- |
| wmdb_ip | x | - | InfluxDB IP |
| wmdb_port | x | - | InfluxDB Port |
| wmdb_db | x | - | InfluxDB DB Name |
| wmdb_user | x | - | InfluxDB User |
| wmdb_pw | x | - | InfluxDB Password |
| lang | x | x | Script output language selection. Possible values: "de" or "en". |
| table_borders | x | x | Hides table borders in HTML by setting it to "no". |
| chart | x | x | Chart selection. Multiple values possible: "all", "year", "month" or "day". |
| onlychart | - | x | Show only Chart graph(s) but no tables |
| timing | - | x | Debug option to show script runtimes |

Example request: `http://192.168.1.1:8080/wm.php?lang=de&table_borders=no&chart=monthday&timing`


## Create a Docker Container

```bash
mkdir sma-stats
cd sma-stats/
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/Dockerfile
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/sma.php
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/em.php
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/wm.php
docker build --tag sma-stats .
```


## Start Docker Container via Docker-Compose File
```bash
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/docker-compose.yaml
vi docker-compose.yaml
docker-compose up -d
```
*Note: please adapt the parameters as needed! Don´t override your existing docker compose file!*
