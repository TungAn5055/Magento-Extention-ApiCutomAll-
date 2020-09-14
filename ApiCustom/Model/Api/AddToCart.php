<?php

namespace Modernrugs\ApiCustom\Model\Api;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Modernrugs\Log\Helper\LoggerContainer;
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
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;

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
    protected $zendClient;
    protected $quoteIdMaskFactory;

    const DEFAULT_WEBSITE = 1;
    const DEFAULT_STORE = 1;
    const DEFAULT_ATTRIBUTE = 'size_modernrugs';
    const DEFAULT_URL_BASE = 'https://www.modernrugs.com';
    const DEFAULT_CATEGORY_ID = ['4', '7', '14'];

    /**
     * AddToCart constructor.
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
     * @param LoggerContainer $logger
     * @param TokenModelFactory $tokenModelFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
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
        LoggerContainer $logger,
        TokenModelFactory $tokenModelFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
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
        $this->tokenModelFactory = $tokenModelFactory;
        $this->logger = $logger->getLoggerAndSwitchTo(LoggerContainer::CONTEXT_NAME_LOG_CONTAINER);
    }

    /**
     * @param $product
     * @param $customer
     * @param $order
     * @return false|string
     */
    public function getPost($product, $customer, $order, $maskCart = null, $token = null)
    {
        $this->logger->info('Start ::: Add To Cart Modernrugs!');
        try {
            if (empty($product) || !isset($product['sku'])) {
                $this->logger->error('End ::: Product empty!');
                return json_encode(['status' => false, 'message' => 'Product can not empty!']);
            }

            if (empty($order)) {
                $this->logger->error('End ::: Order empty!');
                return json_encode(['status' => false, 'message' => 'Order can not empty']);
            }

            // check and add attribute
            if (isset($product['variations']) && isset($product['variations'][0]) && array_keys($product['variations'][0])) {
                $this->createAttribute($product);
            }

            // create product configurable
            if (!$this->product->getIdBySku($product['sku'])) {
                $this->createProductConfigurable($product);
            }

            // create simple product of configurable
            if (isset($product['variations'])) {
                foreach ($product['variations'] as $variation) {
                    if (!$this->product->getIdBySku($variation['sizeSku'])) {
                        $this->createProductSimple($product, $variation);
                    }
                }
                $this->eavConfig->clear();
            }

            // add option and product to configurable
            if ($this->product->getIdBySku($product['sku'])) {
                try {
                    $productData = $this->productRepository->get($product['sku']);
                    if ($productData->getTypeId() == 'configurable') {
                        $this->logger->info("Product configurable: " . $product['sku']);
                        $children = $productData->getTypeInstance()->getUsedProducts($productData);
                        $listChildren = [];
                        $linkedProduct = [];
                        if (count($children) > 0) {
                            foreach ($children as $child) {
                                array_push($listChildren, $child->getID());
                            }
                        }
                        if (isset($product['variations'])) {
                            foreach ($product['variations'] as $variation) {
                                $productId = $this->product->getIdBySku($variation['sizeSku']);
                                if ($productId && !in_array($productId, $listChildren)) {
                                    array_push($linkedProduct, $productId);
                                }
                            }
                        }
                        // add option to product configurable
                        $attributeId = $this->getAttributeIdByCode(self::DEFAULT_ATTRIBUTE);
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
                            $this->logger->info("Add simple to configurable: " . $linkedProduct[0]);
                            $productNew = $this->productRepository->get($product['sku']);
                            $configurableOptions = $this->optionsFactory->create($configurableAttributesData);
                            $productNew->getExtensionAttributes()->setConfigurableProductOptions($configurableOptions);
                            //add list product need associated
                            $productNew->getExtensionAttributes()->setConfigurableProductLinks($linkedProduct);
                            $this->productRepository->save($productNew);
                        }
                    }
                } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
                    $this->logger->error("Add option and product to configurable error: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\InputException $e) {
                    $this->logger->error("Add option and product to configurable error1: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->logger->error("Add option and product to configurable error2: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->logger->error("Add option and product to configurable error3: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\StateException $e) {
                    $this->logger->error("Add option and product to configurable error4: " . $e->getMessage());
                } catch (\Exception $e) {
                    $this->logger->error("Add option and product to configurable error5: " . $e->getMessage());
                }
            }

            // add customer
            if (!empty($customer) || !empty($customer['email'])) {
                $this->createCustomer($customer);
            }

            // get quote and add product to quote
            // check and add customer
            if (empty($customer) || empty($customer['email'])) {
                try {
                    $this->logger->info("Create empty cart or add maskCart: " . $maskCart);
                    if ($maskCart != null) {
                        $quoteMaskData = $this->quoteIdMaskFactory->create()->load($maskCart, 'masked_id');
                        $cartId = $quoteMaskData->getQuoteId();
                    } else {
                        $cartId = $this->cartManagementInterface->createEmptyCart(); //Create empty cart
                    }

                    $quote = $this->cartRepository->get($cartId); // load empty cart quote
                    $quote->setStoreId(self::DEFAULT_STORE);
                    $quote->setCurrency();
                    if (!empty($order)) {
                        $this->addProductToQuote($quote, $product, $order);
                    }
                    if ($maskCart == null) {
                        $quoteMaskData = $this->quoteIdMaskFactory->create()->load($cartId, 'quote_id');
                        $maskCart = $quoteMaskData->getMaskedId();
                    }
                } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
                    $this->logger->error("Add to cart not customer error: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->logger->error("Add to cart not customer error: " . $e->getMessage());
                } catch (\Exception $e) {
                    $this->logger->error("Add to cart not customer error " . $e->getMessage());
                }
            } else {
                try {
                    $customer = $this->customerRepository->get($customer['email'], self::DEFAULT_WEBSITE);
                    $customerId = $customer->getId();
                    $token = $this->tokenModelFactory->create()->createCustomerToken($customerId)->getToken();
                    $quoteId = $this->quoteFactory->create()->getCollection()
                        ->addFieldToSelect('entity_id')
                        ->addFieldToFilter('customer_id', $customerId)
                        ->addFieldToFilter('customer_id', $customerId);
                    if (!empty($order) && $customerId) {
                        $this->logger->info("Add product exist or new to quote: " . $customerId . ' - count quote id ' . count($quoteId));
                        if (count($quoteId) > 0) {
                            $quote = $this->quoteFactory->create()->loadByCustomer($customerId);
                            $this->addProductToQuote($quote, $product, $order);
                        } else {
                            $cartId = $this->cartManagementInterface->createEmptyCart(); //Create empty cart
                            $quoteNew = $this->cartRepository->get($cartId); // load empty cart quote
                            $quoteNew->setStoreId(self::DEFAULT_STORE);
                            $quoteNew->setCurrency();
                            $quoteNew->assignCustomer($customer);
                            $this->addProductToQuote($quoteNew, $product, $order);
                        }
                    }
                } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
                    $this->logger->error("Add to cart customer error: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->logger->error("Add to cart customer error1: " . $e->getMessage());
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->logger->error("Add to cart customer error2: " . $e->getMessage());
                } catch (\Exception $e) {
                    $this->logger->error("Add to cart customer error3: " . $e->getMessage());
                }
            }
            $this->logger->info("token : $token");
            $this->logger->info("mask_quote : $maskCart");

            $response = ['status' => true, 'message' => 'Add to cart done!', 'content' => ['token' => $token, 'maskCart' => $maskCart]];
        } catch (\Exception $e) {
            $this->logger->info("Error not found :" . $e->getMessage());
            $response = ['status' => false, 'message' => ' Error not found'];
        }

        $this->logger->info('End ::: Add To Cart Modernrugs!');
        return json_encode($response);
    }

    /**
     * @param $product
     */
    public function createAttribute($product)
    {
        $this->logger->info("Start createAttribute: ");
        try {
            $listAttributes = [
                'sizeSku' => 'size_sku',
                'size' => self::DEFAULT_ATTRIBUTE,
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
                'height' => 'height',
                'length' => 'length',
                'madeIn' => 'made_in',
                'material' => 'material',
            ];
            // create attribute
            foreach ($listAttributes as $attrCode) {
                if ($this->isProductAttributeExists($attrCode) == false) {
                    $this->logger->info("attribute code: " . $attrCode);
                    $eavSetup = $this->eavSetupFactory->create();
                    $paramAttribute = $this->getDetailAttribute($attrCode, $product['variations']);
                    $eavSetup->addAttribute(Product::ENTITY, $attrCode, $paramAttribute);
                }
                if ($attrCode == self::DEFAULT_ATTRIBUTE && count($product['variations']) > 0 && $this->isProductAttributeExists($attrCode) != false) {

                    $option = $this->attributeOption;
                    $attributeOptionLabel = $this->attributeOptionLabel;
                    $attributeOptionManagement = $this->attributeOptionManagement;
                    $eavSetup = $this->eavSetupFactory->create();
                    $attributeId = $eavSetup->getAttributeId(Product::ENTITY, $attrCode);
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
                $this->eavConfig->clear();
            }
        } catch (\Magento\Framework\Exception\InputException $e) {
            $this->logger->error("Add attribute error: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error("Add attribute error1: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\StateException $e) {
            $this->logger->error("Add attribute error2: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Add attribute error3: " . $e->getMessage());
        }
    }

    /**
     * @param $urlImage
     * @return false|string
     */
    public function getUrlImage($urlImage)
    {
        try {
            $linkImage = (strpos($urlImage, self::DEFAULT_URL_BASE) !== false) ? $urlImage : self::DEFAULT_URL_BASE . $urlImage;
            /** @var string $tmpDir */
            $tmpDir = $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR . 'tmp';
            /** create folder if it is not exists */
            $this->file->checkAndCreateFolder($tmpDir);
            /** @var string $newUrlImage */
            $newUrlImage = $tmpDir . baseName($linkImage);
            /** read file from URL and copy it to the new destination */
            $result = $this->file->read($linkImage, $newUrlImage);
        } catch (\Magento\Framework\Exception\FileSystemException $e) {
            $this->logger->error("Add image error: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error("Add image error1: " . $e->getMessage());
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
            'type' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? "int" : "varchar",
            'label' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? "Size" : $attributeCode,
            'input' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? 'select' : "text",
            'required' => false,
            'is_required' => false,
            'system' => false,
            'backend' => '',
            'sort_order' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? 50 : 100,
            "user_defined" => true,
            "frontend_input" => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? 'select' : "text",
            "options" => [],
            "backend_type" => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? "int" : "string",
            'global' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? ScopedAttributeInterface::SCOPE_GLOBAL : ScopedAttributeInterface::SCOPE_STORE,
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
            "filterable" => true,
            "is_filterable_in_search" => true,
            'visible' => true,
            "is_visible" => true,
            'is_html_allowed_on_front' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? true : false,
            'is_used_in_product_listing' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? true : false,
            'used_in_product_listing' => ($attributeCode == self::DEFAULT_ATTRIBUTE) ? true : false,
            'visible_on_front' => false,
            "is_searchable" => "1",
            "is_visible_in_advanced_search" => "1",
        ];
    }

    /**
     * @param $product
     */
    public function createProductConfigurable($product)
    {
        $this->logger->info("Start createProductConfigurable:" . $product['sku']);
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
            $productAdd->setStoreId(self::DEFAULT_STORE);
            $productAdd->setWebsiteIds(array(self::DEFAULT_WEBSITE));
            $productAdd->setCategoryIds($categoryId);
            $productAdd->setDescription($product['description']);
            $urlImage = $this->getUrlImage($product['picture']);
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
        } catch (\Magento\Framework\Exception\FileSystemException $e) {
            $this->logger->error("Create Product Configurable error: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error("Create Product Configurable error1: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("create Product Configurable error2: " . $e->getMessage());
        }
    }

    /**
     * @param $product
     * @param $variable
     */
    public function createProductSimple($product, $variable)
    {
        $this->logger->info("Start createProductSimple:" . $variable['sizeSku']);
        try {
            $categoryId = self::DEFAULT_CATEGORY_ID;
            $sizeModernrugs = 0;
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, self::DEFAULT_ATTRIBUTE);
            $options = $attribute->getSource()->getAllOptions();
            foreach ($options as $op) {
                if ($op['label'] == $variable['size']) {
                    $sizeModernrugs = $op['value'];
                }
            }

            $productAdd = $this->productFactory->create();
            $productAdd->setSku($variable['sizeSku']);
            $productAdd->setName($product['name'] . ' ' . $variable['size']);
            $productAdd->setAttributeSetId(4);
            $productAdd->setStatus(1);
            $productAdd->setVisibility(4);
            $productAdd->setTaxClassId(0);
            $productAdd->setPrice($variable['price']);
            $productAdd->setTypeId('simple');
            $productAdd->setStoreId(self::DEFAULT_STORE);
            $productAdd->setWebsiteIds(array(self::DEFAULT_WEBSITE));
            $productAdd->setCategoryIds($categoryId);
            $productAdd->setDescription($product['description']);
            $productAdd->setCustomAttribute(self::DEFAULT_ATTRIBUTE, $sizeModernrugs);
            $productAdd->setCustomAttribute('search_size', $variable['searchSize']);
            $productAdd->setCustomAttribute('alias_size', $variable['aliasSize']);
            $productAdd->setCustomAttribute('search_size_floor', $variable['searchSizeFloor']);
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
            $urlImage = $this->getUrlImage($product['picture']);
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
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error("Create Product Simple error: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\FileSystemException $e) {
            $this->logger->error("Create Product Simple error1: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Create Product Simple error2: " . $e->getMessage());
        }
    }

    /**
     * @param $customer
     */
    public function createCustomer($customer)
    {
        try {
            $this->logger->info("Create Customer: " . $customer['email']);
            $isEmailNotExists = $this->customerAccountManagement->isEmailAvailable($customer['email'], Self::DEFAULT_WEBSITE);

            if ($isEmailNotExists) {
                // Preparing data for new customer
                $customerNew = $this->customerFactory->create();
                $customerNew->setWebsiteId(self::DEFAULT_WEBSITE);
                $customerNew->setEmail($customer['email']);
                $customerNew->setFirstname(($customer['firstName']) ? $customer['firstName'] : '');
                $customerNew->setLastname(($customer['lastName']) ? $customer['lastName'] : '');
                if (isset($customer['country']) && isset($customer['zip'])
                    && isset($customer['state']) && isset($customer['city'])
                    && isset($customer['street'])) {
                    // address of customer
                    $address = $this->dataAddressFactory->create();
                    $address->setFirstname(($customer['firstName']) ? $customer['firstName'] : '');
                    $address->setLastname(($customer['lastName']) ? $customer['lastName'] : '');
                    $address->setTelephone($customer['phone']);
                    $street[] = $customer['street'];
                    $address->setStreet($street);
                    $address->setCity($customer['city']);
                    $address->setCountryId($customer['country']);
                    $address->setPostcode($customer['zip']);
                    if ($customer['state'] && $customer['country']) {
                        $region = $this->region->loadByCode($customer['state'], $customer['country']);
                        $regionId = $region->getId();
                        $address->setRegionId($regionId);
                    }
                    $address->setIsDefaultShipping(1);
                    $address->setIsDefaultBilling(1);
                    $customerNew->setAddresses([$address]);
                }
                // Save data
                $hashedPassword = $this->encryptorInterface->getHash($customer['email'], true);
                $this->customerRepository->save($customerNew, $hashedPassword);
            }
        } catch (\Magento\Framework\Exception\InputException $e) {
            $this->logger->error("Create customer error: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error("Create customer error1: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\State\InputMismatchException $e) {
            $this->logger->error("Create customer error2: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Create customer error3: " . $e->getMessage());
        }
    }

    /**
     * @param $quote
     * @param $product
     * @param $order
     */
    public function addProductToQuote($quote, $product, $order)
    {
        try {
            foreach ($order as $data) {
                if (isset($data['sku']) && isset($product['sku']) && isset($product['variations']) && isset($data['variation']) && isset($data['qty'])) {
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
                    $this->logger->info("Add Product To Quote: optionId - attributeId" . $optionId . '-' . $attributeId);
                    //check product has in quote
                    if ($optionId && $attributeId) {
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
                        $this->logger->info("Add Product To Quote: skuparent-qty-quoteId" . $productParent . '-' . $data['qty'] . '-' . $quote->getId());
                        $this->registry->register('modernrugs', 'user');
                        $quote->save();
                        $this->cartRepository->save($quote);
                        $this->registry->unregister('modernrugs');

                        $quoteIdMask = $this->quoteIdMaskFactory->create();
                        $quoteIdMask->setQuoteId($quote->getId())->save();
                    }
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->error("Add Product To Quote error: " . $e->getMessage());
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->error("Add Product To Quote error1: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error("Add Product To Quote error2: " . $e->getMessage());
        }
    }

    /**
     * Returns true if attribute exists and false if it doesn't exist
     *
     * @param string $attributeCode
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
     * @return false|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributeIdByCode($attributeCode)
    {
        $attr = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);

        return ($attr && $attr->getId()) ? $attr->getId() : false;
    }
}
