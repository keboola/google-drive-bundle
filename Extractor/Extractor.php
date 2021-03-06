<?php
/**
 * Extractor.php
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use GuzzleHttp\Exception\RequestException;
use Keboola\Google\DriveBundle\Entity\Account;
use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Exception\ConfigurationException;
use Keboola\Google\DriveBundle\GoogleDrive\RestApi;
use Monolog\Logger;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\Syrup\Exception\UserException;
use Keboola\Temp\temp;

class Extractor
{
    /** @var RestApi */
    protected $driveApi;

    /** @var Configuration */
    protected $configuration;

    /** @var DataManager */
    protected $dataManager;

    protected $currAccountId;

    /** @var Logger */
    protected $logger;

    /** @var Temp */
    protected $temp;

    public function __construct(RestApi $driveApi, Logger $logger, Temp $temp)
    {
        $this->driveApi = $driveApi;
        $this->logger = $logger;
        $this->temp = $temp;
    }

    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function run($options = null)
    {
        $this->dataManager = new DataManager($this->configuration, $this->temp);

        $accounts = $this->configuration->getAccounts();

        if (isset($options['account']) || isset($options['config'])) {

            $accountId = isset($options['account']) ? $options['account'] : $options['config'];

            if (!isset($accounts[$accountId])) {
                throw new ConfigurationException("Account '" . $accountId . "' does not exist.");
            }
            $accounts = array(
                $accountId => $accounts[$accountId]
            );
        }

        if (isset($options['sheetId'])) {
            if (!isset($options['config']) && !isset($options['account'])) {
                throw new UserException("Missing parameter 'config'");
            }
            if (!isset($options['googleId'])) {
                throw new UserException("Missing parameter 'googleId'");
            }
        }

        $status = array();

        /** @var Account $account */
        foreach ($accounts as $accountId => $account) {

            $this->currAccountId = $accountId;

            $this->driveApi->getApi()->setCredentials($account->getAccessToken(), $account->getRefreshToken());
            $this->driveApi->getApi()->setRefreshTokenCallback(array($this, 'refreshTokenCallback'));

            $sheets = $account->getSheets();
            if (isset($options['sheetId'])) {
                $sheets = [$account->getSheet($options['googleId'], $options['sheetId'])];
            }

            /** @var Sheet $sheet */
            foreach ($sheets as $sheet) {
                $this->logger->info('Importing sheet ' . $sheet->getSheetTitle());

                try {
                    $meta = $this->driveApi->getFile($sheet->getGoogleId());
                } catch (RequestException $e) {
                    if ($e->getResponse()->getStatusCode() == 404) {
                        throw new UserException(sprintf("File '%s' not found in Google Drive", $sheet->getTitle()), $e);
                    } else {
                        $userException = new UserException("Google Drive Error: " . $e->getMessage(), $e);
                        $userException->setData(array(
                            'message' => $e->getMessage(),
                            'reason'  => $e->getResponse()->getReasonPhrase(),
                            'account' => $accountId,
                            'sheet'   => $sheet->toArray()
                        ));
                        throw $userException;
                    }
                }

                if (!isset($meta['exportLinks'])) {
                    $e = new ApplicationException("ExportLinks missing in file resource");
                    $e->setData([
                        'fileMetadata' => $meta
                    ]);
                    throw $e;
                }

                if (isset($meta['exportLinks']['text/csv'])) {
                    $exportLink = $meta['exportLinks']['text/csv'] . '&gid=' . $sheet->getSheetId();
                } else {
                    $exportLink = str_replace('pdf', 'csv', $meta['exportLinks']['application/pdf']) . '&gid=' . $sheet->getSheetId();
                }

                try {
                    $data = $this->driveApi->export($exportLink);

                    if ($data->getSize() > 0) {
                        $this->dataManager->save($data, $sheet);
                    } else {
                        $this->logger->warning(sprintf(
                            "Sheet is empty. File: '%s', Sheet: '%s'.",
                            $sheet->getTitle(),
                            $sheet->getSheetTitle()
                        ));
                        $status[$accountId][$sheet->getSheetTitle()] = "file is empty";
                    }
                } catch (RequestException $e) {
                    $userException = new UserException("Error importing file - sheet: '" . $sheet->getTitle() . " - " . $sheet->getSheetTitle() . "'. ", $e);
                    $userException->setData(array(
                        'message' => $e->getMessage(),
                        'reason'  => $e->getResponse()->getReasonPhrase(),
                        'body'    => substr($e->getResponse()->getBody(), 0, 300),
                        'account' => $accountId,
                        'sheet'   => $sheet->toArray()
                    ));
                    throw $userException;
                }
            }
        }

        return array(
            "status" => "ok",
            "sheets" => $status,
        );
    }

    public function setCurrAccountId($id)
    {
        $this->currAccountId = $id;
    }

    public function getCurrAccountId()
    {
        return $this->currAccountId;
    }

    public function refreshTokenCallback($accessToken, $refreshToken)
    {
        $account = $this->configuration->getAccountBy('accountId', $this->currAccountId);
        $account->setAccessToken($accessToken);
        $account->setRefreshToken($refreshToken);
        $account->save();
    }
}
