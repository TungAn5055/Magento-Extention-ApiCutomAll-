<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Modernrugs\ApiCustom\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Quote\Api\ChangeQuoteControlInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Registry;

/**
 * {@inheritdoc}
 */
class ChangeQuoteControl implements ChangeQuoteControlInterface
{
    /**
     * @var UserContextInterface $userContext
     */
    private $userContext;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * ChangeQuoteControl constructor.
     * @param UserContextInterface $userContext
     * @param Registry $registry
     */
    public function __construct(UserContextInterface $userContext, Registry $registry)
    {
        $this->userContext = $userContext;
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllowed(CartInterface $quote): bool
    {
        if ($this->registry->registry('modernrugs')) {
            return true;
        }
        switch ($this->userContext->getUserType()) {
            case UserContextInterface::USER_TYPE_CUSTOMER:
                $isAllowed = ($quote->getCustomerId() == $this->userContext->getUserId());
                break;
            case UserContextInterface::USER_TYPE_GUEST:
                $isAllowed = ($quote->getCustomerId() === null);
                break;
            case UserContextInterface::USER_TYPE_ADMIN:
            case UserContextInterface::USER_TYPE_INTEGRATION:
                $isAllowed = true;
                break;
            default:
                $isAllowed = false;
        }

        return $isAllowed;
    }
}
