import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { api } from '../api';

export default function useExportStatus( isActive = false ) {
	const [ status, setStatus ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const intervalRef = useRef( null );

	const fetchStatus = useCallback( async () => {
		try {
			const data = await api.getExportStatus();
			setStatus( data );
			return data;
		} catch {
			// Silently ignore polling errors.
			return null;
		}
	}, [] );

	// Manual fetch.
	const refresh = useCallback( async () => {
		setLoading( true );
		const data = await fetchStatus();
		setLoading( false );
		return data;
	}, [ fetchStatus ] );

	// Start/stop polling.
	useEffect( () => {
		if ( isActive ) {
			fetchStatus();
			intervalRef.current = setInterval( fetchStatus, 2000 );
		}

		return () => {
			if ( intervalRef.current ) {
				clearInterval( intervalRef.current );
				intervalRef.current = null;
			}
		};
	}, [ isActive, fetchStatus ] );

	return { status, loading, refresh };
}
