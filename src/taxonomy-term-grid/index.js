/**
 * Registra il blocco "Griglia Tassonomia".
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * style.scss viene compilato in style-index.css e caricato sia in editor che in frontend
 * (proprieta' "style" in block.json). editor.scss viene compilato in index.css e caricato
 * solo in editor (proprieta' "editorStyle").
 */
import './style.scss';
import './editor.scss';

import Edit from './edit';
import metadata from './block.json';

// Blocco dinamico: nessuna funzione "save", il markup e' generato lato server da render.php.
registerBlockType( metadata.name, {
	edit: Edit,
} );
