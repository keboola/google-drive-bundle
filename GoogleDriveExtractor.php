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
use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Exception\ConfigurationException;
use Keboola\Google\DriveBundle\Exception\ParameterMissingException;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\Google\DriveBundle\Extractor\Extractor;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;
use Keboola\StorageApi\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

	public function getAccount($id)
	{
		return $this->configuration->getAccountBy('accountId', $id, true);
	}

	public function getAccounts()
	{
		return $this->configuration->getAccounts(true);
	}

	public function getConfigs()
	{
		$accounts = $this->configuration->getAccounts(true);

		$res = array();
		foreach ($accounts as $account) {
			$res[] = array_intersect_key($account, array_fill_keys(array('id', 'name', 'description'), 0));
		}

		return $res;
	}

	public function postConfigs($params)
	{
		$this->checkParams(
			array(
				'name'
			),
			$params
		);

		try {
			$this->configuration->exists();
		} catch (ConfigurationException $e) {
			$this->configuration->create();
		}

		if (null != $this->configuration->getAccountBy('accountName', $params['name'])) {
			throw new ConfigurationException('Account already exists');
		}

		return $this->configuration->addAccount($params);
	}

	public function deleteConfig($id)
	{
		$this->configuration->removeAccount($id);
	}

	public function postAccount($params)
	{
		$this->checkParams(
			array(
				'id',
				'googleId',
				'googleName',
				'email',
				'accessToken',
				'refreshToken'
			),
			$params
		);

		$account = $this->configuration->getAccountBy('accountId', $params['id']);
		if (null == $account) {
			throw new ConfigurationException("Account doesn't exist");
		}

		$account->fromArray($params);
		$account->save();

		return $account->toArray();
	}

	public function getFiles($accountId)
	{
		/** @var RestApi $googleDriveApi */
		$googleDriveApi = $this->_container->get('google_drive_rest_api');

		/** @var Account $account */
		$account = $this->configuration->getAccountBy('accountId', $accountId);

		$googleDriveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

		/** @var Response $response */
		$response = null;
		if (isset($params['mimeType'])) {
			$response = $googleDriveApi->getFilesByOwner($account->getEmail(), $params['mimeType']);
		} else {
			$response = $googleDriveApi->getFilesByOwner($account->getEmail());
		}

		return $response->json();
	}

	/**
	 * Sheets
	 */

	/**
	 * @param $accountId
	 * @param $fileId
	 * @internal param $params
	 * @return array
	 */
	public function getSheets($accountId, $fileId)
	{
		/** @var Account $account */
		$account = $this->configuration->getAccountBy('accountId', $accountId);

		/** @var RestApi $googleDriveApi */
		$googleDriveApi = $this->_container->get('google_drive_rest_api');

		$googleDriveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

		/** @var Response $response */
		$response = null;

		return $googleDriveApi->getWorksheets($fileId);
	}

	/**
	 * @param $accountId
	 * @param $params
	 * @return array
	 * @throws Exception\ParameterMissingException
	 */
	public function postSheets($accountId, $params)
	{
		$account = $this->configuration->getAccountBy('accountId', $accountId);

		if (!isset($params['data'])) {
			throw new ParameterMissingException("missing parameter data");
		}

		foreach ($params['data'] as $sheetData) {
			$this->checkParams(array(
				'googleId',
				'title',
				'sheetId',
				'sheetTitle'
			), $sheetData);

			$account->addSheet(new Sheet($sheetData));
		}
		$account->save();

		return array('status'   => 'ok');
	}

	/**
	 * @param $accountId
	 * @param $fileId
	 * @param $sheetId
	 */
	public function deleteSheet($accountId, $fileId, $sheetId)
	{
		$this->configuration->removeSheet($accountId, $fileId, $sheetId);
	}

}
