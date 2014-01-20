<?php
/**
 * Configuration.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use Keboola\Google\DriveBundle\Entity\AccountFactory;
use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Exception\ConfigurationException;
use Keboola\StorageApi\Client as StorageApi;
use Keboola\StorageApi\Config\Reader;
use Keboola\StorageApi\Table;

class Configuration
{
	/** @var StorageApi */
	protected $storageApi;

	protected $componentName;

	protected $sys_prefix = 'sys.c-';

	const IN_PREFIX = 'in.c-';

	protected $accounts;

	/** @var AccountFactory */
	protected $accountFactory;

	public function __construct(StorageApi $storageApi, $componentName)
	{
		$this->storageApi = $storageApi;
		$this->componentName = $componentName;
		$this->accountFactory = new AccountFactory($this);
		$this->accounts = $this->getAccounts();
	}

	public function getStorageApi()
	{
		return $this->storageApi;
	}

	public function create()
	{
		$this->storageApi->createBucket($this->componentName, 'sys', 'GoogleDrive Extractor');
	}

	public function exists()
	{
		return $this->storageApi->bucketExists($this->getSysBucketId());
	}

	public function initDataBucket($accountId)
	{
		if (!$this->storageApi->bucketExists(self::IN_PREFIX . $this->componentName . '-' . $accountId)) {
			$this->storageApi->createBucket($this->componentName . '-' . $accountId, 'in', 'Google Drive Account bucket');
		}
	}

	/**
	 * Add new account
	 * @param $data
	 * @return \Keboola\Google\DriveBundle\Entity\Account
	 */
	public function addAccount($data)
	{
		$data['id'] = $this->getIdFromName($data['name']);
		$account = $this->accountFactory->get($data['id']);
		$account->fromArray($data);
		$account->save(true);
		$this->accounts[$data['id']] = $account;

		return $account;
	}

	/**
	 * Remove account
	 *
	 * @param $accountId
	 */
	public function removeAccount($accountId)
	{
		$tableId = $this->getSysBucketId() . '.' . $accountId;
		if ($this->storageApi->tableExists($tableId)) {
			$this->storageApi->dropTable($tableId);
		}

		unset($this->accounts[$accountId]);
	}

	public function addSheet(Account $account, $params)
	{
//		$accountId = $params['accountId'];
//		unset($params['accountId']);

//		$accounts = $this->getAccounts();
//		/** @var Account $account */
//		$account = $accounts[$accountId];

		$exists = false;
		foreach ($account->getSheets() as $sheet) {
			/** @var Sheet $sheet */
			if ($sheet->getGoogleId() == $params['googleId'] && $sheet->getSheetId() == $params['sheetId']) {
				$exists = true;
				break;
			}
		}

		if (!$exists) {
			$account->addSheet(new Sheet($params));
		}
	}

	public function getSheets($accountId)
	{
		$config = $this->getConfig();
		$account = $config[$accountId];
		$savedFiles = $account['items'];

		$result = array();

		foreach($savedFiles as $savedFile) {
			$result[$savedFile['googleId']][$savedFile['sheetId']] = $savedFile;
			$result[$savedFile['googleId']]['fileId'] = $savedFile['fileId'];
		}

		return $result;
	}

	public function getConfig()
	{
		Reader::$client = $this->storageApi;
		try {
			$config = Reader::read($this->getSysBucketId());

			if (isset($config['items'])) {
				return $config['items'];
			}
		} catch (\Exception $e) {

		}

		return array();
	}

	public function getSysBucketId()
	{
		if ($this->storageApi->bucketExists('sys.c-' . $this->componentName)) {
			return 'sys.c-' . $this->componentName;
		} else if ($this->storageApi->bucketExists('sys.' . $this->componentName)) {
			return 'sys.' . $this->componentName;
		}
		throw new ConfigurationException("SYS bucket don't exists");
	}

	public function getInBucketId($accountId)
	{
		return self::IN_PREFIX . $this->componentName . '-' . $accountId;
	}

	/**
	 * @param bool $asArray - convert Account objects to array
	 * @return array - array of Account objects or 2D array
	 */
	public function getAccounts($asArray = false)
	{
		$accounts = array();
		foreach ($this->getConfig() as $accountId => $v) {
			$account = $this->accountFactory->get($accountId);
			$account->fromArray($v);
			if ($asArray) {
				$account = $account->toArray();
			}
			$accounts[$accountId] = $account;
		}

		return $accounts;
	}

	public function getAccountBy($key, $value, $asArray = false)
	{
		$accounts = $this->getAccounts();

		$method = 'get' . ucfirst($key);
		/** @var Account $account */
		foreach ($accounts as $account) {
			if ($account->$method() == $value) {
				if ($asArray) {
					return $account->toArray();
				}
				return $account;
			}
		}

		return null;
	}

	private function getAccountId()
	{
		$accountId = 0;
		/** @var Account $v */
		foreach($this->getAccounts() as $k => $v) {
			if ($k >= $accountId) {
				$accountId = $k+1;
			}
		}

		return $accountId;
	}

	private function getIdFromName($name)
	{
		return strtolower(Table::removeSpecialChars($name));
	}

	public function removeSheet($accountId, $fileId, $sheetId)
	{
		/** @var Account $account */
		$account = $this->getAccountBy('accountId', $accountId);

		if (null == $account) {
			throw new ConfigurationException("Account doesn't exist");
		}

		$account->removeSheet($fileId, $sheetId);
		$account->save();
	}

}
