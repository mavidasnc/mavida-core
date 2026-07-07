/**
 * Componente "Edit": interfaccia mostrata nell'editor a blocchi.
 * L'anteprima del blocco e' delegata a ServerSideRender, che richiama lo stesso
 * render.php usato in frontend: editor e frontend mostrano sempre lo stesso risultato.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	FormTokenField,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

import './editor.scss';

// Tag HTML disponibili per il nome della categoria: stesso elenco validato lato server
// (vedi allowed_name_tags in render.php).
const NAME_TAG_OPTIONS = [
	{ label: 'H1', value: 'h1' },
	{ label: 'H2', value: 'h2' },
	{ label: 'H3', value: 'h3' },
	{ label: 'H4', value: 'h4' },
	{ label: 'Div', value: 'div' },
	{ label: 'Span', value: 'span' },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		columns,
		excludedCategories,
		cacheMinutes,
		cardBackgroundColor,
		cardBorderRadius,
		nameTagName,
		nameColor,
		nameFontSize,
		ctaText,
		ctaUrl,
		ctaIsButton,
		ctaTextColor,
		ctaBackgroundColor,
		ctaFontSize,
	} = attributes;
	const blockProps = useBlockProps();

	// Stato del pulsante "Svuota cache": invalida la cache server-side del blocco
	// (vedi includes/block-cache.php) tramite l'endpoint REST mavida-core/v1/purge-cache.
	const [ isPurgingCache, setIsPurgingCache ] = useState( false );
	const [ purgeNotice, setPurgeNotice ] = useState( null );

	function onPurgeCache() {
		setIsPurgingCache( true );
		setPurgeNotice( null );

		apiFetch( { path: '/mavida-core/v1/purge-cache', method: 'POST' } )
			.then( () => {
				setPurgeNotice( { status: 'success', message: __( 'Cache svuotata.', 'mavida-core' ) } );
			} )
			.catch( () => {
				setPurgeNotice( { status: 'error', message: __( 'Impossibile svuotare la cache. Riprova.', 'mavida-core' ) } );
			} )
			.finally( () => {
				setIsPurgingCache( false );
			} );
	}

	// Elenco delle categorie prodotto, recuperato dall'endpoint REST del plugin
	// (mavida-core/v1/product-categories) per popolare la select di esclusione.
	// Endpoint dedicato invece di core-data/getEntityRecords: risposta minima
	// (id/nome/conteggio) e comportamento indipendente da eventuali filtri
	// che disabilitassero show_in_rest sulla tassonomia product_cat.
	const [ categories, setCategories ] = useState( [] );
	const [ isLoadingCategories, setIsLoadingCategories ] = useState( true );
	const [ categoriesError, setCategoriesError ] = useState( false );

	useEffect( () => {
		// Evita di aggiornare lo stato se il componente e' stato smontato (blocco rimosso)
		// prima che la richiesta sia completata.
		let isMounted = true;

		apiFetch( { path: '/mavida-core/v1/product-categories' } )
			.then( ( result ) => {
				if ( ! isMounted ) {
					return;
				}
				setCategories( result );
				setIsLoadingCategories( false );
			} )
			.catch( () => {
				if ( ! isMounted ) {
					return;
				}
				setCategoriesError( true );
				setIsLoadingCategories( false );
			} );

		return () => {
			isMounted = false;
		};
	}, [] );

	// FormTokenField (lo stesso componente usato nativamente da WordPress per il pannello
	// "Tag" nella barra laterale) lavora con token di testo, non con ID: serve una mappa
	// bidirezionale nome<->id per tradurre da/verso l'attributo excludedCategories (array di ID).
	const excludedCategoryNames = excludedCategories
		.map( ( id ) => categories.find( ( category ) => category.id === id )?.name )
		.filter( Boolean );

	function onChangeExcludedCategories( tokens ) {
		const ids = tokens
			.map( ( token ) => categories.find( ( category ) => category.name === token )?.id )
			// Ignora eventuali token digitati liberamente che non corrispondono a nessuna
			// categoria esistente: la select deve restare vincolata alle categorie reali.
			.filter( ( id ) => typeof id === 'number' );

		setAttributes( { excludedCategories: ids } );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Impostazioni griglia', 'mavida-core' ) }>
					<RangeControl
						label={ __( 'Numero di colonne', 'mavida-core' ) }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 8 }
					/>

					{ isLoadingCategories && <Spinner /> }

					{ ! isLoadingCategories && categoriesError && (
						<Notice status="error" isDismissible={ false }>
							{ __( "Impossibile caricare l'elenco delle categorie. Ricarica la pagina per riprovare.", 'mavida-core' ) }
						</Notice>
					) }

					{ ! isLoadingCategories && ! categoriesError && (
						<FormTokenField
							label={ __( 'Categorie da escludere', 'mavida-core' ) }
							value={ excludedCategoryNames }
							suggestions={ categories.map( ( category ) => category.name ) }
							onChange={ onChangeExcludedCategories }
							__experimentalExpandOnFocus
						/>
					) }
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Aspetto card', 'mavida-core' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: cardBackgroundColor,
							onChange: ( value ) => setAttributes( { cardBackgroundColor: value || '#ffffff' } ),
							label: __( 'Colore sfondo card', 'mavida-core' ),
						},
					] }
				>
					<RangeControl
						label={ __( 'Arrotondamento angoli (px)', 'mavida-core' ) }
						value={ cardBorderRadius }
						onChange={ ( value ) => setAttributes( { cardBorderRadius: value } ) }
						min={ 0 }
						max={ 40 }
					/>
				</PanelColorSettings>

				<PanelColorSettings
					title={ __( 'Testo categoria', 'mavida-core' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: nameColor,
							onChange: ( value ) => setAttributes( { nameColor: value || '' } ),
							label: __( 'Colore testo', 'mavida-core' ),
						},
					] }
				>
					<SelectControl
						label={ __( 'Tag HTML', 'mavida-core' ) }
						help={ __( 'Il tag usato per il nome della categoria (es. H2 se la griglia è il titolo principale della sezione).', 'mavida-core' ) }
						value={ nameTagName }
						options={ NAME_TAG_OPTIONS }
						onChange={ ( value ) => setAttributes( { nameTagName: value } ) }
					/>
					<RangeControl
						label={ __( 'Dimensione testo (px)', 'mavida-core' ) }
						value={ nameFontSize }
						onChange={ ( value ) => setAttributes( { nameFontSize: value } ) }
						min={ 10 }
						max={ 60 }
					/>
				</PanelColorSettings>

				<PanelBody title={ __( 'Call to action', 'mavida-core' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Testo (vuoto = nessuna CTA)', 'mavida-core' ) }
						value={ ctaText }
						onChange={ ( value ) => setAttributes( { ctaText: value } ) }
						placeholder={ __( 'Es. Visualizza tutti i prodotti', 'mavida-core' ) }
					/>
					<TextControl
						type="url"
						label={ __( 'URL', 'mavida-core' ) }
						value={ ctaUrl }
						onChange={ ( value ) => setAttributes( { ctaUrl: value } ) }
						placeholder="https://"
					/>
					<ToggleControl
						label={ __( 'Mostra come pulsante', 'mavida-core' ) }
						checked={ ctaIsButton }
						onChange={ ( value ) => setAttributes( { ctaIsButton: value } ) }
					/>
					<RangeControl
						label={ __( 'Dimensione testo (px)', 'mavida-core' ) }
						value={ ctaFontSize }
						onChange={ ( value ) => setAttributes( { ctaFontSize: value } ) }
						min={ 10 }
						max={ 40 }
					/>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Colori CTA', 'mavida-core' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: ctaTextColor,
							onChange: ( value ) => setAttributes( { ctaTextColor: value || '' } ),
							label: __( 'Colore testo', 'mavida-core' ),
						},
						...( ctaIsButton
							? [
									{
										value: ctaBackgroundColor,
										onChange: ( value ) => setAttributes( { ctaBackgroundColor: value || '' } ),
										label: __( 'Colore sfondo pulsante', 'mavida-core' ),
									},
							  ]
							: [] ),
					] }
				/>

				<PanelBody title={ __( 'Cache', 'mavida-core' ) } initialOpen={ false }>
					<TextControl
						type="number"
						min={ 0 }
						label={ __( 'Durata cache (minuti)', 'mavida-core' ) }
						help={ __( 'Il markup della griglia viene salvato in cache per il numero di minuti indicato. Imposta 0 per disattivare la cache.', 'mavida-core' ) }
						value={ cacheMinutes }
						onChange={ ( value ) => setAttributes( { cacheMinutes: Math.max( 0, Number( value ) || 0 ) } ) }
					/>

					<Button
						variant="secondary"
						isBusy={ isPurgingCache }
						disabled={ isPurgingCache }
						onClick={ onPurgeCache }
					>
						{ __( 'Svuota cache', 'mavida-core' ) }
					</Button>
					<p className="components-base-control__help">
						{ __( 'Svuota la cache di tutte le griglie categorie del sito, non solo di questo blocco.', 'mavida-core' ) }
					</p>

					{ purgeNotice && (
						<Notice
							status={ purgeNotice.status }
							isDismissible={ false }
							className="mavida-core-purge-notice"
						>
							{ purgeNotice.message }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="mavida-core/product-category-grid"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
