<?php

include ('../../../conexion/conexion_db.php');

$table = 'OLT_INFORMACION_ONT_DETALLE_COMPLETO';
        $primaryKey = 'id';
        $columns = array(
            array( 'db' => 'equipo',                  'dt' => 0 ),
            array( 'db' => 'ip',                   'dt' => 1 ),
            array( 'db' => 'frame_id',                   'dt' => 2 ),
            array( 'db' => 'slot_id',                'dt' => 3 ),
            array( 'db' => 'port_id',     'dt' => 4 ),
            array( 'db' => 'onu_id',             'dt' => 5 ),
            array( 'db' => 'estado',                'dt' => 6 ),
            array( 'db' => 'region',        'dt' => 7 ),
            array( 'db' => 'comuna',               'dt' => 8 ),
            array( 'db' => 'onu_name',               'dt' => 9 ),
            array( 'db' => 'modelo',               'dt' => 10 ),
            array( 'db' => 'sn_mac',               'dt' => 11 ),
            array( 'db' => 'olt_rx_onu',               'dt' => 12 ),
            array( 'db' => 'tx_optical_power',               'dt' => 13 ),
            array( 'db' => 'rx_optical_power',               'dt' => 14 ),
            array( 'db' => 'voltaje',               'dt' => 15 ),
            array( 'db' => 'temperature',               'dt' => 16 ),
            array( 'db' => 'last_up_time',               'dt' => 17 ),
            array( 'db' => 'last_down_time',               'dt' => 18 ),
            array( 'db' => 'distancia',               'dt' => 19 ),
            array( 'db' => 'line_profile_name',               'dt' => 20 ),
            array( 'db' => 'service_profile_name',               'dt' => 21 ),
            array( 'db' => 'vas_profile',               'dt' => 22 ),
            array( 'db' => 'fecha_registo',               'dt' => 23 ),
        );

        $sql_details = array(
            'host' => '172.29.159.57',
            'user' => 'adminAden',
            'pass' => 'adenl2.,123',
            'db'   => 'Aden'
        );
        
        // Include SQL query processing class
        require 'ssp.class.php';
        
        // Output data as json format
        echo json_encode(
            SSP::simple( $_GET, $sql_details, $table, $primaryKey, $columns )
        );
?>
