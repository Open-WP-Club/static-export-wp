import apiFetch from '@wordpress/api-fetch';

const BASE = 'sewp/v1';

export const api = {
	getSettings() {
		return apiFetch( { path: `${ BASE }/settings` } );
	},

	saveSettings( settings ) {
		return apiFetch( {
			path: `${ BASE }/settings`,
			method: 'POST',
			data: settings,
		} );
	},

	startExport() {
		return apiFetch( {
			path: `${ BASE }/export/start`,
			method: 'POST',
		} );
	},

	cancelExport() {
		return apiFetch( {
			path: `${ BASE }/export/cancel`,
			method: 'POST',
		} );
	},

	getExportStatus() {
		return apiFetch( { path: `${ BASE }/export/status` } );
	},

	getExportLog( page = 1 ) {
		return apiFetch( { path: `${ BASE }/export/log?page=${ page }` } );
	},

	discoverUrls() {
		return apiFetch( { path: `${ BASE }/export/discover-urls` } );
	},

	cleanExport() {
		return apiFetch( {
			path: `${ BASE }/export/clean`,
			method: 'POST',
		} );
	},
};
