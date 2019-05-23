<?php

abstract class GDC_Abstract_Users
{
	abstract public function get_users();


	public function __construct($SerialNumber)
	{
		$this->SerialNumber = $SerialNumber;
	}



	public function insert_user( $userdata = array(),$usermeta =  array()){
		global $wpdb;
		
		if(!empty($userdata)){
			$user_id = wp_insert_user( $userdata );
			
			if($user_id){
				
			}
		}
	}
}