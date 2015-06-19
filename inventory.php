<?php 

require('config.php');
require('./class/inventory.class.php');
require('./lib/inventory.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

if(!$user->rights->inventory->read) accessforbidden();

$langs->load("inventory@inventory");

_action();

function _action() 
{
	global $user, $db, $conf, $langs;	
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
			
			$sql = 'SELECT DISTINCT fk_product FROM '.MAIN_DB_PREFIX.'product_stock WHERE fk_entrepot = '.(int) $_REQUEST['fk_warehouse'];
			$PDOdb->Execute($sql);
			
			while ($PDOdb->Get_line())
			{
				$k = $inventory->addChild($PDOdb, 'TInventorydet');
				$inventory->TInventorydet[$k]->fk_inventory = $fk_inventory;
				$inventory->TInventorydet[$k]->fk_product = $PDOdb->Get_field('fk_product');
			}
			
			$inventory->save($PDOdb);
			
			header('Location: '.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=edit', 2));
		
		case 'view':
		case 'edit':
			if (!$user->rights->inventory->create) accessforbidden();
			
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
				header('Location: '.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=view', 2));
			}
			
			break;
			
		case 'regulate':
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');
			
			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);
			$inventory->status = 1;
			$inventory->save($PDOdb);
			
			_fiche($PDOdb, $user, $db, $conf, $langs, $inventory, 'view');
			
			break;
			
		case 'add_line':
			if (!$user->rights->inventory->write) accessforbidden();
			
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');
			
			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);
			
			$type = (!empty($conf->use_javascript_ajax) && !empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT) ? 'string' : 'int');
			
			$fk_product = __get('fk_product', 0, $type);
			
			if ($fk_product)
			{
				
			
				if (!empty($conf->use_javascript_ajax) && !empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT))
				{
					$product = new Product($db);
					$product->fetch(null, $fk_product);				
					
					//Check product not already exists
					$alreadyExists = false;
					foreach ($inventory->TInventorydet as $TInventory)
					{
						if ($TInventory->fk_product == $product->id)
						{
							$alreadyExists = true;
							break;
						}
					}
					
					if (!$alreadyExists)
					{
						$k = $inventory->addChild($PDOdb, 'TInventorydet');
						$inventory->TInventorydet[$k]->fk_inventory = $id;
						$inventory->TInventorydet[$k]->fk_product = $product->id;
					}
					else
					{
						setEventMessage($langs->trans('inventoryWarningProductAlreadyExists'), 'warnings');
					}
				}
				else
				{
					$k = $inventory->addChild($PDOdb, 'TInventorydet');
					$inventory->TInventorydet[$k]->fk_inventory = $id;
					$inventory->TInventorydet[$k]->fk_product = $fk_product;
				}
				
				$inventory->save($PDOdb);
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
			
		case 'delete':
			if (!$user->rights->inventory->create) accessforbidden();
            
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');
			
			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);
			
			$inventory->delete($PDOdb);
			
			_liste($user, $db, $conf, $langs);
			
		case 'printDoc':
			$PDOdb = new TPDOdb;
			$id = __get('id', 0, 'int');
			
			$inventory = new TInventory;
			$inventory->load($PDOdb, $id);
			
			generateODT($PDOdb, $db, $conf, $langs, $inventory);
			break;
			
		default:
			//Rien
			break;
	}
	
}

function _liste(&$user, &$db, &$conf, &$langs) 
{	
	llxHeader('',$langs->trans('inventoryListTitle'),'','');
	
	$form=new TFormCore;

	$inventory = new TInventory;
	$r = new TSSRenderControler($inventory);

	$sql="SELECT i.rowid, e.label, i.fk_warehouse, i.date_cre, i.date_maj, i.status
		  FROM ".MAIN_DB_PREFIX."inventory i
		  LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON (e.rowid = i.fk_warehouse)
		  WHERE i.entity=".(int) $conf->entity;
	
 	if (!__get('TListTBS', 0, 'int')) $sql .= " ORDER BY i.rowid DESC";
	
	$THide = array('label');

	$form=new TFormCore($_SERVER['PHP_SELF'], 'form', 'POST');

	$ATMdb=new TPDOdb;

	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'nbLine'=>'30'
		)
		,'subQuery'=>array()
		,'link'=>array(
			'rowid'=>'<a href="'.DOL_URL_ROOT.'/custom/inventory/inventory.php?id=@val@&action=view">'.img_picto('','object_list.png','',0).' '.$langs->trans('inventoryTitle').' @val@</a>'
			,'fk_warehouse'=>'<a href="'.DOL_URL_ROOT.'/product/stock/card.php?id=@val@">'.img_picto('','object_stock.png','',0).' @label@</a>'
		)
		,'translate'=>array()
		,'hide'=>$THide
		,'type'=>array(
			'date_cre'=>'date'
			,'date_maj'=>'datetime'
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
			,'date_cre'=>'Date création'
			,'date_maj'=>'Date mise à jour'
			,'status'=>'Status'
		)
		,'eval'=>array(
			'status' => '(@val@ ? img_picto("'.$langs->trans("inventoryValidate").'", "statut4") : img_picto("'.$langs->trans("inventoryDraft").'", "statut3"))'
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

function _fiche_warehouse($PDOdb, $user, $db, $conf, $langs, $inventory)
{
	llxHeader('',$langs->trans('inventorySelectWarehouse'),'','');
	print dol_get_fiche_head(inventoryPrepareHead($inventory));
	
	$form=new TFormCore('inventory.php', 'confirmCreate');
	print $form->hidden('action', 'confirmCreate');
	$form->Set_typeaff('edit');
	
	$formproduct = new FormProduct($db);
	print $langs->trans('inventorySelectWarehouse') .'&nbsp;:&nbsp;'. $formproduct->selectWarehouses('', 'fk_warehouse');
	
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('inventoryConfirmCreate').'" />';
	print '</div>';
	
	print $form->end_form();	
	llxFooter('');
}

function _fiche(&$PDOdb, &$user, &$db, &$conf, &$langs, &$inventory, $mode='edit')
{
	llxHeader('',$langs->trans('inventoryEdit'),'','');
	
	$warehouse = new Entrepot($db);
	$warehouse->fetch($inventory->fk_warehouse);
	
	print dol_get_fiche_head(inventoryPrepareHead($inventory, $langs->trans('inventoryOfWarehouse', $warehouse->libelle), '&action='.$mode));
	
	$form=new TFormCore();
	$form->Set_typeaff($mode);
	
	$TInventory = array();
	$Tinventory = _fiche_ligne($db, $user, $langs, $inventory, $TInventory, $form);
	
	$TBS=new TTemplateTBS();
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;
	
	print $TBS->render('tpl/inventory.tpl.php'
		,array(
			'TInventory'=>$TInventory
		)
		,array(
			'inventory'=>array(
				'id'=> $inventory->getId()
				,'date_cre' => $inventory->get_date('date_cre', 'd/m/Y')
				,'date_maj' => $inventory->get_date('date_maj', 'd/m/Y H:i')
				,'fk_warehouse' => $inventory->fk_warehouse
				,'status' => $inventory->status
				,'entity' => $inventory->entity
				,'amount' => price( round($inventory->amount,2) )
				,'amount_actual'=>$inventory->amount_actual
				
			)
			,'view'=>array(
				'mode' => $mode
				,'url' => dol_buildpath('inventory/inventory.php', 2)
				,'can_validate' => (int) $user->rights->inventory->validate
				,'is_already_validate' => (int) $inventory->status
			)
			,'product'=>array(
				'list'=>inventorySelectProducts($PDOdb, $inventory)
			)
		)
	);
	
	/*$doliform = new Form($db);
	ob_start();
	$doliform->select_produits('', "fk_product", 0, $conf->product->limit_size);
	$productlist = ob_get_clean();*/
	
	
	llxFooter('');
}

function _fiche_ligne(&$db, &$user, &$langs, &$inventory, &$TInventory, $form)
{
	
	$inventory->amount_actual = 0;
	
	foreach ($inventory->TInventorydet as $k => $TInventorydet)
	{
		$product = new Product($db);
		$product->fetch($TInventorydet->fk_product);
		
		if (!$inventory->status) //En cours
		{
			$res = $product->load_stock();
			$stock = $res > 0 ? (float) $product->stock_warehouse[$inventory->fk_warehouse]->real : 0;
		}
		else //Validé
		{
			$stock = $TInventorydet->qty_stock;
		}

		$pmp_actual = $product->pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

		$TInventory[]=array(
			'produit' => $product->getNomUrl(1).'&nbsp;-&nbsp;'.$product->label
			,'qty' => $form->texte('', 'qty_to_add['.$k.']', (isset($_REQUEST['qty_to_add'][$k]) ? $_REQUEST['qty_to_add'][$k] : 0), 8, 0, "style='text-align:center;'")
			,'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
			,'qty_stock' => $stock
			,'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
			,'action' => $user->rights->inventory->write ? '<a onclick="if (!confirm(\'Confirmez-vous la suppression de la ligne ?\')) return false;" href="'.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=delete_line&rowid='.$TInventorydet->getId(), 2).'">'.img_picto($langs->trans('inventoryDeleteLine'), 'delete').'</a>' : ''
			,'pmp'=>price(round($TInventorydet->pmp * $TInventorydet->qty_view,2))
			,'pmp_actual'=>price(round($pmp_actual,2))
		);
	}
	
}

function generateODT(&$PDOdb, &$db, &$conf, &$langs, &$inventory) 
{	
	$TBS=new TTemplateTBS();

	$TInventoryPrint = array(); // Tableau envoyé à la fonction render contenant les informations concernant l'inventaire
	
	foreach($inventory->TInventorydet as $k => $v) 
	{
		$prod = new Product($db);
		$prod->fetch($v->fk_product);
		//$prod->fetch_optionals($prod->id);
		
		$TInventoryPrint[] = array(
			'product' => $prod->ref.' - '.$prod->label
			, 'qty_view' => $v->qty_view
		);
	}

	$warehouse = new Entrepot($db);
	$warehouse->fetch($inventory->fk_warehouse);
	
	$dirName = 'INVENTORY'.$inventory->getId().'('.date("d_m_Y").')';
	$dir = DOL_DATA_ROOT.'/inventory/'.$dirName.'/';
	
	@mkdir($dir, 0777, true);
	
	$template = "templateINVENTORY.odt";
	//$template = "templateOF.doc";

	$TBS->render(dol_buildpath('inventory/exempleTemplate/'.$template)
		,array(
			'TInventoryPrint'=>$TInventoryPrint
		)
		,array(
			'date_cre'=>$inventory->get_date('date_cre', 'd/m/Y')
			,'date_maj'=>$inventory->get_date('date_maj', 'd/m/Y H:i')
			,'numero'=>$inventory->getId()
			,'warehouse'=>$warehouse->libelle
			,'status'=>($inventory->status ? $langs->trans('inventoryValidate') : $langs->trans('inventoryDraft'))
			,'logo'=>DOL_DATA_ROOT."/mycompany/logos/".MAIN_INFO_SOCIETE_LOGO
		)
		,array()
		,array(
			'outFile'=>$dir.$inventory->getId().".odt"
			,"convertToPDF"=>true
			,'charset'=>OPENTBS_ALREADY_UTF8
			//'outFile'=>$dir.$assetOf->numero.".doc"
		)
		
	);	
	
	header("Location: ".DOL_URL_ROOT."/document.php?modulepart=inventory&entity=".$conf->entity."&file=".$dirName."/".$inventory->getId().".pdf");
	//header("Location: ".DOL_URL_ROOT."/document.php?modulepart=asset&entity=1&file=".$dirName."/".$assetOf->numero.".doc");

}
