<?php
/*---------------------------------------------\
|											   |
| @Author:       Andrey Brykin (Drunya)        |
| @Version:      1.0                           |
| @Project:      CMS                           |
| @Package       AtomX CMS                     |
| @subpackege    UsersVotes Model              |
| @copyright     ©Andrey Brykin 2010-2012      |
| @last mod      2012/05/19                    |
|----------------------------------------------|
|											   |
| any partial or not partial extension         |
| CMS AtomX,without the consent of the         |
| author, is illegal                           |
|----------------------------------------------|
| Любое распространение                        |
| CMS AtomX или ее частей,                     |
| без согласия автора, является не законным    |
\---------------------------------------------*/



/**
 *
 */
class UsersVotesModel extends FpsModel
{
	public $Table  = 'users_votes';

    protected $RelatedEntities = array(
		'touser' => array(
            'model' => 'Users',
            'type' => 'has_one',
            'internalKey' => 'to_user',
		),
		'fromuser' => array(
            'model' => 'Users',
            'type' => 'has_one',
            'internalKey' => 'from_user',
		),
	);
	

	
	
	public function deleteUserWarnings($id)
	{
		$Register = Register::getInstance();
		$votes = $this->getCollection(array('user_id' => $id));
		if (!empty($votes)) {
			foreach ($votes as $vote) {
				$vote->delete();
			}
		}
	}
}