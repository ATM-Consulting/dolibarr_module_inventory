<?php 

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
            $fk_category = (int)GETPOST('fk_category');
            $fk_warehouse = (int)GETPOST('fk_warehouse');
            
			$sql = 'SELECT DISTINCT ps.fk_product 
			     FROM '.MAIN_DB_PREFIX.'product_stock ps 
			     INNER JOIN '.MAIN_DB_PREFIX.'product p ON (p.rowid = ps.fk_product) 
                 LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_product = p.rowid)
			     WHERE ps.fk_entrepot = '.$fk_warehouse;
                 
            if($fk_category>0) $sql.= " AND cp.fk_categorie=".$fk_category;
			     
			$sql.=' ORDER BY p.ref ASC,p.label ASC';
                 
                 
			$Tab = $PDOdb->ExecuteAsArray($sql);
			
			foreach($Tab as &$row) {
			
                $inventory->add_product($PDOdb, $row->fk_product);
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
						if ($invdet->fk_product == $product->id)
						{
							$alreadyExists = true;
							break;
						}
					}
					
					if (!$alreadyExists)
					{
					    $inventory->add_product($PDOdb, $product->id);
                        
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
	llxHeader('',$langs->trans('inventoryListTitle'),'','');
	
	$form=new TFormCore;

	$inventory = new TInventory;
	$r = new TSSRenderControler($inventory);

	$sql="SELECT i.rowid, e.label, i.date_inventory, i.fk_warehouse, i.date_cre, i.date_maj, i.status
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
			'fk_warehouse'=>'<a href="'.DOL_URL_ROOT.'/product/stock/card.php?id=@val@">'.img_picto('','object_stock.png','',0).' @label@</a>'
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
	dol_include_once('/categories/class/categorie.class.php');    
        
	llxHeader('',$langs->trans('inventorySelectWarehouse'),'','');
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
            <td><?php echo $formDoli->select_all_categories(0,'', 'fk_category') ?></td> 
        </tr>
        
    </table>
    <?php
    
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
	
	//set_time_limit(10);
	//var_dump($TInventory);exit;	
	
	print '<b>'.$langs->trans('inventoryOnDate')." ".$inventory->get_date('date_inventory').'</b><br><br>';
	
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
		,'token'=>$_SESSION['newtoken']
	);
	
	$product = array(
		'list'=>inventorySelectProducts($PDOdb, $inventory)
	);

	include './tpl/inventory.tpl.php';
	
	llxFooter('');
}


function _fiche_ligne(&$db, &$user, &$langs, &$inventory, &$TInventory, &$form)
{
	$inventory->amount_actual = 0;
	
	foreach ($inventory->TInventorydet as $k => $TInventorydet)
	{
	    
        $product = & $TInventorydet->product;
		$stock = $TInventorydet->qty_stock;
	
        $pmp = $TInventorydet->pmp;
		$pmp_actual = $pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

        $last_pa = $TInventorydet->pa;
        
		$TInventory[]=array(
			'produit' => $product->getNomUrl(1).'&nbsp;-&nbsp;'.$product->label
			,'barcode' => $product->barcode
			,'qty' => $form->texte('', 'qty_to_add['.$k.']', (isset($_REQUEST['qty_to_add'][$k]) ? $_REQUEST['qty_to_add'][$k] : 0), 8, 0, "style='text-align:center;'")
                        .($form->type_aff!='view' ? '<a id="a_save_qty_'.$k.'" href="javascript:save_qty('.$k.')">'.img_picto('Ajouter', 'plus16@inventory').'</a>' : '')
			,'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
			,'qty_stock' => $stock
			,'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
			,'action' => $user->rights->inventory->write ? '<a onclick="if (!confirm(\'Confirmez-vous la suppression de la ligne ?\')) return false;" href="'.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=delete_line&rowid='.$TInventorydet->getId(), 1).'">'.img_picto($langs->trans('inventoryDeleteLine'), 'delete').'</a>' : ''
			,'pmp_stock'=>round($pmp_actual,2)
            ,'pmp_actual'=>round($pmp * $TInventorydet->qty_view,2)
            ,'pa_stock'=>round($last_pa * $stock,2)
            ,'pa_actual'=>round($last_pa * $TInventorydet->qty_view,2)
            ,'k'=>$k
            ,'id'=>$TInventorydet->getId()
		);
	}
	
}

function exportCSV(&$inventory) {
	
	header('Content-Type: application/octet-stream');
    header('Content-disposition: attachment; filename=inventory-'. $inventory->getId().'-'.date('Ymd-His').'.csv');
    header('Pragma: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
	
	echo 'Ref;Label;barcode;qty theorique;PMP;dernier PA;qty réelle;PMP;PA;qty regulée;'."\r\n";
	
	foreach ($inventory->TInventorydet as $k => $TInventorydet)
	{
		$product = & $TInventorydet->product;
		$stock = $TInventorydet->qty_stock;
	
        $pmp = $TInventorydet->pmp;
		$pmp_actual = $pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

        $last_pa = $TInventorydet->pa;
        
		$row=array(
			'produit' => $product->ref
			,'label'=>$product->label
			,'barcode' => $product->barcode
			,'qty_stock' => $stock
			,'pmp_stock'=>round($pmp_actual,2)
            ,'pa_stock'=>round($last_pa * $stock,2)
            ,'qty_view' => $TInventorydet->qty_view ? $TInventorydet->qty_view : 0
			,'pmp_actual'=>round($pmp * $TInventorydet->qty_view,2)
            ,'pa_actual'=>round($last_pa * $TInventorydet->qty_view,2)
            
			,'qty_regulated' => $TInventorydet->qty_regulated ? $TInventorydet->qty_regulated : 0
			
		);
		
		echo '"'.implode('";"', $row).'"'."\r\n";
		
	}
	
	exit;
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

	$file_gen = $TBS->render(dol_buildpath('inventory/exempleTemplate/'.$template)
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
