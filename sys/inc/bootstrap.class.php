<?php
/*-----------------------------------------------\
| 												 |
|  @Author:       Andrey Brykin (Drunya)         |
|  @Version:      1.0.0                          |
|  @Project:      CMS                            |
|  @package       CMS AtomX                      |
|  @subpackege    Bootstrap Class                |
|  @copyright     ©Andrey Brykin 2010-2011       |
|  @last mod.     2012/02/16                     |
\-----------------------------------------------*/

/*-----------------------------------------------\
| 												 |
|  any partial or not partial extension          |
|  CMS AtomX,without the consent of the          |
|  author, is illegal                            |
|------------------------------------------------|
|  Любое распространение                         |
|  CMS AtomX или ее частей,                      |
|  без согласия автора, является не законным     |
\-----------------------------------------------*/


class Bootstrap
{

    /**
     * @var \ACL|bool|\Document_Parser|mixed|null
     */
    private $Register;


    /**
     *
     */
    public function __construct()
    {
        $this->Register = Register::getInstance();

        $this->setPhpSettings();
        $this->touchStartTime();
		$this->Register['Cache'] = new Cache;

		
        $viewerLoader = new Fps_Viewer_Loader(array(
            'template_root' => ROOT . '/template/' . getTemplateName() . '/html/'
        ));
        $this->Register['Viewer'] = new Fps_Viewer_Manager($viewerLoader);


		if (isInstall()) {
            $this->registerCustomTemplateFunctions();

			$this->Register['DB'] = (class_exists('PDO') && Config::read('use_pdo')) ? FpsPDO::get() : FpsDataBase::get();
			$this->Register['UserAuth'] = new UserAuth;
			$this->Register['Log'] = new Logination();
		}
		
		
        $this->Register['DocParser'] = new Document_Parser;
        $this->Register['ACL'] = new ACL(ROOT . '/sys/settings/');
        $this->Register['PrintText'] = new PrintText;
        $this->Register['Validate'] = new Validate(function($errors) {
            $Register = Register::getInstance();
			return $Register['DocParser']->wrapErrors($errors);
		});
        $this->Register['ModManager'] = new ModulesManager(ROOT . '/sys/settings/modules_access.php');
        $this->Register['PluginController'] = new Plugins;
        $this->Register['URL'] = new AtmUrl;
        $this->Register['Protector'] = new Protect;

		
		if (isInstall()) {
			$this->inputCheck();
			$this->initProtect();
			$this->initUser();
            $this->loadLanguages();
		}
    }



    /**
     * @return void
     */
    public function initUser()
    {
        $UserAuth = new UserAuth;
        /*
        * Auto login... if user during a previos
        * visit set AUTOLOGIN option
        */
        if (!isset($_SESSION['user']) and isset($_COOKIE['autologin']))
            $UserAuth->autoLogin();
        
        /*
        * if user is autorizet set
        * last time visit
        * This information store in <DataBase>.users
        */
        if (!empty($_SESSION['user'])) {
            $UserAuth->setTimeVisit();
        }
    }


    public function loadLanguages()
    {
        $modules = $this->Register['ModManager']->getModulesList(true, true);
        $permitted_langs = getPermittedLangs();
        if (!$modules || !$permitted_langs) return array();

        $result = array();
        foreach ($modules as $module) {
            $lang_files_paths = glob(ROOT . '/modules/' . $module . '/languages/*.php');
            if (!$lang_files_paths) continue;
            foreach ($lang_files_paths as $lang_file_path) {
                $lang = basename($lang_file_path, '.php');

                if (is_file($lang_file_path) && !empty($permitted_langs) && in_array($lang, $permitted_langs)) {
                    if (!array_key_exists($module, $result))
                        $result[$module] = array();
                    $result[$module][$lang] = $lang_file_path;
                }
            }
        }
        $this->Register['modules_translations'] = $result;
    }


    /**
     * @return void
     */
    public function touchStartTime()
    {
        /**
        * pinpoint the time
        * I wont know time needed for load page
        */
        $this->Register['fps_boot_start_time'] = getMicroTime();
    }



    /**
     * @return void
     */
    private function initProtect()
    {
        $Protect = new Protect;
        /**
        * ban by IP adres
        */
        $Protect->checkIpBan();
        
        /**
        * AntiDDOS protection
        * this is optionaly
        */
        if (Config::read('anti_ddos', 'secure') == 1) {
            $Protect->antiDdos();
        }

        /*
        * defense
        */
        if(Config::read('antisql', 'secure') == 1) {
            $Protect->antiSQL();
        }
    }



    /**
     * @return void
     */
    private function inputCheck()
    {
        /**
        * magic gemor
        */
        if (get_magic_quotes_gpc()) {
          strips($_GET);
          strips($_POST);
          strips($_COOKIE);
          strips($_REQUEST);
          if (isset($_SERVER['PHP_AUTH_USER'])) strips($_SERVER['PHP_AUTH_USER']);
          if (isset($_SERVER['PHP_AUTH_PW']))   strips($_SERVER['PHP_AUTH_PW']);
        }
    }



    /**
     * @return void
     */
    private function setPhpSettings()
    {
        @ini_set('session.gc_maxlifetime', 10000);

        ini_set('post_max_size', "100M");
        ini_set('upload_max_filesize', "100M");
        if (function_exists('set_time_limit')) @set_time_limit(200);

        ini_set('register_globals', 0);
        ini_set('magic_quotes_gpc', 0);
        ini_set('magic_quotes_runtime', 0);
        session_set_cookie_params(3000);
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_NOTICE);



        /**
        * if debug mode On - view errors
        */
        if (Config::read('debug_mode') == 1) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }
		
		ini_set('log_errors', 1);
		ini_set('error_log', ROOT . '/sys/logs/php_errors.log');



        
        /**
         * Set default encoding
         * After set this, we mustn't set encoding
         * into next functions: mb_substr, mb_strlen, etc...
         */
        if (function_exists('mb_internal_encoding'))
            mb_internal_encoding('UTF-8');
			
		date_default_timezone_set('UTC');
    }


    private function registerCustomTemplateFunctions()
    {
        $Viewer = $this->Register['Viewer'];
        $functions = AtmTemplateFunctions::get();

        if (is_array($functions) && count($functions)) {
            foreach ($functions as $name => $function) {
                $Viewer->registerCustomFunction($name, $function);
            }
        }
    }
}
