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

use Joomla\CMS\Form\FormField;

class JFormFieldKmlFile extends FormField
{
    protected $type = 'kmlFile';

    protected function getInput()
    {
        $ret  = '<fieldset style="width:500px;">';
        $ret .= '<textarea name="' . $this->name . '" id="' . $this->id . '" style="float:left!important;width:500px;height:75px;">';
        $ret .= $this->value;
        $ret .= '</textarea>';
        $ret .= '<div style="float:left!important;width:500px;">';
        $ret .= "http://services.google.com/earth/kmz/realtime_earthquakes_n.kmz;<br/>
                 http://ms.tvorba.com/a.kmz;<br/>
                 http://gmaps-samples.googlecode.com/svn/trunk/ggeoxml/cta.kml;<br/>
                 http://www.sports-clubs.net/kml/Cricket.kml<br/>";
        $ret .= '</div>';
        $ret .= '</fieldset>';
        return $ret;
    }
}
