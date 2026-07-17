<?php
/**
 * Pull Quotes migration tooling.
 *
 * @package Pull_Quotes
 */

/**
 * Converts legacy pullquote shortcodes to inline format markers.
 */
final class Pull_Quotes_Migrator {
	/**
	 * Find non-revision posts containing legacy pullquote shortcodes.
	 *
	 * @return array<int, int> Post IDs.
	 */
	public function find_candidate_ids(): array {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( '[pullquote' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type <> 'revision' AND post_status <> 'auto-draft' ORDER BY ID",
				$like
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Count block and classic migration candidates.
	 *
	 * @return array{total: int, block: int, classic: int} Candidate counts.
	 */
	public function candidate_counts(): array {
		$counts = array(
			'total'   => 0,
			'block'   => 0,
			'classic' => 0,
		);

		foreach ( $this->find_candidate_ids() as $post_id ) {
			$content = get_post_field( 'post_content', $post_id );

			if ( ! is_string( $content ) ) {
				continue;
			}

			++$counts['total'];
			++$counts[ has_blocks( $content ) ? 'block' : 'classic' ];
		}

		return $counts;
	}

	/**
	 * Migrate block-authored posts containing legacy shortcodes.
	 *
	 * @param array<int, int> $post_ids Optional post IDs. All candidates when empty.
	 * @param bool            $dry_run Whether to report without updating posts.
	 * @return array{found: int, migrated: int, classic: int, unchanged: int, errors: int} Summary.
	 */
	public function migrate( array $post_ids = array(), bool $dry_run = false ): array {
		$summary  = array(
			'found'     => 0,
			'migrated'  => 0,
			'classic'   => 0,
			'unchanged' => 0,
			'errors'    => 0,
		);
		$post_ids = empty( $post_ids ) ? $this->find_candidate_ids() : array_map( 'absint', $post_ids );

		foreach ( array_unique( $post_ids ) as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || ! str_contains( $post->post_content, '[pullquote' ) ) {
				continue;
			}

			++$summary['found'];

			if ( ! has_blocks( $post->post_content ) ) {
				++$summary['classic'];
				continue;
			}

			$converted = $this->convert_block_content( $post->post_content );

			if ( $converted === $post->post_content ) {
				++$summary['unchanged'];
				continue;
			}

			if ( $dry_run ) {
				++$summary['migrated'];
				continue;
			}

			$result = wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $converted,
					)
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				++$summary['errors'];
			} else {
				++$summary['migrated'];
			}
		}

		return $summary;
	}

	/**
	 * Convert legacy shortcodes within a serialized block tree.
	 *
	 * @param string $content Serialized block content.
	 * @return string Converted serialized blocks.
	 */
	public function convert_block_content( string $content ): string {
		if ( ! has_blocks( $content ) || ! str_contains( $content, '[pullquote' ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		$this->convert_blocks( $blocks );

		return serialize_blocks( $blocks );
	}

	/**
	 * Convert all legacy shortcodes in an HTML fragment.
	 *
	 * @param string $html HTML containing legacy shortcodes.
	 * @return string HTML containing inline format markers.
	 */
	public function convert_shortcodes_in_html( string $html ): string {
		if ( ! str_contains( $html, '[pullquote' ) ) {
			return $html;
		}

		$pattern = '/' . get_shortcode_regex( array( 'pullquote' ) ) . '/s';

		return (string) preg_replace_callback(
			$pattern,
			static function ( array $matches ): string {
				if ( 'pullquote' !== $matches[2] || '[' === $matches[1] || ']' === $matches[6] ) {
					return $matches[0];
				}

				$parsed_attributes = shortcode_parse_atts( $matches[3] );
				$attributes        = is_array( $parsed_attributes ) ? $parsed_attributes : array();
				$attributes        = shortcode_atts(
					array(
						'align'   => 'left',
						'back'    => '',
						'forward' => '',
						'width'   => '',
					),
					$attributes,
					'pullquote'
				);

				$offset    = 0;
				$direction = 'back';

				if ( '' !== $attributes['back'] ) {
					$offset = absint( $attributes['back'] );
				} elseif ( '' !== $attributes['forward'] ) {
					$offset    = absint( $attributes['forward'] );
					$direction = 'forward';
				}

				$align   = 'right' === strtolower( $attributes['align'] ) ? 'right' : 'left';
				$marker  = '<span class="pullquote" data-offset="' . esc_attr( (string) $offset ) . '"';
				$marker .= ' data-direction="' . esc_attr( $direction ) . '" data-align="' . esc_attr( $align ) . '"';

				if ( '' !== $attributes['width'] ) {
					$marker .= ' data-width="' . esc_attr( $attributes['width'] ) . '"';
				}

				return $marker . '>' . $matches[5] . '</span>';
			},
			$html
		);
	}

	/**
	 * Run the WP-CLI migration command.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<id>]
	 * : Limit migration to one post.
	 *
	 * [--dry-run]
	 * : Report changes without updating posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pull-quotes migrate --dry-run
	 *     wp pull-quotes migrate --post_id=568
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function cli_migrate( array $args, array $assoc_args ): void {
		unset( $args );

		$post_ids = isset( $assoc_args['post_id'] ) ? array( absint( $assoc_args['post_id'] ) ) : array();
		$dry_run  = array_key_exists( 'dry-run', $assoc_args );
		$summary  = $this->migrate( $post_ids, $dry_run );

		WP_CLI::log( 'Found: ' . $summary['found'] );
		WP_CLI::log( ( $dry_run ? 'Would migrate: ' : 'Migrated: ' ) . $summary['migrated'] );
		WP_CLI::log( 'Classic posts skipped: ' . $summary['classic'] );
		WP_CLI::log( 'Unchanged: ' . $summary['unchanged'] );

		if ( 0 < $summary['errors'] ) {
			WP_CLI::error( 'Migration errors: ' . $summary['errors'] );
		}

		WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Migration complete.' );
	}

	/**
	 * Convert shortcode strings throughout a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	private function convert_blocks( array &$blocks ): void {
		foreach ( $blocks as &$block ) {
			$inner_content = $block['innerContent'] ?? array( $block['innerHTML'] ?? '' );

			foreach ( $inner_content as &$fragment ) {
				if ( null !== $fragment ) {
					$fragment = $this->convert_shortcodes_in_html( $fragment );
				}
			}
			unset( $fragment );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->convert_blocks( $block['innerBlocks'] );
			}

			$block['innerContent'] = $inner_content;
			$block['innerHTML']    = implode(
				'',
				array_filter(
					$inner_content,
					static fn( $part ): bool => null !== $part
				)
			);
		}
		unset( $block );
	}
}
