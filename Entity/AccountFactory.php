<?php
/**
 * TableFactory.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 27.6.13
 */

namespace Keboola\Google\DriveBundle\Entity;


use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Extractor\Configuration;

class AccountFactory
{
	protected $configuration;

	public function __construct(Configuration $configuration)
	{
		$this->configuration = $configuration;
	}

	public function get($accountId)
	{
		return new Account($this->configuration, $accountId);
	}

}
