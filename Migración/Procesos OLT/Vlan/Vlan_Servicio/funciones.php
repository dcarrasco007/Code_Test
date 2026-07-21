<?php
function vlanInternet(){
    return $mibPorSlot = array(
        '1960','1965','1971','3450','3650','3610',      
    );
}
function vlanVoz(){
    return $mibPorSlot = array(
       '1963','1966','1972','3550','3660','3620',   
    );
}
function vlanTV(){
    return $mibPorSlot = array(
        '1967','1973','3670','3630',    
    );
}
function vlanGestion(){
    return $mibPorSlot = array(
        '1968','3680',       
    );
}

?>