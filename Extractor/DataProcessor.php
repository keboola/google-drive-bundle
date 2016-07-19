<?php
/**
 * DataProcessor.php
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 24.6.13
 */

namespace Keboola\Google\DriveBundle\Extractor;

use Keboola\Csv\CsvFile;
use Keboola\Syrup\Utility\Utility;

class DataProcessor
{
    protected $config;

    /** @var CsvFile */
    protected $inputCsv;

    /** @var CsvFile */
    protected $outputCsv;

    protected $outputFile;

    public function __construct($inputFile, $config)
    {
        $this->config = $config;
        $this->inputCsv = new CsvFile($inputFile);
        $this->outputFile = substr($inputFile, 0, (count($inputFile) - 5)) . '_out.csv';
        $this->outputCsv = new CsvFile($this->outputFile);
    }

    /**
     * Process file using config settings
     * @return string Output file name
     */
    public function process()
    {
        $i = 0;
        $csvHeader = array();
        $csvTransposeHeader = null;
        $csvHeaderRaw = array();

        foreach ($this->inputCsv as $csvRow) {
            if ($i < $this->config['header']['rows']) {
                if ($i == $this->config['header']['rows'] - 1) {
                    $csvHeaderRaw = $csvRow;
                    $csvOutHeaderArr = $csvHeaderRaw;

                    if (isset($this->config['header']['columns'])) {
                        $csvOutHeaderArr = $this->config['header']['columns'];
                    }
                    if (isset($this->config['transform']['transpose'])) {
                        $csvOutHeaderArr = $this->transposeHeader($csvOutHeaderArr);
                    }
                    if (isset($this->config['transform']['merge'])) {
                        $csvOutHeaderArr = $this->mergeHeader($csvOutHeaderArr);
                    }
                    if (!isset($this->config['header']['sanitize']) || $this->config['header']['sanitize'] != 0) {
                        $csvOutHeaderArr = $this->normalizeCsvHeader($csvOutHeaderArr);
                    }

                    $this->outputCsv->writeRow($csvOutHeaderArr);
                } else {
                    $csvTransposeHeader = $csvRow;
                }

            } else {
                if (isset($this->config['transform'])) {
                    // Transpose
                    if (isset($this->config['transform']['transpose'])) {
                        $this->transpose($csvRow, $csvHeaderRaw, $csvTransposeHeader);
                    }
                    // Merge
                    if (isset($this->config['transform']['merge'])) {
                        $csvRow = $this->merge($csvRow);
                    }
                }

                if (!isset($this->config['transform']['transpose'])) {
                    $this->outputCsv->writeRow($csvRow);
                }
            }

            $i++;
        }

        return $this->outputFile;
    }

    protected function transpose($csvRow, $csvHeaderRaw, $csvTransposeHeader)
    {
        $transposeFrom = $this->config['transform']['transpose']['from'];

        $outRowArr = array_slice($csvRow, 0, ($transposeFrom - 1));
        $transposeCsvRow = array_slice($csvRow, ($transposeFrom - 1), null, true);

        foreach ($transposeCsvRow as $k => $v) {
            $outRowArr['key'] = $csvHeaderRaw[$k];
            $outRowArr['value'] = $v;

            if (!is_null($csvTransposeHeader) && !empty($csvTransposeHeader[$k])) {
                $outRowArr[$this->config['header']['transpose']['name']] = $csvTransposeHeader[$k];
            }

            $this->outputCsv->writeRow($outRowArr);
        }
    }

    protected function transposeHeader($csvHeader)
    {
        $transposeFrom = $this->config['transform']['transpose']['from'];
        $csvOutHeaderArr = array_slice($csvHeader, 0, ($transposeFrom - 1));
        $csvOutHeaderArr[] = 'key';
        $csvOutHeaderArr[] = 'value';

        if (isset($this->config['header']['transpose']['name'])) {
            $csvOutHeaderArr[] = $this->config['header']['transpose']['name'];
        }

        return $csvOutHeaderArr;
    }

    protected function merge($csvRow)
    {
        $from = $this->config['transform']['merge']['from'];
        $length = $this->config['transform']['merge']['length'];
        $size = count($csvRow);

        $outRowArr = array_slice($csvRow, 0, $from);

        $mergeArr = array_slice($csvRow, $from);

        for ($i = 0; $i < $size; $i = $i + $length) {
            $slice = array_slice($mergeArr, $i, $length);

            $isEmpty = true;
            foreach ($slice as $col) {
                if (!empty($col)) {
                    $isEmpty = false;
                    break;
                }
            }

            if (!$isEmpty) {
                $outRowArr = array_merge($outRowArr, $slice);
                break;
            }
        }

        return $outRowArr;
    }

    protected function mergeHeader($csvHeader)
    {
        $from = $this->config['transform']['merge']['from'];
        $length = $this->config['transform']['merge']['length'];
        $csvOutHeaderArr = array_slice($csvHeader, 0, $from + $length);

        return $csvOutHeaderArr;
    }

    protected function normalizeCsvHeader($header)
    {
        foreach ($header as &$col) {
            $col = $this->sanitize($col);
        }

        return $header;
    }

    protected function sanitize($string)
    {
        $string = str_replace('#', 'count', $string);
        $string = Utility::unaccent($string);
        $string = preg_replace("/[\n\r]/","",$string);
        $string = preg_replace("/[^A-Za-z0-9_\s]/", '', $string);
        $string = trim($string);
        $string = str_replace(' ', '_', $string);

        if (strlen($string) < 2) {
            $string = 'empty';
        }

        return $string;
    }
}
