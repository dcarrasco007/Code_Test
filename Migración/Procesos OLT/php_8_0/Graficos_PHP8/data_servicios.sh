#!/bin/bash
cd /var/www/procesos/php/Graficos_PHP8
flock -n /tmp/data_servicios.lock php -f data_servicios.php