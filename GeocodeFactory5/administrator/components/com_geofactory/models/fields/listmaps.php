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

use Joomla\CMS\Form\Field\ListField;

class JFormFieldListMaps extends ListField
{
    protected $type = 'listMaps';

    protected function getOptions()
    {
        $options = GeofactoryHelperAdm::getArrayListMaps();
        return $options;
    }
}