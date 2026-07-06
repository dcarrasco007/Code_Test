#!/bin/bash
cd /u01/crontab127/OLT/Uplink/Procesos
php -f proceso_uplink_trafico_diario_MA5600T.php > /u01/crontab127/OLT/Uplink/Procesos/proceso_uplink_trafico_diario_MA5600T.log
php -f proceso_uplink_trafico_diario_conexped.php > /u01/crontab127/OLT/Uplink/Procesos/proceso_uplink_trafico_diario_5800-x15.log

