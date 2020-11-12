<?php

    require('../config.php');
    require('../class/inventory.class.php');

    
    $get = GETPOST('get','alpha');
    $put = GETPOST('put','alpha');
    
    
    $PDOdb=new TPDOdb;
    
    switch ($put) {
        case 'qty':
            if (!$user->rights->inventory->write) { echo -1; exit; }
            
            $fk_det_inventory = GETPOST('fk_det_inventory','int');
            
            $det = new TInventorydet;
            if( $det->load($PDOdb, $fk_det_inventory)) {
                $det->qty_view+=GETPOST('qty','int');
                $det->save($PDOdb);
                
                echo $det->qty_view;
            }
            else {
                echo -2;
            }            
            
            break;
			
        case 'pmp':
            if (!$user->rights->inventory->write || !$user->rights->inventory->changePMP) { echo -1; exit; }
            
            $fk_det_inventory = GETPOST('fk_det_inventory','int');
            
            $det = new TInventorydet;
            if( $det->load($PDOdb, $fk_det_inventory)) {
                $det->new_pmp=price2num(GETPOST('pmp','int'));
                $det->save($PDOdb);
                
                echo $det->new_pmp;
            }
            else {
                echo -2;
            }            
            
            break;
        
        case 'batch':
            if (!$user->rights->inventory->write) { echo -1; exit; }
            
            $index =  (int)GETPOST('index','int');
            $lot = GETPOST('batch','alpha');
            $qty =  (float)GETPOST('qty','int');
            
            // id de l'inventaire
            $fk_inventory =  (int)GETPOST('fk_inventory','int');
            $inv = new TInventory();
            $inv->load($PDOdb, $fk_inventory);
            
            // ajouter une ligne copie de la dernière ligne de l'inventaire
            
            
            $k = $inv->addChild($PDOdb, 'TInventorydet');
            $det =  &$inv->TInventorydet[$k];
            
//             echo '<pre>'; print_r($inv->TInventorydet[$index]->id); exit;
            $det->fk_inventory = $inv->getId();
            $det->fk_product = $inv->TInventorydet[$index]->fk_product;
            $det->fk_warehouse = $inv->TInventorydet[$index]->fk_warehouse;
//             //        var_dump($det);exit;
            $det->load_product();
            $det->lot = $lot;
            $det->qty_view = $qty;
            $inv->save($PDOdb);
                        
            echo 1;
            
            break;
    }
 
