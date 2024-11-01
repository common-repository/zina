<?php
/*
Plugin Name: Zina
Plugin URI: http://pancake.org/zina
Description: Zina is a graphical interface to your MP3 collection, a personal jukebox, an MP3 streamer.
Version: 2.0b22
Author: Ryan Lathouwers
Author URI: http://www.pancake.org
*/
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * ZINA (Zina is not Andromeda)
 *
 * Zina is a graphical interface to your MP3 collection, a personal
 * jukebox, an MP3 streamer. It can run on its own, embeded into an
 * existing website, or as a Drupal/Joomla/Wordpress/etc. module.
 *
 * http://www.pancake.org/zina
 * Author: Ryan Lathouwers <ryanlath@pacbell.net>
 * Support: http://sourceforge.net/projects/zina/
 * License: GNU GPL2 <http://www.gnu.org/copyleft/gpl.html>
 *
 * Wordpress Module
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('activate_' . plugin_basename(__FILE__), 'zinawp_pluginactivate');
add_action('deactivate_' . plugin_basename(__FILE__), 'zinawp_plugindeactivate');
add_action('admin_head', 'zinawp_admin_init');
add_action('admin_menu', 'zinawp_menus_admin');
add_filter('wp_title', 'zinawp_title');
add_action('wp_head', 'zinawp_header');
add_action('template_redirect', 'zinawp_zina');
add_action('show_user_profile', 'zinawp_profile');
add_action('edit_user_profile', 'zinawp_profile');
add_action('profile_update', 'zinawp_profile_update');
add_action('wp_logout', 'zinawp_logout');
add_filter('rewrite_rules_array', 'zinawp_rewrite_rules');
add_filter('mod_rewrite_rules', 'zinawp_modrewrite');
add_filter('init', 'zinawp_init');
add_action('widgets_init', 'zinawp_widgets_init');
add_filter('generate_rewrite_rules', 'zinawp_trimpermalinkrules');

#uninstall called on upgrade =(
#if ( function_exists('register_uninstall_hook') ) register_uninstall_hook(__FILE__, 'zinawp_uninstall');

#if (function_exists('zinawp_init')) return;

function zinawp_init() {
	if (zinawp_is_zina() && ($_GET['l'] == 10 || $_GET['l'] == 6 || $_GET['l'] == 65)) {
		remove_action('template_redirect', 'redirect_canonical');
		if (function_exists('members_only')) {
			remove_action('template_redirect', 'members_only');
		}
	}
}
 
function zinawp_cron() {
  $conf['time'] = microtime(true);
  $conf['embed'] = 'drupal';
  $conf['index_abs'] = dirname(__FILE__);
  require_once('zina/index.php');
  zina_init($conf);
  zina_cron_run();
}

function zinawp_rewrite_rules($rules) {
	$options = get_option('zina_options');
	$post = get_post($options['page_id']);
	$rules = array($post->post_name."/([^?]+)"=>"index.php?page_id=".$options['page_id']."&p=%1") + $rules;
	return $rules;
}

function zinawp_modrewrite($rules) {
	$home_root = parse_url(get_option('home'));
	$home_root = trailingslashit($home_root['path']);

	$options = get_option('zina_options');
	$post = get_post($options['page_id']);
	$zina = $post->post_name;
	return "\n#Zina\n<IfModule mod_rewrite.c>\n".
		"RewriteEngine On\n".
		"#RewriteBase $home_root\n".
		"RewriteCond %{REQUEST_FILENAME} !-f\n".
		"RewriteCond %{REQUEST_FILENAME} !-d\n".
		"RewriteCond %{THE_REQUEST} {$home_root}{$zina}/([^?]+)(\?.|\ .)\n".
		"RewriteCond %{REQUEST_URI} !{$home_root}{$zina}$\n".
		"RewriteRule .   {$home_root}{$zina}?p=%1   [QSA,L]\n".
		"</IfModule>\n\n".$rules;
}

function zinawp_menus_admin() {
	add_options_page('Zina', 'Zina', 'administrator', __FILE__, 'zinawp_admin');
	add_submenu_page('themes.php', 'Zina Layout', 'Zina Layout', 'manage_options', __FILE__, 'zinawp_layout_opts');
}

function zinawp_layout_opts() {
	if (isset($_POST['submit']) && current_user_can('manage_options')) {
		if (isset($_POST['zina_sidebar']))
			update_option('zina_sidebar', 1);
		else
			update_option('zina_sidebar', 0);

		echo '<div id="message" class="updated fade"><p><strong>'.__('Options saved.').'</strong></p></div>';
	}

	$zina_sidebar = (bool) get_option('zina_sidebar');

	echo '<div class="wrap"><h2>'.__('Zina Layout Options').'</h2>'.
		'<form action="" method="post" id="zina-conf">'.
		'<p><label><input name="zina_sidebar" id="zina_sidebar" value="1" type="checkbox" '.zinawp_checked(true, $zina_sidebar).'/> '. __('Show wordpress theme sidebar.').'</label></p>'.
		'<p class="submit"><input type="submit" name="submit" value="'. __('Update options &raquo;').'" /></p>'.
		'</form></div>';
}

function zinawp_admin_init() {
	$page = 'zina/zina.php';
	if (strpos($_SERVER['HTTP_REFERER'], 'themes.php') === FALSE && $_GET['page'] == $page && current_user_can('manage_options')) {
		$options = get_option('zina_options');
		if (!headers_sent()) {
			$redirect = get_option('home').'?page_id='.$options['page_id'].'&l=20';
			@ob_end_clean();
			wp_redirect($redirect);
			exit;
		}
	}
}

function zinawp_logout() {
	global $user_ID;
	delete_usermeta($user_ID, 'zina_session_id');
}

function zinawp_profile_update() {
	global $wpdb, $user_ID;
	get_currentuserinfo();
	if ($_GET['user_id'] ) $user_ID = $_GET['user_id'];
	update_usermeta($user_ID ,'zina_lastfm', (bool)$_POST['lastfm']);
	update_usermeta($user_ID ,'zina_lastfm_username', $wpdb->prepare($_POST['lastfm_username']));
	update_usermeta($user_ID ,'zina_lastfm_password', $wpdb->prepare($_POST['lastfm_password']));
	update_usermeta($user_ID ,'zina_twitter', (bool)$_POST['twitter']);
	update_usermeta($user_ID ,'zina_twitter_username', $wpdb->prepare($_POST['twitter_username']));
	update_usermeta($user_ID ,'zina_twitter_password', $wpdb->prepare($_POST['twitter_password']));
}

function zinawp_checked( $checked, $current) {
	if ($checked === $current) return ' checked="checked"';
}

function zinawp_select($select, $current) {
	return ($select === $current) ? ' selected="selected"' : '';
}

function zinawp_profile() {
	global $user_ID;
	get_currentuserinfo();
	if( $_GET['user_id'] ) $user_ID = $_GET['user_id'];

	$lastfm = (bool) get_usermeta($user_ID, 'zina_lastfm');
	$twitter = (bool) get_usermeta($user_ID, 'zina_twitter');

	echo '<h3>' . __('Zina Last.fm') . '</h3><table class="form-table"><tbody>'.
		'<tr><th><label for="lastfm">'.__('Last.fm').'</label></th>'.
		'<td><input type="radio" name="lastfm" id="lastfm" value="1" '.zinawp_checked(true, $lastfm).'> True '.
		'<input type="radio" name="lastfm" id="lastfm" value="0" '.zinawp_checked(false, $lastfm).'> False '.
		' (Enable last.fm nowplaying and logging)</td></tr>'.
		'<tr><th><label for="lastfm_username">'.__('Last.fm username').'</label></th>'.
		'<td><input type="text" name="lastfm_username" id="lastfm_username" value="'.get_usermeta($user_ID, 'zina_lastfm_username').'"></td></tr>'.
		'<tr><th><label for="lastfm_password">'.__('Last.fm password').'</label></th>'.
		'<td><input type="password" name="lastfm_password" id="lastfm_password" value="'.get_usermeta($user_ID, 'zina_lastfm_password').'"></td></tr>'.
		'</tbody></table>';

	echo '<h3>' . __('Zina Twitter.com') . '</h3><table class="form-table"><tbody>'.
		'<tr><th><label for="twitter">'.__('Last.fm').'</label></th>'.
		'<td><input type="radio" name="twitter" id="twitter" value="1" '.zinawp_checked(true, $twitter).'> True '.
		'<input type="radio" name="twitter" id="twitter" value="0" '.zinawp_checked(false, $twitter).'> False '.
		' (Enable twitter.com updating)</td></tr>'.
		'<tr><th><label for="twitter_username">'.__('Twitter username').'</label></th>'.
		'<td><input type="text" name="twitter_username" id="twitter_username" value="'.get_usermeta($user_ID, 'zina_twitter_username').'"></td></tr>'.
		'<tr><th><label for="twitter_password">'.__('Twitter password').'</label></th>'.
		'<td><input type="password" name="twitter_password" id="twitter_password" value="'.get_usermeta($user_ID, 'zina_twitter_password').'"></td></tr>'.
		'</tbody></table>';
}

function zinawp_admin($seed = null) {
	# should only get here if PHP buffering is off
	if (current_user_can('manage_options')) {
		$options = get_option('zina_options');
		echo '<p>Warning: PHP Output buffering appears to be off.<br/><br/></p>';
		echo '<p><a href="'.get_option('home').'?page_id='.$options['page_id'].'&l=20'.'">Click Here</a></p>';
	}
}

function zinawp_title($title, $sep = ' &laquo; ', $seploc = 'right', $addition = null) {
	static $zina_title = '';
	if (!empty($addition)) $zina_title = $addition;

	if (!empty($title) && !empty($zina_title)) {
		if ($seploc == 'right') {
			if (($pos = strpos($title, $sep)) !== false) {
				$title = substr_replace($title, $zina_title, 0, $pos);
			}
		} else {
			if (($pos = strpos($title, $sep)) !== false) {
				$title = $sep.substr_replace($title, $zina_title, $pos);
			}
		}
	}	
	return $title;
}

function zinawp_header($text = null) {
	static $header = '';
	if (empty($text)) echo $header;
	$header .= $text;
}

function zinawp_zina() {
	global $zc, $user_ID, $id;
	@session_start(); #no session in WP???
	if (!zinawp_is_zina()) return;

	require_once('zina/index.php');
	$conf['time'] = microtime(true);
	$uid = 0;
	$post = get_post($id);

	if (empty($user_ID)) { # OR NOT ZINA ACCESS (if possible in wp?)
		$access = false;
		if (isset($_GET['zid']) && isset($_GET['l']) && ($_GET['l'] == '6' || $_GET['l'] == '10')) {
			if ($uid = zina_token('verify',$_GET['zid'])) {
				if (get_usermeta($uid, 'zina_session_id')) {
					$access = true;
				}
			}
		 	if (!$access) { zina_access_denied(); return; }
		}
		if (post_password_required($post)) {
			get_the_password_form();
			return;
		}
	} else {

		if (post_password_required($post)) {
			get_the_password_form();
			return;
		}

		$uid = $user_ID;
		update_usermeta($uid, 'zina_session_id', session_id());
	}

	$conf['user_id'] = $uid;
	$conf['is_admin'] = current_user_can('manage_options');
	$conf['embed'] = 'wordpress';
	$conf['index_abs'] = dirname(__FILE__);
	$conf['site_name'] = get_option('blogname');

	zinawp_set_conf($conf);

	$lastfm = (bool) get_usermeta($user_ID, 'zina_lastfm');
	$twitter = (bool) get_usermeta($user_ID, 'zina_twitter');

	if ($user_ID > 0) {
	  if ($lastfm) {
		 $conf['lastfm'] = true;
		 $conf['lastfm_username'] = get_usermeta($user_ID, 'zina_lastfm_username');
		 $conf['lastfm_password'] = get_usermeta($user_ID, 'zina_lastfm_password');
	  }
	  if ($twitter) {
		 $conf['twitter'] = true;
		 $conf['twitter_username'] = get_usermeta($user_ID, 'zina_twitter_username');
		 $conf['twitter_password'] = get_usermeta($user_ID, 'zina_twitter_password');
	  }
	}
	if (isset($_GET['p'])) $_GET['p'] = stripslashes($_GET['p']);
	$zina = zina($conf);

	zinawp_title(null, null, null, $zina['title']);
	
	if (!isset($zina['breadcrumb']) || !is_array($zina['breadcrumb'])) $zina['breadcrumb'] = array();
	array_unshift($zina['breadcrumb'], ztheme('wp_home', get_option('home')));

	zinawp_header($zina['head_html']);
	zinawp_header($zina['head_css']);
	zinawp_header($zina['head_js']);
	$sidebar = get_option('zina_sidebar');

	get_header();
	if ($zina) echo ztheme('wp_page_complete', ztheme('page_complete', $zina), $sidebar);
	if ($sidebar) get_sidebar();
	get_footer();
	exit;
}

function zinawp_is_zina() {
	global $wp_rewrite;
	static $zina;
	if (!isset($zina)) {
		$page_id = ($wp_rewrite->wp_rewrite_rules()) ? url_to_postid($_SERVER['REQUEST_URI']) : $_GET['page_id'];
		$options = get_option('zina_options');
		$zina = ($page_id == $options['page_id']);
	}
	return $zina;
}

function zinawp_set_conf(&$conf) {
	$clean_urls = get_option('permalink_structure');
	$options = get_option('zina_options');
	$post = get_post($options['page_id']);

	if (!empty($clean_urls)) {
		$conf['clean_urls_hack'] = false;
		$conf['clean_urls'] = true;
		$conf['index_rel'] = (!empty($post->post_name)) ? $post->post_name : 'zina';
	} else {
		$conf['clean_urls'] = false;
		$conf['url_query'][] = 'page_id='.$options['page_id'];
	}
}

function zina_access_denied() {
	header('HTTP/1.1 403 Forbidden');
	if (!function_exists('zt')) {
		echo __('Access Denied');
		exit;
	}
	return zina_page_simple(zt('Access denied.'), zt('You are not authorized to access this page.'));
}

function zina_not_found() {
	header('HTTP/1.1 404 Not Found');
	if (!function_exists('zt')) {
		echo __('File not found.');
		exit;
	}
	return zina_page_simple(zt('Page not found.'), zt('The requested page could not be found.'));
}

function zinawp_pluginactivate() {
	global $user_ID, $wp_rewrite, $wp_roles, $z_dbc, $zc;

	$options['page_id'] = wp_insert_post(array(
		'post_author'		=> $user_ID,
		'post_title'		=> 'Zina',
		'post_name'		 	=> 'Zina',
		'post_status'		=> "publish",
		'post_type'			=> "page",
		'comment_status'	=> "closed",
		'ping_status'		=> " ",
		'post_content'		=> "Zina Internal Page/<br /><br />..."
	));

	if (empty($options['page_id'])) {
 		return new WP_Error('zina_not_insert', __('Zina could not insert its default page!'));
	}

	update_option('zina_options', $options);
	#TODO: this might not be good, just include cron.php?
	wp_schedule_event(time(), 'daily', 'zinawp_cron');
	update_option('zina_sidebar', 1);

	require_once('zina/index.php');
	if ($z_dbc = zina_get_active_db($db_opts)) {
		if (!in_array($db_opts['type'], zina_get_db_types())) return;
		$zc['db_type'] = $db_opts['type'];
		$zc['database'] = true;
		$zc['debug'] = false;
		$zc['db_pre'] = $db_opts['prefix'].'zina_';

		#TODO: test
		require_once('zina/common.php');
		require_once('zina/database.php');
		require_once('zina/database-'.$zc['db_type'].'.php');
		if (!zdbq('SELECT 1 FROM {dirs} LIMIT 0')) {
			require_once('zina/install.php');
			zina_install_database();
		} else {
			require_once('zina/update.php');
			zina_updates_execute();
		}
	}

	$roles = $wp_roles->get_names();
	foreach ($roles as $role => $name) {
		$wp_roles->add_cap($role, 'zina_edit_playlists');
		$wp_roles->add_cap($role, 'zina_editor');
	}

	$wp_rewrite->flush_rules();
}

function zinawp_plugindeactivate() {
	#TODO: 
	if ($_GET['action'] != 'deactivate-all') {
		$options = get_option('zina_options');
		if (!empty($options['page_id'])) wp_delete_post($options['page_id']);

		delete_option('zina_options');
		wp_clear_scheduled_hook('zinawp_cron');
		# Test:
		remove_filter('rewrite_rules_array', 'zinawp_rewrite_rules');
		remove_filter('mod_rewrite_rules', 'zinawp_modrewrite');
		global $wp_rewrite, $wpdb;
		$wp_rewrite->flush_rules();
		foreach(array('zina_session_id') as $key) {
			$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE meta_key = %s", $key) );
		}
	}
}
# NOT USED... called on Upgrade?  
function zinawp_uninstall() {
	#TODO: what else have we left around?
	global $wpdb, $zc;
	foreach(array('zina_lastfm', 'zina_lastfm_username', 'zina_lastfm_password') as $key) {
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE meta_key = %s", $key) );
	}
	foreach(array('zina_twitter', 'zina_twitter_username', 'zina_twitter_password') as $key) {
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE meta_key = %s", $key) );
	}
  
	$conf['time'] = microtime(true);
	$conf['embed'] = 'drupal';
	$conf['index_abs'] = dirname(__FILE__);
	require_once('zina/index.php');
	zina_init($conf);

	if ($zc['database']) zdb_uninstall_database();

	for($i=1; $i<=5; $i++) {
		delete_option('zina_block_'.$i.'_type');
		delete_option('zina_block_'.$i.'_period');
		delete_option('zina_block_'.$i.'_page');
		delete_option('zina_block_'.$i.'_items');
	}
}

function zina_token($type, $value) {
	$sitekey = get_option('secret');

	$sep = '|';
	if ($type == 'get') {
		return $value.$sep.md5($value.$sitekey);
	} elseif ($type == 'verify') {
		$x = explode($sep, $value);
		if (md5($x[0].$sitekey) === $x[1]) {
			return $x[0];
		} else {
			return false;
		}
	}

	return false;
}

function ztheme_wp_page_complete($content, $sidebar = false) {
	if ($sidebar)
		return '<div id="content" class="narrowcolumn">'.$content.'</div>';
	else
		return '<div style="margin: 0 20px;">'.$content.'</div>';
}

function ztheme_wp_home($url) {
	return '<a href="'.$url.'">'.zt('Home').'</a>';
}

function zinawp_widgets_init() {
	#echo 'WIDGET INIT';
	#TODO: make 5 configable
	for($i=1;$i<=5;$i++) {
		register_sidebar_widget('Zina Block '.$i, 'zinawp_widget_display'.$i,'');
		register_widget_control('Zina Block '.$i, 'zinawp_widget_control'.$i, null, 75, 'ZINA'.$i);
	}

	register_sidebar_widget('Zina Flash Player', 'zinawp_zinamp');
	#if (is_active_widget('zinawp_zinamp') !== false && !zinawp_is_zina()) {
		#wp_enqueue_script('jquery');
	#}
}
function zinawp_widget_display1($args) { $args['delta'] = 1; zinawp_widget_display($args); }
function zinawp_widget_display2($args) { $args['delta'] = 2; zinawp_widget_display($args); }
function zinawp_widget_display3($args) { $args['delta'] = 3; zinawp_widget_display($args); }
function zinawp_widget_display4($args) { $args['delta'] = 4; zinawp_widget_display($args); }
function zinawp_widget_display5($args) { $args['delta'] = 5; zinawp_widget_display($args); }

function zinawp_widget_control1() { zinawp_widget_control(1); }
function zinawp_widget_control2() { zinawp_widget_control(2); }
function zinawp_widget_control3() { zinawp_widget_control(3); }
function zinawp_widget_control4() { zinawp_widget_control(4); }
function zinawp_widget_control5() { zinawp_widget_control(5); }

function zinawp_widget_display($args) {
	extract($args);

	if (!function_exists('zina_init')) {
		$conf['time'] = microtime(true);
		$conf['embed'] = 'wordpress';
		$conf['index_abs'] = dirname(__FILE__);
		zinawp_set_conf($conf);
   	require_once('zina/index.php');
   	zina_init($conf);
  	}

	$options = get_option('zina_block_'.$delta.'_options');
	$items = zina_get_block_stat($options['type'], $options['page'], $options['period'], $options['number'], $options['id3'], $options['images']);

	echo ztheme('wordpress_block', $items, $options, $before_widget, $after_widget, $before_title, $after_title);
}

function ztheme_wordpress_block($items, $options, $before_widget, $after_widget, $before_title, $after_title) {
	$output = $before_widget.$before_title.$options['title'].$after_title.'<div>';

	if (!empty($items)) {
		if ($options['images']) {
			$output .= '<style type="text/css">div.zina-stats-block{float:left;}'.
				'.zina-stats-block p{margin:0;padding:0;}'.
				'.zina-stats-block img{float:left;padding-right:5px;padding-bottom:5px;}</style>';
			$output .= '<div class="zina-stats-block"><table>';
			foreach($items as $item) {
				$output .= '<tr><td valign="top">'.
					'<a href="'.$item['image_url'].'">'.$item['image'].'</a></td>'.
					'<td valign="top"><p>'.$item['display'].'</p></td>'.
					'</tr>';
			}
			$output .= '</table></div>';
		} else {
			$output .= '<ul class="item-list">';
			foreach($items as $item) {
				$output .= '<li>'.$item['display'].'</li>';
			}
			$output .= '</ul>';
		}
	} else {
		$output .= zt('No results');
	}

	$output .= '</div>'.$after_widget;
	return $output;
}

function zinawp_widget_control($delta) {
	if (!function_exists('zina_init')) {
		$conf['time'] = microtime(true);
		$conf['embed'] = 'wordpress';
		$conf['index_abs'] = dirname(__FILE__);

		zinawp_set_conf($conf);
   	require_once('zina/index.php');
   	zina_init($conf);
  	}

	$options = get_option('zina_block_'.$delta.'_options');
	if (!is_array($options)) {
		#set defaults
		$options = array(
			'title'=>'Highest Rated Songs',
			'type'=>'song',
			'page'=>'rating',
			'period'=>'all',
			'number'=>10,
			'id3'=>false,
			'images'=>false,
		);
	}
	if ($_POST['zinawp_block_'.$delta.'_submit']) {
		$options['title'] = strip_tags(stripslashes($_POST['zinawp-title-'.$delta]));
		$options['page'] = $_POST['zinawp-page-'.$delta];
		$options['type'] = $_POST['zinawp-type-'.$delta];
		$options['period'] = $_POST['zinawp-period-'.$delta];
		$options['number'] = $_POST['zinawp-number-'.$delta];
		$options['id3'] = $_POST['zinawp-id3-'.$delta];
		$options['images'] = $_POST['zinawp-images-'.$delta];

		update_option('zina_block_'.$delta.'_options', $options);
	}
	$title = htmlspecialchars($options['title'], ENT_QUOTES);
	$type = $options['type'];
	$period = $options['period'];
	$page = $options['page'];
	$number = (int) $options['number'];
	$id3 = (bool) $options['id3'];
	$images = (bool) $options['images'];

	$output = '';
	$output .= '<p><label for="zinawp-title-'.$delta.'">'. zt('Title').
		': <input style="width: 250px;" id="zinawp-title-'.$delta.'" name="zinawp-title-'.$delta.'" type="text" value="'.$title.'" /></label></p>';

	$types = zina_get_stats_types();
	$output .= '<p><label for="zinawp-type-'.$delta.'">'. zt('Type').
		': <select id="zinawp-type-'.$delta.'" name="zinawp-type-'.$delta.'" class="widefat">';
	foreach($types as $key=>$val) {
		$output .= '<option value="'.$key.'"'.zinawp_select($options['type'], $key).'>'.zt($val).'</option>';
	}
	$output .= '</select></label></p>';

	$pages = zina_get_stats_pages();
	$output .= '<p><label for="zinawp-page-'.$delta.'">'.zt('Statistic').
		': <select id="zinawp-page-'.$delta.'" name="zinawp-page-'.$delta.'" class="widefat">';
	foreach($pages as $key=>$val) {
		$output .= '<option value="'.$key.'"'.zinawp_select($options['page'], $key).'>'.zt($val).'</option>';
	}
	$output .= '</select></label></p>';

	$periods = zina_get_stats_periods();
	$output .= '<p><label for="zinawp-period-'.$delta.'">'. zt('Period').
		': <select id="zinawp-period-'.$delta.'" name="zinawp-period-'.$delta.'" class="widefat">';
	foreach($periods as $key=>$val) {
		$output .= '<option value="'.$key.'"'.zinawp_select($options['period'], $key).'>'.zt($val).'</option>';
	}
	$output .= '</select></label></p>';

	$output .= '<p><label for="zinawp-number-'.$delta.'">'. zt('Number').
		': <input style="width:50px;" id="zinawp-number-'.$delta.'" name="zinawp-number-'.$delta.'" type="text" value="'.$number.'" /></label></p>';

	$output .= '<p><label for="zinawp-id3-'.$delta.'">'. zt('Use ID3 tags (if applicable)').
		': <input type="radio" id="zinawp-id3-'.$delta.'" name="zinawp-id3-'.$delta.'" value="1" '.zinawp_checked(true,$id3).'/> '.zt('True').
		' <input type="radio" id="zinawp-id3-'.$delta.'" name="zinawp-id3-'.$delta.'" value="0" '.zinawp_checked(false,$id3).'/> '.zt('False').
		'</label></p>';

	$output .= '<p><label for="zinawp-images-'.$delta.'">'. zt('Show images').
		': <input type="radio" id="zinawp-images-'.$delta.'" name="zinawp-images-'.$delta.'" value="1" '.zinawp_checked(true,$images).'/> '.zt('True').
		' <input type="radio" id="zinawp-images-'.$delta.'" name="zinawp-images-'.$delta.'" value="0" '.zinawp_checked(false,$images).'/> '.zt('False').
		'</label></p>';

	$output .= '<input type="hidden" id="zinawp_block_'.$delta.'_submit" name="zinawp_block_'.$delta.'_submit" value="1" />';
	echo $output;
}


function zinawp_zinamp() {
	if (zinawp_is_zina()) return;

	if (!function_exists('zina_init')) {
		$conf['time'] = microtime(true);
		$conf['embed'] = 'wordpress';
		$conf['index_abs'] = dirname(__FILE__);
		zinawp_set_conf($conf);
   	require_once('zina/index.php');
   	zina_init($conf);
	}
	zina_set_js('file', 'extras/jquery.js');
	$output = ztheme('zinamp_embed');
	echo zina_get_js();		
	echo $output;
}

function zinawp_trimpermalinkrules() {
	global $wp_rewrite;

	// Verify WP Permalink Rules do not have trailing slashes
	$permalink_structure = get_option('permalink_structure');
	$category_base = get_option('category_base');

	if (!empty($permalink_structure) || !empty($category_base) ) {
		if (!empty($permalink_structure)) {
			$permalink_structure = rtrim ($permalink_structure, "/");
			$wp_rewrite->set_permalink_structure($permalink_structure);
		}
	if (!empty($category_base)) {
		$category_base = rtrim ($category_base, "/");
		$wp_rewrite->set_category_base($category_base);
		}
	}
}

function zina_get_active_db(&$opts) {
	global $wpdb;
	#TODO: verify mysql
	$opts['prefix'] = $wpdb->prefix;
	$opts['type'] = 'mysql';
	return $wpdb->dbh;

}

function zina_cms_access($type, $type_user_id = null) {
	global $zc, $user_ID;

	if ($zc['is_admin']) return true;

	$user = new WP_User($user_ID);

	switch ($type) {
		case 'edit_playlists':
			return ($zc['pls_user'] && $user->has_cap('zina_edit_playlists') && $user_ID == $type_user_id);
		case 'editor':
			return ($zc['cms_editor'] && ($user->has_cap('zina_editor') || $user->has_cap('editor')));
	}
	return false;
}

function zina_cms_user($user_id = false) {
	global $user_ID;
	static $users;
	
	$uid = ($user_id !== false) ? $user_id : $user_ID;

	if (isset($users[$uid])) return $users[$uid];

	$user_local = new WP_User($uid);

	if ($uid == 0) {
		$users[$uid] = false;
	} else {
		$users[$uid] = array(
			'uid' => $user_local->ID,
			'name' => $user_local->display_name,
			'profile_url' => false,
			#'profile_url' => get_author_posts_url($user_local->ID, $user_local->user_nicename)
		);
	}
	return $users[$uid];
}

function zina_cms_info() {
	global $wp_version;
	return array(
		'version' => $wp_version,
		'modules' => get_option('active_plugins'),
	);
}
?>
