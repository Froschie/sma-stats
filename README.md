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
| table_borders | x | x | Hides table borders in HTML tables is set to "no". |
| chart | x | x | Chart selection. Multiple values possible: "all", "year", "month" or "day". |
| onlychart | - | x | Show only Chart graph(s) but no tables |
| timing | - | x | Debug option to show script runtimes |

Example request: `http://192.168.1.1:8001/sma.php?lang=de&table_borders=no&chart=monthday&timing`


## Create a Docker Container

```bash
mkdir sma-stats
cd sma-stats/
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/Dockerfile
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/sma.php
docker build --tag sma-stats .
```
