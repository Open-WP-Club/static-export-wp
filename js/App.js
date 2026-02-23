import { useState } from '@wordpress/element';
import Header from './components/Header';
import Dashboard from './components/Dashboard';
import Settings from './components/Settings';
import ExportProgress from './components/ExportProgress';
import ExportLog from './components/ExportLog';
import BrokenLinks from './components/BrokenLinks';

const TABS = [
	{ name: 'dashboard', title: 'Dashboard' },
	{ name: 'export', title: 'Export' },
	{ name: 'settings', title: 'Settings' },
	{ name: 'log', title: 'History' },
	{ name: 'broken-links', title: 'Broken Links' },
];

export default function App() {
	const [ activeTab, setActiveTab ] = useState( 'dashboard' );

	return (
		<div className="sewp-admin">
			<Header
				tabs={ TABS }
				activeTab={ activeTab }
				onTabChange={ setActiveTab }
			/>
			<div className="sewp-admin__content">
				{ activeTab === 'dashboard' && (
					<Dashboard onNavigate={ setActiveTab } />
				) }
				{ activeTab === 'export' && <ExportProgress /> }
				{ activeTab === 'settings' && <Settings /> }
				{ activeTab === 'log' && <ExportLog /> }
			{ activeTab === 'broken-links' && <BrokenLinks /> }
			</div>
		</div>
	);
}
