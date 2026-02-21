import { useState, useEffect } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api';

export default function ExportLog() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ page, setPage ] = useState( 1 );

	const fetchLog = async ( p = 1 ) => {
		setLoading( true );
		try {
			const result = await api.getExportLog( p );
			setData( result );
			setPage( p );
		} catch {
			setData( null );
		}
		setLoading( false );
	};

	useEffect( () => {
		fetchLog();
	}, [] );

	if ( loading && ! data ) {
		return <Spinner />;
	}

	const logs = data?.logs || [];
	const totalPages = data?.total_pages || 1;

	return (
		<div className="sewp-log">
			<Card>
				<CardHeader>
					<h2>{ __( 'Export History', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					{ logs.length === 0 ? (
						<p>{ __( 'No exports yet.', 'static-export-wp' ) }</p>
					) : (
						<>
							<table className="sewp-log__table widefat striped">
								<thead>
									<tr>
										<th>{ __( 'Export ID', 'static-export-wp' ) }</th>
										<th>{ __( 'Status', 'static-export-wp' ) }</th>
										<th>{ __( 'Pages', 'static-export-wp' ) }</th>
										<th>{ __( 'Failed', 'static-export-wp' ) }</th>
										<th>{ __( 'Started', 'static-export-wp' ) }</th>
										<th>{ __( 'Completed', 'static-export-wp' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ logs.map( ( log ) => (
										<tr key={ log.id }>
											<td>
												<code>{ log.export_id?.substring( 0, 8 ) }</code>
											</td>
											<td>
												<span className={ `sewp-status sewp-status--${ log.status }` }>
													{ log.status }
												</span>
											</td>
											<td>{ log.completed_urls } / { log.total_urls }</td>
											<td>{ log.failed_urls }</td>
											<td>{ log.started_at || '-' }</td>
											<td>{ log.completed_at || '-' }</td>
										</tr>
									) ) }
								</tbody>
							</table>

							{ totalPages > 1 && (
								<div className="sewp-log__pagination">
									<Button
										variant="secondary"
										disabled={ page <= 1 || loading }
										onClick={ () => fetchLog( page - 1 ) }
									>
										{ __( 'Previous', 'static-export-wp' ) }
									</Button>
									<span>
										{ /* translators: %1$d: current page, %2$d: total pages */ }
										{ sprintf( __( 'Page %1$d of %2$d', 'static-export-wp' ), page, totalPages ) }
									</span>
									<Button
										variant="secondary"
										disabled={ page >= totalPages || loading }
										onClick={ () => fetchLog( page + 1 ) }
									>
										{ __( 'Next', 'static-export-wp' ) }
									</Button>
								</div>
							) }
						</>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
