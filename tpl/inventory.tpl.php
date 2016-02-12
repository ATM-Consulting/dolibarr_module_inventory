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
		<tr style="background-color:#dedede;">
			<th align="left" width="20%">&nbsp;&nbsp;Produit</th>
			<?php if (! empty($conf->barcode->enabled)) { ?>
				<th align="center">Code-barre</td>
			<?php } ?>
			<?php if ($view['can_validate'] == 1) { ?>
				<th align="center" width="20%">Quantité théorique</th>
				<th align="center" width="20%" colspan="2">Valeur théorique</th>
			<?php } ?>
			    <th align="center" width="20%">Quantité réelle</th>
			<?php if ($view['can_validate'] == 1) { ?>
			    <th align="center" width="20%" colspan="2">Valeur réelle</th>	
				<th align="center" width="15%">Quantité régulée</th>
			<?php } ?>
			<?php if ($view['is_already_validate'] != 1) { ?>
				<th align="center" width="5%">#</th>
			<?php } ?>
		</tr>
		<?php if ($view['can_validate'] == 1) { ?>
    	<tr style="background-color:#dedede;">
    	    <th colspan="<?php echo empty($conf->barcode->enabled) ? 2 : 3;  ?>">&nbsp;</th>
    	    <th>PMP</th>
    	    <th>Dernier PA</th>
    	    <th>&nbsp;</th>
    	    <th>PMP</th>
            <th>Dernier PA</th>
            <th>&nbsp;</th>
            <?php if ($view['is_already_validate'] != 1) { ?>
            <th>&nbsp;</th>
            <?php } ?>
    	</tr>
    	<?php } 
        
        $total_pmp = $total_pa = $total_pmp_actual = $total_pa_actual = 0;
        $i=1;
        foreach ($TInventory as $k=>$row) { 
            
            $total_pmp+=$row['pmp_stock'];
            $total_pa+=$row['pa_stock'];
            $total_pmp_actual+=$row['pmp_actual'];
            $total_pa_actual+=$row['pa_actual'];
            
			if($i%20 === 0)
			{
            ?>
			<tr style="background-color:#dedede;">
				<th align="left" width="20%">&nbsp;&nbsp;Produit</th>
				<?php if (! empty($conf->barcode->enabled)) { ?>
					<th align="center">Code-barre</td>
				<?php } ?>
				<?php if ($view['can_validate'] == 1) { ?>
					<th align="center" width="20%">Quantité théorique</th>
					<th align="center" width="20%" colspan="2">Valeur théorique</th>
				<?php } ?>
				    <th align="center" width="20%">Quantité réelle</th>
				<?php if ($view['can_validate'] == 1) { ?>
				    <th align="center" width="20%" colspan="2">Valeur réelle</th>	
					<th align="center" width="15%">Quantité régulée</th>
				<?php } ?>
				<?php if ($view['is_already_validate'] != 1) { ?>
					<th align="center" width="5%">#</th>
				<?php } ?>
			</tr>
			<?php if ($view['can_validate'] == 1) { ?>
	    	<tr style="background-color:#dedede;">
	    	    <th colspan="<?php echo empty($conf->barcode->enabled) ? 2 : 3;  ?>">&nbsp;</th>
	    	    <th>PMP</th>
	    	    <th>Dernier PA</th>
	    	    <th>&nbsp;</th>
	    	    <th>PMP</th>
	            <th>Dernier PA</th>
	            <th>&nbsp;</th>
	            <?php if ($view['is_already_validate'] != 1) { ?>
	            <th>&nbsp;</th>
	            <?php } ?>
	    	</tr>
	    	<?php 
				} 
			} // Fin IF principal
	    	?>
			<tr style="background-color:<?php echo ($k%2 == 0) ? '#fff':'#eee'; ?>;">
				<td align="left">&nbsp;&nbsp;<?php echo $row['produit']; ?></td>
				<?php if (! empty($conf->barcode->enabled)) { ?>
					<td align="center"><?php echo $row['barcode']; ?></td>
				<?php } ?>
				<?php if ($view['can_validate'] == 1) { ?>
					<td align="center" style="background-color: #e8e8ff;"><?php echo $row['qty_stock']; ?></td>
					<td align="right" style="background-color: #e8e8ff;"><?php echo price( $row['pmp_stock']); ?></td>
					<td align="right" style="background-color: #e8e8ff;"><?php echo price( $row['pa_stock']); ?></td>
				<?php } ?>
				<td align="center"><?php echo $row['qty']; ?>&nbsp;&nbsp;<span id="qty_view_<?php echo $row['k']; ?>"><?php echo $row['qty_view']; ?></span>
                    <input type="hidden" name="det_id_<?php echo $row['k']; ?>" value="<?php echo $row['id']; ?>" /> 
                </td>
                <?php if ($view['can_validate'] == 1) { ?>
                    <td align="right"><?php echo price($row['pmp_actual']); ?></td>
                    <td align="right"><?php echo price($row['pa_actual']); ?></td>
                    <td align="center"><?php echo $row['qty_regulated']; ?></td>
				<?php } ?>
				<?php if ($view['is_already_validate'] != 1) { ?>
					<td align="center" width="20%"><?php echo $row['action']; ?></td>
				<?php } ?>
			</tr>
			<?php $i++; ?>
		
		<?php } ?>
	
	    <?php if ($view['can_validate'] == 1) { ?>
        <tr style="background-color:#dedede;">
            <th colspan="2">&nbsp;</th>
            <th align="right"><?php echo price($total_pmp) ?></th>
            <th align="right"><?php echo price($total_pa) ?></th>
            <th>&nbsp;</th>
            <th align="right"><?php echo price($total_pmp_actual) ?></th>
            <th align="right"><?php echo price($total_pa_actual) ?></th>
            <th>&nbsp;</th>
            <?php if ($view['is_already_validate'] != 1) { ?>
            <th>&nbsp;</th>
            <?php } ?>
        </tr>
        <?php } ?>   
	       
	  
		
	</table>
	
	<?php if ($view['is_already_validate'] != 1) { ?>
		<div class="tabsAction" style="height:30px;">
			<?php if ($view['mode'] == 'view') { ?>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=printDoc" class="butAction">Imprimer</a>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=exportCSV" class="butAction">Export CSV</a>
				<a href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=edit" class="butAction">Modifier</a>
				<?php if ($view['can_validate'] == 1) { ?>
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
				<!-- <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="<?php echo $view['url']; ?>?id=<?php echo $inventoryTPL['id']; ?>&action=delete" class="butActionDelete">Supprimer</a> -->
				<a href="#" title="Cet inventaire est validé" class="butActionRefused">Supprimer</a>
			<?php } ?>
		</div>
	<?php } ?>
</form>
<p>Date de création : <?php echo $inventoryTPL['date_cre']; ?><br />Dernière mise à jour : <?php echo $inventoryTPL['date_maj']; ?></p>
	

	
