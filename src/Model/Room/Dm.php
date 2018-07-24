<?php

namespace RocketChat\Model\Room;

use Httpful\Request;
use RocketChat\Model\Room as RoomModel;

class Dm extends RoomModel {

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