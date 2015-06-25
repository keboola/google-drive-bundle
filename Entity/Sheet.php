<?php
/**
 * Sheet.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Entity;

class Sheet
{
	protected $fileId;
	protected $googleId;
	protected $title;
	protected $sheetId;
	protected $sheetTitle;
	protected $config;

	/** @var Account */
	protected $account;

	public function __construct($data = array())
	{
		if (!empty($data)) {
			$this->fromArray($data);
		}
	}

	public function setAccount(Account $account)
	{
		$this->account = $account;
	}

	public function getAccount()
	{
		return $this->account;
	}

	public function setFileId($fileId)
	{
		$this->fileId = $fileId;
		return $this;
	}

	public function getFileId()
	{
		return $this->fileId;
	}

	public function setGoogleId($googleId)
	{
		$this->googleId = $googleId;
		return $this;
	}

	public function getGoogleId()
	{
		return $this->googleId;
	}

	public function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}

	public function getTitle()
	{
		return $this->title;
	}

	public function setSheetId($sheetId)
	{
		$this->sheetId = $sheetId;
		return $this;
	}

	public function getSheetId()
	{
		return $this->sheetId;
	}

	public function setSheetTitle($sheetTitle)
	{
		$this->sheetTitle = $sheetTitle;
		return $this;
	}

	public function getSheetTitle()
	{
		return $this->sheetTitle;
	}

	public function setConfig(array $config)
	{
		$this->config = json_encode($config);
		return $this;
	}

	public function getConfig()
	{
		return (is_array($this->config))?$this->config:json_decode($this->config, true);
	}

	public function fromArray(array $data)
	{
		foreach ($data as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function toArray()
	{
		return array(
			'fileId'    => $this->fileId,
			'googleId'  => $this->googleId,
			'title'     => $this->title,
			'sheetId'   => $this->sheetId,
			'sheetTitle'    => $this->sheetTitle,
			'config'    => json_encode($this->getConfig())
		);
	}
}
