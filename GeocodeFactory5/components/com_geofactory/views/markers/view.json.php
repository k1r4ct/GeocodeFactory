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

use Joomla\CMS\MVC\View\JsonView;

class GeofactoryViewMarkers extends JsonView
{
    protected $state;
    protected $item;

    public function display($tpl = null)
    {
        // Qui è possibile implementare la logica per preparare i dati JSON,
        // ad esempio assegnando a $this->item e $this->state i dati necessari.
        parent::display($tpl);
    }
}
