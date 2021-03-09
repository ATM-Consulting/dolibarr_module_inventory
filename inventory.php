<?php

/* Copyright (C) 2018      Alexis LAURIER              <alexis@alexislaurier.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require('config.php');

ini_set('memory_limit', '512M');

require('./class/inventory.class.php');
require('./lib/inventory.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

set_time_limit(0);
$dol_version = (float) DOL_VERSION;

if(!$user->rights->inventory->read) accessforbidden();

$langs->load("inventory@inventory");

$contextpage = 'inventoryatmcard';

include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

$hookmanager->initHooks(array('inventoryatmcard'));

_action();

function _action()
{
	global $user, $db, $conf, $langs, $hookmanager;
	$PDOdb=new TPDOdb;
	//$PDOdb->debug=true;

	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/

	$action=__get('action','list');

	switch($action) {
		case 'list':
			_liste($user, $db, $conf, $langs);

			break;

		case 'create':
			if (!$user->rights->inventory->create) accessforbidden();

			$PDOdb = new TPDOdb;
			$inventory = new TInventory;

			_fiche_warehouse($PDOdb, $user, $db, $conf, $langs, $inventory);

			break;

		case 'confirmCreate':
			if (!$user->rights->inventory->create) accessforbidden();

			$PDOdb = new TPDOdb;
			$inventory = new TInventory;
			$inventory->set_values($_REQUEST);

            $fk_inventory = $inventory->save($PDOdb);
            $fk_category = GETPOST('fk_category', 'array');
            $fk_supplier = (int)GETPOST('fk_supplier','int');
            $fk_warehouse = (int)GETPOST('fk_warehouse','int');
			$only_prods_in_stock = (int)GETPOST('OnlyProdsInStock','int');
			$inventoryWithBatchDetail = (int)GETPOST('inventoryWithBatchDetail','int');
			$inventory->per_batch = $inventoryWithBatchDetail;


			$e = new Entrepot($db);
			$e->fetch($fk_warehouse);
			$TChildWarehouses = array($fk_warehouse);
			if(method_exists($e, 'get_children_warehouses')) $e->get_children_warehouses($fk_warehouse, $TChildWarehouses);

			$sql = 'SELECT p.rowid AS fk_product, sm.fk_entrepot, SUM(sm.value) AS qty';

			// Add fields from hooks
			$parameters=array();
			$reshook=$hookmanager->executeHooks('printFieldListSelect',$parameters);    // Note that $action and $object may have been modified by hook
			$sql.=$hookmanager->resPrint;

			$sql.= ' FROM '.MAIN_DB_PREFIX.'product p';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock ps ON (p.rowid = ps.fk_product)';
			$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'stock_mouvement sm ON (p.rowid = sm.fk_product)';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_product = p.rowid)';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price pfp ON (pfp.fk_product = p.rowid)';
			$sql.= ' WHERE sm.fk_entrepot IN ('.implode(', ', $TChildWarehouses).')';
			if (is_array($fk_category) && !empty($fk_category)) $sql.= ' AND cp.fk_categorie IN ('.implode(',',$fk_category).')';
			if ($fk_supplier > 0) $sql.= ' AND pfp.fk_soc = '.$fk_supplier;

			$parameters=array('fk_category'=>$fk_category, 'fk_supplier' => $fk_supplier, 'only_prods_in_stock' => $only_prods_in_stock);
			$reshook=$hookmanager->executeHooks('printFieldListWhere',$parameters,$inventory,$action);    // Note that $action and $object may have been modified by some hooks
			if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
			else $sql.=$hookmanager->resPrint;

			$sql.= ' GROUP BY p.rowid, sm.fk_entrepot';

			if ($only_prods_in_stock > 0) $sql.= ' HAVING qty > 0';

			$sql.= ' ORDER BY p.ref ASC, p.label ASC';

			$Tab = $PDOdb->ExecuteAsArray($sql);

			foreach($Tab as &$row) {

                $inventory->add_product($PDOdb, $row->fk_product, $row->fk_entrepot, GETPOST('includeWithStockPMP','alpha')!='' );
                $product = new Product($db);
                $product->fetch($row->fk_product);
                if($inventoryWithBatchDetail && $product->hasbatch()) $inventory->add_batch($PDOdb, $row->fk_product, $row->fk_entrepot, $inventory->get_date('date_inventory', 'Y-m-d'), GETPOST('includeWithStockPMP','alpha')!='' );
			}

			$inventory->save($PDOdb);

			header('Location: '.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=edit', 1));

		case 'view':
		case 'edit':
			if (!$user->rights->inventory->write) accessforbidden();

			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			_fiche($PDOdb, $user, $db, $conf, $langs, $inventory, __get('action', 'edit', 'string'));

			break;

		case 'save':
			if (!$user->rights->inventory->write) accessforbidden();

			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			$inventory->set_values($_REQUEST);

			if ($inventory->errors)
			{
				setEventMessage($inventory->errors, 'errors');
				_fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'edit');
			}
			else
			{
				$inventory->save($PDOdb);
				header('Location: '.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=view', 1));
			}

			break;

		case 'regulate':
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

            if($inventory->status == 0) {
                $inventory->status = 1;
                $inventory->save($PDOdb);

                _fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'view');


            }
            else {
               _fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'view');
            }

			break;

		case 'changePMP':
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			$inventory->changePMP($PDOdb);

			_fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'view');

			break;

		case 'add_line':
			if (!$user->rights->inventory->write) accessforbidden();

			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');
			$fk_warehouse = __get('fk_warehouse', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			$type = (!empty($conf->use_javascript_ajax) && !empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT) ? 'string' : 'int'); //AA heu ?

			$fk_product = __get('fk_product', 0, $type);

			if ($fk_product)
			{
				$product = new Product($db);
				$product->fetch($fk_product);	// ! ref TODO vérifier quand même
				if($product->type != 0) {
					setEventMessage($langs->trans('ThisIsNotAProduct'),'errors');
				}
				else{

					//Check product not already exists
					$alreadyExists = false;
					foreach ($inventory->TInventorydet as $invdet)
					{
						if ($invdet->fk_product == $product->id
							&& $invdet->fk_warehouse == $fk_warehouse)
						{
							$alreadyExists = true;
							break;
						}
					}

					if (!$alreadyExists)
					{
					    $inventory->add_product($PDOdb, $product->id, $fk_warehouse);

					}
					else
					{
						setEventMessage($langs->trans('inventoryWarningProductAlreadyExists'), 'warnings');
					}

				}

				$inventory->save($PDOdb);
				$inventory->sort_det();
			}

			_fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'edit');

			break;

		case 'delete_line':
			if (!$user->rights->inventory->write) accessforbidden();
			$PDOdb = new TPDOdb;

			//Cette action devrais se faire uniquement si le status de l'inventaire est à 0 mais aucune vérif
			$rowid = __get('rowid', 0, 'int');
			$TInventorydet = new TInventorydet;
			$TInventorydet->load($PDOdb, $rowid);
			$TInventorydet->delete($PDOdb);

			$id = __get('id', 0, 'int');
			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			_fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'edit');

			break;
        case 'flush':
            if (!$user->rights->inventory->create) accessforbidden();

            $PDOdb = new TPDOdb;
            $id = __get('id', 0, 'int');

            $inventory = new TInventory;
            $inventory->load($PDOdb, $id);

            $inventory->deleteAllLine($PDOdb);

            setEventMessage('Inventaire vidé');

            _fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'edit');


            break;
		case 'delete':
			if (!$user->rights->inventory->create) accessforbidden();

			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			$inventory->delete($PDOdb);

			header('Location: '.dol_buildpath('/inventory/inventory.php', 1));
			exit;
			//_liste($user, $db, $conf, $langs);

		case 'printDoc':
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

			generateODT($PDOdb, $db, $conf, $langs, $inventory);
			break;

		case 'exportCSV':
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');

			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);

            exportCSV($inventory);
            exit;

            break;

		default:
			//Rien
			break;
	}

}

function _liste(&$user, &$db, &$conf, &$langs)
{
	global $dol_version, $module_helpurl;
	llxHeader('',$langs->trans('inventoryListTitle'),$module_helpurl,'');

	$form=new TFormCore;

	$inventory = new TInventory;
	$r = new TSSRenderControler($inventory);

	$sql="SELECT i.rowid, ".($dol_version >= 7 ? 'e.ref' : 'e.label').", i.date_inventory, i.fk_warehouse, i.date_cre, i.date_maj, i.status
		  FROM ".MAIN_DB_PREFIX."inventory i
		  LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON (e.rowid = i.fk_warehouse)
		  WHERE i.entity=".(int) $conf->entity;

 	if (!__get('TListTBS', 0, 'int')) $sql .= " ORDER BY i.rowid DESC";
	$hide = $dol_version >= 7 ? 'ref' : 'label';
	$THide = array($hide);

	$form=new TFormCore($_SERVER['PHP_SELF'], 'form', 'POST');

	$ATMdb=new TPDOdb;
	$lien = '/product/stock/card.php';
	if($dol_version < 3.8) $lien = '/product/stock/fiche.php';

	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'nbLine'=>'30'
		)
		,'subQuery'=>array()
		,'link'=>array(
			'fk_warehouse'=>'<a href="'.DOL_URL_ROOT.$lien.'?id=@val@">'.img_picto('','object_stock.png','',0).' @'.($dol_version >= 7 ? 'ref' : 'label').'@</a>'
		)
		,'translate'=>array()
		,'hide'=>$THide
		,'type'=>array(
			'date_cre'=>'date'
			,'date_maj'=>'datetime'
			,'date_inventory'=>'date'
		)
		,'liste'=>array(
			'titre'=>$langs->trans('inventoryListTitle')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['fk_soc']) | (int)isset($_REQUEST['fk_product'])
			,'messageNothing'=>$langs->trans('inventoryListEmpty')
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'rowid'=>'Numéro'
			,'fk_warehouse'=>'Entrepôt'
			,'date_inventory'=>'Date inventaire'
			,'date_cre'=>'Date création'
			,'date_maj'=>'Date mise à jour'
			,'status'=>'Status'
		)
		,'eval'=>array(
			'status' => '(@val@ ? img_picto("'.$langs->trans("inventoryValidate").'", "statut4") : img_picto("'.$langs->trans("inventoryDraft").'", "statut3"))'
			,'rowid'=>'TInventory::getLink(@val@)'

		)
	));

	$form->end();

	if ($user->rights->inventory->create)
	{
		print '<div class="tabsAction">';
		print '<a class="butAction" href="inventory.php?action=create">'.$langs->trans('inventoryCreate').'</a>';
		print '</div>';
	}

	$ATMdb->close();

	llxFooter('');
}

function _fiche_warehouse(&$PDOdb, &$user, &$db, &$conf, $langs, $inventory)
{
	global $module_helpurl;
	dol_include_once('/categories/class/categorie.class.php');

	llxHeader('',$langs->trans('inventorySelectWarehouse'),$module_helpurl,'');
	print dol_get_fiche_head(inventoryPrepareHead($inventory));

	$form=new TFormCore('inventory.php', 'confirmCreate');
	print $form->hidden('action', 'confirmCreate');
	$form->Set_typeaff('edit');

    $formproduct = new FormProduct($db);
    $formDoli = new Form($db);

    ?>
    <table class="border" width="100%" >
        <tr>
            <td><?php echo $langs->trans('Title') ?></td>
            <td><?php echo $form->texte('', 'title', '',50,255) ?></td>
        </tr>
        <tr>
            <td><?php echo $langs->trans('Date') ?></td>
            <td><?php echo $form->calendrier('', 'date_inventory',time()) ?></td>
        </tr>

        <tr>
            <td><?php echo $langs->trans('inventorySelectWarehouse') ?></td>
            <td><?php echo $formproduct->selectWarehouses('', 'fk_warehouse') ?></td>
        </tr>

        <tr>
            <td><?php echo $langs->trans('SelectCategory') ?></td>
            <td><?php echo $formDoli->select_all_categories(0,'', 'fk_category[]') ?></td>
        </tr>
        <tr>
            <td><?php echo $langs->trans('SelectFournisseur') ?></td>
            <td><?php echo $formDoli->select_company('','fk_supplier','s.fournisseur = 1', 1) ?></td>
        </tr>
        <tr>
            <td><?php echo $langs->trans('OnlyProdsInStock') ?></td>
            <td><input type="checkbox" name="OnlyProdsInStock" value="1"></td>
        </tr>

        <tr>
            <td><?php echo $langs->trans('IncludeProdWithCurrentStockValue') ?></td>
            <td><input type="checkbox" name="includeWithStockPMP" value="1"></td>
        </tr>
        <?php if($conf->productbatch->enabled)  { ?>
        <tr>
            <td><?php echo $langs->trans('InventoryWithBatchDetail') ?></td>
            <td><input type="checkbox" name="inventoryWithBatchDetail" value="1"></td>
        </tr>
        <?php } ?>
    </table>
    <?php

	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('inventoryConfirmCreate').'" />';
	print '</div>';

	print $form->end_form();
	llxFooter('');
    ?>
    <script type="text/javascript">
        $(document).ready(function(){
            $('select[name*="fk_category"]').attr("multiple", 'multiple').select2().val('').change();//Pour vider le multiselect car select_all_categories est ...
        });
    </script>
    <?php
}

function _fiche(&$PDOdb, &$user, &$db, &$conf, &$langs, &$inventory, $mode='edit')
{
    global $module_helpurl, $arrayfields, $extrafields, $hookmanager, $parameters, $extrafieldsobjectkey;

	llxHeader('',$langs->trans('inventoryEdit'),$module_helpurl,'');

    $action = GETPOST('action','alphanohtml');
    $reshook=$hookmanager->executeHooks('doActions', $parameters,$inventory,$action);

	$warehouse = new Entrepot($db);
	$warehouse->fetch($inventory->fk_warehouse);

	print dol_get_fiche_head(inventoryPrepareHead($inventory, $langs->trans('inventoryOfWarehouse', $warehouse->libelle), '&action='.$mode));

	//Récupération du tableau des champs extrafields que l'on peut ajouter en tant que colonne
    $arrayfields = array();
    $extrafields = new ExtraFields($db);

    $product = new Product($db);

    $extrafields->fetch_name_optionals_label('product');
    $extrafields->getOptionalsFromPost($product->table_element,'','ef_');

    if (is_array($extrafields->attributes[$product->table_element]['label']) && count($extrafields->attributes[$product->table_element]['label']))
    {
        foreach($extrafields->attributes[$product->table_element]['label'] as $key => $val)
        {
            if (! empty($extrafields->attributes[$product->table_element]['list'][$key]))
                $arrayfields["ef.".$key]=array('label'=>$extrafields->attributes[$product->table_element]['label'][$key], 'checked'=>0, 'position'=>$extrafields->attributes[$product->table_element]['pos'][$key], 'enabled'=>(abs($extrafields->attributes[$product->table_element]['list'][$key])!=3 && $extrafields->attributes[$product->table_element]['perms'][$key]));
        }
    }

    $extrafieldsobjectkey = 'product';

    $arrayfields = dol_sort_array($arrayfields, 'position');

	$form=new TFormCore();
	$form->Set_typeaff($mode);

	$TInventory = array();
	$Tinventory = _fiche_ligne($db, $user, $langs, $inventory, $TInventory, $form, $mode);

	$TBS=new TTemplateTBS();
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;

	//set_time_limit(10);
	//var_dump($inventory);exit;

	print '<b>'.$langs->trans('inventoryOnDate')." ".$inventory->get_date('date_inventory', 'd/m/Y').'</b><br><br>';

	$inventoryTPL = array(
		'id'=> $inventory->getId()
		,'date_cre' => $inventory->get_date('date_cre', 'd/m/Y')
		,'date_maj' => $inventory->get_date('date_maj', 'd/m/Y H:i')
		,'fk_warehouse' => $inventory->fk_warehouse
		,'status' => $inventory->status
		,'entity' => $inventory->entity
		,'amount' => price( round($inventory->amount,2) )
		,'amount_actual'=>price (round($inventory->amount_actual,2))

	);

	$view = array(
		'mode' => $mode
		,'url' => dol_buildpath('/inventory/inventory.php', 1)
		,'can_validate' => (int) $user->rights->inventory->validate
		,'is_already_validate' => (int) $inventory->status
	    ,'per_batch' => $inventory->per_batch
		,'token'=>$_SESSION['newtoken']
	);

	$product = array(
		'list'=>inventorySelectProducts($PDOdb, $inventory)
	);

	include './tpl/inventory.tpl.php';
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $inventory, $action);
	llxFooter('');
}


function _fiche_ligne(&$db, &$user, &$langs, &$inventory, &$TInventory, &$form, $mode)
{
	global $db,$conf, $extrafieldsobjectkey;

	$inventory->amount_actual = 0;

	$TCacheEntrepot = array();

	foreach ($inventory->TInventorydet as $k => $TInventorydet)
	{

        $product = & $TInventorydet->product;
		$stock = $TInventorydet->qty_stock;

		if ($inventory->per_batch && $product->hasbatch())
		{
    		$product->load_stock();
    		$lotstotal = 0;
    		if (!empty($product->stock_warehouse[$inventory->fk_warehouse]->detail_batch) && is_array($product->stock_warehouse[$inventory->fk_warehouse]->detail_batch))
    		{
    		    foreach ($product->stock_warehouse[$inventory->fk_warehouse]->detail_batch as $lot => $details)
    		    {
    		        $lotstotal += $details->qty;
    		    }
    		}

    		$lotparent = '';
    		if ((float) $lotstotal !== (float) $stock)
    		{
    		    $lotparent = img_picto('Stock à corriger', 'error');
    		}
    // 		var_dump($lotstotal, $stock); exit;
		}

        $pmp = $TInventorydet->pmp;
		$pmp_actual = $pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

        $last_pa = $TInventorydet->pa;
		$current_pa = $TInventorydet->current_pa;

		if(!empty($conf->global->INVENTORY_USE_MIN_PA_OR_LAST_PA_MIN_PMP_IS_NULL) && empty($pmp_actual)) {
			if(!empty($last_pa)){ $pmp_actual = $last_pa* $stock;$pmp=$last_pa;}
			else if(!empty($current_pa)) {$pmp_actual = $current_pa* $stock; $pmp=$current_pa;}
		}

		$e = new Entrepot($db);
		if(!empty($TCacheEntrepot[$TInventorydet->fk_warehouse])) $e = $TCacheEntrepot[$TInventorydet->fk_warehouse];
		elseif($e->fetch($TInventorydet->fk_warehouse) > 0) $TCacheEntrepot[$e->id] = $e;
		if($inventory->per_batch)  {

		    if ($TInventorydet->lot == '') $lastprodline = $k;

		    $inventoryItem = array(
// 		        'produit' => $product->getNomUrl(1).'&nbsp;-&nbsp;'.$product->label
// 		        ,'entrepot'=>$e->getNomUrl(1)
// 		        ,'barcode' => $product->barcode
// 		        ,'qty' => ($TInventorydet->lot == '') ? '' : $form->texte('', 'qty_to_add['.$k.']', (isset($_REQUEST['qty_to_add'][$k]) ? $_REQUEST['qty_to_add'][$k] : 0), 8, 0, "style='text-align:center;'")
// 		        .($form->type_aff!='view' ? '<a id="a_save_qty_'.$k.'" href="javascript:save_qty('.$k.')">'.img_picto('Ajouter', 'plus16@inventory').'</a>' : '')
// 		        ,'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
// 		        ,'qty_stock' => $stock
// 		        ,'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
// 		        ,'action' => $user->rights->inventory->write ? '<a onclick="if (!confirm(\'Confirmez-vous la suppression de la ligne ?\')) return false;" href="'.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=delete_line&rowid='.$TInventorydet->getId(), 1).'">'.img_picto($langs->trans('inventoryDeleteLine'), 'delete').'</a>' : ''
// 		        ,'pmp_stock'=>round($pmp_actual,2)
// 		        ,'pmp_actual'=> round($pmp * $TInventorydet->qty_view,2)
// 		        ,'pmp_new'=> ''
// 		        ,'pa_stock'=>round($last_pa * $stock,2)
// 		        ,'pa_actual'=>round($last_pa * $TInventorydet->qty_view,2)
// 		        ,'current_pa_stock'=>round($current_pa * $stock,2)
// 		        ,'current_pa_actual'=>round($current_pa * $TInventorydet->qty_view,2)
// 		        ,'lot' => $TInventorydet->lot
// 		        ,'k'=>$k
// 		        ,'id'=>$TInventorydet->getId()

		        'produit' => ($TInventorydet->lot == '') ? $product->getNomUrl(1).'&nbsp;-&nbsp;'.$product->label : $product->getNomUrl(1)
		        ,'entrepot'=>$e->getNomUrl(1)
		        ,'barcode' => $product->barcode
		        ,'lot' => $TInventorydet->lot.(($TInventorydet->lot !== '') ? '<input class="enfant" type="hidden" id="prod_'.$k.'" value="'.$lastprodline.'">': (($mode == "edit" && $product->hasbatch()) ? '<input type="hidden" id="prod_'.$k.'" value="'.$TInventorydet->getId().'"><input type="hidden" id="prod_line_'.$k.'" value="'.$TInventorydet->getId().'"><a class="addBatch" href="javascript:addBatch('.$k.')">' . img_picto('Ajouter un lot', 'plus16@inventory') . '</a>&nbsp;' : '') . $lotparent)
		        ,'qty' => ($TInventorydet->lot == '' && $product->hasbatch()) ? '' : ($form->type_aff!='view' ? '<a id="a_save_qty_minus_-'.$k.'" href="javascript:save_qty_minus('.$k.')">'.img_picto('Enlever', 'minus16@inventory').'</a>' : '' ).
			($form->texte('', 'qty_to_add['.$k.']', (isset($_REQUEST['qty_to_add'][$k]) ? $_REQUEST['qty_to_add'][$k] : 0), 8, 0, "style='text-align:center;'"))
                        .($form->type_aff!='view' ? '<a id="a_save_qty_'.$k.'" href="javascript:save_qty('.$k.')">'.img_picto('Ajouter', 'plus16@inventory').'</a>' : '')
		        ,'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
		        ,'qty_stock' => $stock
		        ,'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
		        ,'action' => $user->rights->inventory->write ? '<a id="delline_'.$k.'" onclick="javascript:deleteLine('.$k.')" href="#" data-href="'.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=delete_line&rowid='.$TInventorydet->getId(), 1).'">'.img_picto($langs->trans('inventoryDeleteLine'), 'delete').'</a>' : ''
		        ,'pmp_stock'=>round($pmp_actual,2)
		        ,'pmp_actual'=> round($pmp * $TInventorydet->qty_view,2)
		        ,'pmp_new'=>($TInventorydet->lot == '') ? (!empty($user->rights->inventory->changePMP) ?  $form->texte('', 'new_pmp['.$k.']',$TInventorydet->new_pmp, 8, 0, "style='text-align:right;'")
		            .($form->type_aff!='view' ? '<a id="a_save_new_pmp_'.$k.'" href="javascript:save_pmp('.$k.')">'.img_picto($langs->trans('Save'), 'bt-save.png@inventory').'</a>' : '') : '' ) : ""
		        ,'pa_stock'=>round($last_pa * $stock,2)
		        ,'pa_actual'=>round($last_pa * $TInventorydet->qty_view,2)
		        ,'current_pa_stock'=>round($current_pa * $stock,2)
		        ,'current_pa_actual'=>round($current_pa * $TInventorydet->qty_view,2)
		        ,'k'=>$k
		        ,'id'=>$TInventorydet->getId()
                ,'fk_product'=>$product->id
		    );


		    //var_dump($product->stock_warehouse[$e->id]->detail_batch); exit;

		} else {
			$inventoryItem = array(
				'produit' => _productGetNomUrl($product).'&nbsp;-&nbsp;'.$product->label
				,'entrepot'=>_entrepotGetNomUrl($e)
    			,'barcode' => $product->barcode
    			,'qty' => ($form->type_aff!='view' ? '<a id="a_save_qty_minus_-'.$k.'" href="javascript:save_qty_minus('.$k.')">'.img_picto('Enlever', 'minus16@inventory').'</a>' : '' ).
			($form->texte('', 'qty_to_add['.$k.']', (isset($_REQUEST['qty_to_add'][$k]) ? $_REQUEST['qty_to_add'][$k] : 0), 8, 0, "style='text-align:center;'"))
                        .($form->type_aff!='view' ? '<a id="a_save_qty_'.$k.'" href="javascript:save_qty('.$k.')">'.img_picto('Ajouter', 'plus16@inventory').'</a>' : '')
    			,'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
    			,'qty_stock' => $stock
    			,'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
    			,'action' => $user->rights->inventory->write ? '<a onclick="if (!confirm(\'Confirmez-vous la suppression de la ligne ?\')) return false;" href="'.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=delete_line&rowid='.$TInventorydet->getId(), 1).'">'.img_picto($langs->trans('inventoryDeleteLine'), 'delete').'</a>' : ''
    			,'pmp_stock'=>round($pmp_actual,2)
                ,'pmp_actual'=> round($pmp * $TInventorydet->qty_view,2)
    			,'pmp_new'=>(!empty($user->rights->inventory->changePMP) ?  $form->texte('', 'new_pmp['.$k.']',$TInventorydet->new_pmp, 8, 0, "style='text-align:right;'")
                            .($form->type_aff!='view' ? '<a id="a_save_new_pmp_'.$k.'" href="javascript:save_pmp('.$k.')">'.img_picto($langs->trans('Save'), 'bt-save.png@inventory').'</a>' : '') : '' )
                ,'pa_stock'=>round($last_pa * $stock,2)
                ,'pa_actual'=>round($last_pa * $TInventorydet->qty_view,2)
    			,'current_pa_stock'=>round($current_pa * $stock,2)
    			,'current_pa_actual'=>round($current_pa * $TInventorydet->qty_view,2)

                ,'k'=>$k
                ,'id'=>$TInventorydet->getId()
                ,'fk_product'=>$product->id
    		);
		}


		$inventoryItem['fk_warehouse'] = $e->id;

		$TInventory[] = $inventoryItem;
	}

}

function exportCSV(&$inventory) {
	global $conf, $db, $hookmanager;

	header('Content-Type: application/octet-stream');
    header('Content-disposition: attachment; filename=inventory-'. $inventory->getId().'-'.date('Ymd-His').'.csv');
    header('Pragma: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

	echo 'Ref;Label;';
	if($inventory->per_batch) echo 'Lot;';
	echo 'barcode;qty theorique;PMP;dernier PA;';
	if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) echo 'PA courant;';
	echo 'qty réelle;PMP;dernier PA;';
	if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) echo 'PA courant;';
	echo 'qty regulée;';

    // Add fields from hooks
    $parameters=array();
    $reshook=$hookmanager->executeHooks('printExportColumnTitle',$parameters, $inventory);    // Note that $action and $object may have been modified by hook
    if ($reshook < 0) dol_print_error($db, $hookmanager->error, $hookmanager->errors);
	else print $hookmanager->resPrint;

	echo "\r\n";

	foreach ($inventory->TInventorydet as $k => $TInventorydet)
	{
		$product = & $TInventorydet->product;
		$stock = $TInventorydet->qty_stock;
		$lot = $TInventorydet->lot;

        $pmp = $TInventorydet->pmp;
		$pmp_actual = $pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

        $last_pa = $TInventorydet->pa;
        $current_pa = $TInventorydet->current_pa;

	if(!empty($conf->global->INVENTORY_USE_MIN_PA_OR_LAST_PA_MIN_PMP_IS_NULL) && empty($pmp_actual)) {
		if(!empty($last_pa)){ $pmp_actual = $last_pa* $stock;$pmp=$last_pa;}
		else if(!empty($current_pa)) {$pmp_actual = $current_pa* $stock; $pmp=$current_pa;}
	}

		if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) {
			if($inventory->per_batch) {
				$row = array(
					'produit' => $product->ref
					, 'label' => $product->label
					, 'lot' => $lot
					, 'barcode' => $product->barcode
					, 'qty_stock' => $stock
					, 'pmp_stock' => round($pmp_actual, 2)
					, 'pa_stock' => round($last_pa * $stock, 2)
					, 'current_pa_stock' => round($current_pa * $stock, 2)
					, 'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
					, 'pmp_actual' => round($pmp * $TInventorydet->qty_view, 2)
					, 'pa_actual' => round($last_pa * $TInventorydet->qty_view, 2)
					, 'current_pa_actual' => round($current_pa * $TInventorydet->qty_view, 2)
					, 'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
				);
			} else {
				$row = array(
					'produit' => $product->ref
					, 'label' => $product->label
					, 'barcode' => $product->barcode
					, 'qty_stock' => $stock
					, 'pmp_stock' => round($pmp_actual, 2)
					, 'pa_stock' => round($last_pa * $stock, 2)
					, 'current_pa_stock' => round($current_pa * $stock, 2)
					, 'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
					, 'pmp_actual' => round($pmp * $TInventorydet->qty_view, 2)
					, 'pa_actual' => round($last_pa * $TInventorydet->qty_view, 2)
					, 'current_pa_actual' => round($current_pa * $TInventorydet->qty_view, 2)
					, 'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
				);
			}
		}
		else{
			if($inventory->per_batch) {
				$row = array(
					'produit' => $product->ref
					, 'label' => $product->label
					, 'lot' => $lot
					, 'barcode' => $product->barcode
					, 'qty_stock' => $stock
					, 'pmp_stock' => round($pmp_actual, 2)
					, 'pa_stock' => round($last_pa * $stock, 2)
					, 'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
					, 'pmp_actual' => round($pmp * $TInventorydet->qty_view, 2)
					, 'pa_actual' => round($last_pa * $TInventorydet->qty_view, 2)
					, 'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
				);
			} else {
				$row = array(
					'produit' => $product->ref
					, 'label' => $product->label
					, 'barcode' => $product->barcode
					, 'qty_stock' => $stock
					, 'pmp_stock' => round($pmp_actual, 2)
					, 'pa_stock' => round($last_pa * $stock, 2)
					, 'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
					, 'pmp_actual' => round($pmp * $TInventorydet->qty_view, 2)
					, 'pa_actual' => round($last_pa * $TInventorydet->qty_view, 2)
					, 'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
				);
			}

		}

        // Add fields from hooks
        $parameters=array('row'=>&$row, 'TInventorydet'=>$TInventorydet);
        $reshook=$hookmanager->executeHooks('printExportColumnContent',$parameters, $inventory);    // Note that $action and $object may have been modified by hook
        if ($reshook < 0) dol_print_error($db, $hookmanager->error, $hookmanager->errors);

        echo '"'.implode('";"', $row).'"'."\r\n";

	}

	exit;
}

function generateODT(&$PDOdb, &$db, &$conf, &$langs, &$inventory)
{
    global $hookmanager;
	$TBS=new TTemplateTBS();

	$TInventoryPrint = array(); // Tableau envoyé à la fonction render contenant les informations concernant l'inventaire

	foreach($inventory->TInventorydet as $k => $v)
	{
		$prod = new Product($db);
		$prod->fetch($v->fk_product);
		//$prod->fetch_optionals($prod->id);

		$TInventoryPrint[] = array(
			'product' => $prod->ref.' - '.$prod->label
			, 'lot' => isset($v->lot) ? $v->lot : ''
			, 'qty_view' => $v->qty_view
		);

		// Add fields from hooks
        $parameters=array('TInventoryPrint'=>&$TInventoryPrint, 'TInventorydet'=> $v);
        $reshook=$hookmanager->executeHooks('printODTColumn',$parameters, $inventory);    // Note that $action and $object may have been modified by hook
        if ($reshook < 0) dol_print_error($db, $hookmanager->error, $hookmanager->errors);
	}

	$warehouse = new Entrepot($db);
	$warehouse->fetch($inventory->fk_warehouse);

	$dirName = 'INVENTORY'.$inventory->getId().'('.date("d_m_Y").')';
	$dir = $conf->inventory->multidir_output[$conf->entity].'/'.$dirName.'/';

	@mkdir($dir, 0777, true);

	$inventory->per_batch ? $template = "templateINVENTORY_lot.odt" : $template = "templateINVENTORY.odt";
	//$template = "templateOF.doc";

	$file_gen = $TBS->render(dol_buildpath('inventory/exempleTemplate/'. $template)
		,array(
			'TInventoryPrint'=>$TInventoryPrint
		)
		,array(
			'date_cre'=>$inventory->get_date('date_cre', 'd/m/Y')
			,'date_maj'=>$inventory->get_date('date_maj', 'd/m/Y H:i')
			,'date_inv'=>$inventory->get_date('date_inventory', 'd/m/Y')
			,'numero'=>empty($inventory->title) ? 'Inventaire n°'.$inventory->getId() : $inventory->title
			,'warehouse'=>$warehouse->libelle
			,'status'=>($inventory->status ? $langs->transnoentitiesnoconv('inventoryValidate') : $langs->transnoentitiesnoconv('inventoryDraft'))
			,'logo'=>DOL_DATA_ROOT."/mycompany/logos/".MAIN_INFO_SOCIETE_LOGO
		)
		,array()
		,array(
			'outFile'=>$dir.$inventory->getId().".odt"
			,"convertToPDF"=>(!empty($conf->global->INVENTORY_GEN_PDF) ? true : false)
			,'charset'=>OPENTBS_ALREADY_UTF8

		)

	);

	header("Location: ".DOL_URL_ROOT."/document.php?modulepart=inventory&entity=".$conf->entity."&file=".$dirName."/".$inventory->getId(). (!empty($conf->global->INVENTORY_GEN_PDF) ? '.pdf' : '.odt') );

   /*
    $size = filesize("./" . basename($file_gen));
    header("Content-Type: application/force-download; name=\"" . basename($file_gen) . "\"");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: $size");
    header("Content-Disposition: attachment; filename=\"" . basename($file_gen) . "\"");
    header("Expires: 0");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");

	readfile($file_gen);
   */

	//header("Location: ".DOL_URL_ROOT."/document.php?modulepart=asset&entity=1&file=".$dirName."/".$assetOf->numero.".doc");

}
function _footerList($view,$total_pmp,$total_pmp_actual,$total_pa,$total_pa_actual, $total_current_pa,$total_current_pa_actual) {
    global $conf,$user,$langs;


	    if ($view['can_validate'] == 1) { ?>
        <tr style="background-color:#dedede;">
            <th colspan="3">&nbsp;</th>
            <?php if (! empty($conf->barcode->enabled)) { ?>
					<th align="center">&nbsp;</td>
			<?php } ?>
			<?php if($view['per_batch']) {?>
    				<th align="center">&nbsp;</th>
			<?php } ?>
            <th align="right" nowrap="nowrap"><?php echo price($total_pmp) ?></th>
            <th align="right" nowrap="nowrap"><?php echo price($total_pa) ?></th>
            <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th align="right">'.price($total_current_pa).'</th>';
					 }
			?>
            <th>&nbsp;</th>
            <th align="right" nowrap="nowrap"><?php echo price($total_pmp_actual) ?></th>
            <?php
            if(!empty($user->rights->inventory->changePMP)) {
               	echo '<th>&nbsp;</th>';
			}
			?>
            <th align="right" nowrap="nowrap"><?php echo price($total_pa_actual) ?></th>
            <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th align="right" nowrap="nowrap">'.price($total_current_pa_actual).'</th>';
					 }
			?>

            <th>&nbsp;</th>
            <?php if ($view['is_already_validate'] != 1) { ?>
            <th>&nbsp;</th>
            <?php } ?>
        </tr>
        <?php }
}
function _headerList($view) {
    global $conf,$user,$langs, $db, $selectedfields, $arrayfields, $extrafields, $extrafieldsobjectkey, $hookmanager, $inventory;

    //tri croissant/décroissant des colonnes
    $sortfield = GETPOST("sortfield", 'alpha');             //nom du champs à trier
    $sortorder = GETPOST("sortorder", 'alpha');             //ordre de tri
    $id_inventory = GETPOST("id",'int');

    if (! $sortfield) $sortfield="p.ref";
    if (! $sortorder ) $sortorder = 'asc';


    $param = "&contextpage=inventorylist&id=".$id_inventory."&action=".GETPOST('action','alphanohtml'); //paramètres supplémentaires du lien lorsqu'on souhaite trier la colonne

    //champs à cocher du hamburger
    $form = new Form($db);
    $selectedfields=$form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, "inventoryatmcard");

	?>
			<tr style="background-color:#dedede !important;">
                <?php print_liste_field_titre("Produit", $_SERVER["PHP_SELF"],"p.ref", "", $param, "", $sortfield, $sortorder, "", ""); ?>
                <?php print_liste_field_titre("Entrepôt", $_SERVER["PHP_SELF"],"d.fk_warehouse", "", $param, "", $sortfield, $sortorder, "", ""); ?>
<!--				<th align="center" class="warehouse">Entrepôt</td>-->
				<?php if (! empty($conf->barcode->enabled)) { ?>
					<th align="center">Code-barre</td>
				<?php } ?>
				<?php if ($view['can_validate'] == 1) { ?>
					<?php if($view['per_batch']) {?>
						<th align="center">N° de lots</th>
					<?php } ?>
					<th align="center" width="20%">Quantité théorique</th>
					<?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th align="center" width="20%" colspan="3">Valeur théorique</th>';
					 }
					 else {
					 	echo '<th align="center" width="20%" colspan="2">Valeur théorique</th>';
					 }

					?>

				<?php } ?>
				    <th align="center" width="20%">Quantité réelle</th>
				<?php if ($view['can_validate'] == 1) { ?>

				    <?php

				     $colspan = 2;
					 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) $colspan++;
				     if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) $colspan++;
				     if(empty($user->rights->inventory->changePMP)) $colspan --;

	                 echo '<th align="center" width="20%" colspan="'.$colspan.'">Valeur réelle</th>';

					?>

					<th align="center" width="15%">Quantité régulée</th>
				<?php }

				// Hook fields
				$parameters=array(
						'arrayfields'=>$arrayfields,
						'selectedfields' => $selectedfields,
						'sortfield' => $sortfield,
						'sortorder' => $sortorder,
						'baseUrl' => $_SERVER["PHP_SELF"] . '?id=1&action=view'
				);
				$reshook=$hookmanager->executeHooks('printFieldListTitle',$parameters, $inventory);    // Note that $action and $object may have been modified by hook
				print $hookmanager->resPrint;

				if ($view['is_already_validate'] != 1) { ?>
					<th align="center" width="5%">#</th>
				<?php }
				//titres des extrafields cochés dans le hamburger
				if(intval(DOL_VERSION) > 6 ){
                	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
					echo '<th>&nbsp;</th>';
					echo '<th align="center" width="5%"></th>';

					//menu hamburger
					print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"] . '?id=1&action=view', "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
				}
                ?>
			</tr>
			<?php if ($view['can_validate'] == 1) { ?>
	    	<tr style="background-color:#dedede !important;">
	    		<?php $colspan = empty($conf->barcode->enabled) ? 3 : 4;  ?>
	    		<?php if(!empty($conf->productbatch->enabled)) $colspan++;  ?>
	    	    <th class = "firstcolspan" colspan="<?php echo $colspan;  ?>">&nbsp;</th>
	    	    <th>PMP<?php if(!empty($conf->global->INVENTORY_USE_MIN_PA_OR_LAST_PA_MIN_PMP_IS_NULL)) echo img_info($langs->trans('UsePAifnull')); ?></th>
	    	    <th>Dernier PA</th>
	    	    <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th>PA courant</th>';
					 }

				?>
	    	    <th>&nbsp;</th>
	    	    <th>PMP<?php if(!empty($conf->global->INVENTORY_USE_MIN_PA_OR_LAST_PA_MIN_PMP_IS_NULL)) echo img_info($langs->trans('UsePAifnull')); ?></th>
	    	    <?php
	    	    if(!empty($user->rights->inventory->changePMP)) {
	    	    	echo '<th rel="newPMP">'.$langs->trans('ColumnNewPMP').'</th>';
	    	    }
	    	    ?>
	            <th>Dernier PA</th>
	            <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th>PA courant</th>';
					 }

				?>
	            <th>&nbsp;</th>
	            <?php

				// Fields from hook
				$parameters=array(
						'arrayfields'=>$arrayfields,
						'selectedfields' => $selectedfields,
						'sortfield' => $sortfield,
						'sortorder' => $sortorder,
						'baseUrl' => $_SERVER["PHP_SELF"] . '?id=1&action=view'
				);
				$reshook=$hookmanager->executeHooks('printFieldListOption',$parameters, $inventory);    // Note that $action and $object may have been modified by hook
				print $hookmanager->resPrint;

				if ($view['is_already_validate'] != 1) { print '<th>&nbsp;</th>'; }

				if(intval(DOL_VERSION) > 6 ) {
					foreach ($arrayfields as $field) {
						if ($field['checked'] == 1) echo '<th data-label="' . $field['label'] . '">&nbsp;</th>';          //espaces deuxième ligne de titre pour s'adapter à la première en fonction des extrafields
					}
					echo '<th>&nbsp;</th>';
					echo '<th>&nbsp;</th>';
				}
				?>
	    	</tr>
	    	<?php
	}

}

/**
 * Performance-enhancing function: when getNomUrl() is called 6000 times, it weighs on performance; since there is only
 * about one call per product, caching won't help much but simplifying is effective.
 * @param Product $product
 * @return string
 */
function _productGetNomUrl($product) {
	global $conf;
	if ($conf->global->INVENTORY_PERF_TWEAKS) {
		return '<a href="' . DOL_URL_ROOT.'/product/card.php?id='.$product->id . '">' . $product->ref . '</a>';
	} else {
		return $product->getNomUrl(1);
	}
}

/**
 * Performance-enhancing function: when getNomUrl() is called 6000 times, it weighs on performance; since it is
 * mostly called on the same entrepot, caching may be effective, though tests don't show anything significant
 * @param Entrepot $entrepot
 * @return string
 */
function _entrepotGetNomUrl($entrepot) {
	// static variables keep their value across calls; this assignment will never be run (it is resolved at compile time)
	static $CACHE_Entrepot_GetNomUrl = array();

	if (!array_key_exists($entrepot->id, $CACHE_Entrepot_GetNomUrl)) {
		$CACHE_Entrepot_GetNomUrl[$entrepot->id] = $entrepot->getNomUrl(1);
	}
	return $CACHE_Entrepot_GetNomUrl[$entrepot->id];
}
