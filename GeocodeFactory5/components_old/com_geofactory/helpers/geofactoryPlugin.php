<?php
/**
 * @name		Geocode Factory
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author		Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website		www.myJoom.com
 */
defined('_JEXEC') or die;

class GeofactoryPluginHelper extends JPlugin{
	/**
	  * Return the plugin info to be displayed in backend listboxes
	  *
	  * This function return a protected variable, if you TPC contains multiples environements
	  * with mutliple settings (sample sobipro/mosets tree can have mutliple directories with 
	  * theire own address fields), you need to add here the aditionals names of theses sub-
	  * directories like return array(array($this->gatewayCode."- id dir", $this->gatewayName." - name of dir")) ;
	  * in all other basic cases (on TPC = on unique settings), simply return $this->vGatewayInfo  
	  *
	  * @since 1.0
	  *
	  * @return array $this->vGatewayInfo 
	  */
	public function getPlgInfo(){
		// --> specific for multidirectories TPC, see function description...
		$this->_mergeInternalDirectories() ;

		// ... in all other case this line is enough
		return $this->vGatewayInfo ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type
	  * @param bool 	$flag
	  */
	public function isProfile($type, &$flag){
		if (!$this->_isInCurrentType($type))
			return ;
		$flag = $this->isProfileCom ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type
	  * @param bool 	$flag
	  */
	public function isEvent($type, &$flag){
		if (!$this->_isInCurrentType($type))
			return ;

		if (isset($this->isEventCom) AND $this->isEventCom==true)
			$flag=true ;
	}

	public function isSpecialMs($type, &$flag){
		if (!$this->_isInCurrentType($type))
			return ;

		if (isset($this->isSpecialMs) AND $this->isSpecialMs==true)
			$flag=true ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 
	  * @param bool 	$flag
	  */
	public function isIconAvatarEntrySupported($type, &$flag){
		if (!$this->_isInCurrentType($type))
			return ;
		$flag = $this->isSupportAvatar ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 
	  * @param bool 	$flag
	  */
	public function isIconCategorySupported($type, &$flag){
		if (!$this->_isInCurrentType($type))
			return ;
		$flag = $this->isSupportCatIcon ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 
	  * @param bool 	$flag
	  */
	public function getIsSingleGpsField($type, &$flag){
		if (!$this->_isInCurrentType($type))
			return ;
		$flag = $this->isSingleGpsField ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 
	  * @param bool 	$flag
	  */
	public function isOnCurContext($type, $ssType, &$isOnCurItem){
		if (!$this->_isInCurrentType($type))	return ;
		if ($this->gatewayCode!=$ssType)		return ;
		$isOnCurItem = true ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 
	  * @param bool 	$flag
	  */
	public function isPluginInstalled($type, &$flag){
		if (!$this->_isInCurrentType($type))	return ;

		$flag= true ;
	}

	/**
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 
	  * @return array
	  */
	public function getListFieldsAssign($type){
		$listFields = array() ;
		if (!$this->_isInCurrentType($type))
			return array($this->gatewayCode, $listFields) ;

		// détermine si j'utilise les champs ou non
		if ($this->custom_latitude) 	$listFields[] = "field_latitude";
		if ($this->custom_longitude) 	$listFields[] = "field_longitude";
		if ($this->custom_street) 		$listFields[] = "field_street";
		if ($this->custom_postal) 		$listFields[] = "field_postal";
		if ($this->custom_city) 		$listFields[] = "field_city";
		if ($this->custom_county) 		$listFields[] = "field_county";
		if ($this->custom_state) 		$listFields[] = "field_state";
		if ($this->custom_country)	 	$listFields[] = "field_country";

		return array($type, $listFields) ;
	}

	/**
	  * COMMON - readonly function
	  *
	  * if your component dont support multi directory, comment all calls to _getSubDirIdFromTypeListe
	  *
	  * @since 1.0
	  *
	  * @param string 	MS_SP-13
	  * @return int 	sub directory id (13)
	  */
	protected function _getSubDirIdFromTypeListe($typeList) {
		if (! is_string($typeList))
			return -1;

		$v = explode('-', $typeList) ;
		if (count($v)<2)
			return -1 ;

		return $v[1];
	}

	/**
	  * Method to dertermine if the short name from url is usable by this plugin 
	  * ususaly this is used by js to determine if is in a category for the current plugin extenstion
	  * if cb, then the cb plugin should retrun true, if sp then the SP 1.0 and 1.1 plugins should retrun true
	  *
	  * COMMON - read only function
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 		internal use
	  * @param string 	$ext		current ext name
	  * @param bool 	$ret		allowed or not
	  */
	public function isMyShortName($type, $ext, &$ret){
		if (!$this->_isInCurrentType($type))
			return ;

		if (!$this->_isInCurrentType($ext))
			return ;

		$ret = true ;
	}

	/**
	  * INTERNAL 
	  * permet de savoir si la fonction du plugin est executé pour le bon composant (S2, SP, ...)
	  * astuce pour ne remplir que les champs du type en cours, avec une liste de plugins non définis
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 		internal use
	  * @param array 	$codes		code of the application
	  * @param array   	$resCode 	contains the list of available codes
	  */
	protected function _isInCurrentType($type){
		$this->_mergeInternalDirectories() ;
		// pour chaque section du courant je controle si c'est celui ci
		foreach($this->vGatewayInfo as $gi){
			if (strtolower($type) == strtolower($gi[0]))
				return true ;
		}
		return false ;
	}

	/**
	  * INTERNAL 
	  * permet de savoir si la fonction du plugin est executé pour le bon composant (S2, SP, ...)
	   * astuce pour ne remplir que les champs du type en cours, avec une liste de plugins non définis
	  *
	  * @since 1.0
	  *
	  * @param string 	$type 		internal use
	  * @param array 	$codes		code of the application
	  * @param array   	$resCode 	contains the list of available codes
	  */
	protected function _getMenuItemId($itemid=0){
		if ($itemid>0)
			return $itemid;

		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id');
		$query->from($db->quoteName('#__menu'));
		$query->where("link LIKE '%index.php?option={$this->gatewayOption}%' AND type='component' AND published='1'");
		$db->setQuery($query, 0, 1);
		return $db->loadResult();
	}

	/**
	  * add where condition to a db query to know if, regarding your component, a coordinates field is valid
	  *
	  * @since 1.0
	  *
	  * @param object 	$db 		database object
	  * @param string 	$fieldLat 	represent the latitude query field
	  * @param string 	$fieldLng 	represent the longitude query field
	  */
	protected function _getValidCoordTest($fieldLat, $fieldLng){
		// je teste si il y a un VP 
		$app 	= JFactory::getApplication('site');
		$vp		= $app->input->getString('bo', '');
		$allM	= $app->input->getInt('allM');
		$vp 	= explode(',', $vp);
		if (!is_array($vp) || count($vp)!=4 || $allM==1)
			$vp=null;

		$db = JFactory::getDBO();
		$t = "(";
		$t.= 	"({$fieldLat}<>".$db->Quote("") ;
		if ($vp)$t.=" AND ({$fieldLat} BETWEEN {$vp[0]} AND {$vp[2]})" ;
		$t.= 		" AND {$fieldLat} IS NOT NULL ";
		$t.= 		" AND {$fieldLat}<>0 ";// désolé pour celui qui habite a 0
		$t.= 		" AND {$fieldLat}<>".$this->defEmptyLat.")";
		$t.= " OR " ;
		$t.= 	"({$fieldLng}<>".$db->Quote("");
		if ($vp)$t.=" AND ({$fieldLng} BETWEEN {$vp[1]} AND {$vp[3]})" ;
		$t.= 		" AND {$fieldLng} IS NOT NULL ";
		$t.= 		" AND {$fieldLng}<>0 ";// désolé pour celui qui habite a 0
		$t.= 		" AND {$fieldLng}<>".$this->defEmptyLng.")";
		$t.= ")";

		$t = str_replace(array('\t', '   ', '  '), ' ', $t) ;
		return $t ;
	}

	// utilisation de having car on utilise une variabble ... AS c_status ---
	protected function _finaliseGetListQueryBackGeocode($query, $filters){
		$filterSearch 	= $filters[0] ;
		$filterGeocoded	= $filters[2] ; 
		$listDirection	= $filters[1] ;
		$db 			= JFactory::getDBO();
		$query->group('item_id, item_name, c_status');

		// Filter by search in title
		if (!empty($filterSearch)) {
			if (stripos($filterSearch, 'id:') === 0) {
				$query->having('item_id = '.(int) substr($filterSearch, 3));
			} else {
				$filterSearch = $db->Quote('%'.$db->escape($filterSearch, true).'%');
				$query->having('item_name LIKE '.$filterSearch);
			}
		}

		// 1=geocoded, 2=not gecoded 
		if ($filterGeocoded==1)			$query->having('c_status=1');
		else if ($filterGeocoded==2)	$query->having('c_status=0');
	
		$query->order($db->escape('item_name').' '.$db->escape($listDirection));
		return $query ;
	}

	protected function _genericUrl($href){
		$href = str_replace( '&amp;', '&', $href );
		$uri = JURI::getInstance();
		$prefix = $uri->toString( array( 'scheme' , 'host' , 'port' ) );
		return $prefix . JRoute::_($href);
	}

	public function getAllTags($idTopCat, &$vCats){
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(	'id as catid, parent_id as parentid, title');
		$query->from(	$db->quoteName('#__tags'));
		$query->order(	'title');
		$db->setQuery($query);    	$vCats = $db->loadObjectList();
		if ($db->getErrorNum()) {	trigger_error("getAllTags: DB reports: ".$db->stderr(), E_USER_WARNING);}
	}

    // si indent est pas null, c'est que je veux une liste visible par un humain
	public static function _getChildCatOf($categoryList, &$par, &$vRes, $indent){
		if (is_string($indent)) 	$indent.="- " ;
		if(sizeof($categoryList) > 0) {
	    	foreach($categoryList as $category) {
				if($category->parentid == $par) {
		    		$vRes[] = is_string($indent)?JHTML::_('select.option', $category->catid, $indent.stripcslashes( stripslashes( stripslashes($category->title)))) : $category->catid ;
					GeofactoryPluginHelper::_getChildCatOf($categoryList, $category->catid, $vRes, $indent);
				}
	    	}
		}
	}
}
