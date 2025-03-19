<?php
/**
 * @name		Radius Search Application
 * @package		mjradius
 * @copyright	Copyright © 2011 - All rights reserved.
 * @license		GNU/GPL
 * @author		Cédric Pelloquin
 * @author mail	info@myJoom.com
 * @website		www.myJoom.com
 */
defined( 'SOBIPRO' ) || exit( 'Restricted access' );
SPLoader::loadController( 'config', true );

class SPMJRadiusCtrl extends SPConfigAdmCtrl{
	protected $_type = 'mjradius';
	protected $_defTask = 'config';

	public function execute(){
		$this->_task = strlen( $this->_task ) ? $this->_task : $this->_defTask;
		SPLang::load( 'SpApp.mjradius' );
		switch ( $this->_task ) {
			case 'config':
				$this->screen();
				Sobi::ReturnPoint();
				break;
			case 'save':
				$this->save();
				break;
			default:
				Sobi::Error( 'MJRadiusCtrl', 'Task not found', SPC::WARNING, 404, __LINE__, __FILE__ );
				break;
		}
	}

	private function screen(){
		SPFactory::registry()->loadDBSection( 'mjradius' );
		$view = $this->getView( 'mjradius' );
		if( SPFs::exists( implode( DS, array( SOBI_PATH, 'opt', 'plugins', 'mjradius', 'description_'.Sobi::Lang().'.html' ) ) ) ) {
			$c = SPFs::read( implode( DS, array( SOBI_PATH, 'opt', 'plugins', 'mjradius', 'description_'.Sobi::Lang().'.html' ) ) );
		}
		else {
			$c = SPFs::read( implode( DS, array( SOBI_PATH, 'opt', 'plugins', 'mjradius', 'description_en-GB.html' ) ) );
		}
		$view->assign( $c, 'description' );


		$setting = Sobi::Reg('mjradius.settings.params');
		if (strlen($setting))
			$setting = SPConfig::unserialize($setting);

		if (is_array($setting) && isset($setting[Sobi::Section()])){
			$setting = $setting[Sobi::Section()] ;

			// ancienne methode
			$view->assign( $setting['m_enabled']			, 'm_enabled');
			$view->assign( $setting['m_unit'] 				, 'm_unit');
			$view->assign( $setting['m_distances']			, 'm_distances');
			$view->assign( $setting['m_uselocateme'] 		, 'm_uselocateme');
			$view->assign( $setting['m_googleicon'] 		, 'm_googleicon');
			$view->assign( $setting['m_orderresult'] 		, 'm_orderresult');
			$view->assign( $setting['m_label'] 				, 'm_label');
			$view->assign( $setting['m_mjrslic'] 			, 'm_mjrslic');
			$view->assign( $setting['m_raddec'] 			, 'm_raddec');
			$view->assign( $setting['m_radmil'] 			, 'm_radmil');
			$view->assign( $setting['m_radvir'] 			, 'm_radvir');
			$view->assign( $setting['m_restricpt1'] 		, 'm_restricpt1');
			$view->assign( $setting['m_restricpt2'] 		, 'm_restricpt2');
			$view->assign( $setting['m_inputText'] 			, 'm_inputText');
			$view->assign( $setting['m_geocodeMode'] 		, 'm_geocodeMode');
			$view->assign( $setting['m_acTypes'] 			, 'm_acTypes');
			$view->assign( $setting['m_acCountry'] 			, 'm_acCountry');
			$view->assign( $setting['m_mapVariable'] 		, 'm_mapVariable');
			$view->assign( $setting['m_locateStart'] 		, 'm_locateStart');
			$view->assign( $setting['m_inputwidth']			, 'm_inputwidth');
			$view->assign( $setting['m_custDistText']		, 'm_custDistText');
			$view->assign( $setting['m_defaultcenter']		, 'm_defaultcenter');
			$view->assign( $setting['m_spGeoFieldId']		, 'm_spGeoFieldId');
			$view->assign( $setting['m_salesAreaDist']		, 'm_salesAreaDist');
			$view->assign( $setting['m_salesAreaVcMode']	, 'm_salesAreaVcMode');
			$view->assign( $setting['m_exclusionField']		, 'm_exclusionField');
			$view->assign( $setting['m_exclusionFilter']	, 'm_exclusionFilter');
			$view->assign( $setting['m_exclusionFieldGoogle'], 'm_exclusionFieldGoogle');
			$view->assign( $setting['m_exclusionValueGoogle'], 'm_exclusionValueGoogle');
			$view->assign( $setting['m_useNotGeocode']		, 'm_useNotGeocode');
			$view->assign( $setting['m_hideDistances']		, 'm_hideDistances');
		}else{
			// ancienne methode
			$view->assign( Sobi::Reg('mjradius.m_enabled.value'				), 'm_enabled');
			$view->assign( Sobi::Reg('mjradius.m_unit.value' 				), 'm_unit');
			$view->assign( Sobi::Reg('mjradius.m_distances.value'			), 'm_distances');
			$view->assign( Sobi::Reg('mjradius.m_uselocateme.value' 		), 'm_uselocateme');
			$view->assign( Sobi::Reg('mjradius.m_googleicon.value' 			), 'm_googleicon');
			$view->assign( Sobi::Reg('mjradius.m_orderresult.value' 		), 'm_orderresult');
			$view->assign( Sobi::Reg('mjradius.m_label.value' 				), 'm_label');
			$view->assign( Sobi::Reg('mjradius.m_mjrslic.value' 			), 'm_mjrslic');
			$view->assign( Sobi::Reg('mjradius.m_raddec.value' 				), 'm_raddec');
			$view->assign( Sobi::Reg('mjradius.m_radmil.value' 				), 'm_radmil');
			$view->assign( Sobi::Reg('mjradius.m_radvir.value' 				), 'm_radvir');
			$view->assign( Sobi::Reg('mjradius.m_restricpt1.value' 			), 'm_restricpt1');
			$view->assign( Sobi::Reg('mjradius.m_restricpt2.value' 			), 'm_restricpt2');
			$view->assign( Sobi::Reg('mjradius.m_inputText.value' 			), 'm_inputText');
			$view->assign( Sobi::Reg('mjradius.m_geocodeMode.value' 		), 'm_geocodeMode');
			$view->assign( Sobi::Reg('mjradius.m_acTypes.value' 			), 'm_acTypes');
			$view->assign( Sobi::Reg('mjradius.m_acCountry.value' 			), 'm_acCountry');
			$view->assign( Sobi::Reg('mjradius.m_mapVariable.value' 		), 'm_mapVariable');
			$view->assign( Sobi::Reg('mjradius.m_locateStart.value' 		), 'm_locateStart');
			$view->assign( Sobi::Reg('mjradius.m_inputwidth.value'			), 'm_inputwidth');
			$view->assign( Sobi::Reg('mjradius.m_custDistText.value'		), 'm_custDistText');
			$view->assign( Sobi::Reg('mjradius.m_defaultcenter.value'		), 'm_defaultcenter');
			$view->assign( Sobi::Reg('mjradius.m_spGeoFieldId.value'		), 'm_spGeoFieldId');
			$view->assign( Sobi::Reg('mjradius.m_salesAreaDist.value'		), 'm_salesAreaDist');
			$view->assign( Sobi::Reg('mjradius.m_salesAreaVcMode.value'		), 'm_salesAreaVcMode');
			$view->assign( Sobi::Reg('mjradius.m_exclusionField.value'		), 'm_exclusionField');
			$view->assign( Sobi::Reg('mjradius.m_exclusionFilter.value'		), 'm_exclusionFilter');
			$view->assign( Sobi::Reg('mjradius.m_exclusionFieldGoogle.value'), 'm_exclusionFieldGoogle');
			$view->assign( Sobi::Reg('mjradius.m_exclusionValueGoogle.value'), 'm_exclusionValueGoogle');
			$view->assign( Sobi::Reg('mjradius.m_useNotGeocode.value'		), 'm_useNotGeocode');
			$view->assign( Sobi::Reg('mjradius.m_hideDistances.value'		), 'm_hideDistances');
		}
		$view->loadConfig('extensions.mjradius' );
		$view->setTemplate('extensions.mjradius' );
		$view->display();
	}

	protected function save(){


        SPFactory::registry()->loadDBSection( 'mjradius' );
        $settings = Sobi::Reg( 'mjradius.settings.params' );
        if ( strlen( $settings ) ) {
            $settings = SPConfig::unserialize( $settings );
        }
        $settings[Sobi::Section()]['m_enabled']					= SPRequest::int('m_enabled') ;
        $settings[Sobi::Section()]['m_unit']					= SPRequest::int( 'm_unit');
        $settings[Sobi::Section()]['m_distances']				= SPRequest::string( 'm_distances');
        $settings[Sobi::Section()]['m_googleicon']				= SPRequest::int( 'm_googleicon');
        $settings[Sobi::Section()]['m_orderresult']				= SPRequest::int( 'm_orderresult');
        $settings[Sobi::Section()]['m_mjrslic']					= SPRequest::string( 'm_mjrslic');
        $settings[Sobi::Section()]['m_raddec']					= SPRequest::int( 'm_raddec');
        $settings[Sobi::Section()]['m_radmil']					= SPRequest::int( 'm_radmil');
        $settings[Sobi::Section()]['m_radvir']					= SPRequest::int( 'm_radvir');
        $settings[Sobi::Section()]['m_restricpt1']				= SPRequest::string( 'm_restricpt1');
        $settings[Sobi::Section()]['m_restricpt2']				= SPRequest::string( 'm_restricpt2');
        $settings[Sobi::Section()]['m_label']					= SPRequest::string( 'm_label');
        $settings[Sobi::Section()]['m_uselocateme']				= SPRequest::int( 'm_uselocateme');
        $settings[Sobi::Section()]['m_inputText']				= SPRequest::string( 'm_inputText');
        $settings[Sobi::Section()]['m_geocodeMode']				= SPRequest::int( 'm_geocodeMode');
        $settings[Sobi::Section()]['m_mapVariable']				= SPRequest::string( 'm_mapVariable');
        $settings[Sobi::Section()]['m_acTypes']					= stripslashes(SPRequest::string( 'm_acTypes'));
        $settings[Sobi::Section()]['m_locateStart']				= SPRequest::int( 'm_locateStart');
        $settings[Sobi::Section()]['m_inputwidth']				= SPRequest::int( 'm_inputwidth');
        $settings[Sobi::Section()]['m_spGeoFieldId']			= SPRequest::int( 'm_spGeoFieldId');
        $settings[Sobi::Section()]['m_salesAreaDist']			= SPRequest::int( 'm_salesAreaDist');
        $settings[Sobi::Section()]['m_salesAreaVcMode']			= SPRequest::int( 'm_salesAreaVcMode');
        $settings[Sobi::Section()]['m_defaultcenter']			= SPRequest::string( 'm_defaultcenter');
        $settings[Sobi::Section()]['m_custDistText']			= SPRequest::string( 'm_custDistText');
        $settings[Sobi::Section()]['m_acCountry']				= SPRequest::string( 'm_acCountry');
        $settings[Sobi::Section()]['m_exclusionFieldGoogle']	= SPRequest::int( 'm_exclusionFieldGoogle');
        $settings[Sobi::Section()]['m_exclusionValueGoogle']	= SPRequest::string( 'm_exclusionValueGoogle');
        $settings[Sobi::Section()]['m_exclusionField']			= SPRequest::int( 'm_exclusionField');
        $settings[Sobi::Section()]['m_hideDistances']			= SPRequest::int( 'm_hideDistances');
        $settings[Sobi::Section()]['m_useNotGeocode']			= SPRequest::int( 'm_useNotGeocode');
        $settings[Sobi::Section()]['m_exclusionFilter']			= stripslashes(SPRequest::string( 'm_exclusionFilter'));

        SPFactory::registry()->saveDBSection( array( array( 'key' => 'settings', 'params' => $settings ) ), $this->_type );

		Sobi::Redirect( SPMainFrame::getBack(), Sobi::Txt( 'MSG.ALL_CHANGES_SAVED' ) );



	}
}
?>