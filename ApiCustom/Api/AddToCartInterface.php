<?php

namespace Modernrugs\ApiCustom\Api;

interface AddToCartInterface
{
    /**
     * GET for Post api
     * @param mixed|null $product
     * @param mixed|null $customer
     * @param mixed|null $order
     * @return string
     */
    public function getPost($product, $customer, $order);
}