<?php
/**
 * DataManager.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\StorageApi\Table;
use SplFileInfo;
use Syrup\ComponentBundle\Filesystem\Temp;

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

		$this->configuration->initDataBucket($sheet->getAccount()->getAccountId());

		$table = new Table($this->configuration->getStorageApi(), $sheetConfig['db']['table'], $outFilename);
		$table->save(true);

		unlink($tmpFilename);
	}

	protected function writeRawCsv($data, Sheet $sheet)
	{
		$fileName = str_replace(' ', '-', $sheet->getTitle()) . "_" . $sheet->getSheetId() . "_" . date('Y-m-d') . '-' . uniqid() . ".csv";

		/** @var SplFileInfo $fileInfo */
		$fileInfo = $this->temp->createFile($fileName);

		$fh = fopen($fileInfo->getPathname(), 'w+');

		if (!$fh) {
			throw new \Exception("Can't write to file " . $fileInfo->getPathname());
		}

		fwrite($fh, $data);
		fclose($fh);

		return $fileInfo->getPathname();
	}

}
