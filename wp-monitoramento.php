<?php
/*
Plugin Name: WP-Monitoramento
Plugin URI: http://github.com/gabinetedigital/wp-minitoramento
Description: Interface para administração dos dados de monitoramento de projetos do governo do estado do RS.
Version: 0.1
Author: Sérgio Berlotto <sergio.berlotto@gmail.com>
Author URI: http://pythonrs.org
License: GPLv3

*/
/*  Copyright 2012

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function create_post_type_monitoramento() {
	register_post_type( 'gdobra',
		array(
			'labels' => array(
				'name' => __( 'Obras' ),
				'singular_name' => __( 'Obra' ),
				'menu_name' => __('Obras'),
				'add_new' => __('Nova obra'),
				'add_new_item' => __('Adicionar obra'),
				'edit_item' => __('Editar obra'),
				'new_item' => __('Nova obra'),
				'view_item' => __('Ver obra'),
				'items_archive' => __('Arquivo de obras'),
				'search_items' => __('Procurar obras'),
				'not_found' => __('Nenhuma obra...')
			),
		'hierarchical' => true,
		'menu_icon' => plugins_url( 'img/icone.png' , __FILE__ ),
		'public' => true,
		'has_archive' => true,
		'rewrite' => array('with_front' => false, 'slug' => 'monitore/obra'),
		'description' => 'Obras do governo que serão monitoradas pelo cidadão',
		'supports' => array('title','editor','author','thumbnail','thumbnail','excerpt',
			                'trackbacks','custom-fields','comments','revisions','page-attributes',
			                'post-formats')
		)
	);
	add_theme_support( 'post-formats', array( 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio' ) );
	register_taxonomy_for_object_type( 'category', 'gdobra' );
}

add_action( 'init', 'create_post_type_monitoramento' );

# ---- metaboxes para dados adicionais ----

global $meta_boxes_obras;

$gdobras_prefix = 'gdobra_';

$meta_boxes_obras = array();

# O campo "nohistory" não é utilizado pelo 'RW_Meta_Box'. É somente para dizer que este campo não
# vai ser guardado no revision do WP.
$gd_obras_custom_fields = array(
		array(	'name'		=> 'Descritivo Livre da Obra',
				'id'		=> $gdobras_prefix . 'descritivo_livre',
				'desc'		=> '',
				'type'		=> 'wysiwyg'	),
		array(	'name'		=> '% Execução da Obra',
				'id'		=> $gdobras_prefix . 'porc_concluido',
				'desc'		=> 'Porcentagem da obra fisica concluida',
				'type'		=> 'text'	),
		array(	'name'		=> 'Início Efetivo',
				'id'		=> $gdobras_prefix . 'incio_efetivo',
				'desc'		=> '',
				'type'		=> 'date'	),
		array(	'name'		=> 'Fim Previsto',
				'id'		=> $gdobras_prefix . 'fim_previsto',
				'desc'		=> '',
				'type'		=> 'date'	),
		array(	'name'		=> 'Valor global da obra (R$)',
				'id'		=> $gdobras_prefix . 'valor_global',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Empresa Contratada',
				'id'		=> $gdobras_prefix . 'empresa_contratada',
				'clone'     => true,
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Objetivo Estratégico',
				'id'		=> $gdobras_prefix . 'objetivo_estrategico',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Nome do Projeto',
				'id'		=> $gdobras_prefix . 'projeto',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Tema',
				'id'		=> $gdobras_prefix . 'tema',
				'desc'		=> '',
				'type'		=> 'select',
				'options'   => array('saude' => "Saúde", 'seguranca' => "Segurança", 'transito' => "Trânsito")	),
		array(	'name'		=> 'Url Stream',
				'id'		=> $gdobras_prefix . 'stream',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Coordenadas da Obra',
				'id'		=> $gdobras_prefix . 'coordenadas',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Município',
				'id'		=> $gdobras_prefix . 'municipio',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Evidencias',
				'id'		=> $gdobras_prefix . 'evidencia',
				'desc'		=> '',
				'type'		=> 'file',
				'nohistory'	=> true ),
	);

$meta_boxes_obras[] = array(
		'id' => $gdobras_prefix.'configuracao-obra',
		'title' => 'Detalhes das Obras',
		'pages' => array('gdobra'),
		'context'=> 'normal',
		'priority'=> 'high',
		'fields' => $gd_obras_custom_fields
);

function wp_obras_register_meta_boxes()
{
	global $meta_boxes_obras;

	if ( class_exists( 'RW_Meta_Box' ) )
	{
		foreach ( $meta_boxes_obras as $meta_box )
		{
			new RW_Meta_Box( $meta_box );
		}
	}
}

add_action('admin_init', 'wp_obras_register_meta_boxes' );

# ------------------------------------------------------------------
# Métodos utilizados para salvar as revisões dos dados nas revisões dos posts. \/ \/ \/

function gdobras_save_post( $post_id, $post ) {

	$parent_id = wp_is_post_revision( $post_id );

	global $gd_obras_custom_fields;
	if ( $parent_id ) {
		$parent  = get_post( $parent_id );

		foreach ($gd_obras_custom_fields as $cf) {
			if(!array_key_exists( 'nohistory', $cf )){
				$meta_name = $cf['id'];
				$my_meta = get_post_meta( $parent->ID, $meta_name, true );
				error_log("SALVANDO ".$meta_name." VLR:".$my_meta);
				if ( $my_meta != Null )
					add_metadata( 'post', $post_id, $meta_name, $my_meta );
			}
		}

	}
}
add_action( 'save_post', 'gdobras_save_post' );

# -----

function gdobras_restore_revision( $post_id, $revision_id ) {

	$post     = get_post( $post_id );
	$revision = get_post( $revision_id );


	foreach ($gd_obras_custom_fields as $cf) {
		if(!array_key_exists( 'nohistory', $cf )){
			$meta_name = $cf['id'];
			$my_meta  = get_metadata( 'post', $revision->ID, $meta_name, true );
			error_log("RESTORING:".$cf['id']."=".$my_meta);
			if ( $my_meta != Null )
				update_post_meta( $post_id, $meta_name, $my_meta );
			else
				delete_post_meta( $post_id, $meta_name );
		}
	}

}
add_action( 'wp_restore_post_revision', 'gdobras_restore_revision', 10, 2 );

# -----

function gdobras_revision_fields( $fields ) {

	global $gdobras_prefix;
	global $gd_obras_custom_fields;

	foreach ($gd_obras_custom_fields as $cf) {
		if(!array_key_exists( 'nohistory', $cf )){
			error_log("VIEWING:".$cf['id']);
			$fields[$cf['id']] = $cf['name'];
		}
	}

	// $fields[$gdobras_prefix . 'descritivo_livre'] = 'Descritivo Livre';
	return $fields;

}
add_filter( '_wp_post_revision_fields', 'gdobras_revision_fields' );

function gdobras_revision_field( $value, $field ) {

	global $revision;
	error_log("GETTING:".$field);
	return get_metadata( 'post', $revision->ID, $field, true );

}
add_filter( '_wp_post_revision_field_my_meta', 'gdobras_revision_field', 10, 2 );

# Métodos utilizados para salvar as revisões dos dados nas revisões dos posts. /\ /\ /\

?>
