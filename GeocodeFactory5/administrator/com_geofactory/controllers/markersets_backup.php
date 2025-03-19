<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Utilities\ArrayHelper;

class GeofactoryControllerMarkersets extends AdminController
{
    protected $text_prefix = 'COM_GEOFACTORY';

    public function getModel($name = 'Markerset', $prefix = 'GeofactoryModel', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function saveOrderAjax()
    {
        // Ottieni input
        $pks   = $this->input->post->get('cid', [], 'array');
        $order = $this->input->post->get('order', [], 'array');

        // Sanitizza
        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        // Ottieni il modello
        $model = $this->getModel();

        // Salva l'ordinamento
        $return = $model->saveorder($pks, $order);

        exit;
    }
}
