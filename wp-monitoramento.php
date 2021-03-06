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


include("wp-monitoramento.post.php");
include("wp-monitoramento.xmlrpc.php");


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
		'rewrite' => array('with_front' => false, 'slug' => 'deolho/obra'),
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

# ---- adiciona filtro de paginas pai -----

function fws_admin_posts_filter( $query ) {
    global $pagenow;
    global $wpdb;
    if ( is_admin() && $pagenow == 'edit.php' &&
    	 $query->query_vars['post_type'] == 'gdobra' ) {
        if( !empty($_GET['my_parent_pages']) ){
            if( $_GET['my_parent_pages'] == 'only_top'){
            	$query->query_vars['post_parent'] = '0';
            }else{
                $children = get_pages('post_type=gdobra&child_of='.$_GET['my_parent_pages']);
                // error_log("CHILDREN --------------------------- of:".$_GET['my_parent_pages']);
                // error_log( print_r($children, True));
                $postids = array($_GET['my_parent_pages']);
                foreach( $children as $child ){
                    // error_log("CHILD ID:".$child->ID);
                    $postids[count($postids)] = $child->ID;
                }

            	// $query->query_vars['post_parent'] = $_GET['my_parent_pages'];

                $query->query_vars['post__in'] = $postids;
            }
        }else{
            //Default é listar somente as obras
            $query->query_vars['post_parent'] = '0';
        }
    }
    // error_log("----------------------- SQL FILTRADO -----------------------------");
    // error_log( print_r($query, True) );
    // error_log("----------------------- SQL FILTRADO -----------------------------");
}
add_filter( 'parse_query', 'fws_admin_posts_filter' );


add_filter( 'manage_edit-gdobra_columns', 'set_custom_edit_book_columns' );
add_action( 'manage_gdobra_posts_custom_column' , 'custom_book_column', 10, 2 );

function set_custom_edit_book_columns($columns) {
    unset( $columns['author'] );
    $columns['obra_andamento'] = __( 'Andamento', 'your_text_domain' );
    $columns['obra_icon'] = __( 'Ver Filhos', 'your_text_domain' );

    return $columns;
}

function custom_book_column( $column, $post_id ) {

    $parents = get_post_ancestors( $post_id );
    $idparent = ($parents) ? $parents[count($parents)-1]: 0;

    switch ( $column ) {
        case 'obra_andamento' :
            $andamento = get_post_meta( $post_id, "gdobra_porc_concluido", True );
            if ( is_string( $andamento ) && !empty($andamento) )
                echo "$andamento %";
            else
                echo( '-x-' );
            break;

        case 'obra_icon' :
            if( $idparent == 0 ){
                $post_url = admin_url( "edit.php?post_type=gdobra&my_parent_pages=$post_id" ) ;
                echo "<br><a class='button-secondary' href='$post_url'>Ver filhos</a>";
            }else{
                if( count($parents) == 1 ){
                    echo "GOV INFORMA";
                }elseif( count($parents) > 1 ){
                    echo "ITEM";
                }else{
                    echo "-x-";
                }
            }
            break;

    }
}

function admin_page_filter_parentpages() {
    global $wpdb;
    if (isset($_GET['post_type']) && $_GET['post_type'] == 'gdobra') {
		$sql = "SELECT ID, post_title FROM ".$wpdb->posts." WHERE post_type = 'gdobra' AND post_parent = 0 AND post_status not in ('auto-draft') ORDER BY post_title";
		// $sql = "SELECT ID, post_title FROM ".$wpdb->posts." WHERE post_type = 'page' AND post_parent = 0 AND post_status = 'publish' ORDER BY post_title";
		// error_log("QUERY======================");
		// error_log($sql);
		// error_log("QUERY======================");
		$parent_pages = $wpdb->get_results($sql, OBJECT_K);
		$current = isset($_GET['my_parent_pages']) ? $_GET['my_parent_pages'] : '';
		$selpais = "only_top" == $current ? ' selected="selected"' : '';
		$select = '
			<select name="my_parent_pages">
				<option value="">- Lista inicial -</option>
				<option value="only_top" '.$selpais.'>- Ver somente as obras pai -</option>';
		foreach ($parent_pages as $page) {
			$select .= sprintf('
				<option value="%s"%s>%s</option>', $page->ID, $page->ID == $current ? ' selected="selected"' : '', $page->post_title);
		}
		$select .= '
			</select>';
		echo $select;
	} else {
		return;
	}
}
add_action( 'restrict_manage_posts', 'admin_page_filter_parentpages' );

# ---- metaboxes para dados adicionais ----

global $meta_boxes_obras;

$gdobras_prefix = 'gdobra_';

$meta_boxes_obras = array();

# O campo "nohistory" não é utilizado pelo 'RW_Meta_Box'. É somente para dizer que este campo não
# vai ser guardado no revision do WP.
$gd_obras_custom_fields = array(
//		array(	'name'		=> 'Descritivo de Status da Obra',
//				'id'		=> $gdobras_prefix . 'descritivo_status',
//				'desc'		=> '',
//				'type'		=> 'wysiwyg'	),
        array(  'name'      => 'Data da última R2',
                'id'        => $gdobras_prefix . 'ultima_r2',
                'desc'      => '',
                'type'      => 'date'   ),
        array(  'name'      => 'Emails para atualização atrasada',
                'id'        => $gdobras_prefix . 'atrasada_emails1',
                'desc'      => '',
                'type'      => 'text'   ),
		array(	'name'		=> '% Execução da Obra',
				'id'		=> $gdobras_prefix . 'porc_concluido',
				'desc'		=> 'Porcentagem da obra fisica concluida',
				'type'		=> 'text'	),
		array(	'name'		=> 'Início Efetivo',
				'id'		=> $gdobras_prefix . 'inicio_efetivo',
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
        array(  'name'      => 'Região',
             'id'        => $gdobras_prefix . 'regiao',
             'desc'      => 'Região do estado onde a obra se encontra',
             'type'      => 'select',
             'options'   => array(  '' => " - Selecione - ",
                                    'medio-alto-uruguai' => "Médio Alto Uruguai",
                                    'celeiro' => "Celeiro",
                                    'norte' => "Norte",
                                    'fronteira-noroeste' => "Fronteira Noroeste",
                                    'rio-da-varzea' => "Rio da Várzea",
                                    'nordeste' => "Nordeste",
                                    'noroeste-colonial' => "Noroeste Colonial",
                                    'missoes' => "Missões",
                                    'alto-jacui' => "Alto Jacuí",
                                    'producao' => "Produção",
                                    'campos-de-cima-da-serra' => "Campos de Cima da Serra",
                                    'botucarai' => "Botucaraí",
                                    'central' => "Central",
                                    'campanha' => "Campanha",
                                    'vale-do-jaguari' => "Vale do Jaguari",
                                    'fronteira-oeste' => "Fronteira Oeste",
                                    'jacui-centro' => "Jacuí Centro",
                                    'vale-do-Taquari' => "Vale do Taquari",
                                    'serra' => "Serra",
                                    'hortensias' => "Hortênsias",
                                    'vale-do-cai' => "Vale do Caí",
                                    'paranhana' => "Paranhana",
                                    'v-rio-dos-sinos' => "V. Rio dos Sinos",
                                    'vale-do-rio-pardo' => "Vale do Rio Pardo",
                                    'm-delta-do-jacui' => "M. Delta do Jacuí",
                                    'litoral' => "Litoral",
                                    'centro-Sul' => "Centro-Sul",
                                    'sul' => "Sul")
            ),
		array(	'name'		=> 'Url Stream',
				'id'		=> $gdobras_prefix . 'stream',
				'desc'		=> '',
				'type'		=> 'text'	),
		array(	'name'		=> 'Coordenadas da Obra',
				'id'		=> $gdobras_prefix . 'coordenadas',
				'desc'		=> '',
				'clone'     => true,
				'type'		=> 'text'	),
		array(	'name'		=> 'Município',
				'id'		=> $gdobras_prefix . 'municipio',
				'desc'		=> '',
				'clone'     => true,
				'type'		=> 'text'	),
		array(	'name'		=> 'Código SME',
				'id'		=> $gdobras_prefix . 'numcodigopk',
				'desc'		=> '',
				'type'		=> 'text'	),

		array(	'name'		=> 'Vídeo',
				'id'		=> $gdobras_prefix . 'video',
				'desc'		=> 'Link do video. (Para itens da timeline)',
				'type'		=> 'text'	),

		array(	'name'		=> 'Imagem',
				'id'		=> $gdobras_prefix . 'imagem',
				'desc'		=> 'Link da Imagem. (Para itens da timeline)',
				'type'		=> 'text'	),

		array(	'name'		=> 'Arquivo',
				'id'		=> $gdobras_prefix . 'arquivo',
				'desc'		=> 'Link do Arquivo. (Para itens da timeline)',
				'type'		=> 'text'	),

		array(	'name'		=> 'Votos Positivos',
				'id'		=> $gdobras_prefix . 'voto_up',
				'desc'		=> 'Nro de votos positivos recebidos. (Para itens da timeline)',
				'type'		=> 'number'	),
		array(	'name'		=> 'Votos Negativos',
				'id'		=> $gdobras_prefix . 'voto_down',
				'desc'		=> 'Nro de votos negativos recebidos. (Para itens da timeline)',
				'type'		=> 'number'	),
		array(	'name'		=> 'Score',
				'id'		=> $gdobras_prefix . 'voto_score',
				'desc'		=> 'Nro de votos negativos recebidos. (Para itens da timeline)',
				'type'		=> 'number'	),
		array(	'name'		=> 'Imagem Estatica Google Maps',
				'id'		=> $gdobras_prefix . 'link_maps_estatico',
				'desc'		=> 'Imagem customizada do google maps',
				'type'		=> 'text'	),
		array(	'name'		=> 'Link Google Maps',
				'id'		=> $gdobras_prefix . 'link_maps',
				'desc'		=> 'Link customizado para google maps',
				'type'		=> 'text'	),
        array(  'name'      => 'Obra Entregue?',
                'id'        => $gdobras_prefix . 'obra_entregue',
                'desc'      => 'Indica se a obra já foi entregue aos cidadãos',
                'type'      => 'checkbox' ),

//		array(	'name'		=> 'Evidencias',
//				'id'		=> $gdobras_prefix . 'evidencia',
//				'desc'		=> '',
//				'type'		=> 'file',
//				'nohistory'	=> true ),
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

function gdobras_publish_post( $new_status, $old_status, $post ) {

    $post_id = $post->ID;

    if ( $new_status != $old_status && $new_status == "publish" ){

        if ( $post->post_type == 'gdobra' && get_post_format($post_id) == "status" ){
            // Chama a url do GD que envia o aviso aos seguidores das obras que teve
            // nova atualiação.
            // error_log("Chamando /sendnews para post ID ".$post->post_parent);
            $base_url = get_option("gd_base_url");
            $lines = file($base_url.'deolho/sendnews?obra='.$post->post_parent);
        }

    }


}

add_action( 'transition_post_status', 'gdobras_publish_post', 10, 3 );

# -----

function gdobras_restore_revision( $post_id, $revision_id ) {

	$post     = get_post( $post_id );
	$revision = get_post( $revision_id );


	foreach ($gd_obras_custom_fields as $cf) {
		if(!array_key_exists( 'nohistory', $cf )){
			$meta_name = $cf['id'];
			$my_meta  = get_metadata( 'post', $revision->ID, $meta_name, true );
			// error_log("RESTORING:".$cf['id']."=".$my_meta);
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
			// error_log("VIEWING:".$cf['id']);
			$fields[$cf['id']] = $cf['name'];
		}
	}

	// $fields[$gdobras_prefix . 'descritivo_livre'] = 'Descritivo Livre';
	return $fields;

}
add_filter( '_wp_post_revision_fields', 'gdobras_revision_fields' );

function gdobras_revision_field( $value, $field ) {

	global $revision;
	// error_log("GETTING:".$field);
	return get_metadata( 'post', $revision->ID, $field, true );

}
add_filter( '_wp_post_revision_field_my_meta', 'gdobras_revision_field', 10, 2 );

# Métodos utilizados para salvar as revisões dos dados nas revisões dos posts. /\ /\ /\

# ===============================================================================

// Register Custom Taxonomy
function gdobra_tema_taxonomy()  {
    $labels = array(
        'name'                       => 'Secretarias Responsáveis',
        'singular_name'              => 'Secretaria Responsável',
        'menu_name'                  => 'Secretaria Responsável',
        'all_items'                  => 'Todas as Secretarias',
        'parent_item'                => 'Secretaria pai',
        'parent_item_colon'          => 'Secretaria pai:',
        'new_item_name'              => 'Nova Secretaria Responsável',
        'add_new_item'               => 'Adicionar nova Secretaria Responsável',
        'edit_item'                  => 'Editar Secretaria',
        'update_item'                => 'Alterar Secretaria',
        'separate_items_with_commas' => 'Secretarias Responsáveis separadas por virgulas',
        'search_items'               => 'Procurar secretarias',
        'add_or_remove_items'        => 'Adicionar ou remover secretarias',
        'choose_from_most_used'      => 'Escolha uma das secretarias mais utilizadas',
    );

    $rewrite = array(
        'slug'                       => 'obra/tema',
        'with_front'                 => true,
        'hierarchical'               => true,
    );

    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => true,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
        'query_var'                  => 'tema',
        'rewrite'                    => $rewrite,
    );

    register_taxonomy( 'tema', 'gdobra', $args );
}

// Hook into the 'init' action
add_action( 'init', 'gdobra_tema_taxonomy', 0 );

// Hook to save the last update in childs of "gdobra"
function gdobra_save_update_children( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return;

    if ( !wp_is_post_revision( $post_id )
    && 'gdobra' == get_post_type( $post_id )
    && 'auto-draft' != get_post_status( $post_id ) ) {
        $obra = get_post( $post_id );
        if( 0 != $obra->post_parent ){
            $parents =& get_post_ancestors( $post_id );
            if( !empty( $parents ) ){
                //Salva a data de AGORA no custom_field do pai (a obra)
                $pai_id = end($parents);
                $date = date('Y-m-d H:i:s', time());
                update_post_meta($pai_id, "gdobra_last_child_update", $date);
            }
        }
    }
}
add_action( 'save_post', 'gdobra_save_update_children' );

?>
