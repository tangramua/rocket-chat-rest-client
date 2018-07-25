<?php

namespace RocketChat\Model\Room;

use Httpful\Request;
use RocketChat\Model\Room as RoomModel;

class Dm extends RoomModel {

    /**
     * @return bool
     */
    public function isMemberLoadRequired() {
        return false; //as on 2018.07.27 'dm.members does not return any thing if running as non admin user'
        //return (bool)$this->getClient()->runningAsUser; //Force member load as separate request if running as non admin user
    }

    /**
     * Load members should be run as one of the members
     * Currently: not used
     */
    public function loadMembers() {
        if(!$this->id) return;

        $list = $this->getClient()->getDmMembers($this->id);

        if($list) $this->members = $list;
    }

    /**
     * Removes the room from the user’s list of dms
     */
    public function close(){
        $response = Request::post( $this->getClient()->getUrl('dm.close') )
            ->body(array('roomId' => $this->id))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return true;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }
}