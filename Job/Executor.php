<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 13/06/14
 * Time: 12:20
 */

namespace Keboola\Google\DriveBundle\Job;


use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\Google\DriveBundle\Extractor\Extractor;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;
use Keboola\StorageApi\Client as SapiClient;
use Monolog\Logger;
use Syrup\ComponentBundle\Filesystem\Temp;
use Syrup\ComponentBundle\Job\Executor as BaseExecutor;
use Syrup\ComponentBundle\Job\ExecutorInterface;
use Syrup\ComponentBundle\Job\Metadata\Job;
use Syrup\ComponentBundle\Encryption\Encryptor;

class Executor extends BaseExecutor
{
	/** @var Configuration  */
	protected $configuration;

	/** @var Extractor */
	protected $extractor;

	public function __construct(Configuration $configuration, Extractor $extractor)
	{
		$this->extractor = $extractor;
		$this->configuration = $configuration;
	}

	protected function initConfiguration()
	{
		$this->configuration->setStorageApi($this->storageApi);
		return $this->configuration;
	}

	public function execute(Job $job)
	{
		$this->extractor->setConfiguration($this->initConfiguration());

		// @todo get params from job
		$status = $this->extractor->run(array('account' => 'new3'));
	}

	/**
	 * @param Account $account
	 * @internal param $accessToken
	 * @internal param $refreshToken
	 * @return RestApi
	 */
	protected function getApi(Account $account)
	{
//		$googleDriveApi->getApi()->setRefreshTokenCallback(array($this->extractor, 'refreshTokenCallback'));

//		return $googleDriveApi;
	}
}
