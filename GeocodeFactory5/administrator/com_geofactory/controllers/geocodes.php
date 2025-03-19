<?php
/**
 * @name   Geocode Factory - geocodes controller
 * @update Daniele Bellante
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\Utilities\ArrayHelper;

require_once JPATH_COMPONENT . '/helpers/geofactory.php';

class GeofactoryControllerGeocodes extends AdminController
{
    protected $text_prefix = 'COM_GEOFACTORY';

    public function getModel($name = 'Geocodes', $prefix = 'GeofactoryModel', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function geocodeselected()
    {
        $app = Factory::getApplication();
        $ids = $app->input->get('cid', [], 'array');
        ArrayHelper::toInteger($ids);
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        if (!count($ids)) {
            $this->setRedirect(Route::_('index.php?option=com_geofactory&view=geocodes', false), Text::_('COM_GEOFACTORY_GEOCODE_NO_SELECTION'));
            return;
        }
        $view  = $this->getView('Geocodes', 'batch');
        $model = $this->getModel('Geocodes');
        $view->idsToGc = $ids;
        $view->display('batch');
    }

    public function geocodefiltered()
    {
        $view  = $this->getView('Geocodes', 'batch');
        $model = $this->getModel('Geocodes');
        $ids   = $model->getListIdsToGeocode();
        $view->idsToGc = $ids;
        $view->display('batch');
    }

    public function geocodecurrentitem()
    {
        static $log;
        $app    = Factory::getApplication();
        $id     = $app->input->getInt('curId', -1);
        $cur    = $app->input->getInt('cur', -1);
        $max    = $app->input->getInt('total', -1);
        $err    = $app->input->getInt('errors', -1);
        $type   = $app->input->getString('type', '');
        $assign = $app->input->getInt('assign', 0);

        $model   = $this->getModel('Geocodes');
        $vAssign = GeofactoryHelperAdm::getAssignArray($assign);

        $config    = ComponentHelper::getParams('com_geofactory');
        $geocodeLog = (int)$config->get('geocodeLog', 0);

        if ($geocodeLog) {
            if ($log === null) {
                $options = [
                    'format'    => '{DATE}\t{TIME}\t{LEVEL}\t{CLIENTIP}\t{MESSAGE}',
                    'text_file' => 'com_geofactory.geocode.php'
                ];
                Log::addLogger($options, Log::INFO);
                $log = true;
            }
        }

        Log::add("____________________________________");
        Log::add("New entry for -{$type}- : {$id}");

        $adr   = $model->getAdress($id, $type, $vAssign);
        $ggUrl = $model->getGoogleGeocodeQuery($adr);
        Log::add($ggUrl);
        $coor  = $model->geocodeItem($ggUrl);
        $save  = $model->saveCoord($id, $coor, $type, $vAssign);
        $msg   = $model->htmlResult($cur, $max, $adr, $save);
        if (is_array($coor) && count($coor) > 1) {
            $msg .= '#-@' . implode('#-@', $coor);
        }
        echo $msg;
        
        // In Joomla 4, usiamo un metodo più moderno per terminare l'applicazione
        $app->getDocument()->setMimeEncoding('text/html');
        $app->sendHeaders();
        $app->close();
    }

    public function getcurrentitemaddressraw()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('curId', -1);
        $cur = $app->input->getInt('cur', -1);
        $max = $app->input->getInt('total', -1);
        $err = $app->input->getInt('errors', -1);
        $type = $app->input->getString('type', '');
        $assign = $app->input->getInt('assign', 0);
        $model = $this->getModel('Geocodes');
        $vAssign = GeofactoryHelperAdm::getAssignArray($assign);
        $adr = $model->getAdress($id, $type, $vAssign);
        $adr = trim(implode(' ', $adr));
        echo $adr;
        
        // In Joomla 4, usiamo un metodo più moderno per terminare l'applicazione
        $app->getDocument()->setMimeEncoding('text/plain');
        $app->sendHeaders();
        $app->close();
    }

    public function axsavecoord()
    {
        $app = Factory::getApplication();
        $coor = [];
        $id = $app->input->getInt('curId', -1);
        $cur = $app->input->getInt('cur', -1);
        $max = $app->input->getInt('total', -1);
        $err = $app->input->getInt('errors', -1);
        $type = $app->input->getString('type', '');
        $assign = $app->input->getInt('assign', 0);
        $coor[] = $app->input->getFloat('savlat');
        $coor[] = $app->input->getFloat('savlng');
        $coor[] = $app->input->getString('savMsg', '');
        $adresse = $app->input->getString('adresse', '-');
        $adresse = [$adresse];
        $model = $this->getModel('Geocodes');
        $vAssign = GeofactoryHelperAdm::getAssignArray($assign);
        $save = $model->saveCoord($id, $coor, $type, $vAssign);
        $msg = $model->htmlResult($cur, $max, $adresse, $save);
        echo $msg;
        
        // In Joomla 4, usiamo un metodo più moderno per terminare l'applicazione
        $app->getDocument()->setMimeEncoding('text/html');
        $app->sendHeaders();
        $app->close();
    }

    public function geocodeuniqueitem()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('cur', -1);
        $type = $app->input->getString('type', '');
        $assign = $app->input->getInt('assign', 0);
        $model = $this->getModel('Geocodes');
        $vAssign = GeofactoryHelperAdm::getAssignArray($assign);
        $adr = $model->getAdress($id, $type, $vAssign);
        $ggUrl = $model->getGoogleGeocodeQuery($adr);
        $coor = $model->geocodeItem($ggUrl);
        $save = $model->saveCoord($id, $coor, $type, $vAssign);
        $msg = $model->htmlResult($id, 1, $adr, $save, false);
        $config = ComponentHelper::getParams('com_geofactory');
        $ggApikey = (strlen($config->get('ggApikeySt', '')) > 3) ? '&key=' . $config->get('ggApikeySt') : '';
        $http = $config->get('sslSite', '');
        if (empty($http)) {
            $http = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        }
        $img = '<img src="' . $http . 'maps.googleapis.com/maps/api/staticmap?center=' . $coor[0] . ',' . $coor[1] . '&zoom=15&size=200x100&markers=' . $coor[0] . ',' . $coor[1] . $ggApikey . '">';
        echo $img . '<br />' . $msg;
        
        // In Joomla 4, usiamo un metodo più moderno per terminare l'applicazione
        $app->getDocument()->setMimeEncoding('text/html');
        $app->sendHeaders();
        $app->close();
    }

    public function getaddress()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('idCur', -1);
        $type = $app->input->getString('type', '');
        $assignId = $app->input->getInt('assign', 0);
        $gglink = (bool)$app->input->getInt('gglink', 0);
        $model = $this->getModel('Geocodes');
        $vAssign = GeofactoryHelperAdm::getAssignArray($assignId);
        $adr = $model->getAdress($id, $type, $vAssign);
        $ggUrl = '';
        if (count($adr)) {
            $ggUrl = $model->getGoogleGeocodeQuery($adr);
            $ggUrl = ' href="' . $ggUrl . '" ';
        } else {
            $ggUrl = ' onclick="alert(\'' . Text::_('COM_GEOFACTORY_NO_ADDRESS_FOUND') . '\');" ';
        }
        if ($gglink) {
            echo '<br /><a ' . $ggUrl . ' target="_blank">' . Text::_('COM_GEOFACTORY_GEOCODE_ERROR') . '</a>';
        } else {
            echo '<br /><strong>' . Text::_('COM_GEOFACTORY_ADDRESS') . ' : </strong><br />' . implode("<br />", $adr);
        }
        
        // In Joomla 4, usiamo un metodo più moderno per terminare l'applicazione
        $app->getDocument()->setMimeEncoding('text/html');
        $app->sendHeaders();
        $app->close();
    }

    public function deletek2()
    {
        $app = Factory::getApplication();
        $type = $app->input->getString('typeliste', '');
        $assign = $app->input->getInt('assign', 0);
        $model = $this->getModel('Geocodes');
        $model->deleteFromGFTable('com_k2');
        $this->setRedirect(Route::_('index.php?option=com_geofactory&view=geocodes&typeliste=' . $type . '&assign=' . $assign, false));
    }

    public function deletejc()
    {
        $app = Factory::getApplication();
        $type = $app->input->getString('typeliste', '');
        $assign = $app->input->getInt('assign', 0);
        $model = $this->getModel('Geocodes');
        $model->deleteFromGFTable('com_content');
        $this->setRedirect(Route::_('index.php?option=com_geofactory&view=geocodes&typeliste=' . $type . '&assign=' . $assign, false));
    }

    public function deletejct()
    {
        $app = Factory::getApplication();
        $type = $app->input->getString('typeliste', '');
        $assign = $app->input->getInt('assign', 0);
        $model = $this->getModel('Geocodes');
        $model->deleteFromGFTable('com_contact');
        $this->setRedirect(Route::_('index.php?option=com_geofactory&view=geocodes&typeliste=' . $type . '&assign=' . $assign, false));
    }
}