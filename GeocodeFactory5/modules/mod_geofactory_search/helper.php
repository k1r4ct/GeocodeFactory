<?php
/**
 * @name        Geocode Factory Search module
 * @package     mod_geofactory_search
 * @copyright   Copyright © 2014-2023 - All rights reserved.
 * @license     GNU/GPL
 * @author      Cédric Pelloquin
 * @author mail  info@myJoom.com
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class ModGeofactorySearchHelper
{
    /**
     * Genera l'input per il campo radius
     *
     * @param object $params Parametri del modulo
     * @param string $lmb HTML del bottone "Locate me"
     * @return string|null HTML dell'input o null se non necessario
     */
    public function getRadiusInput(object $params, string $lmb): ?string
    {
        if (!$params->get('bRadius')) {
            return null;
        }

        $app = Factory::getApplication('site');
        $def = $app->input->getString('gf_mod_search', '');
        $def = htmlspecialchars(str_replace(['"', '`'], '', $def), ENT_QUOTES, 'UTF-8');

        $ph = (strlen($params->get('placeholder')) > 1) ? ' placeholder="' . $params->get('placeholder') . '" ' : '';

        return '<input id="gf_mod_search" name="gf_mod_search" type="text" class="form-control" value="' . $def . '" ' . $ph . ' />' . $lmb;
    }

    /**
     * Configura gli script JavaScript necessari
     *
     * @param object $params Parametri del modulo
     * @return void
     */
    public function setJsInit(object $params): void
    {
        if (!$params->get('bRadius')) {
            return;
        }
        
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $wa = $document->getWebAssetManager();
        
        // Assicuriamoci che jQuery sia caricato
        HTMLHelper::_('jquery.framework');
        
        $com = $app->input->getString('option', '');
        
        if (strtolower($com) != 'com_geofactory') {
            $config = ComponentHelper::getParams('com_geofactory');
            $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=" . $config->get('ggApikey') : "";
            $mapLang = (strlen($config->get('mapLang')) > 1) ? '&language=' . $config->get('mapLang') : '';
            
            // Registra e utilizza lo script Google Maps API
            $wa->registerAndUseScript(
                'google-maps-places', 
                'https://maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey . $mapLang,
                [],
                ['defer' => true]
            );
        }

        $country = '';
        $co = $params->get('sCountryLimit');
        if (strlen($co) == 2) {
            $country = ",componentRestrictions: {country: '{$co}'} ";
        }

        $js = 'function initDataForm() {
                    var input = document.getElementById("gf_mod_search");
                    if (input && typeof google !== "undefined" && google.maps && google.maps.places) {
                        var ac = new google.maps.places.Autocomplete(input, {types: ["geocode"]' . $country . '});
                    }
                }';

        $js .= ' if (window.addEventListener) { window.addEventListener("load", initDataForm, false); }
                else if (document.addEventListener) { document.addEventListener("load", initDataForm, false); }
                else if (window.attachEvent) { window.attachEvent("onload", initDataForm); }';

        if ((int)$params->get('bLocateMe') > 0) {
            $js .= ' function userPosMod() {
                        if (typeof google === "undefined" || !google.maps) {
                            console.error("Google Maps API non disponibile");
                            return;
                        }
                        
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
                            }, function(error) {
                                alert("Geolocation error: " + error.message);
                            });
                        } else {
                            alert("Your browser doesn\'t allow geocoding.");
                        }
                    }';
        }

        $js = str_replace(["\n", "\t", "  "], '', $js);
        $wa->addInlineScript($js);
    }

    /**
     * Genera i pulsanti per il modulo
     *
     * @param object $params Parametri del modulo
     * @param array $labels Etichette da visualizzare
     * @return string HTML dei pulsanti
     */
    public function getButtons(object $params, array $labels): string
    {
        $but = '<input type="submit" name="Submit" class="btn btn-primary" value="' . $labels[2] . '" />';

        $app = Factory::getApplication('site');
        $com = $app->input->getString('option', '');
        
        if ($com != 'com_geofactory') {
            return $but;
        }

        $document = $app->getDocument();
        $wa = $document->getWebAssetManager();
        
        $js = 'function applyField() {
                    if (document.getElementById("addressInput")){
                        document.getElementById("addressInput").value = document.getElementById("gf_mod_search").value;
                    }
                    if (document.getElementById("radiusSelect")){
                        document.getElementById("radiusSelect").value = document.getElementById("gf_mod_radius").value;
                    }
                }';
                
        $js = str_replace(["\n", "\t", "  "], '', $js);
        $wa->addInlineScript($js);

        $but = '<button type="button" class="btn btn-primary me-2" onclick="applyField(); document.getElementById(\'gf_search_rad_btn\').onclick();">' . $labels[2] . '</button> ';
        $but .= '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'gf_mod_search\').value=\'\'; document.getElementById(\'gf_reset_rad_btn\').onclick();">' . $labels[3] . '</button>';
        
        return $but;
    }

    /**
     * Genera l'input per le distanze del radius
     *
     * @param object $params Parametri del modulo
     * @return string|null HTML del select o null se non necessario
     */
    public function getRadiusDistances(object $params): ?string
    {
        if (!$params->get('bRadius')) {
            return null;
        }

        $ret = '<select id="gf_mod_radius" name="gf_mod_radius" class="form-select">';
        $ret .= $this->_getListRadius($params);
        $ret .= '</select>';
        
        return $ret;
    }

    /**
     * Ottiene il testo di introduzione del radius
     *
     * @param object $params Parametri del modulo
     * @return string Testo di introduzione
     */
    public function getRadiusIntro(object $params): string
    {
        if (!$params->get('sIntro')) {
            return '';
        }
        
        return $params->get('sIntro');
    }

    /**
     * Genera il contenitore della barra laterale
     *
     * @param object $params Parametri del modulo
     * @return string|null HTML della barra laterale o null se non necessario
     */
    public function getSideBar(object $params): ?string
    {
        if (!$params->get('bSidebar')) {
            return null;
        }
        
        return '<div id="gf_sidebar" class="gf-sidebar mt-3"></div>';
    }

    /**
     * Genera il contenitore delle liste laterali
     *
     * @param object $params Parametri del modulo
     * @return string|null HTML delle liste laterali o null se non necessario
     */
    public function getSideLists(object $params): ?string
    {
        if (!$params->get('bSidelists')) {
            return null;
        }
        
        return '<div id="gf_sidelists" class="gf-sidelists mt-3"></div>';
    }

    /**
     * Ottiene le etichette per il modulo
     *
     * @param object $params Parametri del modulo
     * @return array Array di etichette
     */
    public function getLabels(object $params): array
    {
        $sLabInput  = $params->get('sLabInput');
        $sLabSelect = $params->get('sLabSelect');
        $sLabSearch = $params->get('sLabSearch');
        $sLabReset  = $params->get('sLabReset');

        $vRes = [];
        $vRes[] = (strlen($sLabInput) > 1) ? $sLabInput : Text::_('MOD_GEOFACTORY_SEARCH_ENTER_PLACE');
        $vRes[] = (strlen($sLabSelect) > 1) ? $sLabSelect : Text::_('MOD_GEOFACTORY_SEARCH_SELECT_DIST');
        $vRes[] = (strlen($sLabSearch) > 1) ? $sLabSearch : Text::_('MOD_GEOFACTORY_SEARCH_SEARCH');
        $vRes[] = (strlen($sLabReset) > 1) ? $sLabReset : Text::_('MOD_GEOFACTORY_SEARCH_RESET_MAP');
        
        return $vRes;
    }

    /**
     * Genera la lista delle distanze disponibili
     *
     * @param object $params Parametri del modulo
     * @return string HTML delle opzioni di distanza
     */
    protected function _getListRadius(object $params): string
    {
        $listVal = explode(',', $params->get('vRadius', ''));
        $unit = $params->get('iUnit', 'km');

        $app = Factory::getApplication('site');
        $def = $app->input->getString('gf_mod_radius', '');

        $ret = "";
        
        if (count($listVal)) {
            foreach ($listVal as $val) {
                $val = trim($val);
                $val = intval($val);
                
                if ($val < 1) {
                    continue;
                }
                
                $sel = ($def == $val) ? ' selected="selected" ' : '';
                $ret .= '<option value="' . $val . '" ' . $sel . '>' . $val . ' ' . $unit . '</option>';
            }
        }
        
        if ($ret == "") {
            return '<option value="10">10' . $unit . '</option>';
        }
        
        return $ret;
    }

    /**
     * Genera il pulsante "Locate Me"
     *
     * @param object $params Parametri del modulo
     * @return string HTML del pulsante o stringa vuota
     */
    public function getLocateMeBtn(object $params): string
    {
        if ((int)$params->get('bLocateMe') < 1) {
            return '';
        }
        
        return '<button type="button" name="mod_gfs_locateme_btn" id="mod_gfs_locateme_btn" class="btn btn-outline-secondary mt-2" onClick="userPosMod();">' . Text::_('MOD_GEOFACTORY_LOCATE_ME_TXT') . '</button>';
    }
}