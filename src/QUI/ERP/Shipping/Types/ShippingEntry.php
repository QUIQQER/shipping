<?php

/**
 * This file contains QUI\ERP\Shipping\Types\ShippingEntry
 */

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\CRUD\Factory;
use QUI\Translator;
use QUI\Permissions\Permission;

use QUI\ERP\Areas\Utils as AreaUtils;
use QUI\ERP\Shipping\Api;
use QUI\ERP\Shipping\Exceptions\ShippingCanNotBeUsed;

/**
 * Class ShippingEntry
 * A user created shipping entry
 *
 * @package QUI\ERP\Shipping\Types
 */
class ShippingEntry extends QUI\CRUD\Child implements Api\ShippingInterface
{
    /**
     * Shipping constructor.
     *
     * @param int $id
     * @param Factory $Factory
     */
    public function __construct($id, Factory $Factory)
    {
        parent::__construct($id, $Factory);

        $this->Events->addEvent('onDeleteBegin', function () {
            Permission::checkPermission('quiqqer.shipping.delete');
        });

        $this->Events->addEvent('onSaveBegin', function () {
            Permission::checkPermission('quiqqer.shipping.edit');
        });
    }

    /**
     * Return the shipping as an array
     *
     * @return array
     */
    public function toArray()
    {
        $lg = 'quiqqer/shipping';
        $id = $this->getId();

        $attributes = $this->getAttributes();
        $Locale     = QUI::getLocale();

        try {
            $availableLanguages = QUI\Translator::getAvailableLanguages();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $availableLanguages = [];
        }

        foreach ($availableLanguages as $language) {
            $attributes['title'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.title'
            );

            $attributes['description'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.description'
            );

            $attributes['workingTitle'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.workingTitle'
            );
        }

        // shipping type
        $attributes['shippingType'] = false;

        try {
            $attributes['shippingType'] = $this->getShippingType()->toArray();
        } catch (QUI\ERP\Shipping\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // icon
        $attributes['icon'] = '';

        try {
            $attributes['icon'] = $this->getIcon();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $attributes;
    }

    /**
     * Return the shipping type of the type
     *
     * @return Api\AbstractShippingEntry
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getShippingType()
    {
        $type = $this->getAttribute('shipping_type');

        if (!class_exists($type)) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.found',
                ['shippingType' => $type]
            ]);
        }

        $Type = new $type();

        if (!($Type instanceof Api\AbstractShippingEntry)) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.abstractShipping',
                ['shippingType' => $type]
            ]);
        }

        return $Type;
    }

    /**
     * is the user allowed to use this shipping
     *
     * @param QUI\Interfaces\Users\User $User
     * @return boolean
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        if ($this->isActive() === false) {
            return false;
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerShippingCanUsedBy', [$this, $User]);
        } catch (ShippingCanNotBeUsed $Exception) {
            return false;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return false;
        }


        try {
            $this->getShippingType();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }

        // usage definitions / limits
        $dateFrom  = $this->getAttribute('date_from');
        $dateUntil = $this->getAttribute('date_until');
        $now       = time();

        if ($dateFrom && strtotime($dateFrom) > $now) {
            return false;
        }

        if ($dateUntil && strtotime($dateUntil) < $now) {
            return false;
        }

        // assignment
        $userGroupValue = $this->getAttribute('user_groups');
        $areasValue     = $this->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            return true;
        }

        // not in area
        $areasValue = explode(',', $areasValue);

        if (!empty($areasValue) && !AreaUtils::isUserInAreas($User, $areasValue)) {
            return false;
        }

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $this->getAttribute('user_groups')
        );

        $discountUsers  = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        if (empty($discountUsers) && empty($discountGroups)) {
            return true;
        }

        // user checking
        foreach ($discountUsers as $uid) {
            if ($User->getId() == $uid) {
                return true;
            }
        }

        // group checking
        $groupsOfUser = $User->getGroups();

        /* @var $Group QUI\Groups\Group */
        foreach ($discountGroups as $gid) {
            foreach ($groupsOfUser as $Group) {
                if ($Group->getId() == $gid) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * is the shipping allowed in the order?
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return bool
     */
    public function canUsedInOrder(QUI\ERP\Order\OrderInterface $Order)
    {
        try {
            QUI::getEvents()->fireEvent('shippingCanUsedInOrder', [$this, $Order]);
        } catch (ShippingCanNotBeUsed $Exception) {
            return false;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Activate the shipping type
     *
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function activate()
    {
        $this->setAttribute('active', 1);
        $this->update();
        $this->refresh();
    }

    /**
     * Is the shipping active?
     *
     * @return bool
     */
    public function isActive()
    {
        return !!$this->getAttribute('active');
    }

    /**
     * Deactivate the shipping type
     *
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function deactivate()
    {
        $this->setAttribute('active', 0);
        $this->update();
        $this->refresh();
    }

    //region GETTER

    /**
     * Return the shipping title
     *
     * @param null $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/shipping',
            'shipping.'.$this->getId().'.title'
        );
    }

    /**
     * Return the shipping description
     *
     * @param null $Locale
     * @return array|string
     */
    public function getDescription($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/shipping',
            'shipping.'.$this->getId().'.description'
        );
    }

    /**
     * Return the shipping working title
     *
     * @param null $Locale
     * @return array|string
     */
    public function getWorkingTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/shipping',
            'shipping.'.$this->getId().'.workingTitle'
        );
    }

    /**
     *  Return the icon for the Shipping
     *
     * @return string - image url
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getIcon()
    {
        if (!QUI\Projects\Media\Utils::isMediaUrl($this->getAttribute('icon'))) {
            return $this->getShippingType()->getIcon();
        }

        try {
            $Image = QUI\Projects\Media\Utils::getImageByUrl(
                $this->getAttribute('icon')
            );

            return $Image->getSizeCacheUrl();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $this->getShippingType()->getIcon();
    }

    //endregion

    //region SETTER

    /**
     * Set the title
     *
     * @param array $titles
     */
    public function setTitle(array $titles)
    {
        $this->setShippingLocale(
            'shipping.'.$this->getId().'.title',
            $titles
        );
    }

    /**
     * Set the description
     *
     * @param array $descriptions
     */
    public function setDescription(array $descriptions)
    {
        $this->setShippingLocale(
            'shipping.'.$this->getId().'.description',
            $descriptions
        );
    }

    /**
     * Set the working title
     *
     * @param array $titles
     */
    public function setWorkingTitle(array $titles)
    {
        $this->setShippingLocale(
            'shipping.'.$this->getId().'.workingTitle',
            $titles
        );
    }

    /**
     * @param string $icon - image.php?
     */
    public function setIcon($icon)
    {
        if (QUI\Projects\Media\Utils::isMediaUrl($icon)) {
            $this->setAttribute('icon', $icon);
        }
    }

    /**
     * Creates a locale
     *
     * @param string $var
     * @param array $title
     */
    protected function setShippingLocale($var, $title)
    {
        $data = [
            'datatype' => 'php,js',
            'package'  => 'quiqqer/shipping'
        ];

        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (!isset($title[$language])) {
                continue;
            }

            $data[$language]         = $title[$language];
            $data[$language.'_edit'] = $title[$language];
        }

        $exists = Translator::getVarData('quiqqer/shipping', $var, 'quiqqer/shipping');

        try {
            if (empty($exists)) {
                Translator::addUserVar('quiqqer/shipping', $var, $data);
            } else {
                Translator::edit('quiqqer/shipping', $var, 'quiqqer/shipping', $data);
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        try {
            Translator::publish('quiqqer/shipping');
        } catch (QUi\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
    //endregion
}
