<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 14/01/14
 * Time: 14:01
 */

namespace Keboola\Google\DriveBundle\Controller;


use Keboola\Google\DriveBundle\Exception\ParameterMissingException;
use Keboola\Google\DriveBundle\GoogleDriveExtractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Syrup\ComponentBundle\Controller\ApiController;
use Syrup\ComponentBundle\Exception\UserException;

class GoogleDriveController extends ApiController
{

	// @todo: refactor
	public function preExecute(Request $request)
	{
		parent::preExecute($request);

		$this->initStorageApi();
		$this->initComponent($this->storageApi, $this->componentName);
	}

	/** Tokens */

	public function postExternalAuthLinkAction(Request $request)
	{
		$post = $this->getPostJson($request);

		if (!isset($post['account'])) {
			throw new ParameterMissingException("Parameter 'account' is required");
		}

		if (!isset($post['referrer'])) {
			throw new ParameterMissingException("Parameter 'referrer' is required");
		}

		$token = $this->getComponent()->getToken();

		$referrer = $post['referrer'] . '?token=' . $token['token'] .'&account=' . $post['account'];

		$url = $this->generateUrl('keboola_google_drive_external_auth', array(
			'token'     => $token['token'],
			'account'   => $post['account'],
			'referrer'  => $referrer
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

	public function postConfigsAction(Request $request)
	{
		$account = $this->getComponent()->postConfigs($this->getPostJson($request));

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
		$account = $this->getComponent()->getAccount($id);

		if ($account == null) {
			throw new UserException(sprintf('Account %s not found', $id));
		}

		return $this->getJsonResponse($account);
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

	public function postSheetsAction($accountId, Request $request)
	{
		return $this->getJsonResponse($this->getComponent()->postSheets($accountId, $this->getPostJson($request)));
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
