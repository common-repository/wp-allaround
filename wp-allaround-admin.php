<?php
/* Only if ADMIN */
if(!is_admin()) {
    wp_die();
}

/* ============================================================ */
/* 								*/
/* POST 							*/
/* 								*/
/* =============================================================*/

add_action( 'admin_post_register', 'allaround_action_register' );

function allaround_action_register() {
    $headers = array('Accept' => 'application/json');

    // Verify if the nonce is valid
    if ( !isset($_POST['_mynonce']) || !wp_verify_nonce($_POST['_mynonce'], 'register-user')) {
	echo "NONCE VERIFICATION FAILED";
    } else {
	$option_api_key = get_option( 'allaround_api_key' );

	if(!isset($option_api_key)) {
	    $option_api_key = ''; /* No API key */
	}

	$blog_name = ($_POST["allaround_blog_name"] ? $_POST["allaround_blog_name"]:get_bloginfo('name'));
	$blog_description = ($_POST["allaround_blog_description"] ? $_POST["allaround_blog_description"]:get_bloginfo('description'));
	$blog_admin_email = ($_POST["allaround_admin_email"] ? $_POST["allaround_admin_email"]:get_bloginfo('admin_email'));
	$blog_language = get_bloginfo('language');
	$blog_url = ($_POST["allaround_blog_url"] ? $_POST["allaround_blog_url"]:get_bloginfo('url'));

	$data = array('key' => $option_api_key,
	    'blog_name' => $blog_name,
	    'blog_description' => $blog_description,
	    'blog_admin_email' => $blog_admin_email,
	    'blog_language' => $blog_language,
	    'blog_url' => $blog_url,
	);

	$body = Unirest\Request\Body::multipart($data);

	$result = Unirest\Request::post('https://www.allaroundsiena.com/rest/register', $headers, $body);

	write_log($result);

	if($result->code == '200') {
	    if(isset($result->body->apikey)) {
		$api_key = $result->body->apikey;

		update_option('allaround_api_key', $api_key);

		$result = "success";
	    } else {
		$result = "fail";
    	    }
	} else {
	    $result = "fail";
	}

	wp_redirect(admin_url("admin.php?page=allaround_options&result=$result"));
	exit;
    }
    wp_die();
}

add_action( 'admin_post_update', 'allaround_action_update' );

function allaround_action_update() {
    if ( !isset($_POST['_mynonce']) || !wp_verify_nonce($_POST['_mynonce'], 'update')) {
	echo "NONCE VERIFICATION FAILED";
    } else {
	if(isset($_POST["allaround_default_publish"])) {
	    update_option( 'allaround_default_publish',true);
	} else {
	    update_option( 'allaround_default_publish',false);
        }

	$api_key = $_POST["allaround_api_key"];

	update_option('allaround_api_key',$api_key);

	$result = 'updated';

	wp_redirect(admin_url("admin.php?page=allaround_options&result=$result"));
	exit;
    }
}

function allaround_enqueue() {
    global $wp_styles;

    wp_register_script( 'validation-locale', plugins_url( '/js/jquery.validationEngine-it.js', __FILE__ ));
    wp_register_script( 'validation-engine', plugins_url( '/js/jquery.validationEngine.js', __FILE__ ));
    wp_register_script( 'custom-js', plugins_url( '/js/custom.js', __FILE__ ));

    wp_localize_script( 'ajax-script', 'ajax_object', array(
	'ajax_url' => admin_url( 'admin-ajax.php' ),
    ));
     
    wp_register_style( 'validation-css', plugins_url( '/css/validationEngine.jquery.css', __FILE__ ));
    wp_register_style( 'custom-css', plugins_url( '/css/wp-allaround.css', __FILE__ ));

    wp_enqueue_script('validation-locale');
    wp_enqueue_script('validation-engine');
    wp_enqueue_script('custom-js');
    wp_enqueue_style('validation-css');
    wp_enqueue_style('custom-css');
}

add_action('admin_enqueue_scripts', 'allaround_enqueue');

/* ============================================================ */
/* 								*/
/* AJAX 							*/
/* 								*/
/* =============================================================*/

add_action( 'wp_ajax_verify', 'allaround_action_verify' );

function allaround_action_verify() {
    global $wpdb; // this is how you get access to the database

    // Do connection test to REST Server @ allaroundsiena.com/bot/rest
    $option_api_key = get_option('allaround_api_key');

    $headers = array('Accept' => 'application/json');

    $data = array('key' => $option_api_key,
	'blog_name' => get_bloginfo('name'),
	'blog_description' => get_bloginfo('description'),
	'blog_admin_email' => get_bloginfo('admin_email'),
	'blog_language' => get_bloginfo('language'),
	'blog_url' => get_bloginfo('url'),
    );

    $body = Unirest\Request\Body::multipart($data);

    $result = Unirest\Request::post('https://www.allaroundsiena.com/rest/verify', $headers, $body);

    if($result->code == '200') {
	_e("Connected !",'wp-allaround');
    } else {
	_e("Error: ",'wp-allaround');
	echo $result->code;
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

/* ============================================================ */
/* 								*/
/* ADMIN 							*/
/* 								*/
/* =============================================================*/

function allaround_options_menu() {
    add_submenu_page(
          'options-general.php',          // admin page slug
          __( 'AllAround Options', 'wp-allaround' ), // page title
          __( 'AllAround Options', 'wp-allaround' ), // menu title
          'manage_options',               // capability required to see the page
          'allaround_options',                // admin page slug, e.g. options-general.php?page=wporg_options
          'allaround_options_page'            // callback function to display the options page
     );
}
add_action('admin_menu', 'allaround_options_menu');

function allaround_register_settings() {
     register_setting(
          'allaround_options',  // settings section
          'allaround_api_key' // setting name
     );
     register_setting(
          'allaround_options',  // settings section
          'allaround_default_ublish' // setting name
     );

}
add_action( 'admin_init', 'allaround_register_settings' );

function allaround_options_page() {
     if(!isset( $_REQUEST['settings-updated'])) {
          $_REQUEST['settings-updated'] = false; 
    }
    settings_fields( 'allaround_options' );
    $option_api_key = get_option( 'allaround_api_key' );
    $option_default_publish = get_option( 'allaround_default_publish' );
?>
     <div class="wrap"><!-- WRAP -->
          <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
<?php 
    if(empty($option_api_key)) {

	if(isset($_REQUEST['result'])) {
	    if($_REQUEST['result'] == 'fail') {
?>
		<div class='notice notice-error'>
		    <p><?php _e('Ooops ! Something wrong while registering your blog: please try later.', 'wp-allaround' ); ?></p>
		</div>
<?php
	    } else if($_REQUEST['result'] == 'success') {
?>	
		<div class='notice notice-success'>
		    <p><?php _e('Great ! Your BLOG was registered successfully on AllAroundSiena.com: now check your mailbox...', 'wp-allaround' ); ?></p>
		</div>
<?php
	    }
	}
?>
	<div>
	    <script>
		jQuery(document).ready(function($){
	    	    jQuery(".validate").validationEngine('attach');
		});
	    </script>
	    <form class="validate" action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
		<?php echo wp_nonce_field('register-user', '_mynonce'); ?>
		<input type="hidden" name="action" value="register">
		<h3><?php _e('Register your blog on AllAroundSiena.com', 'wp-allaround' ); ?></h3>
		<p><?php _e('Please check (and fix, if need) the following data, that will be sent to allaroundsiena.com server to register your blog. Check twice Admin e-mail field, because this mailbox will receive 
credentials to access AllAroundSiena and manage your blog subscriptions'); ?></p>
		<table class="form-table">
		    <tr valign="top">
			<td>
			    <?php _e('Blog name','wp-allaround');?>: 
			</td><td>
			    <input type="text" name="allaround_blog_name" id="allaround_blog_name" class="validate[required]" value="<?php echo get_bloginfo('name'); ?>">
			</td>
		    </tr><tr>
			<td>
			    <?php _e('Blog description','wp-allaround');?>: 
			</td><td>
			    <input type="text" name="allaround_blog_description" id="allaround_blog_description" class="validate[required]" value="<?php echo get_bloginfo('description'); ?>">
			</td>
		    </tr><tr>
			<td>
			    <?php _e('Admin e-mail','wp-allaround');?>: 
			</td><td>
			    <input type="text" name="allaround_admin_email" id="allaround_admin_email"  class="validate[required,custom[email]]" value="<?php echo get_bloginfo('admin_email'); ?>">
			</td>
		    </tr><tr>
			<td>
			    <?php _e('Blog URL','wp-allaround');?>: 
			</td><td>
			    <input type="text" name="allaround_blog_url" id="allaround_blog_url" class="validate[required]" value="<?php echo get_bloginfo('url'); ?>">
			</td>
		    </tr>
		</table>
		<p>
		    <?php _e("By clicking Sign Up, you agree to our <a href='http://www.allaroundsiena.com/legal'>Terms</a>, including data use policy and cookie use"); ?>
		</p>
	        <input type="submit" class="btn btn-large" id="allaround_register" value="<?php _e('Sign up', 'wp-allaround'); ?>">
	    </form>
	</div>
<?php
    } else {
	if(isset($_REQUEST['result'])) {
	    if($_REQUEST['result'] == 'updated') {
?>
		<div class='notice notice-success'>
		    <p><?php _e('Options updated !', 'wp-allaround' ); ?></p>
		</div>
<?php
	    }
	}
?>
	<div id="poststuff">
	    <div id="post-body">
		<div id="post-body-content">
		    <table class="form-table">
<tr>
			    <td>
			        <button class="btn btn-large" id="allaround_verify"><?php _e('Verify connection', 'wp-allaround'); ?></button> <span id="allaround_verify_result">&nbsp;</span>
			    </td>
			</tr>
		    </table>
		    <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
			<?php echo wp_nonce_field('update', '_mynonce'); ?>
		        <input type="hidden" name="action" value="update">
		    	<table class="form-table">
		    	    <tr valign="top">
				<th scope="row"><?php _e('Your AllAround BOT Api Key', 'wp-allaround' ); ?></th>
				<td>
			    	    <input type="text" name="allaround_api_key" id="allaround_api_key" value="<?php echo $option_api_key; ?>">
			    	    <br/>
			    	    <label class="description" for="allaround_api_key"><?php _e('This is the unique API key for your blog. Please, keep it safe and do not change if not needed.'); ?></label>
				</td>
			    </tr><tr>
				<th scope="row"><?php _e('Default is to publish new articles', 'wp-allaround' ); ?></th>
				<td>
				    <input type="checkbox" name="allaround_default_publish" id="allaround_default_publish" <?php echo ($option_default_publish ? 'checked':''); ?> >
				</td>
			    </tr><tr>
				<td>
			    	    <input type="submit" value="<?php _e('Submit'); ?>">
				</td>
			    </tr>
			</table>
		    </form>
		</div> <!-- end post-body-content -->
	    </div> <!-- end post-body -->
	</div> <!-- end poststuff -->
<?php
    }
?>
    </div><!-- /WRAP -->
<?php
}
?>
