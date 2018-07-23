<?php

namespace RocketChat\Model\Livechat;

use RocketChat\Model\Base as BaseModel;

class User extends BaseModel {

    const TYPE_AGENT = 'agent';
    const TYPE_MANAGER = 'manager';

    public $id;
    public $username;
    public $type;

    public $remoteData;

    public function __construct($data = array()){
        $this->setData($data);
    }

    public function setRemoteData($data) {
        $this->remoteData = $data;

        $this->id = $this->remoteData->_id;
        $this->username = $this->remoteData->username;
    }


}
