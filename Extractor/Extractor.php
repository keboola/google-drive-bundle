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
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\Google\DriveBundle\Extractor\DataManager;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;

class Extractor
{
	/** @var RestApi */
	protected $driveApi;

	/** @var Configuration */
	protected $configuration;

	/** @var DataManager */
	protected $dataManager;

	public function __construct(RestApi $driveApi, $configuration)
	{
		$this->driveApi = $driveApi;
		$this->configuration = $configuration;
		$this->dataManager = new DataManager($configuration);
	}

	public function run($options = null)
	{
		$accounts = $this->configuration->getAccounts();

		$status = array();

		/** @var Account $account */
		foreach ($accounts as $accountId => $account) {

			$this->driveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

			/** @var Sheet $sheet */
			foreach ($account->getSheets() as $sheet) {

				$status[$accountId][$sheet->getTitle()] = 'ok';
				try {
					$data = $this->driveApi->exportSpreadsheet($sheet->getGoogleId(), $sheet->getSheetId());
					$this->dataManager->save($data, $sheet);
				} catch (\Exception $e) {
					$status[$accountId][$sheet->getTitle()] = array('error' => $e->getMessage());
				}
			}
		}

		return $status;
	}

}
