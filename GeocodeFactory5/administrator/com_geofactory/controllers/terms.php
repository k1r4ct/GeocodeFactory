<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

class GeofactoryControllerTerms extends BaseController
{
    public function apply()
    {
        $model = $this->getModel('terms');
        $model->setTerms(0);

        $this->setRedirect(
            Route::_('index.php?option=com_geofactory', false),
            Text::_('COM_GEOFACTORY_TERMS_WELCOME')
        );
    }

    public function cancel()
    {
        $model = $this->getModel('terms');
        $model->setTerms(1);

        $this->setRedirect(
            Route::_('index.php', false),
            Text::_('COM_GEOFACTORY_TERMS_WARNING')
        );
    }
}
