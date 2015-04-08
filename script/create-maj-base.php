<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
require('../class/inventory.class.php');

$PDOdb=new TPDOdb;
$PDOdb->db->debug=true;

$o=new TInventory();
$o->init_db_by_vars($PDOdb);

$o=new TInventorydet();
$o->init_db_by_vars($PDOdb);