<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Model_Buy_Template_NewProduct_Source
{
    const ADDITIONAL_IMAGES_COUNT_MAX = 4;

    /* @var $listingProduct Ess_M2ePro_Model_Buy_Listing_Product */
    private $listingProduct = null;

    /* @var $category Ess_M2ePro_Model_Buy_Template_NewProduct */
    private $category = null;

    /* @var $coreTemplate Ess_M2ePro_Model_Buy_Template_NewProduct_Core */
    private $coreTemplate = null;

    /* @var $attributeTemplates Ess_M2ePro_Model_Buy_Template_NewProduct_Attribute[] */
    private $attributeTemplates = array();

    // ########################################

    public function __construct($args)
    {
        list($this->listingProduct,$this->category) = $args;

        $this->coreTemplate = $this->category->getCoreTemplate();
        $this->attributeTemplates = $this->category->getAttributesTemplate();
    }

    // ########################################

    public function getCoreData()
    {
        $msrp = $this->getPriceMsrp();

        return array(
            'seller_sku' => $this->getSellerSku(),
            'gtin' => $this->getGtin(),
            'isbn' => $this->getIsbn(),
            'asin' => $this->getAsin(),
            'mfg_name' => $this->getMfgName(),
            'mfg_part_number' => $this->getMfgPartNumber(),
            'product_set_id' => $this->getProductSetId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'main_image' => $this->getMainImage(),
            'additional_images' => $this->getAdditionalImages(),
            'keywords' => $this->getKeywords(),
            'features' => $this->getFeatures(),
            'weight' => $this->getWeight(),
            'listing_price' => $msrp,
            'msrp' => $msrp,
            'category_id' => $this->getCategoryId(),
        );
    }

    public function getAttributesData()
    {
        $attributes = array();

        foreach ($this->attributeTemplates as $attribute) {

            $src = $attribute->getAttributeSource();
            $value = '';

            switch ($src['mode']) {
                case Ess_M2ePro_Model_Buy_Template_NewProduct_Attribute::ATTRIBUTE_MODE_CUSTOM_VALUE:
                    $value = $src['custom_value'];
                    //$value = str_replace(',','^',$src['custom_value']);
                    break;

                case Ess_M2ePro_Model_Buy_Template_NewProduct_Attribute::ATTRIBUTE_MODE_CUSTOM_ATTRIBUTE:
                    $value = $this->listingProduct
                            ->getActualMagentoProduct()
                            ->getAttributeValue($src['custom_attribute']);

                    $value = str_replace(',','^',$value);
                    break;

                case Ess_M2ePro_Model_Buy_Template_NewProduct_Attribute::ATTRIBUTE_MODE_RECOMMENDED_VALUE:
                    $value = $src['recommended_value'];
                    is_array($value) && $value = implode('^',$value);
                    break;

                default:
                    $value = '';
                    break;
            }

            $attributes = array_merge($attributes,array($src['name'] => $value));
        }

        return $attributes;
    }

    // ########################################

    public function getCategoryId()
    {
        return (int)$this->category->getCategoryId();
    }

    public function getPriceMsrp()
    {
        return $this->listingProduct->getPrice();
    }

    public function getSellerSku()
    {
        $src = $this->coreTemplate->getSellerSkuSource();
        return $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
    }

    public function getGtin()
    {
        $src = $this->coreTemplate->getGtinSource();
        return $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
    }

    public function getIsbn()
    {
        $isbn = NULL;
        $src = $this->coreTemplate->getIsbnSource();

        if ($this->coreTemplate->isIsbnCustomAttribute()) {
            $isbn = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
        }

        return $isbn;
    }

    public function getAsin()
    {
        $asin = NULL;
        $src = $this->coreTemplate->getAsinSource();

        if ($this->coreTemplate->isAsinCustomAttribute()) {
            $asin = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
        }

        return $asin;
    }

    public function getMfgName()
    {
        $mfgName = NULL;
        $src = $this->coreTemplate->getMfgSource();

        if ($src['template'] != '') {
            $mfgName = Mage::getSingleton('M2ePro/Template_Description_Parser')->parseTemplate(
                $src['template'],
                $this->listingProduct->getActualMagentoProduct()->getProduct()
            );
        }

        return $mfgName;
    }

    public function getMfgPartNumber()
    {
        $src = $this->coreTemplate->getMfgPartNumberSource();

        if ($this->coreTemplate->isMfgPartNumberCustomValue()) {
            $mfgPartNumber = $src['custom_value'];
        } else {
            $mfgPartNumber = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
        }

        return $mfgPartNumber;
    }

    public function getProductSetId()
    {
        $productSetId = NULL;
        $src = $this->coreTemplate->getProductSetIdSource();

        if ($this->coreTemplate->isProductSetIdCustomValue()) {
            $productSetId = $src['custom_value'];
        } elseif ($this->coreTemplate->isProductSetIdCustomAttribute()) {
            $productSetId = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
        }

        return $productSetId;
    }

    public function getTitle()
    {
        $src = $this->coreTemplate->getTitleSource();

        switch ($src['mode']) {
            case Ess_M2ePro_Model_Buy_Template_NewProduct_Core::TITLE_MODE_PRODUCT_NAME:
                $title = $this->listingProduct->getActualMagentoProduct()->getName();
                break;

            case Ess_M2ePro_Model_Buy_Template_NewProduct_Core::TITLE_MODE_CUSTOM_TEMPLATE:
                $title = Mage::getSingleton('M2ePro/Template_Description_Parser')->parseTemplate(
                    $src['template'],
                    $this->listingProduct->getActualMagentoProduct()->getProduct()
                );
                break;

            default:
                $title = $this->listingProduct->getActualMagentoProduct()->getName();
                break;
        }

        return $title;
    }

    public function getDescription()
    {
        $src = $this->coreTemplate->getDescriptionSource();
        /* @var $templateProcessor Mage_Core_Model_Email_Template_Filter */
        $templateProcessor = Mage::getModel('Core/Email_Template_Filter');

        switch ($src['mode']) {
            case Ess_M2ePro_Model_Buy_Template_NewProduct_Core::DESCRIPTION_MODE_PRODUCT_FULL:
                $description = $this->listingProduct->getActualMagentoProduct()->getProduct()->getDescription();
                $description = $templateProcessor->filter($description);
                break;

            case Ess_M2ePro_Model_Buy_Template_NewProduct_Core::DESCRIPTION_MODE_PRODUCT_SHORT:
                $description = $this->listingProduct->getActualMagentoProduct()->getProduct()->getShortDescription();
                $description = $templateProcessor->filter($description);
                break;

            case Ess_M2ePro_Model_Buy_Template_NewProduct_Core::DESCRIPTION_MODE_CUSTOM_TEMPLATE:
                $description = Mage::getSingleton('M2ePro/Template_Description_Parser')->parseTemplate(
                    $src['template'],
                    $this->listingProduct->getActualMagentoProduct()->getProduct()
                );
                break;

            default:
                return;
                break;
        }

        $description = str_replace(array('<![CDATA[', ']]>'), '', $description);
        $description = preg_replace('/[^(\x20-\x7F)]*/','', $description);

        return strip_tags($description);
    }

    public function getMainImage()
    {
        $imageLink = NULL;

        if ($this->coreTemplate->isMainImageBroductBase()) {
            $imageLink = $this->listingProduct->getActualMagentoProduct()->getImageLink('image');
        }

        if ($this->coreTemplate->isMainImageAttribute()) {
            $src = $this->coreTemplate->getMainImageSource();
            $imageLink = $this->listingProduct->getActualMagentoProduct()->getImageLink($src['attribute']);
        }

        return $imageLink;
    }

    public function getAdditionalImages()
    {
        $limitImages = self::ADDITIONAL_IMAGES_COUNT_MAX;
        $galleryImages = array();
        $src = $this->coreTemplate->getAdditionalImageSource();

        if ($this->coreTemplate->isAdditionalImageNone()) {
            return;
        }

        if ($this->coreTemplate->isAdditionalImageProduct()) {
            $limitImages = (int)$src['limit'];
            $galleryImages = $this->listingProduct->getActualMagentoProduct()->getGalleryImagesLinks((int)$src['limit']);
        }

        if ($this->coreTemplate->isAdditionalImageCustomAttribute()) {
            $galleryImagesTemp = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['attribute']);
            $galleryImagesTemp = (array)explode(',', $galleryImagesTemp);

            foreach ($galleryImagesTemp as $tempImageLink) {
                $tempImageLink = trim($tempImageLink);
                if (!empty($tempImageLink)) {
                    $galleryImages[] = $tempImageLink;
                }
            }
        }

        $mainImageLink = $this->listingProduct->getActualMagentoProduct()->getImageLink('image');
        $isMainImageInArray = array_search($mainImageLink,$galleryImages);
        if ($isMainImageInArray !== false) {
            unset($galleryImages[$isMainImageInArray]);
        }

        $galleryImages = array_unique($galleryImages);
        if (count($galleryImages) <= 0) {
            return;
        }

        $galleryImages = array_slice($galleryImages,0,$limitImages);

        return implode('|',$galleryImages);
    }

    public function getFeatures()
    {
        $src = $this->coreTemplate->getFeaturesSource();

        if ($this->coreTemplate->isFeaturesNone()) {
            return;
        } else {
            foreach ($src['template'] as $feature) {
                $features[] = strip_tags(
                    Mage::getSingleton('M2ePro/Template_Description_Parser')->parseTemplate(
                        $feature,
                        $this->listingProduct->getActualMagentoProduct()->getProduct()
                    )
                );
            }
        }

        $features = implode('|',$features);
        $features = preg_replace('/[^(\x20-\x7F)]*/','', $features);

        return $features;
    }

    public function getKeywords()
    {
        $src = $this->coreTemplate->getKeywordsSource();

        if ($this->coreTemplate->isKeywordsNone()) {
            return;
        } elseif ($this->coreTemplate->isKeywordsCustomValue()) {
            $keywords = $src['custom_value'];
        } elseif ($this->coreTemplate->isKeywordsCustomAttribute()) {
            $keywords = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
        }

        $keywords = preg_replace('/(?<=,)\s/','',$keywords);
        $keywords = strip_tags(str_replace(',','|',$keywords));
        $keywords = preg_replace('/[^(\x20-\x7F)]*/','',$keywords);

        return $keywords;
    }

    public function getWeight()
    {
        $weight = NULL;
        $src = $this->coreTemplate->getWeightSource();

        if ($this->coreTemplate->isWeightCustomValue()) {
            $weight = $src['custom_value'];
        } else {
            $weight = $this->listingProduct->getActualMagentoProduct()->getAttributeValue($src['custom_attribute']);
        }

        $weight = str_replace(',','.',$weight);
        $weight = round((float)$weight,2);

        return $weight;
    }

    // ########################################
}