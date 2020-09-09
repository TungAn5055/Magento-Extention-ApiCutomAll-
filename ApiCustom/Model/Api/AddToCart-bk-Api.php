<?php

namespace Modernrugs\ApiCustom\Model\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Product;
use Zend\Http\Client;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Eav\Model\Config;
use Zend\Http\Exception\RuntimeException;
use Magento\Catalog\Model\ProductRepository;

class AddToCart
{
    protected $logger;
    protected $product;
    protected $zendClient;
    protected $storeManager;
    protected $eavConfig;
    protected $productRepository;

    public function __construct(
        LoggerInterface $logger,
        Client $zendClient,
        Product $product,
        Config $eavConfig,
        ProductRepository $productRepository,
        StoreManagerInterface $storeManager
    )
    {
        $this->logger = $logger;
        $this->product = $product;
        $this->zendClient = $zendClient;
        $this->storeManager = $storeManager;
        $this->eavConfig = $eavConfig;
        $this->productRepository = $productRepository;
    }

    /**
     * @inheritdoc
     */

    public
    function getPost($product, $customer, $order)
    {
//        $this->logger->info('Start Api Modernrugs!!!!!!!!!');
        $response = ['success' => false];
        try {
            if (empty($product) || !isset($product['sku'])) {
                return json_encode(['success' => true, 'message' => 'Product can not empty!']);
            }
            $urlBase = $this->getBaseUrl();
            $token = $this->getTokenAdmin($urlBase);
            if ($token == false) {
                return json_encode(['success' => true, 'message' => 'Cannot getTokenAdmin!']);
            }

            // add attribute
            if (isset($product['variations']) && isset($product['variations'][0]) && array_keys($product['variations'][0])) {
                $attrListNotChecks = ['option', 'active', 'price'];
                $attrListCustoms = ['vendor', 'designer', 'madeIn', 'quality', 'material', 'distressed', 'patterned', 'silk', 'transitional'];
                $attFormatNames = [
                    'sizeSku' => 'size_sku',
                    'size' => 'size_modernrugs',
                    'sale' => 'sale_modernrugs',
                    'searchSize' => 'search_size',
                    'aliasSize' => 'alias_size',
                    'searchSizeFloor' => 'search_size_floor',
                    'oldPrice' => 'old_price',
                    'shippingLength' => 'shipping_length',
                    'shippingWidth' => 'shipping_width',
                    'shippingHeight' => 'shipping_height',
                    'shippingWeight' => 'shipping_weight',
                    'shippingType' => 'shipping_type',
                    'origMap' => 'orig_map',
                    'GS1' => 'gs1',
                    'madeIn' => 'made_in',
                ];

                // create attribute
                $listAttributes = array_merge(array_keys($product['variations'][0]), $attrListCustoms);
                foreach ($listAttributes as $attr) {
                    $attrCode = in_array($attr, array_keys($attFormatNames)) ? $attFormatNames[$attr] : $attr;
                    if ($this->isProductAttributeExists($attrCode) == false && !in_array($attr, $attrListNotChecks)) {
                        $uriAttribute = $urlBase . 'rest/V1/products/attributes';
                        $paramAttribute = $this->getParamsAttribute($attrCode, $product['variations']);
                        $responseData = $this->apiPost($uriAttribute, $paramAttribute, $token);
                        if ($responseData != false) {
                            $dataDecode = json_decode($responseData);
                            if ($dataDecode->attribute_id) {
                                $this->logger->info('Create done attribute code ' . $attrCode . 'with id ' . $dataDecode->attribute_id);
                                // 4: attribute set 7 :attribute group
                                $uriAttributeSet = $urlBase . 'rest/V1/products/attribute-sets/attributes';
                                $paramAttributeSet = [
                                    "attributeSetId" => 4,
                                    "attributeGroupId" => 7,
                                    "attributeCode" => $attrCode,
                                    "sortOrder" => 100
                                ];
                                $responseAttributeSet = $this->apiPost($uriAttributeSet, json_encode($paramAttributeSet), $token);
                                if ($responseAttributeSet != false) {
                                    $this->logger->info('Add attribute code tp attribute set done ' . $attrCode);
                                }
                            } else {
                                $this->logger->info('Can not create attribute code ' . $attrCode);
                            }
                        }
                    }
                }
            }

            // create product configurable
            if (!$this->product->getIdBySku($product['sku'])) {
//                $this->logger->info('Product Exist');
                $uriProduct = $urlBase . 'rest/default/V1/products';
                $paramsProductConfi = $this->getParamsProductConfiguable($product);
                $responseDataConfi = $this->apiPost($uriProduct, $paramsProductConfi, $token);
                if ($responseDataConfi != false) {
                    $dataDecodeConfi = json_decode($responseDataConfi);
                    if ($dataDecodeConfi->id) {
                        $this->logger->info('Create done Product with id ' . $dataDecodeConfi->id);
                    } else {
                        $this->logger->info('Can not create product by sku ' . $product['sku']);
                    }
                }
            }

            // create simple product of configurable
            if (isset($product['variations'])) {
                $uriProduct = $urlBase . 'rest/default/V1/products';
                foreach ($product['variations'] as $variation) {
                    if (!$this->product->getIdBySku($variation['sizeSku'])) {
                        $paramsProductSimple = $this->getParamsProductSimple($product, $variation);
                        $resSimpleProduct = $this->apiPost($uriProduct, $paramsProductSimple, $token);
                        if ($resSimpleProduct != false) {
                            $decodeSimpleProduct = json_decode($resSimpleProduct);
                            if ($decodeSimpleProduct->id) {
                                $this->logger->info('Create done Product with id ' . $decodeSimpleProduct->id);
                            } else {
                                $this->logger->info('Can not create product by sku ' . $product['sku']);
                            }
                        }
                    }
                }
            }

            // add option and product to configurable
            if ($this->product->getIdBySku($product['sku'])) {
                $productData = $this->productRepository->get($product['sku']);
                if ($productData->getTypeId() == 'configurable') {
                    $children = $productData->getTypeInstance()->getUsedProducts($productData);
                    $listChildrens = [];
                    $listChildrenAdd = [];
                    if (count($children) > 0) {
                        foreach ($children as $child) {
                            array_push($listChildrens, $child->getID());
                        }
                    }
                    if (isset($product['variations'])) {
                        foreach ($product['variations'] as $variation) {
                            $productId = $this->product->getIdBySku($product['sku']);
                            if ($productId && !in_array($productId, $listChildrens)) {
                                array_push($listChildrenAdd, $variation['sizeSku']);
                            }
                        }
                    }
                    // add option to product configuable

                    $attributeId = $this->getAttributeIdByCode('size_modernrugs');
                    if (count($listChildrenAdd) > 0 && $attributeId) {
                        $uriOption = $urlBase . '/rest/default/V1/configurable-products/' . $product['sku'] . '/options';
                        $paramsOption = [
                            "option" => [
                                "attribute_id" => $attributeId,
                                "label" => "Size",
                                "position" => 0,
                                "is_use_default" => true,
                                "values" => [
                                    [
                                        "value_index" => 0
                                    ]
                                ]
                            ]
                        ];
                        $resDataAddOption = $this->apiPost($uriOption, json_encode($paramsOption), $token);
                        if ($resDataAddOption != false) {
                            $this->logger->info('Add option done for ' . $product['sku'] . 'result ' . $resDataAddOption);
                        }
                        if ($resDataAddOption) {
                            $uriChildrenProduct = $urlBase . 'rest/default/V1/configurable-products/' . $product['sku'] . '/child';
                            foreach ($listChildrenAdd as $child) {
                                $paramsChildrenProduct = [
                                    "childSku" => $child
                                ];
                                $resDataChildren = $this->apiPost($uriChildrenProduct, json_encode($paramsChildrenProduct), $token);
                                if ($resDataChildren != false) {
                                    $this->logger->info('Add children id  ' . $child);
                                }
                            }
                        }
                    }
                }
            }

            //check and add customer
            if (empty($customer)) {
                return json_encode(['success' => true, 'message' => 'Customer can not empty']);
            }
            $response = ['success' => true, 'message' => 'annnnnnn'];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
//            $this->logger->info($e->getMessage());
        }
        return json_encode($response);
    }

    function getTokenAdmin($urlBase)
    {
        $params = ["username" => "admin", "password" => "admin123"];
        $uriToken = $urlBase . 'rest/V1/integration/admin/token';
        $token = $this->apiPost($uriToken, json_encode($params), null);
        $token = str_replace('"', '', $token);
        return $token;
    }

    public function getBaseUrl()
    {
        $urlBase = false;
        try {
            $urlBase = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        } catch (NoSuchEntityException $e) {
            $this->logger->error($e->getMessage());
        }

        return $urlBase;
    }

    public function getParamsAttribute($attributeCode, $variations)
    {
        $arrOptionSize = [];
        if ($attributeCode == 'size_modernrugs') {
            foreach ($variations as $variation) {
                if (isset($variation['size'])) {
                    $arrValue = [
                        "label" => $variation['size'],
                        "value" => $variation['size'],
                        "sort_order" => 0,
                        "is_default" => false,
                        "store_labels" => [
                            [
                                "store_id" => 0,
                                "label" => $variation['size']
                            ]
                        ]];
                    array_push($arrOptionSize, $arrValue);
                }
            }
        }

        $params = [
            "attribute" => [
                "is_wysiwyg_enabled" => false,
                "is_html_allowed_on_front" => false,
                "used_for_sort_by" => false,
                "is_filterable" => true,
                "is_filterable_in_search" => true,
                "is_used_in_grid" => false,
                "is_visible_in_grid" => false,
                "is_filterable_in_grid" => false,
                "position" => 0,
                "apply_to" => [],
                "is_searchable" => "1",
                "is_visible_in_advanced_search" => "1",
                "is_comparable" => "1",
                "is_used_for_promo_rules" => "0",
                "is_visible_on_front" => "0",
                "used_in_product_listing" => "0",
                "is_visible" => true,
                "scope" => ($attributeCode == 'size_modernrugs') ? 'global' : "store",
                "extension_attributes" => [],
                "attribute_id" => 0,
                "attribute_code" => $attributeCode,
                "frontend_input" => ($attributeCode == 'size_modernrugs') ? 'select' : "text",
                "entity_type_id" => "4",
                "is_required" => false,
                "options" =>  ($attributeCode == 'size_modernrugs') ? $arrOptionSize : [],
                "is_user_defined" => true,
                "default_frontend_label" => $attributeCode,
                "backend_type" => ($attributeCode == 'size_modernrugs') ? 'int' : "string",
                "frontend_labels" => [],
                "is_unique" => "0",
                "validation_rules" => [],
                "custom_attributes" => [],
            ],
        ];

        return json_encode($params);
    }

    public function getParamsProductConfiguable($product)
    {
        $array = explode(" . ", $product['image']);
        $imageType = '.' . end($array);
        $imageData = base64_encode(file_get_contents($product['image']));

        $paramsProduct = [
            "product" => [
                "sku" => $product['sku'],
                "name" => $product['name'],
                "attribute_set_id" => 4,
                "status" => 1,
                "visibility" => 4,
                "type_id" => "configurable",
                "extension_attributes" => [
                    "category_links" => [
                        [
                            "position" => 1,
                            "category_id" => "11",
                        ],
                        [
                            "position" => 1,
                            "category_id" => "14",
                        ],
                        [
                            "position" => 1,
                            "category_id" => "38",
                        ],
                    ],
                    "stock_item" => [
                        "qty" => '10',
                        "is_in_stock" => true,
                        "is_qty_decimal" => true,
                    ],
                ],
                "custom_attributes" => [
                    [
                        "attribute_code" => "description",
                        "value" => $product['description'],
                    ],
                    [
                        "attribute_code" => "image",
                        "value" => "https://www.modernrugs.com/rug_images/5810_VNE10_yellow_medium.jpg"
                    ],
                    ["attribute_code" => "small_image", "value" => "https://www.modernrugs.com/rug_images/5810_VNE10_yellow_medium.jpg"],
                    ["attribute_code" => "thumbnail", "value" => "https://www.modernrugs.com/rug_images/5810_VNE10_yellow_medium.jpg"],
                    ["attribute_code" => "material",
                        "value" => $product['material']
                    ],
                    [
                        "attribute_code" => "collection",
                        "value" => $product['collection']
                    ],
                    [
                        "attribute_code" => "vendor",
                        "value" => $product['vendor']
                    ],
                    [
                        "attribute_code" => "designer",
                        "value" => $product['designer']
                    ],
                    [
                        "attribute_code" => "made_in",
                        "value" => $product['madeIn']
                    ],
//                            [
//                                "attribute_code" => "distressed",
//                                "value" => $product['distressed']
//                            ],
//                            [
//                                "attribute_code" => "patterned",
//                                "value" => $product['patterned']
//                            ],
//                            [
//                                "attribute_code" => "silk",
//                                "value" => $product['silk']
//                            ],
//                            [
//                                "attribute_code" => "transitional",
//                                "value" => $product['transitional']
//                            ],
                ],
                "media_gallery_entries" => [
                    [
                        "id" => 0,
                        "media_type" => "image",
                        "label" => 'name.jpeg',
                        "position" => 0,
                        "disabled" => false,
                        "types" => [
                            "image",
                            "small_image",
                            "thumbnail",
                            "swatch_image",
                        ],
                        "file" => $product['image'],
                        "content" => [
                            "base64_encoded_data" => $imageData,
                            "type" => "image/jpeg",
                            "name" => 'name.jpeg'
                        ],
                    ]
                ],
            ]];
        return json_encode($paramsProduct);

    }

    public function getParamsProductSimple($product, $variable)
    {
        $array = explode(".", $product['image']);
        $imageType = '.' . end($array);
        $imageData = base64_encode(file_get_contents($product['image']));

        $sizeModernrugs = 0;
        $attribute = $this->eavConfig->getAttribute('catalog_product', 'size_modernrugs');
        $options = $attribute->getSource()->getAllOptions();
        foreach ($options as $op) {
            if($op['label'] == $variable['size']){
                $sizeModernrugs = $op['value'];
            }
        }
        var_dump($variable['size']);
        var_dump($sizeModernrugs);
        var_dump($options);

        $paramsProduct = [
            "product" => [
                "sku" => $variable['sizeSku'],
                "name" => $product['name'] . $variable['size'],
                "attribute_set_id" => 4,
                "status" => 1,
                "price" => $variable['price'],
                "visibility" => 4,
                "type_id" => "simple",
                "extension_attributes" => [
                        "category_links" => [[
                            "position" => 1,
                            "category_id" => "11",
                        ],
                        [
                            "position" => 1,
                            "category_id" => "14",
                        ],
                        [
                            "position" => 1,
                            "category_id" => "38",
                        ],
                            ],
                    "stock_item" => [
                        "qty" => 100,
                        "is_in_stock" => true,
                        "is_qty_decimal" => true,
                    ],
                ],
                "custom_attributes" => [
                    [
                        "attribute_code" => "description",
                        "value" => $product['description'],
                    ],
                    [
                        "attribute_code" => "gs1",
                        "value" => $variable['GS1']
                    ],
                    [
                        "attribute_code" => "size_modernrugs",
                        "value" => $sizeModernrugs
                    ],
                    [
                        "attribute_code" => "search_size",
                        "value" => $variable['searchSize']
                    ],
                    [
                        "attribute_code" => "alias_size",
                        "value" => $variable['aliasSize']
                    ],
                    [
                        "attribute_code" => "search_size_floor",
                        "value" => $variable['searchSizeFloor']
                    ],
                    [
                        "attribute_code" => "shape",
                        "value" => $variable['shape']
                    ],
                    [
                        "attribute_code" => "old_price",
                        "value" => $variable['oldPrice']
                    ],
                    [
                        "attribute_code" => "sale_modernrugs",
                        "value" => $variable['sale']
                    ],
                    [
                        "attribute_code" => "width",
                        "value" => $variable['width']
                    ],
                    [
                        "attribute_code" => "length",
                        "value" => $variable['length']
                    ],
                    [
                        "attribute_code" => "height",
                        "value" => $variable['height']
                    ],
                    [
                        "attribute_code" => "shipping_length",
                        "value" => $variable['shippingLength']
                    ],
                    [
                        "attribute_code" => "shipping_width",
                        "value" => $variable['shippingWidth']
                    ],
                    [
                        "attribute_code" => "shipping_weight",
                        "value" => $variable['shippingWeight']
                    ],
                    [
                        "attribute_code" => "shipping_length",
                        "value" => $variable['shippingLength']
                    ],
                    [
                        "attribute_code" => "shipping_type",
                        "value" => $variable['shippingType']
                    ],
                    [
                        "attribute_code" => "orig_map",
                        "value" => $variable['origMap']
                    ],
                    [
                        "attribute_code" => "wholesale",
                        "value" => $variable['wholesale']
                    ],
                    [
                        "attribute_code" => "msrp",
                        "value" => $variable['MSRP']
                    ],
                ],
                "media_gallery_entries" => [
                    [
                        "id" => 0,
                        "media_type" => "image",
                        "label" => $product['sku'] . $imageType,
                        "position" => 0,
                        "disabled" => false,
                        "types" => [
                            "image",
                            "small_image",
                            "thumbnail",
                            "swatch_image",
                        ],
                        "file" => $product['image'],
                        "content" => [
                            "base64_encoded_data" => $imageData,
                            "type" => "image/jpeg",
                            "name" => 'name' . $imageType
                        ],
                    ]
                ],
            ]];
        return json_encode($paramsProduct);

    }

    /**
     * Returns true if attribute exists and false if it doesn't exist
     *
     * @param string $field
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isProductAttributeExists($attributeCode)
    {
        $attr = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);

        return $attr && $attr->getId();
    }

    public function getAttributeIdByCode($attributeCode)
    {
        $attr = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);

        return ($attr && $attr->getId()) ? $attr->getId() : false;
    }

    public function apiPost($uri, $paramPost, $token = null)
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
            if (!is_null($token)) {
                $headers["Authorization"] = 'Bearer ' . $token;
            }

            $this->zendClient->reset();
            $this->zendClient->setUri($uri);
            $this->zendClient->setMethod(\Zend\Http\Request::METHOD_POST);
            $this->zendClient->setHeaders($headers);
            $this->zendClient->setRawBody($paramPost);
            $this->zendClient->send();
            if ($this->zendClient->getResponse()->getStatusCode() == 200) {
                return $response = $this->zendClient->getResponse()->getBody();
            } else {
                $this->logger->error($this->zendClient->getResponse()->getStatusCode());
                var_dump('error');
//                var_dump($paramPost);
                var_dump($this->zendClient->getResponse()->getStatusCode());
                var_dump($this->zendClient->getResponse()->getContent());
                return false;
            }
        } catch (RuntimeException $runtimeException) {
            $this->logger->error($runtimeException->getMessage());;
            return false;
        }
    }
}
