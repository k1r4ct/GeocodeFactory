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

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

class GeofactoryViewGeocodesBatch extends HtmlView
{
    protected $total;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $this->total   = $app->input->getInt('total', -1);
        $this->type    = $app->input->getCmd('typeliste', 'NO_TYPE');
        $this->assign  = $app->input->getInt('assign', -1);
        $this->items   = $this->get('Items');
        $this->idsToGc = $this->get('idsToGc', '');

        // Verifica errori
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(JText::_('COM_GEOFACTORY_BATCH_GEOCODE'));
    }
}
