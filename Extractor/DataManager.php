<?php
/**
 * DataManager.php
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Table;
use Keboola\Temp\Temp;
use SplFileInfo;
use Keboola\Syrup\Exception\UserException;
use GuzzleHttp\Stream\Stream;

class DataManager
{
    /** @var Configuration */
    protected $configuration;

    /** @var Temp */
    protected $temp;

    public function __construct(Configuration $configuration, Temp $temp)
    {
        $this->configuration = $configuration;
        $this->temp = $temp;
    }

    public function save($data, Sheet $sheet)
    {
        $sheetConfig = $sheet->getConfig();
        $tmpFilename = $this->writeRawCsv($data, $sheet);

        $dataProcessor = new DataProcessor($tmpFilename, $sheetConfig);
        $outFilename = $dataProcessor->process();

        $this->configuration->initDataBucket($sheetConfig['db']['table']);

        $outputTable = $sheetConfig['db']['table'];
        $tableNameArr = explode('.', $outputTable);

        if (count($tableNameArr) != 3) {
            throw new UserException(sprintf("Error in configuration. Wrong tableId format '%s'", $outputTable));
        }

        $table = new Table($this->configuration->getStorageApi(), $outputTable, $outFilename);

        try {
            $table->save(true);
        } catch (ClientException $e) {
            throw new UserException($e->getMessage(), $e, [
                'outputTable' => $outputTable,
                'sheet' => $sheet->toArray()
            ]);
        }

        unlink($tmpFilename);
    }

    protected function writeRawCsv($data, Sheet $sheet)
    {
        $fileName = $sheet->getGoogleId() . "_" . $sheet->getSheetId() . "_" . date('Y-m-d') . '-' . uniqid() . ".csv";

        /** @var SplFileInfo $fileInfo */
        $fileInfo = $this->temp->createFile($fileName);

        $fh = fopen($fileInfo->getPathname(), 'w+');

        if (!$fh) {
            throw new \Exception("Can't write to file " . $fileInfo->getPathname());
        }

        /* @var Stream $data */
        fwrite($fh, $data->getContents());
        fclose($fh);

        return $fileInfo->getPathname();
    }

}
