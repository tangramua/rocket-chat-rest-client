<?php

namespace RocketChat\Model;

use Httpful\Request;
use RocketChat\Model\Base as BaseModel;

class Room extends BaseModel {
    const DIRECT_MESSAGE_ROOM_TYPE = 'd';
    const PUBLIC_CHANNEL_ROOM_TYPE = 'c';
    const PRIVATE_CHANNEL_ROOM_TYPE = 'p';
    const DISCUSSION_ROOM_TYPE = 'discussions';
    const TEAMS_ROOM_TYPE = 'teams';
    const LIVECHAT_ROOM_TYPE = 'l';
    const OMNICHANNEL_VOIP_ROOM_TYPE = 'v';

    public $id;
    public $name;
    public $members = [];

    /** @var array room owner */
    public $u = [];

    /** @var string room type */
    public $t = '';

    /** @var string existing room identifier */
    public $_id = '';

    public function __construct($data = [])
    {
        if( is_string($data) ) {
            $data = (object)['name' => $data];
        }
        $this->setData($data);
    }

    public function isMemberLoadRequired() {
        return false;
    }

    public function setRemoteData($data) {
        parent::setRemoteData($data);

        $this->name = $this->remoteData->name;
        $this->id = $this->remoteData->_id;


        //load members if not present in remote data, or search for members in a list of all users
        if($this->isMemberLoadRequired() || !isset($this->remoteData->usernames)) {
            $this->loadMembers();
        } elseif (!empty($this->remoteData->usernames)) {
            $users = $this->getClient()->getAllUsers();
            $this->members = array_intersect_key($users, array_combine($this->remoteData->usernames, $this->remoteData->usernames));
        }
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    public function isOwner($username)
    {
        return isset($this->u['username']) && $this->u['username'] === $username;
    }

    /**
     * @return string
     */
    public function getRoomID()
    {
        $room_id = $this->id;

        if (!$room_id) {
            $room_id = $this->_id;
        }

        return $room_id;
    }

    /**
     * Assign owner role for a user in the current private channel. But first add current admin user that is used in API requests.
     *
     * @url https://developer.rocket.chat/apidocs/invite-users-to-group
     * @url https://developer.rocket.chat/apidocs/add-group-owner
     *
     * @return bool
     */
    public function addGroupOwner($user_id)
    {
        $request = Request::init();

        $adminUserIdUserInRequest = isset($request->headers['X-User-Id'])
            ? $request->headers['X-User-Id']
            : '';

        $room_id = $this->getRoomID();

        $addUserToGroupResponse = Request::post( $this->getClient()->getUrl('groups.invite') )
            ->body([
                'roomId' => $room_id,
                'userId' => $adminUserIdUserInRequest,
            ])
            ->send();

        if($addUserToGroupResponse->code !== 200 && !isset($addUserToGroupResponse->body->success) && !$addUserToGroupResponse->body->success == true) {
            return false;
        }

        $addOwnerResponse = Request::post( $this->getClient()->getUrl('groups.addOwner') )
            ->body([
                'roomId' => $room_id,
                'userId' => $user_id
            ])
            ->send();

        if($addOwnerResponse->code == 200 && isset($addOwnerResponse->body->success) && $addOwnerResponse->body->success == true) {
            return true;
        } else {
            $this->lastError = $addOwnerResponse->body->error;
            return false;
        }
    }
}

