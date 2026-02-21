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
	CheckboxControl,
	ToggleControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useSettings from '../hooks/useSettings';
import { api } from '../api';

export default function Settings() {
	const { settings, loading, saving, error, saveSettings } = useSettings();
	const [ form, setForm ] = useState( {} );
	const [ saved, setSaved ] = useState( false );
	const [ postTypes, setPostTypes ] = useState( [] );

	useEffect( () => {
		if ( settings ) {
			setForm( { ...settings } );
		}
	}, [ settings ] );

	useEffect( () => {
		api.getPostTypes().then( setPostTypes ).catch( () => {} );
	}, [] );

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
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Output Directory', 'static-export-wp' ) }
						value={ form.output_dir || '' }
						onChange={ ( val ) => updateField( 'output_dir', val ) }
						help={ __( 'Absolute path where static files will be saved.', 'static-export-wp' ) }
					/>

					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
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
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Base URL', 'static-export-wp' ) }
							value={ form.base_url || '' }
							onChange={ ( val ) => updateField( 'base_url', val ) }
							placeholder="https://example.com"
							help={ __( 'The URL where the static site will be hosted.', 'static-export-wp' ) }
						/>
					) }

					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Export Mode', 'static-export-wp' ) }
						value={ form.export_mode || 'full' }
						options={ [
							{ label: __( 'Full Site', 'static-export-wp' ), value: 'full' },
							{ label: __( 'Selective (specific pages)', 'static-export-wp' ), value: 'selective' },
						] }
						onChange={ ( val ) => updateField( 'export_mode', val ) }
						help={ __( 'Full exports all discovered pages. Selective exports only specific URLs.', 'static-export-wp' ) }
					/>

					{ form.export_mode === 'selective' && (
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'URLs to Export', 'static-export-wp' ) }
							value={ ( form.selected_urls || [] ).join( '\n' ) }
							onChange={ ( val ) =>
								updateField( 'selected_urls', val.split( '\n' ).filter( Boolean ) )
							}
							help={ __( 'Absolute URLs to export (one per line). Only these pages and their assets will be exported.', 'static-export-wp' ) }
							rows={ 6 }
						/>
					) }

					{ postTypes.length > 0 && (
						<fieldset className="sewp-settings__post-types">
							<legend>{ __( 'Post Types to Export', 'static-export-wp' ) }</legend>
							{ postTypes.map( ( pt ) => (
								<CheckboxControl
									__nextHasNoMarginBottom
									key={ pt.name }
									label={ pt.label }
									checked={ ( form.post_types || [] ).includes( pt.name ) }
									onChange={ ( checked ) => {
										const current = form.post_types || [];
										const updated = checked
											? [ ...current, pt.name ]
											: current.filter( ( t ) => t !== pt.name );
										updateField( 'post_types', updated );
									} }
								/>
							) ) }
						</fieldset>
					) }

					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Batch Size', 'static-export-wp' ) }
						value={ form.batch_size || 10 }
						onChange={ ( val ) => updateField( 'batch_size', val ) }
						min={ 1 }
						max={ 100 }
						help={ __( 'URLs processed per batch.', 'static-export-wp' ) }
					/>

					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Rate Limit (req/s)', 'static-export-wp' ) }
						value={ form.rate_limit || 50 }
						onChange={ ( val ) => updateField( 'rate_limit', val ) }
						min={ 1 }
						max={ 200 }
						help={ __( 'Max requests per second to avoid overloading.', 'static-export-wp' ) }
					/>

					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Timeout (seconds)', 'static-export-wp' ) }
						value={ form.timeout || 30 }
						onChange={ ( val ) => updateField( 'timeout', val ) }
						min={ 5 }
						max={ 120 }
					/>

					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Max Retries', 'static-export-wp' ) }
						value={ form.max_retries || 3 }
						onChange={ ( val ) => updateField( 'max_retries', val ) }
						min={ 0 }
						max={ 10 }
					/>

					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Extra URLs', 'static-export-wp' ) }
						value={ ( form.extra_urls || [] ).join( '\n' ) }
						onChange={ ( val ) =>
							updateField( 'extra_urls', val.split( '\n' ).filter( Boolean ) )
						}
						help={ __( 'Additional URLs to include (one per line).', 'static-export-wp' ) }
						rows={ 4 }
					/>

					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Exclude Patterns', 'static-export-wp' ) }
						value={ ( form.exclude_patterns || [] ).join( '\n' ) }
						onChange={ ( val ) =>
							updateField( 'exclude_patterns', val.split( '\n' ).filter( Boolean ) )
						}
						help={ __( 'URL patterns to exclude (one per line).', 'static-export-wp' ) }
						rows={ 4 }
					/>

					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Pagination Depth', 'static-export-wp' ) }
						value={ form.pagination_depth || 0 }
						onChange={ ( val ) => updateField( 'pagination_depth', val ) }
						min={ 0 }
						max={ 50 }
						help={ __( 'Max pagination pages to crawl (e.g. /page/2/). 0 = unlimited.', 'static-export-wp' ) }
					/>

					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Post-Export Deploy', 'static-export-wp' ) }
						value={ form.deploy_method || 'none' }
						options={ [
							{ label: __( 'None', 'static-export-wp' ), value: 'none' },
							{ label: __( 'Shell Command', 'static-export-wp' ), value: 'command' },
						] }
						onChange={ ( val ) => updateField( 'deploy_method', val ) }
						help={ __( 'Automatically deploy after a successful export.', 'static-export-wp' ) }
					/>

					{ form.deploy_method === 'command' && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Deploy Command', 'static-export-wp' ) }
							value={ form.deploy_command || '' }
							onChange={ ( val ) => updateField( 'deploy_command', val ) }
							placeholder="rsync -avz {{output_dir}}/ user@host:/var/www/"
							help={ __( 'Shell command to run after export. Use {{output_dir}} as placeholder for the export path.', 'static-export-wp' ) }
						/>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Incremental Export', 'static-export-wp' ) }
						help={ __( 'Skip unchanged pages on re-export by comparing content hashes.', 'static-export-wp' ) }
						checked={ !! form.incremental_export }
						onChange={ ( val ) => updateField( 'incremental_export', val ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Pagefind Search', 'static-export-wp' ) }
						help={ __( 'Generate a client-side search index after export using Pagefind. Requires npx.', 'static-export-wp' ) }
						checked={ !! form.pagefind_enabled }
						onChange={ ( val ) => updateField( 'pagefind_enabled', val ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Email Notification', 'static-export-wp' ) }
						help={ __( 'Send an email when a background export completes or fails.', 'static-export-wp' ) }
						checked={ !! form.notify_enabled }
						onChange={ ( val ) => updateField( 'notify_enabled', val ) }
					/>

					{ !! form.notify_enabled && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Notification Email', 'static-export-wp' ) }
							value={ form.notify_email || '' }
							onChange={ ( val ) => updateField( 'notify_email', val ) }
							type="email"
							help={ __( 'Leave blank to use the admin email address.', 'static-export-wp' ) }
						/>
					) }

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
