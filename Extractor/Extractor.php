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
use SplFileInfo;
use Syrup\ComponentBundle\Exception\ApplicationException;
use Syrup\ComponentBundle\Exception\UserException;
use Syrup\ComponentBundle\Filesystem\Temp;

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

	/** @var Temp */
	protected $temp;

	public function __construct(RestApi $driveApi, Logger $logger, Temp $temp)
	{
		$this->driveApi = $driveApi;
		$this->logger = $logger;
		$this->temp = $temp;
	}

	public function setConfiguration(Configuration $configuration)
	{
		$this->configuration = $configuration;
	}

	public function run($options = null)
	{
		$this->dataManager = new DataManager($this->configuration, $this->temp);

		$accounts = $this->configuration->getAccounts();

		if (isset($options['account']) || isset($options['config'])) {

			$accountId = isset($options['account'])?$options['account']:$options['config'];

			if (!isset($accounts[$accountId])) {
				throw new ConfigurationException("Account '" . $accountId . "' does not exist.");
			}
			$accounts = array(
				$accountId => $accounts[$accountId]
			);
		}

		/** @var Account $account */
		foreach ($accounts as $accountId => $account) {

			$this->currAccountId = $accountId;

			$this->driveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());
			$this->driveApi->getApi()->setRefreshTokenCallback(array($this, 'refreshTokenCallback'));

			/** @var Sheet $sheet */
			foreach ($account->getSheets() as $sheet) {

				$meta = $this->driveApi->getFile($sheet->getGoogleId());

				if (!isset($meta['exportLinks'])) {
					$e = new ApplicationException("ExportLinks missing in file resource");
					$e->setData([
						'fileMetadata'  => $meta
					]);
					throw $e;
				}

				$exportLink = str_replace('pdf', 'csv', $meta['exportLinks']['application/pdf']) . '&gid=' . $sheet->getSheetId();

				try {
					$data = $this->driveApi->export($exportLink);

					if (!empty($data)) {
						$this->dataManager->save($data, $sheet);
					} else {
						$status[$accountId][$sheet->getSheetTitle()] = "file is empty";
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

		return array(
			"status"    => "ok"
		);
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
