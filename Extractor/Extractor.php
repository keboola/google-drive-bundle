<?php
/**
 * Extractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Exception\ConfigurationException;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;
use Monolog\Logger;
use Syrup\ComponentBundle\Filesystem\TempService;

class Extractor
{
	/** @var RestApi */
	protected $driveApi;

	/** @var Configuration */
	protected $configuration;

	/** @var DataManager */
	protected $dataManager;

	protected $currAccountId;

	/** @var Logger */
	protected $logger;

	public function __construct(RestApi $driveApi, $configuration, Logger $logger, TempService $temp)
	{
		$this->driveApi = $driveApi;
		$this->configuration = $configuration;
		$this->logger = $logger;
		$this->dataManager = new DataManager($configuration, $temp);
	}

	public function run($options = null)
	{
		$accounts = $this->configuration->getAccounts();

		if (isset($options['account'])) {
			if (!isset($account[$options['account']])) {
				throw new ConfigurationException("Account '" . $options['account'] . "' does not exist.");
			}
			$accounts = array(
				$options['account'] => $account[$options['account']]
			);
		}

		$status = array();

		/** @var Account $account */
		foreach ($accounts as $accountId => $account) {

			$this->currAccountId = $accountId;

			$this->driveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());
			$this->driveApi->getApi()->setRefreshTokenCallback(array($this, 'refreshTokenCallback'));

			/** @var Sheet $sheet */
			foreach ($account->getSheets() as $sheet) {

				$status[$accountId][$sheet->getTitle()] = 'ok';
				try {
					$data = $this->driveApi->exportSpreadsheet($sheet->getGoogleId(), $sheet->getSheetId());
					if (!empty($data)) {
						$this->dataManager->save($data, $sheet);
					} else {
						$status = "file is empty";
					}
				} catch (\Exception $e) {
					$status[$accountId][$sheet->getTitle()] = array('error' => $e->getMessage());

					$this->logger->warn("Error while importing sheet", array(
						'exception' => $e,
						'accountId' => $accountId,
						'sheet'     => $sheet->toArray()
					));
				}
			}
		}

		return $status;
	}

	public function setCurrAccountId($id)
	{
		$this->currAccountId = $id;
	}

	public function getCurrAccountId()
	{
		return $this->currAccountId;
	}

	public function refreshTokenCallback($accessToken, $refreshToken)
	{
		$account = $this->configuration->getAccountBy('accountId', $this->currAccountId);
		$account->setAccessToken($accessToken);
		$account->setRefreshToken($refreshToken);
		$account->save();
	}

}
