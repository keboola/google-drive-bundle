<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 14/01/14
 * Time: 14:01
 */

namespace Keboola\Google\DriveBundle\Controller;

use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Exception\ConfigurationException;
use Keboola\Google\DriveBundle\Exception\ParameterMissingException;
use Keboola\Google\DriveBundle\Extractor\Extractor;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Keboola\Syrup\Controller\ApiController;
use Keboola\Syrup\Exception\UserException;
use Keboola\Google\DriveBundle\Extractor\Configuration;

class GoogleDriveController extends ApiController
{
    /** @var Extractor */
    protected $extractor;

    /** @var Configuration */
    protected $configuration;

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        if ($this->configuration == null) {
            $this->configuration = $this->container->get('ex_google_drive.configuration');
            $this->configuration->setStorageApi($this->storageApi);
        }
        return $this->configuration;
    }

    /**
     * @param array $required
     * @param array $params
     */
    protected function checkParams($required, $params)
    {
        foreach ($required as $r) {
            if (!isset($params[$r])) {
                throw new ParameterMissingException(sprintf("Parameter %s is missing.", $r));
            }
        }
    }

	/** Tokens */

    /**
     * @param Request $request
     * @return JsonResponse
     */
	public function postExternalAuthLinkAction(Request $request)
	{
		$post = $this->getPostJson($request);

		if (!isset($post['account'])) {
			throw new ParameterMissingException("Parameter 'account' is required");
		}

		if (!isset($post['referrer'])) {
			throw new ParameterMissingException("Parameter 'referrer' is required");
		}

		/** @var Account $account */
		$account = $this->getConfiguration()->getAccountBy('accountId', $post['account']);

        if ($account == null) {
            throw new UserException(sprintf("Account '%s' not found", $post['account']));
        }
		$account->setExternal(true);
		$account->save();

		$token = $this->getConfiguration()->createToken();

		$referrer = $post['referrer'] . '?token=' . $token['token'] .'&account=' . $post['account'];

		$url = $this->generateUrl('keboola_google_drive_external_auth', [
			'token'     => $token['token'],
			'account'   => $post['account'],
			'referrer'  => $referrer
		], true);

		return $this->createJsonResponse([
			'link'  => $url
		]);
	}

	/** Configs */

    /**
     * @return JsonResponse
     */
	public function getConfigsAction()
	{
        $accounts = $this->getConfiguration()->getAccounts(true);

        $res = [];
        foreach ($accounts as $account) {
            $res[] = array_intersect_key($account, array_fill_keys(['id', 'name', 'description'], 0));
        }

		return $this->getJsonResponse($res);
	}

    /**
     * @param Request $request
     * @return JsonResponse
     */
	public function postConfigsAction(Request $request)
	{
        $params = $this->getPostJson($request);
        $this->checkParams(['name'], $params);

        try {
            $this->getConfiguration()->exists();
        } catch (ConfigurationException $e) {
            $this->getConfiguration()->create();
        }

        if (null != $this->getConfiguration()->getAccountBy(
            'accountId',
            $this->configuration->getIdFromName($params['name']))
        ) {
            throw new ConfigurationException('Account already exists');
        }
        $params['accountName'] = $params['name'];

        $account = $this->getConfiguration()->addAccount($params);

		return $this->getJsonResponse([
			'id'    => $account->getAccountId(),
			'name'  => $account->getAccountName(),
			'description'   => $account->getDescription()
		]);
	}

    /**
     * @param $id
     * @return JsonResponse
     */
	public function deleteConfigAction($id)
	{
        $this->getConfiguration()->removeAccount($id);

		return $this->getJsonResponse([], 204);
	}

	/** Accounts */

    /**
     * @param $id
     * @return JsonResponse
     */
	public function getAccountAction($id)
	{
		$account = $this->getConfiguration()->getAccountBy('accountId', $id, true);

		if ($account == null) {
			throw new UserException(sprintf('Account %s not found', $id));
		}

        $account['items'] = array_map(function ($item) {
            $item['sheetId'] = (string) $item['sheetId'];
            return $item;
        }, $account['items']);

		return $this->getJsonResponse($account);
	}

    /**
     * @return JsonResponse
     */
	public function getAccountsAction()
	{
		return $this->getJsonResponse($this->getConfiguration()->getAccounts(true));
	}


	/** Files */

    /**
     * @param string $accountId
     * @param null|string $pageToken
     * @return JsonResponse
     * @throws UserException
     */
	public function getFilesAction($accountId, $pageToken = null)
	{
        /** @var Account $account */
        $account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

        if (null == $account) {
            throw new UserException(sprintf("Account '%s' doesn't exist", $accountId));
        }

        $googleDriveApi = $this->getApi($account);

		return $this->getJsonResponse($googleDriveApi->getFiles($pageToken));
	}


	/** Sheets */

    /**
     * @param string $accountId
     * @param string $fileId
     * @return JsonResponse
     */
	public function getSheetsAction($accountId, $fileId)
	{
        /** @var Account $account */
        $account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

        $googleDriveApi = $this->getApi($account);

		return $this->getJsonResponse($googleDriveApi->getWorksheets($fileId));
	}

    /**
     * @param string $accountId
     * @param Request $request
     * @return JsonResponse
     * @throws UserException
     */
	public function postSheetsAction($accountId, Request $request)
	{
        $account = $this->getConfiguration()->getAccountBy('accountId', $accountId);

        if ($account == null) {
            throw new UserException("Account '$accountId' not found");
        }

        $params = $this->getPostJson($request);

        if (!isset($params['data'])) {
            throw new ParameterMissingException("missing parameter data");
        }

        foreach ($params['data'] as $sheetData) {
            $this->checkParams([
                'googleId',
                'title',
                'sheetId',
                'sheetTitle'
            ], $sheetData);

            $account->addSheet(new Sheet($sheetData));
        }
        $account->save();

        $sheets = $account->getData();

        $sheets = array_map(function ($item) {
            $item['sheetId'] = (string) $item['sheetId'];
            return $item;
        }, $sheets);

		return $this->getJsonResponse($sheets);
	}

    /**
     * @param $accountId
     * @param $fileId
     * @param $sheetId
     * @return JsonResponse
     */
	public function deleteSheetAction($accountId, $fileId, $sheetId)
	{
        $this->getConfiguration()->removeSheet($accountId, $fileId, $sheetId);

		return $this->getJsonResponse([], 204);
	}

	protected function getJsonResponse(array $data, $status = 200)
	{
		$response = new JsonResponse($data, $status);
		$response->headers->set('Access-Control-Allow-Origin', '*');

		return $response;
	}

    /**
     * @param Account $account
     * @return RestApi
     */
    protected function getApi(Account $account)
    {
        /** @var RestApi $googleDriveApi */
        $googleDriveApi = $this->container->get('ex_google_drive.rest_api');
        $googleDriveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());

        $this->extractor = $this->container->get('ex_google_drive.extractor');
        $this->extractor->setConfiguration($this->getConfiguration());
        $this->extractor->setCurrAccountId($account->getAccountId());

        $googleDriveApi->getApi()->setRefreshTokenCallback([$this->extractor, 'refreshTokenCallback']);

        return $googleDriveApi;
    }
}
