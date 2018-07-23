<?php

namespace RocketChat\Model;

use RocketChat\Client;

class Base {

    /**
     * @return null|Client
     */
    public function getClient() {
        return Client::getInstance();
    }


    /**
     * Set data in existing properties
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        foreach($data as $field => $value) {
            if(!property_exists($this, $field)) continue;
            $this->{$field} = $value;
        }
        return $this;
    }
}