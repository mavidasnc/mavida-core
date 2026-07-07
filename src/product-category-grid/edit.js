/**
 * Componente "Edit": interfaccia mostrata nell'editor a blocchi.
 * L'anteprima del blocco e' delegata a ServerSideRender, che richiama lo stesso
 * render.php usato in frontend: editor e frontend mostrano sempre lo stesso risultato.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, TextControl, Button, Notice, Spinner } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { columns, excludedCategories, cacheMinutes } = attributes;
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

	useEffect( () => {
		apiFetch( { path: '/mavida-core/v1/product-categories' } )
			.then( ( result ) => {
				setCategories( result );
				setIsLoadingCategories( false );
			} )
			.catch( () => {
				setIsLoadingCategories( false );
			} );
	}, [] );

	// SelectControl nativa "multiple": i value sono sempre stringhe, vanno
	// riconvertiti a number per restare coerenti con l'attributo excludedCategories.
	function onChangeExcludedCategories( values ) {
		setAttributes( { excludedCategories: values.map( Number ) } );
	}

	const categoryOptions = categories.map( ( category ) => ( {
		label: `${ category.name } (${ category.count })`,
		value: String( category.id ),
	} ) );

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

					{ isLoadingCategories ? (
						<Spinner />
					) : (
						<SelectControl
							multiple
							label={ __( 'Categorie da escludere', 'mavida-core' ) }
							help={ __( 'Tieni premuto Ctrl (Cmd su Mac) per selezionare piu\' categorie.', 'mavida-core' ) }
							value={ excludedCategories.map( String ) }
							options={ categoryOptions }
							onChange={ onChangeExcludedCategories }
						/>
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
