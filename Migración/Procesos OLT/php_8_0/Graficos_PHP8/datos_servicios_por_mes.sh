#!/bin/bash
cd /var/www/procesos/php/Graficos_PHP8
flock -n /tmp/datos_servicios_por_mes.lock php -f datos_servicios_por_mes.php