<?php

namespace RocketChat;

use Httpful\Request;

class Client{

    public $api;

    public $lastError = null;

    //static lists
    public $allUsers;
    public $allChannels;
    public $allGroups;
    public $allLivechatAgents;
    public $allLivechatManagers;
    public $allLivechatDepartments;

    public static function getInstance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new self();
        }
        return $inst;
    }

    private function __construct(){
        $this->api = ROCKET_CHAT_INSTANCE . REST_API_ROOT;

        // set template request to send and expect JSON
        $tmp = Request::init()
            ->sendsJson()
            ->expectsJson();
        Request::ini( $tmp );
    }

    public function getUrl($route, $arguments = []) {
        return $this->api . $route . ((count($arguments)) ? '?'.http_build_query($arguments) : '');
    }


    /**
     * Get version information. This simple method requires no authentication.
     */
    public function version() {
        $response = Request::get( $this->getUrl('info') )->send();
        return $response->body->info->version;
    }

    /**
     * Quick information about the authenticated user.
     */
    public function me() {
        $response = Request::get( $this->getUrl('me') )->send();

        if( $response->body->status != 'error' ) {
            if( isset($response->body->success) && $response->body->success == true ) {
                return $response->body;
            }
        } else {
            $this->lastError = $response->body->message;
            return false;
        }
    }

    /**
     * List all of the users and their information.
     *
     * Gets all of the users in the system and their information, the result is
     * only limited to what the callee has access to view.
     */
    public function list_users($query = []){
        $arguments = [
            'count' => 0,
        ];
        if($query) {
            $arguments['query'] = json_encode($query);
        }
        $url = $this->getUrl('users.list', $arguments);
        $response = Request::get($url)->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return $response->body->users;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    public function getUsers($query = [])
    {
        $list = $this->list_users($query);
        $result = [];
        foreach($list as $userData) {
            $user = new Model\User();
            $user->setRemoteData($userData);
            $result[$user->username] = $user;
        }
        return $result;
    }

    /**
     * Get all of the users and return them as \RocketChat\User array
     * @return array
     */
    public function getAllUsers($update = false, $query = []) {
        if(!empty($this->allUsers) && !$update) return $this->allUsers;

        $this->allUsers = $this->getUsers($query);

        return $this->allUsers;
    }

    /**
     * List the private groups the caller is part of.
     */
    public function list_groups() {
        $response = Request::get($this->getUrl('groups.list') )->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $groups = array();
            foreach($response->body->groups as $group){
                $groups[] = new Model\Group($group);
            }
            return $groups;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * List the channels the caller has access to.
     */
    public function loadChannels() {
        $response = Request::get($this->getUrl('channels.list'))->send();
        if($response->code != 200 || !isset($response->body->success) || $response->body->success != true ) {
            $this->lastError = $response->body->error;
            return false;
        }
        return $response->body->channels;
    }

    public function getChannels() {
        $list = $this->loadChannels();

        $result = [];
        foreach($list as $channelData) {
            $channel = new Model\Channel($channelData);
            $result[$channel->id] = $channel;
        }
        return $result;
    }

    /**
     * Get all of the channels
     * @return array
     */
    public function getAllChannels($update = false) {
        if($this->allChannels && !$update) return $this->allChannels;

        $this->allChannels = $this->getChannels();

        return $this->allChannels;
    }


    public function loadChannelMembers($id) {
        $response = Request::get($this->getUrl('channels.members', ['roomId' => $id]))->send();
        if($response->code != 200 || !isset($response->body->success) || $response->body->success != true ) {
            $this->lastError = $response->body->error;
            return false;
        }
        return $response->body->members;
    }

    public function getChannelMembers($id) {
        $list = $this->loadChannelMembers($id);
        if(!$list) return false;

        $users = $this->getAllUsers();

        $result = [];
        foreach ($list as $member) {
            if(!isset($users[$member->username])) continue;
            $result[$member->username] = $users[$member->username];
        }

        return $result;
    }



    /**
     * List all livechat users 
     * @return array
     */
    public function list_livechat_users($type = Model\Livechat\User::TYPE_AGENT)
    {
        $response = Request::get($this->getUrl('livechat/users/'.$type))->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $list = array();
            foreach($response->body->users as $livechatUserData){
                $livechatUser = new Model\Livechat\User(['type' => $type]);
                $livechatUser->setRemoteData($livechatUserData);
                $list[] = $livechatUser;
            }
            return $list;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Get all livechat agents 
     * @return array
     */
    public function getAllLivechatAgents($update = false) {
        if($this->allLivechatUsers && !$update) return $this->allLivechatAgents;

        $list = $this->list_livechat_users(Model\Livechat\User::TYPE_AGENT);
        $result = [];
        foreach($list as $item) {
            $result[$item->id] = $item;
        }

        $this->allLivechatUsers = $result;

        return $result;
    }

    /**
     * Get all livechat managers 
     * @return array
     */
    public function getAllLivechatManagers($update = false) {
        if($this->allLivechatManagers && !$update) return $this->allLivechatManagers;

        $list = $this->list_livechat_users(Model\Livechat\User::TYPE_MANAGER);
        $result = [];
        foreach($list as $item) {
            $result[$item->id] = $item;
        }

        $this->allLivechatManagers = $result;

        return $result;
    }

    /**
     * List all livechat users 
     * @return array
     */
    public function list_livechat_departments()
    {
        $response = Request::get($this->getUrl('livechat/department'))->send();
        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $list = array();
            foreach($response->body->departments as $livechatDepartmentData){
                $livechatDepartment = new Model\Livechat\Department();
                $livechatDepartment->setRemoteData($livechatDepartmentData);
                $livechatDepartment->loadInfo();
                $list[] = $livechatDepartment;
            }
            return $list;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Get all livechat departments 
     * @return array
     */
    public function getAllLivechatDepartments($update = false) {
        if($this->allLivechatDepartments && !$update) return $this->allLivechatDepartments;

        $list = $this->list_livechat_departments();
        $result = [];
        foreach($list as $item) {
            $result[$item->id] = $item;
        }

        $this->allLivechatDepartments = $result;

        return $result;
    }
}
