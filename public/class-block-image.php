<?php
/**
 * Draw Attention Block Image.
 *
 * @since   1.8
 * @package Draw_Attention
 */

/**
 * Draw Attention Block Image.
 *
 * @since 1.8
 */
class DrawAttention_Block_Image {
	/**
	 * Parent plugin class.
	 *
	 * @since 1.8
	 *
	 * @var   DrawAttention
	 */
	protected $plugin = null;

	/**
	 * Constructor.
	 *
	 * @since  1.8
	 *
	 * @param  Draw_Attention $plugin Main plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  1.8
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_image_block' ) );
	}

	function register_image_block() {
		if( function_exists('register_block_type') ){
			wp_register_script(
				'drawattention-image-block-js',
				$this->plugin->url( 'admin/assets/js/draw-attention-block.js' ),
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor' )
			);

			register_block_type( 'draw-attention/image', array(
				'editor_script' => 'drawattention-image-block-js',
				'keywords' => array( 'image', 'hotspot', 'map' ),

				'render_callback' => array( $this, 'render' ),
			) );
		}
	}

	function render() {
		return $this->plugin->shortcodes->shortcode();
	}

}
