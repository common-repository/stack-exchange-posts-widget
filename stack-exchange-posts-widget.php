<?php
/**
 * Plugin Name: Stack Posts Widget  
 * Plugin URI: http://wordpress.org/plugins/stack-exchange-posts-widget
 * Description: Create widgets for any Stack Exchange site, display questions or answers by specific users or generic for the whole site. The random mode will show posts from different sites at each page view.
 * Version: 1.1
 * Author: Rodolfo Buaiz
 * Author URI: http://wordpress.stackexchange.com/users/12615/brasofilo
 * Text Domain: sepw
 * Domain Path: /languages/
 * License: GPLv2 or later
 */ 

/*
Stack Posts Widget
Copyright (C) 2013  Rodolfo Buaiz

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

add_action(
		'plugins_loaded', 
		array( SEPW_Widget_Init::get_instance(), 'plugin_setup' )
);


class SEPW_Widget_Init
{
	protected static $instance = NULL;
	public static $option_name = 'b5f_widgets_options';
	public $option_value = NULL;
	public $plugin_url = NULL;
	public $plugin_path = NULL;
	public $plugin_slug = NULL;
    private $options;

	
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;
		return self::$instance;
	}


	public function plugin_setup()
	{
		$this->plugin_url = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_slug = dirname( plugin_basename( __FILE__ ) );
        $this->options = get_option( self::$option_name );

		# Workaround to translate the description in the plugin page
		$translate_description = __( 'Create widgets for any Stack Exchange site, display questions or answers by specific users or generic for the whole site. The random mode will show posts from different sites at each page view.', 'sepw' );
		
		# Load translation files
		$this->plugin_locale( 'sepw' );

		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_head-widgets.php', array( $this, 'widgets_page_head' ) );
		add_filter( 'plugin_action_links', array( $this, 'settings_plugin_link' ), 10, 2 );
		add_action( 'widgets_init', array( $this, 'load_widget' ) );
		add_action( 'sidebar_admin_page', array( $this, 'widgets_page_settings' ) );
        add_filter( 'plugin_row_meta', array( $this, 'donate_link' ), 10, 4 );
	}

	
	/**
	 * Intentionally left empty
	 */
	public function __construct() {}
	
	
	/**
	 * Register the widget.
	 */
	public function load_widget()
	{
		include_once 'includes/class-sepw-widget-config.php';
		register_widget( 'SEPW_Widget_Config' );
	}
	
	
	/**
	 * Adjust width of options column
	 */
	public function widgets_page_head()
	{
		wp_enqueue_script( 'dd_js', $this->plugin_url . 'js/jquery.dd.min.js', array('jquery'));
		wp_enqueue_style( 'dd_style', $this->plugin_url . 'css/dd.css' );
	}

	
	/**
	 * Add link to settings in Plugins list page
	 * 
	 * @return Plugin link
	 */
	public function settings_plugin_link( $links, $file )
	{
		$base = plugin_basename( __FILE__ );
		if( $file == $base )
		{
			$in = sprintf(
					'<a href="%s">%s</a>',
					admin_url( 'widgets.php#se-widget' ),
					__( 'Settings', 'sepw' )
			);
			array_unshift( $links, $in );
		}
		return $links;
	}

	
	/**
     * Options page callback
     */
    public function widgets_page_settings()
    {
        ?>
        <div class="wrap clear"><a name="se-widget"></a>
			<?php screen_icon('plugins'); ?>
            <h2><?php _e( 'Stack Posts Widget', 'sepw' ); ?></h2>           
            <form id="se-form-settings" method="post" action="options.php">
            <?php
                settings_fields( 'b5f_widgets_group' );   
                do_settings_sections( 'b5f-widget-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

	
    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'b5f_widgets_group', // Option group
            self::$option_name, // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'b5f-widget-admin' // Page
        );  

        add_settings_field(
            'css', // ID
            '', // Title 
            array( $this, 'css_callback' ), // Callback
            'b5f-widget-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'cache', // ID
            '', // Title 
            array( $this, 'cache_callback' ), // Callback
            'b5f-widget-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'fancy_dropdown', // ID
            '', // Title 
            array( $this, 'fancy_dropdown_callback' ), // Callback
            'b5f-widget-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'disable_meta', 
            '', 
            array( $this, 'meta_callback' ), 
            'b5f-widget-admin', 
            'setting_section_id'
        );      

        add_settings_field(
            'reset', 
            '', 
            array( $this, 'reset_callback' ), 
            'b5f-widget-admin', 
            'setting_section_id'
        );      
    }

	
    /**
     * Sanitize each setting field as needed
	 * Empty cache, delete transient
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        $check = array( 'css', 'cache','fancy_dropdown', 'disable_meta' );
        foreach( $check as $c )
        {
            if( !empty( $input[$c] ) ) 
                $new_input[$c] = 'on';
        }
		if( !empty( $input['reset'] ) ) 
		{
			$cache_folder = dirname( plugin_dir_path( __FILE__ ) ) . '/cache/';
			if( is_dir( $cache_folder ) ) 
				$this->del_tree( $cache_folder );
			delete_transient( 'sepw_widget_sites' );
		}
        return $new_input;
    }
	
	
	/** 
     * Print the Section text
     */
    public function print_section_info() { }
	
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function css_callback()
    {
		printf(
			'<label><input type="checkbox" name="%s" %s /> %s</label>',
			'b5f_widgets_options[css]',
			checked( isset($this->options['css']), true, false),
			__( 'Disable CSS', 'sepw' )
		);
		$info = sprintf(
						'%s <a href="%s" target="_blank">%s</a> %s',
						__( 'See the plugin', 'sepw' ),
						$this->plugin_url . 'css/sepw.css',
						__( 'CSS file', 'sepw' ),
						__( 'to copy the rules.', 'sepw' )
				);
		printf(
				'<br /><small>%s</small>',
				$info
		);
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function cache_callback()
    {
		printf(
			'<label><input type="checkbox" name="%s" %s /> %s</label>',
			'b5f_widgets_options[cache]',
			checked( isset($this->options['cache']), true, false),
			__( 'Disable cache', 'sepw' )
		);
 		printf(
				'<br /><small>%s</small>',
				__( 'For debugging purposes only', 'sepw' )
		);
   }

    /** 
     * Get the settings option array and print one of its values
     */
    public function fancy_dropdown_callback()
    {
		printf(
			'<label><input type="checkbox" name="%s" %s /> %s</label>',
			'b5f_widgets_options[fancy_dropdown]',
			checked( isset($this->options['fancy_dropdown']), true, false),
			__( 'Disable advanced Sites Dropdown', 'sepw' )
		);
 		printf(
				'<br /><small>%s</small>',
				__( 'It may be buggy sometimes. E.G.: The Advanced Dropdown only works after saving the widget for the first time. And it is not tested in all browsers.', 'sepw' )
		);
   }

    /** 
     * Disable meta sites in random displays
     */
    public function meta_callback()
    {
		printf(
			'<label><input type="checkbox" name="%s" %s /> %s</label>',
			'b5f_widgets_options[disable_meta]',
			checked( isset($this->options['disable_meta']), true, false),
			__( 'Disable Meta sites in random display.', 'sepw' )
		);
    }
    
    
    /** 
     * Get the settings option array and print one of its values
     */
    public function reset_callback()
    {
		printf(
			'<label><input type="checkbox" name="%s" /> %s</label>',
			'b5f_widgets_options[reset]',
			__( 'Empty cache', 'sepw' )
		);
    }
    
    
    /**
     * Add donate link to plugin description in /wp-admin/plugins.php
     * 
     * @param array $plugin_meta
     * @param string $plugin_file
     * @param string $plugin_data
     * @param string $status
     * @return array
     */
    public function donate_link( $plugin_meta, $plugin_file, $plugin_data, $status ) 
	{
		if( plugin_basename( __FILE__ ) == $plugin_file )
			$plugin_meta[] = '&hearts; <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=JNJXKWBYM9JP6&lc=ES&item_name=Stack%20Posts%20Widget%20%3a%20Rodolfo%20Buaiz&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">Buy me a beer :o)</a>';
		return $plugin_meta;
	}

    
    /**
	 * Translation
	 *
	 * @uses    load_plugin_textdomain, plugin_basename
	 * @since   2.0.0
	 * @return  void
	 */
	public function plugin_locale( $domain )
	{
		# Prepare vars
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		$mo = sprintf(
				'%s/plugins/%s/%s', 
				WP_LANG_DIR, $this->plugin_slug, 
				$domain . '-' . $locale . '.mo'
		);

		# Load from /wp-content/languages/plugins/plugin-name/plug-xx_XX.mo'
		load_textdomain( $domain, $mo );

		# Load from /wp-content/plugins/plugin-name/languages/plug-xx_XX.mo'
		load_plugin_textdomain(
				$domain, FALSE, $this->plugin_slug . '/languages'
		);
	}

	/**
	 * From PHP Manual on rmdir
	 * 
	 * @param string $dir
	 */
	private function del_tree( $dir )
	{
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach( $files as $file )
			unlink( "$dir/$file" );
	}


}