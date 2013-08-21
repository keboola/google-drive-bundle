<?php
/**
 * DataManager.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use Keboola\Google\DriveBundle\Entity\Sheet;
use Keboola\Google\DriveBundle\Extractor\Configuration;
use Keboola\StorageApi\Table;

class DataManager
{
	/** @var Configuration */
	protected $configuration;

	public function __construct(Configuration $configuration)
	{
		$this->configuration = $configuration;
	}

	public function save($data, Sheet $sheet)
	{
		$sheetConfig = $sheet->getConfig();
		$tmpFilename = $this->writeRawCsv($data, $sheet);

		$dataProcessor = new DataProcessor($tmpFilename, $sheetConfig);
		$outFilename = $dataProcessor->process();
		unlink($tmpFilename);

		$this->configuration->initDataBucket($sheet->getAccount()->getAccountId());

		$table = new Table($this->configuration->getStorageApi(), $sheetConfig['db']['table'], $outFilename);
		$table->save(true);
	}

	protected function writeRawCsv($data, Sheet $sheet)
	{
		$file = ROOT_PATH . "app/tmp/" . str_replace(' ', '-', $sheet->getTitle())
			. "_" . $sheet->getSheetId() . "_" . date('Y-m-d') . ".csv";

		$fh = fopen($file, 'w+');

		if (!$fh) {
			throw new \Exception("Can't write to file " . $file);
		}
		fwrite($fh, utf8_encode($data));
		fclose($fh);

		return $file;
	}

}
