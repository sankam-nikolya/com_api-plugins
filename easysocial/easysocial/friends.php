<?php
/**
 * @package     Joomla.Site
 * @subpackage  Com_api
 *
 * @copyright   Copyright (C) 2009-2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license     GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link        http://techjoomla.com
 * Work derived from the original RESTful API by Techjoomla (https://github.com/techjoomla/Joomla-REST-API)
 * and the com_api extension by Brian Edgerton (http://www.edgewebworks.com)
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.html.html');

require_once JPATH_SITE . '/plugins/api/easysocial/libraries/mappingHelper.php';
/**
 * API class EasysocialApiResourceEvent_category
 *
 * @since  1.0
 */
class EasysocialApiResourceFriends extends ApiResource
{
	/**
	 * Method get
	 *
	 * @return  mixed
	 *
	 * @since 1.0
	 */
	public function get()
	{
		$this->plugin->setResponse($this->getFriends());
	}

	/**
	 * Method post
	 *
	 * @return  mixed
	 *
	 * @since 1.0
	 */
	public function post()
	{
		$this->plugin->setResponse($this->getFriends());
	}

	/**
	 * Method function use for get friends data
	 *
	 * @return  mixed
	 *
	 * @since 1.0
	 */
	public function getFriends()
	{
		$avt_model	=	FD::model('Avatars');
		$default	=	$avt_model->getDefaultAvatars(0, $type = SOCIAL_TYPE_PROFILES);

		// Init variable
		$app		=	JFactory::getApplication();
		$user		=	JFactory::getUser($this->plugin->get('user')->id);
		$userid		=	$app->input->get('target_user', 0, 'INT');
		$filter		=	$app->input->get('filter', null, 'STRING');
		$search		=	$app->input->get('search', '', 'STRING');
		$limit		=	$app->input->get('limit', 10, 'INT');
		$limitstart	=	$app->input->get('limitstart', 0, 'INT');

		// $options['limit']=$limit;
		// $options['limitstart']=$limitstart;

		$mapp		=	new EasySocialApiMappingHelper;

		if ($userid == 0)
		{
			$userid		=	$user->id;
		}

		$frnd_mod	=	FD::model('Friends');
		$frnd_mod->setState('limit', $limit);

		// $frnd_mod->setState('limitstart',$limitstart);
		$ttl_list	=	array();

		switch ($filter)
		{
			case 'pending':

// 						Get the total pending friends.
							$options['state']	=	SOCIAL_FRIENDS_STATE_PENDING;
							$mssg				=	JText::_('PLG_API_EASYSOCIAL_NO_PENDING_REQUESTS');
							$flag				=	0;
			break;
			case 'all':

// 						Getting all friends
							$options['state']	=	SOCIAL_FRIENDS_STATE_FRIENDS;
							$mssg				=	JText::_('PLG_API_EASYSOCIAL_NO_FRIENDS');
							$flag				=	0;
			break;
			case 'request':

// 						Getting sent requested friends.
							$options[ 'state' ]		=	SOCIAL_FRIENDS_STATE_PENDING;
							$options[ 'isRequest' ]	=	true;
							$flag					=	0;
							$mssg					=	JText::_('PLG_API_EASYSOCIAL_NOT_SENT_REQUEST');
			break;
			case 'suggest':

// 						Getting suggested friends
							$sugg_list				=	$frnd_mod->getSuggestedFriends($userid);

							foreach ($sugg_list as $sfnd)
							{
								$ttl_list[]	=	$sfnd->friend;
							}

							if (!empty($ttl_list))
							{
								$flag	=	1;
							}
							else
							{
								$flag	=	1;
								$mssg	=	JText::_('PLG_API_EASYSOCIAL_NO_SUGGESTIONS');
							}
			break;
			case 'invites':

// 						Getting invited friends
							$invites['data']	=	$frnd_mod->getInvitedUsers($userid);
							$mssg				=	JText::_('PLG_API_EASYSOCIAL_NO_INVITATION');

							if (empty($invites['data']))
							{
								$invites['data']['message']	=	$mssg;
								$invites['data']['status']	=	false;
							}

							return $invites;
			break;
		}

		//  if search word present then search user as per term and given id
		if (empty($search) && empty($ttl_list) && $flag != 1)
		{
			$ttl_list	=	$frnd_mod->getFriends($userid, $options);
		}
		elseif (!empty($search) && empty($filter))
		{
			$ttl_list	=	$frnd_mod->search($userid, $search, 'username');
		}

		if (count($ttl_list) > '0')
		{
			$frnd_list['data']		=	$mapp->mapItem($ttl_list, 'user', $userid);
			$frnd_list['data']		=	$mapp->frnd_nodes($frnd_list['data'], $user);
			$myoptions['state']		=	SOCIAL_FRIENDS_STATE_PENDING;
			$myoptions['isRequest']	=	true;
			$req					=	$frnd_mod->getFriends($user->id, $myoptions);
			$myarr					=	array();

			if (!empty($req))
			{
				foreach ($req as $ky => $row)
				{
					$myarr[]	=	$row->id;
				}
			}

			//  Get other data
			foreach ($frnd_list['data'] as $ky => $lval)
			{
				//  Get mutual friends of given user

				if ($lval->id != $user->id)
				{
					$lval->mutual	=	$frnd_mod->getMutualFriendCount($user->id, $lval->id);
					$lval->isFriend	=	$frnd_mod->isFriends($user->id, $lval->id);
					$lval->isself	=	false;

					if (in_array($lval->id, $myarr))
					{
						$lval->isinitiator	=	true;
					}
					else
					{
						$lval->isinitiator	=	false;
					}
				}
				else
				{
					$lval->mutual	=	$frnd_mod->getMutualFriendCount($userid, $lval->id);
					$lval->isFriend	=	$frnd_mod->isFriends($userid, $lval->id);
					$lval->isself	=	true;
				}
			}
		}
		else
		{
			$frnd_list['data']	=	$ttl_list;
		}

		// If data is empty givin respective message and status.

		if (count($frnd_list['data']))
		{
//  As per front developer requirement manage list

			$frnd_list['data']			=	array_slice($frnd_list['data'], $limitstart, $limit);
			$frnd_list['data_status']	=	(count($frnd_list['data']))?true:false;
		}
		else
		{
			$frnd_list['data']['message']	=	$mssg;
			$frnd_list['data_status']		=	false;
		}

		// Pending
		$frnd_list['status']['pending']		=	$frnd_mod->getTotalPendingFriends($userid);

		// All frined
		$frnd_list['status']['all']		=	$frnd_mod->getTotalFriends($userid);

		// Suggested
		$frnd_list['status']['suggest']	=	$frnd_mod->getSuggestedFriends($userid, null, true);

		// Request sent
		$frnd_list['status']['request']	=	$frnd_mod->getTotalRequestSent($userid);

		// Invited
		$frnd_list['status']['invites']		=	$frnd_mod->getTotalInvites($userid);

		return($frnd_list);
	}
}
