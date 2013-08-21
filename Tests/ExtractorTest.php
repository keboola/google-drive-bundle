<?php

namespace Keboola\Google\DriveBundle\Tests;

use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\StorageApi\Client as SapiClient;
use Keboola\StorageApi\Config\Reader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Client;

class ExtractorTest extends WebTestCase
{
	/** @var SapiClient */
	protected $storageApi;

	/** @var Client */
	protected static $client;

	/** @var Configuration */
	protected $configuration;


	protected function setUp()
	{
		self::$client = static::createClient();
		$container = self::$client->getContainer();
		self::$client->setServerParameters(array(
			'HTTP_X-StorageApi-Token' => $container->getParameter('storageApi.test.token')
		));

		$this->storageApi = new SapiClient($container->getParameter('storageApi.test.token'));
		$this->configuration = new Configuration($this->storageApi, 'ex-googleDrive');

		// Cleanup
		$accounts = $this->configuration->getAccounts(true);

		/** @var Account $account */
		foreach ($accounts as $k => $account) {
			$this->configuration->removeAccount($k);
			$tables = $this->storageApi->listTables('in.c-ex-googleDrive-' . $k);
			foreach ($tables as $table) {
				$this->storageApi->dropTable($table['id']);
			}
		}
	}

	protected function createTestAccount()
	{
		$this->configuration->addAccount(array(
			'googleId'  => '123456',
			'name'      => 'test',
			'email'     => 'test@keboola.com',
			'accessToken'   => 'accessToken',
			'refreshToken'  => 'refreshToken'
		));
	}

	protected function assertAccount($account)
	{
		$this->assertArrayHasKey('googleId', $account);
		$this->assertArrayHasKey('name', $account);
		$this->assertArrayHasKey('email', $account);
		$this->assertArrayHasKey('accessToken', $account);
		$this->assertArrayHasKey('refreshToken', $account);

		$this->assertNotEmpty($account['googleId']);
		$this->assertNotEmpty($account['name']);
		$this->assertNotEmpty($account['email']);
		$this->assertNotEmpty($account['accessToken']);
		$this->assertNotEmpty($account['refreshToken']);
	}

	protected function createTestProfile()
	{
		$this->createTestAccount();

		$this->configuration->addProfile(array(
			array(
				'profileId'     => '0',
				'googleId'      => '987654321',
				'name'          => 'testProfile',
				'webPropertyId' => 'web-property-id'
			)
		), 0);
	}

	/**
	 * Accounts
	 */

	public function testPostAccount()
	{
		self::$client->request(
			'POST', '/ex-google-drive/account',
			array(),
			array(),
			array(),
			json_encode(array(
				'googleId'  => '123456',
				'name'      => 'test',
				'email'     => 'test@keboola.com',
				'accessToken'   => 'accessToken',
				'refreshToken'  => 'refreshToken'
			))
		);

		/* @var Response $responseJson */
		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$this->assertEquals("ok", $response['status']);

		$accounts = $this->configuration->getAccounts(true);
		$account = $accounts[0];

		$this->assertAccount($account);
	}

	public function testGetAccount()
	{
		$this->createTestAccount();

		self::$client->request(
			'GET', '/ex-google-drive/account',
			array(
				'accountId' => 0
			)
		);

		/* @var Response $responseJson */
		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$this->assertEquals("ok", $response['status']);
		$this->assertArrayHasKey('account', $response);

		$account = $response['account'];
		$this->assertAccount($account);
	}

	public function testGetAccounts()
	{
		$this->createTestAccount();

		self::$client->request(
			'GET', '/ex-google-drive/accounts'
		);

		/* @var Response $responseJson */
		$responseJson = self::$client->getResponse()->getContent();
		$response = json_decode($responseJson, true);

		$this->assertEquals("ok", $response['status']);
		$this->assertArrayHasKey('accounts', $response);
		$this->assertNotEmpty($response['accounts']);
	}

	public function testDeleteAccount()
	{

	}

	/**
	 * Sheets
	 */


}
