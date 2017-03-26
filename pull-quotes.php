<?php
/**
 * Plugin Name: Pull Quotes
 * Plugin URI: http://aarondcampbell.com/wordpress-plugin/pull-quotes/
 * Description: Pull Quotes done right
 * Version: 1.0.2
 * Author: Aaron D. Campbell
 * Author URI: http://ran.ge/
 * License: GPLv2 or later
 * Text Domain: pull-quotes
 */

class pullQuotes {
	/**
	 * @var pullQuotes - Static property to hold our singleton instance
	 */
	static $instance = false;

	/**
	 * This is our constructor, which is private to force the use of getInstance()
	 * @return void
	 */
	protected function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_shortcode( 'pullquote', array( $this, 'pullquote' ) );
	}

	public function init() {
		// Only do this stuff when the current user has permissions and we are in Rich Editor mode
		if ( ( current_user_can('edit_posts') || current_user_can('edit_pages') ) && get_user_option('rich_editing') ) {
			add_filter( 'mce_external_plugins', array( $this, 'mce_external_plugins' ) );
			add_filter( 'mce_buttons', array( $this, 'mce_buttons' ) );
		}
	}

	public function mce_external_plugins( $plugin_array ) {
		$plugin_array['pullquote'] = plugins_url( '/js/tinymce-plugin.js', __FILE__ );
		return $plugin_array;
	}

	public function mce_buttons( $buttons ) {
		array_push( $buttons, 'separator', 'pullquote' );
		return $buttons;
	}

	function wp_enqueue_scripts() {
		wp_register_script( 'pull-quotes', plugins_url( 'js/pull-quotes.js', __FILE__ ), array( 'jquery' ), '20170324', true );
	}

	function admin_enqueue_scripts() {
		wp_enqueue_script( 'pull-quotes-quicktags', plugins_url( 'js/text-editor-plugin.js', __FILE__ ), array( 'quicktags' ) );
		wp_enqueue_style( 'pull-quotes', plugins_url( 'css/pull-quotes.css', __FILE__ ), array(), '20130429' );
	}

	public function pullquote( $attr, $content = '' ) {
		wp_enqueue_script( 'pull-quotes' );
		$defaults = array(
			'align'   => 'left',
			'back'    => '',
			'forward' => '',
			'width'   => '',
			'wrap'    => '',
		);

        $attr = shortcode_atts( $defaults, $attr );
		$attr['align'] = strtolower( $attr['align'] );
		if ( ! in_array( $attr['align'], array( 'left', 'right', '' ) ) )
			$attr['align'] = 'left';

		if ( ! empty( $attr['align'] ) )
			$attr['align'] = ' align' . $attr['align'];
		$data = '';
		if ( '' != $attr['back'] ) {
			$data = ' data-back="' . absint( $attr['back'] ) . '"';
		} elseif ( '' != $attr['forward'] ) {
			$data = ' data-forward="' . absint( $attr['forward'] ) . '"';
		}

		if ( ! empty( $attr['width'] ) )
			$data .= ' style="width:' . esc_attr( $attr['width'] ) . '"';

		if ( ! empty( $attr['wrap'] ) )
			$data .= ' data-wrap="' . esc_attr( $attr['wrap'] ) . '"';
        return '<span class="pullquote' . $attr['align'] . '"' . $data . '>'. do_shortcode( $content ) .'</span>';
	}

	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function getInstance() {
		if ( !self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
}
// Instantiate our class
$pullQuotes = pullQuotes::getInstance();
