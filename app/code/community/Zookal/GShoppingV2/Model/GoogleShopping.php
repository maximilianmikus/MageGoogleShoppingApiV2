<?php
/**
 * @copyright   Copyright (c) 2015 BlueVisionTec UG (haftungsbeschränkt) (http://www.bluevisiontec.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Google Shopping connector
 *
 */
class Zookal_GShoppingV2_Model_GoogleShopping extends Varien_Object
{

    const APPNAME = 'Magento GoogleShopping V2';

    /**
     * @var Google_Client
     */
    protected $_client = null;

    /**
     * @var Google_Service_ShoppingContent
     */
    protected $_shoppingService = null;

    /**
     * Google Content Config
     *
     * @return Zookal_GShoppingV2_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('gshoppingv2/config');
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    private function _getAccessToken($storeId)
    {
        $clientId     = $this->getConfig()->getConfigData('client_id', $storeId);
        $accessTokens = Mage::getSingleton('admin/session')->getGoogleOAuth2Token();
        return isset($accessTokens[$clientId]) && false === empty($accessTokens[$clientId])
            ? $accessTokens[$clientId]
            : false;
    }

    /**
     * Return Google Content Client Instance
     * @todo remove header/exit and implement exception
     * @param $storeId
     *
     * @return bool|Google_Client
     */
    public function getClient($storeId)
    {

        if (isset($this->_client)) {
            if ($this->_client->isAccessTokenExpired()) {
                header('Location: ' . Mage::getUrl("adminhtml/gShoppingV2_oauth/auth", ['store_id' => $storeId]));
                exit;
            }
            return $this->_client;
        }
        $clientId     = $this->getConfig()->getConfigData('client_id', $storeId);
        $clientSecret = $this->getConfig()->getClientSecret($storeId);
        $accessToken  = $this->_getAccessToken($storeId);

        if (!$clientId || !$clientSecret) {
            Mage::getSingleton('adminhtml/session')->addError("Please specify Google Content API access data for this store!");
            return false;
        }

        if (!isset($accessToken) || empty($accessToken)) {
            header('Location: ' . Mage::getUrl("adminhtml/gShoppingV2_oauth/auth", ['store_id' => $storeId]));
            exit;
        }

        $client = new Google_Client();
        $client->setApplicationName(self::APPNAME);
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setScopes('https://www.googleapis.com/auth/content');
        $client->setAccessToken($accessToken);

        if ($client->isAccessTokenExpired()) {
            header('Location: ' . Mage::getUrl("adminhtml/gShoppingV2_oauth/auth", ['store_id' => $storeId]));
            exit;
        }

        if ($this->getConfig()->getIsDebug($storeId)) {
            $client->setLogger(Mage::getModel('gshoppingv2/logger', $client)->setStoreID($storeId));
        }

        $this->_client = $client;

        return $this->_client;
    }

    /**
     * @param null|int $storeId
     *
     * @return Google_Service_ShoppingContent
     */
    public function getShoppingService($storeId = null)
    {
        if (null !== $this->_shoppingService) {
            return $this->_shoppingService;
        }

        $this->_shoppingService = new Google_Service_ShoppingContent($this->getClient($storeId));
        return $this->_shoppingService;
    }

    /**
     * @param null|int $storeId
     *
     * @return Google_Service_ShoppingContent_ProductsListResponse
     */
    public function listProducts($storeId = null)
    {
        $merchantId = $this->getConfig()->getConfigData('merchant_id', $storeId);
        return $this->getShoppingService($storeId)->products->listProducts($merchantId);
    }

    /**
     * @param  string  $productId
     * @param null|int $storeId
     *
     * @return Google_Service_ShoppingContent_Product
     */
    public function getProduct($productId, $storeId = null)
    {
        $merchantId = $this->getConfig()->getConfigData('account_id', $storeId);
        return $this->getShoppingService($storeId)->products->get($merchantId, $productId);
    }

    /**
     * @param string   $productId
     * @param null|int $storeId
     *
     * @return Google_Http_Request
     */
    public function deleteProduct($productId, $storeId = null)
    {
        $merchantId = $this->getConfig()->getConfigData('account_id', $storeId);
        $result     = $this->getShoppingService($storeId)->products->delete($merchantId, $productId);
        return $result;
    }

    /**
     * @param Google_Service_ShoppingContent_Product $product
     * @param null|int                               $storeId
     *
     * @return Google_Service_ShoppingContent_Product
     */
    public function insertProduct(Google_Service_ShoppingContent_Product $product, $storeId = null)
    {
        $merchantId = $this->getConfig()->getConfigData('account_id', $storeId);
        $product->setChannel("online");
        $expDate = date("Y-m-d", (time() + 30 * 24 * 60 * 60));//product expires in 30 days
        $product->setExpirationDate($expDate);
        $result = $this->getShoppingService($storeId)->products->insert($merchantId, $product);
        return $result;
    }

    /**
     * @param Google_Service_ShoppingContent_Product $product
     * @param null|int                               $storeId
     *
     * @return Google_Service_ShoppingContent_Product
     */
    public function updateProduct(Google_Service_ShoppingContent_Product $product, $storeId = null)
    {
        return $this->insertProduct($product, $storeId);
    }
}
