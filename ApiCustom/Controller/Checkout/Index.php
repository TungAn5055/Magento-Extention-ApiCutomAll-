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
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

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
    const COOKIE_DOMAIN = 'http://magento233.com/';
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
        CookieMetadataFactory $cookieMetadataFactory,
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
        $this->logger->info("Modernrugs Checkout Start!");
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
////            ->setPath('/')
////            ->setDuration(self::COOKIE_DURATION)
////            ->setDomain(self::COOKIE_DOMAIN)
//            ->setHttpOnly(false);

//        $this->cookieManager->setPublicCookie(
//            self::COOKIE_NAME_TOKEN,
//            'ymc6muketvdknlkzd9xspsfqgfm97d6b',
//            $metadata
//        );

//        $this->cookieManager->setPublicCookie(
//            self::COOKIE_NAME_QUOTE_MASK,
//            'i920yiuotbd6xuzzptmsowtyheu05zr4',
//            $metadata
//        );

        $cookieToken = $this->cookieManager->getCookie(self::COOKIE_NAME_TOKEN);
        $this->logger->info("Get cookie token: " . $cookieToken);
        if ($cookieToken) {
            $customerId = $this->tokenModelFactory->create()->loadByToken($cookieToken)->getCustomerId();
            $this->logger->info("Get cookie token: " . $customerId);
            $quote = $this->quoteFactory->create()->loadByCustomer($customerId);
            $this->logger->info("Get Quote form customer: " . $quote->getId());
            if ($customerId && $quote->getId()) {
                $this->customerSession->setCustomerId($customerId);
                $this->checkoutSession->setQuoteId($quote->getId());
//                $this->cacheTypeList->cleanType('full_page');
            }
        }
//        return $this->_redirect('checkout/cart');
        return $this->pageFactory->create();
    }
}
