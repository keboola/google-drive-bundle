<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 15/01/14
 * Time: 17:20
 */

namespace Keboola\Google\DriveBundle\Controller;


use Keboola\Encryption\EncryptorInterface;
use Keboola\Google\DriveBundle\Exception\ConfigurationException;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\StorageApi\Client as StorageApi;
use Keboola\Google\ClientBundle\Google\RestApi;
use Keboola\Google\DriveBundle\Exception\ParameterMissingException;
use Keboola\StorageApi\ClientException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Syrup\ComponentBundle\Controller\BaseController;
use Syrup\ComponentBundle\Exception\SyrupComponentException;

class OauthController extends BaseController
{
	/**
	 * @var AttributeBag
	 */
	protected $sessionBag;

	private $sessionTimeout = 1200;

	protected $componentName = 'ex-google-drive';

	/**
	 * Init OAuth session bag
	 *
	 * @return AttributeBag
	 */
	private function initSessionBag()
	{
		if (!$this->sessionBag) {
			/** @var Session $session */
			$session = $this->container->get('session');
			$bag = new AttributeBag('_ex_google_drive');
			$bag->setName('googledrive');
			$session->registerBag($bag);

			$this->sessionBag = $session->getBag('googledrive');
		}

		return $this->sessionBag;
	}

	/**
	 * @return RestApi
	 */
	private function getGoogleApi()
	{
		return $this->container->get('google_rest_api');
	}

	public function externalAuthAction()
	{
		$request = $this->getRequest();

		// check token - if expired redirect to error page
		try {
			$sapi = new StorageApi($request->query->get('token'), null, $this->componentName);
		} catch (ClientException $e) {

			if ($e->getCode() == 401) {
				return $this->render('KeboolaGoogleDriveBundle:Oauth:expired.html.twig');
			} else {
				throw $e;
			}
		}

		$request->request->set('token', $request->query->get('token'));
		$request->request->set('account', $request->query->get('account'));
		$request->request->set('referrer', $request->query->get('referrer'));

		return $this->forward('KeboolaGoogleDriveBundle:Oauth:oauth');
	}

	public function externalAuthFinishAction()
	{
		return $this->render('KeboolaGoogleDriveBundle:Oauth:finish.html.twig');
	}

	public function oauthAction()
	{
		if (!$this->getRequest()->request->get('account')) {
			throw new ParameterMissingException("Parameter 'account' is missing");
		}

		$bag = $this->initSessionBag();
		$googleApi = $this->getGoogleApi();

		try {
			$client = new StorageApi($this->getRequest()->request->get('token'), null, $this->componentName);

			$url = $googleApi->getAuthorizationUrl(
				$this->container->get('router')->generate('keboola_google_drive_oauth_callback', array(), UrlGeneratorInterface::ABSOLUTE_URL),
				'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email https://spreadsheets.google.com/feeds',
				'force'
			);

			$bag->set('token', $client->getTokenString());
			$bag->set('account', $this->getRequest()->request->get('account'));
			$bag->set('referrer', $this->getRequest()->request->get('referrer'));

			return new RedirectResponse($url);
		} catch (\Exception $e) {
			throw new SyrupComponentException(500, 'OAuth UI request error', $e);
		}
	}

	public function oauthCallbackAction()
	{
		$bag = $this->initSessionBag();

		$token = $bag->get('token');
		$accountId = $bag->get('account');
		$referrer = $bag->get('referrer');

		/** @var Session $session */
		$session = $this->container->get('session');
		if (time() - $session->getMetadataBag()->getCreated() > $this->sessionTimeout || null == $token) {
			$url = $this->container->get('router')->generate('keboola_google_drive_external_auth');
			$url .= '?token=' . $token .'&account=' . $accountId . '&referrer=' . $referrer;

			$session->invalidate();

			return new RedirectResponse($url);
		}

		$code = $this->get('request')->query->get('code');

		if (empty($code)) {
			throw new SyrupComponentException(400, 'Could not read from Google API');
		}

		$googleApi = $this->getGoogleApi();

		try {
			$storageApi = new StorageApi($token, null, $this->componentName);

			/** @var EncryptorInterface $encryptor */
			$encryptor = $this->get('syrup.encryptor');

			$configuration = new Configuration($storageApi, $this->componentName, $encryptor);

			$tokens = $googleApi->authorize($code, $this->container->get('router')->generate(
				'keboola_google_drive_oauth_callback', array(), UrlGeneratorInterface::ABSOLUTE_URL)
			);

			$googleApi->setCredentials($tokens['access_token'], $tokens['refresh_token']);
			$userData = $googleApi->call(RestApi::USER_INFO_URL)->json();

			$account = $configuration->getAccountBy('accountId', $accountId);

			if (null == $account) {
				throw new ConfigurationException("Account doesn't exist");
			}

			$account
				->setGoogleId($userData['id'])
				->setGoogleName($userData['name'])
				->setEmail($userData['email'])
				->setAccessToken($tokens['access_token'])
				->setRefreshToken($tokens['refresh_token'])
			;
			$account->save();

			if ($referrer) {
				return new RedirectResponse($referrer);
			} else {
				return new JsonResponse(array('status' => 'ok'));
			}
		} catch (\Exception $e) {
			throw new SyrupComponentException(500, 'Could not save API tokens', $e);
		}
	}

}
