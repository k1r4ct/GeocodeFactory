<?php
/**
 * @name        Radius Search Application
 * @package     mjradius
 * @copyright   Copyright © 2023 - All rights reserved.
 * @license     GNU/GPL
 * @author      MyJoom
 * @author mail info@myJoom.com
 * @website     www.myJoom.com
 */

defined('SOBIPRO') || exit('Restricted access');

// Importazione di tutti i namespace necessari per Joomla 4
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Database\DatabaseInterface;

/**
 * Class per la gestione del Radius Search in SobiPro
 *
 * @since  1.0.0
 */
class MJRadius extends SPPlugin
{
    /**
     * Metodi disponibili per l'estensione
     *
     * @var    array
     * @since  1.0.0
     */
    private static $methods = array('ListEntry', 'SearchDisplay', 'AfterExtendedSearch', 'OnFormStartSearch', 'OnRequestSearch', 'HeaderSend');
    
    /**
     * Proprietà di configurazione
     */
    private $m_enabled              = false;
    private $m_unit                 = 1;
    private $m_distances            = array(10, 25, 50, 100, 250, 500);
    private $m_orderresult          = true;
    private $m_googleicon           = false;
    private $m_uselocateme          = true;
    private $m_sprequest            = false;
    private $m_label                = "";
    private $m_inputText            = "";
    private $m_restricpt1           = "255,255";
    private $m_restricpt2           = "255,255";
    private $m_geocodeMode          = 0;    // 0=default autocomplete, 1=geocode in search, 2=hybrid
    private $m_acTypes              = "[]";
    private $m_acCountry            = "{}";
    private $m_mapVariable          = "";
    private $m_locateStart          = false;
    private $m_inputwidth           = 0;
    private $m_defaultcenter        = "";
    private $m_custDistText         = "";
    private $m_spGeoFieldId         = 0;
    private $m_salesAreaDist        = "";
    private $m_salesAreaVcMode      = 0;
    private $m_exclusionField       = null;
    private $m_exclusionFilter      = "";
    private $m_exclusionFieldGoogle = null;
    private $m_exclusionValueGoogle = "";
    private $m_hideDistances        = 0;
    private $m_useNotGeocode        = 0;

    /**
     * Costruttore
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function __construct()
    {
        // Carica la configurazione dal registro
        SPFactory::registry()->loadDBSection('mjradius');
        $setting = Sobi::Reg('mjradius.settings.params');
        
        if (strlen($setting)) {
            $setting = SPConfig::unserialize($setting);
        }

        // Carica le impostazioni in base alla sezione corrente o predefinite
        if (is_array($setting) && isset($setting[Sobi::Section()])) {
            $setting = $setting[Sobi::Section()];
            $this->loadSettingsFromArray($setting);
        } else {
            // Fallback: carica le impostazioni dal metodo precedente (per retrocompatibilità)
            $this->loadSettingsFromRegistry();
        }

        // Converti la distanza in array
        $this->m_distances = explode(',', $this->m_distances);
        if (!count($this->m_distances)) {
            $this->m_distances = array(10, 25, 50, 100, 250, 500);
        }

        // Gestione delle virgole nei tipi di autocomplete
        $this->m_acTypes = str_replace("#", ",", $this->m_acTypes);
    }

    /**
     * Carica le impostazioni da un array
     *
     * @param   array  $setting  Array di impostazioni
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function loadSettingsFromArray($setting)
    {
        $this->m_enabled              = (strlen($setting['m_mjrslic']) < 4) ? 0 : $setting['m_enabled'];
        $this->m_unit                 = $setting['m_unit'];
        $this->m_distances            = $setting['m_distances'];
        $this->m_googleicon           = $setting['m_googleicon'];
        $this->m_uselocateme          = $setting['m_uselocateme'];
        $this->m_orderresult          = $setting['m_orderresult'];
        $this->m_label                = $setting['m_label'];
        $this->m_inputText            = $setting['m_inputText'];
        $this->m_raddec               = $setting['m_raddec'];
        $this->m_radmil               = $setting['m_radmil'];
        $this->m_radvir               = $setting['m_radvir'];
        $this->m_restricpt1           = trim($setting['m_restricpt1']);
        $this->m_restricpt2           = trim($setting['m_restricpt2']);
        $this->m_geocodeMode          = $setting['m_geocodeMode'];
        $this->m_acTypes              = $setting['m_acTypes'];
        $this->m_acCountry            = $setting['m_acCountry'];
        $this->m_mapVariable          = $setting['m_mapVariable'];
        $this->m_locateStart          = $setting['m_locateStart'];
        $this->m_inputwidth           = $setting['m_inputwidth'];
        $this->m_defaultcenter        = $setting['m_defaultcenter'];
        $this->m_spGeoFieldId         = $setting['m_spGeoFieldId'];
        $this->m_custDistText         = $setting['m_custDistText'];
        $this->m_salesAreaDist        = $setting['m_salesAreaDist'];
        $this->m_salesAreaVcMode      = $setting['m_salesAreaVcMode'];
        $this->m_exclusionField       = $setting['m_exclusionField'];
        $this->m_exclusionFilter      = trim($setting['m_exclusionFilter']);
        $this->m_exclusionFieldGoogle = $setting['m_exclusionFieldGoogle'];
        $this->m_exclusionValueGoogle = trim($setting['m_exclusionValueGoogle']);
        $this->m_hideDistances        = $setting['m_hideDistances'];
        $this->m_useNotGeocode        = $setting['m_useNotGeocode'];
    }

    /**
     * Carica le impostazioni dal registro (metodo precedente)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function loadSettingsFromRegistry()
    {
        SPFactory::registry()->loadDBSection('mjradius');
        $this->m_enabled       = Sobi::Reg('mjradius.m_mjrslic.value') < 12345 ? 0 : Sobi::Reg('mjradius.m_enabled.value');
        $this->m_unit          = Sobi::Reg('mjradius.m_unit.value');
        $this->m_distances     = Sobi::Reg('mjradius.m_distances.value');
        $this->m_googleicon    = Sobi::Reg('mjradius.m_googleicon.value');
        $this->m_uselocateme   = Sobi::Reg('mjradius.m_uselocateme.value');
        $this->m_orderresult   = Sobi::Reg('mjradius.m_orderresult.value');
        $this->m_label         = Sobi::Reg('mjradius.m_label.value');
        $this->m_inputText     = Sobi::Reg('mjradius.m_inputText.value');
        $this->m_raddec        = Sobi::Reg('mjradius.m_raddec.value');
        $this->m_radmil        = Sobi::Reg('mjradius.m_radmil.value');
        $this->m_radvir        = Sobi::Reg('mjradius.m_radvir.value');
        $this->m_restricpt1    = trim(Sobi::Reg('mjradius.m_restricpt1.value'));
        $this->m_restricpt2    = trim(Sobi::Reg('mjradius.m_restricpt2.value'));
        $this->m_geocodeMode   = Sobi::Reg('mjradius.m_geocodeMode.value');
        $this->m_acTypes       = Sobi::Reg('mjradius.m_acTypes.value');
        $this->m_acCountry     = Sobi::Reg('mjradius.m_acCountry.value');
        $this->m_mapVariable   = Sobi::Reg('mjradius.m_mapVariable.value');
        $this->m_locateStart   = Sobi::Reg('mjradius.m_locateStart.value');
        $this->m_inputwidth    = Sobi::Reg('mjradius.m_inputwidth.value');
        $this->m_defaultcenter = Sobi::Reg('mjradius.m_defaultcenter.value');
        $this->m_spGeoFieldId  = Sobi::Reg('mjradius.m_spGeoFieldId.value');
        $this->m_custDistText  = Sobi::Reg('mjradius.m_custDistText.value');
        $this->m_salesAreaDist = Sobi::Reg('mjradius.m_salesAreaDist.value');
        $this->m_salesAreaVcMode = Sobi::Reg('mjradius.m_salesAreaVcMode.value');
        $this->m_exclusionField    = Sobi::Reg('mjradius.m_exclusionField.value');
        $this->m_exclusionFilter   = trim(Sobi::Reg('mjradius.m_exclusionFilter.value'));
        $this->m_exclusionFieldGoogle = Sobi::Reg('mjradius.m_exclusionFieldGoogle.value');
        $this->m_exclusionValueGoogle = trim(Sobi::Reg('mjradius.m_exclusionValueGoogle.value'));
        $this->m_hideDistances     = Sobi::Reg('mjradius.m_hideDistances.value');
        $this->m_useNotGeocode     = Sobi::Reg('mjradius.m_useNotGeocode.value');
    }

    /**
     * Verifica se il plugin può gestire l'azione specificata
     *
     * @param   string  $action  Azione da verificare
     *
     * @return  boolean  True se l'azione può essere gestita
     *
     * @since   1.0.0
     */
    public function provide($action)
    {
        if ($this->m_enabled) {
            return in_array($action, self::$methods);
        }
        
        return false;
    }

    /**
     * Visualizza l'interfaccia di ricerca nel modulo di ricerca
     *
     * @param   array  &$data  Dati da visualizzare
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function SearchDisplay(&$data)
    {
        // In Joomla 4, è consigliato usare sempre HTTPS per le API Google
        $gfConfig = ComponentHelper::getParams('com_geofactory');
        $ggApikey = "&key=" . trim($gfConfig->get('ggApikey'));

        // Determina se il sito sta usando HTTPS
        $scheme = Uri::getInstance()->isSsl() ? 'https' : 'http';

        // Aggiunge lo script Google Maps
        Factory::getDocument()->addScript($scheme . '://maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey);

        $this->_setJs();
        SPLang::load('SpApp.mjradius');

        $unit = $this->_getUnit();
        foreach ($this->m_distances as $dist) {
            // se lavoriamo in metri ...
            $distAff = ((count($unit) == 2) && ($dist < 1)) ? ($dist * 1000) : ($dist * 1);
            $unitAff = (count($unit) == 1) ? $unit[0] : (($dist < 1) ? $unit[1] : $unit[0]);
            $sout[$dist] = $distAff . $unitAff;
        }

        $input_param = array('id' => 'mj_rs_center_selector', 'class' => 'input-medium');
        if (strlen($this->m_inputText) > 0) {
            $input_param['placeholder'] = $this->m_inputText;
        }
        if ($this->m_inputwidth > 0) {
            $input_param['size'] = $this->m_inputwidth;
        }

        // Ottieni la sessione dal container di Joomla 4
        $session = Factory::getApplication()->getSession();
        $ref_lat  = $session->get('mj_rs_ref_lat', null);
        $ref_lng  = $session->get('mj_rs_ref_lng', null);
        $ref_dist = $session->get('mj_rs_ref_dist', null);
        $ref_loc  = $session->get('mj_rs_center_selector', null);
        $ref_excl = $session->get('mj_rs_ref_excl', null);

        $label  = (strlen($this->m_label)) ? $this->m_label : Sobi::Txt('MJRS.CENTER');
        $radius = ($this->m_hideDistances == 1)
            ? '<input type="hidden" id="mj_rs_radius_selector" name="mj_rs_radius_selector" value="' . $this->m_distances[0] . '" />'
            : SPHtml_Input::select('mj_rs_radius_selector', $sout, $ref_dist, false, array('id' => 'mj_rs_radius_selector', 'class' => 'text_area', 'style' => 'width:100px;'));

        $center = SPHtml_Input::text('mj_rs_center_selector', $ref_loc, $input_param);

        $lmbtn   = ' <img style="cursor:pointer;" src="' . Sobi::FixPath(Sobi::Cfg('img_folder_live') . '/locateme.png') . '" onClick="userPos();" alt="' . Sobi::Txt('MJRS.USE_POSITION') . '" class="btn" title="' . Sobi::Txt('MJRS.USE_POSITION') . '" /> ';
        $button1 = ($this->m_uselocateme == 1)
            ? SPHtml_Input::button('mj_rs_cutom', '<i class="icon-circle-blank"></i> ' . Sobi::Txt('MJRS.USE_POSITION'), array('id' => 'mj_rs_cutom', 'class' => 'btn', 'onClick' => 'userPos();'))
            : '';
        $button2 = ($this->m_uselocateme == 2) ? $lmbtn : '';
        $center  = ($this->m_uselocateme == 3) ? $center . $lmbtn : $center;

        $imageW  = ($this->m_googleicon == 1) ? ' <img src="https://developers.google.com/maps/documentation/places/web-service/images/powered-by-google-on-white.png" alt="Powered by Google" />' : '';
        $imageB  = ($this->m_googleicon == 2) ? ' <img src="https://developers.google.com/maps/documentation/places/web-service/images/powered-by-google-on-black.png" alt="Powered by Google" />' : '';

        // Gestione ENTER per Joomla 4
        if ($this->m_geocodeMode != 0) {
            Factory::getDocument()->addScriptDeclaration(
                "document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('#form .input').forEach(function(input) {
                        input.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                this.nextElementSibling.focus();
                            }
                        });
                    });
                });"
            );
        }

        // Gestione dell'input con ENTER
        Factory::getDocument()->addScriptDeclaration(
            "document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('mj_rs_center_selector').addEventListener('keypress', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                    }
                });
            });"
        );

        $out = Sobi::Txt(
            '<div class="control-group">
                <label class="control-label" for="mj_rs_center_selector">' . $label . '</label>
                <div class="controls">' . $center . ' ' . $radius . ' ' . $button1 . $button2 . $imageW . $imageB . '</div>
                <input type="hidden" id="mj_rs_ref_lat" name="mj_rs_ref_lat" value="' . $ref_lat . '" />
                <input type="hidden" id="mj_rs_ref_lng" name="mj_rs_ref_lng" value="' . $ref_lng . '" />
            </div>'
        );

        if (!$this->m_enabled) {
            return;
        }

        SPLang::load('SpApp.mjradius');
        $data['mjradius'] = $out;
    }

    /**
     * Aggiunge gli script Google Maps all'header
     *
     * @param   array  &$head  Array di header
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function HeaderSend(&$head)
    {
        $gfConfig = ComponentHelper::getParams('com_geofactory');
        $ggApikey = "&key=" . trim($gfConfig->get('ggApikey'));

        // Determina se il sito sta usando HTTPS
        $scheme = Uri::getInstance()->isSsl() ? 'https' : 'http';
        $googleAPILink = $scheme . '://maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey;

        $doc = Factory::getDocument();
        if (!isset($doc->_scripts[$googleAPILink])) {
            array_unshift($head['js'], '<script type="text/javascript" src="' . $googleAPILink . '"></script>');
        }
    }

    /**
     * Inizializza la ricerca 
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function OnFormStartSearch()
    {
        $task = SPRequest::string('task', null);
        if (strtolower($task) == "search.results") {
            return;
        }
        
        // Puliamo la sessione
        $session = Factory::getApplication()->getSession();
        $session->clear('mj_rs_ref_lat');
        $session->clear('mj_rs_ref_lng');
        $session->clear('mj_rs_ref_dist');
        $session->clear('mj_rs_center_selector');
        $session->clear('mj_rs_ref_excl');
    }

    /**
     * Gestisce la richiesta di ricerca
     *
     * @param   array  &$req  Parametri di ricerca
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function OnRequestSearch(&$req)
    {
        if (!count($req)) {
            return;
        }
        
        $query = trim($req["search_for"]);
        if ((strlen($query) > 0) && ($query != Sobi::Txt('SH.SEARCH_FOR_BOX'))) {
            $this->m_sprequest = true;
            return;
        }
        
        // Cerchiamo campi field_
        foreach ($req as $k => $v) {
            if (substr($k, 0, 6) != "field_") {
                continue;
            }
            
            if (is_array($v)) {
                foreach ($v as $t) {
                    if (strlen($t) > 0) {
                        $this->m_sprequest = true;
                        return;
                    }
                }
            } else {
                if (strlen($v) > 0) {
                    $this->m_sprequest = true;
                    return;
                }
            }
        }
    }

    /**
     * Filtra i risultati della ricerca estesa
     *
     * @param   array  &$result  Risultati di ricerca
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function AfterExtendedSearch(&$result)
    {
        if (!$this->m_enabled) {
            return;
        }
        
        // Prima otteniamo la lista delle entry senza coordinate,
        // poi le entry da escludere, e infine filtriamo in base al radius
        $withoutCoord   = $this->filterWithoutCoordinatesAfterExtendedSearch($result);
        $inExclude      = $this->filterExludeAfterExtendedSearch($result, $withoutCoord);
        $inExcludeGoogle = $this->filterGoogleExludeAfterExtendedSearch($result, $withoutCoord);
        $vRadRes        = $this->filterRadiusAfterExtendedSearch($result);

        if (!$vRadRes) {
            return; // Non abbiamo un center definito o radius
        }

        $inRadius = $vRadRes[0];
        $dist     = $vRadRes[1];
        $resultOri = $result;
        $result   = array();

        if (!count($inRadius)) {
            return;
        }

        foreach ($inRadius as $rad) {
            // Se la distanza (distance - salesArea) è > $dist e non è fra le exclusion, saltiamo
            if ((($rad->distance - $rad->salesArea) > $dist)
                && (!in_array($rad->id, $inExclude))
                && (!in_array($rad->id, $inExcludeGoogle))) {
                continue;
            }
            
            // Se c'era una query "pura" di SobiPro
            if ($this->m_sprequest) {
                if (in_array($rad->id, $resultOri)) {
                    $result[] = $rad->id;
                }
            } else {
                // Ricerchiamo tutte
                $result[] = $rad->id;
            }
        }
        
        // Includiamo in coda le entry senza coordinate se necessario
        if ($this->m_useNotGeocode > 0 && is_array($withoutCoord) && count($withoutCoord)) {
            foreach ($withoutCoord as $noCo) {
                if (!in_array($noCo, $result)) {
                    $result[] = $noCo;
                }
            }
        }
    }

    /**
     * Filtra le entry senza coordinate
     *
     * @param   array  $result  Risultati di ricerca
     *
     * @return  array  Entry senza coordinate
     *
     * @since   1.0.0
     */
    private function filterWithoutCoordinatesAfterExtendedSearch($result)
    {
        if ($this->m_useNotGeocode < 1 || !count($result)) {
            return array();
        }

        $spGeo = ($this->m_spGeoFieldId < 1) ? " " : " AND GEO.fid=" . (int)$this->m_spGeoFieldId;

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('DISTINCT O.id')
                ->from($db->quoteName('#__sobipro_object', 'O'))
                ->leftJoin(
                    $db->quoteName('#__sobipro_field_geo', 'GEO') 
                    . ' ON GEO.sid = O.id' . $spGeo
                )
                ->where([
                    $db->quoteName('O.oType') . ' = ' . $db->quote('entry'),
                    '((O.validUntil > NOW() OR O.validUntil IN(' . $db->quote('0000-00-00 00:00:00') . ',' . $db->quote('1970-01-01 01:00:00') . ')) 
                      AND (O.validSince < NOW() OR O.validSince IN(' . $db->quote('0000-00-00 00:00:00') . ',' . $db->quote('1970-01-01 01:00:00') . ')))',
                    $db->quoteName('state') . ' = 1',
                    $db->quoteName('GEO.section') . ' = ' . Sobi::Section(),
                    $db->quoteName('GEO.latitude') . ' IS NOT NULL',
                    $db->quoteName('GEO.longitude') . ' IS NOT NULL'
                ]);
                
            $vIds = $db->setQuery($query)->loadColumn();
            
            if (!is_array($vIds) || !count($vIds)) {
                return array();
            }
            
            // Differenza: otteniamo quelle presenti in $result ma non in $vIds
            return array_diff($result, $vIds);
        } catch (\Exception $e) {
            Sobi::Error('mjradius', "Radius search plugin query error: " . $e->getMessage(), SPC::WARNING, 0, __LINE__, __CLASS__);
            return array();
        }
    }

    /**
     * Filtra le entry per esclusione basata su Google
     *
     * @param   array  $result         Risultati di ricerca
     * @param   array  &$withoutCoord  Entry senza coordinate
     *
     * @return  array  Entry escluse
     *
     * @since   1.0.0
     */
    private function filterGoogleExludeAfterExtendedSearch($result, &$withoutCoord)
    {
        if (!$this->m_exclusionFieldGoogle || !$this->m_exclusionValueGoogle) {
            return array();
        }

        $session = Factory::getApplication()->getSession();
        $session->clear('mj_rs_ref_excl');

        $ref_excl = SPRequest::string('mj_rs_ref_excl', null);
        if (strlen($ref_excl) < 2) {
            $ref_excl = SPRequest::string('mj_rs_mod_ref_excl', null);
        }
        if (strlen($ref_excl) < 1) {
            return array();
        }
        
        $session->set('mj_rs_ref_excl', $ref_excl);

        $field = $this->m_exclusionFieldGoogle;
        $table = "#__sobipro_field_data";
        $text  = "baseData";

        if ($field < 0) {
            $field = $field * -1;
            $table = "#__sobipro_field_option_selected";
            $text  = "optValue";
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('DISTINCT sid')
                ->from($db->quoteName($table))
                ->where([
                    $db->quoteName('fid') . ' = ' . (int)$field,
                    $db->quoteName($text) . ' = ' . $db->quote($ref_excl)
                ]);
                
            if (count($result) > 0) {
                $query->where($db->quoteName('sid') . ' IN (' . implode(',', $result) . ')');
            }
            
            $vIds = $db->setQuery($query)->loadColumn();
            
            if ($this->m_useNotGeocode > 0 && is_array($vIds) && count($vIds)) {
                $withoutCoord = array_intersect($withoutCoord, $vIds);
            }
            
            return $vIds;
        } catch (\Exception $e) {
            Sobi::Error('mjradius', "Radius search plugin query error: " . $e->getMessage(), SPC::WARNING, 0, __LINE__, __CLASS__);
            return array();
        }
    }

    /**
     * Filtra le entry per esclusione
     *
     * @param   array  $result         Risultati di ricerca
     * @param   array  &$withoutCoord  Entry senza coordinate
     *
     * @return  array  Entry escluse
     *
     * @since   1.0.0
     */
    private function filterExludeAfterExtendedSearch($result, &$withoutCoord)
    {
        if (!$this->m_exclusionField || (strlen($this->m_exclusionFilter) < 2)) {
            return array();
        }

        $field = $this->m_exclusionField;
        $table = "#__sobipro_field_data";
        $text  = "baseData";

        if ($field < 0) {
            $field = $field * -1;
            $table = "#__sobipro_field_option_selected";
            $text  = "optValue";
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('DISTINCT sid')
                ->from($db->quoteName($table))
                ->where($db->quoteName('fid') . ' = ' . (int)$field);
                
            // Aggiungi la condizione di filtro in modo sicuro
            $query->where($db->quoteName($text) . $this->m_exclusionFilter);
                
            if (count($result) > 0) {
                $query->where($db->quoteName('sid') . ' IN (' . implode(',', $result) . ')');
            }
            
            $vIds = $db->setQuery($query)->loadColumn();
            
            if ($this->m_useNotGeocode > 0 && is_array($vIds) && count($vIds)) {
                $withoutCoord = array_intersect($withoutCoord, $vIds);
            }
            
            return $vIds;
        } catch (\Exception $e) {
            Sobi::Error('mjradius', "Radius search plugin query error: " . $e->getMessage(), SPC::WARNING, 0, __LINE__, __CLASS__);
            return array();
        }
    }

    /**
     * Filtra i risultati in base al raggio di ricerca
     *
     * @param   array  $result  Risultati di ricerca
     *
     * @return  array|null  Array con risultati nel raggio e distanza
     *
     * @since   1.0.0
     */
    private function filterRadiusAfterExtendedSearch($result)
    {
        $session = Factory::getApplication()->getSession();
        $keepSess = SPRequest::string('kss', 0);

        if ($keepSess == 0) {
            $session->clear('mj_rs_ref_lat');
            $session->clear('mj_rs_ref_lng');
            $session->clear('mj_rs_ref_dist');

            $dist    = SPRequest::string('mj_rs_radius_selector', 10);
            $ref_lat = SPRequest::string('mj_rs_ref_lat', null);
            $ref_lng = SPRequest::string('mj_rs_ref_lng', null);
            $ref_loc = SPRequest::string('mj_rs_center_selector', null);
        } else {
            $ref_lat = $session->get('mj_rs_ref_lat', null);
            $ref_lng = $session->get('mj_rs_ref_lng', null);
            $ref_loc = $session->get('mj_rs_center_selector', null);
            $dist    = $session->get('mj_rs_ref_dist', 10);
        }

        if ((!$ref_loc) && (!$ref_lat) && (!$ref_lng)) {
            $dist    = SPRequest::string('mj_rs_mod_radius_selector', 10);
            $ref_lat = SPRequest::double('mj_rs_mod_ref_lat', null);
            $ref_lng = SPRequest::double('mj_rs_mod_ref_lng', null);
            $ref_loc = SPRequest::string('mj_rs_mod_center_selector', null);
        }

        $dist = $dist * 1;
        if (strlen($ref_loc) < 2) {
            return;
        }
        if ((strlen($ref_lat) < 1) || (strlen($ref_lng) < 1)) {
            return;
        }

        $session->set('mj_rs_ref_lat', $ref_lat);
        $session->set('mj_rs_ref_lng', $ref_lng);
        $session->set('mj_rs_ref_dist', $dist);
        $session->set('mj_rs_center_selector', $ref_loc);

        $km      = $this->_getKm();
        $section = Sobi::Section();

        $carre = $this->_getCarre($ref_lat, $ref_lng, $km, ($dist * 1.05));

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            
            // Prima query per ottenere gli ID nel raggio
            $query = $db->getQuery(true)
                ->select('DISTINCT O.id, G.latitude, G.longitude')
                ->from($db->quoteName('#__sobipro_object', 'O'))
                ->leftJoin(
                    $db->quoteName('#__sobipro_field_geo', 'G') 
                    . ' ON G.sid = O.id'
                )
                ->where([
                    $db->quoteName('O.oType') . ' = ' . $db->quote('entry'),
                    $db->quoteName('state') . ' = 1',
                    $db->quoteName('G.section') . ' = ' . (int)$section,
                    $db->quoteName('G.latitude') . ' BETWEEN ' . $carre[0] . ' AND ' . $carre[2],
                    $db->quoteName('G.longitude') . ' BETWEEN ' . $carre[1] . ' AND ' . $carre[3],
                    '(O.validUntil > NOW() OR O.validUntil IN(' . $db->quote('0000-00-00 00:00:00') . ',' . $db->quote('1970-01-01 01:00:00') . '))',
                    '(O.validSince < NOW() OR O.validSince IN(' . $db->quote('0000-00-00 00:00:00') . ',' . $db->quote('1970-01-01 01:00:00') . '))'
                ]);
            
            $in = $db->setQuery($query)->loadColumn();
            
            if (!count($in)) {
                $in = array(-1);
            }
            
            // Calcolo del parametro per la salesArea
            $minus = ($this->m_salesAreaDist < 1)
                ? " 0 as salesArea "
                : " (IF(((LENGTH(SALES.baseData)>0) AND (SALES.baseData>0)),(SALES.baseData ),0) ) as salesArea ";
                
            $join = ($this->m_salesAreaDist < 1)
                ? " "
                : " LEFT JOIN " . $db->quoteName('#__sobipro_field_data', 'SALES') 
                  . " ON (SALES.sid = O.id AND SALES.fid = " . (int)$this->m_salesAreaDist . ") ";

            $spGeo = ($this->m_spGeoFieldId < 1) ? " " : " AND GEO.fid=" . (int)$this->m_spGeoFieldId;
            
            // Seconda query per ottenere le distanze calcolate
            $query = $db->getQuery(true)
                ->select([
                    'DISTINCT O.id',
                    'GEO.latitude',
                    'GEO.longitude',
                    "({$km}*acos(cos(radians({$ref_lat}))*cos(radians(GEO.latitude))
                     *cos(radians(GEO.longitude)-radians({$ref_lng}))
                     +sin(radians({$ref_lat}))*sin(radians(GEO.latitude)))) AS distance",
                    $minus
                ])
                ->from($db->quoteName('#__sobipro_object', 'O'))
                ->join('LEFT', $db->quoteName('#__sobipro_field_geo', 'GEO') . ' ON GEO.sid = O.id' . $spGeo)
                ->where([
                    $db->quoteName('state') . ' = 1',
                    $db->quoteName('O.id') . ' IN (' . implode(',', $in) . ')',
                    $db->quoteName('GEO.latitude') . ' IS NOT NULL',
                    $db->quoteName('GEO.longitude') . ' IS NOT NULL'
                ]);
                
            if (!empty($join)) {
                $query->join('LEFT', $join);
            }
            
            if (count($result) > 0) {
                $query->where($db->quoteName('O.id') . ' IN (' . implode(',', $result) . ')');
            }
            
            if ($this->m_orderresult) {
                $query->order('distance');
            }
            
            $inRadius = $db->setQuery($query)->loadObjectList();
            
            return array($inRadius, $dist);
        } catch (\Exception $e) {
            Sobi::Error('mjradius', "Radius search plugin query error: " . $e->getMessage(), SPC::WARNING, 0, __LINE__, __CLASS__);
            return null;
        }
    }

    /**
     * Calcola le coordinate di un quadrato intorno al punto centrale
     *
     * @param   float   $lat   Latitudine del centro
     * @param   float   $lng   Longitudine del centro
     * @param   float   $u     Unità di misura (km/mi)
     * @param   float   $d     Distanza in unità
     *
     * @return  array   Coordinate del quadrato [lat_min, lng_min, lat_max, lng_max]
     *
     * @since   1.0.0
     */
    protected function _getCarre($lat, $lng, $u, $d)
    {
        $a = 0; // Cardinal top
        $lat_H = rad2deg(asin(sin(deg2rad($lat)) * cos($d / $u)
                + cos(deg2rad($lat)) * sin($d / $u) * cos(deg2rad($a))));
                
        $a = 180; // Bottom
        $lat_B = rad2deg(asin(sin(deg2rad($lat)) * cos($d / $u)
                + cos(deg2rad($lat)) * sin($d / $u) * cos(deg2rad($a))));
                
        $a = 270; // Left
        $nlat = rad2deg(asin(sin(deg2rad($lat)) * cos($d / $u)
                + cos(deg2rad($lat)) * sin($d / $u) * cos(deg2rad($a))));
                
        $lng_G = rad2deg(deg2rad($lng)
                + atan2(sin(deg2rad($a)) * sin($d / $u) * cos(deg2rad($lat)),
                       cos($d / $u) - sin(deg2rad($lat)) * sin(deg2rad($nlat))));
                       
        $a = 90; // Right
        $nlat = rad2deg(asin(sin(deg2rad($lat)) * cos($d / $u)
                + cos(deg2rad($lat)) * sin($d / $u) * cos(deg2rad($a))));
                
        $lng_D = rad2deg(deg2rad($lng)
                + atan2(sin(deg2rad($a)) * sin($d / $u) * cos(deg2rad($lat)),
                       cos($d / $u) - sin(deg2rad($lat)) * sin(deg2rad($nlat))));
                       
        return array($lat_B, $lng_G, $lat_H, $lng_D);
    }

    /**
     * Gestisce la visualizzazione dell'entry nella lista risultati
     *
     * @param   array  &$data  Dati dell'entry
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function ListEntry(&$data)
    {
        if (!$this->m_enabled) {
            return;
        }
        
        $task = SPRequest::string('task', null);
        if (strtolower($task) != "search.results") {
            return;
        }

        $session = Factory::getApplication()->getSession();
        $ref_lat = $session->get('mj_rs_ref_lat', null);
        $ref_lng = $session->get('mj_rs_ref_lng', null);
        $ref_loc = $session->get('mj_rs_center_selector', null);
        $ref_dis = $session->get('mj_rs_ref_dist', null);
        
        if (!$ref_lat || !$ref_lng) {
            return;
        }

        $km  = $this->_getKm();
        $id  = $data['id'];
        
        try {
            $spGeo = ($this->m_spGeoFieldId < 1) ? " " : " AND GEO.fid=" . (int)$this->m_spGeoFieldId;
            
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select("({$km}*acos(cos(radians({$ref_lat}))*cos(radians(GEO.latitude))
                        *cos(radians(GEO.longitude)-radians({$ref_lng}))
                        +sin(radians({$ref_lat}))*sin(radians(GEO.latitude)))) AS distance")
                ->from($db->quoteName('#__sobipro_object', 'O'))
                ->leftJoin(
                    $db->quoteName('#__sobipro_field_geo', 'GEO') 
                    . ' ON GEO.sid = O.id' . $spGeo
                )
                ->where([
                    $db->quoteName('O.oType') . ' = ' . $db->quote('entry'),
                    $db->quoteName('O.id') . ' = ' . (int)$id
                ])
                ->order('distance ASC')
                ->setLimit(1);
                
            $distance = $db->setQuery($query)->loadResult();
        } catch (\Exception $e) {
            Sobi::Error('mjradius', "Radius search plugin query error: " . $e->getMessage(), SPC::WARNING, 0, __LINE__, __CLASS__);
            return;
        }

        SPLang::load('SpApp.mjradius');
        $unit = $this->_getUnit();
        $unitAff = (count($unit) == 1)
            ? $unit[0]
            : (($distance < 1) ? $unit[1] : $unit[0]);

        $sales = ($distance > $ref_dis) ? true : false;
        $diff  = ($sales) ? $this->_getHumanNumber($distance - $ref_dis) : 0;
        $distance = $this->_getHumanNumber($distance);
        $ref_dis  = $this->_getHumanNumber($ref_dis);

        // Se m_salesAreaVcMode=1 e sale => forziamo la distanza
        if (($this->m_salesAreaVcMode == 1) && $sales) {
            $distance = $ref_dis;
        }
        // Se m_salesAreaVcMode=2 => ref_dis + la differenza
        if (($this->m_salesAreaVcMode == 2) && $sales) {
            $distance = $ref_dis . "+" . $diff;
        }

        $result = (strlen($this->m_custDistText) > 2)
            ? sprintf($this->m_custDistText, $distance)
            : sprintf(Sobi::Txt('MJRS.DISTANCE'), $distance . ' ' . $unitAff);

        $data['mjradius'] = $result;
    }

    /**
     * Aggiunge il link di amministrazione
     *
     * @param   array  &$links  Collegamenti amministrazione
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function admMenu(&$links)
    {
        SPLang::load('SpApp.mjradius');
        $links['Radius Search - myJoom'] = 'mjradius';
    }

    /**
     * Ottiene il fattore di conversione per le unità
     *
     * @return  float  Fattore di conversione
     *
     * @since   1.0.0
     */
    private function _getKm()
    {
        if ($this->m_unit == 2) {
            return 3959; // Miles
        } elseif ($this->m_unit == 3) {
            return 3440; // Miles nautici
        }
        
        return 6371;   // Default km
    }

    /**
     * Ottiene le unità di misura
     *
     * @return  array  Unità di misura
     *
     * @since   1.0.0
     */
    private function _getUnit()
    {
        SPLang::load('SpApp.mjradius');
        
        if ($this->m_unit == 2) {
            return array(Sobi::Txt('MJRS.UNIT_MILE'));
        } elseif ($this->m_unit == 3) {
            return array(Sobi::Txt('MJRS.UNIT_NAUTIC_MILE'));
        } elseif ($this->m_unit == 4) {
            return array(Sobi::Txt('MJRS.UNIT_KILOMETER'), Sobi::Txt('MJRS.UNIT_METER'));
        }
        
        return array(Sobi::Txt('MJRS.UNIT_KILOMETER'));
    }

    /**
     * Formatta un numero secondo le impostazioni
     *
     * @param   float  $distance  Valore da formattare
     *
     * @return  string  Valore formattato
     *
     * @since   1.0.0
     */
    private function _getHumanNumber($distance)
    {
        // Se unit=4 e distance<1 => calcoliamo in metri
        if (($this->m_unit == 4) && ($distance < 1)) {
            return number_format($distance, 3) * 1000; // Metri
        }

        $vir = ($this->m_radvir == 0) ? '.' : ',';
        $mil = '';
        
        if ($this->m_radmil == 1) { 
            $mil = ' '; 
        } elseif ($this->m_radmil == 2) { 
            $mil = '.'; 
        } elseif ($this->m_radmil == 3) { 
            $mil = "'"; 
        } elseif ($this->m_radmil == 4) { 
            $mil = ','; 
        }

        return number_format($distance, $this->m_raddec, $vir, $mil);
    }

    /**
     * Imposta gli script JavaScript necessari
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function _setJs()
    {
        $exclGeoShort = (substr($this->m_exclusionValueGoogle, 0, 1) == "-") ? 0 : 1;
        $exclGeoValue = ($exclGeoShort == 0)
            ? substr($this->m_exclusionValueGoogle, 1)
            : $this->m_exclusionValueGoogle;

        $js = '';
        $js .= 'function getGeoExcl(arComp){';
        $js .= ' var res="";';

        if (strlen($exclGeoValue) > 3) {
            $js .= ' jQuery.each(arComp,function(i,adComp){';
            $js .= '     if(adComp.types[0]=="' . $exclGeoValue . '"){';
            
            if ($exclGeoShort == 1) {
                $js .= ' res=adComp.short_name;';
            } else {
                $js .= ' res=adComp.long_name;';
            }
            
            $js .= '     return;';
            $js .= '    }';
            $js .= ' });';
        }
        
        $js .= ' return res;}';

        // Minimizza un po'
        $js = str_replace(array("\n", "\t", "  "), '', $js);
        Factory::getDocument()->addScriptDeclaration($js);

        $bound     = '';
        $param     = '';
        $container = '';
        SPLang::load('SpApp.mjradius');

        if ((strlen($this->m_restricpt1) > 2) && (strlen($this->m_restricpt2) > 2)) {
            $bound = "var restricted=new google.maps.LatLngBounds(
                new google.maps.LatLng({$this->m_restricpt1}),
                new google.maps.LatLng({$this->m_restricpt2})
            );";
            $param = ",bounds:restricted";
        }

        $country = '';
        if (strlen($this->m_acCountry) == 2) {
            $country = ",componentRestrictions:{country:'{$this->m_acCountry}'} ";
        }

        if (strlen($this->m_mapVariable) > 2) {
            $container = 'if(' . $this->m_mapVariable . '){
                ac.bindTo("bounds",' . $this->m_mapVariable . ');
            } else {
                alert("there is no map named ' . $this->m_mapVariable . '!");
            }';
        }

        // Se geocodeMode !=1 => init autocomplete
        $js = '';
        if ((int)$this->m_geocodeMode != 1) {
            $js = 'function initRSA(){' . $bound . '
                var input=document.getElementById("mj_rs_center_selector");
                var options={types:' . $this->m_acTypes . $param . $country . '};
                var ac=new google.maps.places.Autocomplete(input,options);
                ' . $container . '
                google.maps.event.addListener(ac,"place_changed",function(){
                    var pl=ac.getPlace();
                    jQuery("#mj_rs_ref_lat").val(pl.geometry.location.lat());
                    jQuery("#mj_rs_ref_lng").val(pl.geometry.location.lng());
                    jQuery("#mj_rs_ref_excl").val(getGeoExcl(pl.address_components));
                });
            }
            google.maps.event.addDomListener(window,"load",initRSA);';
            
            $js = str_replace(array("\n", "\t", "  "), '', $js);
            Factory::getDocument()->addScriptDeclaration($js);
        }

        if ($this->m_geocodeMode > 0) {
            if (strlen($this->m_acCountry) == 2) {
                $country = ",region:'{$this->m_acCountry}'";
            }
            
            $js = '
            jQuery(document).ready(function(){
                jQuery("#mj_rs_center_selector").blur(function(){
                    _manGeocode();
                });
            });
            function _manGeocode(){
                jQuery("#top_button").fadeOut("fast");
                var entry=jQuery("#mj_rs_center_selector").val();
                if(entry.length<3){return;}
                geocoder=new google.maps.Geocoder();
                geocoder.geocode({address:entry' . $country . '},function(results,status){
                    if(status==google.maps.GeocoderStatus.OK){
                        jQuery("#mj_rs_ref_lat").val(results[0].geometry.location.lat());
                        jQuery("#mj_rs_ref_lng").val(results[0].geometry.location.lng());
                        jQuery("#mj_rs_ref_excl").val(getGeoExcl(results[0].address_components));
                        jQuery("#mj_rs_center_selector").val(results[0].formatted_address);
                        jQuery("#top_button").fadeIn("fast");
                        return true;
                    } else {
                        jQuery("#mj_rs_center_selector").val("' . Sobi::Txt('MJRS.GEOCODE_NOT_FOLLOWING_REASON') . ' "+status);
                        jQuery("#top_button").fadeIn("fast");
                        return false;
                    }
                });
            };';
            
            $js = str_replace(array("\n", "\t", "  "), '', $js);
            Factory::getDocument()->addScriptDeclaration($js);
        }

        if (($this->m_locateStart) || ($this->m_uselocateme)) {
            $js = 'function userPos(){
                var gc=new google.maps.Geocoder();
                if(navigator.geolocation){
                    navigator.geolocation.getCurrentPosition(function(po){
                        gc.geocode({"latLng":new google.maps.LatLng(po.coords.latitude,po.coords.longitude)},function(results,status){
                            if(status==google.maps.GeocoderStatus.OK){
                                jQuery("#mj_rs_ref_lat").val(po.coords.latitude);
                                jQuery("#mj_rs_ref_lng").val(po.coords.longitude);
                                jQuery("#mj_rs_center_selector").val(results[0]["formatted_address"]);
                                jQuery("#mj_rs_ref_excl").val(getGeoExcl(results[0].address_components));
                            } else {
                                alert("' . Sobi::Txt('MJRS.GEOCODE_NOT_FOLLOWING_REASON') . ' "+status);
                            }
                        });
                    });
                }
                else{
                    alert("' . Sobi::Txt('MJRS.ALLOW_GEOCODE') . '");
                }
            };';
            
            $js = str_replace(array("\n", "\t", "  "), '', $js);
            Factory::getDocument()->addScriptDeclaration($js);
        }

        $session = Factory::getApplication()->getSession();
        $ref_loc = $session->get('mj_rs_center_selector', $this->m_defaultcenter);

        if ((strlen($this->m_defaultcenter) > 0)
            && ((strcasecmp($ref_loc, $this->m_defaultcenter) == 0) || (strlen($ref_loc) < 1))
            && ($this->m_geocodeMode > 0)) {
            Factory::getDocument()->addScriptDeclaration('
                jQuery(document).ready(function(){
                    jQuery("#mj_rs_center_selector").val("' . $this->m_defaultcenter . '");
                });
            ');
        }

        if ($this->m_locateStart) {
            Factory::getDocument()->addScriptDeclaration('
                jQuery(document).ready(function(){
                    if(jQuery("#mj_rs_center_selector").val().length<3){
                        userPos();
                    }
                });
            ');
        }
    }
}