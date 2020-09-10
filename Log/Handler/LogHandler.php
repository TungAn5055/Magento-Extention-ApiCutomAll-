<?php

namespace Modernrugs\Log\Handler;

use Modernrugs\Log\Formatter\CsvLineFormatter;
use Modernrugs\Log\Handler\CsvHandler;
use Modernrugs\Log\Helper\BaseConfig;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Stdlib\DateTime;

class LogHandler extends CsvHandler
{
    /**
     * LogHandler constructor.
     * @param DriverInterface $filesystem
     * @param DateTime $dateTime
     * @param CsvLineFormatter $csvLineFormatter
     * @param BaseConfig $baseConfig
     * @param string|null $fileName
     * @param string|null $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        DateTime $dateTime,
        CsvLineFormatter $csvLineFormatter,
        BaseConfig $baseConfig,
        $fileName = null,
        $filePath = null
    ) {
        $rootPath = BP . '/var/' . trim($baseConfig->getRootOnVarFolder(), '/') . '/';
        $filePath = $rootPath . trim($baseConfig->getLogFolder(), '/') . '/';
        parent::__construct($filesystem, $dateTime, $csvLineFormatter, $fileName, $filePath);
    }
}
