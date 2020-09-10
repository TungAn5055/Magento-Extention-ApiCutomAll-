<?php

namespace Modernrugs\Log\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

class BaseConfig
{
    /**
     * Export sftp configuration
     */
    const COMMON_MINIMUM_FILE_SIZE = "/common_settings/minimum_file_size";
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var
     */
    protected $logContext;

    protected $options;

    /**
     * BaseConfig constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param string $logContext
     * @param array $options
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $logContext,
        $options = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logContext = $logContext;
        $this->options = $options;
    }

    /**
     * Get minimum file size in bytes
     * @return int
     */
    public function getMinimumFileSize()
    {
        return 0;
    }

    public function getRootOnVarFolder()
    {
        return '';
    }

    /**
     * Get file name
     * @return string|null
     */
    public function getLogFolder()
    {
        return 'addtocart';
    }

    /**
     * Get log context configure
     * @return string
     */
    public function getLogContext()
    {
        return $this->logContext;
    }

    /**
     * Get option by key
     * @param string $key
     * @return mixed
     */
    public function getOptionByKey($key)
    {
        if (array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }
        return null;
    }
}
