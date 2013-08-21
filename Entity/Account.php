<?php
/**
 * Account.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Entity;

use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\StorageApi\Table;

class Account extends Table
{
	protected $_header = array('fileId', 'googleId', 'title', 'sheetId', 'sheetTitle', 'config');

	protected $accountId;

	protected $sheets = array();

	/** @var Configuration */
	protected $configuration;

	public function __construct(Configuration $configuration, $accountId)
	{
		$this->configuration = $configuration;
		$storageApi = $this->configuration->getStorageApi();
		$sysBucket = $this->configuration->getSysBucketId();
		$this->accountId = $accountId;

		parent::__construct($storageApi, $sysBucket . '.account-' . $accountId);
	}

	public function getAccountId()
	{
		return $this->accountId;
	}

	public function setGoogleId($googleId)
	{
		$this->setAttribute('googleId', $googleId);
		return $this;
	}

	public function getGoogleId()
	{
		return $this->getAttribute('googleId');
	}

	public function setEmail($email)
	{
		$this->setAttribute('email', $email);
		return $this;
	}

	public function getEmail()
	{
		return $this->getAttribute('email');
	}

	public function setAccountName($name)
	{
		$this->setAttribute('name', $name);
		return $this;
	}

	public function getAccountName()
	{
		return $this->getAttribute('name');
	}

	public function setAccessToken($accessToken)
	{
		$this->setAttribute('accessToken', $accessToken);
		return $this;
	}

	public function getAccessToken()
	{
		return $this->getAttribute('accessToken');
	}

	public function setRefreshToken($refreshToken)
	{
		$this->setAttribute('refreshToken', $refreshToken);
		return $this;
	}

	public function getRefreshToken()
	{
		return $this->getAttribute('refreshToken');
	}

	/**
	 * Set sheets from array of Sheet objects
	 *
	 * @param $sheets array of Sheet objects
	 * @return $this
	 */
	public function setSheets($sheets)
	{
		$this->sheets = $sheets;

		return $this;
	}

	/**
	 * Set sheets from associative array
	 */
	public function setSheetsFromArray($array)
	{
		foreach ($array as $data) {
			$sheet = new Sheet($data);
			$sheet->setAccount($this);
			$this->sheets[] = $sheet;
		}

		return $this;
	}

	public function getSheets()
	{
		return $this->sheets;
	}

	public function addSheet(Sheet $sheet)
	{
		$sheet->setAccount($this);
		$fileIds = array();
		/** @var Sheet $savedSheet */
		foreach($this->getData() as $savedSheet) {
			$gid = $savedSheet['googleId'];
			if (!isset($fileIds[$gid])) {
				$fileIds[$gid] = $savedSheet['fileId'];
			}
		}

		$nextFileId = 0;
		if (!empty($fileIds)) {
			if (isset($fileIds[$sheet->getGoogleId()])) {
				$nextFileId = $fileIds[$sheet->getGoogleId()];
			} else {
				$nextFileId = max($fileIds) + 1;
			}
		}

		$tableName = $nextFileId . '-' . $this->removeSpecialChars($sheet->getSheetTitle());

		$sheet->setFileId($nextFileId);
		$sheet->setConfig(
			array(
				'header'    => array('rows' => 1),
				'db'        => array('table' => $this->getInBucketId() . '.' . $tableName)
			)
		);

		// If already saved - reuse config
//		if (isset($savedFiles[$item['googleId']][$item['sheetId']])) {
//			$savedFile = $savedFiles[$item['googleId']][$item['sheetId']];
//			$item['config'] = $savedFile['config'];
//		}

		$this->sheets[] = $sheet;
	}

	public function fromArray($array)
	{
		if (isset($array['items'])) {
			// set sheets as array
			$this->setFromArray($array['items']);
			// set sheets as array of sheet objects
			$this->setSheetsFromArray($array['items']);
		}
		unset($array['items']);

		foreach($array as $k => $v) {
			$this->setAttribute($k, $v);
		}
	}

	public function toArray()
	{
		$attributes = $this->getAttributes();
		$attributes['accountId'] = $this->accountId;
		$array = array_merge(
			$attributes,
			array(
				'items' => $this->getData()
			)
		);
		return $array;
	}

	public function save($isAsync = false)
	{
		// Sheets toArray
		$sheetArray = array();
		foreach ($this->sheets as $sheet) {
			/** @var Sheet $sheet */
			$sheetArray[] = $sheet->toArray();
		}

		$this->setFromArray($sheetArray);

		parent::save($isAsync);
	}

	public function getInBucketId()
	{
		return $this->configuration->getInBucketId($this->accountId);
	}

	public function removeSheet($fileId, $sheetId)
	{
		/** @var Sheet $sheet */
		foreach ($this->sheets as $k => $sheet) {
			if ($fileId == $sheet->getFileId() && $sheetId == $sheet->getSheetId()) {
				unset($this->sheets[$k]);
			}
		}
	}
}
