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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

FormHelper::loadFieldClass('list');

class JFormFieldcategoryMultiSelect extends FormFieldList
{
    protected $type = 'categorymultiselect';

    protected function getOptions()
    {
        PluginHelper::importPlugin('geocodefactory');
        $dsp = JDispatcher::getInstance();
        $typeList = $this->form->getValue("typeList");
        $language = $this->form->getValue("language");
        $idTopParent = -1;

        $vTmp = array();
        $dsp->trigger('getAllSubCats', array($typeList, &$vTmp, &$idTopParent, $language));

        $vRes = array();
        if (count($vTmp) > 5000) { // Per siti grandi, si evita la gerarchia per evitare problemi
            foreach ($vTmp as $category) {
                $vRes[] = HTMLHelper::_('select.option', $category->catid, $category->title);
            }
        } else if (count($vTmp) > 0) {
            GeofactoryPluginHelper::_getChildCatOf($vTmp, $idTopParent, $vRes, "");
        }
        return $vRes;
    }
}
