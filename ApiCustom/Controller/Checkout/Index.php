<?php

namespace Modernrugs\ApiCustom\Controller\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Modernrugs\Log\Helper\LoggerContainer;

/**
 * Class Index
 * @package Modernrugs\ApiCustom\Controller\Checkout
 */
class Index extends Action
{
    protected $pageFactory;
    protected $checkoutSession;
    protected $quoteIdMaskFactory;
    protected $cacheTypeList;
    protected $tokenModelFactory;
    protected $cookieManager;
    protected $customerSession;
    protected $quoteFactory;

    const COOKIE_NAME_TOKEN = 'token';
    const COOKIE_NAME_QUOTE_MASK = 'mask_quote';
    const COOKIE_DURATION = 86400; // lifetime in seconds

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        Session $session,
        TypeListInterface $cacheTypeList,
        TokenModelFactory $tokenModelFactory,
        CookieManagerInterface $cookieManager,
        QuoteFactory $quoteFactory,
        CustomerSession $customerSession,
        LoggerContainer $logger,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
        $this->pageFactory = $pageFactory;
        $this->checkoutSession = $session;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->tokenModelFactory = $tokenModelFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->quoteFactory = $quoteFactory;
        $this->logger = $logger->getLoggerAndSwitchTo(LoggerContainer::CONTEXT_NAME_LOG_CONTAINER);
        return parent::__construct($context);
    }

    public function execute()
    {
        $this->logger->info("Cron job Log is START");
//        http://magento240.com/modernrugs/checkout?mask_quote=2GJfelpTUl3Fk81jCD40D8mi6rx37tgz
        $maskQuote = $this->cookieManager->getCookie(self::COOKIE_NAME_QUOTE_MASK);
//        $maskQuote = $this->getRequest()->getParam('mask_quote');
        if (isset($maskQuote)) {
            $quoteMask = $this->quoteIdMaskFactory->create()->load($maskQuote, 'masked_id');
            $orderId = $quoteMask->getQuoteId();
//            $this->cacheTypeList->cleanType('full_page');
            $this->checkoutSession->setQuoteId($orderId);
        }

//        $metadata = $this->cookieMetadataFactory
//            ->createPublicCookieMetadata()
//            ->setDuration(self::COOKIE_DURATION);
//
//
//        $this->cookieManager->setPublicCookie(
//            self::COOKIE_NAME_TOKEN,
//            'i920yiuotbd6xuzzptmsowtyheu05zr4',
//            $metadata
//        );
//
//        $this->cookieManager->setPublicCookie(
//            self::COOKIE_NAME_QUOTE_MASK,
//            'i920yiuotbd6xuzzptmsowtyheu05zr4',
//            $metadata
//        );


        $cookieToken = $this->cookieManager->getCookie(self::COOKIE_NAME_TOKEN);

        if ($cookieToken) {
            $customerId = $this->tokenModelFactory->create()->loadByToken($cookieToken)->getCustomerId();
            $quote = $this->quoteFactory->create()->loadByCustomer($customerId);
            if ($customerId && $quote->getId()) {
                $this->customerSession->setCustomerId($customerId);
                $this->checkoutSession->setQuoteId($quote->getId());
            }
        }

        return $this->_redirect('checkout/cart');
        return $this->pageFactory->create();
    }
}
