<?php

/**
 * This file contains QUI\ERP\Shipping\Methods\Standard\ShippingType
 */

namespace QUI\ERP\Shipping\Methods\Standard;

use QUI;
use QUI\ERP\Areas\Utils as AreaUtils;
use QUI\ERP\Shipping\Debug;

/**
 * Class ShippingType
 * - This class is a placeholder / helper class for the standard shipping
 *
 * @package QUI\ERP\Shipping\Methods\Free\ShippingType
 */
class ShippingType extends QUI\ERP\Shipping\Api\AbstractShippingType
{
    /**
     * @param null $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/shipping', 'shipping.standard.title');
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return QUI\ERP\Shipping\Shipping::getInstance()->getHost().
               URL_OPT_DIR
               .'quiqqer/shipping/bin/images/shipping/default.png';
    }

    /**
     * @param QUI\ERP\Order\OrderInterface $Order
     * @param QUI\ERP\Shipping\Types\ShippingEntry $ShippingEntry
     * @return bool
     */
    public function canUsedInOrder(
        QUI\ERP\Order\OrderInterface $Order,
        QUI\ERP\Shipping\Types\ShippingEntry $ShippingEntry
    ) {
        if ($ShippingEntry->isActive() === false) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: is not active");

            return false;
        }

        if (!$ShippingEntry->isValid()) {
            return false;
        }


        // assignment
        $articles   = $ShippingEntry->getAttribute('articles');
        $categories = $ShippingEntry->getAttribute('categories');

        // if articles and categories are empty, its allowed
        if (empty($articles) && empty($categories)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: no products or categories [ok]");

            return true;
        }

        $ArticleList   = $Order->getArticles();
        $orderArticles = $ArticleList->getArticles();

        foreach ($orderArticles as $Article) {
            try {
                $productId = $Article->getId();

                if (\in_array($productId, $articles)) {
                    Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: product {$productId} is in allowed list [ok]");

                    return true;
                }

                if (\is_array($categories)) {
                    $Product           = QUI\ERP\Products\Handler\Products::getProduct($productId);
                    $articleCategories = $Product->getCategories();

                    foreach ($articleCategories as $categoryId) {
                        if (\in_array($categoryId, $categories)) {
                            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: category {$categoryId} is in allowed list [ok]");

                            return true;
                        }
                    }
                }
            } catch (QUI\Exception $Exception) {
                return false;
            }
        }

        Debug::addLog("{$this->getTitle()} :: no products found in this order which are fit");

        return false;
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\ERP\Shipping\Api\ShippingInterface $ShippingEntry
     * @return bool
     */
    public function canUsedBy(
        QUI\Interfaces\Users\User $User,
        QUI\ERP\Shipping\Api\ShippingInterface $ShippingEntry
    ) {
        if ($ShippingEntry->isActive() === false) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: is not active");

            return false;
        }

        // assignment
        $userGroupValue = $ShippingEntry->getAttribute('user_groups');
        $areasValue     = $ShippingEntry->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: users + areas are empty [OK]");

            return true;
        }

        $areasValue = \explode(',', \trim($areasValue, ','));

        // not in area
        if (!empty($areasValue) && !AreaUtils::isUserInAreas($User, $areasValue)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: User is not in areas");

            return false;
        }

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $ShippingEntry->getAttribute('user_groups')
        );

        $discountUsers  = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        if (empty($discountUsers) && empty($discountGroups)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: no discounts [OK]");

            return true;
        }

        // user checking
        foreach ($discountUsers as $uid) {
            if ($User->getId() == $uid) {
                Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: user found {$uid} [OK]");

                return true;
            }
        }

        // group checking
        $groupsOfUser = $User->getGroups();

        /* @var $Group QUI\Groups\Group */
        foreach ($discountGroups as $gid) {
            foreach ($groupsOfUser as $Group) {
                if ($Group->getId() == $gid) {
                    Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: group found {$gid} [OK]");

                    return true;
                }
            }
        }

        Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} ::User is not in allowed users or groups");

        return false;
    }
}
