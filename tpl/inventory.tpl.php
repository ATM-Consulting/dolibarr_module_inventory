<script type="text/javascript">
    function save_qty(k) {
        
        var $input = $('input[name="qty_to_add['+k+']"]');
        var fk_det_inventory = $('input[name=det_id_'+k+']').val();
        var qty = $input.val();

        <?php if (!empty($conf->global->INVENTORY_USE_ONLY_INTEGER)) { ?>
            qty = parseInt(qty);
        <?php } ?>

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
            <?php 
            if($view['per_batch'])
            {
            ?>
            	parentProdline = $('#prod_'+k).val();
            	parentProd = $('input[name=det_id_'+parentProdline+']').val();
                
                $.ajax({
                    url:"script/interface.php"
                    ,data:{
                        'fk_det_inventory' : parentProd
                        ,'qty': qty
                        ,'put':'qty'
                    }
                    
                }).done(function(data) {
                	$('#qty_view_'+parentProdline).html(data);
                });
            <?php
            }
            ?>
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
			<?php
			if($view['per_batch'])
			{
			?>
				parentProdline = $('#prod_'+k).val();
				parentProd = $('input[name=det_id_'+parentProdline+']').val();
				$.ajax({
					url:"script/interface.php"
					,data:{
						'fk_det_inventory' : parentProd
						,'qty': -qty
						,'put':'qty'
					}

				}).done(function(data) {
					$('#qty_view_'+parentProdline).html(data);
				});
			<?php
			}
			?>
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

	function deleteLine(k)
	{
		if (confirm('Confirmez-vous la suppression de la ligne ?')) {
			qty = 0;
			qty-= parseFloat($('#qty_view_'+k).html());
			if($('#prod_line_'+k).length !== 0)
			{
				parentProd = $('#prod_line_'+k).val();
			} else {
				parentProdline = $('#prod_'+k).val();
	        	parentProd = $('input[name=det_id_'+parentProdline+']').val();
			}
            $.ajax({
                url:"script/interface.php"
                ,data:{
                    'fk_det_inventory' : parentProd
                    ,'qty': qty
                    ,'put':'qty'
                }
                
            }).done(function(data) {
                console.log(data)
            	document.location = $('#delline_'+k).attr('data-href');
            });
		}
		
		
	}
    
    function addBatch(k)
    {
        var prodline = $('#qty_view_'+k).parent().parent();
        
        if ($('#new_batch_'+k).length == 0)
        {
            var html = '';
            html+= '<tr style="background-color: #e6cece;"><td>Nouveau lot</td><td></td>';
            <?php if (! empty($conf->barcode->enabled)) { ?>
            html+= '<td align="center" style="background-color: #e8e8ff;"></td>';
            <?php } ?>
            html+= '<td align="center" style="background-color: #e8e8ff;"><input type="texte" value="" id="new_batch_'+k+'"></td>';
            html+= '<td align="center" style="background-color: #e8e8ff;">0</td>';
            html+= '<td align="center" style="background-color: #e8e8ff;"></td>';
            html+= '<td align="center" style="background-color: #e8e8ff;"></td>';
            html+= '<td align="center"><input type="texte" value="" id="new_batch_qty_'+k+'" value="0"></td>';
            html+= '<td align="left"><a class="butAction" href="javascript:new_batch('+k+')">Envoyer</a></td>';
            html+= '<td colspan="4"></td></tr>';
            prodline.after(html);
        }
    }

    function new_batch(k)
    {
        var lot = $('#new_batch_'+k).val();
		var qty = $('#new_batch_qty_'+k).val();
		var prodline = $('#qty_view_'+k).parent().parent();
        var rowid = prodline.find('#prod_'+k).val();
		//console.log(qty);
		if (lot !== '') 
		{
			$.ajax({
	            url:"script/interface.php"
	            ,data:{
	                'fk_inventory' : <?php echo $inventoryTPL['id']; ?>
            		,'index' : k
            		,'batch' : lot
            		,'qty' : qty
	                ,'put':'batch'
	            }
	            
	        }).done(function(d) {
	           	//onsole.log(d);
	           	if (d == '1')
	           	{
	           		parentProdline = $('#prod_'+k).val();
	            	parentProd = $('input[name=det_id_'+parentProdline+']').val();
	                
	                $.ajax({
	                    url:"script/interface.php"
	                    ,data:{	                        
		                    'fk_det_inventory' : rowid
	                        ,'qty': qty
	                        ,'put':'qty'
	                    }
	                    
	                }).done(function(data) {
		                console.log(data);
	                	document.location = '?id='+<?php echo $inventoryTPL['id']; ?>+'&action=edit';
	                });
					
	           	}
	        });
		} else {
			$('#new_batch_'+k).focus();
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
    <input type="hidden" name="formfilteraction" value="list" />
    <input type="hidden" name="sortfield" value="<?php echo $sortfield ?>">
    <input type="hidden" name="sortorder" value="<?php echo $sortorder ?>">

    <table width="100%" class="border workstation inventory_table">
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
				<td align="left" class="produit">&nbsp;&nbsp;<?php echo $row['produit']; ?></td>
				<td align="center" class="warehouse"><?php echo $row['entrepot']; ?></td>
				<?php if (! empty($conf->barcode->enabled)) { ?>
					<td align="center"><?php echo $row['barcode']; ?></td>
				<?php } ?>
				<?php if($view['per_batch']) {?>
					<td align="center" style="background-color: #e8e8ff;"><?php echo $row['lot']; ?></td>
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
                <?php


				// Fields from hook
				$parameters=array('arrayfields'=> $arrayfields, 'k' => $k, 'line' => $row);
				$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters, $inventory);    // Note that $action and $object may have been modified by hook
				print $hookmanager->resPrint;

                //définition de l'objet à afficher pour le tpl extrafields_list_print_fields
                $object = new Product($db);               //produit
                $object->fetch($row['fk_product']);
                $obj = (object) $object->array_options; //extrafields du produit

				if(intval(DOL_VERSION) > 6 ) {
					include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_print_fields.tpl.php';
				}
                ?>
				<?php if ($view['is_already_validate'] != 1) { ?>
					<td align="center" width="20%"><?php echo $row['action']; ?></td>
				<?php }

				?>
			</tr>
			<?php $i++; ?>
		
		<?php

        }
		
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
			<?php if ($view['can_validate'] == 1) {
			    $urlToken = '';
                if (function_exists('newToken')) $urlToken = "&token=".newToken();
                ?>
                <a onclick="if (!confirm('Confirmez-vous la vidange ?')) return false;" href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=flush" class="butActionDelete">Vider</a>
                &nbsp;&nbsp;&nbsp;
                <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=delete<?php echo $urlToken; ?>" class="butActionDelete">Supprimer</a>
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
	
<script>
$(document).ready(function(){
	$('.enfant').each(function(){
		parentProdline = $(this).val();
		tr = $('#qty_view_'+parentProdline).parent().parent();
	
		tr.find('td').last().find('a').hide();
	});
});
</script>
	
