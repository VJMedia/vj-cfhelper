<?php
/*
Plugin Name: VJMedia: Cloudflare Helper
Description: Cloudflare Toolbox
Version: 1.0
Author: <a href="http://www.vjmedia.com.hk">VJMedia Technical Team</a>
GitHub Plugin URI: https://github.com/VJMedia/vj-cfhelper
*/

defined('WPINC') || (header("location: /") && die());

function vjcf_dummy(){}

function vjcf_pluginsettings() {
	register_setting( 'vjcf-settingsgroup', 'vjmedia_cfhelper_zoneid' );
	register_setting( 'vjcf-settingsgroup', 'vjmedia_cfhelper_xauth_email' );
	register_setting( 'vjcf-settingsgroup', 'vjmedia_cfhelper_xauth_key' );
}

function vjcf_adminmenu(){
	add_menu_page('Cloudflare Helper', 'CF Helper', 'administrator', __FILE__, 'vjcf_settingspage' , 'dashicons-cloud');
	add_action( 'admin_init', 'vjcf_pluginsettings' );
} add_action('admin_menu', 'vjcf_adminmenu');

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
	
	$result_decode=json_decode($result);
	if($result_decode->success){
		if(! parse_url($_GET["return"])["query"]){
			$symbol="?";
		}else{
			$symbol="&";
		}
		
		header("location:". $_GET["return"].$symbol."vjcf_purgesuccess=true&vjcf_purgemessage=".$result);
	}else{
		echo json_encode($result);
	}
	wp_die(); 
}add_action( 'wp_ajax_vjcf_purge', 'vjcf_purge_callback' );

function vjcf_adminbar( $wp_admin_bar ) {
	$args = array(
		'id'    => 'vjcp',
		'title' => 'Full Purge Cloudflare',
		'href'  => admin_url( 'admin-ajax.php' ).'?action=vjcf_purge&return='.urlencode((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"),
		'meta'  => array( 'class' => 'my-toolbar-page' )
	);
	$wp_admin_bar->add_node( $args );
}add_action( 'admin_bar_menu', 'vjcf_adminbar', 999 );

function vjcf_inlinejs(){ ?>
<script>
function vjcf_purge(){
	var data = {'action': 'vjcf_purge',};
	jQuery.get(ajaxurl, data, function(response) {
		//console.log(ajaxurl);
		//console.log(data);
		response=(JSON.parse(response));
		if(response.success === true){
			alert("Success");
		}else{
			alert("Fail");
		}
	}, "json");
}
</script>
<?php }add_action( 'admin_print_scripts', 'vjcf_inlinejs' ); 

function vjcf_adminnotice() {
	if($_GET["vjcf_purgesuccess"]){
    ?>
    <div class="notice notice-success is-dismissible">
        <p>Pluge cloudflare success: <br /><?=stripslashes($_GET["vjcf_purgemessage"]);?></p>
    </div>
    <?php
}}
add_action( 'admin_notices', 'vjcf_adminnotice' );

function vjcf_saveposthook( $post_id ) {
	if (
		get_post_type($post_id) != "post" ||
		wp_is_post_revision($post_id) ||
		wp_is_post_autosave($post_id) ||
		get_post_status($post_id)=="auto-draft" ||
		get_post_status($post_id)=="draft"
	) return;

	$post_url = get_permalink( );
	$ogurl=vjmedia_ogurl($post_id);
	$wildcard_url=$ogurl."/*";

	//$result=exec($q='curl -X DELETE "https://api.cloudflare.com/client/v4/zones/'.$zoneid.'/purge_cache" -H "X-Auth-Email: '.$xauth_email.'" -H "X-Auth-Key: '.$xauth_key.'" -H "Content-Type: application/json" --data \'{"files":["'.$post_url.'","'.$ogurl.'","'.$wildcard_url.'"]}\'');
	
	$result = vjcf_dopurge([$post_url,$ogurl,$wildcard_url]);
	$result_decode=json_decode($result);
	
	if($result_decode->success){
		//echo $q;
		//exit();
	}else{
		echo "Failed to purge cloudflare cache, please contact Sheep Sheep: ";
		var_dump($result_decode);
		var_dump($result);
		var_dump($post_url);
		var_dump($ogurl);
		var_dump($wildcard_url);
		wp_die();
	}
}
add_action( 'save_post', 'vjcf_saveposthook' );

function vjcf_dopurge($url){

	$zoneid=esc_attr( get_option('vjmedia_cfhelper_zoneid')) ?? false;
	$xauth_email=esc_attr( get_option('vjmedia_cfhelper_xauth_email')) ?? false;
	$xauth_key=esc_attr( get_option('vjmedia_cfhelper_xauth_key')) ?? false;
	
	if(is_array($url)){
		$url = '"'.implode('","',$url).'"';
	}else{
		$url = '"'.$url.'"';
	}
	
	if(! $zoneid || ! $xauth_email || ! $xauth_key){
		$result = false;
	}else{
		exec($q='curl -X DELETE "https://api.cloudflare.com/client/v4/zones/'.$zoneid.'/purge_cache" -H "X-Auth-Email: '.$xauth_email.'" -H "X-Auth-Key: '.$xauth_key.'" -H "Content-Type: application/json" --data \'{"files":['.$url.']}\'', $out);	
		
		foreach($out as $line) {
			echo $result.=$line;
		}
		
		
	}
	return $result;
}
?>
