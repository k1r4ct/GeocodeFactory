<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormFieldList;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

FormHelper::loadFieldClass('list');

class JFormFieldTypeListe extends FormFieldList
{
    protected $type = 'typeListe';

    protected function getOptions()
    {
        $options = GeofactoryHelperAdm::getArrayObjTypeListe();
        array_unshift($options, HTMLHelper::_('select.option', '0', Text::_('JSELECT')));
        return $options;
    }
}
