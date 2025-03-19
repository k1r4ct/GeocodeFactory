<?php
/**
 * @name      Sobipro Radius Search module
 * @package   mod_sobiproRadiusSearch
 * @copyright Copyright © 2012
 * @license   GNU/GPL
 * @author    ...
 * @update Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Helper per il modulo SobiPro Radius Search.
 */
class modsobiproRadiusSearchHelper
{
    /**
     * Inizializza gli script JS necessari (Google Maps, geocoding, ecc.)
     *
     * @param  object $params   I parametri del modulo
     * @param  string $def_loc  Eventuale location di default
     * @param  string $def_rad  Eventuale raggio di default
     * @param  string $apiKey   La chiave API di Google Maps
     */
    public static function setJsSrcipt($params, $def_loc, $def_rad, $apiKey)
    {
        // Gestione dell’esclusione
        $exclGeoShort = (substr($params->m_exclusionValueGoogle, 0, 1) === '-') ? 0 : 1;
        $exclGeoValue = ($exclGeoShort === 0)
            ? substr($params->m_exclusionValueGoogle, 1)
            : $params->m_exclusionValueGoogle;

        $js = '';

        // Funzione JS che recupera un possibile "component" da address_components
        $js .= 'function getGeoExclMod(arComp) {
            var res = "";
        ';

        if (strlen($exclGeoValue) > 3) {
            $js .= '
                jQuery.each(arComp, function(i, adComp){
                    if(adComp.types[0] == "' . $exclGeoValue . '"){
            ';
            if ($exclGeoShort === 1) {
                $js .= 'res = adComp.short_name;';
            } else {
                $js .= 'res = adComp.long_name;';
            }
            $js .= '
                        return;
                    }
                });
            ';
        }

        $js .= 'return res;}';

        // Se il modulo prevede l’autocomplete
        if (isset($params->m_geocodeMode) && $params->m_geocodeMode != 1) {
            $bound = '';
            $param = '';

            if (!empty($params->m_restricpt1) && !empty($params->m_restricpt2)) {
                // Se desideri restringere l’area
                $bound = 'var restricted = new google.maps.LatLngBounds(
                    new google.maps.LatLng(' . $params->m_restricpt1 . '),
                    new google.maps.LatLng(' . $params->m_restricpt2 . ')
                );';
                $param = ', bounds: restricted';
            }

            // Tipi di place: ad esempio ["geocode"], ["(regions)"], ...
            $acTypes = '[]';
            if (!empty($params->m_acTypes)) {
                $acTypes = $params->m_acTypes; 
            }

            $country = '';
            if (!empty($params->m_acCountry) && strlen($params->m_acCountry) == 2) {
                $country = ', componentRestrictions: {country: "' . $params->m_acCountry . '"} ';
            }

            $js .= '
            function initModRSA(){
                ' . $bound . '
                var input = document.getElementById("mj_rs_mod_center_selector");
                var options = {
                    types: ' . $acTypes . $param . $country . '
                };
                var ac = new google.maps.places.Autocomplete(input, options);

                google.maps.event.addListener(ac, "place_changed", function() {
                    var pl = ac.getPlace();
                    document.getElementById("mj_rs_mod_ref_lat").value = pl.geometry.location.lat();
                    document.getElementById("mj_rs_mod_ref_lng").value = pl.geometry.location.lng();
                    document.getElementById("mj_rs_mod_ref_excl").value = getGeoExclMod(pl.address_components);

                    // Se esistono campi nascosti con ID diverso
                    if (document.getElementById("mj_rs_ref_lat")){
                        document.getElementById("mj_rs_ref_lat").value = pl.geometry.location.lat();
                    }
                    if (document.getElementById("mj_rs_ref_lng")){
                        document.getElementById("mj_rs_ref_lng").value = pl.geometry.location.lng();
                    }
                    if (document.getElementById("mj_rs_ref_excl")){
                        document.getElementById("mj_rs_ref_excl").value = document.getElementById("mj_rs_mod_ref_excl").value;
                    }
                });
            }
            google.maps.event.addDomListener(window, "load", initModRSA);
            ';
        }

        // Se è attivata la geocodifica manuale (geocodeMode > 0)
        if (isset($params->m_geocodeMode) && $params->m_geocodeMode > 0) {
            $ifEmpty = '';
            $country = '';

            // Se ci sono parametri di default
            if (strlen($def_loc) > 3) {
                $ifEmpty .= ' entry = "' . $def_loc . '"; ';
            }
            if (strlen($def_rad) > 0) {
                $ifEmpty .= 'document.getElementById("mj_rs_mod_radius_selector").value=' . (float)$def_rad . ';';
            }
            if (!$ifEmpty) {
                // Se non c’è nulla, submitta il form
                $ifEmpty = 'document.getElementById("spSearchForm").submit(); return;';
            }

            if (!empty($params->m_acCountry) && strlen($params->m_acCountry) == 2) {
                $country = ', region: "' . $params->m_acCountry . '"';
            }

            // Funzione per geocodifica manuale
            $js .= '
            function _manGeocodeMod(){
                var entry = document.getElementById("mj_rs_mod_center_selector").value;
                if (entry.length < 2){
                    ' . $ifEmpty . '
                }
                var geocoder = new google.maps.Geocoder();
                geocoder.geocode({address: entry ' . $country . '}, function(results, status){
                    if (status == google.maps.GeocoderStatus.OK) {
                        document.getElementById("mj_rs_mod_ref_lat").value  = results[0].geometry.location.lat();
                        document.getElementById("mj_rs_mod_ref_lng").value  = results[0].geometry.location.lng();
                        document.getElementById("mj_rs_mod_center_selector").value = results[0].formatted_address;
                        document.getElementById("mj_rs_mod_ref_excl").value = getGeoExclMod(results[0].address_components);
                        document.getElementById("spSearchForm").submit();
                    } else {
                        document.getElementById("mj_rs_mod_ref_lat").value = "";
                        document.getElementById("mj_rs_mod_ref_lng").value = "";
                        document.getElementById("mj_rs_mod_center_selector").value = "";
                        document.getElementById("spSearchForm").submit();
                    }
                });
            };
            ';
        }

        // Se c’è la localizzazione userPosMod
        if (!empty($params->m_locateStart) || !empty($params->m_uselocateme)) {
            $js .= '
            function userPosMod(){
                var gc = new google.maps.Geocoder();
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function (po){
                        gc.geocode({"latLng": new google.maps.LatLng(po.coords.latitude, po.coords.longitude)},
                            function(results, status){
                                if(status == google.maps.GeocoderStatus.OK) {
                                    document.getElementById("mj_rs_mod_ref_lat").value    = po.coords.latitude;
                                    document.getElementById("mj_rs_mod_ref_lng").value    = po.coords.longitude;
                                    document.getElementById("mj_rs_mod_center_selector").value = results[0].formatted_address;
                                    document.getElementById("mj_rs_mod_ref_excl").value   = getGeoExclMod(results[0].address_components);
                                } else {
                                    alert("Address error : " + status);
                                }
                            }
                        );
                    });
                } else {
                    alert("Your browser does not allow geolocation.");
                }
            };
            ';
        }

        // Se bisogna auto-ricavare la posizione all’avvio
        if (!empty($params->m_locateStart)) {
            $js .= 'google.maps.event.addDomListener(window, "load", userPosMod);';
        }

        // Carichiamo la libreria Google Maps + Places, con la key
        // Notiamo che Joomla 4 raccomanda l'https in caso di SSL.
        $uri    = Uri::getInstance();
        $scheme = $uri->isSsl() ? 'https' : 'http';

        $doc = Factory::getDocument();
        $doc->addScript($scheme . '://maps.googleapis.com/maps/api/js?libraries=places&key=' . $apiKey);

        // Minimizza e aggiunge come script inline
        $cleanJs = str_replace(["\n", "\t", "  "], '', $js);
        $doc->addScriptDeclaration($cleanJs);
    }

    /**
     * Ritorna il pulsante "Locate me"
     */
    public static function getLocateMeBtn($params, $txt)
    {
        if (empty($params->m_uselocateme)) {
            return '';
        }
        if (strlen($txt) < 2) {
            $txt = Text::_('PEC_M_SPRS_LOCATEME_BTN');
        }
        return '<input type="button" name="mod_sprs_locateme_btn" id="mod_sprs_locateme_btn"
                onclick="userPosMod();" value="' . htmlspecialchars($txt, ENT_QUOTES) . '"/>';
    }

    /**
     * Ritorna la lista distanze (select o hidden)
     */
    public static function getDistanceList($params, $show)
    {
        $unit = 'km';
        if (isset($params->m_unit)) {
            if ($params->m_unit == 2) {
                $unit = 'mi';
            } elseif ($params->m_unit == 3) {
                $unit = 'nm';
            }
        }

        $dist = [];
        if (!empty($params->m_distances)) {
            $dist = explode(',', $params->m_distances);
        }
        if (!$dist || !count($dist) || $dist[0] == 0) {
            $dist = [10, 25, 50, 100, 250, 500];
        }

        $ret = '';
        if ($show == 0) {
            // SELECT
            $options = [];
            foreach ($dist as $d) {
                $dFloat = (float)$d;
                $unitAff = $unit;
                // Se unit=4 => metri?
                if (!empty($params->m_unit) && $params->m_unit == 4 && $dFloat < 1) {
                    $dFloat  = $dFloat * 1000;
                    $unitAff = 'm';
                }
                $options[] = HTMLHelper::_('select.option', $d, $dFloat . $unitAff);
            }
            $ret = HTMLHelper::_('select.genericlist', $options, 'mj_rs_mod_radius_selector',
                'class="inputbox" size="1"', 'value','text');
        } else {
            // hidden
            $idx = $show - 1;
            if ($idx >= count($dist)) {
                $idx = count($dist) - 1;
            }
            $ret = '<input type="hidden" name="mj_rs_mod_radius_selector" value="' . $dist[$idx] . '"/>';
        }
        return $ret;
    }

    /**
     * Ritorna i radio button per la keyword phrase
     */
    public static function getKeywordMode($mode)
    {
        $ret = '';
        if ($mode == 1 || $mode == 2) {
            $ret = '
                <input type="radio" name="spsearchphrase" id="spsearchphrase_all" value="all" checked="checked"/>
                <label for="spsearchphrase_all">' . Text::_('PEC_M_SPRS_SEARCH_ALL') . '</label>
                <input type="radio" name="spsearchphrase" id="spsearchphrase_any" value="any"/>
                <label for="spsearchphrase_any">' . Text::_('PEC_M_SPRS_SEARCH_ANY') . '</label>
                <input type="radio" name="spsearchphrase" id="spsearchphrase_exact" value="exact"/>
                <label for="spsearchphrase_exact">' . Text::_('PEC_M_SPRS_SEARCH_EXACT') . '</label>
            ';
        }
        if ($mode == 1) {
            $ret = '<div class="spsearch_label">' . Text::_('PEC_M_SPRS_SEARCH_MATCH') . '</div>' . $ret;
        }
        if ($mode == 3) {
            $ret = '<input type="hidden" name="spsearchphrase" value="all"/>';
        }
        if ($mode == 4) {
            $ret = '<input type="hidden" name="spsearchphrase" value="exact"/>';
        }
        return $ret;
    }

    /**
     * Ritorna il pulsante "Aggiorna" / "Cerca"
     */
    public static function getSubmitBtn($txt, $params)
    {
        if (strlen($txt) < 2) {
            $txt = Text::_('PEC_M_SPRS_SEARCH_BTN');
        }
        // Se geocodeMode > 0 => onclick
        if (!empty($params->m_geocodeMode) && $params->m_geocodeMode > 0) {
            return '<input type="button" name="mod_sprs_search_btn" id="mod_sprs_search_btn"
                    onclick="_manGeocodeMod();" value="' . htmlspecialchars($txt, ENT_QUOTES) . '"/>';
        }
        // Altrimenti <submit>
        return '<input type="submit" name="mod_sprs_search_btn" id="mod_sprs_search_btn"
                value="' . htmlspecialchars($txt, ENT_QUOTES) . '"/>';
    }

    /**
     * Elabora un eventuale template personalizzato
     */
    public static function getTemplate($useTmpl, $tmplCode, $kwshow, $keyw, $rad, $btn, $lmb)
    {
        if (!$useTmpl) {
            return null;
        }
        $out = str_replace('[KEYWORD]', $kwshow, $tmplCode);
        $out = str_replace('[MATCH]', $keyw, $out);
        $out = str_replace('[RADIUS_START]', $rad, $out);
        $out = str_replace('[SEARCH_BTN]', $btn, $out);
        $out = str_replace('[LOCATE_ME]', $lmb, $out);
        return $out;
    }

    /**
     * Campo di input per la keyword
     */
    public static function getKeywordInput($mode)
    {
        $ret = '';
        if ($mode == 1) {
            $ret = '<div class="spsearch_label">' . Text::_('PEC_M_SPRS_SEARCH_LAB') . '</div>
                    <input type="text" name="sp_search_for" value=""/>';
        }
        if ($mode == 2) {
            $ret = '<input type="text" name="sp_search_for" value=""/>';
        }
        return $ret;
    }

    /**
     * Ritorna l’HTML della selezione radius + input
     */
    public static function getRadiusSearchForm($params, $dist, $useTmpl, $prevEnt)
    {
        $ret = ' <div>';
        if (!empty($params->m_enabled) && !empty($params->m_mjrslic)
            && ($params->m_enabled > 0) && (strlen($params->m_mjrslic) > 5)) {

            $attr = '';
            if (!empty($params->m_inputText)) {
                $attr .= ' placeholder="' . htmlspecialchars($params->m_inputText, ENT_QUOTES) . '" ';
            }
            $lab = Text::_('PEC_M_SPRS_RADIUS');
            if (!empty($params->m_label)) {
                $lab = $params->m_label;
            }
            // se non usi template, mostri un label
            if (!$useTmpl) {
                $ret .= '<div class="spsearch_label">' . $lab . '</div>';
            }
            // onkeypress per evitare submit immediato
            $onKP = $prevEnt ? ' onkeypress="return event.keyCode!=13" ' : '';
            $ret .= '<input type="text"
                            id="mj_rs_mod_center_selector"
                            name="mj_rs_mod_center_selector"
                            value=""
                            ' . $attr . $onKP . ' /> ';
        }
        $ret .= '</div>';
        return $ret . $dist;
    }

    /**
     * Ritorna alcuni parametri (?)
     */
    public static function setGetSpParam($sps)
    {
        SPFactory::registry()->loadDBSection('mjradius');
        $settings = Sobi::Reg('mjradius.settings.params');
        if (strlen($settings)) {
            $settings = SPConfig::unserialize($settings);
        }
        if (is_array($settings) && isset($settings[$sps])) {
            return (object)$settings[$sps];
        }
        return null;
    }
}
