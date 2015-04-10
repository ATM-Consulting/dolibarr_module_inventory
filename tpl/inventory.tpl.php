<form action="[view.url]" method="POST">
	[onshow;block=begin;when [view.is_already_validate]=='1']
		<div class="warning">Cet inventaire est validé</div>
	[onshow;block=end]
	
	<input type="hidden" name="action" value="save" />
	<input type="hidden" name="id" value="[inventory.id]" />
	
	<p>Date de création : [inventory.date_cre]<br />Dernière mise à jour : [inventory.date_maj]</p>
	
	<table width="100%" class="border workstation">
		<tr style="background-color:#dedede;">
			<th align="left" width="25%">&nbsp;&nbsp;Produit</th>
			<th align="center" width="25%">Quantité vue</th>
			[onshow;block=begin;when [view.can_validate]=='1']
				<th align="center" width="25%">Quantité stock</th>
				<th align="center" width="20%">Quantité régulée</th>
			[onshow;block=end]
			[onshow;block=begin;when [view.is_already_validate]!='1']
				<th align="center" width="5%">Action</th>
			[onshow;block=end]
		</tr>
		<tr id="WS[workstation.id]" style="background-color:#fff;">
			<td align="left">&nbsp;&nbsp;[TInventory.produit;strconv=no;block=tr]</td>
			<td align="center">[TInventory.qty;strconv=no;block=tr]&nbsp;&nbsp;[TInventory.qty_view;strconv=no;block=tr]</td>
			[onshow;block=begin;when [view.can_validate]=='1']
				<td align="center">[TInventory.qty_stock;strconv=no;block=tr]</td>
				<td align="center">[TInventory.qty_regulated;strconv=no;block=tr]</td>
			[onshow;block=end]
			[onshow;block=begin;when [view.is_already_validate]!='1']
				<td align="center" width="20%">[TInventory.action;strconv=no;block=tr]</td>
			[onshow;block=end]
		</tr>
		<tr>
			<td colspan="4" align="center">[TInventory;block=tr;nodata]Aucun produit disponible</td>
		</tr>
	</table>
	
	[onshow;block=begin;when [view.is_already_validate]!='1']
		<div class="tabsAction" style="height:30px;">
			[onshow;block=begin;when [view.mode]=='view']
				<a href="[view.url]?id=[inventory.id]&action=printDoc" class="butAction">Imprimer</a>
				<a href="[view.url]?id=[inventory.id]&action=edit" class="butAction">Modifier</a>
				[onshow;block=begin;when [view.can_validate]=='1']
					<a href="[view.url]?id=[inventory.id]&action=regulate" onclick="if (!confirm('Confirmez-vous la régulation ?')) return false;" class="butAction">Réguler le stock</a>
				[onshow;block=end]
			[onshow;block=end]
			[onshow;block=begin;when [view.mode]=='edit']
				<input style="float:left;margin-left:35%" name="modify" type="submit" class="butAction" value="Enregistrer" />
			[onshow;block=end]
			[onshow;block=begin;when [view.can_validate]=='1']
				<a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="[view.url]?id=[inventory.id]&action=delete" class="butActionDelete">Supprimer</a>
			[onshow;block=end]
		</div>
	[onshow;block=end]
	[onshow;block=begin;when [view.is_already_validate]=='1']
		<div class="tabsAction">
			[onshow;block=begin;when [view.can_validate]=='1']
				<!-- <a onclick="if (!confirm('Confirmez-vous la suppression ?')) return false;" href="[view.url]?id=[inventory.id]&action=delete" class="butActionDelete">Supprimer</a> -->
				<a href="#" title="Cet inventaire est validé" class="butActionRefused">Supprimer</a>
			[onshow;block=end]
		</div>
	[onshow;block=end]
</form>

[onshow;block=begin;when [view.is_already_validate]!='1']
	<hr />
	<h3>Ajouter un produit dans l'entrepôt</h3>
	<form action="[view.url]" method="POST">
		<input type="hidden" name="action" value="add_line" />
		<input type="hidden" name="id" value="[inventory.id]" />
		
		[product.list;strconv=no]
		
		<div class="tabsAction" style="text-align:left;">
			<input class="butAction" type="submit" value="Ajouter Produit" />
		</div>
	</form>
[onshow;block=end]