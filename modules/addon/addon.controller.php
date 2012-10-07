<?php
    /**
     * Addon module's controller class
     * @author NHN (developers@xpressengine.com)
     **/
    class addonController extends addon {

        /**
         * Initialization
		 *
		 * @return void
         **/
        function init() {
        }

        /**
         * Returns a cache file path
		 *
		 * @param $type pc or mobile
		 * @return string Returns a path
         **/
        function getCacheFilePath($type = "pc") {
			static $addon_file;
			if(isset($addon_file)) return $addon_file;

            $site_module_info = Context::get('site_module_info');
            $site_srl = $site_module_info->site_srl;

            $addon_path = _XE_PATH_.'files/cache/addons/';

            $addon_file = $addon_path.$site_srl.$type.'.acivated_addons.cache.php';

            if($this->addon_file_called) return $addon_file;
            $this->addon_file_called = true;

            if(!is_dir($addon_path)) FileHandler::makeDir($addon_path);
            if(!file_exists($addon_file)) $this->makeCacheFile($site_srl, $type);
            return $addon_file;
        }


        /**
         * Returns mid list that addons is run
		 *
		 * @param string $selected_addon Name to get list
		 * @param int $site_srl Site srl
		 * @return string[] Returns list that contain mid
         **/
        function _getMidList($selected_addon, $site_srl = 0) {

            $oAddonAdminModel = &getAdminModel('addon');
            $addon_info = $oAddonAdminModel->getAddonInfoXml($selected_addon, $site_srl);
            return $addon_info->mid_list;
        }



        /**
         * Adds mid into running mid list
		 *
		 * @param string $selected_addon Addon name to add mid
		 * @param string $mid Module id to add
		 * @param int $site_srl Site srl
		 * @return void
         **/
        function _setAddMid($selected_addon,$mid, $site_srl=0) {
            // Wanted to add the requested information
            $mid_list = $this->_getMidList($selected_addon, $site_srl);

            $mid_list[] = $mid;
            $new_mid_list = array_unique($mid_list);
            $this->_setMid($selected_addon,$new_mid_list, $site_srl);
        }


        /**
         * Deletes mid from running mid list
		 *
		 * @param string $selected_addon Addon name to delete mid
		 * @param string $mid Module id to delete
		 * @param int $site_srl Site srl
		 * @return void
         **/
        function _setDelMid($selected_addon,$mid,$site_srl=0) {
            // Wanted to add the requested information
            $mid_list = $this->_getMidList($selected_addon,$site_srl);

            $new_mid_list = array();
            if(is_array($mid_list)){
                for($i=0,$c=count($mid_list);$i<$c;$i++){
                    if($mid_list[$i] != $mid) $new_mid_list[] = $mid_list[$i];
                }
            }else{
                $new_mid_list[] = $mid;
            }


            $this->_setMid($selected_addon,$new_mid_list,$site_srl);
        }

        /**
         * Set running mid list
		 *
		 * @param string $selected_addon Addon name to set
		 * @param string[] $mid_list List to set
		 * @param int $site_srl Site srl
		 * @return void
         **/
        function _setMid($selected_addon,$mid_list,$site_srl=0) {
            $args->mid_list =  join('|@|',$mid_list);
            $this->doSetup($selected_addon, $args,$site_srl);
            $this->makeCacheFile($site_srl);
        }


        /**
		 * Adds mid into running mid list
		 *
		 * @return Object
         **/
        function procAddonSetupAddonAddMid() {
            $site_module_info = Context::get('site_module_info');

            $args = Context::getRequestVars();
            $addon_name = $args->addon_name;
            $mid = $args->mid;
            $this->_setAddMid($addon_name,$mid,$site_module_info->site_srl);
        }

        /**
         * Deletes mid from running mid list
		 *
		 * @return Object
         **/
        function procAddonSetupAddonDelMid() {
            $site_module_info = Context::get('site_module_info');

            $args = Context::getRequestVars();
            $addon_name = $args->addon_name;
            $mid = $args->mid;

            $this->_setDelMid($addon_name,$mid,$site_module_info->site_srl);
        }

        /**
         * Re-generate the cache file
		 *
		 * @param int $site_srl Site srl
		 * @param string $type pc or mobile
		 * @param string $gtype site or global
		 * @return void
         **/
        function makeCacheFile($site_srl = 0, $type = "pc", $gtype = 'site') {
            // Add-on module for use in creating the cache file
            $buff = "";
            $oAddonModel = &getAdminModel('addon');
            $addon_list = $oAddonModel->getInsertedAddons($site_srl, $gtype);
            foreach($addon_list as $addon => $val) {
                if($val->addon == "smartphone") continue;
				if(!is_dir(_XE_PATH_.'addons/'.$addon)) continue;
				if(($type == "pc" && $val->is_used != 'Y') || ($type == "mobile" && $val->is_used_m != 'Y') || ($gtype == 'global' && $val->is_fixed != 'Y')) continue;

                $extra_vars = unserialize($val->extra_vars);
                $mid_list = $extra_vars->mid_list;
                if(!is_array($mid_list)||!count($mid_list)) $mid_list = null;

				$buff .= '$rm = \'' . $extra_vars->xe_run_method . "';";
				$buff .= '$ml = array(';
				if($mid_list)
				{
					foreach($mid_list as $mid)
					{
						$buff .= "'$mid' => 1,";
					}
				}
				$buff .= ');';
				$buff .= sprintf('$addon_file = \'./addons/%s/%s.addon.php\';', $addon, $addon);

                if($val->extra_vars) {
                    unset($extra_vars);
                    $extra_vars = base64_encode($val->extra_vars);
                }
				$addon_include = sprintf('unset($addon_info); $addon_info = unserialize(base64_decode(\'%s\')); @include($addon_file);', $extra_vars);

				$buff .= 'if(file_exists($addon_file)){';
				$buff .= 'if($rm === \'no_run_selected\'){';
				$buff .= 'if(!isset($ml[$_m])){';
				$buff .= $addon_include;
				$buff .= '}}else{';
				$buff .= 'if(isset($ml[$_m]) || count($ml) === 0){';
				$buff .= $addon_include;
				$buff .= '}}}';
            }

			$buff = sprintf('<?php if(!defined("__XE__")) exit(); $_m = Context::get(\'mid\'); %s ?>', $buff);

            $addon_path = _XE_PATH_.'files/cache/addons/';
            if(!is_dir($addon_path)) FileHandler::makeDir($addon_path);

            if($gtype == 'site') $addon_file = $addon_path.$site_srl.$type.'.acivated_addons.cache.php';
            else $addon_file = $addon_path.$type.'.acivated_addons.cache.php';

            FileHandler::writeFile($addon_file, $buff);
        }

        /**
         * Save setup
		 *
		 * @param string $addon Addon name
		 * @param object $extra_vars Extra variables
		 * @param int $site_srl Site srl
		 * @param string $gtype site or global
		 * @return Object
         **/
        function doSetup($addon, $extra_vars,$site_srl=0, $gtype = 'site') {
            if(!is_array($extra_vars->mid_list))	unset($extra_vars->mid_list);

            $args->addon = $addon;
            $args->extra_vars = serialize($extra_vars);
            if($gtype == 'global') return executeQuery('addon.updateAddon', $args);
            $args->site_srl = $site_srl;
            return executeQuery('addon.updateSiteAddon', $args);
        }

        /**
         * Remove add-on information in the virtual site
		 *
		 * @param int $site_srl Site srl
		 * @return void
         **/
        function removeAddonConfig($site_srl) {
            $addon_path = _XE_PATH_.'files/cache/addons/';
            $addon_file = $addon_path.$site_srl.'.acivated_addons.cache.php';
            if(file_exists($addon_file)) FileHandler::removeFile($addon_file);

            $args->site_srl = $site_srl;
            executeQuery('addon.deleteSiteAddons', $args);


        }


    }
?>
