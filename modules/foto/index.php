<?php
/*-----------------------------------------------\
| 												 |
| @Author:       Andrey Brykin (Drunya)          |
| @Email:        drunyacoder@gmail.com           |
| @Site:         http://atomx.net                |
| @Version:      1.7.7                           |
| @Project:      CMS                             |
| @package       CMS AtomX                       |
| @subpackege    Foto Module  			 		 |
| @copyright     ©Andrey Brykin 2010-2014        |
| @last  mod     2014/06/26                      |
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







Class FotoModule extends Module {

	/**
	* @module_title  title of module
	*/
	public $module_title = 'Фото';
	/**
	* @template  layout for module
	*/
	public $template = 'foto';
	/**
	* @module module indentifier
	*/
	public $module = 'foto';


	
	/**
	* default action ( show main page )
	*/
	public function index() {
		//turn access
		$this->ACL->turn(array('foto', 'view_list'));
		
		//формируем блок со списком  разделов
		$this->_getCatsTree();
		
		
		//check content cache
		if ($this->cached && $this->Cache->check($this->cacheKey)) {
			$source = $this->Cache->read($this->cacheKey);
			return $this->_view($source);
		}
		
		
		$where = array();
		// we need to know whether to show hidden
		$where[] = $this->_getDeniSectionsCond();
		
		//Узнаем кол-во материалов в БД
		$total = $this->Model->getTotal(array('cond' => $where));
		list ($pages, $page) = pagination( $total, $this->Register['Config']->read('per_page', 'foto'), '/foto/');
		$this->Register['pages'] = $pages;
		$this->Register['page'] = $page;
        $this->addToPageMetaContext('page', $page);

		
		$navi = array();
		$navi['add_link'] = ($this->ACL->turn(array('foto', 'add_materials'), false)) 
			? get_link(__('Add material'), '/foto/add_form/') : '';
		$navi['navigation'] = $this->_buildBreadCrumbs();
		$navi['pagination'] = $pages;
		$navi['meta'] = __('Total materials') . $total;
		$this->_globalize($navi);


		if($total <= 0) {
			$html = __('Materials not found');
			return $this->_view($html);
		}


		$this->Model->bindModel('author');
		$this->Model->bindModel('category');
        $params = array(
            'page' => $page,
            'limit' => $this->Register['Config']->read('per_page', 'foto'),
            'order' => $this->Model->getOrderParam(),
        );
		$records = $this->Model->getCollection($where, $params);
		
		
		// create markers
		$addParams = array();
		foreach ($records as $result) {
			$this->Register['current_vars'] = $result;
			$_addParams = array();
			
			
			$_addParams['moder_panel'] = $this->_getAdminBar($result);
			$entry_url = entryUrl($result, $this->module);
			$_addParams['entry_url'] = $entry_url;
			$_addParams['foto_alt'] = h(preg_replace('#[^\w\d ]+#ui', ' ', $result->getTitle()));
			
			
			
			$_addParams['category_url'] = get_url('/foto/category/' . $result->getCategory_id());
			$_addParams['profile_url'] = getProfileUrl($result->getAuthor()->getId());


			//set users_id that are on this page
			$this->setCacheTag(array(
				'user_id_' . $result->getAuthor()->getId(),
				'record_id_' . $result->getId(),
			));
		

			$result->setAdd_markers($_addParams);
		}
		
		
		$source = $this->render('list.html', array('entities' => $records));
		
		
		//write int cache
		if ($this->cached)
			$this->Cache->write($source, $this->cacheKey, $this->cacheTags);
		
	
		return $this->_view($source);
	}


	 
	public function category($id = null) {
		//turn access
		$this->ACL->turn(array('foto', 'view_list'));
		$id = intval($id);
		if (empty($id) || $id < 1) redirect('/');

		
		$SectionsModel = $this->Register['ModManager']->getModelInstance($this->module . 'Categories');
		$category = $SectionsModel->getById($id);
		if (!$category)
			return showInfoMessage(__('Can not find category'), '/foto/');
		if (!$this->ACL->checkCategoryAccess($category->getNo_access())) 
			return showInfoMessage(__('Permission denied'), '/foto/');


        $this->addToPageMetaContext('category_title', h($category->getTitle()));
		
		
		//формируем блок со списком  разделов
		$this->_getCatsTree($id);
		
		
		if ($this->cached && $this->Cache->check($this->cacheKey)) {
			$source = $this->Cache->read($this->cacheKey);
			return $this->_view($source);
		}
	
		$where = array();
		// we need to know whether to show hidden
		$where[] = $this->_getDeniSectionsCond($id);
		

		$total = $this->Model->getTotal(array('cond' => $where));
		list ($pages, $page) = pagination( $total, Config::read('per_page', 'foto'), '/foto/');
		$this->Register['pages'] = $pages;
		$this->Register['page'] = $page;
        $this->addToPageMetaContext('page', $page);


		
		$navi = array();
		$navi['add_link'] = ($this->ACL->turn(array('foto', 'add_materials'), false)) 
			? get_link(__('Add material'), '/foto/add_form/') : '';
		$navi['navigation'] = $this->_buildBreadCrumbs($id);
		$navi['pagination'] = $pages;
		$navi['meta'] = __('Count material in cat') . $total;
		$navi['category_name'] = h($category->getTitle());
		$this->_globalize($navi);


		if($total <= 0) {
			$html = __('Materials not found');
			return $this->_view($html);
		}


		$this->Model->bindModel('author');
		$this->Model->bindModel('category');
        $params = array(
            'page' => $page,
            'limit' => $this->Register['Config']->read('per_page', 'foto'),
            'order' => $this->Model->getOrderParam(),
        );
		$records = $this->Model->getCollection($where, $params);


		// create markers
		foreach ($records as $result) {
			$this->Register['current_vars'] = $result;
			$_addParams = array();
			
			
			$_addParams['moder_panel'] = $this->_getAdminBar($result);
			$entry_url = entryUrl($result, $this->module);
			$_addParams['entry_url'] = $entry_url;
			$_addParams['foto_alt'] = h(preg_replace('#[^\w\d ]+#ui', ' ', $result->getTitle()));
			
			
			$_addParams['category_url'] = get_url('/foto/category/' . $result->getCategory_id());
			$_addParams['profile_url'] = getProfileUrl($result->getAuthor()->getId());


			//set users_id that are on this page
			$this->setCacheTag(array(
				'user_id_' . $result->getAuthor()->getId(),
				'record_id_' . $result->getId(),
				'category_id_' . $id,
			));
		

			$result->setAdd_markers($_addParams);
		}
		
		
		$source = $this->render('list.html', array('entities' => $records));
		
		
		//write int cache
		if ($this->cached)
			$this->Cache->write($source, $this->cacheKey, $this->cacheTags);
		
	
		return $this->_view($source);
	}
	  
	  
	  
	/**
	 *
	 */
	public function view ($id = null) {
		//turn access
		$this->ACL->turn(array('foto', 'view_materials'));
		$id = intval($id);
		if (empty($id) || $id < 1) redirect('/');

		

		$this->Model->bindModel('author');
		$this->Model->bindModel('category');
		$entity = $this->Model->getById($id);
		
		
		if (!$entity) $this->Parser->showHttpError();
		if (!$this->ACL->checkCategoryAccess($entity->getCategory()->getNo_access())) 
			return $this->showInfoMessage(__('Permission denied'), '/foto/');
		
		
		//category block
		$this->_getCatsTree($entity->getCategory()->getId());

		$this->Register['current_vars'] = $entity;
		
		
		//производим замену соответствующих участков в html шаблоне нужной информацией
        $this->addToPageMetaContext('category_title', h($entity->getCategory()->getTitle()));
        $this->addToPageMetaContext('entity_title', h($entity->getTitle()));

		
		$navi = array();
		$navi['module_url'] = get_url('/foto/');
		$navi['category_url'] = get_url('/foto/category/' . $entity->getCategory()->getId());
		$navi['category_name'] = h($entity->getCategory()->getTitle());
		$navi['navigation'] = $this->_buildBreadCrumbs($entity->getCategory()->getId());
		$this->_globalize($navi);
		
		
		$next_prev = $this->Model->getNextPrev($id);
		
		
		$markers = array();
		$markers['profile_url'] = getProfileUrl($entity->getAuthor()->getId());
		
		$markers['moder_panel'] = $this->_getAdminBar($entity);
		$markers['foto_alt'] = h(preg_replace('#[^\w\d ]+#ui', ' ', $entity->getTitle()));
		$markers['description'] = $this->Textarier->parseBBCodes($entity->getDescription(), $entity);
		

		$prev_id = (!empty($next_prev['prev'])) ? $next_prev['prev']->getId() : $id;
		$next_id = (!empty($next_prev['next'])) ? $next_prev['next']->getId() : $id;
		
		$markers['previous_url'] = get_url('/foto/view/' . $prev_id);
		$markers['next_url'] = get_url('/foto/view/' . $next_id);

		$entry_url = entryUrl($entity, $this->module);
		$markers['entry_url'] = $entry_url;
		
		
		$entity->setAdd_markers($markers);
		
		
		$this->setCacheTag(array(
			'user_id_' . $entity->getAuthor()->getId(),
			'record_id_' . $entity->getId(),
			(!empty($_SESSION['user']['status'])) ? 'user_group_' . $_SESSION['user']['status'] : 'user_group_' . 'guest',
		));
		
		
		$source = $this->render('material.html', array('entity' => $entity));
		
		
		$entity->setViews($entity->getViews() + 1);
		$entity->save();
		$this->DB->cleanSqlCache();
		
		return $this->_view($source);
	}


	
	/**
	 * Show materials by user. User ID must be integer and not null.
	 */
	public function user($id = null) 
	{
		//turn access
		$this->ACL->turn(array($this->module, 'view_list'));
		$id = intval($id);
		if ($id < 1)
		return $this->showInfoMessage(__('Can not find user'), '/' . $this->module . '/');


		$usersModel = $this->Register['ModManager']->getModelInstance('Users');
		$user = $usersModel->getById($id);
		if (!$user)
			return $this->showInfoMessage(__('Can not find user'), '/' . $this->module . '/');


		//формируем блок со списком разделов
		$this->_getCatsTree();


		if ($this->cached && $this->Cache->check($this->cacheKey)) {
			$source = $this->Cache->read($this->cacheKey);
			return $this->_view($source);
		}

		// we need to know whether to show hidden
		$where = array('author_id' => $id);
		$where[] = $this->_getDeniSectionsCond();


		$total = $this->Model->getTotal(array('cond' => $where));
		list ($pages, $page) = pagination($total, $this->Register['Config']->read('per_page', $this->module), '/' . $this->module . '/user/' . $id);
		$this->Register['pages'] = $pages;
		$this->Register['page'] = $page;
        $this->addToPageMetaContext('page', $page);
        $this->addToPageMetaContext('entity_title', sprintf(__('User materials'), h($user->getName())));



		$navi = array();
		$navi['add_link'] = ($this->ACL->turn(array($this->module, 'add_materials'), false)) 
			? get_link(__('Add material'), '/' . $this->module . '/add_form/') 
			: '';
		$navi['navigation'] = get_link(__('Home'), '/') . __('Separator')
			. get_link(h($this->module_title), '/' . $this->module . '/') 
			. __('Separator') 
			. sprintf(__('User materials'), h($user->getName())) . '"';
		$navi['pagination'] = $pages;
		$navi['meta'] = __('Total materials') . $total;
		$navi['category_name'] = sprintf(__('User materials'), h($user->getName()));
		$this->_globalize($navi);


		if ($total <= 0) {
			$html = __('Materials not found');
			return $this->_view($html);
		}


		$this->Model->bindModel('author');
		$this->Model->bindModel('category');
        $params = array(
            'page' => $page,
            'limit' => $this->Register['Config']->read('per_page', $this->module),
            'order' => $this->Model->getOrderParam(),
        );
		$records = $this->Model->getCollection($where, $params);


		// create markers
		foreach ($records as $entity) {
			$this->Register['current_vars'] = $entity;
			$markers = array();


			$markers['moder_panel'] = $this->_getAdminBar($entity);
			$entry_url = entryUrl($entity, $this->module);
			$markers['entry_url'] = $entry_url;
			$markers['foto_alt'] = h(preg_replace('#[^\w\d ]+#ui', ' ', $entity->getTitle()));


			$markers['category_url'] = get_url('/' . $this->module . '/category/' . $entity->getCategory_id());
			$markers['profile_url'] = getProfileUrl($entity->getAuthor_id());


			//set users_id that are on this page
			$this->setCacheTag(array(
			'user_id_' . $entity->getAuthor_id(),
			'record_id_' . $entity->getId(),
			'category_id_' . $id,
			));


			$entity->setAdd_markers($markers);
		}


		$source = $this->render('list.html', array('entities' => $records));


		//write int cache
		if ($this->cached)
			$this->Cache->write($source, $this->cacheKey, $this->cacheTags);


		return $this->_view($source);
	}



	/**
	 * 
	 * Create form and fill his data from SESSION['FpsForm']
	 * or SESSION['previewMessage'] if an exists. 
	 * Show errors if an exists after unsuccessful attempt.
	 *
	 */
	public function add_form () {
		//turn access
		$this->ACL->turn(array('foto', 'add_materials'));

		
		//формируем блок со списком  разделов
		$this->_getCatsTree();
		

        // Check for preview or errors
        $data = array('title' => null, 'in_cat' => null, 'description' => null);
        $data = Validate::getCurrentInputsValues($data);


        $errors = $this->Register['Validate']->getErrors();
        if (isset($_SESSION['FpsForm'])) unset($_SESSION['FpsForm']);

		
		//categories list
		$className = $this->Register['ModManager']->getModelNameFromModule($this->module . 'Categories');
		$catModel = new $className;
		$sql = $catModel->getCollection();
		$cats_change = $this->_buildSelector($sql, ((!empty($data['in_cat'])) ? $data['in_cat'] : false));
		

		$markers = array();
		$markers['errors'] = $errors;
		$markers['action'] = get_url('/foto/add/');
		$markers['cats_selector'] = $cats_change;
		$markers['title'] = (!empty($title)) ? $title : '';
		$markers['main_text'] = (!empty($data['description'])) ? $data['description'] : '';
		
		
		// Navigation Panel
		$navi['navigation'] = $this->_buildBreadCrumbs();
		$navi['add_link'] = ($this->ACL->turn(array('foto', 'add_materials'), false)) 
			? get_link(__('Add material'), '/foto/add_form/') : '';
		$this->_globalize($navi);
		
		$source = $this->render('addform.html', array('context' => $markers));
		
		return $this->_view($source);
	}



	// Функция добавляет новую новость (новую запись в таблицу БД TABLE_NEWS)
	public function add() {
		//turn access
		$this->ACL->turn(array('foto', 'add_materials'));


		// Обрезаем переменные до длины, указанной в параметре maxlength тега input
		$title   	 = trim(mb_substr( $_POST['title'], 0, 128 ));
		$description = trim($_POST['main_text']);
		$in_cat 	 = intval($_POST['cats_selector']);


		// Check fields
		$errors = $this->Register['Validate']->check($this->Register['action']);
		
		
		//categories list
		$className = $this->Register['ModManager']->getModelNameFromModule($this->module . 'Categories');
		$catModel = new $className;
		$sql = $catModel->getCollection(array('id' => $in_cat));

		if (empty($sql)) $errors[] = __('Can not find category');
		

		// errors
		if (!empty($errors)) {
			$data = array('title' => null, 'description' => $description, 'in_cat' => $in_cat);
			$data = array_merge($data, $_POST);
			$data['errors'] = $errors;
			$_SESSION['FpsForm'] = $data;
			redirect('/foto/add_form/');
		}

		// spam protected
		if ( isset( $_SESSION['unix_last_post'] ) and ( time()-$_SESSION['unix_last_post'] < 10 ) ) {
			return $this->showInfoMessage(__('Your message has been added'), '/foto/');
		}
		

		
		// Формируем SQL-запрос на добавление темы	
		$description = mb_substr($description, 0, Config::read('description_lenght', 'foto'));
		$res = array(
			'title'        => $title,
			'description'  => $description,
			'date'         => new Expr('NOW()'),
			'author_id'    => $_SESSION['user']['id'],
			'category_id'  => $in_cat,
			'filename'  => '',
		);


        try {
            $entity = new FotoEntity($res);
            $id = $entity->save();
            if (!$id)
                throw new Exception('ERROR: SAVE_ERR');


            $filename = $this->__saveFile($_FILES['foto'], $id);
            if (!$filename)
                throw new Exception('ERROR: FILE_UPL');


		    $entity->setFilename($filename)->save();

        } catch (Exception $e) {
            $entity->delete();

            $data = array('title' => null, 'description' => null, 'in_cat' => $in_cat);
            $data = array_merge($data, $_POST);
            $data['errors'] = array(__('Some error occurred'));
            $_SESSION['FpsForm'] = $data;
            redirect('/foto/add_form/');
        }

		
		
		// hook for plugins
		Plugins::intercept('new_entity', array(
			'entity' => $entity,
			'module' => $this->module,
		));


		//clean cache
		$this->Cache->clean(CACHE_MATCHING_TAG, array('module_foto'));
		$this->DB->cleanSqlCache();

		if ($this->Log) $this->Log->write('adding foto', 'foto id(' . $id . ')');
		return $this->showInfoMessage(__('Material successfully added'), '/foto/' );		  
	}



	/**
	 * 
	 * Create form and fill his data from record which ID
	 * transfered into function. Show errors if an exists
	 * after unsuccessful attempt. Also can get data for filling
	 * from SESSION if user try preview message or create error.
	 *
	 * @param int $id material then to be edit
	 */
	public function edit_form($id = null) {	
		$id = intval($id);
		if ($id < 1) redirect('/');

		
		$this->Model->bindModel('author');
		$this->Model->bindModel('category');
		$entity = $this->Model->getById($id);
		
		if (!$entity) return redirect('/foto/');
		
		
		if (!$this->ACL->turn(array('foto', 'edit_materials'), false) 
		&& (empty($_SESSION['user']['id']) || $entity->getAuthor_id() != $_SESSION['user']['id'] 
		|| !$this->ACL->turn(array('foto', 'edit_mine_materials'), false))) {
			return $this->showInfoMessage(__('Permission denied'), '/foto/' );
		}

		//формируем блок со списком  разделов
		$this->_getCatsTree($entity->getCategory_id());
		
		
		//navigation panel
		$navi = array();
		$navi['navigation']  = $this->_buildBreadCrumbs($entity->getCategory_id());
		$this->_globalize($navi);
		
		
        // Check for preview or errors
        $data = array('title' => null, 'in_cat' => $entity->getCategory_id(), 'description' => null);
        $data = Validate::getCurrentInputsValues($entity, $data);
	
	
        $errors = $this->Register['Validate']->getErrors();
        if (isset($_SESSION['FpsForm'])) unset($_SESSION['FpsForm']);
	
	
		//categories list
		$className = $this->Register['ModManager']->getModelNameFromModule($this->module . 'Categories');
		$catModel = new $className;
		$cats = $catModel->getCollection();
		$selectedCatId = (!empty($in_cat)) ? $in_cat : $entity->getCategory_id();
		$cats_change = $this->_buildSelector($cats, $selectedCatId);
		
		
		$data->setErrors($errors);
		$data->setAction(get_url('/foto/update/' . $id));
		$data->setCats_selector($cats_change);
		$data->setMain_text($this->Textarier->parseBBCodes($data->getDescription(), $data));
		
		
		$source = $this->render('editform.html', array('context' => $data));
		
		return $this->_view($source);
	}



	/**
	 * 
	 * Validate data and update record into 
	 * Data Base. If an errors, redirect user to add form
	 * and show error message where speaks as not to admit 
	 * errors in the future
	 * 
	 */
	public function update($id = null) {	
		$id = (int)$id;
		if (empty($id) ) redirect('/');


		$entity = $this->Model->getById($id);
		if (!$entity) return $this->_view(__('Some error occurred'));


		if (!$this->ACL->turn(array('foto', 'edit_materials'), false) 
		&& (empty($_SESSION['user']['id']) || $entity->getAuthor_id() !== $_SESSION['user']['id'] 
		|| !$this->ACL->turn(array('foto', 'edit_mine_materials'), false))) {
			return showInfoMessage(__('Permission denied'), '/foto/' );
		}
		
		
		$errors = $this->Register['Validate']->check($this->Register['action']);
		
		
		// Обрезаем переменные до длины, указанной в параметре maxlength тега input
		$title       = trim(mb_substr($_POST['title'], 0, 128));
		$description = trim($_POST['main_text']);
		$in_cat		 = intval($_POST['cats_selector']);
		if (empty($in_cat)) $in_cat = $entity->getCategory_id();
			
			
		$className = $this->Register['ModManager']->getModelNameFromModule($this->module . 'Categories');
		$catModel = new $className;
		$cats = $catModel->getById($in_cat);	
		if (!$cats) $errors[] = __('Can not find category');

		
		// errors
		if (!empty( $errors )) {
			$data = array('title' => $title, 'description' => $description, 'in_cat' => $in_cat);
			$data['errors'] = $errors;
			$_SESSION['FpsForm'] = $data;
			redirect('/foto/edit_form/' . $id );
		}
		
		$description = mb_substr($description, 0, Config::read('description_lenght', 'foto'));
		$entity->setTitle($title);
		$entity->setDescription($description);
		$entity->setCategory_id($in_cat);
		$entity->save();


        if (!empty($_FILES['foto']['name'])) {
            try {
                $filename = $this->__saveFile($_FILES['foto'], $id);
                if (!$filename)
                    throw new Exception('ERROR: FILE_UPL');


                $entity->setFilename($filename)->save();

            } catch (Exception $e) {
                $data = array('title' => null, 'description' => null, 'in_cat' => $in_cat);
                $data = array_merge($data, $_POST);
                $data['errors'] = array(__('Some error occurred'));
                $_SESSION['FpsForm'] = $data;
                redirect('/foto/edit_form/');
            }
        }

		
		//clean cache
		$this->Cache->clean(CACHE_MATCHING_TAG, array('module_foto', 'record_id_' . $id));
		$this->DB->cleanSqlCache();
		if ($this->Log) $this->Log->write('editing foto', 'foto id(' . $id . ')');
		return $this->showInfoMessage(__('Operation is successful'), '/foto/' );
	}



	/**
	 * Check user access and if all right
	 * delete record with geting ID.
	 *
	 * @param int $id
	 */
	public function delete($id = null) {		
		$id = intval($id);
		if ($id < 1) redirect('/');
		
		
		$entity = $this->Model->getById($id);
		if (!$entity) return $this->showInfoMessage(__('Some error occurred'), '/foto/' );


		if (!$this->ACL->turn(array('foto', 'delete_materials'), false) 
		&& (empty($_SESSION['user']['id']) || $entity->getAuthor_id() != $_SESSION['user']['id'] 
		|| !$this->ACL->turn(array('foto', 'delete_mine_materials'), false))) {
			return $this->showInfoMessage(__('Permission denied'), '/foto/' );
		}
		
		
		$entity->delete();

		//clean cache
		$this->Cache->clean(CACHE_MATCHING_TAG, array('module_foto'));
		$this->DB->cleanSqlCache();
		if ($this->Log) $this->Log->write('delete foto', 'foto id(' . $id . ')');
		return $this->showInfoMessage(__('Operation is successful'), '/foto/' );
	}


	
	/**
	* @param int $id - record ID
	*
	* update date by record also up record in recods list
	*/
	public function upper($id) {
		$this->ACL->turn(array('foto', 'up_materials'));
		$entity = $this->Model->getById($id);
		$entity->setDate(date("Y-m-d H:i:s"));
		$entity->save();
		return $this->showInfoMessage(__('Operation is successful'), '/foto/');
	}
	

	
	/**
	* @param array $record - record from database
	* @return string - admin buttons
	*
	* create and return admin bar
	*/
	protected function _getAdminBar($record) {
		$moder_panel = '';
        $uid = $record->getAuthor_id();
        $id = $record->getId();


		if ($this->ACL->turn(array($this->module, 'edit_materials'), false) 
		|| (!empty($_SESSION['user']['id']) && $uid == $_SESSION['user']['id']
		&& $this->ACL->turn(array($this->module, 'edit_mine_materials'), false))) {
			$moder_panel .= get_link('', '/' . $this->module . '/edit_form/' . $id, array('class' => 'fps-edit')) . '&nbsp;';
		}
		
		if ($this->ACL->turn(array($this->module, 'up_materials'), false)) {
			$moder_panel .= get_link('', '/' . $this->module . '/upper/' . $id,
				array('class' => 'fps-up', 'onClick' => "return confirm('" . __('Are you sure') . "')")) . '&nbsp;';
		}
		
		
		if ($this->ACL->turn(array($this->module, 'delete_materials'), false) 
		|| (!empty($_SESSION['user']['id']) && $uid == $_SESSION['user']['id']
		&& $this->ACL->turn(array($this->module, 'delete_mine_materials'), false))) {
			$moder_panel .= get_link('', '/' . $this->module . '/delete/' . $id,
				array('class' => 'fps-delete', 'onClick' => "return confirm('" . __('Are you sure') . "')")) . '&nbsp;';
		}
		
		return $moder_panel;
	}	
	

	
	protected function _getValidateRules()
	{
		$max_attach = Config::read('max_attaches', $this->module);
		if (empty($max_attach) || !is_numeric($max_attach)) $max_attach = 5;
		$rules = array(
			'add' => array(
				'title' => array(
					'required' => true,
					'max_lenght' => 250,
					'title' => 'Title',
				),
				'cats_selector' => array(
					'required' => true,
					'pattern' => V_INT,
					'max_lenght' => 11,
					'title' => 'Category',
				),
				'main_text' => array(
					'required' => 'editable',
					'max_lenght' => Config::read('description_lenght', 'foto'),
				),
				'files__foto' => array(
					'required' => true,
					'type' => 'image',
					'max_size' => Config::read('max_file_size', 'foto'),
				),
			),
			'update' => array(
				'title' => array(
					'required' => true,
					'max_lenght' => 250,
					'title' => 'Title',
				),
				'cats_selector' => array(
					'required' => true,
					'pattern' => V_INT,
					'max_lenght' => 11,
					'title' => 'Category',
				),
				'main_text' => array(
					'required' => 'editable',
					'max_lenght' => Config::read('description_lenght', 'foto'),
                    'title' => 'Description',
				),
				'files__foto' => array(
					'required' => false,
					'type' => 'image',
					'max_size' => Config::read('max_file_size', 'foto'),
				),
			),
		);
		
		return $rules;
	}


    /**
     * Try to save file
     *
     * @param $file array (From POST request)
     */
    private function __saveFile($file, $id)
    {
        /**
         * We doesn't check an file extension here
         * because it was doing above in the Validator.
         * That's why we could be sure that $file is image.
         */

        $ext = strtolower(strchr($file['name'], '.'));
        $file_name = $id . $ext;

        $files_dir = ROOT . '/sys/files/' . $this->module . '/';
        $path = getSecureFilename($file_name, $files_dir);


        // Перемещаем файл из временной директории сервера в директорию files
        if (move_uploaded_file($file['tmp_name'], $files_dir . $path)) {
            chmod( $files_dir . $path, 0644 );


            // Create watermark and resample image
            $watermark_path = ROOT . '/sys/img/' .
                (Config::read('watermark_type') == '1'
                    ? 'watermark_text.png' : Config::read('watermark_img'));

            if (Config::read('use_watermarks') && !empty($watermark_path) && file_exists($watermark_path)) {
                $waterObj = new FpsImg;
                $waterObj->createWaterMark($files_dir . $path, $watermark_path);
            }

            return $path;
        }

        return false;
    }


	public function set_rating($id = null)
    {
		include_once(ROOT . '/sys/inc/includes/set_rating.php');
	}	
}
