import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api';
import useExportStatus from '../hooks/useExportStatus';

export default function Dashboard( { onNavigate } ) {
	const { status, refresh } = useExportStatus( true );
	const [ starting, setStarting ] = useState( false );
	const [ discovering, setDiscovering ] = useState( false );
	const [ urlCount, setUrlCount ] = useState( null );
	const [ error, setError ] = useState( null );

	const isRunning = status?.status === 'running';

	const handleStart = async () => {
		setStarting( true );
		setError( null );
		try {
			await api.startExport();
			await refresh();
			onNavigate( 'export' );
		} catch ( err ) {
			setError( err.message || 'Failed to start export.' );
		}
		setStarting( false );
	};

	const handleDiscover = async () => {
		setDiscovering( true );
		try {
			const data = await api.discoverUrls();
			setUrlCount( data.count );
		} catch {
			setUrlCount( null );
		}
		setDiscovering( false );
	};

	return (
		<div className="sewp-dashboard">
			{ error && (
				<Notice status="error" isDismissible onDismiss={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<h2>{ __( 'Quick Actions', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					<div className="sewp-dashboard__actions">
						<Button
							variant="primary"
							onClick={ handleStart }
							isBusy={ starting }
							disabled={ starting || isRunning }
						>
							{ isRunning
								? __( 'Export Running...', 'static-export-wp' )
								: __( 'Start Export', 'static-export-wp' ) }
						</Button>

						<Button
							variant="secondary"
							onClick={ handleDiscover }
							isBusy={ discovering }
							disabled={ discovering }
						>
							{ __( 'Preview URLs', 'static-export-wp' ) }
						</Button>

						<Button
							variant="tertiary"
							onClick={ () => onNavigate( 'settings' ) }
						>
							{ __( 'Settings', 'static-export-wp' ) }
						</Button>
					</div>

					{ urlCount !== null && (
						<p className="sewp-dashboard__url-count">
							{ /* translators: %d: number of URLs */ }
							{ sprintf( __( 'Found %d URLs to export.', 'static-export-wp' ), urlCount ) }
						</p>
					) }
				</CardBody>
			</Card>

			{ status && status.status !== 'idle' && (
				<Card className="sewp-dashboard__status">
					<CardHeader>
						<h2>{ __( 'Last Export', 'static-export-wp' ) }</h2>
					</CardHeader>
					<CardBody>
						<dl className="sewp-dashboard__status-grid">
							<dt>{ __( 'Status', 'static-export-wp' ) }</dt>
							<dd><span className={ `sewp-status sewp-status--${ status.status }` }>{ status.status }</span></dd>

							<dt>{ __( 'Progress', 'static-export-wp' ) }</dt>
							<dd>{ ( status.completed || 0 ) } / { status.total || 0 }</dd>

							{ status.failed > 0 && (
								<>
									<dt>{ __( 'Failed', 'static-export-wp' ) }</dt>
									<dd>{ status.failed }</dd>
								</>
							) }

							{ status.started_at && (
								<>
									<dt>{ __( 'Started', 'static-export-wp' ) }</dt>
									<dd>{ status.started_at }</dd>
								</>
							) }
						</dl>
						{ ( status.status === 'completed' || status.status === 'failed' ) && (
							<div className="sewp-dashboard__post-export">
								{ window.sewpConfig?.downloadUrl && (
									<Button
										variant="secondary"
										href={ window.sewpConfig.downloadUrl }
										className="sewp-dashboard__download"
									>
										{ __( 'Download ZIP', 'static-export-wp' ) }
									</Button>
								) }
								{ window.sewpConfig?.previewUrl && (
									<Button
										variant="secondary"
										href={ window.sewpConfig.previewUrl }
										target="_blank"
										rel="noopener noreferrer"
									>
										{ __( 'Preview Site', 'static-export-wp' ) }
									</Button>
								) }
							</div>
						) }
					</CardBody>
				</Card>
			) }
		</div>
	);
}
