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
[onshow;block=begin;when [view.is_already_validate]!='1']
	<strong>Ajouter un produit dans l'inventaire : </strong>
	<form action="[view.url]" method="POST">
		<input type="hidden" name="action" value="add_line" />
		<input type="hidden" name="id" value="[inventory.id]" />
		
		[product.list;strconv=no]
		
			<input class="butAction" type="submit" value="Ajouter Produit" />
	</form>
[onshow;block=end]
<form action="[view.url]" method="POST">
	[onshow;block=begin;when [view.is_already_validate]=='1']
		<div class="warning">Cet inventaire est validé</div>
	[onshow;block=end]
	
	<input type="hidden" name="action" value="save" />
	<input type="hidden" name="id" value="[inventory.id]" />
	
	<table width="100%" class="border workstation">
		<tr style="background-color:#dedede;">
			<th align="left" width="20%">&nbsp;&nbsp;Produit</th>
			<th align="center" width="20%">Quantité vue</th>
			[onshow;block=begin;when [view.can_validate]=='1']
				<th align="center" width="20%">Valeur Constatée</th>
				<th align="center" width="20%">Quantité stock</th>
				<th align="center" width="20%">Valeur</th>
				<th align="center" width="15%">Quantité régulée</th>
			[onshow;block=end]
			[onshow;block=begin;when [view.is_already_validate]!='1']
				<th align="center" width="5%">Action</th>
			[onshow;block=end]
		</tr>
		<tr id="WS[workstation.id]" style="background-color:#fff;">
			<td align="left">&nbsp;&nbsp;[TInventory.produit;strconv=no;block=tr]</td>
			<td align="center">[TInventory.qty;strconv=no;block=tr]&nbsp;&nbsp;<span id="qty_view_[TInventory.k]">[TInventory.qty_view;strconv=no;]</span>
			    <input type="hidden" name="det_id_[TInventory.k]" value="[TInventory.id]" /> 
			</td>
			[onshow;block=begin;when [view.can_validate]=='1']
				<td align="right">[TInventory.pmp;strconv=no;]</td>
				<td align="center">[TInventory.qty_stock;strconv=no;]</td>
				<td align="right">[TInventory.pmp_actual;strconv=no;]</td>
				<td align="center">[TInventory.qty_regulated;strconv=no;]</td>
			[onshow;block=end]
			[onshow;block=begin;when [view.is_already_validate]!='1']
				<td align="center" width="20%">[TInventory.action;strconv=no;]</td>
			[onshow;block=end]
		</tr>
		<tr>
			<td colspan="4" align="center">[TInventory;block=tr;nodata]Aucun produit disponible</td>
		</tr>
		[onshow;block=begin;when [view.can_validate]=='1']
		<tr style="background-color:#dedede;">
			<th>&nbsp;</th>
			<th align="center">
			    [onshow;block=begin;when [view.mode]=='edit']
			    <input name="modify" type="submit" class="butAction" value="Enregistrer" />
			    [onshow;block=end]
			</th>
			
			<th align="right">[inventory.amount]</th>
			<th>&nbsp;</th>
			<th align="right">[inventory.amount_actual]</th>
			<th colspan="2">&nbsp;</th>
		</tr>
		[onshow;block=end]
	
	</table>
	
	[onshow;block=begin;when [view.is_already_validate]!='1']
		<div class="tabsAction" style="height:30px;">
			[onshow;block=begin;when [view.mode]=='view']
				<a href="[view.url]?id=[inventory.id]&action=printDoc" class="butAction">Imprimer</a>
				<a href="[view.url]?id=[inventory.id]&action=edit" class="butAction">Modifier</a>
				[onshow;block=begin;when [view.can_validate]=='1']
					<a href="javascript:;" onclick="javascript:if (!confirm('Confirmez-vous la régulation ?')) return false; else document.location.href='[view.url]?id=[inventory.id]&action=regulate&token=[view.token]'; " class="butAction">Réguler le stock</a>
				[onshow;block=end]
			[onshow;block=end]
			[onshow;block=begin;when [view.mode]=='edit']
				<input name="back" type="button" class="butAction" value="Retour" onclick="document.location='?id=[inventory.id]&action=view';" />
				
			[onshow;block=end]
			[onshow;block=begin;when [view.can_validate]=='1']
                <a onclick="if (!confirm('Confirmez-vous la vidange ?')) return false;" href="[view.url]?id=[inventory.id]&action=flush" class="butActionDelete">Vider</a>
                &nbsp;&nbsp;&nbsp;
                <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="[view.url]?id=[inventory.id]&action=delete" class="butActionDelete">Supprimer</a>
                
			[onshow;block=end]
		</div>
	[onshow;block=end]
	[onshow;block=begin;when [view.is_already_validate]=='1']
		<div class="tabsAction">
			[onshow;block=begin;when [view.can_validate]=='1']
				<a href="[view.url]?id=[inventory.id]&action=printDoc" class="butAction">Imprimer</a>
				<!-- <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="[view.url]?id=[inventory.id]&action=delete" class="butActionDelete">Supprimer</a> -->
				<a href="#" title="Cet inventaire est validé" class="butActionRefused">Supprimer</a>
			[onshow;block=end]
		</div>
	[onshow;block=end]
</form>
<p>Date de création : [inventory.date_cre]<br />Dernière mise à jour : [inventory.date_maj]</p>
	