<?php
/**
 * Builds the configured Deployer instance for the current settings.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Core\Settings;
use StaticExportWP\Utility\Logger;

/**
 * Instantiates the Deployer implementation matching the deploy_method setting.
 */
final class DeployerFactory {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings accessor.
	 * @param Logger   $logger   Logger passed to the created deployer.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly Logger $logger,
	) {}

	/**
	 * Create a deployer based on the current deploy_method setting.
	 *
	 * @return Deployer|null Null when deploy method is 'none'.
	 */
	public function create(): ?Deployer {
		$method = $this->settings->get( 'deploy_method', 'none' );

		return match ( $method ) {
			'command' => new CommandDeployer( $this->settings, $this->logger ),
			'git'     => new GitDeployer( $this->settings, $this->logger ),
			'netlify' => new NetlifyDeployer( $this->settings, $this->logger ),
			default   => null,
		};
	}
}
