<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Auth;

class User extends Authenticatable
{
    protected $fillable = ['full_name', 'email', 'password', 'gender', 'college_id', 'mobile'];

    function events(){
        return $this->morphToMany('\App\Event', 'registration');
    }
    function confirmation(){
        return $this->hasOne('App\Confirmation');
    }
    function payment(){
        return $this->hasOne('App\Payment');
    }
    function hasPaid(){
        if($this->payment == null){
            return false;
        }
        else{
            return  true;
        }
    }
    function payments(){
        return $this->hasMany('App\Payment', 'paid_by');
    }
    // Find the team a user has registered for an event
    function teamLeaderFor($event_id){
        $event = Event::find($event_id);
        return $event->teams->where('user_id', $this->id)->first();
    }
    // find the team for which the user has been selected as team member
    function teamMemberFor($event_id){
        $event = Event::find($event_id);
        foreach($event->teams as $team){
            if($team->teamMembers->where('user_id', $this->id)->count()){
                return $team;
            }
        }
    }
    function teamEvents(){
        $events = [];
        //  Add events in which the user is team leader
        foreach($this->teams as $team){
            array_push($events, $team->events()->first());
        }
        //  Add events in which the user is a team member        
        foreach($this->teamMembers as $teamMember){
            array_push($events, $teamMember->team->events()->first());
        }
        // Return as collections
        return collect($events);
    }
    function hasRegisteredEvent($event_id){
        $event = Event::find($event_id);
        if($event->isGroupEvent()){
            foreach($this->teams as $team){
                if($team->hasRegisteredEvent($event_id)){
                    return true;
                }
            }  
            if($this->isTeamMember($event_id)){
                return true;
            }
        }
        else{
             return $this->events()->find($event_id);      
        }
        return false;
    }
    function college(){
        return $this->belongsTo('App\College');
    }
    function teams(){
        return $this->hasMany('App\Team');
    }
    function teamMembers(){
        return $this->hasMany('App\TeamMember');
    }
    function hasConfirmed(){
        if($this->confirmation == null){
            return false;
        }
        else{
            return true;
        }
    }
    function hasUploadedTicket(){
        if($this->hasConfirmed()){
            if(!empty($this->confirmation->file_name)){
                return true;
            }
        }
        return false;
    }
    function needApproval(){
        $teamCount = $this->teams->count();
        $teamMembersCount = $this->teamMembers->count();
        // Need approval if the user is not a team leader and does not belong to any team
        if($teamCount || !$teamMembersCount){
            return true;
        }
        else{
            return false;
        }
    }
    function isTeamMember($event_id){
        return $this->teamEvents()->where('id', $event_id)->count();
    }
    function isTeamLeader($event_id){
        $event = Event::find($event_id);
        if($event->teams()->where('user_id', $this->id)->count()){
            return true;
        }
        else{
            return false;
        }
    }
    function isParticipating(){
        if($this->events()->count() == 0 && $this->teams()->count() == 0 && $this->teamMembers()->count() == 0){
            return false;
        }
        else{
            return true;
        }
    }
    function hasConfirmedTeams(){
        foreach($this->teams as $team){
            if(!$team->isConfirmed()){
                return false;
            }
        }
        return true;
    }
    function canConfirm(){
        if($this->isParticipating()){
            return true;
        }
        else{
            return false;
        }
    }
    function hasTeams(){
        $teamCount = $this->teams->count();  
        return $teamCount; 
    }
    function isAcknowledged(){
        if($this->hasConfirmed()){
            if($this->confirmation->acknowledged){
                return true;
            }
        }
        return false;
    }
    function hasPaidForTeams(){
        foreach($this->teams as $team){
            if(!$team->isPaid()){
                return false;
            }
        }
        return true;
    }
    function getTotalAmount(){
        $transactionFee = 0.04;
        $totalAmount = 0;
        $amount = 200;
        // Amount for the actual user
        if(!$this->hasPaid()){
            $totalAmount += $amount;
        }
        // Amount for teams in  which he is a leader
        foreach($this->teams as $team){
            $totalAmount += $team->getTotalAmount();
        }
        // Very Very important Add the transaction fee
        $totalAmount += $totalAmount*$transactionFee;
        return $totalAmount;
    }
    function doPayment($txnid){
        // Amount for the actual user
        if(!$this->hasPaid()){
            $payment = new Payment();
            $payment->paid_by = $this->id;
            $payment->user_id = $this->id;
            $payment->transaction_id = $txnid;
            $this->payment()->save($payment);
        }
        // Amount for teams in  which he is a leader
        foreach($this->teams as $team){
            $team->doPayment($txnid);
        }
    }
    function getTransactionId(){
        $uid = "LG17_" . $this->id . '_' . strrev(time());
        $uid = substr($uid, 0,25);
        return $uid;
    }
    function getProductInfo(){
        $productInfo = "Legacy17 Event";
        return $productInfo;
    }
    function getKey(){
        $key = "gtKFFx";
        return $key;        
    }
    function getSalt(){
        $salt = "eCwWELxi";
        return $salt;
    }
    function getHash(){
        $key = $this->getKey();
        $salt = $this->getSalt();
        $txnid = $this->getTransactionId();
        $amount = $this->getTotalAmount();
        $productInfo = $this->getProductInfo()        ;
        $firstname = $this->full_name;
        $email = $this->email;
        $hashFormat = "$key|$txnid|$amount|$productInfo|$firstname|$email|||||||||||$salt";
        $hash = strtolower(hash('sha512', $hashFormat));
        return $hash;
    }
}
