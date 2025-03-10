<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

class GeofactoryControllerAssigns extends AdminController
{
    public function getModel($name = 'Assign', $prefix = 'GeofactoryModel', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
