<?php

namespace RocketChat\Model\Room;

use Httpful\Request;
use RocketChat\Model\Room as RoomModel;

class Channel extends RoomModel {

    /**
     * Force load members via separate api call as members that are sent with remote data contain already kicked ones
     * @return bool
     */
    public function isMemberLoadRequired() {
        $version = $this->getClient()->version();
        $versionIsDev = (strpos($version, 'develop') !== false);

        //in 0.59 release channels.members were added and should be used to correctly find channel members
        if((float)$version < 0.59 || ((float)$version == 0.59 && $versionIsDev)) {
            return false;
        }

        return true;
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