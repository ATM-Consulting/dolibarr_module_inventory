<script type="text/javascript">
    function save_qty(k) {
        
        var $input = $('input[name="qty_to_add['+k+']"]');
        var fk_det_inventory = $('input[name=det_id_'+k+']').val();
        var qty = $input.val();
        
        $('#a_save_qty_'+k).hide();
        
        $.ajax({
            url:"script/interface.php"
            ,data:{
                'fk_det_inventory' : fk_det_inventory
                ,'qty': qty
                ,'put':'qty'
            }
            
        }).done(function(data) {
            $('#qty_view_'+k).html(data);
            $input.val(0);
            $.jnotify("Quantité ajoutée : "+qty, "mesgs" );
            
            $('#a_save_qty_'+k).show();
            
            hide_save_button();
        });
        
        
    }
	
	function save_qty_minus(k) {
        
        var $input = $('input[name="qty_to_add['+k+']"]');
        var fk_det_inventory = $('input[name=det_id_'+k+']').val();
        var qty = $input.val();
        
        $('#a_save_qty_minus_'+k).hide();
        
        $.ajax({
            url:"script/interface.php"
            ,data:{
                'fk_det_inventory' : fk_det_inventory
                ,'qty': -qty
                ,'put':'qty'
            }
            
        }).done(function(data) {
            $('#qty_view_'+k).html(data);
            $input.val(0);
            $.jnotify("Quantité enlevée : "+qty, "mesgs" );
            
            $('#a_save_qty_'+k).show();
            
            hide_save_button();
        });
        
        
    }
    
    function save_pmp(k) {
    	
        var $input = $('input[name="new_pmp['+k+']"]');
        var fk_det_inventory = $('input[name=det_id_'+k+']').val();
        var pmp = $input.val();
        
        $('#a_save_new_pmp_'+k).hide();
        
        $.ajax({
            url:"script/interface.php"
            ,data:{
                'fk_det_inventory' : fk_det_inventory
                ,'pmp': pmp
                ,'put':'pmp'
            }
            
        }).done(function(data) {
           	$input.css({"background-color":"#66ff66"});
            $.jnotify("PMP sauvegardé : "+pmp, "mesgs" );
            $('#a_save_new_pmp_'+k).show();
             
        });
        
    }
    
    function hide_save_button() {
       var nb = 0;
       $('input[name^="qty_to_add"]').each(function() {
           nb += $(this).val();
       });
       
       if(nb>0) {
           $('input[name=modify]').show();
           
       }
       else{
           $('input[name=modify]').hide();
           
       }
        
    }
</script>

<?php if ($view['is_already_validate'] != 1) { ?>
	<strong>Ajouter un produit dans l'inventaire : </strong>
	<form action="<?php echo $view['url']; ?>" method="POST">
		<input type="hidden" name="action" value="add_line" />
		<input type="hidden" name="id" value="<?php echo $inventoryTPL['id']; ?>" />
	
		<?php echo $product['list']; ?>
		
			<input class="butAction" type="submit" value="Ajouter Produit" />
	</form>
<?php } ?>

<form action="<?php echo $view['url']; ?>" method="POST">
	
	<?php if ($view['is_already_validate'] == 1) { ?>
		<div class="warning">Cet inventaire est validé</div>
	<?php } ?>
	
	<input type="hidden" name="action" value="save" />
	<input type="hidden" name="id" value="<?php echo $inventoryTPL['id']; ?>" />
	
	<table width="100%" class="border workstation">
		<?php
		
		_headerList($view); 
        
        $total_pmp = $total_pa = $total_pmp_actual = $total_pa_actual =$total_current_pa=$total_current_pa_actual = 0;
        $i=1;
        foreach ($TInventory as $k=>$row) { 
            
            $total_pmp+=round($row['pmp_stock'],2);
            $total_pa+=round($row['pa_stock'],2);
            $total_pmp_actual+=round($row['pmp_actual'],2);
            $total_pa_actual+=round($row['pa_actual'],2);
            
			if($i%20 === 0)
			{
            	_headerList($view);
			} // Fin IF principal
	    	?>
			<tr style="background-color:<?php echo ($k%2 == 0) ? '#fff':'#eee'; ?>;">
				<td align="left">&nbsp;&nbsp;<?php echo $row['produit']; ?></td>
				<td align="center"><?php echo $row['entrepot']; ?></td>
				<?php if (! empty($conf->barcode->enabled)) { ?>
					<td align="center"><?php echo $row['barcode']; ?></td>
				<?php } ?>
				<?php if ($view['can_validate'] == 1) { ?>
					<td align="center" style="background-color: #e8e8ff;"><?php echo $row['qty_stock']; ?></td>
					<td align="right" style="background-color: #e8e8ff;"><?php echo price( $row['pmp_stock']); ?></td>
					<td align="right" style="background-color: #e8e8ff;"><?php echo price( $row['pa_stock']); ?></td>
	               <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	                 	echo '<td align="right" style="background-color: #e8e8ff;">'.price($row['current_pa_stock']).'</td>';
						 $total_current_pa+=$row['current_pa_stock'];
	                 }   
	                    
	               ?>
				<?php } ?>
				<td align="center"><?php echo $row['qty']; ?>&nbsp;&nbsp;<span id="qty_view_<?php echo $row['k']; ?>"><?php echo $row['qty_view']; ?></span>
                    <input type="hidden" name="det_id_<?php echo $row['k']; ?>" value="<?php echo $row['id']; ?>" /> 
                </td>
                <?php if ($view['can_validate'] == 1) { ?>
                    <td align="right"><?php echo price($row['pmp_actual']); ?></td>
                    <?php
                    if(!empty($user->rights->inventory->changePMP)) {
                    	echo '<td align="right">'.$row['pmp_new'].'</td>';	
					}
                    ?>
                    <td align="right"><?php echo price($row['pa_actual']); ?></td>
		               <?php
		                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
		                 	echo '<td align="right">'.price($row['current_pa_actual']).'</td>';
							 $total_current_pa_actual+=$row['current_pa_actual'];
		                 }   
		                    
		               ?>
                    <td align="center"><?php echo $row['qty_regulated']; ?></td>
				<?php } ?>
				<?php if ($view['is_already_validate'] != 1) { ?>
					<td align="center" width="20%"><?php echo $row['action']; ?></td>
				<?php } ?>
			</tr>
			<?php $i++; ?>
		
		<?php } 
		
		_footerList($view,$total_pmp,$total_pmp_actual,$total_pa,$total_pa_actual, $total_current_pa,$total_current_pa_actual);
		?>
	
	  
		
	</table>
	
	<?php if ($view['is_already_validate'] != 1) { ?>
		<div class="tabsAction" style="height:30px;">
			<?php if ($view['mode'] == 'view') { ?>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=printDoc" class="butAction">Imprimer</a>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=exportCSV" class="butAction">Export CSV</a>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=edit" class="butAction">Modifier</a>
				<?php 
				 if(!empty($user->rights->inventory->changePMP)) {
				 	echo '<a href="javascript:;" onclick="javascript:if (!confirm(\'Confirmez-vous l\\\'application du nouveau PMP ?\')) return false; else document.location.href=\''.$view['url']
				 			.'?id='.$inventoryTPL['id']
				 			.'&action=changePMP&token='.$view['token'].'\'; " class="butAction">Appliquer le PMP</a>';
				 }
				
				if ($view['can_validate'] == 1) { ?>
					<a href="javascript:;" onclick="javascript:if (!confirm('Confirmez-vous la régulation ?')) return false; else document.location.href='<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=regulate&token=<?php echo $view['token']; ?>'; " class="butAction">Réguler le stock</a>
				<?php } ?>
			<?php } ?>
			<?php if ($view['mode'] == 'edit') { ?>
				<input name="back" type="button" class="butAction" value="Quitter la saisie" onclick="document.location='?id=<?php echo $inventoryTPL['id']; ?>&action=view';" />
			<?php } ?>
			<?php if ($view['can_validate'] == 1) { ?>
                <a onclick="if (!confirm('Confirmez-vous la vidange ?')) return false;" href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=flush" class="butActionDelete">Vider</a>
                &nbsp;&nbsp;&nbsp;
                <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=delete" class="butActionDelete">Supprimer</a>
        	<?php } ?>
		</div>
	<?php } ?>
	<?php if ($view['is_already_validate'] == 1) { ?>
		<div class="tabsAction">
			<?php if ($view['can_validate'] == 1) { ?>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=printDoc" class="butAction">Imprimer</a>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=exportCSV" class="butAction">Export CSV</a>
				<!-- <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=delete" class="butActionDelete">Supprimer</a> -->
				<a href="#" title="Cet inventaire est validé" class="butActionRefused">Supprimer</a>
			<?php } ?>
		</div>
	<?php } ?>
</form>
<p>Date de création : <?php echo $inventoryTPL['date_cre']; ?><br />Dernière mise à jour : <?php echo $inventoryTPL['date_maj']; ?></p>
	

	
