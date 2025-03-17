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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Document\Document;
use Joomla\CMS\WebAsset\WebAssetManager;

// Carica il file di lingua per i testi
$lang = Factory::getLanguage();
$lang->load('com_geofactory');

class GeofactoryExternalMapHelper
{
    /**
     * Restituisce una mappa completa dal suo ID.
     *
     * @param   int     $id      L'ID della mappa
     * @param   string  $context Contesto ('m' = modulo, 'cbp' = profilo, ecc.)
     * @param   int     $zoom    Zoom forzato (0 per usare il valore predefinito)
     * @return  object|null  L'oggetto mappa o null se non trovato
     */
    public static function getMap($id, $context, $zoom = 0)
    {
        // Aggiunge il percorso dei modelli per Geofactory
        BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_geofactory/models', 'GeofactoryModel');

        $idMap = (int) $id;
        if ($idMap < 1) {
            echo "Nessuna mappa selezionata nelle impostazioni ({$context})!";
            return null;
        }

        // Recupera il modello di mappa (ignorando la richiesta)
        $model = BaseDatabaseModel::getInstance('Map', 'GeofactoryModel', array('ignore_request' => true));
        $model->setMapContext($context);
        $map = $model->getItem($idMap);
        $map->forceZoom = (int) $zoom;

        // Prepara una pseudo-vista per preparare il documento (per metadata, ecc.)
        if (!class_exists('GeofactoryViewMap')) {
            require_once JPATH_ROOT . '/components/com_geofactory/views/map/view.html.php';
        }
        $view = new GeofactoryViewMap();
        $view->initView($map);
        $view->_prepareDocument();

        return $map;
    }

    /**
     * Restituisce una mappa per la visualizzazione in modalità profilo.
     * Tenta di recuperare le coordinate dai campi, altrimenti geocodifica.
     *
     * @param   mixed   $fLat    Identificatore o valore del campo latitudine
     * @param   mixed   $fLng    Identificatore o valore del campo longitudine
     * @param   string  $mapvar  ID del div dove verrà visualizzata la mappa
     * @param   int     $idMap   ID della mappa
     * @param   int     $idU     ID utente (per la bolla, ad esempio)
     * @param   bool    $lib     Se true, carica la libreria Google Maps
     * @return  string  L'HTML generato per visualizzare la mappa
     */
    public static function getProfileMap($fLat, $fLng, $mapvar, $idMap, $idU, $lib = false)
    {
        BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_geofactory/models', 'GeofactoryModel');
        if ($idMap < 1) {
            return "@admin: nessuna mappa selezionata nelle impostazioni del plugin CB!";
        }

        $doc = Factory::getDocument();
        $wa = $doc->getWebAssetManager();

        // Aggiunge Bootstrap e jQuery se necessario (usando WebAsset Manager)
        $wa->useStyle('bootstrap.css')
           ->useScript('jquery');

        // Carica l'oggetto mappa dal modello
        $model = BaseDatabaseModel::getInstance('Map', 'GeofactoryModel', array('ignore_request' => true));
        $model->setMapContext('cbp');
        $map = $model->getItem($idMap);

        // Parametri della mappa
        $width  = (strlen($map->mapwidth) > 5) ? $map->mapwidth : "width:200px";
        $height = (strlen($map->mapheight) > 5) ? $map->mapheight : "height:200px";
        $size   = str_replace(' ', '', $width . ";" . $height . ";");
        $center = $map->centerlat . ',' . $map->centerlng;
        $zoom   = ((int) $map->mapsZoom) > 0 ? ((int) $map->mapsZoom) : 17;

        $type = 'google.maps.MapTypeId.ROADMAP';
        if (in_array($map->mapTypeOnStart, array("ROADMAP", "SATELLITE", "HYBRID", "TERRAIN"))) {
            $type = 'google.maps.MapTypeId.' . $map->mapTypeOnStart;
        }

        // Recupera i markerset collegati alla mappa
        $idMs = GeofactoryHelper::getArrayIdMs($map->id);

        // Cerca il primo markerset di tipo CB
        $cbMs = null;
        foreach ($idMs as $ms) {
            $list = GeofactoryHelper::getMs($ms);
            if (!$list) {
                continue;
            }
            if ($list->typeList == 'MS_CB') {
                $cbMs = $list;
                break;
            }
        }

        // Costruzione del codice JavaScript
        $js = "
            var gf_sr = '" . Uri::root() . "';
            var {$mapvar};
            var marker_{$mapvar};
            var cm_{$mapvar};
            function init_{$mapvar}(){
                if (!jQuery('#{$fLat}')){
                    alert('Nessuna coordinata in questo profilo (lat/long)!');
                    return;
                }
                jQuery('#{$mapvar}').fadeTo('slow', 1);
                var ula = '{$fLat}';
                var ulo = '{$fLng}';
                cm_{$mapvar} = new google.maps.LatLng(ula, ulo);
                if (ula == ulo){
                    cm_{$mapvar} = new google.maps.LatLng({$center});
                }
                var mo = {zoom: {$zoom}, mapTypeId: {$type}, center: cm_{$mapvar}};
                {$mapvar} = new google.maps.Map(document.getElementById('{$mapvar}'), mo);
                if (ula != ulo){
                    marker_{$mapvar} = new google.maps.Marker({map: {$mapvar}, draggable: false, animation: google.maps.Animation.DROP, position: cm_{$mapvar}});
                }
        ";

        if ($cbMs) {
            $icon = null;
            if ($cbMs->markerIconType == 1) {
                // Icona definita dall'utente
                $icon = (strlen($cbMs->customimage) > 3) ? (Uri::root() . $cbMs->customimage) : null;
            } else if ($cbMs->markerIconType == 2 && strlen($cbMs->mapicon) > 3) {
                // Mapicon dalla cartella di installazione
                $icon = (Uri::root() . 'media/com_geofactory/mapicons/' . $cbMs->mapicon);
            }
            if ($icon) {
                $js .= " marker_{$mapvar}.setIcon('{$icon}');";
            }
            // Aggiunge la gestione della bolla (bubble)
            $js .= "
                google.maps.event.addListener(marker_{$mapvar}, 'click', function(){
                    iw.open({$mapvar}, marker_{$mapvar});
                    axGetBubble(iw, {$idU}, {$cbMs->id}, 0, marker_{$mapvar}, {$mapvar}, false);
                });
            ";
        }

        $js .= "}
            google.maps.event.addDomListener(window, 'load', init_{$mapvar});
        ";

        $js = str_replace(array("\n", "\t", "  ", "\r"), '', trim($js));

        // Aggiunge eventualmente la chiave API e il parametro di lingua
        $config = ComponentHelper::getParams('com_geofactory');
        $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=" . $config->get('ggApikey') : "";
        $mapLang = (strlen($config->get('mapLang')) > 1) ? '&language=' . $config->get('mapLang') : "";
        
        if ($lib) {
            $http = $config->get('sslSite');
            if (empty($http)) {
                $http = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'])) ? "https://" : "http://";
            }
            
            // Usando WebAssetManager per aggiungere script esterni
            $wa->registerAndUseScript('googleMapsAPI', $http . 'maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey . $mapLang);
            $wa->registerAndUseScript('geoFactoryHeader', Uri::root() . 'components/com_geofactory/assets/js/header.js');
        }
        
        $doc->addStyleDeclaration("#{$mapvar} img{max-width:none!important;}");
        $wa->addInlineScript($js);

        $html = [];
        $html[] = '<div id="gf_admin_map_container" style="display:_none;padding: 3px 3px 4px 6px;">';
        $html[] = ' <div id="' . $mapvar . '" style="border:1px solid silver;' . $size . '" ></div>';
        $html[] = '</div>';
        return implode($html);
    }

    /**
     * Restituisce una mappa modificabile per il profilo (modalità modifica).
     * Se le coordinate non sono specificate, tenta di recuperarle tramite geocodifica.
     *
     * @param   mixed   $fLat         Identificatore o valore del campo latitudine
     * @param   mixed   $fLng         Identificatore o valore del campo longitudine
     * @param   string  $mapvar       ID del div contenente la mappa
     * @param   bool    $lib          Se true, carica la libreria Google Maps
     * @param   string  $defAdress    Indirizzo predefinito (se nessuna coordinata è specificata)
     * @param   array   $checkBox     Array di configurazione per la casella di selezione di auto-compilazione 
     * @param   array   $addressField Array associativo dei campi dell'indirizzo (city, zip, state, country, street)
     * @param   string  $defCenter    Coordinate del centro predefinito (formato "lat,lng")
     * @return  string  L'HTML generato per visualizzare la mappa modificabile
     */
    public static function getProfileEditMap($fLat, $fLng, $mapvar, $lib = false, $defAdress = '', $checkBox = array(), $addressField = array(), $defCenter = null)
    {
        $streetAndNumber = (isset($checkBox) && count($checkBox) == 4 && (($checkBox[3] == 1) || ($checkBox[3] == 3)))
            ? "adr.rue = adr.rue + ' ' + adr.num ;"
            : "adr.rue = adr.num + ' ' + adr.rue ;";

        $fieldCity    = (isset($addressField['city']) && strlen($addressField['city']) > 2) ? $addressField['city'] : '';
        $fieldZip     = (isset($addressField['zip']) && strlen($addressField['zip']) > 2) ? $addressField['zip'] : '';
        $fieldState   = (isset($addressField['state']) && strlen($addressField['state']) > 2) ? $addressField['state'] : '';
        $fieldCountry = (isset($addressField['country']) && strlen($addressField['country']) > 2) ? $addressField['country'] : '';
        $fieldStreet  = (isset($addressField['street']) && strlen($addressField['street']) > 2) ? $addressField['street'] : '';

        if (!$defCenter || strlen($defCenter) < 2) {
            $defCenter = '46.947,7.444';
        }

        $js = "
            var {$mapvar};
            var marker_{$mapvar};
            var cm_{$mapvar};
            function init_{$mapvar}(){
                if (!jQuery('#{$fLat}')){
                    alert('Campi delle coordinate non caricati (lat/long)!');
                    return;
                }
                jQuery('#{$mapvar}').fadeTo('slow', 1);
                var ula = jQuery('#{$fLat}').val();
                var ulo = jQuery('#{$fLng}').val();
                var uzo = 13;
                cm_{$mapvar} = new google.maps.LatLng(ula, ulo);
                var def_cm = new google.maps.LatLng({$defCenter});
                var mo = {zoom: parseInt(uzo), mapTypeId: google.maps.MapTypeId.ROADMAP, center: cm_{$mapvar}};
                {$mapvar} = new google.maps.Map(document.getElementById('{$mapvar}'), mo);
                marker_{$mapvar} = new google.maps.Marker({map: {$mapvar}, draggable: true, animation: google.maps.Animation.DROP, position: cm_{$mapvar}});
                if ((ula.length < 1) || (ulo.length < 1)){
                    jQuery('#{$fLat}').val(def_cm.lat());
                    jQuery('#{$fLng}').val(def_cm.lng());
                    {$mapvar}.panTo(def_cm);
                    marker_{$mapvar}.setPosition(def_cm);
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function(position){
                                var new_cm = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                                fillAdrFromPos(new_cm, '');
                                jQuery('#{$fLat}').val(new_cm.lat());
                                jQuery('#{$fLng}').val(new_cm.lng());
                                {$mapvar}.panTo(new_cm);
                                marker_{$mapvar}.setPosition(new_cm);
                                cm_{$mapvar} = new_cm;
                            }
                        );
                    }
                    else if (google.gears) {
                        var geo = google.gears.factory.create('beta.geolocation');
                        geo.getCurrentPosition(
                            function(position) {
                                var new_cm = new google.maps.LatLng(position.latitude, position.longitude);
                                fillAdrFromPos(new_cm, '');
                                jQuery('#{$fLat}').val(new_cm.lat());
                                jQuery('#{$fLng}').val(new_cm.lng());
                                {$mapvar}.panTo(new_cm);
                                marker_{$mapvar}.setPosition(new_cm);
                                cm_{$mapvar} = new_cm;
                            }
                        );
                    }
                    else {
                        alert('Geocode non supportato da questo browser.');
                    }
                }
                
                var inp = document.getElementById('searchPos_{$mapvar}');
                var autocomplete = new google.maps.places.Autocomplete(inp);
                autocomplete.bindTo('bounds', {$mapvar});
                
                google.maps.event.addListener(marker_{$mapvar}, 'dragend', function(event) {
                    jQuery('#{$fLat}').val(event.latLng.lat());
                    jQuery('#{$fLng}').val(event.latLng.lng());
                    {$mapvar}.panTo(event.latLng);
                    fillAdrFromPos(event.latLng, 'searchPos_{$mapvar}');
                });
                
                google.maps.event.addListener(autocomplete, 'place_changed', function() {
                    marker_{$mapvar}.setVisible(false);
                    var place = autocomplete.getPlace();
                    if (!place.geometry) {
                        return;
                    }
                    if (place.geometry.viewport) {
                        {$mapvar}.fitBounds(place.geometry.viewport);
                    } else {
                        {$mapvar}.setCenter(place.geometry.location);
                    }
                    jQuery('#{$fLat}').val(place.geometry.location.lat());
                    jQuery('#{$fLng}').val(place.geometry.location.lng());
                    marker_{$mapvar}.setPosition(place.geometry.location);
                    marker_{$mapvar}.setVisible(true);
                    fillAddress(place.address_components);
                });
            }
            
            function fillAdrFromPos(new_cm, target){
                var addressInput = '';
                if (target.length > 0){
                    addressInput = document.getElementById(target);
                }
                var geocoder = new google.maps.Geocoder();
                if (geocoder) {
                    geocoder.geocode({ 'latLng': new_cm}, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            fillAddress(results[0].address_components);
                            if (target.length > 0){
                                addressInput.placeholder = results[1].formatted_address;
                            }
                            return;
                        }
                    });
                }
                if (target.length > 0){
                    addressInput.placeholder = (Math.round(new_cm.lat() * 100) / 100) + ',' + (Math.round(new_cm.lng() * 100) / 100);
                }
            }
            
            function fillAddress(geoAdresse){
                if (!jQuery('#autofilladdress').prop('checked')){
                    return;
                }
                var adr = {};
                for (var i = 0; i < geoAdresse.length; i++){
                    var city = '';
                    var types = geoAdresse[i].types.join(',');
                    if (types == 'street_number'){
                        adr.num = geoAdresse[i].long_name;
                    }
                    if (types == 'route' || types == 'point_of_interest,establishment'){
                        adr.rue = geoAdresse[i].long_name;
                    }
                    if (types == 'sublocality,political' || types == 'locality,political' || types == 'neighborhood,political' || types == 'administrative_area_level_3,political'){
                        adr.vil = (city == '' || types == 'locality,political') ? geoAdresse[i].long_name : city;
                    }
                    if (types == 'administrative_area_level_1,political'){
                        adr.can = geoAdresse[i].short_name;
                    }
                    if (types == 'postal_code' || types == 'postal_code_prefix,postal_code'){
                        adr.zip = geoAdresse[i].long_name;
                    }
                    if (types == 'country,political'){
                        adr.pay = geoAdresse[i].long_name;
                    }
                }
                if (typeof(adr.num) == 'undefined'){ adr.num = ''; }
                if (typeof(adr.rue) == 'undefined'){ adr.rue = ''; }
                {$streetAndNumber}
                addAdressInfo(adr.vil, '{$fieldCity}');
                addAdressInfo(adr.zip, '{$fieldZip}');
                addAdressInfo(adr.can, '{$fieldState}');
                addAdressInfo(adr.pay, '{$fieldCountry}');
                addAdressInfo(adr.rue, '{$fieldStreet}');
            }
            
            function addAdressInfo(info, field){
                if (typeof(info) == 'undefined') { return; }
                if (typeof(info) == '') { return; }
                if (typeof(info) == ' ') { return; }
                if (typeof(field) == 'undefined') { return; }
                if (field.length < 2) { return; }
                if (jQuery('#' + field).length < 1) { return; }
                jQuery('#' + field).val(info);
            }
            
            google.maps.event.addDomListener(window, 'load', init_{$mapvar});
        ";
        $js = str_replace(array("\n", "\t", "  ", "\r"), '', trim($js));
        
        $doc = Factory::getDocument();
        $wa = $doc->getWebAssetManager();
        
        if ($lib) {
            $config = ComponentHelper::getParams('com_geofactory');
            $http = $config->get('sslSite');
            if (empty($http)) {
                $http = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'])) ? "https://" : "http://";
            }
            $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=" . $config->get('ggApikey') : "";
            $mapLang = (strlen($config->get('mapLang')) > 1) ? '&language=' . $config->get('mapLang') : "";
            
            // Usando WebAssetManager
            $wa->registerAndUseScript('googleMapsAPI', $http . 'maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey . $mapLang);
        }
        
        $doc->addStyleDeclaration("#{$mapvar} img{max-width:none!important;}");
        $wa->addInlineScript($js);
        
        $html = [];
        $html[] = '<div id="gf_admin_map_container" style="display:_none;padding: 3px 3px 4px 6px;">';
        $html[] = ' <div id="' . $mapvar . '" style="border:1px solid silver;' . $size . '" ></div>';
        $html[] = '</div>';
        return implode($html);
    }
}