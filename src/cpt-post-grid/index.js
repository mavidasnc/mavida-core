/**
 * Registrazione del blocco "Griglia post per tipo di contenuto".
 * Blocco dinamico puro: nessuna funzione "save", il markup e' sempre generato da render.php.
 */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import './editor.scss';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
} );
