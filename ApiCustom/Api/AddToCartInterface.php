<?php

namespace Modernrugs\ApiCustom\Api;

interface AddToCartInterface
{
    /**
     * GET for Post api
     * @param mixed|null $product
     * @param mixed|null $customer
     * @param mixed|null $order
     * @param string|null $maskCart
     * @param string|null $token
     * @return string
     */
    public function getPost($product, $customer, $order, $maskCart, $token);
}
