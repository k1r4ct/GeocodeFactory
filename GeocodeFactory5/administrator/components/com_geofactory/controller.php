<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;

require_once JPATH_COMPONENT . '/helpers/geofactory.php';

class GeofactoryController extends BaseController
{
    protected $default_view = 'accueil';

    public function display($cachable = false, $urlparams = false)
    {
        $app      = Factory::getApplication();
        $document = Factory::getDocument();

        // Vista da mostrare (di default "accueil")
        $vName = $app->input->get('view', 'accueil');

        // Legge la configurazione
        $config = ComponentHelper::getParams('com_geofactory');
        $terms  = $config->get('showTerms', 1);

        // Se la configurazione richiede di mostrare "terms"
        if ($terms == 1)
        {
            $vName = 'terms';
        }

        $vFormat = $document->getType();
        $lName   = $app->input->get('layout', 'default');

        // Carica la vista
        $view = $this->getView($vName, $vFormat);

        if ($view)
        {
            // Ottieni il modello corrispondente
            $model = $this->getModel($vName);
            $view->setModel($model, true);
            $view->setLayout($lName);
            $view->document = $document;

            // In Joomla 4 non esiste più la sidebar di Joomla 3:
            // GeofactoryHelperAdm::addSubmenu($vName);

            $view->display();
        }

        return $this;
    }
}
