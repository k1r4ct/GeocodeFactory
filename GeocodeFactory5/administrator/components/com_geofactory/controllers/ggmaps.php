<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

class GeofactoryControllerGgmaps extends AdminController
{
    protected $text_prefix = 'COM_GEOFACTORY';

    public function getModel($name = 'Ggmap', $prefix = 'GeofactoryModel', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
