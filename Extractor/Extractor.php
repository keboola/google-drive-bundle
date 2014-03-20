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
use Syrup\ComponentBundle\Exception\UserException;
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
			if (!isset($accounts[$options['account']])) {
				throw new ConfigurationException("Account '" . $options['account'] . "' does not exist.");
			}
			$accounts = array(
				$options['account'] => $accounts[$options['account']]
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

				$meta = $this->driveApi->getFile($sheet->getGoogleId());
				$exportLink = str_replace('pdf', 'csv', $meta['exportLinks']['application/pdf']) . '&gid=' . $sheet->getSheetId();

				try {
					$data = $this->driveApi->export($exportLink);

					if (!empty($data)) {
						$this->dataManager->save($data, $sheet);
					} else {
						$status = "file is empty";
					}
				} catch (\Exception $e) {
					$userException = new UserException("Error importing sheet '" . $sheet->getGoogleId() . "-".$sheet->getSheetId()."'. " . $e->getMessage(), $e);
					$userException->setData(array(
						'accountId' => $accountId,
						'sheet'     => $sheet->toArray()
					));
					throw $userException;
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
