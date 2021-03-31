# sma-stats ![Docker Hub Image](https://github.com/Froschie/sma-stats/workflows/Docker%20Image%20sma-stats%20build/badge.svg)

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
| dark | x | x | Enable dark mode. |
| table_borders | x | x | Hides table borders in HTML by setting it to "no". |
| chart | x | x | Chart selection. Multiple values possible: "all", "year", "breakdown", "month" or "day". |
| breakstep | x | x | Steps for the Breakdown table/charts in kWh. Default value = 5. |
| onlychart | x | x | Show only Chart graph(s) but no tables. |
| onlytable | x | x | Show only HTML table and no graphs. |
| max_solar | x | x | Shows max. peak solar generation within 5min periode from year/month/day if set to "yes". |
| time_solar | x | x | Shows column with first and last time solar generation was over specified value within 5min periode of the day. Only for day table! Default value = 100W. |
| baseline | x | x | Shows colum for the smallest power consumption within 5min periode of the day. Only for day table! |
| nogrid_time | x | x | Shows colum for the time where no power from grid is consumed. |
| car_charging | x | x | Shows colum for possible kWh which could be used to charge a electric car having a definable minimum charging Watt level. |
| days | x | x | Show only defined number of days in day table (0 = all). |
| over_supply | x | x | Shows colum for power supply to grid which is above a defined limit. |
| nounits | x | x | Hide the units ("W" / "kWh") from the tables. To hide set to "yes". |
| color | x | x | Define the graph colors for each year. Default: "*2021,00ff00;2022,ff0000*". Values must be in format "\<year>,\<colorcode>". Multiple years seperated by ";". |
| startyear | x | x | Defines the first year the statistic should be generated for. Values can be "actual" or the 4-digit year number. |
| endyear | x | x | Defines the last year the statistic should be generated for. Values can be "actual" or the 4-digit year number. |
| timing | - | x | Debug option to show script runtimes |

Example request: `http://192.168.1.1:8080/sma.php?lang=de&table_borders=no&chart=monthday&timing&max_solar&baseline&nogrid_time&car_charging=1400&over_supply=3000&startyear=2021&endyear=actual&color=2021,00ff00;2022,ff0000&dark=no`


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
| dark | emdark | x | Enable dark mode. |
| table_borders | x | x | Hides table borders in HTML by setting it to "no". |
| chart | x | x | Chart selection. Multiple values possible: "all", "year" or "month". |
| startyear | x | x | Defines the first year the statistic should be generated for. Values can be "actual" or the 4-digit year number. |
| endyear | x | x | Defines the last year the statistic should be generated for. Values can be "actual" or the 4-digit year number. |
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
| lang | wmlang | x | Script output language selection. Possible values: "de" or "en". |
| dark | wmdark | x | Enable dark mode. |
| table_borders | wmtable_borders | x | Hides table borders in HTML by setting it to "no". |
| chart | wmchart | x | Chart selection. Multiple values possible: "all", "year", "month" or "day". |
| onlychart | - | x | Show only Chart graph(s) but no tables |
| days | wmdays | x | Show only defined number of days in day table (0 = all). |
| color | wmcolor | x | Define the graph colors for each year. Default: "*2021,000055;2022,000022*". Values must be in format "\<year>,\<colorcode>". Multiple years seperated by ";". |
| startyear | x | x | Defines the first year the statistic should be generated for. Values can be "actual" or the 4-digit year number. |
| endyear | x | x | Defines the last year the statistic should be generated for. Values can be "actual" or the 4-digit year number. |
| timing | - | x | Debug option to show script runtimes |

Example request: `http://192.168.1.1:8080/wm.php?lang=de&table_borders=no&chart=monthday&timing`


## Start Docker Container  

Pull latest Image:  
`docker pull froschie/sma-stats:latest`  

Start Container:  
```
docker run -it --rm \
 -p 8080:80
 -e smadb_ip=192.168.1.3
 -e smadb_port=8086
 -e smadb_db=SMA
 -e smadb_user=user
 -e smadb_pw=pw
 -e emdb_ip=192.168.1.3
 -e emdb_port=8086
 -e emdb_db=measurements
 -e emdb_user=user
 -e emdb_pw=pw
 -e lang=en
 -e table_borders=yes
 -e chart=all
 -e max_solar=no
 -e baseline=no
 -e time_solar=0
 -e days=0
 -e nounits=no
 -e onlychart=no
 -e onlytable=no
 -e wmdb_ip=192.168.1.3
 -e wmdb_port=8086
 -e wmdb_db=measurements
 -e wmdb_user=user
 -e wmdb_pw=pw
 -e wmlang=en
 -e wmtable_borders=yes
 -e wmchart=all
 -e wmonlychart=no
 -e wmdays=14
 froschie/sma-stats
```
*Note: please adapt the parameters as needed and replace "-it --rm" with "-d" to run it permanently or use docker-compose!*  


## Start Docker Container via Docker-Compose  
```bash
curl -O https://raw.githubusercontent.com/Froschie/sma-pvoutput/main/docker-compose.yaml
vi docker-compose.yaml
docker-compose up -d
```
*Note: please adapt the parameters as needed!*


## Create a Docker Container

```bash
mkdir sma-stats
cd sma-stats/
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/Dockerfile
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/sma.php
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/em.php
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/wm.php
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/script_functions.php
docker build --tag sma-stats .
```


## Start Docker Container via Docker-Compose File
```bash
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/docker-compose.yaml
vi docker-compose.yaml
docker-compose up -d
```
*Note: please adapt the parameters as needed! Don´t override your existing docker compose file!*
