<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 14/01/14
 * Time: 14:01
 */

namespace Keboola\Google\DriveBundle\Controller;


use Keboola\Google\DriveBundle\GoogleDriveExtractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Syrup\ComponentBundle\Controller\ApiController;

class GoogleDriveController extends ApiController
{
	/** Tokens */

	public function getExternalAuthLinkAction($accountId)
	{
		$token = $this->getComponent()->getToken();

		$url = $this->generateUrl('keboola_google_drive_external_auth', array(
			'token'     => $token['token'],
			'account'   => $accountId,
			'referrer'  => $this->generateUrl('keboola_google_drive_external_auth_finish', array(), true)
		), true);

		return $this->createJsonResponse(array(
			'link'  => $url
		));
	}

	/** Configs */

	public function getConfigsAction()
	{
		return $this->getJsonResponse($this->getComponent()->getConfigs());
	}

	public function postConfigsAction()
	{
		$account = $this->getComponent()->postConfigs($this->getPostJson($this->getRequest()));

		return $this->getJsonResponse(array(
			'id'    => $account->getAccountId(),
			'name'  => $account->getAccountName(),
			'description'   => $account->getDescription()
		));
	}

	public function deleteConfigAction($id)
	{
		$this->getComponent()->deleteConfig($id);

		return $this->getJsonResponse(array(), 204);
	}


	/** Accounts */

	public function getAccountAction($id)
	{
		return $this->getJsonResponse($this->getComponent()->getAccount($id));
	}

	public function getAccountsAction()
	{
		return $this->getJsonResponse($this->getComponent()->getAccounts());
	}


	/** Files */

	public function getFilesAction($accountId)
	{
		return $this->getJsonResponse($this->getComponent()->getFiles($accountId));
	}


	/** Sheets */

	public function getSheetsAction($accountId, $fileId)
	{
		return $this->getJsonResponse($this->getComponent()->getSheets($accountId, $fileId));
	}

	public function postSheetsAction($accountId)
	{
		return $this->getJsonResponse($this->getComponent()->postSheets($accountId, $this->getPostJson($this->getRequest())));
	}

	public function deleteSheetAction($accountId, $fileId, $sheetId)
	{
		$this->getComponent()->deleteSheet($accountId, $fileId, $sheetId);

		return $this->getJsonResponse(array(), 204);
	}


	/**
	 * @return GoogleDriveExtractor
	 */
	protected function getComponent()
	{
		return $this->component;
	}

	protected function getJsonResponse(array $data, $status = 200)
	{
		$response = new JsonResponse($data, $status);
		$response->headers->set('Access-Control-Allow-Origin', '*');

		return $response;
	}

}