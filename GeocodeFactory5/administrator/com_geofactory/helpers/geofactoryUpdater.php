<?php
/**
 * @name        Geocode Factory Updater
 * @package     geoFactory
 * @copyright   Copyright © 2013
 * @license     GNU General Public License version 2 or later
 * @author      ...
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseFactory;
use Joomla\CMS\Filesystem\File;
use RuntimeException;

// Se necessario, altri “use” (ad esempio, File, ecc.)

require_once JPATH_SITE . '/components/com_geofactory/helpers/geofactoryPlugin.php';

/**
 * Classe helper che gestisce gli aggiornamenti (update) per Geocode Factory
 */
class GeofactoryHelperUpdater
{
    /**
     * Ritorna link HTML sulle estensioni GF con update disponibile
     */
    public static function getUpdatesList()
    {
        // Cerca tutte le extension che hanno "geo" nel nome
        $vExts = self::getInstalledGfExt();

        $isPackage = self::ifInstalledGfPakage($vExts);

        // Cerca eventuali aggiornamenti
        $vUpd = self::getUpdatesExt();

        // Elenco di extension “geofactory” (core, plugin, moduli, etc.)
        $vAll = self::getComponentCatalog($isPackage);

        // Verifica subscription
        $subs = self::getUserSubs();

        $vLinks = [];
        foreach ($vAll as $ext => $name) {
            $dummy = new \stdClass();
            $dummy->file  = $ext;
            $dummy->name  = $name;
            $dummy->tag   = '';
            $dummy->link  = '';
            $dummy->alert = '';

            // Se non è installata, skip
            if (!self::isInstalled($vExts, $dummy)) {
                continue;
            }

            // Aggiunge info su eventuale update
            self::addExtUpdate($dummy, $vUpd, $subs);
            $vLinks[] = self::buildLink($dummy);
        }

        return $vLinks;
    }

    /**
     * Costruisce il link HTML con eventuale tag e alert
     */
    protected static function buildLink($dummy)
    {
        $link = $dummy->name;
        if (strlen($dummy->tag) > 0 && strlen($dummy->link) > 0) {
            $button = (strlen($dummy->tag) > 0)
                ? '<span class="label label-info">' . $dummy->tag . '</span>'
                : '';
            $option = (strlen($dummy->alert) > 0)
                ? 'onclick="alert(\'' . $dummy->alert . '\');return false;" '
                : 'class="modal"';
            $link = $link . '<a style="float:right;" href="' . $dummy->link . '" ' . $option . '>' . $button . '</a>';
        }
        return $link;
    }

    /**
     * Restituisce la lista "element" per cui sono disponibili aggiornamenti
     */
    protected static function getUpdatesExt()
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select('element')
            ->from('#__updates')
            ->where('extension_id != 0');

        $db->setQuery($query);
        $updates = $db->loadObjectList();

        if (!is_array($updates) || !count($updates)) {
            return [''];
        }

        $res = [];
        foreach ($updates as $upd) {
            $res[] = strtolower($upd->element);
        }

        return $res;
    }

    /**
     * Mappa delle estensioni “GeocodeFactory” note
     */
    protected static function getComponentCatalog($isPackage)
    {
        // Restituisce l’array fisso
        return [
            'com_geofactory'        => 'Geocode Factory core',
            'mod_geofactory_map'    => 'Module - show map',
            'mod_geofactory_search' => 'Module - search map',

            'plg_geofactory_load_map'     => 'Plugin - Load map in articles',
            'plg_geofactory_content_jc30' => 'Plugin - Joomla Content',
            'plg_geofactory_btn_jc30'     => 'Plugin - Joomla Content Button',
            'plg_geofactory_gw_jc30'      => 'Gateway - Joomla Contents',

            'plg_geofactory_gw_adsm'      => 'Gateway - Ads Manager',
            'plg_geofactory_gw_cb19'      => 'Gateway - Community Builder',
            'plg_geofactory_gw_f2c'       => 'Gateway - Form2content',
            'plg_geofactory_gw_js30'      => 'Gateway - Jomsocial',
            'plg_geofactory_gw_je30'      => 'Gateway - Jomsocial Events',
            'plg_geofactory_gw_mt30'      => 'Gateway - Mosets Tree',
            'plg_geofactory_gw_k2'        => 'Gateway - K2',
            'plg_geofactory_gw_sbl'       => 'Gateway - Seblod',
            'plg_geofactory_gw_sp10'      => 'Gateway - Sobipro',
            'plg_geofactory_levels'       => 'Plugin - Levels',

            'plg_geofactory_profile_js30' => 'Profile plugin - Jomsocial',
            'plg_geofactory_profile_cb19' => 'Profile plugin - Community Builder',
        ];
    }

    /**
     * Se c’è un update per $dummy->file, imposta $dummy->tag / link / alert
     */
    protected static function addExtUpdate(&$dummy, $vUpd, $subs)
    {
        if (!in_array($dummy->file, $vUpd)) {
            return;
        }

        $dummy->tag = 'Update';

        switch ($dummy->file) {
            case 'com_geofactory':
                $dummy->link = 'index.php?option=com_geofactory&view=accueil&task=accueil.update&file=geofactory.zip&free=1';
                break;
            case 'mod_geofactory_map':
                $dummy->link = 'index.php?option=com_geofactory&view=accueil&task=accueil.update&file=mod_geofactory_map.zip&free=1';
                break;
            case 'plg_geofactory_content_jc30':
                $dummy->link = 'index.php?option=com_geofactory&view=accueil&task=accueil.update&file=plg_geofactory_content_jc30.zip&free=1';
                break;
            case 'plg_geofactory_gw_jc30':
                $dummy->link = 'index.php?option=com_geofactory&view=accueil&task=accueil.update&file=plg_geofactory_gw_jc30.zip&free=1';
                break;
            case 'plg_geofactory_btn_jc30':
                $dummy->link = 'index.php?option=com_geofactory&view=accueil&task=accueil.update&file=plg_geofactory_btn_jc30.zip&free=1';
                break;
            case 'plg_geofactory_profile_cb19':
                $dummy->tag   = 'Manual';
                $dummy->alert = 'CB do not allow automatic plugin update.';
                break;
            // Altri plugin/gateway commerciali
            case 'plg_geofactory_gw_js30':
            case 'plg_geofactory_gw_cb19':
            case 'plg_geofactory_gw_mt30':
            case 'plg_geofactory_gw_adsm':
            case 'plg_geofactory_gw_k2':
            case 'plg_geofactory_gw_f2c':
            case 'plg_geofactory_gw_sp10':
            case 'plg_geofactory_gw_sbl':
            case 'plg_geofactory_profile_js30':
            case 'plg_geofactory_levels':
                if ($subs === 'ok') {
                    $file = $dummy->file;
                    if ($file === 'plg_geofactory_gw_cb19') {
                        $file = 'plg_geofactory_gw_cb';
                    }
                    $dummy->link = 'index.php?option=com_geofactory&view=accueil&task=accueil.update&file=' . $file . '.zip';
                } else {
                    $dummy->alert = $subs;
                    $dummy->link  = '#';
                }
                break;
        }
    }

    /**
     * Verifica se l’estensione è installata
     */
    protected static function isInstalled($extDb, &$dummy)
    {
        foreach ($extDb as $row) {
            if (strcasecmp($row->element, $dummy->file) === 0) {
                // Se è un plugin, aggiungi link alle impostazioni
                if (substr($dummy->file, 0, 4) === 'plg_') {
                    $settings = '<a href="index.php?option=com_plugins&task=plugin.edit&extension_id='
                        . $row->extension_id . '" target="_blank"><i class="icon-cog"></i></a> ';
                    $dummy->name = $settings . $dummy->name;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Se esiste un pkg_geofactory
     */
    protected static function ifInstalledGfPakage($extDb)
    {
        foreach ($extDb as $row) {
            if (strcasecmp($row->element, 'pkg_geofactory') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ritorna la lista di extension (che contengono geo...) effettivamente installate
     */
    protected static function getInstalledGfExt()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__extensions')
            ->where('name LIKE ' . $db->quote('%geo%'));
        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Verifica la validità della subscription dell’utente
     */
    protected static function getUserSubs()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $subsEnd = trim($config->get('subsEnd'));

        if (strlen($subsEnd) < 9) {
            return Text::_('COM_GEOFACTORY_ENTER_YOUR_SUBSCRIPTION_IN_SETTINGS');
        }
        // Se volessi validare la data, potresti effettuare ulteriori controlli qui.
        return 'ok';
    }

    /**
     * Carica un DB esterno, se definito
     */
    public static function loadExternalDb()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $option = [];
        $option['driver']   = $config->get('import-driver');
        $option['host']     = $config->get('import-host');
        $option['user']     = $config->get('import-user');
        $option['password'] = $config->get('import-password');
        $option['database'] = $config->get('import-database');
        $option['prefix']   = $config->get('import-prefix');

        // In Joomla 4 preferiamo:
        $db = DatabaseFactory::getInstance($option);
        return $db;
    }
}
