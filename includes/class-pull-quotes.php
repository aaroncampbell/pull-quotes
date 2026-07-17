<?php
/**
 * Pull Quotes plugin class.
 *
 * @package Pull_Quotes
 */

/**
 * Pull Quotes plugin.
 */
final class Pull_Quotes {
	/**
	 * Singleton instance.
	 *
	 * @var Pull_Quotes|null
	 */
	private static ?Pull_Quotes $instance = null;

	/**
	 * Server-side renderer.
	 *
	 * @var Pull_Quotes_Renderer
	 */
	private Pull_Quotes_Renderer $renderer;

	/**
	 * Legacy shortcode migrator.
	 *
	 * @var Pull_Quotes_Migrator
	 */
	private Pull_Quotes_Migrator $migrator;

	/**
	 * Set up the plugin hooks.
	 */
	private function __construct() {
		$this->renderer = new Pull_Quotes_Renderer();
		$this->migrator = new Pull_Quotes_Migrator();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_migration_page' ) );
		add_action( 'admin_post_pull_quotes_migrate', array( $this, 'handle_admin_migration' ) );
		add_filter( 'the_content', array( $this->renderer, 'render' ), 8 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'pull-quotes migrate', array( $this->migrator, 'cli_migrate' ) );
		}
	}

	/**
	 * Prevent cloning the singleton.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing the singleton.
	 *
	 * @throws LogicException Always, because the singleton cannot be unserialized.
	 */
	public function __wakeup(): void {
		throw new LogicException( 'Pull_Quotes cannot be unserialized.' );
	}

	/**
	 * Enforce the minimum supported WordPress and PHP versions on activation.
	 */
	public static function activate(): void {
		global $wp_version;

		$minimum_php       = '8.3';
		$minimum_wordpress = '7.0';

		if ( version_compare( PHP_VERSION, $minimum_php, '>=' ) && version_compare( $wp_version, $minimum_wordpress, '>=' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( PULL_QUOTES_PLUGIN_FILE ) );

		$message = sprintf(
			/* translators: 1: Minimum PHP version. 2: Minimum WordPress version. */
			__( 'Pull Quotes requires PHP %1$s or later and WordPress %2$s or later.', 'pull-quotes' ),
			$minimum_php,
			$minimum_wordpress
		);

		wp_die(
			esc_html( $message ),
			esc_html__( 'Plugin activation failed', 'pull-quotes' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Enqueue the front-end pull-quote styles.
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style(
			'pull-quotes',
			plugins_url( 'css/pull-quotes.css', PULL_QUOTES_PLUGIN_FILE ),
			array(),
			PULL_QUOTES_VERSION
		);
	}

	/**
	 * Enqueue the inline format and Classic-to-block migration integration.
	 */
	public function enqueue_editor_assets(): void {
		$asset_path = plugin_dir_path( PULL_QUOTES_PLUGIN_FILE ) . 'build/index.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			'pull-quotes-editor',
			plugins_url( 'build/index.js', PULL_QUOTES_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'pull-quotes-editor', 'pull-quotes' );

		if ( file_exists( plugin_dir_path( PULL_QUOTES_PLUGIN_FILE ) . 'build/index.css' ) ) {
			wp_enqueue_style(
				'pull-quotes-editor',
				plugins_url( 'build/index.css', PULL_QUOTES_PLUGIN_FILE ),
				array( 'wp-edit-blocks' ),
				$asset['version']
			);
		}
	}

	/**
	 * Register the migration screen under Tools.
	 */
	public function register_migration_page(): void {
		add_management_page(
			esc_html__( 'Pull Quotes Migration', 'pull-quotes' ),
			esc_html__( 'Pull Quotes Migration', 'pull-quotes' ),
			'manage_options',
			'pull-quotes-migration',
			array( $this, 'render_migration_page' )
		);
	}

	/**
	 * Render the migration screen.
	 */
	public function render_migration_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$counts   = $this->migrator->candidate_counts();
		$migrated = filter_input( INPUT_GET, 'migrated', FILTER_VALIDATE_INT );
		$classic  = filter_input( INPUT_GET, 'classic', FILTER_VALIDATE_INT );
		$errors   = filter_input( INPUT_GET, 'errors', FILTER_VALIDATE_INT );
		$dry_run  = filter_input( INPUT_GET, 'dry_run', FILTER_VALIDATE_BOOLEAN );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pull Quotes Migration', 'pull-quotes' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Total candidates. 2: Block posts. 3: Classic posts. */
						__( '%1$d candidate posts found: %2$d block posts and %3$d classic posts.', 'pull-quotes' ),
						$counts['total'],
						$counts['block'],
						$counts['classic']
					)
				);
				?>
			</p>
			<p><?php esc_html_e( 'Batch migration updates block posts. Open each classic post and use “Convert to blocks” to migrate it through the editor.', 'pull-quotes' ); ?></p>

			<?php if ( null !== $migrated && false !== $migrated ) : ?>
				<div class="notice notice-<?php echo $errors ? 'error' : 'success'; ?> is-dismissible"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Action label. 2: Migrated count. 3: Classic count. 4: Error count. */
							__( '%1$s: %2$d migrated, %3$d classic posts skipped, %4$d errors.', 'pull-quotes' ),
							$dry_run ? __( 'Dry run complete', 'pull-quotes' ) : __( 'Migration complete', 'pull-quotes' ),
							$migrated,
							$classic,
							$errors
						)
					);
					?>
				</p></div>
			<?php endif; ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pull_quotes_migrate" />
				<?php wp_nonce_field( 'pull_quotes_migrate' ); ?>
				<label>
					<input type="checkbox" name="dry_run" value="1" checked="checked" />
					<?php esc_html_e( 'Dry run (do not update posts)', 'pull-quotes' ); ?>
				</label>
				<?php submit_button( __( 'Run migration', 'pull-quotes' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the Tools screen migration request.
	 */
	public function handle_admin_migration(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to migrate pull quotes.', 'pull-quotes' ) );
		}

		check_admin_referer( 'pull_quotes_migrate' );

		$dry_run = isset( $_POST['dry_run'] );
		$summary = $this->migrator->migrate( array(), $dry_run );
		$url     = add_query_arg(
			array(
				'page'     => 'pull-quotes-migration',
				'migrated' => $summary['migrated'],
				'classic'  => $summary['classic'],
				'errors'   => $summary['errors'],
				'dry_run'  => $dry_run ? 1 : 0,
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Pull_Quotes Plugin instance.
	 */
	public static function get_instance(): Pull_Quotes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
