<?php
/**
 * RestApi.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 24.6.13
 */

namespace Keboola\Google\DriveBundle\GoogleDrive;

use Guzzle\Http\Message\Response;
use Keboola\Google\ClientBundle\Google\RestApi as GoogleApi;

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

	public function getFilesByOwner($userEmail, $mimeType='application/vnd.google-apps.spreadsheet')
	{
		$request = $this->api->request(
			self::FILES,
			'get',
			array('Accept'  => 'application/json')
		);
		$request->getQuery()->set('q', "'" . $userEmail . "' in owners and mimeType='".$mimeType."'");
		$response = $request->send();

		return $response;
	}

	public function shareFile($googleId, $email)
	{
		$result = $this->api->call(self::FILES . '/' . $googleId . '/permissions', array(
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
		$response = $this->api->call(
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
	}

	public function getFile($googleId)
	{
		$response = $this->api->call(
			self::FILES . '/' . $googleId,
			'GET'
		);

		return $response->json();
	}

	public function exportSpreadsheet($key, $worksheet = 0)
	{
//		$uri = self::EXPORT_SPREADSHEET . '?key=' . $googleId . '&exportFormat=' . $format;
		$uri = str_replace(array('%key%', '%worksheetId%'), array($key, $worksheet), self::LIST_SPREADSHEET) . '?alt=json';

//		if ($worksheet !== 0) {
//			$uri .= '&gid=' . $worksheet;
//		}

		/** @var Response $response */
		$response = $this->api->call(
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
		$response = $this->api->call(
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
