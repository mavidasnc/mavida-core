/**
 * Componente "Edit": interfaccia mostrata nell'editor a blocchi.
 * L'anteprima del blocco e' delegata a ServerSideRender, che richiama lo stesso
 * render.php usato in frontend: editor e frontend mostrano sempre lo stesso risultato.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	FormTokenField,
	ColorPalette,
	BaseControl,
	Modal,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

import './editor.scss';

// Tag HTML disponibili per il nome del termine: stesso elenco validato lato server
// (vedi allowed_name_tags in render.php).
const NAME_TAG_OPTIONS = [
	{ label: 'H1', value: 'h1' },
	{ label: 'H2', value: 'h2' },
	{ label: 'H3', value: 'h3' },
	{ label: 'H4', value: 'h4' },
	{ label: 'Div', value: 'div' },
	{ label: 'Span', value: 'span' },
];

/**
 * Copia leggibile del CSS di default del blocco (vedi src/taxonomy-term-grid/style.scss).
 * Usata per precompilare/ripristinare la modale "Personalizza CSS": NON e' generata
 * automaticamente dal build, va aggiornata a mano se cambia lo SCSS (segnalato anche li').
 * "%SCOPE%" viene sostituito con l'id univoco di questa istanza del blocco, cosi' il CSS
 * mostrato (e quindi quello che l'utente modifica) e' gia' isolato a questa sola griglia.
 */
const DEFAULT_CSS_TEMPLATE = `%SCOPE% .mavida-tax-grid__items {
	display: grid;
	grid-template-columns: repeat( var( --mv-columns, 4 ), minmax( 0, 1fr ) );
	gap: 1rem;
}

@media ( max-width: 782px ) {
	%SCOPE% .mavida-tax-grid__items {
		grid-template-columns: repeat( var( --mv-columns-mobile, 2 ), minmax( 0, 1fr ) );
	}
}

%SCOPE% .mavida-tax-grid__item {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	text-decoration: none;
	color: inherit;
	background: var( --mv-card-bg, #fff );
	border: 1px solid rgba( 0, 0, 0, 0.08 );
	border-radius: var( --mv-card-radius, 12px );
	padding: var( --mv-card-padding, 1rem );
	transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

%SCOPE% .mavida-tax-grid__item:hover {
	transform: translateY( -3px );
	box-shadow: 0 6px 16px rgba( 0, 0, 0, 0.08 ), 0 20px 50px rgba( 0, 0, 0, 0.06 );
	border-color: transparent;
}

%SCOPE% .mavida-tax-grid__name {
	display: block;
	font-weight: 600;
	text-align: center;
	margin: 0;
}

%SCOPE% img {
	width: 100%;
	height: auto;
	display: block;
	border-radius: calc( var( --mv-card-radius, 12px ) - 4px );
}

%SCOPE% .mavida-tax-grid__cta {
	display: inline-block;
	align-self: center;
	margin-top: 0.25rem;
	color: inherit;
}

%SCOPE% .mavida-tax-grid__cta--button {
	padding: 0.5rem 1.25rem;
	border-radius: var( --mv-card-radius, 12px );
	background: #1a1a1a;
	color: #fff;
	transition: filter 0.2s ease;
}

%SCOPE% .mavida-tax-grid__item:hover .mavida-tax-grid__cta--button {
	filter: brightness( 0.9 );
}
`;

/**
 * Genera un id breve e sufficientemente univoco per ancorare il CSS personalizzato a
 * questa specifica istanza del blocco (non serve crittograficamente sicuro, solo stabile
 * e improbabile da collidere tra le poche istanze di uno stesso blocco su una pagina).
 *
 * @return {string} Id, es. "mv3f8a1c2d".
 */
function generateCssInstanceId() {
	return 'mv' + Math.random().toString( 36 ).slice( 2, 10 );
}

/**
 * Etichetta usata nei FormTokenField "termini da includere/escludere": il nome da solo non
 * garantisce l'univocita' del token (due termini, anche di tassonomie diverse elencati in
 * momenti diversi, possono avere lo stesso nome), l'id in coda si'.
 *
 * @param {Object} term Oggetto termine ({ id, name, count } ) restituito dall'endpoint REST.
 * @return {string} Es. "Nome del termine (#42)".
 */
function formatTermToken( term ) {
	return term.name + ' (#' + term.id + ')';
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		taxonomy,
		columns,
		mobileColumns,
		excludedTerms,
		includedTerms,
		cacheMinutes,
		cardBackgroundColor,
		cardBorderColor,
		cardBorderRadius,
		cardPadding,
		nameTagName,
		nameColor,
		nameFontSize,
		ctaText,
		ctaIsButton,
		ctaTextColor,
		ctaBackgroundColor,
		ctaFontSize,
		defaultImageId,
		customCss,
		cssInstanceId,
	} = attributes;
	const blockProps = useBlockProps();

	// L'id istanza serve ad ancorare il CSS personalizzato solo a questo blocco: generato una
	// sola volta (se non presente) e poi stabile, perche' salvato come attributo.
	useEffect( () => {
		if ( ! cssInstanceId ) {
			setAttributes( { cssInstanceId: generateCssInstanceId() } );
		}
	}, [] );

	// Dati dell'allegato scelto come immagine di default, per mostrarne l'anteprima (lo stesso
	// pattern usato dai blocchi nativi Immagine/Copertina): l'attributo salva solo l'ID, non
	// basta a costruire un'anteprima senza recuperare anche url/alt dell'allegato.
	const defaultImageMedia = useSelect(
		( select ) => ( defaultImageId ? select( 'core' ).getMedia( defaultImageId ) : null ),
		[ defaultImageId ]
	);

	// Stato del pulsante "Svuota cache": invalida la cache server-side del blocco (endpoint
	// REST condiviso con gli altri blocchi griglia, vedi includes/block-cache.php).
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

	// Modale "Personalizza CSS": la textarea viene agganciata a CodeMirror (gia' incluso in
	// WordPress core, stesso meccanismo di Aspetto > Personalizza > CSS aggiuntivo) tramite
	// window.wp.codeEditor, caricato solo in editor da includes/block-css-editor.php. Se
	// l'utente ha disattivato l'evidenziazione sintattica nel proprio profilo, quella funzione
	// non e' disponibile: si degrada a una textarea semplice, senza errori.
	const [ isCssModalOpen, setIsCssModalOpen ] = useState( false );
	const cssTextareaRef = useRef( null );
	const cssEditorRef = useRef( null );

	const defaultCssForThisInstance = DEFAULT_CSS_TEMPLATE.replace(
		/%SCOPE%/g,
		cssInstanceId ? '#mavida-tax-grid-' + cssInstanceId : '.mavida-tax-grid'
	);

	function getCssEditorValue() {
		return cssEditorRef.current
			? cssEditorRef.current.codemirror.getValue()
			: cssTextareaRef.current?.value ?? '';
	}

	function setCssEditorValue( value ) {
		if ( cssEditorRef.current ) {
			cssEditorRef.current.codemirror.setValue( value );
		} else if ( cssTextareaRef.current ) {
			cssTextareaRef.current.value = value;
		}
	}

	useEffect( () => {
		if ( ! isCssModalOpen ) {
			return;
		}

		if ( window.wp && window.wp.codeEditor && cssTextareaRef.current ) {
			cssEditorRef.current = window.wp.codeEditor.initialize(
				cssTextareaRef.current,
				window.mavidaCoreCssEditorSettings || {}
			);
		}

		return () => {
			if ( cssEditorRef.current ) {
				cssEditorRef.current.codemirror.toTextArea();
				cssEditorRef.current = null;
			}
		};
	}, [ isCssModalOpen ] );

	function onOpenCssModal() {
		setIsCssModalOpen( true );
	}

	function onCloseCssModal() {
		setIsCssModalOpen( false );
	}

	function onResetCss() {
		setCssEditorValue( defaultCssForThisInstance );
	}

	function onSaveCss() {
		setAttributes( { customCss: getCssEditorValue() } );
		setIsCssModalOpen( false );
	}

	// Elenco delle tassonomie selezionabili, recuperato una sola volta all'apertura
	// dell'editor (endpoint REST mavida-core/v1/taxonomies): tutte le tassonomie pubbliche
	// del sito (category, post_tag, e quelle custom gestite da CPT UI).
	const [ taxonomies, setTaxonomies ] = useState( [] );
	const [ isLoadingTaxonomies, setIsLoadingTaxonomies ] = useState( true );
	const [ taxonomiesError, setTaxonomiesError ] = useState( false );

	useEffect( () => {
		let isMounted = true;

		apiFetch( { path: '/mavida-core/v1/taxonomies' } )
			.then( ( result ) => {
				if ( ! isMounted ) {
					return;
				}
				setTaxonomies( result );
				setIsLoadingTaxonomies( false );
			} )
			.catch( () => {
				if ( ! isMounted ) {
					return;
				}
				setTaxonomiesError( true );
				setIsLoadingTaxonomies( false );
			} );

		return () => {
			isMounted = false;
		};
	}, [] );

	// Elenco dei termini della tassonomia scelta, per popolare le select di
	// inclusione/esclusione: si ricarica ad ogni cambio di taxonomy.
	const [ terms, setTerms ] = useState( [] );
	const [ isLoadingTerms, setIsLoadingTerms ] = useState( false );
	const [ termsError, setTermsError ] = useState( false );

	useEffect( () => {
		if ( ! taxonomy ) {
			setTerms( [] );
			return;
		}

		let isMounted = true;
		setIsLoadingTerms( true );
		setTermsError( false );

		apiFetch( { path: '/mavida-core/v1/taxonomy-terms?taxonomy=' + taxonomy } )
			.then( ( result ) => {
				if ( ! isMounted ) {
					return;
				}
				setTerms( result );
				setIsLoadingTerms( false );
			} )
			.catch( () => {
				if ( ! isMounted ) {
					return;
				}
				setTermsError( true );
				setIsLoadingTerms( false );
			} );

		return () => {
			isMounted = false;
		};
	}, [ taxonomy ] );

	// Cambiare tassonomia invalida gli ID scelti in precedenza: gli stessi ID non hanno
	// alcun significato per una tassonomia diversa.
	function onChangeTaxonomy( value ) {
		setAttributes( { taxonomy: value, includedTerms: [], excludedTerms: [] } );
	}

	// FormTokenField (lo stesso componente usato nativamente da WordPress per il pannello
	// "Tag" nella barra laterale) lavora con token di testo, non con ID: serve una mappa
	// bidirezionale token<->id per tradurre da/verso gli attributi includedTerms/excludedTerms.
	// Il token include l'id (vedi formatTermToken) perche' il nome da solo non garantisce
	// l'univocita' tra termini diversi.
	const excludedTermTokens = excludedTerms
		.map( ( id ) => {
			const term = terms.find( ( candidate ) => candidate.id === id );
			return term ? formatTermToken( term ) : null;
		} )
		.filter( Boolean );

	function onChangeExcludedTerms( tokens ) {
		const ids = tokens
			.map( ( token ) => terms.find( ( term ) => formatTermToken( term ) === token )?.id )
			// Ignora eventuali token digitati liberamente che non corrispondono a nessun
			// termine esistente: la select deve restare vincolata ai termini reali della
			// tassonomia scelta.
			.filter( ( id ) => typeof id === 'number' );

		setAttributes( { excludedTerms: ids } );
	}

	// "Termini da includere": stessa logica di mappatura token<->id di "escludi". L'ordine
	// dei token riflette l'ordine di inserimento (FormTokenField non lo altera da solo),
	// quindi non serve alcun ordinamento aggiuntivo per rispettare l'ordine scelto in editor.
	const includedTermTokens = includedTerms
		.map( ( id ) => {
			const term = terms.find( ( candidate ) => candidate.id === id );
			return term ? formatTermToken( term ) : null;
		} )
		.filter( Boolean );

	function onChangeIncludedTerms( tokens ) {
		const ids = tokens
			.map( ( token ) => terms.find( ( term ) => formatTermToken( term ) === token )?.id )
			.filter( ( id ) => typeof id === 'number' );

		setAttributes( { includedTerms: ids } );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Impostazioni griglia', 'mavida-core' ) }>
					{ isLoadingTaxonomies && <Spinner /> }

					{ ! isLoadingTaxonomies && taxonomiesError && (
						<Notice status="error" isDismissible={ false }>
							{ __( "Impossibile caricare l'elenco delle tassonomie. Ricarica la pagina per riprovare.", 'mavida-core' ) }
						</Notice>
					) }

					{ ! isLoadingTaxonomies && ! taxonomiesError && (
						<SelectControl
							label={ __( 'Tassonomia', 'mavida-core' ) }
							value={ taxonomy }
							options={ [
								{ label: __( '— Seleziona —', 'mavida-core' ), value: '' },
								...taxonomies.map( ( tax ) => ( { label: tax.label, value: tax.slug } ) ),
							] }
							onChange={ onChangeTaxonomy }
						/>
					) }

					<RangeControl
						label={ __( 'Numero di colonne', 'mavida-core' ) }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 8 }
					/>
					<RangeControl
						label={ __( 'Numero di colonne mobile', 'mavida-core' ) }
						help={ __( 'Colonne mostrate sotto i 782px, indipendenti da quelle desktop.', 'mavida-core' ) }
						value={ mobileColumns }
						onChange={ ( value ) => setAttributes( { mobileColumns: value } ) }
						min={ 1 }
						max={ 8 }
					/>

					{ taxonomy && isLoadingTerms && <Spinner /> }

					{ taxonomy && ! isLoadingTerms && termsError && (
						<Notice status="error" isDismissible={ false }>
							{ __( "Impossibile caricare l'elenco dei termini. Ricarica la pagina per riprovare.", 'mavida-core' ) }
						</Notice>
					) }

					{ taxonomy && ! isLoadingTerms && ! termsError && (
						<>
							<FormTokenField
								label={ __( 'Termini da includere', 'mavida-core' ) }
								help={ __( 'Se compilato, mostra solo questi termini, nell\'ordine in cui li aggiungi qui (ignora "termini da escludere").', 'mavida-core' ) }
								value={ includedTermTokens }
								suggestions={ terms.map( formatTermToken ) }
								onChange={ onChangeIncludedTerms }
								__experimentalExpandOnFocus
							/>
							<FormTokenField
								label={ __( 'Termini da escludere', 'mavida-core' ) }
								help={
									includedTerms.length > 0
										? __( 'Ignorato: "termini da includere" è compilato.', 'mavida-core' )
										: undefined
								}
								disabled={ includedTerms.length > 0 }
								value={ excludedTermTokens }
								suggestions={ terms.map( formatTermToken ) }
								onChange={ onChangeExcludedTerms }
								__experimentalExpandOnFocus
							/>
						</>
					) }
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Aspetto card', 'mavida-core' ) }
					initialOpen={ false }
					enableAlpha={ true }
					colorSettings={ [
						{
							value: cardBackgroundColor,
							onChange: ( value ) => setAttributes( { cardBackgroundColor: value || '#ffffff' } ),
							label: __( 'Colore sfondo card', 'mavida-core' ),
						},
						{
							value: cardBorderColor,
							onChange: ( value ) => setAttributes( { cardBorderColor: value || 'rgba(0,0,0,0.08)' } ),
							label: __( 'Colore bordo card', 'mavida-core' ),
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
					<RangeControl
						label={ __( 'Padding card (px)', 'mavida-core' ) }
						value={ cardPadding }
						onChange={ ( value ) => setAttributes( { cardPadding: value } ) }
						min={ 0 }
						max={ 48 }
					/>
				</PanelColorSettings>

				<PanelBody title={ __( 'Immagine di default', 'mavida-core' ) } initialOpen={ false }>
					<p className="components-base-control__help" style={ { marginTop: 0 } }>
						{ __( 'Usata al posto del placeholder quando un termine non ha un\'immagine propria.', 'mavida-core' ) }
					</p>
					{ !! defaultImageId && defaultImageMedia && (
						<img
							src={ defaultImageMedia.source_url }
							alt=""
							style={ { maxWidth: '100%', height: 'auto', display: 'block', marginBottom: '8px' } }
						/>
					) }
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) => setAttributes( { defaultImageId: media.id } ) }
							allowedTypes={ [ 'image' ] }
							value={ defaultImageId }
							render={ ( { open } ) => (
								<div style={ { display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' } }>
									<Button variant="secondary" onClick={ open }>
										{ defaultImageId
											? __( 'Sostituisci immagine', 'mavida-core' )
											: __( 'Scegli immagine', 'mavida-core' ) }
									</Button>
									{ !! defaultImageId && (
										<Button
											variant="link"
											isDestructive
											onClick={ () => setAttributes( { defaultImageId: 0 } ) }
										>
											{ __( 'Rimuovi', 'mavida-core' ) }
										</Button>
									) }
								</div>
							) }
						/>
					</MediaUploadCheck>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Testo termine', 'mavida-core' ) }
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
						help={ __( 'Il tag usato per il nome del termine (es. H2 se la griglia è il titolo principale della sezione).', 'mavida-core' ) }
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
						help={ __( 'Comparirà dentro ogni card termine, senza link proprio: il click resta quello dell\'intera card.', 'mavida-core' ) }
						value={ ctaText }
						onChange={ ( value ) => setAttributes( { ctaText: value } ) }
						placeholder={ __( 'Es. Scopri di più', 'mavida-core' ) }
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

					{ /* Colori CTA: qui invece che in un pannello a parte (PanelColorSettings
					renderizzerebbe il proprio pannello e non può annidarsi in questo). */ }
					<BaseControl label={ __( 'Colore testo', 'mavida-core' ) } id="mavida-core-cta-text-color">
						<ColorPalette
							value={ ctaTextColor }
							onChange={ ( value ) => setAttributes( { ctaTextColor: value || '' } ) }
						/>
					</BaseControl>
					{ ctaIsButton && (
						<BaseControl label={ __( 'Colore sfondo pulsante', 'mavida-core' ) } id="mavida-core-cta-bg-color">
							<ColorPalette
								value={ ctaBackgroundColor }
								onChange={ ( value ) => setAttributes( { ctaBackgroundColor: value || '' } ) }
							/>
						</BaseControl>
					) }
				</PanelBody>

				<PanelBody title={ __( 'CSS personalizzato', 'mavida-core' ) } initialOpen={ false }>
					<p className="components-base-control__help" style={ { marginTop: 0 } }>
						{ __( 'Il CSS scritto qui vale solo per questa griglia: le regole di partenza sono già ancorate a questa istanza, non alle altre griglie del sito.', 'mavida-core' ) }
					</p>
					<Button variant="secondary" onClick={ onOpenCssModal }>
						{ __( 'Personalizza CSS', 'mavida-core' ) }
					</Button>

					{ isCssModalOpen && (
						<Modal
							title={ __( 'CSS personalizzato', 'mavida-core' ) }
							onRequestClose={ onCloseCssModal }
							className="mavida-core-css-modal"
						>
							<textarea
								ref={ cssTextareaRef }
								defaultValue={ customCss || defaultCssForThisInstance }
								rows={ 20 }
								style={ { width: '100%', fontFamily: 'monospace' } }
							/>
							<div style={ { display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '16px' } }>
								<Button variant="tertiary" onClick={ onResetCss }>
									{ __( 'Ripristina default', 'mavida-core' ) }
								</Button>
								<Button variant="primary" onClick={ onSaveCss }>
									{ __( 'Salva CSS', 'mavida-core' ) }
								</Button>
							</div>
						</Modal>
					) }
				</PanelBody>

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
						{ __( 'Svuota la cache di tutte le griglie del sito (categorie prodotto, post per tipo di contenuto e tassonomia), non solo di questo blocco.', 'mavida-core' ) }
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
				{ taxonomy ? (
					<ServerSideRender
						block="mavida-core/taxonomy-term-grid"
						attributes={ attributes }
					/>
				) : (
					<Placeholder
						icon="grid-view"
						label={ __( 'Griglia Tassonomia', 'mavida-core' ) }
						instructions={ __( 'Scegli una tassonomia dal pannello impostazioni per iniziare.', 'mavida-core' ) }
					/>
				) }
			</div>
		</>
	);
}
