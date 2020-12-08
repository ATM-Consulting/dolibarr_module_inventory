<?php

include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

class TInventory extends TObjetStd
{
	function __construct()
	{
		global $conf;

		$this->set_table( MAIN_DB_PREFIX.'inventory' );

		$this->add_champs('fk_warehouse,entity,status,per_batch',array('type'=>'integer','index'=>true));
		$this->add_champs('date_inventory',array('type'=>'date'));
        $this->add_champs('title');

        $this->_init_vars();

	    $this->start();

		$this->setChild('TInventorydet','fk_inventory');

		$this->status = 0;
		$this->entity = $conf->entity;
		$this->errors = array();
		$this->amount = 0;

	}

	function sort_det()
	{
//		usort($this->TInventorydet, array('TInventory', 'customSort'));
        usort($this->TInventorydet, array('TInventory', 'orderSort'));
        // FIX TK11812 : PR https://github.com/ATM-Consulting/dolibarr_module_inventory/pull/37
		if ($this->per_batch && !empty($this->TInventorydet))
		{
			$tmpTab = array();
			foreach ($this->TInventorydet as $invDet)
			{
				if (!array_key_exists($invDet->fk_product, $tmpTab))
				{
					$tmpTab[$invDet->fk_product] = array();
				}

				if (empty($invDet->lot)) $tmpTab[$invDet->fk_product][0] = $invDet;
				else if (empty($tmpTab[$invDet->fk_product])) $tmpTab[$invDet->fk_product][1] = $invDet;
				else $tmpTab[$invDet->fk_product][] = $invDet;
			}

			$this->TInventorydet = array();
			foreach ($tmpTab as $produitId => $lines)
			{
				ksort($lines);
				$count = count($lines);
				for( $i = 0; $i < $count; $i++) $this->TInventorydet[] = $lines[$i];
			}
		}

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

    function orderSort(&$objA, &$objB){

	    //champs à trier
       $sortfield = GETPOST('sortfield','alpha');

       $TFieldparts =  explode('.', $sortfield, 2);
       $fieldtype = $TFieldparts[0];
       $fieldname = $TFieldparts[1];

        if(GETPOST('sortorder','alpha') == 'desc') {    //tri decroissant

            if($fieldtype == 'ef')      //extrafield
            {
                $r = strcmp(strtoupper(trim($objA->product->array_options['options_'.$fieldname])), strtoupper(trim($objB->product->array_options['options_'.$fieldname])));

                if ($r > 0) $r = -1;
                elseif ($r < 0) $r = 1;
                else $r = 0;

                return $r;

            }
            else if ($fieldtype == 'd')
			{
				$r = strcmp(strtoupper(trim($objA->$fieldname)), strtoupper(trim($objB->$fieldname)));

				if ($r > 0) $r = -1;
				elseif ($r < 0) $r = 1;
				else $r = 0;

				return $r;
            }
            else
            {

                $r = strcmp(strtoupper(trim($objA->product->$fieldname)), strtoupper(trim($objB->product->$fieldname)));

                if ($r > 0) $r = -1;
                elseif ($r < 0) $r = 1;
                else $r = 0;

                return $r;
            }

        } elseif (GETPOST('sortorder','alpha') == 'asc') {      //tri croissant

            if($fieldtype == 'ef')      //extrafield
            {
                $r = strcmp(strtoupper(trim($objA->product->array_options['options_'.$fieldname])), strtoupper(trim($objB->product->array_options['options_'.$fieldname])));

                if ($r < 0) $r = -1;
                elseif ($r > 0) $r = 1;
                else $r = 0;

                return $r;

            }
			else if ($fieldtype == 'd')
			{
				$r = strcmp(strtoupper(trim($objA->$fieldname)), strtoupper(trim($objB->$fieldname)));

				if ($r < 0) $r = -1;
				elseif ($r > 0) $r = 1;
				else $r = 0;

				return $r;
			}
            else
            {
                $r = strcmp(strtoupper(trim($objA->product->$fieldname)), strtoupper(trim($objB->product->$fieldname)));

                if ($r < 0) $r = -1;
                elseif ($r > 0) $r = 1;
                else $r = 0;

                return $r;
            }
        }

    }

	function changePMP(&$PDOdb) {

		foreach ($this->TInventorydet as $k => &$TInventorydet)
		{

			if($TInventorydet->new_pmp>0) {
				$TInventorydet->pmp = $TInventorydet->new_pmp;
				$TInventorydet->new_pmp = 0;

				$PDOdb->Execute("UPDATE ".MAIN_DB_PREFIX."product as p SET pmp = ".$TInventorydet->pmp."
				WHERE rowid = ".$TInventorydet->fk_product );

				if((float)DOL_VERSION<4.0) {

					$PDOdb->Execute("UPDATE ".MAIN_DB_PREFIX."product_stock SET pmp=".$TInventorydet->pmp."
					 WHERE fk_entrepot = ".$this->fk_warehouse." AND fk_product = ".$TInventorydet->fk_product) ;

				}

			}
		}

		parent::save($PDOdb);

	}

	function save(&$PDOdb)
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

    function add_product(&$PDOdb, $fk_product, $fk_entrepot='', $addWithCurrentDetails = false) {
  	global $langs;
	if(empty($fk_product)) {
		setEventMessages($langs->trans('ErrorNoSelectedProductToAdd'), 'errors');
		return false;
	}

        $k = $this->addChild($PDOdb, 'TInventorydet');
        $det =  &$this->TInventorydet[$k];

        $det->fk_inventory = $this->getId();
        $det->fk_product = $fk_product;
	$det->fk_warehouse = empty($fk_entrepot) ? $this->fk_warehouse : $fk_entrepot;
//        var_dump($det);exit;
        $det->load_product();

        if($addWithCurrentDetails) {
        	$det->product->load_stock();
        	$det->qty_view = $det->product->stock_warehouse[$fk_entrepot]->real;
        	$det->new_pmp= $det->product->pmp;
        }

        $date = $this->get_date('date_inventory', 'Y-m-d');
        if(empty($date))$date = $this->get_date('date_cre', 'Y-m-d');
        $det->setStockDate($PDOdb, $date , $fk_entrepot);

    }

    function add_batch(&$PDOdb, $fk_product, $fk_entrepot='', $date, $addWithCurrentDetails = false) {
        global $langs, $db;
        if(empty($fk_product)) {
            setEventMessages($langs->trans('ErrorNoSelectedProductToAdd'), 'errors');
            return false;
        }
        $prod = new Product($db);
        $prod->fetch($fk_product);
        $prod->load_stock();

        $detailLot = $prod->stock_warehouse[$fk_entrepot]->detail_batch;
//         var_dump($detailLot); exit;
        //On récupère tous les mouvements de stocks du produit entre aujourd'hui et la date de l'inventaire
        $sql = "SELECT value, price, batch
				FROM ".MAIN_DB_PREFIX."stock_mouvement
				WHERE fk_product = ".$fk_product."
					AND fk_entrepot = ".$fk_entrepot."
					AND datem > '".date('Y-m-d 23:59:59',strtotime($date))."'
				ORDER BY datem DESC";

//          echo $sql.'<br>'; exit;
        $PDOdb->Execute($sql);
        $TMouvementStock = $PDOdb->Get_All();

        $laststock = $stock;
        $lastpmp = $pmp;
        //Pour chacun des mouvements on recalcule le PMP et le stock physique
        foreach($TMouvementStock as $mouvement){

            $price = ($mouvement->price>0 && $mouvement->value>0) ? $mouvement->price : $lastpmp;  // prix du mouvement si positif

            $stock_value = $laststock * $lastpmp; // valeur du stock

            $laststock -= $mouvement->value; // recalcul du stock en fonction du mouvement
            $detailLot[$mouvement->batch]->qty -= $mouvement->value;
            $last_stock_value = $stock_value - ($mouvement->value * $price); // valorisation du stock en fonction du mouvement
            if($last_stock_value < 0) $last_stock_value = 0;

            //if($last_stock_value<0 || $laststock<0) null;
            $lastpmp = ($laststock != 0) ? $last_stock_value / $laststock : $lastpmp; // S'il y a un stock, alors son PMP est sa valeur totale / nombre de pièce

        }

        $total_qty = 0;
        if (!empty($detailLot)){
			foreach ($detailLot as $lot => $detail)
			{
//             var_dump($lot, $detail);exit;
				$k = $this->addChild($PDOdb, 'TInventorydet');
				$det =  &$this->TInventorydet[$k];

				$det->fk_inventory = $this->getId();
				$det->fk_product = $fk_product;
				$det->fk_warehouse = empty($fk_entrepot) ? $this->fk_warehouse : $fk_entrepot;
				//        var_dump($det);exit;
				$det->load_product();
				$det->lot = $lot;
				$det->qty_stock = $detail->qty;
				$total_qty += $detail->qty;

				if($addWithCurrentDetails) {
					$det->product->load_stock();
					$det->qty_view = $detail->qty;
					$det->new_pmp= $det->product->pmp;
				}

				$date = $this->get_date('date_inventory', 'Y-m-d');
				if(empty($date))$date = $this->get_date('date_cre', 'Y-m-d');
//             $det->setStockDate($PDOdb, $date , $fk_entrepot);
			}
		}
//         var_dump((float) $total_qty, (float) $prod->stock_warehouse[$fk_entrepot]->real); exit;
        if ((float) $total_qty !== (float) $prod->stock_warehouse[$fk_entrepot]->real)
        {
            $k = $this->addChild($PDOdb, 'TInventorydet');
            $det =  &$this->TInventorydet[$k];

            $det->fk_inventory = $this->getId();
            $det->fk_product = $fk_product;
            $det->fk_warehouse = empty($fk_entrepot) ? $this->fk_warehouse : $fk_entrepot;
            //        var_dump($det);exit;
            $det->load_product();
            $det->lot = "NA";
            $det->qty_stock = (float) $prod->stock_warehouse[$fk_entrepot]->real - $total_qty;

            if($addWithCurrentDetails) {
                $det->product->load_stock();
                $det->qty_view = $det->product->stock_warehouse[$fk_entrepot]->real;
                $det->new_pmp= $det->product->pmp;
            }
        }

    }

    function correct_stock($fk_product, $id_entrepot, $nbpiece, $movement, $label='', $price=0, $inventorycode='', $batch='')
	{
		global $conf, $db, $langs, $user;

		/* duplication method product to add datem */
		if ($id_entrepot)
		{
			$db->begin();

			require_once DOL_DOCUMENT_ROOT .'/product/stock/class/mouvementstock.class.php';

			$op[0] = "+".trim($nbpiece);
			$op[1] = "-".trim($nbpiece);

			$datem = empty($conf->global->INVENTORY_USE_INVENTORY_DATE_FROM_DATEMVT) ? dol_now() : $this->date_inventory;

			$movementstock=new MouvementStock($db);
			if ($batch == '')
			    $result=$movementstock->_create($user,$fk_product,$id_entrepot,$op[$movement],$movement,$price,$label,$inventorycode, $datem);
			else
			    $result=$movementstock->_create($user,$fk_product,$id_entrepot,$op[$movement],$movement,$price,$label,$inventorycode, $datem, '', '', $batch);

			if ($result >= 0)
			{
				$db->commit();
				return 1;
			}
			else
			{
			    $this->error=$movementstock->error;
			    $this->errors=$movementstock->errors;

				$db->rollback();
				return -1;
			}
		}
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

				if (! $this->per_batch || !$product->hasbatch()) {
    				if(empty($this->title))
    					$this->correct_stock($product->id, $TInventorydet->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStock', $href, $this->getId()));
    				else
    					$this->correct_stock($product->id, $TInventorydet->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStockWithNomInventaire', $href, $this->title));
				}
				else {
				    if($TInventorydet->lot !== '')
				    {
				        if(empty($this->title))
				            $this->correct_stock($product->id, $TInventorydet->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStock', $href, $this->getId()), 0 , '', $TInventorydet->lot);
			            else
			                $this->correct_stock($product->id, $TInventorydet->fk_warehouse, $nbpiece, $movement, $langs->trans('inventoryMvtStockWithNomInventaire', $href, $this->title), 0 , '', $TInventorydet->lot);
				    }
				}
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
		$this->add_champs('fk_inventory,fk_warehouse,fk_product,entity', 'type=entier;');
		$this->add_champs('qty_view,qty_stock,qty_regulated,pmp,pa,new_pmp', 'type=float;');
		$this->add_champs('lot', 'type=text;');

		$this->_init_vars();
	    $this->start();

		$this->entity = $conf->entity;
		$this->errors = array();

		$this->product = null;

		$this->current_pa = 0;

	}

    function save(&$PDOdb)
    {
        global $conf;

        if (!empty($conf->global->INVENTORY_USE_ONLY_INTEGER)) $this->qty_view = (int) $this->qty_view;

        return parent::save($PDOdb); // TODO: Change the autogenerated stub
    }


    function load(&$PDOdb, $id, $loadChild = true)
	{
		global $conf;

		$res = parent::load($PDOdb, $id, $loadChild);
		$this->load_product();
        $this->fetch_current_pa();

		return $res;
	}

	function fetch_current_pa() {
		global $db,$conf;

		if(empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) return false;

		if($this->pa>0){
			$this->current_pa = $this->pa;
		}
		else {

			dol_include_once('/fourn/class/fournisseur.product.class.php');
			$p= new ProductFournisseur($db);
			$p->find_min_price_product_fournisseur($this->fk_product);

			if($p->fourn_qty>0)	$this->current_pa = $p->fourn_price / $p->fourn_qty;
		}
		return true;
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
//		var_dump($res, $this->product);
		if($res>0) {
			$stock = isset($this->product->stock_warehouse[$fk_warehouse]->real) ? $this->product->stock_warehouse[$fk_warehouse]->real : 0;

			if(isset($this->product->stock_warehouse[$fk_warehouse]->pmp))$this->product->stock_warehouse[$fk_warehouse]->pmp = (float)$this->product->stock_warehouse[$fk_warehouse]->pmp;

			if(empty($this->product->stock_warehouse[$fk_warehouse]->pmp)) $pmp = (float)$this->product->pmp;
			else {
				$pmp = $this->product->stock_warehouse[$fk_warehouse]->pmp;
			}

		}

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

			$price = ($mouvement->price>0 && $mouvement->value>0) ? $mouvement->price : $lastpmp;  // prix du mouvement si positif

			$stock_value = $laststock * $lastpmp; // valeur du stock

			$laststock -= $mouvement->value; // recalcul du stock en fonction du mouvement

			$last_stock_value = $stock_value - ($mouvement->value * $price); // valorisation du stock en fonction du mouvement
			if($last_stock_value < 0) $last_stock_value = 0;

			//if($last_stock_value<0 || $laststock<0) null;
			$lastpmp = (round($laststock, 4) != 0) ? $last_stock_value / $laststock : $lastpmp; // S'il y a un stock, alors son PMP est sa valeur totale / nombre de pièce

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
