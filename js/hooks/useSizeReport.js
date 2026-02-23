import { useState, useEffect } from '@wordpress/element';
import { api } from '../api';

export default function useSizeReport() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let cancelled = false;

		api.getSizeReport().then( ( result ) => {
			if ( ! cancelled ) {
				setData( result );
			}
		} ).catch( () => {
			// Ignore — data stays null.
		} ).finally( () => {
			if ( ! cancelled ) {
				setLoading( false );
			}
		} );

		return () => {
			cancelled = true;
		};
	}, [] );

	return { data, loading };
}
