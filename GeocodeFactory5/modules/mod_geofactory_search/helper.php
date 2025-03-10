<?php
/**
 * @name        Geocode Factory Search module
 * @package     mod_geofactory_search
 * @copyright   Copyright © 2014 - All rights reserved.
 * @license     GNU/GPL
 * @author      Cédric Pelloquin
 * @author mail  info@myJoom.com
 * @update		Daniele Bellante
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;

class modGeofactorySearchHelper
{
    public static function getRadiusInput($params, $lmb)
    {
        if (!$params->get('bRadius')) {
            return null;
        }

        $app = Factory::getApplication('site');
        $def = $app->input->getString('gf_mod_search', '');
        $def = htmlspecialchars(str_replace(['"', '`'], '', $def), ENT_QUOTES, 'UTF-8');

        $ph = (strlen($params->get('placeholder')) > 1) ? ' placeholder="' . $params->get('placeholder') . '" ' : '';

        return '<input id="gf_mod_search" name="gf_mod_search" type="text" class="inputbox" value="' . $def . '" ' . $ph . ' />' . $lmb;
    }

    public static function setJsInit($params)
    {
        if (!$params->get('bRadius')) {
            return;
        }
        $document = Factory::getDocument();
        $app = Factory::getApplication('site');
        $com = $app->input->getString('option', '');
        if (strtolower($com) != 'com_geofactory') {
            $config = ComponentHelper::getParams('com_geofactory');
            $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=" . $config->get('ggApikey') : "";
            $mapLang = (strlen($config->get('mapLang')) > 1) ? '&language=' . $config->get('mapLang') : '';
            $document->addCustomTag('<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey . $mapLang . '"></script>');
        }

        $country = '';
        $co = $params->get('sCountryLimit');
        if (strlen($co) == 2) {
            $country = ",componentRestrictions: {country: '{$co}'} ";
        }

        $js = 'function initDataForm() {
                    var input = document.getElementById("gf_mod_search");
                    var ac = new google.maps.places.Autocomplete(input, {types: ["geocode"]' . $country . '});
                }';

        $js .= ' if (window.addEventListener) { window.addEventListener("load", initDataForm, false); }
                else if (document.addEventListener) { document.addEventListener("load", initDataForm, false); }
                else if (window.attachEvent) { window.attachEvent("onload", initDataForm); }';

        if ((int)$params->get('bLocateMe') > 0) {
            $js .= ' function userPosMod(){
                        var gc = new google.maps.Geocoder();
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function (po) {
                                gc.geocode({"latLng": new google.maps.LatLng(po.coords.latitude, po.coords.longitude) }, function(results, status) {
                                    if(status == google.maps.GeocoderStatus.OK) {
                                        document.getElementById("gf_mod_search").value = results[0]["formatted_address"];
                                    } else {
                                        alert("Address error : " + status);
                                    }
                                });
                            });
                        } else {
                            alert("Your browser doesn\'t allow geocoding.");
                        }
                    }';
        }

        $js = str_replace(["\n", "\t", "  "], '', $js);
        $document->addCustomTag('<script type="text/javascript">' . $js . '</script>');
    }

    public static function getButtons($params, $labels)
    {
        $but = '<input type="submit" name="Submit" class="button" value="' . $labels[2] . '" />';

        $app = Factory::getApplication('site');
        $com = $app->input->getString('option', '');
        if ($com != 'com_geofactory') {
            return $but;
        }

        $document = Factory::getDocument();
        $js = 'function applyField(){
                    if (document.getElementById("addressInput")){
                        document.getElementById("addressInput").value = document.getElementById("gf_mod_search").value;
                    }
                    if (document.getElementById("radiusSelect")){
                        document.getElementById("radiusSelect").value = document.getElementById("gf_mod_radius").value;
                    }
                }';
        $js = str_replace(["\n", "\t", "  "], '', $js);
        $document->addCustomTag('<script type="text/javascript">' . $js . '</script>');

        $but = '<input type="button" onclick="applyField(); document.getElementById(\'gf_search_rad_btn\').onclick();" value="' . $labels[2] . '"/> ';
        $but .= '<input type="button" onclick="document.getElementById(\'gf_mod_search\').value=\'\'; document.getElementById(\'gf_reset_rad_btn\').onclick();" value="' . $labels[3] . '"/>';
        return $but;
    }

    public static function getRadiusDistances($params)
    {
        if (!$params->get('bRadius')) {
            return null;
        }

        $ret = '<select id="gf_mod_radius" name="gf_mod_radius" class="inputbox">';
        $ret .= modGeofactorySearchHelper::_getListRadius($params);
        $ret .= '</select>';
        return $ret;
    }

    public static function getRadiusIntro($params)
    {
        if (!$params->get('sIntro')) {
            return '';
        }
        return $params->get('sIntro');
    }

    public static function getSideBar($params)
    {
        if (!$params->get('bSidebar')) {
            return null;
        }
        return '<div id="gf_sidebar"></div>';
    }

    public static function getSideLists($params)
    {
        if (!$params->get('bSidelists')) {
            return null;
        }
        return '<div id="gf_sidelists"></div>';
    }

    public static function getLabels($params)
    {
        $sLabInput  = $params->get('sLabInput');
        $sLabSelect = $params->get('sLabSelect');
        $sLabSearch = $params->get('sLabSearch');
        $sLabReset  = $params->get('sLabReset');

        $vRes = [];
        $vRes[] = (strlen($sLabInput) > 1) ? $sLabInput : JText::_('MOD_GEOFACTORY_SEARCH_ENTER_PLACE');
        $vRes[] = (strlen($sLabSelect) > 1) ? $sLabSelect : JText::_('MOD_GEOFACTORY_SEARCH_SELECT_DIST');
        $vRes[] = (strlen($sLabSearch) > 1) ? $sLabSearch : JText::_('MOD_GEOFACTORY_SEARCH_SEARCH');
        $vRes[] = (strlen($sLabReset) > 1) ? $sLabReset : JText::_('MOD_GEOFACTORY_SEARCH_RESET_MAP');
        return $vRes;
    }

    public static function _getListRadius($params)
    {
        $listVal = explode(',', $params->get('vRadius'));
        $unit = $params->get('sUnit');

        $app = Factory::getApplication('site');
        $def = $app->input->getString('gf_mod_radius', '');

        $ret = "";
        if (count($listVal)) {
            foreach ($listVal as $val) {
                $val = trim($val);
                $val = intval($val);
                if ($val < 1)
                    continue;
                $sel = ($def == $val) ? ' selected="selected" ' : '';
                $ret .= '<option value="' . $val . '" ' . $sel . '>' . $val . $unit . '</option>';
            }
        }
        if ($ret == "")
            return '<option value="10">10' . $unit . '</option>';
        return $ret;
    }

    public static function getLocateMeBtn($params)
    {
        if ((int)$params->get('bLocateMe') < 1)
            return '';
        return '<input type="button" name="mod_gfs_locateme_btn" id="mod_gfs_locateme_btn" onClick="userPosMod();" value="' . JText::_('MOD_GEOFACTORY_LOCATE_ME_TXT') . '" />';
    }
}
