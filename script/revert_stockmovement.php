<?php
require('../config.php');
dol_include_once('/product/stock/class/mouvementstock.class.php');
//Nom de l'inventaire
$label = GETPOST('label');

$sql = "SELECT *
FROM `llx_stock_mouvement`
WHERE `label` LIKE '%$label%' ";

$resql = $db->query($sql);
if(!empty($resql) && $db->num_rows($resql) > 0){

    $db->begin();
    $count = 0;
    while($obj = $db->fetch_object($resql)){
        $movementstock=new MouvementStock($db);
        if($obj->value < 0){
            $val = abs($obj->value);
            $movement = 0;
        }
        else {
            $val = '-'.$obj->value;
            $movement = 1;
        }
        $result=$movementstock->_create($user,$obj->fk_product,$obj->fk_entrepot,$val,$movement,$obj->price,$langs->trans('RevertInventory').' '.$obj->label,$obj->inventorycode);

        if ($result >= 0)
        {
            $count++;
            $db->commit();
            print 'OK </br>';
        }
        else
        {
            print '<pre>';
            print_r($movementstock->error);
            print_r($movementstock->errors);
            print '</pre>';

            $db->rollback();
            print 'KO </br>';
        }

    }
    print $count.' Success';
}
