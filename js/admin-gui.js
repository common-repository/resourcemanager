"use strict";

jQuery(document).ready(function( $ ) {
	if($('#resourcemanager-resource-edit').length){
		RESOURCEMANAGER.admin_resources.init();
	}
	
	if($('#resourcemanager-views-edit').length){
		RESOURCEMANAGER.admin_views.init();
	}
	if($('#resourcemanager-settings').length){
		RESOURCEMANAGER.admin_settings.init();
	}
});


var RESOURCEMANAGER = RESOURCEMANAGER || {};

RESOURCEMANAGER.admin_settings = {
	init: function(){
		jQuery('#resourcemanager-settings form table td select').select2({
			width: '100%',
		});
	}
}

RESOURCEMANAGER.admin_views = {
	views : {},
	resources : {},
	roles : {},
	active_modal_element_id : null,
	
	init: function(){
		this.fetch_view_data( );
		
		jQuery( "#resourcemanager-dialog-confirm" ).dialog({
			resizable: false,
			height: "auto",
			width: 400,
			modal: true,
			buttons: {
				[__( 'OK', RESOURCEMANAGER.config.plugin_name )]: function() {
					RESOURCEMANAGER.admin_views.delete_resource_after_confirm();
					jQuery( this ).dialog( "close" );
				},
				[__( 'Cancel', RESOURCEMANAGER.config.plugin_name )]: function() {
					jQuery( this ).dialog( "close" );
				}
			}
		});
		jQuery( "#resourcemanager-dialog-confirm" ).dialog( "close" );
	},
	
	fetch_view_data: async function(){
		var data = {
			'action': 'resman_load_views'
		};
		//jQuery.post(ajaxurl, data, this.display_data);
		let response = await jQuery.post(ajaxurl, data).promise();
		this.display_data(response);
	},
	
	set_data(response){
		let decoded_response = JSON.parse(response);
		this.views = decoded_response['views'];
		this.resources = decoded_response['resources'];
		this.roles = decoded_response['roles'];
	},
	
	add_select_resources: function(html_obj, selected = []){
		html_obj.find('.editable.resources_arr').append("<select class='multiple-combo-box' multiple='multiple' data-initials='" + selected.join(',') + "'></select>");
		let select2_obj = html_obj.find('.resources_arr select');
			
		let resources = RESOURCEMANAGER.admin_views.resources;
		let html = "<select class='multiple-combo-box' multiple='multiple'></select>";
		
		select2_obj.on("select2:select", RESOURCEMANAGER.admin_views.save_field_and_close);
		select2_obj.on("select2:unselect", RESOURCEMANAGER.admin_views.save_field_and_close);
		
		for(let res_id in resources){
			let newOption = new Option(resources[res_id]['name'], res_id, false, false);
			select2_obj.append(newOption);
		}
		select2_obj.val(selected);
		
		select2_obj.select2Sortable({
			width: '100%',
		});
	},
	
	html_select_roles: function(selected = ''){
		let roles = RESOURCEMANAGER.admin_views.roles;
		let html = "<select><option></option>";
		
		for(let role_key in roles){
			html += "<option value='" + role_key + "'";
			if(role_key == selected){html += " selected";}
			html += ">" + roles[role_key] + "</option>";
		}
		html += "</select>";
		
		return html;
	},
	
	display_data: function(response){
		//console.log(response);
		RESOURCEMANAGER.admin_views.set_data(response);
		jQuery('#resourcemanager-views-edit tbody').html("");
		
		let views = RESOURCEMANAGER.admin_views.views;
			
		//list existing views
		for(let key in views){
			let view = views[key];
			
			let html = "<tr";
					html += view['admin'] ? " class='admin'" : "";
				html += ">";
				html += "<td class='title' data-id='" + key + "' data-field='title'>" + view['title'] + "</td>";
				html += "<td class='resources_arr";
					html += view['admin'] ? " editable" : "";
				html += "' data-id='" + key + "' data-field='resources_arr'></td>";
				html += "<td class='ownerRole' data-id='" + key + "' data-field='ownerRole'>";
					html += view['admin'] ? RESOURCEMANAGER.admin_views.html_select_roles(view['ownerRole']) : view['ownerRole'];
				html += "</td>";
				html += "<td class='author'>" + view['editUserLink'] + "</td>";
				html += "<td class='lastChangedI18n'>" + view['lastChanged'] + "</td>";
				html += "<td class='delete' data-id='" + key + "'>";
					html += view['admin'] ? "<a data-id='" + key + "' href='#'>[X]</a>" : "";
				html += "</td>";
				html += "<td class='shortcode'>[resourcemanager cal_id=" + key + "]</td>";
			html += "</tr>";
			let html_obj = jQuery(html);
			
			
			jQuery('#resourcemanager-views-edit tbody').append(html_obj);
			RESOURCEMANAGER.admin_views.add_select_resources(html_obj,view['content']);
		}
		
		//New view
		let html = "<tr class='new'>";
			html += "<td class='title' data-id='0' data-field='title'>[" + __( 'New entry', RESOURCEMANAGER.config.plugin_name ) + "]</td>";
			html += "<td class='resources_arr'></td>";
			html += "<td class='ownerRole'></td>";
			html += "<td class='author'></td>";
			html += "<td class='lastChangedI18n'></td>";
			html += "<td class='delete'></td>";
			html += "<td class='shortcode'></td>";
		html += "</tr>";
		jQuery('#resourcemanager-views-edit tbody').append(html);
		
		
		
		jQuery('#resourcemanager-views-edit .new .title').attr('contenteditable','true');
		jQuery('#resourcemanager-views-edit .new .title').focusout(RESOURCEMANAGER.admin_views.save_field_and_close);
		
		jQuery('#resourcemanager-views-edit .admin .ownerRole > select').focusout(RESOURCEMANAGER.admin_views.save_field_and_close);
		
		jQuery('#resourcemanager-views-edit .admin .delete > a').click(RESOURCEMANAGER.admin_views.delete_resource);
	},
	
	delete_resource: function(){
		RESOURCEMANAGER.admin_views.active_modal_element_id = jQuery(this).attr('data-id');
		
		jQuery( "#resourcemanager-dialog-confirm" ).dialog( "open" );
		
		return false;
	},
	
	delete_resource_after_confirm: async function(){
		let res_id = RESOURCEMANAGER.admin_views.active_modal_element_id;

		let data = {
			'action':			'resman_delete_view',
			'id':				res_id,
		};
		
		//jQuery.post(ajaxurl, data, RESOURCEMANAGER.admin_views.display_data);
		let response = await jQuery.post(ajaxurl, data).promise();
		RESOURCEMANAGER.admin_views.display_data(response);
		
	},
	
	get_field: function(e){
		
		if(jQuery(e).prop("tagName") == "TD"){
			return {
				value: jQuery(e).text(),
				name: jQuery(e).attr('data-field'),
				res_id: jQuery(e).attr('data-id'),
			}
		}
		else if(jQuery(e).prop("tagName") == "SELECT"){
			return {
				value: jQuery(e).val(),
				name: jQuery(e).parent().attr('data-field'),
				res_id: jQuery(e).parent().attr('data-id'),
			}
		}
	},
	
	save_field_and_close: async function(){
		
		let e = RESOURCEMANAGER.admin_views.get_field(this);
		
		var field_content = e.value;
		var field_name = e.name;
		var res_id = e.res_id;
		
		if(RESOURCEMANAGER.admin_views.edit_previous_content != field_content){
			
			//jQuery.toast({
			//  text : "Gespeichert",
			//  hideAfter : false
			//});
			
			//Speichern
			var data = {
				'action':			'resman_save_view_attr',
				'id':				res_id,
				'field_name':		field_name,
				'field_content':	field_content,
			};
			//console.log(field_content);
			
			//jQuery.post(ajaxurl, data, RESOURCEMANAGER.admin_views.display_data);
			let response = await jQuery.post(ajaxurl, data).promise();
			RESOURCEMANAGER.admin_views.display_data(response);
		}
	}
}


RESOURCEMANAGER.editor_settings = {
    tinymce: {
        wpautop  : true,
        theme    : 'modern',
        skin     : 'lightgray',
        language : 'de',
        formats  : {
            alignleft  : [
                { selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li', styles: { textAlign: 'left' } },
                { selector: 'img,table,dl.wp-caption', classes: 'alignleft' }
            ],
            aligncenter: [
                { selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li', styles: { textAlign: 'center' } },
                { selector: 'img,table,dl.wp-caption', classes: 'aligncenter' }
            ],
            alignright : [
                { selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li', styles: { textAlign: 'right' } },
                { selector: 'img,table,dl.wp-caption', classes: 'alignright' }
            ],
            strikethrough: { inline: 'del' }
        },
        relative_urls       : true,
        remove_script_host  : false,
        convert_urls        : false,
        browser_spellcheck  : true,
        fix_list_elements   : true,
        entities            : '38,amp,60,lt,62,gt',
        entity_encoding     : 'raw',
        keep_styles         : false,
        paste_webkit_styles : 'font-weight font-style color',
        preview_styles      : 'font-family font-size font-weight font-style text-decoration text-transform',
        tabfocus_elements   : ':prev,:next',
        plugins    : 'charmap,hr,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpeditimage,wpgallery,wplink,wpdialogs,wpview',
        resize     : 'vertical',
        menubar    : false,
        indent     : false,
        toolbar1   : 'save_and_close,wp_more,spellchecker,wp_adv',
        toolbar2   : 'bold,italic,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink',
        toolbar3   : 'formatselect,underline,alignjustify,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
        toolbar4   : '',
        body_class : 'id post-type-post post-status-publish post-format-standard',
        wpeditimage_disable_captions: false,
        wpeditimage_html5_captions  : true,
		
		setup: function(editor) {
			editor.addButton('save_and_close', {
				icon: 'save',
				tooltip: "Save and close",
				onclick: function(){
					wp.editor.remove(RESOURCEMANAGER.editor_settings.element_id);
					RESOURCEMANAGER.admin_resources.save_field_and_close.apply(document.getElementById(RESOURCEMANAGER.editor_settings.element_id));
				}
			});
		}

    },
    quicktags   : true,
    mediaButtons: true
}

RESOURCEMANAGER.admin_resources = {
	edit_previous_content : "",
	plugin_name : "ha-resourcemanager",
	resources : {},
	roles : {},
	active_modal_element_id : null,
	
	init: function(){
		this.fetch_resource_data( );
		
		jQuery( "#resourcemanager-dialog-confirm" ).dialog({
			resizable: false,
			height: "auto",
			width: 400,
			modal: true,
			buttons: {
				[__( 'OK', RESOURCEMANAGER.config.plugin_name )]: function() {
					RESOURCEMANAGER.admin_resources.delete_resource_after_confirm();
					jQuery( this ).dialog( "close" );
				},
				[__( 'Cancel', RESOURCEMANAGER.config.plugin_name )]: function() {
					jQuery( this ).dialog( "close" );
				}
			}
		});
		jQuery( "#resourcemanager-dialog-confirm" ).dialog( "close" );
	},
	
	fetch_resource_data: async function(){
		var data = {
			'action': 'resman_load_admin_data'
		};
		//jQuery.post(ajaxurl, data, this.display_data);
		let response = await jQuery.post(ajaxurl, data).promise();
		
		this.display_data(response);
	},
	
	set_data(response){
		//console.log(response);
		let decoded_response = JSON.parse(response);
		this.resources = decoded_response['resources'];
		this.roles = decoded_response['roles'];
	},
	
	html_select_roles: function(selected = ''){
		let roles = RESOURCEMANAGER.admin_resources.roles;
		let html = "<select><option></option>";
		
		for(let role_key in roles){
			html += "<option value='" + role_key + "'";
			if(role_key == selected){html += " selected";}
			html += ">" + roles[role_key] + "</option>";
		}
		html += "</select>";
		
		return html;
	},
	
	display_data: function(response) {
		//console.log('Display');
		
		RESOURCEMANAGER.admin_resources.set_data(response);
		
		let resources = RESOURCEMANAGER.admin_resources.resources;
				
		//list existing resources
		let html = "";
		for(let key in resources){
			let _class = resources[key]['admin'] ? ' class=admin' : '';
			
			html += "<tr" + _class + ">";
				html += "<td class='name' data-id='" + key + "' data-field='name'>" 						+ resources[key]['name'] 				+ "</td>";
				html += "<td class='descripRes' data-id='" + key + "' data-field='descripRes' id='descripRes-" + key + "'>"
					html += resources[key]['descripRes']			+ "</td>";
				html += "<td class='countable' data-id='" + key + "' data-field='countable'>"				+ resources[key]['countable']			+ "</td>";
				html += "<td class='customFields' data-id='" + key + "' data-field='customFields'>"			+ resources[key]['customFields']		+ "</td>";
				html += "<td class='ownerRole' data-id='" + key + "' data-field='ownerRole'>";
					html += resources[key]['admin'] ? RESOURCEMANAGER.admin_resources.html_select_roles(resources[key]['ownerRole']) : resources[key]['ownerRole'];
				html += "</td>";
				html += "<td class='editUserLink'>"			+ resources[key]['editUserLink']		+ "</td>";
				html += "<td class='lastChangedI18n'>"	+ resources[key]['lastChangedI18n']	+ "</td>";
				html += "<td class='delete'><a data-id='" + key + "' href='#'>[X]</a></td>";
			html += "</tr>";
		}
		
		//New Resource
		html += "<tr class='new'>";
			html += "<td class='name' data-id='0' data-field='name'>[" + __( 'New entry', RESOURCEMANAGER.config.plugin_name ) + "]</td>";
			html += "<td class='descripRes'></td>";
			html += "<td class='countable'></td>";
			html += "<td class='customFields'></td>";
			html += "<td class='ownerRole'></td>";
			html += "<td class='editUserLink'></td>";
			html += "<td class='lastChangedI18n'></td>";
			html += "<td class='delete'></td>";
		html += "</tr>";
		
		
		jQuery('#resourcemanager-resource-edit tbody').html(html);
		
		jQuery('#resourcemanager-resource-edit .admin .name').click(RESOURCEMANAGER.admin_resources.save_previous_content);
		jQuery('#resourcemanager-resource-edit .admin .name').attr('contenteditable','true');
		jQuery('#resourcemanager-resource-edit .admin .name').focusout(RESOURCEMANAGER.admin_resources.save_field_and_close);
		
		jQuery('#resourcemanager-resource-edit .admin .descripRes').click(RESOURCEMANAGER.admin_resources.init_editor);
		
		jQuery('#resourcemanager-resource-edit .new .name').click(RESOURCEMANAGER.admin_resources.save_previous_content);
		jQuery('#resourcemanager-resource-edit .new .name').attr('contenteditable','true');
		jQuery('#resourcemanager-resource-edit .new .name').focusout(RESOURCEMANAGER.admin_resources.save_field_and_close);
		
		jQuery('#resourcemanager-resource-edit .admin .customFields').click(RESOURCEMANAGER.admin_resources.save_previous_content);
		jQuery('#resourcemanager-resource-edit .admin .customFields').attr('contenteditable','true');
		jQuery('#resourcemanager-resource-edit .admin .customFields').focusout(RESOURCEMANAGER.admin_resources.save_field_and_close);
		
		jQuery('#resourcemanager-resource-edit .admin .countable').click(RESOURCEMANAGER.admin_resources.save_previous_content);
		jQuery('#resourcemanager-resource-edit .admin .countable').attr('contenteditable','true');
		jQuery('#resourcemanager-resource-edit .admin .countable').focusout(RESOURCEMANAGER.admin_resources.save_field_and_close);
		
		jQuery('#resourcemanager-resource-edit .admin .ownerRole select').click(RESOURCEMANAGER.admin_resources.save_previous_content);
		jQuery('#resourcemanager-resource-edit .admin .ownerRole select').select2({
			width: '100%',
		});
		jQuery('#resourcemanager-resource-edit .admin .ownerRole select').change(RESOURCEMANAGER.admin_resources.save_field_and_close);
		
		jQuery('#resourcemanager-resource-edit .admin .delete > a').click(RESOURCEMANAGER.admin_resources.delete_resource);
	},
	
	init_editor: function(){
		wp.editor.remove(RESOURCEMANAGER.editor_settings.element_id);
		
		RESOURCEMANAGER.editor_settings.element_id = jQuery(this).attr('id');
		RESOURCEMANAGER.admin_resources.edit_previous_content = RESOURCEMANAGER.admin_resources.get_field(document.getElementById(RESOURCEMANAGER.editor_settings.element_id)).value
		
		wp.editor.initialize(RESOURCEMANAGER.editor_settings.element_id, RESOURCEMANAGER.editor_settings, true);
	},
	
	save_previous_content: function(){
		RESOURCEMANAGER.admin_resources.edit_previous_content = RESOURCEMANAGER.admin_resources.get_field(this).value;
	},
	
	get_field: function(e){
		if(jQuery(e).prop("tagName") == "TD" && jQuery(e).hasClass('descripRes')){
			return {
				value: jQuery(e).html(),
				name: jQuery(e).attr('data-field'),
				res_id: jQuery(e).attr('data-id'),
			}
		}
		else if(jQuery(e).prop("tagName") == "TD"){
			return {
				value: jQuery(e).text(),
				name: jQuery(e).attr('data-field'),
				res_id: jQuery(e).attr('data-id'),
			}
		}
		else if(jQuery(e).prop("tagName") == "SELECT"){
			return {
				value: jQuery(e).val(),
				name: jQuery(e).parent().attr('data-field'),
				res_id: jQuery(e).parent().attr('data-id'),
			}
		}
	},
	
	delete_resource: function(){
		RESOURCEMANAGER.admin_resources.active_modal_element_id = jQuery(this).attr('data-id');
		
		jQuery( "#resourcemanager-dialog-confirm" ).dialog( "open" );
		
		return false;
	},
	
	delete_resource_after_confirm: async function(){
		let res_id = RESOURCEMANAGER.admin_resources.active_modal_element_id;

		let data = {
			'action':			'resman_delete_resource',
			'id':				res_id,
		};
		
		//jQuery.post(ajaxurl, data, RESOURCEMANAGER.admin_resources.display_data);
		let response = await jQuery.post(ajaxurl, data).promise();
		RESOURCEMANAGER.admin_resources.display_data(response);
		
	},
	
	save_field_and_close: async function(){
		
		var field_content = RESOURCEMANAGER.admin_resources.get_field(this).value;
		var field_name = RESOURCEMANAGER.admin_resources.get_field(this).name;
		var res_id = RESOURCEMANAGER.admin_resources.get_field(this).res_id;
		
		if(RESOURCEMANAGER.admin_resources.edit_previous_content != field_content){
			
			//console.log("Speichern");
			jQuery.toast({
			  text : __( 'Saved', RESOURCEMANAGER.config.plugin_name )
			});
			
			//Speichern
			var data = {
				'action':			'resman_save_resource_attr',
				'id':				res_id,
				'field_name':		field_name,
				'field_content':	field_content,
			};
			
			//jQuery.post(ajaxurl, data, RESOURCEMANAGER.admin_resources.display_data);
			
			let response = await jQuery.post(ajaxurl, data).promise();
			RESOURCEMANAGER.admin_resources.display_data(response);
			
		}
	}
}
