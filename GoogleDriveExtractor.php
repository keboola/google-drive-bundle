<?php
/**
 * GoogleDriveExtractor.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 24.6.13
 */

namespace Keboola\Google\DriveBundle;

use Guzzle\Http\Message\Response;
use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Exception\ParameterMissingException;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\Google\DriveBundle\Extractor\Extractor;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;
use Keboola\StorageApi\Client;
use Syrup\ComponentBundle\Component\Component;

class GoogleDriveExtractor extends Component
{
	protected $_name = 'googleDrive';
	protected $_prefix = 'ex';

	/** @var Configuration */
	protected $configuration;

	/** @var Extractor */
	protected $extractor;

	public function __construct(Client $storageApi, $log)
	{
		$this->configuration = new Configuration($storageApi, $this->getFullName());
		parent::__construct($storageApi, $log);
	}

	protected function checkParams($required, $params)
	{
		foreach ($required as $r) {
			if (!isset($params[$r])) {
				throw new ParameterMissingException(sprintf("Parameter %s is missing.", $r));
			}
		}
	}

	public function postRun($params)
	{
		/** @var RestApi $googleDriveApi */
		$googleDriveApi = $this->_container->get('google_drive_rest_api');
		$this->extractor = new Extractor($googleDriveApi, $this->configuration);
		$status = $this->extractor->run();

		return array(
			'sheets'    => $status
		);
	}

	public function getAccount($params)
	{
		$this->checkParams(array('accountId'), $params);

		$account = $this->configuration->getAccountBy('accountId', $params['accountId'], true);

		return array(
			'account' => $account
		);
	}

	public function getAccounts($params)
	{
		$accounts = $this->configuration->getAccounts(true);

		return array(
			'accounts'  => $accounts
		);
	}

	public function postAccount($params)
	{
		$this->checkParams(
			array(
				'googleId',
				'name',
				'email',
				'accessToken',
				'refreshToken'
			),
			$params
		);

		if (!$this->configuration->exists()) {
			$this->configuration->create();
		}

		if (null != $this->configuration->getAccountBy('googleId', $params['googleId'])) {
			throw new \Exception('Account already exists');
		}

		$this->configuration->addAccount($params);
	}

	public function deleteAccount($params)
	{
		$this->checkParams(
			array(
				'accountId'
			),
			$params
		);

		$this->configuration->removeAccount($params['accountId']);
	}

	public function getFiles($params)
	{
		$this->checkParams(array(
			'accountId'
		), $params);

		/** @var RestApi $googleDriveApi */
		$googleDriveApi = $this->_container->get('google_drive_rest_api');

		/** @var Account $account */
		$account = $this->configuration->getAccountBy('accountId', $params['accountId']);

		$googleDriveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

		/** @var Response $response */
		$response = null;
		if (isset($params['mimeType'])) {
			$response = $googleDriveApi->getFilesByOwner($account->getEmail(), $params['mimeType']);
		} else {
			$response = $googleDriveApi->getFilesByOwner($account->getEmail());
		}

		$responseJson = $response->json();

		return array(
			'files' => $responseJson['items']
		);
	}

	/**
	 * Sheets
	 */

	/**
	 * @param $params
	 * @return array
	 */
	public function getSheets($params)
	{
		$this->checkParams(array(
			'fileId',
			'accountId'
		), $params);

		/** @var Account $account */
		$account = $this->configuration->getAccountBy('accountId', $params['accountId']);

		/** @var RestApi $googleDriveApi */
		$googleDriveApi = $this->_container->get('google_drive_rest_api');

		$googleDriveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

		/** @var Response $response */
		$response = null;
		$response = $googleDriveApi->getWorksheets($params['fileId']);

		return array(
			'sheets' => $response
		);
	}

	/**
	 * @param $params
	 */
	public function postSheets($params)
	{
		if (isset($params['data'])) {
			foreach ($params['data'] as $sheet) {
				$this->checkParams(array(
					'accountId',
					'googleId',
					'title',
					'sheetId',
					'sheetTitle'
				), $sheet);

				$this->configuration->addSheet($sheet);
			}
		} else {
			$this->checkParams(array(
				'accountId',
				'googleId',
				'title',
				'sheetId',
				'sheetTitle'
			), $params);

			$this->configuration->addSheet($params);
		}
	}

	public function deleteSheets($params)
	{
		$this->checkParams(array(
			'accountId',
			'fileId',
			'sheetId'
		), $params);

		$this->configuration->removeSheet($params['accountId'], $params['fileId'], $params['sheetId']);
	}

}
