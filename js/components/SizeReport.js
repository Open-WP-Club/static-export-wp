import { Card, CardHeader, CardBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useSizeReport from '../hooks/useSizeReport';

const CATEGORIES = [
	{ key: 'html', label: 'HTML', color: '#2271b1' },
	{ key: 'css', label: 'CSS', color: '#9b59b6' },
	{ key: 'js', label: 'JS', color: '#f0b849' },
	{ key: 'images', label: __( 'Images', 'static-export-wp' ), color: '#00a32a' },
	{ key: 'fonts', label: __( 'Fonts', 'static-export-wp' ), color: '#d63638' },
	{ key: 'other', label: __( 'Other', 'static-export-wp' ), color: '#a7aaad' },
];

function formatBytes( bytes ) {
	if ( bytes === 0 ) {
		return '0 B';
	}
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	const i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
	const value = bytes / Math.pow( 1024, i );
	return `${ value.toFixed( i === 0 ? 0 : 1 ) } ${ units[ i ] }`;
}

function SizeBreakdown( { report } ) {
	const total = report.total || 0;

	if ( total === 0 ) {
		return (
			<p className="sewp-size-report__empty">
				{ __( 'No size data yet.', 'static-export-wp' ) }
			</p>
		);
	}

	return (
		<div className="sewp-size-breakdown">
			<div className="sewp-size-bar" role="img" aria-label={ __( 'Size breakdown bar', 'static-export-wp' ) }>
				{ CATEGORIES.map( ( cat ) => {
					const size = report[ cat.key ] || 0;
					const pct = ( size / total ) * 100;
					if ( pct < 0.5 ) {
						return null;
					}
					return (
						<div
							key={ cat.key }
							className="sewp-size-bar__segment"
							style={ { width: `${ pct }%`, backgroundColor: cat.color } }
							title={ `${ cat.label }: ${ formatBytes( size ) }` }
						/>
					);
				} ) }
			</div>
			<div className="sewp-size-legend">
				{ CATEGORIES.map( ( cat ) => {
					const size = report[ cat.key ] || 0;
					if ( size === 0 ) {
						return null;
					}
					return (
						<span key={ cat.key } className="sewp-size-legend__item">
							<span
								className="sewp-size-legend__dot"
								style={ { backgroundColor: cat.color } }
							/>
							{ cat.label } — { formatBytes( size ) }
						</span>
					);
				} ) }
				<span className="sewp-size-legend__total">
					{ __( 'Total', 'static-export-wp' ) }: { formatBytes( total ) }
				</span>
			</div>
		</div>
	);
}

function SizeTrend( { exports } ) {
	if ( exports.length < 2 ) {
		return null;
	}

	// Chronological order (API returns newest first).
	const sorted = [ ...exports ].reverse();

	const totals = sorted.map( ( e ) => e.size_report?.total || 0 );
	const maxVal = Math.max( ...totals );
	if ( maxVal === 0 ) {
		return null;
	}

	const W = 400;
	const H = 120;
	const PAD_X = 40;
	const PAD_Y = 16;

	const plotW = W - PAD_X * 2;
	const plotH = H - PAD_Y * 2;
	const stepX = totals.length > 1 ? plotW / ( totals.length - 1 ) : 0;

	const points = totals.map( ( val, i ) => {
		const x = PAD_X + i * stepX;
		const y = PAD_Y + plotH - ( val / maxVal ) * plotH;
		return `${ x },${ y }`;
	} ).join( ' ' );

	// Y-axis labels.
	const yLabels = [ 0, maxVal / 2, maxVal ].map( ( val, i ) => ( {
		y: PAD_Y + plotH - ( val / maxVal ) * plotH,
		label: formatBytes( val ),
		key: i,
	} ) );

	// X-axis labels — show first and last date.
	const formatDate = ( dateStr ) => {
		if ( ! dateStr ) {
			return '';
		}
		const d = new Date( dateStr );
		return `${ d.getMonth() + 1 }/${ d.getDate() }`;
	};

	return (
		<div className="sewp-size-trend">
			<h4 className="sewp-size-trend__title">
				{ __( 'Total Size Trend', 'static-export-wp' ) }
			</h4>
			<svg viewBox={ `0 0 ${ W } ${ H }` } className="sewp-size-trend__svg" aria-label={ __( 'Size trend graph', 'static-export-wp' ) }>
				{ /* Grid lines */ }
				{ yLabels.map( ( l ) => (
					<line
						key={ l.key }
						x1={ PAD_X }
						y1={ l.y }
						x2={ W - PAD_X }
						y2={ l.y }
						stroke="#e0e0e0"
						strokeWidth="1"
					/>
				) ) }

				{ /* Y-axis labels */ }
				{ yLabels.map( ( l ) => (
					<text
						key={ `t${ l.key }` }
						x={ PAD_X - 4 }
						y={ l.y + 3 }
						textAnchor="end"
						fontSize="9"
						fill="#50575e"
					>
						{ l.label }
					</text>
				) ) }

				{ /* Trend line */ }
				<polyline
					points={ points }
					fill="none"
					stroke="#2271b1"
					strokeWidth="2"
					strokeLinejoin="round"
				/>

				{ /* Data points */ }
				{ totals.map( ( val, i ) => {
					const x = PAD_X + i * stepX;
					const y = PAD_Y + plotH - ( val / maxVal ) * plotH;
					return (
						<circle
							key={ i }
							cx={ x }
							cy={ y }
							r="3"
							fill="#2271b1"
						>
							<title>{ formatBytes( val ) }</title>
						</circle>
					);
				} ) }

				{ /* X-axis labels */ }
				<text x={ PAD_X } y={ H - 2 } textAnchor="middle" fontSize="9" fill="#50575e">
					{ formatDate( sorted[ 0 ]?.completed_at ) }
				</text>
				<text x={ W - PAD_X } y={ H - 2 } textAnchor="middle" fontSize="9" fill="#50575e">
					{ formatDate( sorted[ sorted.length - 1 ]?.completed_at ) }
				</text>
			</svg>
		</div>
	);
}

export default function SizeReport() {
	const { data, loading } = useSizeReport();

	if ( loading ) {
		return (
			<Card className="sewp-size-report">
				<CardHeader>
					<h2>{ __( 'Export Size Report', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	if ( ! data || data.length === 0 ) {
		return (
			<Card className="sewp-size-report">
				<CardHeader>
					<h2>{ __( 'Export Size Report', 'static-export-wp' ) }</h2>
				</CardHeader>
				<CardBody>
					<p className="sewp-size-report__empty">
						{ __( 'No size data yet. Run an export to see the breakdown.', 'static-export-wp' ) }
					</p>
				</CardBody>
			</Card>
		);
	}

	const latest = data[ 0 ];

	return (
		<Card className="sewp-size-report">
			<CardHeader>
				<h2>{ __( 'Export Size Report', 'static-export-wp' ) }</h2>
			</CardHeader>
			<CardBody>
				<SizeBreakdown report={ latest.size_report } />
				<SizeTrend exports={ data } />
			</CardBody>
		</Card>
	);
}
