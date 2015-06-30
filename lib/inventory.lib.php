<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/inventory.lib.php
 *	\ingroup	inventory
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function inventoryAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("inventory@inventory");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/inventory/admin/inventory_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/inventory/admin/inventory_about.php", 2);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@inventory:/inventory/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@inventory:/inventory/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'inventory');

    return $head;
}

function inventoryPrepareHead(&$inventory, $title='Inventaire', $get='')
{
	return array(
		array(dol_buildpath('/inventory/inventory.php?id='.$inventory->getId().$get, 2), $title,'inventaire')
	);
}



function inventorySelectProducts(&$PDOdb, &$inventory)
{
	global $conf;
	
	$except_product_id = array();
	
	foreach ($inventory->TInventorydet as $TInventorydet)
	{
		$except_product_id[] = $TInventorydet->fk_product;
	}
	
	$sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'product WHERE fk_product_type = 0 AND rowid NOT IN ('.implode(',', $except_product_id).') ORDER BY ref, label';
	$PDOdb->Execute($sql);
	
	if (! empty($conf->use_javascript_ajax) && ! empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT))
	{
		$urloption='htmlname=fk_product&outjson=1&price_level=0&type=0&mode=1&status=1&finished=2';
		
		print ajaxAutocompleter('', 'fk_product', DOL_URL_ROOT.'/product/ajax/products.php', $urloption, $conf->global->PRODUIT_USE_SEARCH_TO_SELECT, 0, array());
		
		$select_html = '<input type="text" size="20" name="fk_product" id="search_fk_product" value="" />';
	}
	else 
	{
		$select_html = '<select style="min-width:150px;background:#FFF;font-size:12px;" name="fk_product">';
		while ($PDOdb->Get_line()) 
		{
			$select_html.= '<option value="'.$PDOdb->Get_field('rowid').'">'.$PDOdb->Get_field('ref').' - '.$PDOdb->Get_field('label').'</option>';
		}
		$select_html.= '</select>';
	}

	return $select_html;
}

function ajaxAutocompleter($selected, $htmlname, $url, $urloption='', $minLength=2, $autoselect=0, $ajaxoptions=array())
{
    if (empty($minLength)) $minLength=1;

	$script = '<input type="hidden" name="'.$htmlname.'" id="'.$htmlname.'" value="'.$selected.'" />';

	$script.= '<script type="text/javascript">';
	$script.= '$(document).ready(function() {
					var autoselect = '.$autoselect.';
					var options = '.json_encode($ajaxoptions).';

					// Remove product id before select another product
					// use keyup instead change to avoid loosing the product id
					$("input#search_'.$htmlname.'").keydown(function() {
						//console.log(\'purge_id_after_keydown\');
						$("#'.$htmlname.'").val("");
					});
					$("input#search_'.$htmlname.'").change(function() {
						//console.log(\'change\');
						$("#'.$htmlname.'").trigger("change");
					});
					// Check when keyup
					$("input#search_'.$htmlname.'").keyup(function() {
							//console.log(\'keyup\');
						    if ($(this).val().length == 0)
						    {
	                            $("#search_'.$htmlname.'").val("");
	                            $("#'.$htmlname.'").val("").trigger("change");
	                            if (options.option_disabled) {
	    							$("#" + options.option_disabled).removeAttr("disabled");
	    						}
	    						if (options.disabled) {
	    							$.each(options.disabled, function(key, value) {
	    								$("#" + value).removeAttr("disabled");
									});
	    						}
	    						if (options.update) {
	    							$.each(options.update, function(key, value) {
	    								$("#" + key).val("").trigger("change");
									});
								}
								if (options.show) {
	    							$.each(options.show, function(key, value) {
	    								$("#" + value).hide().trigger("hide");
									});
								}
								if (options.update_textarea) {
	    							$.each(options.update_textarea, function(key, value) {
	    								if (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && CKEDITOR.instances[key] != "undefined") {
	    									CKEDITOR.instances[key].setData("");
	    								} else {
	    									$("#" + key).html("");
										}
	    							});
	    						}
						    }
                    });
    				$("input#search_'.$htmlname.'").autocomplete({
    					source: function( request, response ) {
    						$.get("'.$url.($urloption?'?'.$urloption:'').'", { '.$htmlname.': request.term }, function(data){
								response($.map( data, function( item ) {
									if (autoselect == 1 && data.length == 1) {
										$("#search_'.$htmlname.'").val(item.value);
										$("#'.$htmlname.'").val(item.key).trigger("change");
									}
									var label = item.label.toString();
									var update = {};
									if (options.update) {
										$.each(options.update, function(key, value) {
											update[key] = item[value];
										});
									}
									var textarea = {};
									if (options.update_textarea) {
										$.each(options.update_textarea, function(key, value) {
											textarea[key] = item[value];
										});
									}
									return { label: label, value: item.value, id: item.key, update: update, textarea: textarea, disabled: item.disabled }
								}));
							}, "json");
						},
						dataType: "json",
    					minLength: '.$minLength.',
    					select: function( event, ui ) {		// Function ran when new value is selected into javascript combo
							//console.log(\'set value of id with \'+ui.item.id);
    						$("#'.$htmlname.'").val(ui.item.id).trigger("change");	// Select new value
    						// Disable an element
    						if (options.option_disabled) {
    							if (ui.item.disabled) {
    								$("#" + options.option_disabled).attr("disabled", "disabled");
    								if (options.error) {
    									$.jnotify(options.error, "error", true);		// Output with jnotify the error message
    								}
    								if (options.warning) {
    									$.jnotify(options.warning, "warning", false);		// Output with jnotify the warning message
    								}
							} else {
    								$("#" + options.option_disabled).removeAttr("disabled");
    							}
    						}
    						if (options.disabled) {
    							$.each(options.disabled, function(key, value) {
    								$("#" + value).attr("disabled", "disabled");
    							});
    						}
    						if (options.show) {
    							$.each(options.show, function(key, value) {
    								$("#" + value).show().trigger("show");
    							});
    						}
    						// Update an input
    						if (ui.item.update) {
    							// loop on each "update" fields
    							$.each(ui.item.update, function(key, value) {
    								$("#" + key).val(value).trigger("change");
    							});
    						}
    						if (ui.item.textarea) {
    							$.each(ui.item.textarea, function(key, value) {
    								if (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && CKEDITOR.instances[key] != "undefined") {
    									CKEDITOR.instances[key].setData(value);
    									CKEDITOR.instances[key].focus();
    								} else {
    									$("#" + key).html(value);
    									$("#" + key).focus();
									}
    							});
    						}
    						$("#search_'.$htmlname.'").trigger("change");	// To tell that input text field was modified
    					}
					}).data( "autocomplete" )._renderItem = function( ul, item ) {
						return $( "<li></li>" )
						.data( "item.autocomplete", item )
						.append( \'<a><span class="tag">\' + item.label + "</span></a>" )
						.appendTo(ul);
					};

  				});';
	$script.= '</script>';

	return $script;
}
