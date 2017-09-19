<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');

}


global $db;

//$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'inventory CHANGE COLUMN date_maj tms timestamp');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'inventory ADD COLUMN tms timestamp');
$db->query('UPDATE '.MAIN_DB_PREFIX.'inventory SET tms = date_maj WHERE tms IS NULL');

//$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'inventorydet CHANGE COLUMN date_maj tms timestamp');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'inventorydet ADD COLUMN tms timestamp');
$db->query('UPDATE '.MAIN_DB_PREFIX.'inventorydet SET tms = date_maj WHERE tms IS NULL');

//$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'inventorydet CHANGE COLUMN date_cre datec datetime DEFAULT NULL');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'inventorydet ADD COLUMN datec datetime DEFAULT NULL');
$db->query('UPDATE '.MAIN_DB_PREFIX.'inventorydet SET datec = date_cre WHERE datec IS NULL');