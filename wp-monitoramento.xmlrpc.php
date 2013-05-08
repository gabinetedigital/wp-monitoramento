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
        'format' => (($f = get_post_format($post)) === '' ? 'standard' : $f),
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
      'post_status' => 'publish'
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
    #
    if (!is_array($args = _monit_method_header($args)))
        return $args;
    if (!isset($args[1]))
        return null;

    error_log(' ======================================== ARGS ======================================== ');
    error_log(print_r($args, True));

    $post_type = "gdobra";
    $the_parent = $args[1];

    $query=array(
      'post_parent' => $the_parent,
      'post_type' => $post_type,
      'numberposts' => 10,
      'post_status' => 'publish',
      'orderby' => 'post_date',
      'order' => 'desc'
    );
    $my_posts = get_posts($query);
    error_log( print_r( (array)$my_posts[0], True) );
    if( $my_posts ) {
        error_log( 'ID on the first post found '.$my_posts[0]->ID );
    }

    // Handling posts found
    $struct = array( );
    foreach ( (array)$my_posts as $post ) {
        array_push($struct, _exapi_prepare_post( (array)$post, $params) );
    }
    return $struct;
}

add_filter('xmlrpc_methods', function ($methods) {
    $methods['monitoramento.getObras'] = 'monitoramento_getObras';
    $methods['monitoramento.getObra'] = 'monitoramento_getObra';
    $methods['monitoramento.getObraTimeline'] = 'monitoramento_getObraTimeline';
    return $methods;
});

?>
