<?php
/**
 * RestApi.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 24.6.13
 */

namespace Keboola\Google\DriveBundle\GoogleDrive;

use Guzzle\Http\Message\Response;
use GuzzleHttp\Exception\ClientException;
use Keboola\Google\ClientBundle\Google\RestApi as GoogleApi;
use Keboola\Syrup\Exception\UserException;

class RestApi
{
	/**
	 * @var GoogleApi
	 */
	protected $api;

	const FILES = 'https://www.googleapis.com/drive/v2/files';

	/**
	 * Old Documents API constants
	 */
	const DOCUMENTS_LIST = 'https://docs.google.com/feeds/default/private/full';
	const SPREADSHEETS_LIST = 'https://docs.google.com/feeds/default/private/full/-/spreadsheet';
	const DOCUMENT = 'https://docs.google.com/feeds/default/private/full';
	const SPREADSHEET_CONTENT = 'https://spreadsheets.google.com/feeds/list';
	const SPREADSHEET_WORKSHEETS = 'https://spreadsheets.google.com/feeds/worksheets';

	const EXPORT_SPREADSHEET = 'https://spreadsheets.google.com/feeds/download/spreadsheets/Export';
	const LIST_SPREADSHEET = 'https://spreadsheets.google.com/feeds/cells/%key%/%worksheetId%/private/full';

	const ACCOUNTS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts';
	const DATA_URL = 'https://www.googleapis.com/analytics/v3/data/ga';

	public function __construct(GoogleApi $api)
	{
		$this->api = $api;
	}

	public function toGid($gid)
	{
		return intval(base_convert($gid, 36, 10))^31578;
	}

	public function getApi()
	{
		return $this->api;
	}

	public function callApi($url, $method = 'GET', $headers = [], $params = [])
	{
		try {
			return $this->api->call($url, $method, $headers, $params);
		} catch (ClientException $e) {
			$statusCode = $e->getResponse()->getStatusCode();
			if ($statusCode >= 400 && $statusCode < 500) {
				throw new UserException($e->getMessage());
			}
		}
	}

	public function getFilesByOwner($userEmail, $mimeType='application/vnd.google-apps.spreadsheet')
	{
		return $this->api->request(
			self::FILES,
			'GET',
			['Accept'  => 'application/json'],
			['q'    => "'" . $userEmail . "' in owners and mimeType='".$mimeType."'"]
		);
	}

	public function getFiles($pageToken = null, $mimeType = 'application/vnd.google-apps.spreadsheet')
	{
		return $this->api->request(
			self::FILES,
			'GET',
			['Accept' => 'application/json'],
			[
				'pageToken' => $pageToken,
				'q'         => "mimeType='".$mimeType."'"
			]
		);
	}

	public function shareFile($googleId, $email)
	{
		$result = $this->callApi(self::FILES . '/' . $googleId . '/permissions', array(
			'Content-Type'  => 'application/json',
		), 'POST', array(
			'role'  => 'reader',
			'type'  => 'user',
			'value' => $email
		));

		return $result;
	}

	/**
	 * Returns list of worksheet for given document
	 *
	 * @param string $key
	 * @return array|boolean
	 */
	public function getWorksheets($key)
	{
		$response = $this->callApi(
			self::SPREADSHEET_WORKSHEETS . '/' . $key . '/private/full?alt=json' ,
			'GET',
			array(
				'Accept'		=> 'application/json',
				'GData-Version' => '3.0'
			)
		);

		$response = $response->json();

		$result = array();
		if (isset($response['feed']['entry'])) {
			foreach($response['feed']['entry'] as $entry) {
				$wsUri = explode('/', $entry['id']['$t']);
				$wsId = array_pop($wsUri);
				$gid = $this->getGid($entry['link']);

				if ($gid == null) {
					$gid = $this->toGid($wsId);
				}

				$result[$gid] = array(
					'id'    => $gid,
					'wsid'  => $wsId,
					'title' => $entry['title']['$t']
				);
			}

			return $result;
		}

		return false;
	}

	protected function getGid($links)
	{
		foreach ($links as $link) {
			if ($link['type'] == 'text/csv') {
				$linkArr = explode('?', $link['href']);
				$paramArr = explode('&', $linkArr[1]);

				return str_replace('gid=', '', $paramArr[0]);
			}
		}

		return null;
	}

	public function getFile($googleId)
	{
		$response = $this->callApi(
			self::FILES . '/' . $googleId,
			'GET'
		);

		return $response->json();
	}

	public function exportSpreadsheet($key, $worksheet = 0)
	{
		$uri = str_replace(array('%key%', '%worksheetId%'), array($key, $worksheet), self::LIST_SPREADSHEET) . '?alt=json';

		/** @var Response $response */
		$response = $this->callApi(
			$uri,
			'GET',
			array(
				'Accept'		=> 'application/json',
				'GData-Version' => '3.0'
			)
		);

		return $response->getBody(true);
	}

	public function export($url)
	{
		/** @var Response $response */
		$response = $this->callApi(
			$url,
			'GET',
			array(
				'Accept'		=> 'text/csv; charset=UTF-8',
				'GData-Version' => '3.0'
			)
		);

		return $response->getBody(true);
	}

	protected function _normalize($s)
	{
		// Normalize line endings
		// Convert all line-endings to UNIX format
		$s = str_replace("\r\n", "\n", $s);
		$s = str_replace("\r", "\n", $s);
		// Don't allow out-of-control blank lines
		$s = preg_replace("/\n{2,}/", "\n\n", $s);
		return $s;
	}
}
