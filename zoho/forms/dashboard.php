<?php
// Scaff DB (Local)
$scaffConn = new mysqli('localhost', 'scaff', 'Scaff@inc9999', 'scaff');
if ($scaffConn->connect_error) die("Scaff DB failed: " . $scaffConn->connect_error);

// iLink DB (Local)
$ilinkConn = new mysqli('localhost', 'linkzoho', 'Ilink@zoho9', 'linkzoho');
if ($ilinkConn->connect_error) die("iLink DB failed: " . $ilinkConn->connect_error);

// BestTruck DB (HostGator)
$bestTruckConn = new mysqli('cs2001.hostgator.in', 'ilinksm3_bt', 'BestTruck@123', 'ilinksm3_bt', 3306);
if ($bestTruckConn->connect_error) die("BestTruck DB failed: " . $bestTruckConn->connect_error);

// PlantMachinery DB (Local, same server as others)
$plantConn = new mysqli('localhost', 'ilinksm3_pmzoho', 'PmZoho@9999', 'ilinksm3_pmzoho');
if ($plantConn->connect_error) die("Plant DB failed: " . $plantConn->connect_error);
?>
