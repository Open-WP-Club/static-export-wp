import { useState, useEffect, useCallback } from '@wordpress/element';
import { api } from '../api';

export default function useSettings() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const fetchSettings = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const data = await api.getSettings();
			setSettings( data );
		} catch ( err ) {
			setError( err.message || 'Failed to load settings.' );
		}
		setLoading( false );
	}, [] );

	const saveSettings = useCallback( async ( newSettings ) => {
		setSaving( true );
		setError( null );
		try {
			const response = await api.saveSettings( newSettings );
			setSettings( response.settings );
			return true;
		} catch ( err ) {
			setError( err.message || 'Failed to save settings.' );
			return false;
		} finally {
			setSaving( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	return { settings, loading, saving, error, saveSettings, refresh: fetchSettings };
}
