<?php
/*-----------------------------------------------\
| 												 |
|  @Author:       Andrey Brykin (Drunya)         |
|  @Email:        drunyacoder@gmail.com          |
|  @Site:         http://atomx.net			     |
|  @Version:      2.1.0                          |
|  @Project:      CMS AtomX                      |
|  @Package       CMS AtomX                      |
|  @Subpackege    Pather Class                   |
|  @Copyright     ©Andrey Brykin                 |
|  @Last mod.     2014/03/31                     |
|------------------------------------------------|
| 												 |
|  any partial or not partial extension          |
|  CMS AtomX,without the consent of the          |
|  author, is illegal                            |
|------------------------------------------------|
|  Любое распространение                         |
|  CMS AtomX или ее частей,                      |
|  без согласия автора, является не законным     |
\-----------------------------------------------*/



/**
 * @author      Brykin Andrey
 * @url         http://atomx.net
 * @version     1.1.0
 * @copyright   ©Andrey Brykin 2010 - 2013
 * @last mod.   2013/07/06
 *
 * Parse url path and get from him requested needed params
 * (module, action, etc.)
 */
Class Pather {

    public $Register;

	function __construct($Register) {
        $this->Register = $Register;

		$redirect = $this->Register['Config']->read('redirect');
		if (!empty($redirect)) {
			header('Location: ' . $this->Register['Config']->read('redirect') . '');
			die();
		}
		
		$url = (!empty($_GET['url'])) ? $this->decodeUrl($_GET['url']) : '';
		$params = $this->parsePath($url);
		$data = $this->callAction($params);
	}
	
	
	/**
	 *
	 */
	static public function parseRoutes($url)
	{
		$params = self::getRoutesRules();
		if (!empty($params) && is_array($params))
			return str_replace(array_keys($params), $params, $url);
		return $url;
	}
	
	
	/**
	 *
	 */
	static public function getRoutesRules()
	{
		$path = ROOT . '/sys/settings/routes.php';
		if (!file_exists($path)) return array();
		$params = include $path;
		return $params;
	}



	private function decodeUrl($url)
	{
		$params = self::getRoutesRules();
		if (!empty($params) && is_array($params))
			return str_replace($params, array_keys($params), $url);
		return $url; 
	}
	
	

    /**
     * @return array
     */
	function parsePath($url) {
		$url = (!empty($url)) ? $this->decodeUrl($url) : '';
        $Register = Register::getInstance();


		$fixed_url = $Register['URL']->checkAndRepair($_SERVER['REQUEST_URI']);
		if (!empty($url) && $_SERVER['REQUEST_METHOD'] == 'GET'
        && $fixed_url !== $_SERVER['REQUEST_URI'])
            redirect($fixed_url, 301);
		

		$url = rtrim($url, '/');
        $pathParams = explode('/', $url);
        $pathParams = array_filter($pathParams);


        $start_mod = Config::read('start_mod');
        if ($start_mod) {
            if  (rtrim($start_mod, '/') != $url) {
                $this->getLang($pathParams);

                if (!$pathParams || count($pathParams) < 1) {
                    $pathParams_ = $this->parsePath($start_mod);
                    $pathParams = $pathParams_;
                }

            } else {
                $this->Register['is_home_page'] = true;
            }
        } else {
            $this->getLang($pathParams);
        }

		if (empty($pathParams)) {
            $this->Register['is_home_page'] = true;

			$pathParams = array(
				'pages',
				'index',
			);
		}
		
		// sort array(keys begins from 0)
		$pathParams = array_map(function($r){
			return trim($r);
		}, $pathParams);


		if (count($pathParams) >= 1 && !file_exists(ROOT . '/modules/' . $pathParams[0])) {
			$pathParams = array(
				0 => 'pages',
				1 => 'index',
				2 => implode('/', $pathParams),
			);
		}


		return $pathParams;
	}
	
	
	public function getLang(&$pathParams)
	{
		$permitted_langs = getPermittedLangs();
		if (!empty($permitted_langs)) {
			foreach($permitted_langs as $lang) {
				if (!empty($pathParams[0]) && $pathParams[0] === $lang) {
					$_SESSION['lang'] = $lang;
					unset($pathParams[0]);


                    $tmpArr = array();
					if (count($pathParams) > 0) {
						foreach ($pathParams as $param)
                            $tmpArr[] = $param;
					}
                    $pathParams = $tmpArr;
					
					return;
				}
			}
		}
		
		$_SESSION['lang'] = Config::read('language');
	}

    

    /**
     * @param  $params
     * @return void
     */
	function callAction($params)
    {
		// if we have one argument, we get page if it exists or error
		if (!is_file(ROOT . '/modules/' . strtolower($params[0]) . '/index.php')) {
            $params = array(
                0 => 'pages',
                1 => 'index',
                2 => $params[0],
            );
		}

		
		include_once ROOT . '/modules/' . strtolower($params[0]) . '/index.php';
		$module = ucfirst($params[0]) . 'Module';
		if (!class_exists($module))  {
            $this->Register['DocParser']->showHttpError();
		}


		// Parse two and more arguments
		if (count($params) > 1) {
			// Human Like URL
			if ($this->Register['Config']->read('hlu_understanding') || $this->Register['Config']->read('hlu')) {
				if ($params[1] !== 'view' && (empty($params[2]) || !is_numeric($params[2]))) {

                    // Geting new HLU title if he was changed.
                    $mat_id = $this->getNewHLUTitle($params[1], $params[0]);
                    if ($mat_id) {
                        // redirect to new URL (might the title was changed)
                        redirect($params[0] . '/' . $mat_id, 301);
                    }
                }
			}
		}


        $this->Register['dispath_params'] = $params;
		if (count($params) == 1) $params[] = 'index';
		$this->module = new $module($params);


		// Parse second argument
		if (count($params) > 1) {
			if (preg_match('#^_+#', $params[1])) {
                $this->Register['DocParser']->showHttpError();
			}
			if (!method_exists($this->module, $params[1])) {
                if (method_exists($this->module, ($params[0] === 'forum') ? 'view_theme' : 'view')) {
                    // geting entity ID by HLU title from URL
                    $params[2] = $this->module->getEntryId($params[1]);
                    $params[1] = ($params[0] === 'forum') ? 'view_theme' : 'view';
                } else {
                    $this->Register['DocParser']->showHttpError();
                }
			}
		}


        $params = Plugins::intercept('before_call_module', $params);
		call_user_func_array(array($this->module, $params[1]), array_slice($params, 2));
	}


	/**
	 * Tries to find temporary file with the new entity title if he was changed.
	 *
	 * @param string $string
	 * @param string $module
	 * @return int ID
	 */
	private function getNewHLUTitle($string, $module) {
		$Register = Register::getInstance();
		$clean_str = $string;
		$tmp_file = $Register['URL']->getTmpFilePath($clean_str, $module);
		if (!file_exists($tmp_file) || !is_readable($tmp_file)) return false;

        $params = json_decode(file_get_contents($tmp_file), true);
        return $params['title'];
	}

}