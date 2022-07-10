<?php
/**
 * Module for htaccess related functionality.
 */

require_once(__DIR__.'/speed-up-wordpress.php');
 
if (!defined('ABSPATH')) return;

require_once(ABSPATH . 'wp-admin/includes/file.php');

class SpeedUpWordPressHtaccessModule {

	const HTACCESS_FILENAME = '.htaccess';

	public function toggle_gzip_compression() {
		$compression_enabled_initially = $this->is_section_filled_in_htaccess_file(
			SpeedUpWordPress::CONFIG_SECTION_GZIP
		);
		$result = FALSE;
		
		if ($compression_enabled_initially) {
			$result = $this->remove_gzip_compression_from_htaccess();
		}
		else {			
			$result = $this->add_gzip_compression_to_htaccess();
		}
				
		if ($result === FALSE) {
			update_option('speedup_htaccess_save', SpeedUpWordPress::STATUS_ERROR);
			$this->redirect_to_settings_page();
		}
		else {
			$working = $this->is_gzip_compression_working_test();
			
			if (!$compression_enabled_initially && !$working) {
				$this->remove_gzip_compression_from_htaccess();
				update_option('speedup_gzip_test_result', SpeedUpWordPress::STATUS_ERROR);
				$this->redirect_to_settings_page();
			} else {
				update_option('speedup_gzip_test_result', SpeedUpWordPress::STATUS_OK);
			}
			
			if ($compression_enabled_initially) {
				update_option('speedup_gzip_compression', SpeedUpWordPress::OPTION_OFF);				
			}
			else {
				update_option('speedup_gzip_compression', SpeedUpWordPress::OPTION_ON);
			}
			update_option('speedup_htaccess_save', SpeedUpWordPress::STATUS_OK);
		}
		
		$this->redirect_to_settings_page();
	}

	public function toggle_expire_headers() {
		$headers_enabled_initially = $this->is_section_filled_in_htaccess_file(
			SpeedUpWordPress::CONFIG_SECTION_EXPIRE
		);
		$result = FALSE;
		
		if ($headers_enabled_initially) {
			$result = $this->remove_file_expires_from_htaccess();
			if ($result) {
				update_option('speedup_expire_headers', SpeedUpWordPress::OPTION_OFF);
			}			
		} 
		else {
			$result = $this->add_file_expires_to_htaccess();
			if ($result) {
				update_option('speedup_expire_headers', SpeedUpWordPress::OPTION_ON);			
			}			
		}
		
		if ($result) {
			update_option('speedup_htaccess_save', SpeedUpWordPress::STATUS_OK);
			update_option('speedup_expire_headers_changed', SpeedUpWordPress::STATUS_OK);
		}
		else {
			update_option('speedup_htaccess_save', SpeedUpWordPress::STATUS_ERROR);
		}
		
		$this->redirect_to_settings_page();
	}
	
	public function toggle_hotlink_prevention() {
		$hotlink_block_enabled_initially = $this->is_section_filled_in_htaccess_file(
			SpeedUpWordPress::CONFIG_SECTION_HOTLINKS
		);
		$result = FALSE;
		
		if ($hotlink_block_enabled_initially) {
			$result = $this->remove_hotlink_block_from_htaccess();
			if ($result) {
				update_option('speedup_block_hotlinks', SpeedUpWordPress::OPTION_OFF);				
			}			
		} 
		else {
			$result = $this->add_hotlink_block_to_htaccess();
			if ($result) {
				update_option('speedup_block_hotlinks', SpeedUpWordPress::OPTION_ON);
			}			
		}
		
		if ($result) {
			update_option('speedup_htaccess_save', SpeedUpWordPress::STATUS_OK);
			update_option('speedup_block_hotlinks_changed', SpeedUpWordPress::STATUS_OK);
		}
		else {
			update_option('speedup_htaccess_save', SpeedUpWordPress::STATUS_ERROR);
		}
		
		$this->redirect_to_settings_page();
	}	
	
	/**
	 * PRIVATE METHODS
	 */
	
	private function add_gzip_compression_to_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		
		$lines = array();
		$lines[] = '<IfModule mod_deflate.c>';
		$lines[] = 'AddOutputFilterByType DEFLATE text/html';
		$lines[] = 'AddOutputFilterByType DEFLATE text/css';
		$lines[] = 'AddOutputFilterByType DEFLATE text/javascript';
		$lines[] = 'AddOutputFilterByType DEFLATE text/xml';
		$lines[] = 'AddOutputFilterByType DEFLATE text/plain';
		$lines[] = 'AddOutputFilterByType DEFLATE image/x-icon';
		$lines[] = 'AddOutputFilterByType DEFLATE image/svg+xml';
		$lines[] = 'AddOutputFilterByType DEFLATE application/rss+xml';
		$lines[] = 'AddOutputFilterByType DEFLATE application/javascript';
		$lines[] = 'AddOutputFilterByType DEFLATE application/x-javascript';
		$lines[] = 'AddOutputFilterByType DEFLATE application/xml';
		$lines[] = 'AddOutputFilterByType DEFLATE application/xhtml+xml';
		$lines[] = 'AddOutputFilterByType DEFLATE application/x-font';
		$lines[] = 'AddOutputFilterByType DEFLATE application/x-font-truetype';
		$lines[] = 'AddOutputFilterByType DEFLATE application/x-font-ttf';
		$lines[] = 'AddOutputFilterByType DEFLATE application/x-font-otf';
		$lines[] = 'AddOutputFilterByType DEFLATE application/x-font-opentype';
		$lines[] = 'AddOutputFilterByType DEFLATE application/vnd.ms-fontobject';
		$lines[] = 'AddOutputFilterByType DEFLATE font/ttf';
		$lines[] = 'AddOutputFilterByType DEFLATE font/otf';
		$lines[] = 'AddOutputFilterByType DEFLATE font/opentype';
		// For Olders Browsers Which Can\'t Handle Compression.
		$lines[] = 'BrowserMatch ^Mozilla/4 gzip-only-text/html';
		$lines[] = 'BrowserMatch ^Mozilla/4\.0[678] no-gzip';
		$lines[] = 'BrowserMatch \bMSIE !no-gzip !gzip-only-text/html';
		$lines[] = "</IfModule>\n";
		// Backup choice.
		$lines[] = '<ifModule mod_gzip.c>';
		$lines[] = 'mod_gzip_on Yes';
		$lines[] = 'mod_gzip_dechunk Yes';
		$lines[] = 'mod_gzip_item_include file \.(html?|txt|css|js|php|pl)$';
		$lines[] = 'mod_gzip_item_include mime ^application/x-javascript.*';
		$lines[] = 'mod_gzip_item_include mime ^text/.*';
		$lines[] = 'mod_gzip_item_exclude rspheader ^Content-Encoding:.*gzip.*';
		$lines[] = 'mod_gzip_item_exclude mime ^image/.*';
		$lines[] = 'mod_gzip_item_include handler ^cgi-script$';
		$lines[] = "</ifModule>\n";

		return insert_with_markers($file, SpeedUpWordPress::CONFIG_SECTION_GZIP, $lines);
	}
	
	private function remove_gzip_compression_from_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		return insert_with_markers($file, SpeedUpWordPress::CONFIG_SECTION_GZIP, array());
	}
	
	private function add_file_expires_to_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		
		$lines = array();
		$lines[] = '<IfModule mod_expires.c>';
		$lines[] = '  ExpiresActive On';
		// Images
		$lines[] = '  ExpiresByType image/jpeg "access plus 1 year"';
		$lines[] = '  ExpiresByType image/gif "access plus 1 year"';
		$lines[] = '  ExpiresByType image/png "access plus 1 year"';
		$lines[] = '  ExpiresByType image/webp "access plus 1 year"';
		$lines[] = '  ExpiresByType image/svg+xml "access plus 1 year"';
		$lines[] = '  ExpiresByType image/x-icon "access plus 1 year"';
		// Fonts
		$lines[] = '  ExpiresByType application/x-font "access plus 1 year"';
		$lines[] = '  ExpiresByType application/x-font-truetype "access plus 1 year"';
		$lines[] = '  ExpiresByType application/x-font-ttf "access plus 1 year"';
		$lines[] = '  ExpiresByType application/x-font-otf "access plus 1 year"';
		$lines[] = '  ExpiresByType application/x-font-woff "access plus 1 year"';
		$lines[] = '  ExpiresByType application/vnd.ms-fontobject "access plus 1 year"';
		$lines[] = '  ExpiresByType font/ttf "access plus 1 year"';
		$lines[] = '  ExpiresByType font/otf "access plus 1 year"';
		$lines[] = '  ExpiresByType font/opentype "access plus 1 year"';
		// Video
		$lines[] = '  ExpiresByType video/mp4 "access plus 1 year"';
		$lines[] = '  ExpiresByType video/mpeg "access plus 1 year"';
		// CSS, JavaScript
		$lines[] = '  ExpiresByType text/css "access plus 1 week"';
		$lines[] = ' ExpiresByType text/javascript "access plus 1 month"';
		$lines[] = ' ExpiresByType application/javascript "access plus 1 month"';
		// Others
		$lines[] = '  ExpiresByType application/pdf "access plus 1 month"';
		$lines[] = '  ExpiresByType application/x-shockwave-flash "access plus 1 month"';
		$lines[] = '</IfModule>';
		
		return insert_with_markers($file, SpeedUpWordPress::CONFIG_SECTION_EXPIRE, $lines);
	}	
	
	private function remove_file_expires_from_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		return insert_with_markers($file, SpeedUpWordPress::CONFIG_SECTION_EXPIRE, array());		
	}
	
	private function add_hotlink_block_to_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		
		$lines = array();
		$lines[] = 'RewriteEngine on';
		$lines[] = 'RewriteCond %{HTTP_REFERER} !^$';
		$lines[] = 'RewriteCond %{HTTP_REFERER} !^http(s)?://(www\.)?' . $this->get_site_domain() . ' [NC]';
		$lines[] = 'RewriteCond %{HTTP_REFERER} !^http(s)?://(www\.)?google.com [NC]';
		$lines[] = 'RewriteCond %{HTTP_REFERER} !^http(s)?://(www\.)?startpage.com [NC]';
		$lines[] = 'RewriteRule \.(jpg|jpeg|png|gif)$ - [F]';
		
		return insert_with_markers($file, SpeedUpWordPress::CONFIG_SECTION_HOTLINKS, $lines);
	}
	
	private function remove_hotlink_block_from_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		return insert_with_markers($file, SpeedUpWordPress::CONFIG_SECTION_HOTLINKS, array());		
	}
	
	private function get_site_domain() {
		$parsed_url = parse_url(get_site_url());
		return $parsed_url['host'];
	}
	
	private function is_gzip_compression_working_test() {
		$arguments = array(
			'headers' => array(
				'Content-Encoding' => 'gzip'
			)
		);
		
		$response = wp_remote_get(get_site_url(), $arguments);
		
		if (!is_array($response)) {
			return FALSE;
		}
		
		return strpos($response['headers']['content-encoding'], 'gzip') !== FALSE;
	}
	
	private function redirect_to_settings_page() {
		header('Location: ' . get_admin_url() . SpeedUpWordPress::ADMIN_SETTINGS_URL);
		exit();
	}
	
	private function is_section_filled_in_htaccess_file($section) {
		$file = get_home_path() . self::HTACCESS_FILENAME;
		$contents = file_get_contents($file);
		$position_start = strpos($contents, '# BEGIN ' . $section);
		$position_end = strpos($contents, '# END ' . $section);
		return $position_start !== FALSE && $position_end !== FALSE && $position_end - $position_start > 50;
	}
	
}