<?php

namespace RocketChat\Model;

use Httpful\Request;
use RocketChat\Model\Base as BaseModel;

class Channel extends BaseModel {

    public $id;
    public $name;
    public $members = [];

    public function __construct($data){

        if( is_string($data) ) {
            $this->name = $data;
        } else if( isset($data->_id) ) {
            $this->name = $data->name;
            $this->id = $data->_id;
        }

        $this->loadMembers();
    }

    public function loadMembers() {
        if(!$this->id) return;

        $list = $this->getClient()->getChannelMembers($this->id);

        if($list) $this->members = $list;
    }

    /**
     * Creates a new channel.
     */
    public function create(){
        // get user ids for members
        $members_id = array();
        foreach($this->members as $member) {
            if( is_string($member) ) {
                $members_id[] = $member;
            } else if( isset($member->username) && is_string($member->username) ) {
                $members_id[] = $member->username;
            }
        }

        $response = Request::post( $this->getClient()->getUrl('channels.create') )
            ->body(array('name' => $this->name, 'members' => $members_id))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $this->id = $response->body->channel->_id;
            return $response->body->channel;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Retrieves the information about the channel.
     */
    public function info() {
        $response = Request::get( $this->getClient()->getUrl('channels.info', ['roomId' => $this->id]))->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $this->id = $response->body->channel->_id;
            return $response->body;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Post a message in this channel, as the logged-in user
     */
    public function postMessage( $text ) {
        $message = is_string($text) ? array( 'text' => $text ) : $text;
        if( !isset($message['attachments']) ){
            $message['attachments'] = array();
        }

        $response = Request::post( $this->getClient()->getUrl('chat.postMessage') )
            ->body( array_merge(array('channel' => '#'.$this->name), $message) )
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return true;
        } else {
            if( isset($response->body->error) )	$this->lastError = $response->body->error;
            else if( isset($response->body->message) ) $this->lastError = $response->body->message;
            return false;
        }
    }

    /**
     * Removes the channel from the user’s list of channels.
     */
    public function close(){
        $response = Request::post( $this->getClient()->getUrl('channels.close') )
            ->body(array('roomId' => $this->id))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return true;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Removes a user from the channel.
     */
    public function kick( $user ){
        // get channel and user ids
        $userId = is_string($user) ? $user : $user->id;

        $response = Request::post( $this->getClient()->getUrl('channels.kick') )
            ->body(array('roomId' => $this->id, 'userId' => $userId))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return true;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

    /**
     * Adds user to channel.
     */
    public function invite( $user ) {

        $userId = is_string($user) ? $user : $user->id;

        $response = Request::post( $this->getClient()->api . 'channels.invite' )
            ->body(array('roomId' => $this->id, 'userId' => $userId))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            return true;
        } else {
            $this->lastError = $response->body->error;
            return false;
        }
    }

}

