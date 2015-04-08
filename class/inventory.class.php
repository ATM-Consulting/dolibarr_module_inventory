<?php

include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

class TInventory extends TObjetStd
{
	function __construct() 
	{
		global $conf;
		
		$this->set_table(MAIN_DB_PREFIX.'inventory');
    	$this->TChamps = array(); 	  
		$this->add_champs('fk_warehouse,entity', 'type=entier;');
		$this->add_champs('status', 'type=entier;');
		
	    $this->start();
		
		$this->setChild('TInventorydet','fk_inventory');
		
		$this->status = 0;
		$this->entity = $conf->entity;
		$this->errors = array();
		
	}
	
	function save($PDOdb)
	{
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
				
				//si on valide l'inventaire on sauvegarde le stock à cette instant
				if ($this->status) 
				{
					$res = $product->load_stock();
					$this->TInventorydet[$k]->qty_stock =  $res > 0 ? $product->stock_warehouse[$this->fk_warehouse]->real : 0;
				}
				
				$this->TInventorydet[$k]->qty_view += $qty;
			}	
		}
		
		return parent::set_values($Tab);
	}
	
	function regulate()
	{
		global $db,$user,$langs;
		
		foreach ($this->TInventorydet as $k => $TInventorydet)
		{
			$product = new Product($db);
			$product->fetch($TInventorydet->fk_product);
			
			if ($TInventorydet->qty_view != $TInventorydet->qty_stock)
			{
				$TInventorydet->qty_regulated = $TInventorydet->qty_stock - $TInventorydet->qty_view;
				$nbpiece = abs($TInventorydet->qty_regulated);
				$movement = (int) ($TInventorydet->qty_view < $TInventorydet->qty_stock); // 0 = add ; 1 = remove
				
				$href = DOL_URL_ROOT.'/custom/inventory/inventory.php?id='.$this->getId().'&action=view';
				
				$product->correct_stock($user, $this->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStock', $href, $this->getId()));
			}
		}
		
		return 1;
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
		$this->add_champs('qty_view,qty_stock,qty_regulated', 'type=float;');
		
	    $this->start();
		
		$this->entity = $conf->entity;
		$this->errors = array();
	}
}
