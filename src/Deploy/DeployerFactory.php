<?php

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Core\Settings;
use StaticExportWP\Utility\Logger;

final class DeployerFactory {

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
