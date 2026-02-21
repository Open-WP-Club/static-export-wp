import { createRoot } from '@wordpress/element';
import App from './App';
import './styles/admin.css';

const container = document.getElementById( 'sewp-admin-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
