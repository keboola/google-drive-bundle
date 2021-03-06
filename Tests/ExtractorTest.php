<?php

namespace Keboola\Google\DriveBundle\Tests;

use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\StorageApi\Client as SapiClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Client;
use Keboola\Syrup\Encryption\Encryptor;

class ExtractorTest extends WebTestCase
{
	/** @var SapiClient */
	protected $storageApi;

	/** @var Client */
	protected static $client;

	/** @var Encryptor */
	protected $encryptor;

	/** @var Configuration */
	protected $configuration;

	protected $componentName = 'ex-google-drive';

	protected $accountId = 'test';

	protected $accountName = 'Test';

	protected $googleId = '123456';

	protected $googleName = 'googleTestAccount';

	protected $email = 'test@keboola.com';

	protected $accessToken = 'accessToken';

	protected $refreshToken = 'refreshToken';

	protected $fileGoogleId = '0Asceg4OWLR3ldGY4UU5Vakd2Z0tkN0dTY3ZkTE9PMVE';

	protected $fileTitle = 'Sales rep monthly targets';

	protected $sheetId = 0;
	protected $sheetTitle = 'Targets Salesreps';

	protected function setUp()
	{
		self::$client = static::createClient();
		$container = self::$client->getContainer();

		$sapiToken = $container->getParameter('storage_api.test.token');
		$sapiUrl = $container->getParameter('storage_api.test.url');

		self::$client->setServerParameters(array(
			'HTTP_X-StorageApi-Token' => $sapiToken
		));

		$this->storageApi = new SapiClient([
			'token'     =>  $sapiToken,
			'url'       =>  $sapiUrl,
			'userAgent' =>  $this->componentName
		]);

		$this->encryptor = $container->get('syrup.encryptor');

		$this->configuration = $container->get('ex_google_drive.configuration');
		$this->configuration->setStorageApi($this->storageApi);

		try {
			$this->configuration->create();
		} catch (\Exception $e) {
			// bucket exists
		}

		// Cleanup
		$sysBucketId = $this->configuration->getSysBucketId();
		$tableId = $sysBucketId . '.' . $this->accountId;

		if ($this->storageApi->tableExists($tableId)) {
			$this->storageApi->dropTable($tableId);
		}
	}

	protected function createConfig()
	{
		$this->configuration->addAccount(array(
			'id'            => $this->accountId,
			'name'          => $this->accountName,
			'accountName'   => $this->accountName,
			'description'   => 'Test Account created by PhpUnit test suite'
		));
	}

	protected function createAccount()
	{
		$account = $this->configuration->getAccountBy('accountId', $this->accountId);
		$account->setAccountName($this->accountName);
		$account->setGoogleId($this->googleId);
		$account->setGoogleName($this->googleName);
		$account->setEmail($this->email);
		$account->setAccessToken($this->accessToken);
		$account->setRefreshToken($this->refreshToken);

		$account->save();
	}

	/**
	 * @param Account $account
	 */
	protected function assertAccount(Account $account)
	{
		$this->assertEquals($this->accountId, $account->getAccountId());
		$this->assertEquals($this->accountName, $account->getAccountName());
		$this->assertEquals($this->googleId, $account->getGoogleId());
		$this->assertEquals($this->googleName, $account->getGoogleName());
		$this->assertEquals($this->email, $account->getEmail());
		$this->assertEquals($this->accessToken, $account->getAccessToken());
		$this->assertEquals($this->refreshToken, $account->getRefreshToken());
	}

	/**
	 * Config
	 */

	public function testPostConfig()
	{
		self::$client->request(
			'POST', $this->componentName . '/configs',
			array(),
			array(),
			array(),
			json_encode(array(
				'name'          => 'Test',
				'description'   => 'Test Account created by PhpUnit test suite'
			))
		);

		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$this->assertEquals('test', $response['id']);
		$this->assertEquals('Test', $response['name']);
	}

	public function testGetConfig()
	{
		$this->createConfig();

		self::$client->request('GET', $this->componentName . '/configs');

		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$testConfig = [];
		foreach ($response as $config) {
			if ($config['id'] == $this->accountId) {
				$testConfig = $config;
			}
		}

		$this->assertEquals('test', $testConfig['id']);
		$this->assertEquals('Test', $testConfig['name']);
	}

	public function testDeleteConfig()
	{
		$this->createConfig();

		self::$client->request('DELETE', $this->componentName . '/configs/test');

		/* @var Response $response */
		$response = self::$client->getResponse();

		$accounts = $this->configuration->getAccounts(true);

		$this->assertEquals(204, $response->getStatusCode());
		$this->assertArrayNotHasKey($this->accountId, $accounts);
	}

	/**
	 * Accounts
	 */

	public function testGetAccount()
	{
		$this->createConfig();
		$this->createAccount();

		self::$client->request('GET', $this->componentName . '/account/' . $this->accountId);

		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$account = $this->configuration->getAccountBy('accountId', $response['accountId']);

		$this->assertAccount($account);
	}

	public function testPostSheets()
	{
		$this->createConfig();
		$this->createAccount();

		self::$client->request(
			'POST', $this->componentName . '/sheets/' . $this->accountId,
			array(),
			array(),
			array(),
			json_encode(array(
				'data'  => array(
					array(
						'googleId'  => $this->fileGoogleId,
						'title'     => $this->fileTitle,
						'sheetId'   => $this->sheetId,
						'sheetTitle'    => $this->sheetTitle
					)
				)
			))
		);

		$this->assertEquals(200, self::$client->getResponse()->getStatusCode());

		$account = $this->configuration->getAccountBy('accountId', $this->accountId);
		$sheets = $account->getSheets();

		$this->assertNotEmpty($sheets);
	}

	/**
	 * External
	 */

	public function testExternalLink()
	{
		$this->createConfig();
		$this->createAccount();

		$referrerUrl = self::$client
			->getContainer()
			->get('router')
			->generate('keboola_google_drive_post_external_auth_link', array(), true);

		self::$client->followRedirects();
		self::$client->request(
			'POST',
			$this->componentName . '/external-link',
			array(),
			array(),
			array(),
			json_encode(array(
				'account'   => $this->accountId,
				'referrer'  => $referrerUrl
			))
		);

		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$this->assertArrayHasKey('link', $response);
		$this->assertNotEmpty($response['link']);
	}

	/**
	 * Run
	 */

	public function testRun()
	{
		//@TODO
	}
}
