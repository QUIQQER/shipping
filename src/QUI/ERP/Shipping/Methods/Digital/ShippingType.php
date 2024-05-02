<?php

namespace QUI\ERP\Shipping\Methods\Digital;

use Exception;
use QUI;
use QUI\ERP\Areas\Utils as AreaUtils;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\DigitalProduct;
use QUI\ERP\Shipping\Debug;
use QUI\Locale;

use function array_filter;
use function array_map;
use function explode;
use function in_array;
use function is_numeric;
use function trim;

/**
 * Class DigitalType
 *
 * Shipping for digital products that are delivered via download and/or email.
 */
class ShippingType extends QUI\ERP\Shipping\Api\AbstractShippingType
{
    /**
     * @param Locale|null $Locale
     * @return string
     */
    public function getTitle(?QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/shipping', 'shipping.DigitalType.title');
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return QUI\ERP\Shipping\Shipping::getInstance()->getHost() .
            URL_OPT_DIR
            . 'quiqqer/shipping/bin/images/shipping/download.png';
    }

    /**
     * @param QUI\ERP\ErpEntityInterface $Entity
     * @param QUI\ERP\Shipping\Types\ShippingEntry $ShippingEntry
     * @return bool
     */
    public function canUsedInOrder(
        QUI\ERP\ErpEntityInterface $Entity,
        QUI\ERP\Shipping\Types\ShippingEntry $ShippingEntry
    ): bool {
        if ($ShippingEntry->isActive() === false) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: is not active");

            return false;
        }

        if (!$ShippingEntry->isValid()) {
            return false;
        }

        // Check if order contains NON-digital products
        /** @var QUI\ERP\Accounting\Article $Article */
        foreach ($Entity->getArticles() as $Article) {
            try {
                // Do not parse coupon codes / discounts
                if (empty($Article->getId()) || !is_numeric($Article->getId())) {
                    continue;
                }

                $Product = Products::getProduct($Article->getId());

                // If a non-digital product is part of the order -> digital shipping is not possible
                if (!($Product instanceof DigitalProduct)) {
                    Debug::addLog(
                        "{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: contains at least one NON-DIGITAL"
                        . " product that must be shipped physically."
                    );

                    return false;
                }
            } catch (Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        // Check restriction to certain products / product categories
        $articles = $ShippingEntry->getAttribute('articles');
        $categories = $ShippingEntry->getAttribute('categories');

        $toInt = function ($article) {
            return (int)$article;
        };

        if (!empty($articles)) {
            $articles = array_map($toInt, explode(',', $articles));
        } else {
            $articles = [];
        }

        if (!empty($categories)) {
            $categories = array_map($toInt, explode(',', $categories));
        } else {
            $categories = [];
        }

        // if articles and categories are empty, its allowed
        if (empty($articles) && empty($categories)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: no products or categories [ok]");

            return true;
        }

        $ArticleList = $Entity->getArticles();
        $entityArticles = $ArticleList->getArticles();

        foreach ($entityArticles as $Article) {
            try {
                $productId = $Article->getId();

                if (!empty($articles) && in_array($productId, $articles)) {
                    Debug::addLog(
                        "{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: product $productId is in allowed list [ok]"
                    );

                    return true;
                }

                $Product = QUI\ERP\Products\Handler\Products::getProduct($productId);
                $articleCategories = $Product->getCategories();

                /* @var $Category QUI\ERP\Products\Category\Category */
                foreach ($articleCategories as $Category) {
                    $categoryId = $Category->getId();

                    if (in_array($categoryId, $categories)) {
                        Debug::addLog(
                            "{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: category $categoryId is in allowed list [ok]"
                        );

                        return true;
                    }
                }
            } catch (QUI\Exception) {
                return false;
            }
        }

        Debug::addLog("{$this->getTitle()} :: no products found in this order which are fit");

        return false;
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\ERP\Shipping\Api\ShippingInterface $ShippingEntry
     * @param QUI\ERP\ErpEntityInterface $Entity
     * @return bool
     */
    public function canUsedBy(
        QUI\Interfaces\Users\User $User,
        QUI\ERP\Shipping\Api\ShippingInterface $ShippingEntry,
        QUI\ERP\ErpEntityInterface $Entity
    ): bool {
        if ($ShippingEntry->isActive() === false) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: is not active");

            return false;
        }

        if ($User instanceof QUI\ERP\User) {
            try {
                $User = QUI::getUsers()->get($User->getUUID());
            } catch (QUI\Exception) {
            }
        }

        // assignment
        $userGroupValue = $ShippingEntry->getAttribute('user_groups');
        $areasValue = $ShippingEntry->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: users + areas are empty [OK]");

            return true;
        }

        $Address = $Entity->getDeliveryAddress();

        $areasValue = trim($areasValue);
        $areasValue = trim($areasValue, ',');
        $areasValue = explode(',', $areasValue);
        $areasValue = array_filter($areasValue);

        // not in area
        if (!empty($areasValue) && !AreaUtils::isAddressInArea($Address, $areasValue)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: User is not in areas");

            return false;
        }

        Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: User is in areas");

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $ShippingEntry->getAttribute('user_groups')
        );

        $discountUsers = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        if (empty($discountUsers) && empty($discountGroups)) {
            Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: no discounts [OK]");

            return true;
        }

        // user checking
        foreach ($discountUsers as $uid) {
            if ($User->getId() == $uid || $User->getUUID() == $uid) {
                Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: user found $uid [OK]");

                return true;
            }
        }

        // group checking
        $groupsOfUser = $User->getGroups();

        /* @var $Group QUI\Groups\Group */
        foreach ($discountGroups as $gid) {
            foreach ($groupsOfUser as $Group) {
                if ($Group->getId() == $gid || $Group->getUUID() == $gid) {
                    Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} :: group found $gid [OK]");

                    return true;
                }
            }
        }

        Debug::addLog("{$this->getTitle()} :: {$ShippingEntry->getTitle()} ::User is not in allowed users or groups");

        return false;
    }
}
