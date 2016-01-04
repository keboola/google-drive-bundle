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
            $response = $this->api->request($url, $method, $headers, $params);
			return json_decode($response->getBody(), true);
		} catch (ClientException $e) {
			$statusCode = $e->getResponse()->getStatusCode();
			if ($statusCode >= 400 && $statusCode < 500) {
				throw new UserException($e->getMessage());
			}
		}
	}

    /** @deprecated */
	public function getFilesByOwner($userEmail, $mimeType='application/vnd.google-apps.spreadsheet')
	{
		return $this->api->request(
			self::FILES,
			'GET',
			['Accept'  => 'application/json'],
			['q'    => "'" . $userEmail . "' in owners and mimeType='".$mimeType."'"]
		);
	}

    /** @deprecated */
	public function shareFile($googleId, $email)
	{
		return $this->callApi(self::FILES . '/' . $googleId . '/permissions', array(
			'Content-Type'  => 'application/json',
		), 'POST', array(
			'role'  => 'reader',
			'type'  => 'user',
			'value' => $email
		));
	}

    public function getFiles($pageToken = null, $mimeType = 'application/vnd.google-apps.spreadsheet')
    {
        return $this->callApi(
            self::FILES,
            'GET',
            ['Accept' => 'application/json'],
            [
                'pageToken' => $pageToken,
                'q'         => "mimeType='".$mimeType."'"
            ]
        );
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
		return $this->callApi(
			self::FILES . '/' . $googleId,
			'GET'
		);
	}

	public function export($url)
	{
        list($baseUrl, $query) = explode('?', $url);

		/** @var Response $response */
		$response = $this->api->request(
            $baseUrl,
			'GET',
			[
				'Accept'		=> 'application/json; charset=UTF-8',
				'GData-Version' => '3.0'
			],
            \GuzzleHttp\Psr7\parse_query($query)
		);

		return $response->getBody(true);
	}
}
