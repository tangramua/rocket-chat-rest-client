<?php

namespace RocketChat;

use Httpful\Request;

class Client{

	public $api;
    
    public $lastError = null;
    
    //static lists
    private static $allUsers;
    private static $allChannels;
    private static $allGroups;

	function __construct(){
		$this->api = ROCKET_CHAT_INSTANCE . REST_API_ROOT;

		// set template request to send and expect JSON
		$tmp = Request::init()
			->sendsJson()
			->expectsJson();
		Request::ini( $tmp );
	}

	/**
	* Get version information. This simple method requires no authentication.
	*/
	public function version() {
		$response = Request::get( $this->api . 'info' )->send();
		return $response->body->info->version;
	}

	/**
	* Quick information about the authenticated user.
	*/
	public function me() {
		$response = Request::get( $this->api . 'me' )->send();

		if( $response->body->status != 'error' ) {
			if( isset($response->body->success) && $response->body->success == true ) {
				return $response->body;
			}
		} else {
			echo( $response->body->message . "\n" );
			return false;
		}
	}

	/**
	* List all of the users and their information.
	*
	* Gets all of the users in the system and their information, the result is
	* only limited to what the callee has access to view.
	*/
	public function list_users(){
		$response = Request::get( $this->api . 'users.list' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response->body->users;
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}

    /**
     * Get all of the users and return them as \RocketChat\User array
     * @return array
     */
    public function getAllUsers($update = false) {
        if($this->allUsers && !$update) return $this->allUsers;
        
        $list = $this->list_users();
        $result = [];
        foreach($list as $userData) {
            $user = new \RocketChat\User();
            $user->setRemoteData($userData);
            $result[$user->username] = $user;
        }

        $this->allUsers = $result;
        
        return $result;
    }

	/**
	* List the private groups the caller is part of.
	*/
	public function list_groups() {
		$response = Request::get( $this->api . 'groups.list' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$groups = array();
			foreach($response->body->groups as $group){
				$groups[] = new Group($group);
			}
			return $groups;
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}

	/**
	* List the channels the caller has access to.
	*/
	public function list_channels() {
		$response = Request::get( $this->api . 'channels.list' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$groups = array();
			foreach($response->body->channels as $group){
				$groups[] = new Channel($group);
			}
			return $groups;
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
	}
    
    /**
     * Get all of the channels
     * @return array
     */
    public function getAllChannels($update = false) {
        if($this->allChannels && !$update) return $this->allChannels;
        
        $list = $this->list_channels();
        $result = [];
        foreach($list as $channel) {
            $result[$channel->id] = $channel;
        }

        $this->allChannels = $result;
        
        return $result;
    }
    
    /**
     * List all livechat users 
     * @return array
     */
    public function list_livechat_users($type = 'users')
    {
        $response = Request::get( $this->api . 'livechat/users/'.$type )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$list = array();
			foreach($response->body->users as $livechatUserData){
				$list[] = new Livechat\User($livechatUserData);
			}
			return $list;
		} else {
			echo( $response->body->error . "\n" );
			return false;
		}
    }
    
    /**
     * Get all livechat users 
     * @return array
     */
    public function getAllLivechatUsers($update = false) {
        if($this->allLivechatUsers && !$update) return $this->allLivechatUsers;
        
        $list = $this->list_livechat_users('users');
        $result = [];
        foreach($list as $channel) {
            $result[$channel->id] = $channel;
        }

        $this->allChannels = $result;
        
        return $result;
    }

}
