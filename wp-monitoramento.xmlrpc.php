<?php /* -*- Mode: php; c-basic-offset:4; -*- */
/* Copyright (C) 2011  Governo do Estado do Rio Grande do Sul
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function _monit_method_header(&$args) {
    // We don't like smart-ass people
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );

    // getting rid of blog_id
    array_shift($args);

    // Reading the attribute list
    $username = array_shift($args);
    $password = array_shift($args);

    // All methods in this API are being protected
    if (!$user = $wp_xmlrpc_server->login($username, $password))
        return $wp_xmlrpc_server->error;
    return $args;
}

function _monit_prepare_post($post, $params) {
    global $wp_xmlrpc_server;
    $pid = $post['ID'];
    $post_date = monit_post_date($post);
    // error_log("POST::::::::::::::::::");
    // error_log(print_r($post, True));
    return array(
        'id' => (string) $pid,
        'title' => $post['post_title'],
        'slug' => $post['post_name'],
        'date' => $post_date,
        'tema' => monit_post_tema($pid),
        'link' => post_permalink($pid),
        'format' => get_post_format($pid),
        'author' => monit_post_author($post),
        'categories' => monit_post_categories($post),
        'tags' => monit_post_tags($post),
        'tags_object' => monit_post_tags_object($post),
        'comments' => monit_post_comment_info($post),
        'thumbs' => monit_post_thumb($post, $params),
        'excerpt' => monit_post_excerpt($post),
        'content' => monit_post_content($post),
        'post_status' => $post['post_status'],
        'post_type' => get_post_type( $pid ),
        'custom_fields' => $wp_xmlrpc_server->get_custom_fields($pid),
    );
}

function monitoramento_getObra($args) {
    #
    # Retorna a lista completa dos posts(gdobra)
    #
    if (!is_array($args = _monit_method_header($args)))
        return $args;

    error_log(' ======================================== ARGS ======================================== ');
    error_log(print_r($args, True));

    $post_type = "gdobra"; #$args[1];

    if (!isset($args[1]))
        return null;

    $the_slug = $args[1];
    error_log($the_slug);

    $query=array(
      'name' => $the_slug,
      'post_type' => $post_type,
      'post_status' => 'publish',
      'numberposts' => 1
    );
    $my_posts = get_posts($query);

    error_log( print_r( (array)$my_posts[0], True) );
    if( $my_posts ) {
        error_log( 'ID on the first post found '.$my_posts[0]->ID );
    }

    $post = _monit_prepare_post( (array)$my_posts[0], $args);
    // return $my_posts[0];
    return $post;
}

function monitoramento_getObras($args) {
    #
    # Retorna a lista completa dos posts(gdobra)
    #
    if (!is_array($args = _monit_method_header($args)))
        return $args;

    error_log(' ======================================== ARGS ======================================== ');
    error_log(print_r($args, True));

    $post_type = "gdobra"; #$args[1];

    // if (isset($args[1]))
    //     $the_slug = $args[1];

    $query=array(
      // 'name' => $the_slug,
      'post_type' => $post_type,
      'post_status' => 'publish',
      'post_parent'     => 0 # -> Somente os posts PAI
      //, 'numberposts' => 1
    );
    $my_posts = get_posts($query);

    error_log( print_r( (array)$my_posts[0], True) );
    if( $my_posts ) {
        error_log( 'ID on the first post found '.$my_posts[0]->ID );
    }

    // Handling posts found
    $obras = array( );
    foreach ( (array)$my_posts as $post ) {
        array_push($obras, _monit_prepare_post( (array)$post, $args) );
    }
    return $obras;

    // $post = _monit_prepare_post( (array)$my_posts[0], $args);
    // // return $my_posts[0];
    // return $post;
}

function monitoramento_getObraTimeline($args) {
    #
    # Este método retorna todos os posts(gdobra) filhos de um gdobra específico.
    # São os itens da 'timeline'
    # Se um id for passado, retorna somente aquele item especifico
    #
    if (!is_array($args = _monit_method_header($args)))
        return $args;
    if (!isset($args[1]))
        return null;

    error_log(' ======================================== ARGS ======================================== ');
    error_log(print_r($args, True));

    $post_type = "gdobra";
    $the_parent = $args[1];

    // Utilizado get_pages ao invés de get_posts pois get_pages retorna a arvore correta dos posts conforme hierarquia.
    if (isset($args[2])){
        $theid = $args[2];
        $my_posts = get_pages("include=$theid&post_type=$post_type&post_status=publish&orderby=post_date&order=desc");
    }else{
        $my_posts = get_pages("child_of=$the_parent&post_type=$post_type&post_status=publish&orderby=post_date&order=desc");
    }

    error_log( print_r( (array)$my_posts[0], True) );
    if( $my_posts ) {
        error_log( 'ID on the first post found '.$my_posts[0]->ID );
    }

    // Handling posts found
    $struct = array( );
    foreach ( (array)$my_posts as $post ) {
        array_push($struct, _monit_prepare_post( (array)$post, $args) );
    }
    return $struct;
}

function monitoramento_getObraStatsFilhos($args){

    global $wpdb;

    if (!is_array($args = _monit_method_header($args)))
        return $args;

    error_log(' ======================================== ARGS ======================================== ');
    error_log(print_r($args, True));

    if (isset($args[1])){
        // Conta todos os filhos de uma obra.
        $post_pai = $args[1];

        $querystr = "
            SELECT count(1) filhos
            FROM $wpdb->posts
            WHERE $wpdb->posts.post_parent = $post_pai
            AND $wpdb->posts.post_status = 'publish'
            AND $wpdb->posts.post_type = 'gdobra'
        ";
    }else{
        // Conta todos os items de timeline de obras. TOTAL GERAL
        $querystr = "
            SELECT count(1) filhos
            FROM $wpdb->posts
            WHERE $wpdb->posts.post_status = 'publish'
            AND $wpdb->posts.post_type = 'gdobra'
        ";
    }

    // error_log($querystr);
    $pageposts = $wpdb->get_results($querystr, OBJECT);
    // error_log( print_r( $pageposts, True) );

    foreach ($pageposts as $key) {
        return $pageposts[0]->filhos;
    }

    return 0;
}

function monitoramento_getObraStatsVotos($args){

    global $wpdb;

    if (!is_array($args = _monit_method_header($args)))
        return $args;

    error_log(' ======================================== ARGS ======================================== ');
    error_log(print_r($args, True));

    if (isset($args[1])){
        // Conta todos os filhos de uma obra.
        $post_id = $args[1];

        $querystr = "
            SELECT sum(wpostmeta.meta_value) votos
            FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta
            WHERE wposts.ID = $post_id
            AND wposts.ID = wpostmeta.post_id
            AND wpostmeta.meta_key = 'gdobra_voto_up'
            AND wposts.post_status = 'publish'
            AND wposts.post_type = 'gdobra'
            ";
    }else{
        // Conta todos os items de timeline de obras. TOTAL GERAL
        $querystr = "
            SELECT sum(wpostmeta.meta_value) votos
            FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta
            WHERE wposts.ID = wpostmeta.post_id
            AND wpostmeta.meta_key = 'gdobra_voto_up'
            AND wposts.post_status = 'publish'
            AND wposts.post_type = 'gdobra'
            ";
    }

    error_log($querystr);
    $pageposts = $wpdb->get_results($querystr, OBJECT);
    // error_log( print_r( $pageposts, True) );

    foreach ($pageposts as $key) {
        return $pageposts[0]->votos;
    }

    return 0;
}

add_filter('xmlrpc_methods', function ($methods) {
    $methods['monitoramento.getObras'] = 'monitoramento_getObras';
    $methods['monitoramento.getObra'] = 'monitoramento_getObra';
    $methods['monitoramento.getObraTimeline'] = 'monitoramento_getObraTimeline';
    $methods['monitoramento.getObraStatsFilhos'] = 'monitoramento_getObraStatsFilhos';
    $methods['monitoramento.getObraStatsVotos'] = 'monitoramento_getObraStatsVotos';
    return $methods;
});

?>
