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

const WEBHOOK_EVENT_OPTIONS = [
	{ value: 'completed', label: __( 'Export completed', 'static-export-wp' ) },
	{ value: 'failed', label: __( 'Export failed', 'static-export-wp' ) },
];

export default function Settings() {
	const { settings, loading, saving, error, saveSettings } = useSettings();
	const [ form, setForm ] = useState( {} );
	const [ saved, setSaved ] = useState( false );
	const [ postTypes, setPostTypes ] = useState( [] );
	const [ webhookTesting, setWebhookTesting ] = useState( false );
	const [ webhookResult, setWebhookResult ] = useState( null );

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

	const handleTestWebhook = async () => {
		setWebhookTesting( true );
		setWebhookResult( null );
		try {
			const res = await api.testWebhook();
			setWebhookResult( { success: true, message: __( 'Webhook delivered successfully.', 'static-export-wp' ) } );
		} catch ( err ) {
			setWebhookResult( { success: false, message: err.message || __( 'Webhook delivery failed.', 'static-export-wp' ) } );
		}
		setWebhookTesting( false );
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
							{ label: __( 'Git Push', 'static-export-wp' ), value: 'git' },
							{ label: __( 'Netlify API', 'static-export-wp' ), value: 'netlify' },
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

					{ form.deploy_method === 'git' && (
						<>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Repository URL', 'static-export-wp' ) }
								value={ form.deploy_git_remote || '' }
								onChange={ ( val ) => updateField( 'deploy_git_remote', val ) }
								placeholder="https://github.com/user/repo.git"
								help={ __( 'HTTPS URL of the Git repository to push to.', 'static-export-wp' ) }
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Branch', 'static-export-wp' ) }
								value={ form.deploy_git_branch || 'main' }
								onChange={ ( val ) => updateField( 'deploy_git_branch', val ) }
								placeholder="main"
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Access Token', 'static-export-wp' ) }
								value={ form.deploy_git_token || '' }
								onChange={ ( val ) => updateField( 'deploy_git_token', val ) }
								type="password"
								help={ __( 'Personal access token or fine-grained token with push access. Leave empty for public repos.', 'static-export-wp' ) }
							/>
							<p className="sewp-settings__deploy-hint">
								{ __( 'Platforms like GitHub Pages, Netlify, Vercel, and Cloudflare Pages can auto-deploy from this repository.', 'static-export-wp' ) }
							</p>
						</>
					) }

					{ form.deploy_method === 'netlify' && (
						<>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Personal Access Token', 'static-export-wp' ) }
								value={ form.deploy_netlify_token || '' }
								onChange={ ( val ) => updateField( 'deploy_netlify_token', val ) }
								type="password"
								help={ __( 'Netlify personal access token from User Settings > Applications.', 'static-export-wp' ) }
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Site ID', 'static-export-wp' ) }
								value={ form.deploy_netlify_site_id || '' }
								onChange={ ( val ) => updateField( 'deploy_netlify_site_id', val ) }
								help={ __( 'Found in Site Settings > General > Site ID.', 'static-export-wp' ) }
							/>
						</>
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
						label={ __( 'Auto-Export on Publish', 'static-export-wp' ) }
						help={ __( 'Automatically run a background export when content is published or updated.', 'static-export-wp' ) }
						checked={ !! form.auto_export_on_publish }
						onChange={ ( val ) => updateField( 'auto_export_on_publish', val ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'WebP Image Optimization', 'static-export-wp' ) }
						help={ __( 'Convert JPEG and PNG images to WebP during export for smaller file sizes.', 'static-export-wp' ) }
						checked={ !! form.image_optimization }
						onChange={ ( val ) => updateField( 'image_optimization', val ) }
					/>

					{ !! form.image_optimization && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Image Quality', 'static-export-wp' ) }
							value={ form.image_quality || 80 }
							onChange={ ( val ) => updateField( 'image_quality', val ) }
							min={ 1 }
							max={ 100 }
							help={ __( 'WebP compression quality. Lower = smaller files. 80 is a good balance.', 'static-export-wp' ) }
						/>
					) }

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Minify CSS', 'static-export-wp' ) }
						help={ __( 'Remove whitespace and comments from CSS files during export.', 'static-export-wp' ) }
						checked={ !! form.minify_css }
						onChange={ ( val ) => updateField( 'minify_css', val ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Minify JS', 'static-export-wp' ) }
						help={ __( 'Remove whitespace and comments from JavaScript files during export.', 'static-export-wp' ) }
						checked={ !! form.minify_js }
						onChange={ ( val ) => updateField( 'minify_js', val ) }
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

					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Webhook URL', 'static-export-wp' ) }
						value={ form.webhook_url || '' }
						onChange={ ( val ) => {
							updateField( 'webhook_url', val );
							setWebhookResult( null );
						} }
						placeholder="https://hooks.slack.com/services/..."
						help={ __( 'POST a JSON payload to this URL on export completion or failure.', 'static-export-wp' ) }
					/>

					{ !! form.webhook_url && (
						<>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Webhook Secret', 'static-export-wp' ) }
								value={ form.webhook_secret || '' }
								onChange={ ( val ) => updateField( 'webhook_secret', val ) }
								type="password"
								help={ __( 'Optional. Used to sign payloads with HMAC-SHA256 (X-SEWP-Signature header).', 'static-export-wp' ) }
							/>

							<fieldset className="sewp-settings__webhook-events">
								<legend>{ __( 'Webhook Events', 'static-export-wp' ) }</legend>
								{ WEBHOOK_EVENT_OPTIONS.map( ( opt ) => (
									<CheckboxControl
										__nextHasNoMarginBottom
										key={ opt.value }
										label={ opt.label }
										checked={ ( form.webhook_events || [] ).includes( opt.value ) }
										onChange={ ( checked ) => {
											const current = form.webhook_events || [];
											const updated = checked
												? [ ...current, opt.value ]
												: current.filter( ( e ) => e !== opt.value );
											updateField( 'webhook_events', updated );
										} }
									/>
								) ) }
							</fieldset>

							<div className="sewp-settings__webhook-test">
								<Button
									variant="secondary"
									onClick={ handleTestWebhook }
									isBusy={ webhookTesting }
									disabled={ webhookTesting }
								>
									{ __( 'Send Test', 'static-export-wp' ) }
								</Button>
								{ webhookResult && (
									<Notice
										status={ webhookResult.success ? 'success' : 'error' }
										isDismissible={ false }
										className="sewp-settings__webhook-result"
									>
										{ webhookResult.message }
									</Notice>
								) }
							</div>
						</>
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

			<Card>
				<CardHeader>
					<h2>{ __( 'Hosting Files', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( '_redirects', 'static-export-wp' ) }
						value={ form.redirects_content || '' }
						onChange={ ( val ) => updateField( 'redirects_content', val ) }
						help={ __( 'Written as _redirects in the export root. One rule per line, e.g. /old-path /new-path 301', 'static-export-wp' ) }
						rows={ 6 }
						className="sewp-settings__code-textarea"
					/>

					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( '_headers', 'static-export-wp' ) }
						value={ form.headers_content || '' }
						onChange={ ( val ) => updateField( 'headers_content', val ) }
						help={ __( 'Written as _headers in the export root. Use Netlify/Cloudflare Pages header syntax.', 'static-export-wp' ) }
						rows={ 6 }
						className="sewp-settings__code-textarea"
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
