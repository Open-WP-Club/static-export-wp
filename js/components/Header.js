import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Header( { tabs, activeTab, onTabChange } ) {
	return (
		<div className="sewp-header">
			<div className="sewp-header__title">
				<h1>{ __( 'Static Export', 'static-export-wp' ) }</h1>
			</div>
			<nav className="sewp-header__nav">
				{ tabs.map( ( tab ) => (
					<Button
						key={ tab.name }
						variant={ activeTab === tab.name ? 'primary' : 'secondary' }
						onClick={ () => onTabChange( tab.name ) }
						className="sewp-header__tab"
					>
						{ tab.title }
					</Button>
				) ) }
			</nav>
		</div>
	);
}
