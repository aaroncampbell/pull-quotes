<?php
/**
 * Pull Quotes server-side renderer.
 *
 * @package Pull_Quotes
 */

/**
 * Transforms pull-quote markers in parsed block content.
 */
final class Pull_Quotes_Renderer {
	/**
	 * Transform markers and render the transformed block tree.
	 *
	 * The transformed tree is serialized for WordPress's `do_blocks()` filter,
	 * which runs immediately after this filter at the default priority 9.
	 *
	 * @param string $content Post content.
	 * @return string Transformed block markup.
	 */
	public function render( string $content ): string {
		if ( ! has_blocks( $content ) || ! str_contains( $content, '<span' ) || ! str_contains( $content, 'pullquote' ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		$quotes = array();

		$this->extract_from_siblings( $blocks, array(), $quotes );

		if ( empty( $quotes ) ) {
			return $content;
		}

		$insertions = $this->build_insertions( $blocks, $quotes );
		$blocks     = $this->inject_into_siblings( $blocks, array(), $insertions );

		return serialize_blocks( $blocks );
	}

	/**
	 * Extract quote markers from a sibling list in document order.
	 *
	 * @param array<int, array<string, mixed>>                     $blocks Blocks at this level.
	 * @param array<int, int>                                      $parent_path Path to this sibling list.
	 * @param array<int, array<string, int|string|array<int,int>>> $quotes Extracted quotes.
	 */
	private function extract_from_siblings( array &$blocks, array $parent_path, array &$quotes ): void {
		foreach ( $blocks as $index => &$block ) {
			$path = array_merge( $parent_path, array( $index ) );
			$this->extract_from_block( $block, $path, $quotes );
		}
		unset( $block );
	}

	/**
	 * Extract markers from one block.
	 *
	 * @param array<string, mixed>                                 $block Block to inspect.
	 * @param array<int, int>                                      $path Block path.
	 * @param array<int, array<string, int|string|array<int,int>>> $quotes Extracted quotes.
	 */
	private function extract_from_block( array &$block, array $path, array &$quotes ): void {
		$child_index   = 0;
		$inner_content = $block['innerContent'] ?? array( $block['innerHTML'] ?? '' );

		foreach ( $inner_content as &$fragment ) {
			if ( null === $fragment ) {
				if ( isset( $block['innerBlocks'][ $child_index ] ) ) {
					$child_path = array_merge( $path, array( $child_index ) );
					$this->extract_from_block( $block['innerBlocks'][ $child_index ], $child_path, $quotes );
				}

				++$child_index;
				continue;
			}

			$result   = $this->extract_from_html( $fragment );
			$fragment = $result['html'];

			foreach ( $result['quotes'] as $quote ) {
				$quote['source_path'] = $path;
				$quotes[]             = $quote;
			}
		}
		unset( $fragment );

		while ( isset( $block['innerBlocks'][ $child_index ] ) ) {
			$child_path = array_merge( $path, array( $child_index ) );
			$this->extract_from_block( $block['innerBlocks'][ $child_index ], $child_path, $quotes );
			++$child_index;
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

	/**
	 * Unwrap inline markers and collect their content and placement metadata.
	 *
	 * @param string $html Block HTML.
	 * @return array{html: string, quotes: array<int, array<string, int|string>>} Result.
	 */
	private function extract_from_html( string $html ): array {
		$tag_pattern = "~<!--.*?-->|</?[A-Za-z][^>\"']*(?:\"[^\"]*\"|'[^']*')*[^>]*>~s";
		$matches     = array();
		$output      = '';
		$quotes      = array();
		$span_stack  = array();
		$active      = array();
		$cursor      = 0;

		preg_match_all( $tag_pattern, $html, $matches, PREG_OFFSET_CAPTURE );

		foreach ( $matches[0] as $match ) {
			$token  = $match[0];
			$offset = $match[1];
			$text   = substr( $html, $cursor, $offset - $cursor );

			$this->append_fragment( $text, $output, $quotes, $active );

			if ( preg_match( '~^</\s*span\b~i', $token ) ) {
				$span = array_pop( $span_stack );

				if ( is_int( $span ) ) {
					array_pop( $active );
				} else {
					$this->append_fragment( $token, $output, $quotes, $active );
				}
			} elseif ( preg_match( '~^<\s*span\b~i', $token ) ) {
				$processor = new WP_HTML_Tag_Processor( $token );
				$is_marker = $processor->next_tag( 'SPAN' ) && $processor->has_class( 'pullquote' );

				if ( $is_marker ) {
					$quote_id            = count( $quotes );
					$quotes[ $quote_id ] = $this->marker_attributes( $processor );
					$span_stack[]        = $quote_id;
					$active[]            = $quote_id;
				} else {
					$span_stack[] = null;
					$this->append_fragment( $token, $output, $quotes, $active );
				}
			} else {
				$this->append_fragment( $token, $output, $quotes, $active );
			}

			$cursor = $offset + strlen( $token );
		}

		$this->append_fragment( substr( $html, $cursor ), $output, $quotes, $active );

		return array(
			'html'   => $output,
			'quotes' => $quotes,
		);
	}

	/**
	 * Append HTML to the body output and every active marker capture.
	 *
	 * @param string                                $fragment Fragment to append.
	 * @param string                                $output Body output.
	 * @param array<int, array<string, int|string>> $quotes Quote captures.
	 * @param array<int, int>                       $active Active quote IDs.
	 */
	private function append_fragment( string $fragment, string &$output, array &$quotes, array $active ): void {
		$output .= $fragment;

		foreach ( $active as $quote_id ) {
			$quotes[ $quote_id ]['html'] .= $fragment;
		}
	}

	/**
	 * Read placement metadata from a marker span.
	 *
	 * @param WP_HTML_Tag_Processor $processor Marker processor.
	 * @return array<string, int|string> Quote metadata.
	 */
	private function marker_attributes( WP_HTML_Tag_Processor $processor ): array {
		$offset_value = $processor->get_attribute( 'data-offset' );
		$offset       = is_string( $offset_value ) && ctype_digit( $offset_value ) ? absint( $offset_value ) : 0;
		$direction    = (string) $processor->get_attribute( 'data-direction' );

		if ( 0 < $offset && ! in_array( $direction, array( 'back', 'forward' ), true ) ) {
			$offset    = 0;
			$direction = '';
		}

		$align = (string) $processor->get_attribute( 'data-align' );

		if ( $processor->has_class( 'alignright' ) ) {
			$align = 'right';
		} elseif ( $processor->has_class( 'alignleft' ) ) {
			$align = 'left';
		} elseif ( ! in_array( $align, array( 'left', 'right' ), true ) ) {
			$align = 'left';
		}

		return array(
			'html'      => '',
			'offset'    => $offset,
			'direction' => $direction,
			'align'     => $align,
			'width'     => (string) $processor->get_attribute( 'data-width' ),
		);
	}

	/**
	 * Resolve quotes to insertion points and build aside blocks.
	 *
	 * @param array<int, array<string, mixed>>                     $blocks Parsed blocks.
	 * @param array<int, array<string, int|string|array<int,int>>> $quotes Extracted quotes.
	 * @return array<string, array<int, array<int, array<string, mixed>>>> Insertions.
	 */
	private function build_insertions( array $blocks, array $quotes ): array {
		$insertions = array();

		foreach ( $quotes as $quote ) {
			$point                                   = $this->resolve_insertion(
				$blocks,
				$quote['source_path'],
				(int) $quote['offset'],
				(string) $quote['direction']
			);
			$key                                     = $this->path_key( $point['parent_path'] );
			$insertions[ $key ][ $point['index'] ][] = $this->aside_block( $quote );
		}

		return $insertions;
	}

	/**
	 * Resolve an offset to a sibling insertion point.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<int, int>                  $source_path Source block path.
	 * @param int                              $offset Number of moves.
	 * @param string                           $direction Direction of travel.
	 * @return array{parent_path: array<int, int>, index: int} Insertion point.
	 */
	private function resolve_insertion( array $blocks, array $source_path, int $offset, string $direction ): array {
		$current = $source_path;
		$point   = $this->before_block( $current );

		if ( 0 === $offset ) {
			return $point;
		}

		for ( $move = 0; $move < $offset; ++$move ) {
			$current = $this->adjacent_counted_path( $blocks, $current, $direction );

			if ( null === $current ) {
				return 'back' === $direction
					? array(
						'parent_path' => array(),
						'index'       => 0,
					)
					: array(
						'parent_path' => array(),
						'index'       => count( $blocks ),
					);
			}

			$point = $this->before_block( $current );
		}

		return $point;
	}

	/**
	 * Find the next authored block path in one direction.
	 *
	 * Parser whitespace, generated pull-quote asides, and moves across container
	 * boundaries do not consume an offset.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<int, int>                  $path Current block path.
	 * @param string                           $direction Direction of travel.
	 * @return array<int, int>|null Adjacent counted path, or null at the post boundary.
	 */
	private function adjacent_counted_path( array $blocks, array $path, string $direction ): ?array {
		$current = $path;

		while ( ! empty( $current ) ) {
			$parent_path   = array_slice( $current, 0, -1 );
			$current_index = (int) end( $current );
			$siblings      = $this->siblings_at_path( $blocks, $parent_path );

			if ( 'back' === $direction ) {
				for ( $index = $current_index - 1; 0 <= $index; --$index ) {
					if ( $this->is_counted_block( $siblings[ $index ] ) ) {
						return array_merge( $parent_path, array( $index ) );
					}
				}
			} else {
				$sibling_count = count( $siblings );

				for ( $index = $current_index + 1; $index < $sibling_count; ++$index ) {
					if ( $this->is_counted_block( $siblings[ $index ] ) ) {
						return array_merge( $parent_path, array( $index ) );
					}
				}
			}

			$current = $parent_path;
		}

		return null;
	}

	/**
	 * Determine whether a parsed block should consume an offset.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return bool Whether the block is visible authored content.
	 */
	private function is_counted_block( array $block ): bool {
		$html = (string) ( $block['innerHTML'] ?? '' );

		if ( null === ( $block['blockName'] ?? null ) && '' === trim( $html ) && empty( $block['innerBlocks'] ) ) {
			return false;
		}

		return ! preg_match( '/<aside\b[^>]*class=(?:"[^"]*\bpullquote\b[^"]*"|\'[^\']*\bpullquote\b[^\']*\')[^>]*>/i', $html );
	}

	/**
	 * Convert a block path to its before-block insertion point.
	 *
	 * @param array<int, int> $path Block path.
	 * @return array{parent_path: array<int, int>, index: int} Insertion point.
	 */
	private function before_block( array $path ): array {
		return array(
			'parent_path' => array_slice( $path, 0, -1 ),
			'index'       => (int) end( $path ),
		);
	}

	/**
	 * Get siblings at a path in the original parsed tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<int, int>                  $parent_path Parent path.
	 * @return array<int, array<string, mixed>> Sibling blocks.
	 */
	private function siblings_at_path( array $blocks, array $parent_path ): array {
		$siblings = $blocks;

		foreach ( $parent_path as $index ) {
			$siblings = $siblings[ $index ]['innerBlocks'];
		}

		return $siblings;
	}

	/**
	 * Create a freeform block containing a generated aside.
	 *
	 * @param array<string, int|string|array<int,int>> $quote Quote metadata.
	 * @return array<string, mixed> Aside block.
	 */
	private function aside_block( array $quote ): array {
		$classes = 'pullquote pulledquote align' . ( 'right' === $quote['align'] ? 'right' : 'left' );
		$style   = '';

		if ( '' !== $quote['width'] ) {
			$width = safecss_filter_attr( 'width:' . $quote['width'] );

			if ( '' !== $width ) {
				$style = ' style="' . esc_attr( $width ) . '"';
			}
		}

		$html  = '<aside class="' . esc_attr( $classes ) . '" aria-hidden="true"' . $style . '>';
		$html .= wp_kses_post( (string) $quote['html'] );
		$html .= '</aside>';

		return array(
			'blockName'    => null,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Inject generated aside blocks throughout the parsed tree.
	 *
	 * @param array<int, array<string, mixed>>                            $blocks Blocks at this level.
	 * @param array<int, int>                                             $parent_path Path to this sibling list.
	 * @param array<string, array<int, array<int, array<string, mixed>>>> $insertions Insertion map.
	 * @return array<int, array<string, mixed>> Transformed siblings.
	 */
	private function inject_into_siblings( array $blocks, array $parent_path, array $insertions ): array {
		foreach ( $blocks as $index => &$block ) {
			if ( empty( $block['innerBlocks'] ) ) {
				continue;
			}

			$path                  = array_merge( $parent_path, array( $index ) );
			$original_child_count  = count( $block['innerBlocks'] );
			$child_insertions      = $insertions[ $this->path_key( $path ) ] ?? array();
			$block['innerBlocks']  = $this->inject_into_siblings( $block['innerBlocks'], $path, $insertions );
			$block['innerContent'] = $this->inject_inner_content(
				$block['innerContent'],
				$original_child_count,
				$child_insertions
			);
			$block['innerHTML']    = implode(
				'',
				array_filter(
					$block['innerContent'],
					static fn( $part ): bool => null !== $part
				)
			);
		}
		unset( $block );

		if ( ! empty( $parent_path ) ) {
			return $blocks;
		}

		$current_insertions = $insertions[ $this->path_key( $parent_path ) ] ?? array();
		$transformed        = array();
		$block_count        = count( $blocks );

		for ( $index = 0; $index <= $block_count; ++$index ) {
			foreach ( $current_insertions[ $index ] ?? array() as $aside ) {
				$transformed[] = $aside;
			}

			if ( $index < $block_count ) {
				$transformed[] = $blocks[ $index ];
			}
		}

		return $transformed;
	}

	/**
	 * Add nested asides to a parent block's inner content.
	 *
	 * @param array<int, string|null>                      $inner_content Original inner content.
	 * @param int                                          $child_count Original child count.
	 * @param array<int, array<int, array<string, mixed>>> $insertions Insertions by child index.
	 * @return array<int, string|null> Updated inner content.
	 */
	private function inject_inner_content( array $inner_content, int $child_count, array $insertions ): array {
		if ( empty( $insertions ) ) {
			return $inner_content;
		}

		$null_positions = array_keys( $inner_content, null, true );
		$last_null      = end( $null_positions );
		$result         = array();
		$child_index    = 0;
		$end_inserted   = false;

		foreach ( $inner_content as $position => $fragment ) {
			if ( null === $fragment ) {
				foreach ( $insertions[ $child_index ] ?? array() as $aside ) {
					$result[] = $aside['innerHTML'];
				}

				$result[] = null;
				++$child_index;

				if ( $position === $last_null ) {
					foreach ( $insertions[ $child_count ] ?? array() as $aside ) {
						$result[] = $aside['innerHTML'];
					}
					$end_inserted = true;
				}
				continue;
			}

			$result[] = $fragment;
		}

		if ( ! $end_inserted ) {
			foreach ( $insertions[ $child_count ] ?? array() as $aside ) {
				$result[] = $aside['innerHTML'];
			}
		}

		return $result;
	}

	/**
	 * Build a stable map key for a parent path.
	 *
	 * @param array<int, int> $path Parent path.
	 * @return string Path key.
	 */
	private function path_key( array $path ): string {
		return empty( $path ) ? 'root' : implode( '.', $path );
	}
}
