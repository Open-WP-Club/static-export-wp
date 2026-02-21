import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	TextControl,
	SelectControl,
	RangeControl,
	TextareaControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useSettings from '../hooks/useSettings';

export default function Settings() {
	const { settings, loading, saving, error, saveSettings } = useSettings();
	const [ form, setForm ] = useState( {} );
	const [ saved, setSaved ] = useState( false );

	useEffect( () => {
		if ( settings ) {
			setForm( { ...settings } );
		}
	}, [ settings ] );

	if ( loading ) {
		return <Spinner />;
	}

	const updateField = ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaved( false );
	};

	const handleSave = async () => {
		const success = await saveSettings( form );
		if ( success ) {
			setSaved( true );
		}
	};

	return (
		<div className="sewp-settings">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ saved && (
				<Notice status="success" isDismissible onDismiss={ () => setSaved( false ) }>
					{ __( 'Settings saved.', 'static-export-wp' ) }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<h2>{ __( 'Export Settings', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Output Directory', 'static-export-wp' ) }
						value={ form.output_dir || '' }
						onChange={ ( val ) => updateField( 'output_dir', val ) }
						help={ __( 'Absolute path where static files will be saved.', 'static-export-wp' ) }
					/>

					<SelectControl
						label={ __( 'URL Mode', 'static-export-wp' ) }
						value={ form.url_mode || 'relative' }
						options={ [
							{ label: __( 'Relative', 'static-export-wp' ), value: 'relative' },
							{ label: __( 'Absolute', 'static-export-wp' ), value: 'absolute' },
						] }
						onChange={ ( val ) => updateField( 'url_mode', val ) }
						help={ __( 'Relative paths work offline. Absolute requires a base URL.', 'static-export-wp' ) }
					/>

					{ form.url_mode === 'absolute' && (
						<TextControl
							label={ __( 'Base URL', 'static-export-wp' ) }
							value={ form.base_url || '' }
							onChange={ ( val ) => updateField( 'base_url', val ) }
							placeholder="https://example.com"
							help={ __( 'The URL where the static site will be hosted.', 'static-export-wp' ) }
						/>
					) }

					<RangeControl
						label={ __( 'Batch Size', 'static-export-wp' ) }
						value={ form.batch_size || 10 }
						onChange={ ( val ) => updateField( 'batch_size', val ) }
						min={ 1 }
						max={ 100 }
						help={ __( 'URLs processed per batch.', 'static-export-wp' ) }
					/>

					<RangeControl
						label={ __( 'Rate Limit (req/s)', 'static-export-wp' ) }
						value={ form.rate_limit || 50 }
						onChange={ ( val ) => updateField( 'rate_limit', val ) }
						min={ 1 }
						max={ 200 }
						help={ __( 'Max requests per second to avoid overloading.', 'static-export-wp' ) }
					/>

					<RangeControl
						label={ __( 'Timeout (seconds)', 'static-export-wp' ) }
						value={ form.timeout || 30 }
						onChange={ ( val ) => updateField( 'timeout', val ) }
						min={ 5 }
						max={ 120 }
					/>

					<RangeControl
						label={ __( 'Max Retries', 'static-export-wp' ) }
						value={ form.max_retries || 3 }
						onChange={ ( val ) => updateField( 'max_retries', val ) }
						min={ 0 }
						max={ 10 }
					/>

					<TextareaControl
						label={ __( 'Extra URLs', 'static-export-wp' ) }
						value={ ( form.extra_urls || [] ).join( '\n' ) }
						onChange={ ( val ) =>
							updateField( 'extra_urls', val.split( '\n' ).filter( Boolean ) )
						}
						help={ __( 'Additional URLs to include (one per line).', 'static-export-wp' ) }
						rows={ 4 }
					/>

					<TextareaControl
						label={ __( 'Exclude Patterns', 'static-export-wp' ) }
						value={ ( form.exclude_patterns || [] ).join( '\n' ) }
						onChange={ ( val ) =>
							updateField( 'exclude_patterns', val.split( '\n' ).filter( Boolean ) )
						}
						help={ __( 'URL patterns to exclude (one per line).', 'static-export-wp' ) }
						rows={ 4 }
					/>

					<div className="sewp-settings__actions">
						<Button
							variant="primary"
							onClick={ handleSave }
							isBusy={ saving }
							disabled={ saving }
						>
							{ __( 'Save Settings', 'static-export-wp' ) }
						</Button>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}
