# sma-stats
Script to visualize statistics about SMA Inverter and other meters.


## SMA Statistics

<img src="https://raw.githubusercontent.com/Froschie/sma-stats/master/sma-stats.png" width="840" height="410" alt="SMA Statistics Screenshot">


## Create a Docker Container

```bash
mkdir sma-stats
cd sma-stats/
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/Dockerfile
curl -O https://raw.githubusercontent.com/Froschie/sma-stats/master/sma.php
docker build --tag sma-stats .
```
