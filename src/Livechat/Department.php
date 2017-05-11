<?php

namespace RocketChat\Livechat;

use Httpful\Request;
use RocketChat\Client;

class Department extends Client {
    
    public $id;
    public $enabled = true;
    public $name;
    public $description = null;
    public $showOnRegistration = true;
    
    public $agents = [];
    
    public $remoteData;
    public $remoteAgentsData;
    
    
	public function __construct($data = array()){
		parent::__construct();

        $this->setData($data);
	}
    
    /**
     * Set data for user properties
     * @param array $data
     */
    public function setData(array $data)
    {
        foreach($data as $field => $value) {
            if(!property_exists($this, $field)) continue;
            $this->{$field} = $value;
        }
        return $this;
    }
    
    public function setRemoteData($data) {
        $this->remoteData = $data;

        $this->id = $this->remoteData->_id;
        $this->enabled = $this->remoteData->enabled;
        $this->name = $this->remoteData->name;
        $this->description  = $this->remoteData->description;
        $this->showOnRegistration  = $this->remoteData->showOnRegistration;
    }
    
    public function setRemoteAgentsData($data) {
        $this->remoteAgentsData = $data;
        
        $this->agents = [];
        foreach($this->remoteAgentsData as $remoteAgentData) {
            $agent = new User([
                'type' => User::TYPE_AGENT,
                'id' => $remoteAgentData->agentId,
                'username' => $remoteAgentData->username,
            ]);
            $this->agents[$agent->username] = $agent;
        }
    }
    
    public function loadInfo($update = false)
    {
        $response = Request::get( $this->api . 'livechat/department/' . $this->id )->send();
        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->setRemoteData($response->body->department);
			$this->setRemoteAgentsData($response->body->agents);
		} else {
			$this->lastError = $response->body->error;
			return false;
		}
    }
    
    public function update() 
    {
        $body = [
            'department' => [
                'enabled' => $this->enabled,
                'showOnRegistration' => $this->showOnRegistration,
                'name' => $this->name,
            ],
            'agents' => [],
        ];
        foreach($this->agents as $agent) {
            $body['agents'][] = [
                'agentId' => $agent->id,
                'username' =>  $agent->username,
            ];
        }

        $response = Request::put( $this->api . 'livechat/department/' . $this->id )
            ->body($body)
            ->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			$this->lastError = $response->body->error;
			return false;
		}
    }
    
    
    /**
     * 
     * @param \RocketChat\User $user
     * @return bool
     */
    public function invite(\RocketChat\User $user) {
        //check
        $livechatUser = isset($this->agents[$user->username]) ? $this->agents[$user->username] : null;
        if($livechatUser) return true;
        //add
        $this->agents[$agent->username] = new User([
            'type' => User::TYPE_AGENT,
            'id' => $user->id,
            'username' => $user->username,
        ]);
        //save
        return $this->update();
    }
    
    /**
     * 
     * @param \RocketChat\User $user
     * @return bool
     */
    public function kick(\RocketChat\User $user) {
        //check
        $livechatUser = isset($this->agents[$user->username]) ? $this->agents[$user->username] : null;
        if(!$livechatUser) return true;
        //remove
        unset($this->agents[$user->username]);
        //save
        return $this->update();
    }


}
