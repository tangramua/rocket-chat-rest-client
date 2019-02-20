<?php

namespace RocketChat\Model\Livechat;

use Httpful\Request;
use RocketChat\Model\Base as BaseModel;
use RocketChat\Model\Livechat\User;

class Department extends BaseModel {

    public $id;
    public $enabled = true;
    public $name;
    public $description = null;
    public $showOnRegistration = true;

    public $agents = [];

    public $remoteData;
    public $remoteAgentsData;


    public function __construct($data = array()){
        $this->setData($data);
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
        $response = Request::get( $this->getClient()->getUrl('livechat/department/' . $this->id) )->send();
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
            'description' => $this->description,
            ],
            'agents' => [],
            ];
        foreach($this->agents as $agent) {
            $body['agents'][] = [
                'agentId' => $agent->id,
                'username' =>  $agent->username,
                ];
        }

        $response = Request::put( $this->getClient()->getUrl('livechat/department/' . $this->id) )
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
     * @param User $user
     * @return bool
     */
    public function invite($user) {
        //check
        $livechatUser = isset($this->agents[$user->username]) ? $this->agents[$user->username] : null;
        if($livechatUser) return true;
        //add
        $this->agents[$user->username] = new User([
            'type' => User::TYPE_AGENT,
            'id' => $user->id,
            'username' => $user->username,
            ]);
        //save
        return $this->update();
    }

    /**
     * 
     * @param User $user
     * @return bool
     */
    public function kick($user) {
        //check
        $livechatUser = isset($this->agents[$user->username]) ? $this->agents[$user->username] : null;
        if(!$livechatUser) return true;
        //remove
        unset($this->agents[$user->username]);
        //save
        return $this->update();
    }


}
