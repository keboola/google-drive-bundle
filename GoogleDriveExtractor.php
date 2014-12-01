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
use Syrup\ComponentBundle\Component\Component;

class GoogleDriveExtractor extends Component
{
	protected $name = 'google-drive';
	protected $prefix = 'ex';

	/** @var Extractor */
	protected $extractor;

	/** @var Configuration */
	protected $configuration;

	/**
	 * @return Configuration
	 */
	public function getConfiguration()
	{
		if ($this->configuration == null) {
			$this->configuration = $this->container->get('ex_google_drive.configuration');
			$this->configuration->setStorageApi($this->storageApi);
		}
		return $this->configuration;
	}

	protected function checkParams($required, $params)
	{
		foreach ($required as $r) {
			if (!isset($params[$r])) {
				throw new ParameterMissingException(sprintf("Parameter %s is missing.", $r));
			}
		}
	}

	/**
	 * @param Entity\Account $account
	 * @internal param $accessToken
	 * @internal param $refreshToken
	 * @return RestApi
	 */
	protected function getApi(Account $account)
	{
		/** @var RestApi $googleDriveApi */
		$googleDriveApi = $this->container->get('ex_google_drive.rest_api');
		$googleDriveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

		$this->extractor = $this->container->get('ex_google_drive.extractor');
		$this->extractor->setConfiguration($this->getConfiguration());
		$this->extractor->setCurrAccountId($account->getAccountId());

		$googleDriveApi->getApi()->setRefreshTokenCallback(array($this->extractor, 'refreshTokenCallback'));

		return $googleDriveApi;
	}

	public function postRun($params)
	{
		$this->extractor = $this->container->get('ex_google_drive.extractor');
		$this->extractor->setConfiguration($this->getConfiguration());

		$status = $this->extractor->run($params);

		return array(
			'status'    => $status
		);
	}

	public function getAccount($id)
	{
		return $this->getConfiguration()->getAccountBy('accountId', $id, true);
	}

	public function getAccounts()
	{
		return $this->getConfiguration()->getAccounts(true);
	}

	public function getConfigs()
	{
		$accounts = $this->getConfiguration()->getAccounts(true);

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
			$this->getConfiguration()->exists();
		} catch (ConfigurationException $e) {
			$this->getConfiguration()->create();
		}

		if (null != $this->getConfiguration()->getAccountBy('accountId', $this->configuration->getIdFromName($params['name']))) {
			throw new ConfigurationException('Account already exists');
		}
		$params['accountName'] = $params['name'];

		return $this->getConfiguration()->addAccount($params);
	}

	public function deleteConfig($id)
	{
		$this->getConfiguration()->removeAccount($id);
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

		/** @var Account $account */
		$account = $this->getConfiguration()->getAccountBy('accountId', $params['id']);
		if (null == $account) {
			throw new ConfigurationException("Account doesn't exist");
		}

		$account
			->setAccountId($params['id'])
			->setGoogleId($params['googleId'])
			->setGoogleName($params['googleName'])
			->setEmail($params['email'])
			->setAccessToken($params['accessToken'])
			->setRefreshToken($params['refreshToken'])
		;
		$account->save();

		return $account->toArray();
	}

	public function getFiles($accountId)
	{
		/** @var Account $account */
		$account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

		$googleDriveApi = $this->getApi($account);

		/** @var Response $response */
		$response = $googleDriveApi->getFiles();

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
		$account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

		$googleDriveApi = $this->getApi($account);

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
		$account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

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
		$this->getConfiguration()->removeSheet($accountId, $fileId, $sheetId);
	}

	public function exportFile($accountId, $fileId)
	{
		/** @var Account $account */
		$account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

		$googleDriveApi = $this->getApi($account);

		$meta = $googleDriveApi->getFile($fileId);
		$exportLink = $meta['exportLinks']['application/xlsx'];

		return $googleDriveApi->export($exportLink);
	}


	public function getToken()
	{
		return $this->getConfiguration()->createToken();
	}

}
