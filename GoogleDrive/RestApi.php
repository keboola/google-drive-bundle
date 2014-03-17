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

	const ACCOUNTS_URL = 'https://www.googleapis.com/analytics/v3/management/accounts';
	const DATA_URL = 'https://www.googleapis.com/analytics/v3/data/ga';

	public static $GID_TABLE = array(
		'od6' => 0,
		'od7' => 1,
		'od4' => 2,
		'od5' => 3,
		'oda' => 4,
		'odb' => 5,
		'od8' => 6,
		'od9' => 7,
		'ocy' => 8,
		'ocz' => 9,
		'ocw' => 10,
		'ocx' => 11,
		'od2' => 12,
		'od3' => 13,
		'od0' => 14,
		'od1' => 15,
		'ocq' => 16,
		'ocr' => 17,
		'oco' => 18,
		'ocp' => 19,
		'ocu' => 20,
		'ocv' => 21,
		'ocs' => 22,
		'oct' => 23,
		'oci' => 24,
		'ocj' => 25,
		'ocg' => 26,
		'och' => 27,
		'ocm' => 28,
		'ocn' => 29,
		'ock' => 30,
		'ocl' => 31,
		'oe2' => 32,
		'oe3' => 33,
		'oe0' => 34,
		'oe1' => 35,
		'oe6' => 36,
		'oe7' => 37,
		'oe4' => 38,
		'oe5' => 39,
		'odu' => 40,
		'odv' => 41,
		'ods' => 42,
		'odt' => 43,
		'ody' => 44,
		'odz' => 45,
		'odw' => 46,
		'odx' => 47,
		'odm' => 48,
		'odn' => 49,
		'odk' => 50,
		'odl' => 51,
		'odq' => 52,
		'odr' => 53,
		'odo' => 54,
		'odp' => 55,
		'ode' => 56,
		'odf' => 57,
		'odc' => 58,
		'odd' => 59,
		'odi' => 60,
		'odj' => 61,
		'odg' => 62,
		'odh' => 63,
		'obe' => 64,
		'obf' => 65,
		'obc' => 66,
		'obd' => 67,
		'obi' => 68,
		'obj' => 69,
		'obg' => 70,
		'obh' => 71,
		'ob6' => 72,
		'ob7' => 73,
		'ob4' => 74,
		'ob5' => 75,
		'oba' => 76,
		'obb' => 77,
		'ob8' => 78,
		'ob9' => 79,
		'oay' => 80,
		'oaz' => 81,
		'oaw' => 82,
		'oax' => 83,
		'ob2' => 84,
		'ob3' => 85,
		'ob0' => 86,
		'ob1' => 87,
		'oaq' => 88,
		'oar' => 89,
		'oao' => 90,
		'oap' => 91,
		'oau' => 92,
		'oav' => 93,
		'oas' => 94,
		'oat' => 95,
		'oca' => 96,
		'ocb' => 97,
		'oc8' => 98,
		'oc9' => 99
	);

	public function __construct(GoogleApi $api)
	{
		$this->api = $api;
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
				$wsId = substr($entry['id']['$t'], -3);
				$result[self::$GID_TABLE[$wsId]] = array(
					'id' => self::$GID_TABLE[$wsId],
					'title' => $entry['title']['$t']
				);
			}

			return $result;
		}

		return false;
	}

	public function getFile($googleId)
	{
		$response = $this->api->call(
			self::FILES . '/' . $googleId,
			'GET'
		);

		return $response->json();
	}

	/**
	 * @param        $googleId
	 * @param int    $worksheet
	 * @param string $format
	 * @return \Guzzle\Http\EntityBodyInterface|string
	 * @deprecated
	 */
	public function exportSpreadsheet($googleId, $worksheet = 0, $format = 'csv')
	{
		/** @var Response $response */
		$response = $this->api->call(
			self::EXPORT_SPREADSHEET . '?key=' . $googleId . '&exportFormat=' . $format . '&gid=' . $worksheet,
			'GET',
			array(
				'Accept'		=> 'text/'. $format .'; charset=UTF-8',
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
