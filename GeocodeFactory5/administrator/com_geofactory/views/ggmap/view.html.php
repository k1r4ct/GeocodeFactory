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
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;

class GeofactoryViewGgmap extends HtmlView
{
    protected $form;
    protected $item;
    protected $state;

    public function display($tpl = null)
    {
        // Inizializza le variabili
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        // Verifica errori
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        $user      = Factory::getUser();
        $isNew     = ($this->item->id == 0);
        $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));

        ToolbarHelper::title($isNew ? Text::_('COM_GEOFACTORY_MAPS_NEW_MAP') : Text::_('COM_GEOFACTORY_MAPS_EDIT_MAP'));

        if (!$checkedOut) {
            ToolbarHelper::apply('ggmap.apply');
            ToolbarHelper::save('ggmap.save');
            ToolbarHelper::save2new('ggmap.save2new');
            if (!$isNew) {
                ToolbarHelper::save2copy('ggmap.save2copy');
            }
        }

        if ($isNew) {
            ToolbarHelper::cancel('ggmap.cancel');
        } else {
            ToolbarHelper::cancel('ggmap.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}