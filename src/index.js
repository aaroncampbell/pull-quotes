import { RichTextToolbarButton } from '@wordpress/block-editor';
import { rawHandler } from '@wordpress/blocks';
import {
	Button,
	Dropdown,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { use as applyDataRegistryPlugin } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { pullquote } from '@wordpress/icons';
import {
	applyFormat,
	registerFormatType,
	removeFormat,
} from '@wordpress/rich-text';
import { next as nextShortcode } from '@wordpress/shortcode';

import './editor.scss';

const FORMAT_NAME = 'pull-quotes/pullquote';
const DEFAULT_ATTRIBUTES = {
	offset: '0',
	direction: 'back',
	align: 'left',
};

const normalizeAttributes = ( attributes = {} ) => ( {
	offset: /^\d+$/.test( attributes[ 'data-offset' ] || '' )
		? attributes[ 'data-offset' ]
		: '0',
	direction: [ 'back', 'forward' ].includes( attributes[ 'data-direction' ] )
		? attributes[ 'data-direction' ]
		: 'back',
	align: [ 'left', 'right' ].includes( attributes[ 'data-align' ] )
		? attributes[ 'data-align' ]
		: 'left',
} );

function PullQuoteEdit( { activeAttributes, isActive, onChange, value } ) {
	const [ settings, setSettings ] = useState( DEFAULT_ATTRIBUTES );

	useEffect( () => {
		if ( isActive ) {
			setSettings( normalizeAttributes( activeAttributes ) );
		}
	}, [ activeAttributes, isActive ] );

	const applyPullQuote = () => {
		onChange(
			applyFormat( value, {
				type: FORMAT_NAME,
				attributes: {
					'data-offset': settings.offset,
					'data-direction': settings.direction,
					'data-align': settings.align,
				},
			} )
		);
	};

	return (
		<Dropdown
			className="pull-quotes-format-dropdown"
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<RichTextToolbarButton
					icon={ pullquote }
					isActive={ isActive }
					onClick={ () => {
						if ( ! isActive ) {
							setSettings( DEFAULT_ATTRIBUTES );
							onChange(
								applyFormat( value, {
									type: FORMAT_NAME,
									attributes: {
										'data-offset':
											DEFAULT_ATTRIBUTES.offset,
										'data-direction':
											DEFAULT_ATTRIBUTES.direction,
										'data-align': DEFAULT_ATTRIBUTES.align,
									},
								} )
							);
						}
						onToggle();
					} }
					title={ __( 'Pull quote', 'pull-quotes' ) }
					aria-expanded={ isOpen }
				/>
			) }
			renderContent={ ( { onClose } ) => (
				<div className="pull-quotes-format-settings">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Offset', 'pull-quotes' ) }
						min={ 0 }
						onChange={ ( offset ) =>
							setSettings( {
								...settings,
								offset: String(
									Math.max(
										0,
										Number.parseInt( offset || '0', 10 ) ||
											0
									)
								),
							} )
						}
						type="number"
						value={ settings.offset }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Direction', 'pull-quotes' ) }
						onChange={ ( direction ) =>
							setSettings( { ...settings, direction } )
						}
						options={ [
							{
								label: __( 'Back', 'pull-quotes' ),
								value: 'back',
							},
							{
								label: __( 'Forward', 'pull-quotes' ),
								value: 'forward',
							},
						] }
						value={ settings.direction }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'pull-quotes' ) }
						onChange={ ( align ) =>
							setSettings( { ...settings, align } )
						}
						options={ [
							{
								label: __( 'Left', 'pull-quotes' ),
								value: 'left',
							},
							{
								label: __( 'Right', 'pull-quotes' ),
								value: 'right',
							},
						] }
						value={ settings.align }
					/>
					<div className="pull-quotes-format-actions">
						<Button
							variant="primary"
							onClick={ () => {
								applyPullQuote();
								onClose();
							} }
						>
							{ __( 'Apply', 'pull-quotes' ) }
						</Button>
						{ isActive && (
							<Button
								isDestructive
								variant="secondary"
								onClick={ () => {
									onChange(
										removeFormat( value, FORMAT_NAME )
									);
									onClose();
								} }
							>
								{ __( 'Remove', 'pull-quotes' ) }
							</Button>
						) }
					</div>
				</div>
			) }
		/>
	);
}

registerFormatType( FORMAT_NAME, {
	title: __( 'Pull quote', 'pull-quotes' ),
	tagName: 'span',
	className: 'pullquote',
	attributes: {
		'data-offset': 'data-offset',
		'data-direction': 'data-direction',
		'data-align': 'data-align',
		'data-width': 'data-width',
	},
	edit: PullQuoteEdit,
} );

const escapeAttribute = ( value ) =>
	String( value ).replace( /[&<>"']/g, ( character ) => {
		const entities = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;',
		};

		return entities[ character ];
	} );

export function migrateLegacyShortcodes( html ) {
	if ( ! html || ! html.includes( '[pullquote' ) ) {
		return html;
	}

	let cursor = 0;
	let migrated = '';
	let match = nextShortcode( 'pullquote', html, cursor );

	while ( match ) {
		const attributes = match.shortcode.attrs.named || {};
		const hasBack = attributes.back !== undefined && attributes.back !== '';
		const hasForward =
			attributes.forward !== undefined && attributes.forward !== '';
		const direction = hasForward && ! hasBack ? 'forward' : 'back';
		let rawOffset = '0';

		if ( hasBack ) {
			rawOffset = attributes.back;
		} else if ( hasForward ) {
			rawOffset = attributes.forward;
		}
		const offset = String(
			Math.max( 0, Number.parseInt( rawOffset, 10 ) || 0 )
		);
		const align = attributes.align === 'right' ? 'right' : 'left';
		const width = attributes.width
			? ` data-width="${ escapeAttribute( attributes.width ) }"`
			: '';

		migrated += html.slice( cursor, match.index );
		migrated += `<span class="pullquote" data-offset="${ escapeAttribute(
			offset
		) }" data-direction="${ direction }" data-align="${ align }"${ width }>`;
		migrated += match.shortcode.content || '';
		migrated += '</span>';

		cursor = match.index + match.content.length;
		match = nextShortcode( 'pullquote', html, cursor );
	}

	return migrated + html.slice( cursor );
}

if ( ! window.pullQuotesClassicMigrationInstalled ) {
	applyDataRegistryPlugin( ( registry ) => ( {
		dispatch( storeNameOrDescriptor ) {
			const actions = registry.dispatch( storeNameOrDescriptor );
			const storeName =
				typeof storeNameOrDescriptor === 'string'
					? storeNameOrDescriptor
					: storeNameOrDescriptor?.name;

			if (
				storeName !== 'core/block-editor' ||
				! actions?.replaceBlocks
			) {
				return actions;
			}

			return {
				...actions,
				replaceBlocks( clientIds, blocks, ...args ) {
					const clientId = Array.isArray( clientIds )
						? clientIds[ 0 ]
						: clientIds;
					const sourceBlock = registry
						.select( 'core/block-editor' )
						?.getBlock( clientId );
					const sourceHtml = sourceBlock?.attributes?.content;

					if (
						sourceBlock?.name === 'core/freeform' &&
						typeof sourceHtml === 'string' &&
						sourceHtml.includes( '[pullquote' )
					) {
						blocks = rawHandler( {
							HTML: migrateLegacyShortcodes( sourceHtml ),
						} );
					}

					return actions.replaceBlocks( clientIds, blocks, ...args );
				},
			};
		},
	} ) );

	window.pullQuotesClassicMigrationInstalled = true;
}
