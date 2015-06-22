<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */
 
if(!defined('INC_FROM_DOLIBARR')) 
{
    define('INC_FROM_CRON_SCRIPT', true);
    require('../config.php');
    $PDOdb=new TPDOdb;
    $PDOdb->debug=true;
}
else
{
    $PDOdb=new TPDOdb;
}

dol_include_once('/inventory/class/inventory.class.php');

$o=new TInventory();
$o->init_db_by_vars($PDOdb);

$o=new TInventorydet();
$o->init_db_by_vars($PDOdb);