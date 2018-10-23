<?php

namespace RocketChat;

use Httpful\Request;

class Client{

    public $api;

    public $adminAuthTemplate;
    public $runningAsUser;

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
//        return $this->api . $route . ((count($arguments)) ? '?'.http_build_query($arguments, null, null, PHP_URL_QUERY) : '');
        return $this->api . $route . ((count($arguments)) ? '?'.http_build_query($arguments, null, '&', PHP_QUERY_RFC3986) : '');
    }


    /**
     * Get version information. This simple method requires no authentication.
     */
    public function version() {
        if(!empty($this->apiVersion)) return $this->apiVersion;
        $response = Request::get( $this->getUrl('info') )->send();
        $this->apiVersion = $response->body->info->version;
        return $this->apiVersion;
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

    public function runAsUser($user, $callback) {
        //auth as user
        $this->runningAsUser = true;
        $user->loginByToken();

        $callback();

        //restore
        $this->runningAsUser = false;
        Request::ini( $this->adminAuthTemplate );
    }


    /**
     * List all of the users and their information.
     *
     * Gets all of the users in the system and their information, the result is
     * only limited to what the callee has access to view.
     */
    public function loadUsers($query = []){
        if(!count($query)) $query = ['type' => 'user']; // default filter
        $arguments = [
            'count' => 0,
            'query' => json_encode($query),
        ];
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
        $result = [];
        $list = $this->loadUsers($query);
        if(!$list) return $result;

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
    public function loadGroups() {
        $response = Request::get($this->getUrl('groups.list') )->send();
        if( $response->code != 200 || !isset($response->body->success) || !$response->body->success ) {
            $this->lastError = $response->body->error;
            return false;
        }
        return $response->body->groups;
    }

    public function getGroups()
    {
        $result = [];
        $list = $this->loadGroups();
        if(!$list) return $result;

        foreach($list as $group){
            $result[] = new Model\Group($group);
        }
        return $result;

    }

    /**
     * Room Members
     *
     * @param $type
     * @param $id
     * @return bool
     * @throws \Exception
     */
    public function loadRoomMembers($type, $id) {
        $routesByType = [
            'channel' => 'channels.members',
            'dm' => 'dm.members',
        ];
        $route = isset($routesByType[$type]) ? $routesByType[$type] : null;
        if(!$route) {
            throw new \Exception('Route to load room members is not defined for type '.$type);
        }

        $arguments = [
            'roomId' => $id,
            'sort' => json_encode(['username' => 1]),
        ];
        $response = Request::get($this->getUrl($route, $arguments))->send();

        if($response->code != 200 || !isset($response->body->success) || $response->body->success != true ) {
            $this->lastError = $response->body->error;
            return false;
        }
        return $response->body->members;
    }

    public function getRoomMembers($type, $id) {
        $list = $this->loadRoomMembers($type, $id);
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
     * Load Rooms : Channels (all on the server)
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
            $channel = new Model\Room\Channel();
            $channel->setRemoteData($channelData);
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

    /**
     * @param $id
     * @return array|bool
     */
    public function getChannelMembers($id) {
        return $this->getRoomMembers('channel', $id);
    }


    /**
     * Load Rooms : Direct Messages
     * @param array $query
     * @param bool $currentUser
     * @return array|bool
     */
    public function loadDms($query = [], $currentUser = false) {
        $arguments = ['count' => 0,];
        if($query) $arguments['query'] = json_encode($query);

        $route = ($currentUser) ? 'dm.list' : 'dm.list.everyone';
        $url = $this->getUrl($route, $arguments);
        $response = Request::get($url)->send();

        if( !$response->code == 200 || !isset($response->body->success) || !$response->body->success) {
            $this->lastError = $response->body->error;
            return false;
        }
        return $response->body->ims;
    }

    /**
     * @param array $filter
     * @param bool $currentUser
     * @return array
     */
    public function getDms($filter = [], $currentUser = false) {
        $list = $this->loadDms($filter, $currentUser);

        $result = [];
        foreach($list as $dmData) {
            $dm = new Model\Room\Dm();
            $dm->setRemoteData($dmData);
            $result[$dm->id] = $dm;
        }
        return $result;
    }

    /**
     * @param $id
     * @return array|bool
     */
    public function getDmMembers($id) {
        return $this->getRoomMembers('dm', $id);
    }



    /**
     * List all livechat users 
     * @return array
     */
    public function loadLivechatUsers($type = Model\Livechat\User::TYPE_AGENT)
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
        if($this->allLivechatAgents && !$update) return $this->allLivechatAgents;

        $list = $this->loadLivechatUsers(Model\Livechat\User::TYPE_AGENT);
        $result = [];
        foreach($list as $item) {
            $result[$item->id] = $item;
        }

        $this->allLivechatAgents = $result;

        return $result;
    }

    /**
     * Get all livechat managers 
     * @return array
     */
    public function getAllLivechatManagers($update = false) {
        if($this->allLivechatManagers && !$update) return $this->allLivechatManagers;

        $list = $this->loadLivechatUsers(Model\Livechat\User::TYPE_MANAGER);
        $result = [];
        foreach($list as $item) {
            $result[$item->id] = $item;
        }

        $this->allLivechatManagers = $result;

        return $result;
    }

    /**
     * Load livechat departments data
     * @return array|bool
     */
    public function loadLivechatDepartments()
    {
        $response = Request::get($this->getUrl('livechat/department'))->send();
        if( !$response->code == 200 || !isset($response->body->success) || !$response->body->success) {
            $this->lastError = $response->body->error;
            return false;
        }
        return $response->body->departments;
    }

    /**
     * Get livechat departments as Models
     * @return array|bool
     */
    public function getLivechatDepartments()
    {
        $list = $this->loadLivechatDepartments();
        if(!$list) return [];

        $result = [];
        foreach($list as $livechatDepartmentData){
            $livechatDepartment = new Model\Livechat\Department();
            $livechatDepartment->setRemoteData($livechatDepartmentData);
            $livechatDepartment->loadInfo();
            $list[$livechatDepartment->id] = $livechatDepartment;
        }
        return $result;
    }

    /**
     * Get all livechat departments 
     * @return array
     */
    public function getAllLivechatDepartments($update = false) {
        if($this->allLivechatDepartments && !$update) return $this->allLivechatDepartments;

        $result = $this->getLivechatDepartments();
        if($result) $this->allLivechatDepartments = $result;

        return $result;
    }
}
