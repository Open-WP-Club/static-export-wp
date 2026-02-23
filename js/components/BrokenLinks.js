import { useState, useEffect } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';

export default function BrokenLinks() {
	const [ links, setLinks ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ exportId, setExportId ] = useState( null );

	useEffect( () => {
		const fetch = async () => {
			try {
				// Get the latest export to find its export_id.
				const log = await api.getExportLog( 1 );
				const latest = log?.logs?.[ 0 ];

				if ( ! latest?.export_id ) {
					setLoading( false );
					return;
				}

				setExportId( latest.export_id );
				const result = await api.getBrokenLinks( latest.export_id );
				setLinks( result?.links || [] );
			} catch {
				setLinks( [] );
			}
			setLoading( false );
		};

		fetch();
	}, [] );

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div className="sewp-broken-links">
			<Card>
				<CardHeader>
					<h2>{ __( 'Broken Links', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					{ ! exportId && (
						<p>{ __( 'No exports found. Run an export first.', 'static-export-wp' ) }</p>
					) }
					{ exportId && links.length === 0 && (
						<p>{ __( 'No broken links found in the latest export.', 'static-export-wp' ) }</p>
					) }
					{ links.length > 0 && (
						<table className="sewp-broken-links__table widefat striped">
							<thead>
								<tr>
									<th>{ __( 'URL', 'static-export-wp' ) }</th>
									<th>{ __( 'Status', 'static-export-wp' ) }</th>
									<th>{ __( 'Referrer', 'static-export-wp' ) }</th>
									<th>{ __( 'Error', 'static-export-wp' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ links.map( ( link, i ) => (
									<tr key={ i }>
										<td className="sewp-broken-links__url">
											<code>{ link.url }</code>
										</td>
										<td>
											<span className="sewp-status sewp-status--failed">
												{ link.http_status }
											</span>
										</td>
										<td className="sewp-broken-links__referrer">
											{ link.referrer ? (
												<code>{ link.referrer }</code>
											) : (
												<span className="sewp-broken-links__na">—</span>
											) }
										</td>
										<td>{ link.error_message || '—' }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
