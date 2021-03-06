<?php
/**
 * Bluem Integration - Magento2 Module
 * (C) Bluem 2021
 *
 * @category Module
 * @author   Daan Rijpkema <d.rijpkema@bluem.nl>
 */

namespace Bluem\Integration\Plugin;

use \Magento\Customer\Model\Session;
use \Magento\Framework\App\ObjectManager;
use stdClass;
use Bluem\Integration\Helper\Data as DataHelper;
use Bluem\Integration\Helper\Bluem as BluemHelper;
use \Magento\Catalog\Model\Product ;
use \Magento\Catalog\Model\ProductRepository;
use Throwable;

class ProductFilter
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    public function __construct(
        Session $customerSession,
        DataHelper $dataHelper,
        BluemHelper $bluemHelper,
        ProductRepository $productRepository
    ) {
        $this->_customerSession = $customerSession;

        $this->productRepository = $productRepository;
        $this->_dataHelper = $dataHelper;
        $this->_bluemHelper = $bluemHelper;
    }

    public function afterIsSaleable(\Magento\Catalog\Model\Product $product)
    {
        $filter_debug = false;

        $identity_block_mode = $this->_dataHelper
            ->getIdentityConfig('identity_block_mode');
        
        if ($filter_debug) {
            echo "Initiating product filter";
            var_dump($identity_block_mode);
        }
        $check_necessary = false;
        if (is_null($identity_block_mode)
            || (!is_null($identity_block_mode)
            && $identity_block_mode =="all_products")
        ) {
            $check_necessary = true;
        }
        if (!is_null($identity_block_mode)
            && $identity_block_mode =="product_attribute"
        ) {
            $identity_product_agecheck_attribute = $this->_dataHelper
                        ->getIdentityConfig('identity_product_agecheck_attribute');
            // fallback
            if (is_null($identity_product_agecheck_attribute)) {
                $identity_product_agecheck_attribute = "agecheck_required";
            }
            try {
                $attr = $product->getData($identity_product_agecheck_attribute);
                // var_dump($attr);
                        // die();
            } catch (Throwable $th) {
                if ($filter_debug) {
                    echo "ERROR in productfilter";
                }
                // error in retrieving the data? then just allow the checkout for now
                $check_necessary = false;
            }
            if (is_null($attr)) {
                if ($filter_debug) {
                    echo "Emtpy in productfilter";
                }
                // attribute is not set? then just allow the checkout for now
                $check_necessary = false;
            } else {
                if ($attr == "1"
                    || $attr == "true"
                    || $attr == true
                ) {
                    $check_necessary = true;
                }
            }
            // echo "Success in productfilter";
        }
        
        if ($filter_debug) {
            echo "Check necessary? " . ($check_necessary?"Yes":"No");
        }
        // no check? then just say s'all good
        if ($check_necessary == false) {
            return true;
        }


        if ($check_necessary) {
            $identity_valid = $this->_dataHelper->getIdentityValid();
            if ($filter_debug) {
                echo "Identity valid? " . ($identity_valid->valid?"Yes":"No");
                var_dump($identity_valid);
            }
            return $identity_valid->valid;
        } else {
            return true;
        }
    }
}

// https://magento.stackexchange.com/questions/165218/disable-entire-cart-functionality-checkout-in-magento-2
// https://magento.stackex  change.com/questions/304604/how-to-hide-add-to-cart-button-for-particular-products-on-all-page-in-magento-2
// https://www.mageplaza.com/magento-2-module-development/magento-2-add-product-attribute-programmatically.html
// https://devdocs.magento.com/videos/fundamentals/add-new-product-attribute/
