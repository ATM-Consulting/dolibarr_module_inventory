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
	
	function load(&$PDOdb, $id) 
	{
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
			 $this->regulate();
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
    
	function regulate()
	{
		global $db,$user,$langs;
		
		foreach ($this->TInventorydet as $k => $TInventorydet)
		{
			$product = new Product($db);
			$product->fetch($TInventorydet->fk_product);
			
			$product->load_stock();
			$TInventorydet->qty_stock = $product->stock_warehouse[$this->fk_warehouse]->real;
			
			if ($TInventorydet->qty_view != $TInventorydet->qty_stock)
			{
				$TInventorydet->qty_regulated = $TInventorydet->qty_view - $TInventorydet->qty_stock;
				$nbpiece = abs($TInventorydet->qty_regulated);
				$movement = (int) ($TInventorydet->qty_view < $TInventorydet->qty_stock); // 0 = add ; 1 = remove
						
				$href = dol_buildpath('/inventory/inventory.php?id='.$this->getId().'&action=view', 2);
				
				if(empty($this->title))
					$product->correct_stock($user, $this->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStock', $href, $this->getId()));
				else
					$product->correct_stock($user, $this->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStockWithNomInventaire', $href, $this->title));
			}
		}
		
		return 1;
	}
    
    static function getLink($id) {
        global $langs;
        
        $PDOdb=new TPDOdb;
        
        $i = new TInventory;
        $i->load($PDOdb, $id);
        
        $title = !empty($i->title) ? $i->title : $langs->trans('inventoryTitle').' '.$i->getId();
        
        return '<a href="'.dol_buildpath('/inventory/inventory.php?id='.$i->getId().'&action=view', 2).'">'.img_picto('','object_list.png','',0).' '.$title.'</a>';
        
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
		$this->add_champs('qty_view,qty_stock,qty_regulated,pmp', 'type=float;');
		
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
	
	function load_product() 
	{
		global $db;
		
		if($this->fk_product>0) {
			$this->product = new Product($db);
			$this->product->fetch($this->fk_product);
		}
		
	}
	
}
