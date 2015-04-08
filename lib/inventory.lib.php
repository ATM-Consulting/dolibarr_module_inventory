<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/inventory.lib.php
 *	\ingroup	inventory
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function inventoryAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("inventory@inventory");

    $h = 0;
    $head = array();

    /*$head[$h][0] = dol_buildpath("/inventory/admin/inventory_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;*/
    $head[$h][0] = dol_buildpath("/inventory/admin/inventory_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@inventory:/inventory/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@inventory:/inventory/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'inventory');

    return $head;
}

function inventoryPrepareHead(&$inventory, $title='Inventaire', $get='')
{
	return array(
		array(DOL_URL_ROOT.'/custom/inventory/inventory.php?id='.$inventory->getId().$get, $title,'inventaire')
	);
}

function inventorySelectProducts(&$PDOdb, &$inventory)
{
	$except_product_id = array();
	
	foreach ($inventory->TInventorydet as $TInventorydet)
	{
		$except_product_id[] = $TInventorydet->fk_product;
	}
	
	$sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'product WHERE fk_product_type = 0 AND rowid NOT IN ('.implode(',', $except_product_id).')';
	$PDOdb->Execute($sql);
	
	$select_html = '<select style="min-width:150px;background:#FFF;font-size:12px;" name="fk_product">';
	while ($PDOdb->Get_line()) 
	{
		$select_html.= '<option value="'.$PDOdb->Get_field('rowid').'">'.$PDOdb->Get_field('ref').' - '.$PDOdb->Get_field('label').'</option>';
	}
	$select_html.= '</select>';

	return $select_html;
}
