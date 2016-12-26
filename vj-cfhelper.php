<?php
/*
Plugin Name: VJMedia: Cloudflare Helper
Description: Cloudflare Toolbox
Version: 1.0
Author: <a href="http://www.vjmedia.com.hk">VJMedia Technical Team</a>
*/

function vjcf_adminmenu(){
        add_menu_page('Cloudflare Helper', 'CF Helper', 'administrator', __FILE__, 'vjcf_settingspage' , 'dashicons-cloud');
        add_action( 'admin_init', 'vjcf_pluginsettings' );
} add_action('admin_menu', 'vjcf_adminmenu');

function vjcf_pluginsettings() {
        register_setting( 'vjcf-settingsgroup', 'vjmedia_cfhelper_zoneid' );
        register_setting( 'vjcf-settingsgroup', 'vjmedia_cfhelper_xauth_email' );
        register_setting( 'vjcf-settingsgroup', 'vjmedia_cfhelper_xauth_key' );
}

function vjcf_settingspage(){
?>
<div class="wrap">
<h1>VJMedia Cloudflare Helper</h1>

<form method="post" action="options.php">
        <?php settings_fields( 'vjcf-settingsgroup' ); ?>
        <?php do_settings_sections( 'vjcf-settingsgroup' ); ?>
        <table class="form-table">
                <tr valign="top"><th scope="row">Zone ID</th><td><input type="text" name="vjmedia_cfhelper_zoneid" value="<?php echo esc_attr( get_option('vjmedia_cfhelper_zoneid') ); ?>" /></td></tr>
                <tr valign="top"><th scope="row">XAuth Email</th><td><input type="text" name="vjmedia_cfhelper_xauth_email" value="<?php echo esc_attr( get_option('vjmedia_cfhelper_xauth_email') ); ?>" /></td></tr>
                <tr valign="top"><th scope="row">XAuth Key</th><td><input type="text" name="vjmedia_cfhelper_xauth_key" value="<?php echo esc_attr( get_option('vjmedia_cfhelper_xauth_key') ); ?>" /></td></tr>
        </table>

    <?php submit_button(); ?>

</form>
</div>
<?php }


function vjcf_purge_callback() {
	$zoneid=esc_attr( get_option('vjmedia_cfhelper_zoneid')) ?? false;
	$xauth_email=esc_attr( get_option('vjmedia_cfhelper_xauth_email')) ?? false;
	$xauth_key=esc_attr( get_option('vjmedia_cfhelper_xauth_key')) ?? false;

	if(! $zoneid || ! $xauth_email || ! $xauth_key){

	}else{
		if(is_admin()){
			$result=exec('curl -X DELETE "https://api.cloudflare.com/client/v4/zones/'.$zoneid.'/purge_cache" -H "X-Auth-Email: '.$xauth_email.'" -H "X-Auth-Key: '.$xauth_key.'" -H "Content-Type: application/json" --data \'{"purge_everything":true}\'');
		}
	}

	echo json_encode($result); wp_die(); 
}add_action( 'wp_ajax_vjcf_purge', 'vjcf_purge_callback' );


function vjcf_adminbar( $wp_admin_bar ) {
	$args = array(
		'id'    => 'vjcp',
		'title' => 'Purge Cloudflare',
		'href'  => admin_url( 'admin-ajax.php' ).'?action=vjcf_purge',
		'meta'  => array( 'class' => 'my-toolbar-page' )
	);
	$wp_admin_bar->add_node( $args );
}add_action( 'admin_bar_menu', 'vjcf_adminbar', 999 );

function vjcf_inlinejs(){ ?>
<script>
function vjcf_purge(){
		var data = {
			'action': 'vjcf_purge',
		};

		jQuery.get(ajaxurl, data, function(response) {
			console.log(ajaxurl);
			console.log(data);
			response=(JSON.parse(response));
			if(response.success === true){
				alert("Success");
			}else{
				alert("Fail");
			}
		}, "json");
}
</script>
<?php }add_action( 'admin_print_scripts', 'vjcf_inlinejs' ); ?>
