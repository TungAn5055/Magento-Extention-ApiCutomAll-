<?php

namespace Modernrugs\Log\Handler;

use Modernrugs\Log\Formatter\CsvLineFormatter;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Stdlib\DateTime;

class CsvHandler extends Base
{
    /**
     * File name
     * @var string
     */
    protected $fileName = 'log_%s.csv';

    /**
     * @var DateTime $dateTime
     */
    protected $dateTime;

    /**
     * @var CsvLineFormatter $csvLineFormatter
     */
    protected $csvLineFormatter;

    /**
     * CsvHandler constructor.
     * @param DriverInterface $filesystem
     * @param DateTime $dateTime
     * @param CsvLineFormatter $csvLineFormatter
     * @param string $fileName
     * @param string $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        DateTime $dateTime,
        CsvLineFormatter $csvLineFormatter,
        $fileName = null,
        $filePath = null
    ) {
        if (isset($fileName)) {
            $this->fileName = $fileName;
        }
        // Separate on end of months
        $this->dateTime = $dateTime;
        $this->extractToNewMonth();
        parent::__construct($filesystem, $filePath);

        $this->csvLineFormatter = $csvLineFormatter;
        $this->setFormatter($this->csvLineFormatter);

        $this->insertHeader($this->csvLineFormatter->getHeader());
        $this->pushProcessor(function ($record) {
            return $this->buildToCsv($record);
        });
    }

    /**
     * Extract to a new log file if it's first day of month
     */
    protected function extractToNewMonth()
    {
        $this->fileName = sprintf($this->fileName, $this->dateTime->gmDate('mY', time()));
    }

    /**
     * Insert header to top of file
     * @param array $header
     */
    protected function insertHeader($header)
    {
        if (!$this->filesystem->isExists($this->url)) {
            $this->write(['formatted' => $header]);
        } else if ($this->filesystem->stat($this->url)['size'] == 0) {
            $this->write(['formatted' => $header]);
        }
    }

    /**
     * Escapce double quote
     * @param array $record
     * @return array
     */
    protected function buildToCsv($record)
    {
        return $this->csvLineFormatter->csvEscapeDoubleQuote($record);
    }
}
