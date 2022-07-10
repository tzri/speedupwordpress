<?php
/*
Plugin Name: Speed Up WordPress
Description: A nifty little plugin to speed up your site's page and file loading times.
Version: 1.0.8
Author: Moki-Moki Ios
Author URI: https://github.com/tzri/
License: GPL3
*/

/*
Copyright (C) 2017 Moki-Moki Ios https://github.com/tzri/

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Speed Up WordPress
 *
 * @version 1.0.8
 */

if (!defined('ABSPATH')) return;

require_once(__DIR__.'/htaccess-module.php');
require_once(__DIR__.'/libraries/tiny-html-minifier.php');

add_action('init', array(SpeedUpWordPress::get_instance(), 'initialize'));
add_action('admin_notices', array(SpeedUpWordPress::get_instance(), 'plugin_activation_notice'));
register_activation_hook(__FILE__, array(SpeedUpWordPress::get_instance(), 'setup_plugin_on_activation')); 

/**
 * Main class of the plugin.
 */
class SpeedUpWordPress {
	
	const PLUGIN_NAME = "Speed Up WordPress";
	const CONFIG_SECTION_GZIP = "Speed Up WordPress Gzip";
	const CONFIG_SECTION_EXPIRE = "Speed Up WordPress Expire";
	const CONFIG_SECTION_HOTLINKS = "Speed Up WordPress Hotlinks";
	const ADMIN_SETTINGS_URL = 'options-general.php?page=speed-up-wordpress';
	const VERSION = '1.0.8';
	const OPTION_ON = 'on';
	const OPTION_OFF = 'off';
	const STATUS_OK = 'ok';
	const STATUS_ERROR = 'error';
	
	private static $instance;
	private static $htaccess;
	
	private function __construct() {}
		
	public static function get_instance() {
		if (!isset(self::$instance)) {
			self::$instance = new self();
			self::$htaccess = new SpeedUpWordPressHtaccessModule();
		}
		return self::$instance;
	}
	
	public function initialize() {
		add_action('admin_menu', array($this, 'create_options_menu'));
		add_action('admin_enqueue_scripts', array($this, 'add_admin_style'));
		add_action('wp_enqueue_scripts', array($this, 'add_scripts'));
		add_action('wp_ajax_speedup_toggle_gzip', array(self::$htaccess, 'toggle_gzip_compression'));
		add_action('wp_ajax_speedup_toggle_expire', array(self::$htaccess, 'toggle_expire_headers'));
		add_action('wp_ajax_speedup_toggle_hotlinks', array(self::$htaccess, 'toggle_hotlink_prevention'));
		add_action('wp_ajax_speedup_toggle_pingbacks', array($this, 'toggle_pingbacks'));
		add_action('wp_ajax_speedup_toggle_emojis', array($this, 'toggle_emojis'));
		add_action('wp_ajax_speedup_toggle_image_lazyload', array($this, 'toggle_image_lazyload'));
		add_action('wp_ajax_speedup_toggle_html_minify', array($this, 'toggle_html_minify'));
		
		$lazy_loading_enabled = get_option('speedup_image_lazy_loading') === self::OPTION_ON;
		if ($lazy_loading_enabled) {
			add_filter('the_content', array($this, 'convert_images_to_lazy_load'));
			add_filter('max_srcset_image_width', array($this, 'disable_srcset_on_images'));
		}
		
		$disable_emojis = get_option('speedup_disable_emojis') === self::OPTION_ON;
		if ($disable_emojis) {
			remove_action('wp_head', 'print_emoji_detection_script', 7);
			remove_action('admin_print_scripts', 'print_emoji_detection_script');
			remove_action('wp_print_styles', 'print_emoji_styles');
			remove_action('admin_print_styles', 'print_emoji_styles'); 
			remove_filter('the_content_feed', 'wp_staticize_emoji');
			remove_filter('comment_text_rss', 'wp_staticize_emoji'); 
			remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
		}
		
		$minify_html_enabled = get_option('speedup_minify_html') === self::OPTION_ON;
		if ($minify_html_enabled) {
			$this->start_minify_html();
			remove_action('shutdown', 'wp_ob_end_flush_all', 1);
			add_action('shutdown', array($this, 'end_minify_html'), -1);
		}
	}
	
	public function create_options_menu() {
		add_submenu_page(
			'options-general.php',
			'Speed Up WordPress',
			'Speed Up WordPress',
			'manage_options',
			'speed-up-wordpress',
			array($this, 'print_settings_page')
		);
	}
	
	public function setup_plugin_on_activation() {		
		set_transient('speedup_activation_notice', TRUE, 5);
		add_action('admin_notices', array($this, 'plugin_activation_notice'));
	}
	
	public function plugin_activation_notice() {
		if (get_transient('speedup_activation_notice')) {
			$settings_url = $settings_url = get_admin_url() . SpeedUpWordPress::ADMIN_SETTINGS_URL;
			echo '<div class="notice updated"><p><strong>'.sprintf(__('Speed Up WordPress plugin is activated. Please set it up at <a href="%s">settings page</a>.'), $settings_url).'</strong></p></div>';	
		}		
	}
	
	public function print_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}		
		?>
		
		<h1><?php _e('Speed Up WordPress'); ?></h1>
											
		<?php $this->print_notifications(); ?>	
		
		<div class="speedup-settings">
			
			<?php
				$compression_enabled = get_option('speedup_gzip_compression') === self::OPTION_ON;
				$expire_headers_enabled = get_option('speedup_expire_headers') === self::OPTION_ON;
				$hotlink_block_enabled = get_option('speedup_block_hotlinks') === self::OPTION_ON;
				$pingbacks_denied = get_option('default_ping_status') === 'closed';
				$disable_emojis = get_option('speedup_disable_emojis') === self::OPTION_ON;
				$lazy_loading_enabled = get_option('speedup_image_lazy_loading') === self::OPTION_ON;
				$html_minimifying_enabled = get_option('speedup_minify_html') === self::OPTION_ON;
			?>			
			
			<h2><span style="color: blue" class="dashicons dashicons-admin-users"></span> <?php _e('Control Panel'); ?></h2>
			
			<p><?php _e('It is recommended to enable all features.'); ?></p>

			<table>
				<tr class="header">
					<th class="first-header"><?php _e('Feature'); ?></th>
					<th><?php _e('Description'); ?></th>
					<th><?php _e('Status'); ?></th>
					<th class="last-header"><?php _e('Action'); ?></th>
				</tr>
				<tr>
				<td class="title"><?php _e('Gzip Compression'); ?></td>
				<td><?php _e('Enables compression for files delivered by your web server and thus speeds up download times.'); ?></td>
				<td><?php if ($compression_enabled) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_gzip"/>
						<?php if (!$compression_enabled) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>
				</tr>
				
				<tr>
				<td class="title"><?php _e('Expire Headers'); ?></td>
				<td><?php _e('Sets expiration headers for files so they are not downloaded multiple times when surfing on your site. Speeds up download times.'); ?></td>				
				<td><?php if ($expire_headers_enabled) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_expire"/>
						<?php if (!$expire_headers_enabled) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>				
				</tr>
				
				<tr>
				<td class="title"><?php _e('Block Hotlinking'); ?></td>
				<td><?php _e('Denies third party websites from showing up images from your site. Stops wasting of server resources and thus speeds your site up. Google image search allowed.'); ?></td>				
				<td><?php if ($hotlink_block_enabled) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_hotlinks"/>
						<?php if (!$hotlink_block_enabled) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>				
				</tr>
				
				<tr>
				<td class="title"><?php _e('Deny Pingbacks, Trackbacks'); ?></td>
				<td><?php _e('These are used to alert when a page receives an incoming link. This can strain your server resources. Use tools such as Google Webmaster Tools instead. <strong>Activate this setting</strong> to improve performance.'); ?></td>				
				<td><?php if ($pingbacks_denied) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_pingbacks"/>
						<?php if (!$pingbacks_denied) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>				
				</tr>
				
				<tr>
				<td class="title"><?php _e('Disable Emojis'); ?></td>
				<td><?php _e('By default tiny emoji icons are loaded during the fetch of each page. Enable this feature to stop emoji JavaScript file from loading to improve performance.'); ?></td>				
				<td><?php if ($disable_emojis) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_emojis"/>
						<?php if (!$disable_emojis) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>				
				</tr>		

				<tr>
				<td class="title"><?php _e('Lazy Load Images'); ?></td>
				<td><?php _e('Load each image in post content only just before it becomes visible for user. This decreases traffic and speeds up page loading time.'); ?></td>				
				<td><?php if ($lazy_loading_enabled) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_image_lazyload"/>
						<?php if (!$lazy_loading_enabled) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>				
				</tr>		

				<!--<tr>
				<td class="title"><?php _e('Minify HTML'); ?></td>
				<td><?php _e('Remove unnecessary content such as comments and whitespace from HTML ouput. Makes your pages smaller reduceing download time.'); ?></td>				
				<td><?php if ($html_minimifying_enabled) _e('<span class="enabled">On</span>'); else _e('<span class="disabled">Off</span>'); ?></td>
				<td>
					<form style="display: inline;" action="admin-ajax.php" method="post">
						<input type="hidden" name="action" value="speedup_toggle_html_minify"/>
						<?php if (!$html_minimifying_enabled) : ?>				
						<input type="submit" name="submit" value="<?php _e('Enable'); ?>"/>
						<?php else : ?>
						<input type="submit" name="submit" value="<?php _e('Disable'); ?>"/>
						<?php endif; ?>
					</form>					
				</td>				
				</tr>-->		
			</table>
			
			<h2><span style="color: green" class="dashicons dashicons-thumbs-up"></span> <?php _e('Support and Updates'); ?></h2>
			
			<p><?php _e('Thank you for using <strong>Speed Up WordPress</strong> plugin. Please stay tuned for updates at <a target="_blank" href="https://creativemarket.com/mokimoki/">Creative Market</a>.'); ?></p>
			
		</div>
		<?php
			delete_option('speedup_htaccess_save');
			delete_option('speedup_gzip_test_result');
			delete_option('speedup_expire_headers_changed');
			delete_option('speedup_block_hotlinks_changed');
		?>
		<?php
	}
	
	private function print_notifications() {
		echo '<div class="speedup-notifications">';
		if (get_option('speedup_htaccess_save', FALSE) === self::STATUS_ERROR) : ?>
		<div class="notice error">
			<p><strong><?php _e('Could not not update .htaccess file. Please check that the file is writable.'); ?></strong></p>
		</div>
		<?php endif; ?>
		
		<?php if (get_option('speedup_gzip_test_result', FALSE) === self::STATUS_ERROR) : ?>			
			<div class="notice error">
				<p><strong><?php _e('Gzip compression seems not to be working. Perhaps you need to activate mod_deflate or mod_gzip module.'); ?></strong></p>
			</div>			
		<?php endif; ?>
		
		<?php 
			$htaccess_saved = get_option('speedup_htaccess_save', FALSE) === self::STATUS_OK;
			if (!$htaccess_saved) {
				echo '</div> <!-- notifications -->';
				return;
			}
			$gzip_working = get_option('speedup_gzip_test_result', FALSE) === self::STATUS_OK;
			
			if ($gzip_working) : ?>
			<div class="notice updated">
				<?php if (get_option('speedup_gzip_compression') == self::OPTION_ON) : ?>
				<p><strong><?php _e('Gzip compression is now enabled and working.'); ?></strong></p>
				<?php else : ?>
				<p><strong><?php _e('Gzip compression is now disabled.'); ?></strong></p>
				<?php endif; ?>
			</div>
			<?php endif;
			
			$expire_headers_enabled = get_option('speedup_expire_headers', FALSE) === self::OPTION_ON;
			$expire_headers_changed = get_option('speedup_expire_headers_changed', FALSE) === self::STATUS_OK;
			
			if ($expire_headers_changed) : ?>
			<div class="notice updated">
				<?php if ($expire_headers_enabled) : ?>
				<p><strong><?php _e('File expire headers successfully set.'); ?></strong></p>
				<?php else : ?>
				<p><strong><?php _e('File expire headers removed.'); ?></strong></p>
				<?php endif; ?>
			</div>				
			<?php endif;
			
			$hotlink_block_enabled = get_option('speedup_block_hotlinks', FALSE) === self::OPTION_ON;
			$hotlink_block_changed = get_option('speedup_block_hotlinks_changed', FALSE) === self::STATUS_OK;
			
			if ($hotlink_block_changed) : ?>
			<div class="notice updated">
				<?php if ($hotlink_block_enabled) : ?>
				<p><strong><?php _e('Hotlink blocking now up and running.'); ?></strong></p>
				<?php else : ?>
				<p><strong><?php _e('Hotlink blocking disabled.'); ?></strong></p>
				<?php endif; ?>
			</div>						
			<?php endif;	
			
			echo '</div> <!-- notifications -->';
		}
		
	public function add_admin_style() {
		wp_register_style('speed_up_wordpress_admin_style', plugin_dir_url(__FILE__) . 'admin.css');
		wp_enqueue_style('speed_up_wordpress_admin_style');
	}
	
	public function add_scripts() {
		wp_register_script('speedup_lazyload', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.lazy/1.7.9/jquery.lazy.min.js', NULL, NULL, FALSE);
		wp_register_script('speedup_initpage', plugin_dir_url(__FILE__) . 'speedup-init.js', 'speedup_lazyload', NULL, FALSE);
		wp_enqueue_script('speedup_lazyload');
		wp_enqueue_script('speedup_initpage');
	}
	
	public function toggle_pingbacks() {
		$pingbacks_enabled = get_option('default_ping_status') === 'open';
		
		if ($pingbacks_enabled) {
			update_option('default_ping_status', 'closed');
		}
		else {
			update_option('default_ping_status', 'open');
		}
		
		$this->redirect_to_settings_page();
	}
	
	public function toggle_emojis() {
		$disable_emojis = get_option('speedup_disable_emojis') === self::OPTION_ON;
		
		if ($disable_emojis) {
			update_option('speedup_disable_emojis', self::OPTION_OFF);	
		}
		else {
			update_option('speedup_disable_emojis', self::OPTION_ON);
		}
		
		$this->redirect_to_settings_page();
	}
	
	public function toggle_image_lazyload() {
		$lazy_loading_enabled = get_option('speedup_image_lazy_loading') === self::OPTION_ON;
		
		if ($lazy_loading_enabled) {
			update_option('speedup_image_lazy_loading', self::OPTION_OFF);	
		}
		else {
			update_option('speedup_image_lazy_loading', self::OPTION_ON);
		}
		
		$this->redirect_to_settings_page();
	}
	
	public function toggle_html_minify() {
		$html_minimifying_enabled = get_option('speedup_minify_html') === self::OPTION_ON;
		
		if ($html_minimifying_enabled) {
			update_option('speedup_minify_html', self::OPTION_OFF);	
		}
		else {
			update_option('speedup_minify_html', self::OPTION_ON);
		}
		
		$this->redirect_to_settings_page();
	}
	
	public function start_minify_html() {
		//ob_start();
	}
	
	function end_minify_html() {
		/*$original_html = '';
		$levels = ob_get_level();

		for ($level = 0; $level < $levels; $level++) {
			$original_html .= ob_get_clean();
		}
		
		$minified_html = TinyMinify::html($original_html);
		echo apply_filters('final_output', $minified_html);*/
	}	
	
	public function convert_images_to_lazy_load($content) {
		return str_replace('src=', 'data-src=', $content);
	}
	
	public function disable_srcset_on_images() {
		return 1;
	}
	
	private function redirect_to_settings_page() {
		header('Location: ' . get_admin_url() . self::ADMIN_SETTINGS_URL);
		exit();
	}
}
