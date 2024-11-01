<?php
/*
Plugin Name: 	WP AllAround Connector Plugin
Plugin URI: 	http://www.allaroundsiena.com/plugin
Description: 	This plugin let you to connect a Telegram Bot with your blog
Version: 	0.8.3
Author: 	Michele Pinassi
Author URI:	http://www.zerozone.it
License:	GPL2
License URI: 	https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: 	/languages
Text Domain: 	wp-allaround
*/

if(!defined('WPINC')) {
    die;
}

require_once(dirname(__file__).'/Unirest.php');

if(is_admin()) {
     // We are in admin mode
     require_once(dirname(__file__).'/wp-allaround-admin.php');
}

if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}


/* ============================================================ */
/* 								*/
/* SETUP 							*/
/* 								*/
/* =============================================================*/

/* Hooks */
register_activation_hook(__FILE__, 'allaround_activate');
register_deactivation_hook(__FILE__, 'allaround_deactivate');

add_action( 'activated_plugin', 'allaround_redirect' );

function allaround_redirect($plugin) {
    if($plugin == plugin_basename(__FILE__)) {
	exit( wp_redirect( admin_url( 'admin.php?page=allaround_options' ) ) );
    }
}

function allaround_activate() {
    add_option('allaround_api_key',false);
    add_option('allaround_default_publish',true);
}

function allaround_deactivate() {
    delete_option('allaround_api_key');
    delete_option('allaround_default_publish');
}

function allaround_load_textdomain() {
    load_plugin_textdomain( 'wp-allaround', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'allaround_load_textdomain' );

/* ============================================================ */
/* 								*/
/* POST METABOX							*/
/* 								*/
/* =============================================================*/

function allaround_post_metabox() {
    add_meta_box('allaround-1', __( 'AllAround Option', 'wp-allaround' ), 'allaround_metabox_callback', 'post' );
}
add_action('add_meta_boxes', 'allaround_post_metabox');

function allaround_metabox_callback( $post, $metabox ) {

    $post_status = get_post_meta($post->ID,'allaround_post_status', true);
    $post_date = get_post_meta($post->ID, 'allaround_post_date', true);
    $post_message = get_post_meta($post->ID,'allaround_post_message', true);

    $t_date = date_i18n( get_option( 'date_format' ),strtotime($post_date));

    if($post_status == 'published') {
	printf( __('Post published on %s', 'wp-allaround'), $t_date);
    } else {
	if($post_status == 'error') { /* Error */
	    printf( __('Error %s while publishing on %s', 'wp-allaround'), $post_message, $t_date);
?>
	    <p>
		<input type="checkbox" name="allaround_post_do_publish" id="allaround_post_do_publish" value="1"/>
		<label class="description" for="allaround_post_do_publish"><?php _e('Try again', 'wp-allaround' ); ?></label>
	    </p>
<?php

	} else {
	    echo __("This post was not published on AllAround",'wp-allaround');
?>
	    <p>
		<input type="checkbox" name="allaround_post_do_publish" id="allaround_post_do_publish" value="1" checked/>
		<label class="description" for="allaround_post_do_publish"><?php _e('Publish this post on AllAround', 'wp-allaround' ); ?></label>
	    </p>
<?php
	}
    }
}

/* ============================================================ */
/* 								*/
/* POSTS LIST TABLE						*/
/* 								*/
/* =============================================================*/

/* Display custom column */
function allaround_display_column( $column, $post_id ) {
    if ($column == 'allaround'){
	$post_status = get_post_meta($post_id,'allaround_post_status', true);

	switch($post_status) {
	    case "error":
		$icon_status = "iserror";
		break;
	    case "published":
		$icon_status = "published";
		break;
	    default:
		$icon_status = "na";
		break;
	}   
?>
        <div aria-hidden="true" title="AllAround Post Status" class="allaround-score-icon <?php echo $icon_status; ?>"></div><span class="screen-reader-text"><?php echo $publish_status; ?></span></td>
<?php
    }
}
add_action( 'manage_posts_custom_column' , 'allaround_display_column', 10, 2 );

function allaround_column($columns) {
    return array_merge( $columns, 
              array('allaround' => __('AllAround')) );
}
add_filter('manage_posts_columns' , 'allaround_column');

/* ============================================================ */
/* 								*/
/* PUBLISH POST 						*/
/* 								*/
/* =============================================================*/

function allaround_excerpt($text) {
    $text = strip_shortcodes( $text );
    $text = apply_filters('the_content', $text);
    $text = str_replace(']]>', ']]>', $text);
    $text = strip_tags($text);
    $text = nl2br($text);
    $excerpt_length = apply_filters('excerpt_length', 55);
    $words = explode(' ', $text, $excerpt_length + 1);
    if (count($words) > $excerpt_length) {
        array_pop($words);
        array_push($words, '[...]');
        $text = implode(' ', $words);
    }
    return $text;
}

function allaround_do_publish($post) {
    $option_api_key = get_option( 'allaround_api_key' );

    if($option_api_key) {
        /* Get the post properties */

        $author = $post->post_author;

	$post_title = $post->post_title;
	$post_excerpt = allaround_excerpt($post->post_content); 
	$post_author = get_the_author_meta('display_name', $author);
	$post_date = $post->post_date_gmt;
	$post_url = get_permalink($post->ID);
	/* Post have an image ? */
	if(has_post_thumbnail($post->ID)) {
	    $post_image_url = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'medium')[0];
	} else {
	    $post_image_url = '';
	}

	/* Get post TAGS */
	$tags_array = array();
	$tags = wp_get_post_tags($post->ID);
	    
	foreach($tags as $tag) {
	    $tags_array[] = $tag->name;
	}

	$post_tags = implode(",",$tags_array);

	/* Build REST query */

	$data = array('key' => $option_api_key,
	    'title' => $post_title,
	    'excerpt' => $post_excerpt,
	    'author' => $post_author,
	    'date' => $post_date,
	    'image_url' => $post_image_url,
	    'tags' => $post_tags,
	    'url' => $post_url,
	);

	$headers = array('Accept' => 'application/json');

	$body = Unirest\Request\Body::multipart($data);

	$result = Unirest\Request::post('https://www.allaroundsiena.com/rest/publish', $headers, $body);
	error_log(print_r($result, true));

	/* 	[code] => 403
		[raw_body] => {
		"error": {
	            "code": 403,
	            "message": "Error: this POST seems to be a duplicate"
	        }
	*/

	return $result;
    }
    return false;
}

/* ============================================================ */
/* 								*/
/* PUBLISH HOOK 						*/
/* 								*/
/* =============================================================*/

function allaround_action_publish($new_status, $old_status, $post) {
    $do_publish = false;

    error_log("FIRED: allaround_action_publish($new_status,$old_status,$post->ID)");

    if ($new_status == 'publish') {

	/* Check if this post was already published to avoid duplicates */
	$post_status = get_post_meta($post->ID,'allaround_post_status', true);

	if($post_status == 'published') {
	    /* If already published, do nothing... */
	} else {
	    if(isset($_POST['allaround_post_do_publish'])) {
    		$do_publish = true;
	    }
	}
    }

    /* Need to publish ? */
    if($do_publish) {
	/* Publish and get the result */
	$result = allaround_do_publish($post);

	if($result) {
	    $post_date = current_time('mysql');

	    $post_result = json_decode($result->raw_body,true);

	    error_log(print_r($post_result, true));

	    if(isset($post_result['error'])) {
		// ERROR !
		$post_result_code = $result->code;
		$post_result_message = $post_result['error']['message'];
		$post_is_published = false;
	    } else if(isset($post_result['success'])) {
		// DONE !
		$post_result_code = $result->code;
		$post_result_message = $post_result['success'];
		$post_is_published = true;
	    } else {
		// Something wrong with network ?
		$post_is_published = false;
	    }

	    update_post_meta($post->ID, "allaround_post_date","$post_date");
	    update_post_meta($post->ID, "allaround_post_message", $post_result_message);

	    if($post_is_published) { /* Success */
		update_post_meta($post->ID, "allaround_post_status","published");
		add_action( 'admin_notices', 'allaround_notice_publish_success' );
	    } else {
		update_post_meta($post->ID, "allaround_post_status","error");
		add_action( 'admin_notices', 'allaround_notice_publish_error' );
	    }
	} else {
    	    add_action( 'admin_notices', 'allaround_notice_publish_error' );
	}
    }
}

add_action('transition_post_status', 'allaround_action_publish', 10, 3);

/* ============================================================ */
/* 								*/
/* NOTICES							*/
/* 								*/
/* =============================================================*/


function allaround_notice_publish_success() {
?>
    <div class='notice notice-success'>
	<p><?php _e('Post published successfully on AllAround !', 'wp-allaround' ); ?></p>
    </div>
<?php
}

function allaround_notice_publish_error() {
?>
    <div class='notice notice-error'>
	<p><?php _e('Ooops ! Something wrong happens while publish your post on AllAround: try again in few minutes', 'wp-allaround' ); ?></p>
    </div>
<?php
}
