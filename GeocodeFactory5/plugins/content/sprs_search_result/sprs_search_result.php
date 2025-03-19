<?php
/**
 * @name        Sobipro Radius Search - Search Results
 * @package     sprs_search_result
 * @copyright   Copyright © 2012
 * @license     GNU/GPL
 * @author      Cédric
 * @author mail info@myJoom.com
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

// In Joomla 4 non serve più jimport(...); utilizziamo i namespaces:
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
// AGGIUNTA per Joomla 4 (router):
use Joomla\CMS\Router\Route;

/**
 * Plugin di contenuto per mostrare link o form di ricerca SobiPro.
 */
class plgContentSprs_search_result extends CMSPlugin
{
    // Per Joomla 4 si preferisce l’uso di "protected" (o "private") per le proprietà
    protected $m_plgCode = 'myjoom_sprs';
    protected $m_regex   = '/{myjoom_sprs\s+(.*?)}/i';

    /**
     * Costruttore compatibile con Joomla 4.
     *
     * @param  object  &$subject  Il subject a cui il plugin si collega.
     * @param  array   $config    La configurazione del plugin.
     */
    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
    }

    /**
     * Evento onContentPrepare: sostituisce i placeholder {myjoom_sprs ...} nel contenuto.
     *
     * @param   string   $context     Il contesto.
     * @param   object   &$article    L'oggetto articolo.
     * @param   object   &$params     I parametri del plugin.
     * @param   int      $limitstart  Il limit start.
     *
     * @return  bool
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
        // Non eseguire durante l'indicizzazione (ad es. per Finder)
        if ($context === 'com_finder.indexer') {
            return true;
        }

        // Se l'articolo non è un oggetto o non contiene un ID, non facciamo nulla
        if (!is_object($article) || !isset($article->id)) {
            return true;
        }

        // Sostituisci i placeholder nel testo
        $article->text = $this->_prepareArticle($article->text, $article->id);

        return true;
    }

    /**
     * (Opzionale) Evento onContentBeforeDisplay: anche se Joomla 4 potrebbe non chiamarlo
     *
     * @param   string   $context
     * @param   object   &$article
     * @param   object   &$params
     * @param   int      $limitstart
     *
     * @return  string
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0)
    {
        // Applichiamo la stessa logica di sostituzione
        $article->text = $this->_prepareArticle($article->text, $article->id);
        return '';
    }

    /**
     * Cerca i placeholder {myjoom_sprs ...} e li sostituisce con un form (o link) generato.
     *
     * @param   string  $text  Il testo dell’articolo.
     * @param   int     $id    L’ID dell’articolo.
     *
     * @return  string
     */
    private function _prepareArticle($text, $id)
    {
        // Se il testo non contiene il tag, ritorna il testo così com'è
        if (strpos($text, $this->m_plgCode) === false) {
            return $text;
        }

        // Se il plugin è disabilitato tramite i parametri (plgDisable = 1), rimuovi i placeholder
        if ($this->params->get('plgDisable', 1)) {
            return preg_replace($this->m_regex, '', $text);
        }

        // Trova tutte le occorrenze dei placeholder
        preg_match_all($this->m_regex, $text, $matches);
        $count = count($matches[0]);

        if ($count) {
            for ($i = 0; $i < $count; $i++) {
                // Esempio: {myjoom_sprs sps:lat:lng:city:10:optionalText}
                $code = str_replace($this->m_plgCode, '', $matches[0][$i]);
                $code = str_replace(['{','}'], '', $code);
                $code = trim($code);

                // Dividiamo il codice in parti: [sps, lat, lng, city, rad, txt]
                $vCode = explode(':', $code);

                // Imposta i valori di default
                $ssp   = -1;  // Sezione SobiPro (default: non valida)
                $lat   = 255;
                $lng   = 255;
                $rad   = 10;
                $place = null;
                $txt   = null;

                if (count($vCode) > 3) {
                    $ssp   = (int)$vCode[0];
                    $lat   = $vCode[1];
                    $lng   = $vCode[2];
                    $place = $vCode[3];
                }
                if (count($vCode) > 4 && (int)$vCode[4] > 0) {
                    $rad = (int)$vCode[4];
                }
                if (count($vCode) > 5) {
                    $txt = $vCode[5];
                }

                // Genera il form/link HTML sostitutivo
                $link = $this->_getLink($i, $ssp, $lat, $lng, $rad, $place, $txt);

                if (!$link) {
                    // Se non sono stati forniti dati validi, rimuovi il placeholder
                    $text = preg_replace('{' . preg_quote($matches[0][$i], '/') . '}', '', $text);
                    continue;
                }

                // Sostituisci il placeholder con il form HTML generato
                $text = preg_replace('{' . preg_quote($matches[0][$i], '/') . '}', $link, $text);
            }
        }
        return $text;
    }

    /**
     * Costruisce il form di ricerca con i parametri nascosti e il pulsante/link.
     *
     * @param   int     $idx    Indice per distinguere i form multipli.
     * @param   int     $ssp    ID della sezione SobiPro.
     * @param   mixed   $lat    Valore latitudine.
     * @param   mixed   $lng    Valore longitudine.
     * @param   int     $rad    Raggio di ricerca.
     * @param   string  $place  Nome della città o altro.
     * @param   string  $txt    Testo opzionale per il pulsante.
     *
     * @return  string|null    Il form HTML oppure null se i dati non sono validi.
     */
    private function _getLink($idx, $ssp, $lat, $lng, $rad, $place, $txt)
    {
        if ($ssp < 1 || ($lat + $lng == 510)) {
            return null;
        }

        $text = $this->_getText($txt, $idx);

        // Ottieni Itemid dal parametro oppure dall'input
        $app = Factory::getApplication();
        $itemid = $this->params->get('itemid') ? $this->params->get('itemid') : $app->input->getInt('Itemid', 0);

        /*
         * MODIFICA PRINCIPALE:
         * Sostituiamo l'action="index.php" con Route::_('index.php')
         * in modo che Joomla possa gestire correttamente l'URL rewriting.
         */
        $form  = '<form action="' . Route::_('index.php') . '" method="post" id="spSearchForm_' . $idx . '" style="display:inline;">';
        $form .= '<input type="hidden" name="option" value="com_sobipro"/>';
        $form .= '<input type="hidden" name="task" value="search.search"/>';
        $form .= '<input type="hidden" name="sid" value="' . (int)$ssp . '"/>';
        $form .= '<input type="hidden" name="Itemid" value="' . (int)$itemid . '"/>';
        $form .= '<input type="hidden" name="mj_rs_mod_ref_lat" id="mj_rs_mod_ref_lat_' . $idx . '" value="' . $lat . '"/>';
        $form .= '<input type="hidden" name="mj_rs_mod_ref_lng" id="mj_rs_mod_ref_lng_' . $idx . '" value="' . $lng . '"/>';
        $form .= '<input type="hidden" name="mj_rs_mod_radius_selector" id="mj_rs_mod_radius_selector_' . $idx . '" value="' . $rad . '"/>';
        $form .= '<input type="hidden" name="mj_rs_mod_center_selector" id="mj_rs_mod_center_selector_' . $idx . '" value="' . htmlspecialchars($place, ENT_QUOTES) . '"/>';
        $form .= $text . '</form>';

        return $form;
    }

    /**
     * Restituisce il link (o pulsante) di testo, a seconda della modalità impostata.
     *
     * @param   string  $txt  Testo opzionale passato nel placeholder.
     * @param   int     $idx  Indice per distinguere form multipli.
     *
     * @return  string  L'HTML del link/pulsante.
     */
    private function _getText($txt, $idx)
    {
        $defTxt  = 'Read more...';
        $custTxt = trim($this->params->get('linkText'));

        if (strlen($custTxt) > 0) {
            $defTxt = $custTxt;
        }
        if (trim($txt) != '') {
            $defTxt = $txt;
        }

        // 0 = link <a>, 1 = pulsante <input>, 2 = template personalizzato
        $type = (int)$this->params->get('linkMode', 0);
        $out  = '<a href="javascript:void(0);" onClick="document.getElementById(\'spSearchForm_' . $idx . '\').submit(); return false;">' . $defTxt . '</a>';

        if ($type === 1) {
            $out = '<input style="display:inline!important;" type="submit" name="submit"
                    id="mod_sprs_search_btn_' . $idx . '"
                    value="' . $defTxt . '"
                    onClick="document.getElementById(\'spSearchForm_' . $idx . '\').submit(); return false;"/>';
        } elseif ($type === 2) {
            $templateLink = $this->params->get('templateLink');
            $templateLink = str_replace('[text]', $defTxt, $templateLink);
            $out = '<div style="cursor:pointer;" onClick="document.getElementById(\'spSearchForm_' . $idx . '\').submit(); return false;">'
                 . $templateLink
                 . '</div>';
        }
        return $out;
    }
}
