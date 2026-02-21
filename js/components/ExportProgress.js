import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';
import useExportStatus from '../hooks/useExportStatus';

export default function ExportProgress() {
	const [ starting, setStarting ] = useState( false );
	const [ cancelling, setCancelling ] = useState( false );
	const [ error, setError ] = useState( null );

	const { status, refresh } = useExportStatus( true );

	const isRunning = status?.status === 'running';
	const isCompleted = status?.status === 'completed' || status?.status === 'failed';
	const isIdle = ! status || status.status === 'idle';
	const total = status?.total || 0;
	const completed = status?.completed || 0;
	const failed = status?.failed || 0;
	const percent = total > 0 ? Math.round( ( completed / total ) * 100 ) : 0;

	const handleStart = async () => {
		setStarting( true );
		setError( null );
		try {
			await api.startExport();
			await refresh();
		} catch ( err ) {
			setError( err.message || 'Failed to start export.' );
		}
		setStarting( false );
	};

	const handleCancel = async () => {
		setCancelling( true );
		try {
			await api.cancelExport();
			await refresh();
		} catch ( err ) {
			setError( err.message || 'Failed to cancel export.' );
		}
		setCancelling( false );
	};

	const handleClean = async () => {
		if ( ! window.confirm( __( 'Delete the exported files?', 'static-export-wp' ) ) ) {
			return;
		}
		try {
			await api.cleanExport();
			setError( null );
		} catch ( err ) {
			setError( err.message || 'Failed to clean export.' );
		}
	};

	return (
		<div className="sewp-export">
			{ error && (
				<Notice status="error" isDismissible onDismiss={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<h2>{ __( 'Export', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					{ isIdle && (
						<div className="sewp-export__idle">
							<p>{ __( 'No export is currently running.', 'static-export-wp' ) }</p>
							<Button
								variant="primary"
								onClick={ handleStart }
								isBusy={ starting }
								disabled={ starting }
							>
								{ __( 'Start Export', 'static-export-wp' ) }
							</Button>
						</div>
					) }

					{ ! isIdle && (
						<div className="sewp-export__progress">
							<div className="sewp-export__bar-wrapper">
								<div className="sewp-export__bar">
									<div
										className="sewp-export__bar-fill"
										style={ { width: `${ percent }%` } }
									/>
								</div>
								<span className="sewp-export__percent">{ percent }%</span>
							</div>

							<dl className="sewp-export__stats">
								<dt>{ __( 'Status', 'static-export-wp' ) }</dt>
								<dd>
									<span className={ `sewp-status sewp-status--${ status.status }` }>
										{ status.status }
									</span>
								</dd>

								<dt>{ __( 'Completed', 'static-export-wp' ) }</dt>
								<dd>{ completed } / { total }</dd>

								{ failed > 0 && (
									<>
										<dt>{ __( 'Failed', 'static-export-wp' ) }</dt>
										<dd>{ failed }</dd>
									</>
								) }

								{ status?.current_url && isRunning && (
									<>
										<dt>{ __( 'Current', 'static-export-wp' ) }</dt>
										<dd className="sewp-export__current-url">{ status.current_url }</dd>
									</>
								) }
							</dl>

							<div className="sewp-export__actions">
								{ isRunning && (
									<Button
										variant="secondary"
										isDestructive
										onClick={ handleCancel }
										isBusy={ cancelling }
										disabled={ cancelling }
									>
										{ __( 'Cancel Export', 'static-export-wp' ) }
									</Button>
								) }

								{ ! isRunning && (
									<>
										<Button
											variant="primary"
											onClick={ handleStart }
											isBusy={ starting }
											disabled={ starting }
										>
											{ __( 'New Export', 'static-export-wp' ) }
										</Button>
										{ isCompleted && window.sewpConfig?.downloadUrl && (
											<Button
												variant="secondary"
												href={ window.sewpConfig.downloadUrl }
											>
												{ __( 'Download ZIP', 'static-export-wp' ) }
											</Button>
										) }
										{ isCompleted && window.sewpConfig?.previewUrl && (
											<Button
												variant="secondary"
												href={ window.sewpConfig.previewUrl }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __( 'Preview Site', 'static-export-wp' ) }
											</Button>
										) }
										<Button
											variant="secondary"
											isDestructive
											onClick={ handleClean }
										>
											{ __( 'Clean Output', 'static-export-wp' ) }
										</Button>
									</>
								) }
							</div>
						</div>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
