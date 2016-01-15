<?php

include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

class TInventory extends TObjetStd
{
	function __construct() 
	{
		global $conf;
		
		$this->set_table(MAIN_DB_PREFIX.'inventory');
    	 
		$this->add_champs('fk_warehouse,entity', 'type=entier;');
		$this->add_champs('status', 'type=entier;');
        $this->add_champs('date_inventory', 'type=date;');
        
        $this->_init_vars('title');
        
	    $this->start();
		
		$this->setChild('TInventorydet','fk_inventory');
		
		$this->status = 0;
		$this->entity = $conf->entity;
		$this->errors = array();
		$this->amount = 0;
		
	}
	
	function sort_det() 
	{
		usort($this->TInventorydet, array('TInventory', 'customSort'));
	}
	
	function load(&$PDOdb, $id,$annexe = true) 
	{
	    
        if(!$annexe) $this->withChild = false;
        
		$res = parent::load($PDOdb, $id);
		$this->sort_det();
		
		$this->amount = 0;
		foreach($this->TInventorydet as &$det){
			$this->amount+=$det->qty_view * $det->pmp;
		}
		
		return $res;
	}
	
	
	function customSort(&$objA, &$objB)
	{
		global $db;
		
		$r = strcmp(strtoupper(trim($objA->product->ref)), strtoupper(trim($objB->product->ref)));
		
		if ($r < 0) $r = -1;
		elseif ($r > 0) $r = 1;
		else $r = 0;
		
		return $r;
	}
	
	function save($PDOdb)
	{
		//si on valide l'inventaire on sauvegarde le stock à cette instant
		if ($this->status)
		{
			 $this->regulate($PDOdb);
		}
		
		parent::save($PDOdb);
	}
	
	function set_values($Tab)
	{
		global $db,$langs;
		
		if (isset($Tab['qty_to_add']))
		{
			foreach ($Tab['qty_to_add'] as $k => $qty)
			{
				$qty = (float) price2num($qty);
				
				if ($qty < 0) 
				{
					$this->errors[] = $langs->trans('inventoryErrorQtyAdd');
					return 0;
				} 
				
				$product = new Product($db);
				$product->fetch($this->TInventorydet[$k]->fk_product);
				
				$this->TInventorydet[$k]->pmp = $product->pmp;
				$this->TInventorydet[$k]->qty_view += $qty;
			}	
		}
		
		return parent::set_values($Tab);
	}
	
    function deleteAllLine(&$PDOdb) {
        
        foreach($this->TInventorydet as &$det) {
            $det->to_delete = true;
        }
        
        $this->save($PDOdb);
      
        $this->TInventorydet=array();
        
    }
    
    function add_product(&$PDOdb, $fk_product) {
        
        $k = $this->addChild($PDOdb, 'TInventorydet');
        $det =  &$this->TInventorydet[$k];
        
        $det->fk_inventory = $this->getId();
        $det->fk_product =$fk_product;
        
        $det->load_product();
                
        $date = $this->get_date('date_inventory', 'Y-m-d');
        if(empty($date))$date = $this->get_date('date_cre', 'Y-m-d'); 
        $det->setStockDate($PDOdb, $date , $this->fk_warehouse);
        
    }
    
	function regulate(&$PDOdb)
	{
		global $db,$user,$langs,$conf;
		
		if($conf->global->INVENTORY_DISABLE_VIRTUAL){
			$pdt_virtuel = false;
			// Test si pdt virtuel est activé
			if($conf->global->PRODUIT_SOUSPRODUITS)
			{
				$pdt_virtuel = true;
				$conf->global->PRODUIT_SOUSPRODUITS = 0;
			}
		}
		
		foreach ($this->TInventorydet as $k => $TInventorydet)
		{
			$product = new Product($db);
			$product->fetch($TInventorydet->fk_product);
			
			/*
			 * Ancien code qui était pourri et qui modifié la valeur du stock théorique si le parent était déstocké le même jour que l'enfant
			 * 
			 * $product->load_stock();
			$TInventorydet->qty_stock = $product->stock_warehouse[$this->fk_warehouse]->real;
			
			if(date('Y-m-d', $this->date_inventory) < date('Y-m-d')) {
				$TRes = $TInventorydet->getPmpStockFromDate($PDOdb, date('Y-m-d', $this->date_inventory), $this->fk_warehouse);
				$TInventorydet->qty_stock = $TRes[1];
			}
			*/
			if ($TInventorydet->qty_view != $TInventorydet->qty_stock)
			{
				$TInventorydet->qty_regulated = $TInventorydet->qty_view - $TInventorydet->qty_stock;
				$nbpiece = abs($TInventorydet->qty_regulated);
				$movement = (int) ($TInventorydet->qty_view < $TInventorydet->qty_stock); // 0 = add ; 1 = remove
						
				$href = dol_buildpath('/inventory/inventory.php?id='.$this->getId().'&action=view', 1);
				
				if(empty($this->title))
					$product->correct_stock($user, $this->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStock', $href, $this->getId()));
				else
					$product->correct_stock($user, $this->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStockWithNomInventaire', $href, $this->title));
			}
		}

		if($conf->global->INVENTORY_DISABLE_VIRTUAL){
			// Test si pdt virtuel était activé avant la régule
			if($pdt_virtuel) $conf->global->PRODUIT_SOUSPRODUITS = 1;
		}
		
		return 1;
	}
    
    static function getLink($id) {
        global $langs;
        
        $PDOdb=new TPDOdb;
        
        $i = new TInventory;
        $i->load($PDOdb, $id, false);
        
        $title = !empty($i->title) ? $i->title : $langs->trans('inventoryTitle').' '.$i->getId();
        
        return '<a href="'.dol_buildpath('/inventory/inventory.php?id='.$i->getId().'&action=view', 1).'">'.img_picto('','object_list.png','',0).' '.$title.'</a>';
        
    }
}

class TInventorydet extends TObjetStd
{
	function __construct()
	{
		global $conf;
		
		$this->set_table(MAIN_DB_PREFIX.'inventorydet');
    	$this->TChamps = array(); 	  
		$this->add_champs('fk_inventory,fk_product,entity', 'type=entier;');
		$this->add_champs('qty_view,qty_stock,qty_regulated,pmp,pa', 'type=float;');
		
	    $this->start();
		
		$this->entity = $conf->entity;
		$this->errors = array();
		
		$this->product = null;
		
	}
	
	function load(&$PDOdb, $id) 
	{
		$res = parent::load($PDOdb, $id);
		$this->load_product();
        
        return $res;
	}
	
    function setStockDate(&$PDOdb, $date, $fk_warehouse) {
        
		list($pmp,$stock) = $this->getPmpStockFromDate($PDOdb, $date, $fk_warehouse);
		
        $this->qty_stock = $stock;
        $this->pmp = $pmp;
        
        $last_pa = 0;
        $sql = "SELECT price FROM ".MAIN_DB_PREFIX."stock_mouvement 
                WHERE fk_entrepot=".$fk_warehouse." 
                AND fk_product=".$this->fk_product." 
                AND (origintype='order_supplier' || origintype='invoice_supplier')
                AND price>0 
                AND datem<='".$date." 23:59:59'
                ORDER BY datem DESC LIMIT 1";
               
        $PDOdb->Execute($sql);
       
        if($obj = $PDOdb->Get_line()) {
            $last_pa = $obj->price;
        }
        
        $this->pa = $last_pa;
      /*  var_dump($fk_warehouse,$this->product->stock_warehouse,$this->pmp, $this->pa, $this->qty_stock);
        exit;*/
    }

	function getPmpStockFromDate(&$PDOdb, $date, $fk_warehouse){
		
		$res = $this->product->load_stock();
		
		$stock = $res > 0 ? (float) $this->product->stock_warehouse[$fk_warehouse]->real : 0;
        $pmp = $res > 0 ? (float) $this->product->stock_warehouse[$fk_warehouse]->pmp : 0;
		
		//On récupère tous les mouvements de stocks du produit entre aujourd'hui et la date de l'inventaire
		$sql = "SELECT value, price
				FROM ".MAIN_DB_PREFIX."stock_mouvement
				WHERE fk_product = ".$this->product->id."
					AND fk_entrepot = ".$fk_warehouse."
					AND datem > '".date('Y-m-d 23:59:59',strtotime($date))."'
				ORDER BY datem DESC";

		//echo $sql.'<br>';
		$PDOdb->Execute($sql);
		$TMouvementStock = $PDOdb->Get_All();
		$laststock = $stock;
		$lastpmp = $pmp;
		//Pour chacun des mouvements on recalcule le PMP et le stock physique
		foreach($TMouvementStock as $mouvement){
			
			//150
			//if($this->product->id==394) echo 'laststock = '.$stock.'<br>';
			
			//9.33
			//if($this->product->id==394) echo 'lastpmp = '.$pmp.'<br>';
			$price = ($mouvement->price>0 && $mouvement->value>0) ? $mouvement->price : $lastpmp;  
				
			$stock_value = $laststock * $lastpmp;
			
			$laststock -= $mouvement->value;
			
			$last_stock_value = $stock_value - ($mouvement->value * $price);	
			
			$lastpmp = ($laststock != 0) ? $last_stock_value / $laststock : $lastpmp;
			 
			

		}
		
		return array($lastpmp,$laststock);
	}
    
	function load_product() 
	{
		global $db;
		
		if($this->fk_product>0) {
			$this->product = new Product($db);
			$this->product->fetch($this->fk_product);
		}
		
	}
	
}
