<?php

namespace RocketChat\Livechat;

use Httpful\Request;
use RocketChat\Client;

class User extends Client {
	
    public $remoteData;
    
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

    }


}
