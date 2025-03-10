<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

class GeofactoryControllerOldmaps extends AdminController
{
    public function getModel($name = 'Oldmap', $prefix = 'GeofactoryModel', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function import()
    {
        $model = $this->getModel('oldmaps');
        $model->import();

        $this->setRedirect(
            Route::_('index.php?option=com_geofactory&view=oldmaps', false),
            Text::_('COM_GEOFACTORY_IMP_DONE')
        );
    }
}
