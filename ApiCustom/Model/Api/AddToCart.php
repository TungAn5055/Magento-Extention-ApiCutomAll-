<?php

namespace Modernrugs\ApiCustom\Model\Api;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Setup\Exception;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Product;
use Zend\Http\Client;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Eav\Model\Config;
use Zend\Http\Exception\RuntimeException;
use Magento\Catalog\Model\ProductRepository;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionFactory;
use Magento\Eav\Model\Entity\Attribute\Option as AttributeOption;
use Magento\Eav\Api\Data\AttributeOptionLabelInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Directory\Model\Region;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Registry;

/**
 * Class AddToCart
 * @package Modernrugs\ApiCustom\Model\Api
 */
class AddToCart
{
    protected $logger;
    protected $product;
    protected $storeManager;
    protected $eavConfig;
    protected $productRepository;
    protected $eavSetupFactory;
    protected $productFactory;
    protected $directoryList;
    protected $file;
    protected $optionsFactory;
    protected $attributeOption;
    protected $attributeOptionLabel;
    protected $attributeOptionManagement;
    protected $customerFactory;
    protected $customerRepository;
    protected $dataAddressFactory;
    protected $customerAccountManagement;
    protected $encryptorInterface;
    protected $region;
    protected $quoteFactory;
    protected $cart;
    protected $cartRepository;
    protected $cartManagementInterface;
    protected $registry;
    protected $quoteIdMaskFactory;

    const DEFAULT_WEBSITE = 1;
    const DEFAULT_STORE = 1;
    const DEFAULT_ATTRIBUTE = 'size_modernrugs';

    /**
     * AddToCart constructor.
     * @param LoggerInterface $logger
     * @param Product $product
     * @param Config $eavConfig
     * @param ProductRepository $productRepository
     * @param EavSetupFactory $eavSetupFactory
     * @param ProductFactory $productFactory
     * @param DirectoryList $directoryList
     * @param File $file
     * @param OptionFactory $optionsFactory
     * @param AttributeOption $attributeOption
     * @param AttributeOptionLabelInterface $attributeOptionLabel
     * @param AttributeOptionManagementInterface $attributeOptionManagement
     * @param CustomerInterfaceFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressInterfaceFactory $dataAddressFactory
     * @param AccountManagementInterface $customerAccountManagement
     * @param EncryptorInterface $encryptorInterface
     * @param Region $region
     * @param CartManagementInterface $cartManagementInterface
     * @param QuoteFactory $quoteFactory
     * @param CartRepositoryInterface $cartRepository
     * @param Cart $cart
     * @param Registry $registry
     * @param Client $zendClient
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        LoggerInterface $logger,
        Product $product,
        Config $eavConfig,
        ProductRepository $productRepository,
        EavSetupFactory $eavSetupFactory,
        ProductFactory $productFactory,
        DirectoryList $directoryList,
        File $file,
        OptionFactory $optionsFactory,
        AttributeOption $attributeOption,
        AttributeOptionLabelInterface $attributeOptionLabel,
        AttributeOptionManagementInterface $attributeOptionManagement,
        CustomerInterfaceFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        AddressInterfaceFactory $dataAddressFactory,
        AccountManagementInterface $customerAccountManagement,
        EncryptorInterface $encryptorInterface,
        Region $region,
        CartManagementInterface $cartManagementInterface,
        QuoteFactory $quoteFactory,
        CartRepositoryInterface $cartRepository,
        Cart $cart,
        Registry $registry,
        Client $zendClient,
        QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
        $this->logger = $logger;
        $this->zendClient = $zendClient;
        $this->product = $product;
        $this->eavConfig = $eavConfig;
        $this->productRepository = $productRepository;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->productFactory = $productFactory;
        $this->directoryList = $directoryList;
        $this->optionsFactory = $optionsFactory;
        $this->file = $file;
        $this->attributeOption = $attributeOption;
        $this->attributeOptionLabel = $attributeOptionLabel;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->dataAddressFactory = $dataAddressFactory;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->encryptorInterface = $encryptorInterface;
        $this->region = $region;
        $this->quoteFactory = $quoteFactory;
        $this->cart = $cart;
        $this->cartRepository = $cartRepository;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->registry = $registry;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param $product
     * @param $customer
     * @param $order
     * @return false|string
     */
    public function getPost($product, $customer, $order)
    {
//        $this->logger->info('Start Api Modernrugs!!!!!!!!!');
        $response = ['success' => false];
        try {
            if (empty($product) || !isset($product['sku'])) {
                return json_encode(['success' => true, 'message' => 'Product can not empty!']);
            }

            if (empty($order)) {
                return json_encode(['success' => true, 'message' => 'Order can not empty']);
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
                        $eavSetup = $this->eavSetupFactory->create();
                        $paramAttribute = $this->getDetailAttribute($attrCode, $product['variations']);
                        $eavSetup->addAttribute(Product::ENTITY, $attrCode, $paramAttribute);
                        $attributeId = $eavSetup->getAttributeId(Product::ENTITY, $attrCode);
                        if ($attrCode == 'size_modernrugs' && count($product['variations']) > 0) {
                            $option = $this->attributeOption;
                            $attributeOptionLabel = $this->attributeOptionLabel;
                            $attributeOptionManagement = $this->attributeOptionManagement;

                            foreach ($product['variations'] as $key => $variation) {
                                if (isset($variation['size'])) {
                                    $option->setValue($variation['size']);
                                    $attributeOptionLabel->setStoreId(self::DEFAULT_STORE);
                                    $attributeOptionLabel->setLabel($variation['size']);
                                    $option->setLabel($variation['size']);
                                    $option->setStoreLabels([$attributeOptionLabel]);
                                    $option->setSortOrder(0);
                                    $option->setIsDefault(true);
                                    $attributeOptionManagement->add(Product::ENTITY, $attributeId, $option);
                                }
                            }
                        }
                    }
                    $this->eavConfig->clear();
                }
            }

            // create product configurable
            if (!$this->product->getIdBySku($product['sku'])) {
                // $this->logger->info('Product Exist');
                $this->createProductConfigurable($product);
            }

            // create simple product of configurable
            if (isset($product['variations'])) {
                foreach ($product['variations'] as $variation) {
                    if (!$this->product->getIdBySku($variation['sizeSku'])) {
                        $this->createProductSimple($product, $variation);
                    }
                }
            }

            // add option and product to configurable
            if ($this->product->getIdBySku($product['sku'])) {
                $productData = $this->productRepository->get($product['sku']);
                if ($productData->getTypeId() == 'configurable') {
                    $children = $productData->getTypeInstance()->getUsedProducts($productData);
                    $listChildrens = [];
                    $linkedProduct = [];
                    if (count($children) > 0) {
                        foreach ($children as $child) {
                            array_push($listChildrens, $child->getID());
                        }
                    }
                    if (isset($product['variations'])) {
                        foreach ($product['variations'] as $variation) {
                            $productId = $this->product->getIdBySku($variation['sizeSku']);
                            if ($productId && !in_array($productId, $listChildrens)) {
                                array_push($linkedProduct, $productId);
                            }
                        }
                    }
                    // add option to product configuable
                    $attributeId = $this->getAttributeIdByCode('size_modernrugs');
                    if (count($linkedProduct) > 0 && $attributeId) {
                        $configurableAttributesData = [
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

                        $product = $this->productRepository->get($product['sku']);
                        $configurableOptions = $this->optionsFactory->create($configurableAttributesData);
                        $product->getExtensionAttributes()->setConfigurableProductOptions($configurableOptions);
                        //add list product need associated
                        $product->getExtensionAttributes()->setConfigurableProductLinks($linkedProduct);
                        $this->productRepository->save($product);
                    }
                }
            }

            // add customer
            if (!empty($customer) || !empty($customer['email'])) {
                try {
                    $isEmailNotExists = $this->customerAccountManagement->isEmailAvailable($customer['email'], Self::DEFAULT_WEBSITE);

                    if ($isEmailNotExists) {
                        // Preparing data for new customer
                        $customerNew = $this->customerFactory->create();
                        $customerNew->setWebsiteId(self::DEFAULT_WEBSITE);
                        $customerNew->setEmail($customer['email']);
                        $customerNew->setFirstname($customer['firstName']);
                        $customerNew->setLastname($customer['lastName']);

                        // address of customer
                        $address = $this->dataAddressFactory->create();
                        $address->setFirstname($customer['firstName']);
                        $address->setLastname($customer['lastName']);
                        $address->setTelephone($customer['phone']);
                        $street[] = '1408 N 3rd st';
                        $address->setStreet($street);
                        $address->setCity($customer['city']);
                        $address->setCountryId($customer['country']);
                        $address->setPostcode($customer['zip']);
                        if ($customer['state'] && $customer['country']) {
                            $region = $this->region->loadByCode('CA', 'US');
                            $regionId = $region->getId();
                            $address->setRegionId($regionId);
                        }
                        $address->setIsDefaultShipping(1);
                        $address->setIsDefaultBilling(1);
                        $customerNew->setAddresses([$address]);

                        // Save data
                        $hashedPassword = $this->encryptorInterface->getHash($customer['email'], true);
                        $this->customerRepository->save($customerNew, $hashedPassword);
                    }
                } catch (Exception $exception) {
                }
            }

            // get quote and add product to quote
            //check and add customer
            if (empty($customer) || empty($customer['email'])) {
                $cartId = $this->cartManagementInterface->createEmptyCart(); //Create empty cart
                $quote = $this->cartRepository->get($cartId); // load empty cart quote
                $quote->setStoreId(self::DEFAULT_STORE);
                $quote->setCurrency();
                if (!empty($order)) {
                    foreach ($order as $data) {
                        if (isset($data['sku']) && $product['sku'] && isset($product['variations']) && isset($data['variation']) && $data['qty']) {
                            $productParent = $this->product->getIdBySku($product['sku']);
                            // get attribute id and option id
                            $optionId = null;
                            $attributeId = $this->eavSetupFactory->create()->getAttributeId(Product::ENTITY, self::DEFAULT_ATTRIBUTE);
                            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, self::DEFAULT_ATTRIBUTE);
                            if ($attribute->usesSource()) {
                                foreach ($product['variations'] as $variation) {
                                    if (isset($variation['option']) && $variation['option'] == $data['variation']) {
                                        $optionId = $attribute->getSource()->getOptionId($variation['size']);
                                    }
                                }
                            }
                            //check product has in quote
                            if ($optionId && $attributeId) {
                                var_dump("todooo add cart");
                                // add new product to cart
                                $cart = $this->cart->setQuote($quote);
                                $requestInfo = new \Magento\Framework\DataObject(
                                    [
                                        'product' => $productParent,
                                        'selected_configurable_option' => 1,
                                        'qty' => $data['qty'],
                                        'super_attribute' => [
                                            $attributeId => $optionId
                                        ],
                                    ]
                                );
                                $productModel = $this->productRepository->get($product['sku'], false, self::DEFAULT_STORE, true);
                                $quote->addProduct($productModel, $requestInfo);
                                $this->registry->register('modernrugs', 'user');
                                $quote->save();
                                $this->cartRepository->save($quote);
                                $this->registry->unregister('modernrugs');

                                $quoteIdMask = $this->quoteIdMaskFactory->create();
                                $quoteIdMask->setQuoteId($quote->getId())
                                    ->save();
                            }

                        }
                    }
                }
            } else {
                try {
                    $customer = $this->customerFactory->create()->setWebsiteId(self::DEFAULT_STORE)->loadByEmail($customer['email']);
                    $customerId = $customer->getId();
                    $quote = $this->quoteFactory->create()->loadByCustomer($customerId);
                    if (!empty($order)) {
                        foreach ($order as $data) {
                            if (isset($data['sku']) && $product['sku'] && isset($product['variations']) && isset($data['variation']) && $data['qty']) {
                                $productParent = $this->product->getIdBySku($product['sku']);
                                // get attribute id and option id
                                $optionId = null;
                                $attributeId = $this->eavSetupFactory->create()->getAttributeId(Product::ENTITY, self::DEFAULT_ATTRIBUTE);
                                $attribute = $this->eavConfig->getAttribute(Product::ENTITY, self::DEFAULT_ATTRIBUTE);
                                if ($attribute->usesSource()) {
                                    foreach ($product['variations'] as $variation) {
                                        if (isset($variation['option']) && $variation['option'] == $data['variation']) {
                                            $optionId = $attribute->getSource()->getOptionId($variation['size']);
                                        }
                                    }
                                }
                                //check product has in quote
                                if ($optionId && $attributeId) {
                                    var_dump("todooo add cart");
                                    // add new product to cart
                                    $cart = $this->cart->setQuote($quote);
                                    $requestInfo = new \Magento\Framework\DataObject(
                                        [
                                            'product' => $productParent,
                                            'selected_configurable_option' => 1,
                                            'qty' => $data['qty'],
                                            'super_attribute' => [
                                                $attributeId => $optionId
                                            ],
                                        ]
                                    );
                                    $productModel = $this->productRepository->get($product['sku'], false, self::DEFAULT_STORE, true);
                                    $quote->addProduct($productModel, $requestInfo);
                                    $this->registry->register('modernrugs', 'user');
                                    $quote->save();
                                    $this->cartRepository->save($quote);
                                    $this->registry->unregister('modernrugs');
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    var_dump('error');
                    var_dump($e->getMessage());
                }
            }

            $response = ['success' => true, 'message' => 'annnnnnn'];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            //            $this->logger->info($e->getMessage());
        }

        return json_encode($response);
    }

    /**
     * @param $urlImage
     * @return bool|string
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getUrlImage($urlImage)
    {
        try {
            /** @var string $tmpDir */
            $tmpDir = $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR . 'tmp';
            /** create folder if it is not exists */
            $this->file->checkAndCreateFolder($tmpDir);
            /** @var string $newUrlImage */
            $newUrlImage = $tmpDir . baseName($urlImage);
            /** read file from URL and copy it to the new destination */
            $result = $this->file->read($urlImage, $newUrlImage);
        } catch (\Magento\Framework\Exception\FileSystemException $e) {
            $this->logger->error($this->zendClient->getResponse()->getStatusCode());
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error($this->zendClient->getResponse()->getStatusCode());
        }

        return $result ? $newUrlImage : false;
    }

    /**
     * @param $attributeCode
     * @param $variations
     * @return array
     */
    public function getDetailAttribute($attributeCode, $variations)
    {
        return [
            'group' => 'General',
            'attribute_set' => 'Default',
            'type' => ($attributeCode == 'size_modernrugs') ? "int" : "varchar",
            'label' => $attributeCode,
            'input' => ($attributeCode == 'size_modernrugs') ? 'select' : "text",
            'required' => false,
            'is_required' => false,
            'system' => false,
            'backend' => '',
            'sort_order' => ($attributeCode == 'size_modernrugs') ? 50 : 100,
            "user_defined" => true,
            "frontend_input" => ($attributeCode == 'size_modernrugs') ? 'select' : "text",
            "options" => [],
            "backend_type" => ($attributeCode == 'size_modernrugs') ? "int" : "string",
            'global' => ($attributeCode == 'size_modernrugs') ? ScopedAttributeInterface::SCOPE_GLOBAL : ScopedAttributeInterface::SCOPE_STORE,
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
            "filterable" => true,
            "is_filterable_in_search" => true,
            'visible' => true,
            "is_visible" => true,
            'is_html_allowed_on_front' => ($attributeCode == 'size_modernrugs') ? true : false,
            'is_used_in_product_listing' => ($attributeCode == 'size_modernrugs') ? true : false,
            'used_in_product_listing' => ($attributeCode == 'size_modernrugs') ? true : false,
            'visible_on_front' => false,
            "is_searchable" => "1",
            "is_visible_in_advanced_search" => "1",
        ];
    }

    /**
     * @param $product
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createProductConfigurable($product)
    {
        try {
            $categoryId = ['4', '7', '14'];
            $productAdd = $this->productFactory->create();
            $productAdd->setSku($product['sku']);
            $productAdd->setName($product['name']);
            $productAdd->setAttributeSetId(4);
            $productAdd->setStatus(1);
            $productAdd->setVisibility(4);
            $productAdd->setTaxClassId(0);
            $productAdd->setTypeId('configurable');
//            $productAdd->setStoreId(0);
            $productAdd->setStoreId(self::DEFAULT_STORE);
            $productAdd->setWebsiteIds(array(self::DEFAULT_WEBSITE));
            $productAdd->setCategoryIds($categoryId);
            $productAdd->setDescription($product['description']);
            $productAdd->setCustomAttribute('material', $product['material']);
            $productAdd->setCustomAttribute('collection', $product['collection']);
            $productAdd->setCustomAttribute('vendor', $product['vendor']);
            $productAdd->setCustomAttribute('designer', $product['designer']);
            $productAdd->setCustomAttribute('made_in', $product['madeIn']);
            $urlImage = $this->getUrlImage($product['image']);
            if ($urlImage) {
                $imageType = [
                    "image",
                    "small_image",
                    "thumbnail",
                    "swatch_image",
                ];
                $productAdd->addImageToMediaGallery($urlImage, $imageType, true, false);
            }
            $productAdd->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => 10
                )
            );
            $productAdd->save();
        } catch (\Exception $e) {
            $this->logger->error($this->zendClient->getResponse()->getStatusCode());
        }
    }

    /**
     * @param $product
     * @param $variable
     * @return false|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createProductSimple($product, $variable)
    {
        $categoryId = ['4', '7', '14'];
        $sizeModernrugs = 0;
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, 'size_modernrugs');
        $options = $attribute->getSource()->getAllOptions();
        foreach ($options as $op) {
            if ($op['label'] == $variable['size']) {
                $sizeModernrugs = $op['value'];
            }
        }
        try {
            $productAdd = $this->productFactory->create();
            $productAdd->setSku($variable['sizeSku']);
            $productAdd->setName($product['name'] . $variable['size']);
            $productAdd->setAttributeSetId(4);
            $productAdd->setStatus(1);
            $productAdd->setVisibility(4);
            $productAdd->setTaxClassId(0);
            $productAdd->setPrice($variable['price']);
            $productAdd->setTypeId('simple');
            //            $productAdd->setStoreId(0);
            $productAdd->setStoreId(self::DEFAULT_STORE);
            $productAdd->setWebsiteIds(array(self::DEFAULT_WEBSITE));
            $productAdd->setCategoryIds($categoryId);
            $productAdd->setDescription($product['description']);
            $productAdd->setCustomAttribute('size_modernrugs', $sizeModernrugs);
            $productAdd->setCustomAttribute('gs1', $variable['GS1']);
            $productAdd->setCustomAttribute('search_size', $variable['searchSize']);
            $productAdd->setCustomAttribute('alias_size', $variable['aliasSize']);
            $productAdd->setCustomAttribute('search_size_floor', $variable['searchSizeFloor']);
            $productAdd->setCustomAttribute('shape', $variable['shape']);
            $productAdd->setCustomAttribute('old_price', $variable['oldPrice']);
            $productAdd->setCustomAttribute('sale_modernrugs', $variable['sale']);
            $productAdd->setCustomAttribute('width', $variable['width']);
            $productAdd->setCustomAttribute('length', $variable['length']);
            $productAdd->setCustomAttribute('height', $variable['height']);
            $productAdd->setCustomAttribute('shipping_length', $variable['shippingLength']);
            $productAdd->setCustomAttribute('shipping_width', $variable['shippingWidth']);
            $productAdd->setCustomAttribute('shipping_height', $variable['shippingHeight']);
            $productAdd->setCustomAttribute('shipping_weight', $variable['shippingWeight']);
            $productAdd->setCustomAttribute('shipping_type', $variable['shippingType']);
            $productAdd->setCustomAttribute('orig_map', $variable['origMap']);
            $productAdd->setCustomAttribute('wholesale', $variable['wholesale']);
            $productAdd->setCustomAttribute('msrp', $variable['MSRP']);
            $urlImage = $this->getUrlImage($product['image']);
            if ($urlImage) {
                $imageType = [
                    "image",
                    "small_image",
                    "thumbnail",
                    "swatch_image",
                ];
                $productAdd->addImageToMediaGallery($urlImage, $imageType, true, false);
            }
            $productAdd->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => 10
                )
            );
            $productAdd->save();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->logger->error($this->zendClient->getResponse()->getStatusCode());
        }
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

    /**
     * @param $attributeCode
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributeIdByCode($attributeCode)
    {
        $attr = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);

        return ($attr && $attr->getId()) ? $attr->getId() : false;
    }
}
