<?php


class KikBot {
	const TYPE_GET = "get";
		const TYPE_POST = "post";
		protected $apiUrl = 'https://api.kik.com/v1';
		public $botname = null;
		protected $botkey = null;
		protected $boturl = null;
		protected $plugin = null;
		
	
		protected $features = [
				'manuallySendReadReceipts' => false,
				'receiveReadReceipts' => false,
				'receiveIsTyping' => false,
				'receiveDeliveryReceipts' => false,
		];
		
		public function __construct($plugin, $botname, $botkey, $boturl = null, $features = [
				'manuallySendReadReceipts' => false,
				'receiveReadReceipts' => false,
				'receiveIsTyping' => false,
				'receiveDeliveryReceipts' => false,
		])
		{
			$this->botname = $botname;
			$this->botkey = $botkey;
			$this->boturl = $boturl;
			$this->features = $features;
			$this->plugin = $plugin;
		}
		
		public function write_log($message) {
		    $this->plugin->write_log($message);
		
		}
		
		public function getName() {
		    return $this->botname;
		}
		
		public function kik_in($inputJSON) {
		    $data = json_decode( $inputJSON, true);    
		     //message in from kik, persist to d
            //$this->write_log('kik_in');
            
            global $wpdb;
    		try
    		{
    			$tableName = $this->plugin->prefixTableName('msg');
    			$tableName_util = $this->plugin->prefixTableName('util');
        		$utc = current_time( 'mysql', true );
    		
    		    foreach($data['messages'] as $message) 
    		    {
    		        $botname = $this->getName();
    		        $from = $message['from'];
    		        //get last message for user
    		        $lastid = $wpdb->get_var( 'SELECT value FROM '.$tableName_util.' WHERE keyname=\''.$from.'_lastid\' LIMIT 0,1' );
    		        
    		        if (is_null($lastid) ||  ($lastid!= $message['id'])) {
            			$wpdb->insert(
            					$tableName,
            					array(
            							'who' => $from,
            							'utc' => $utc,
            							'chatid' => $message['chatId'],
            							'isout' => 0,
            							'payload' => json_encode($message)
            					),
            					array(
            						
            							'%s',
            							'%s',
            							'%s',
            							'%d',
            							'%s'
            					)
            			);
            			
            			$this->delKeyValue($from.'_lastid',null);
            			$this->addKeyValue($from.'_lastid', $message['id']);
            			
    		        }
    		    }
    		    
    		}
    		catch (Exception $e) {
    			$this->plugin->write_log($e->getMessage());
    		}
		    
		    do_action('kik_in', $this, $data);
		}
		
		public function getKeyValues($key) {
		    global $wpdb;
		    $tableName_util = $this->plugin->prefixTableName('util');
		    $vals = array();
		    	    
        	foreach( $wpdb->get_results('SELECT value FROM '
		    	    .$tableName_util.' WHERE keyname=\''.$key.'\'') as $stuff => $row) {
        	    // each column in your row will be accessible like this
        	    array_push($vals, $row->value);
            }    
            return $vals;
		}
		
		public function getKeyValue($key) {
		       global $wpdb;
		    	$tableName_util = $this->plugin->prefixTableName('util');
		    	return  $wpdb->get_var( 'SELECT value FROM '
		    	    .$tableName_util.' WHERE keyname=\''.$key.'\' LIMIT 0,1');
		}
		
		public function getRandomKeyValue($key) {
		       global $wpdb;
		    	$tableName_util = $this->plugin->prefixTableName('util');
		    	return  $wpdb->get_var( 'SELECT value FROM '
		    	    .$tableName_util.' WHERE keyname=\''.$key.'\' ORDER BY RAND() LIMIT 0,1');
		}
		
		public function addKeyValue($key, $value) {
		    if (is_null($value) || ($value==='')) {
		        return;
		    }
		    
		    global $wpdb;
		    $tableName_util = $this->plugin->prefixTableName('util');
		    
	    	$wpdb->replace(
        					$tableName_util,
        					array(
        						'keyname' => $key,
        						'value' =>  $value
        					),
        					array(
        				    	'%s',
        						'%s'	
        					)
        			);
		}
		
		public function delKeyValue($key, $value) {
		    global $wpdb;
		    $tableName_util = $this->plugin->prefixTableName('util');
		    
		    if (is_null($value)|| ($value==='')) {
	    	    $wpdb->delete(
        					$tableName_util,
        					array(
        						'keyname' => $key
        					),
        					array(
        				    	'%s'	
        					)
        			);
		    }
        	else {		
        			
        			$wpdb->delete(
        					$tableName_util,
        					array(
        						'keyname' => $key,
        						'value' =>  $value
        					),
        					array(
        				    	'%s',
        						'%s'	
        					)
        			);
        	}
		}
		
		
		public function kik_out($data) {
		    
    		    //message out to kik, persist to db
             //message in from kik, persist to d
            $this->plugin->write_log('kik_out');
            
            global $wpdb;
    		try
    		{
    			$tableName = $this->plugin->prefixTableName('msg');
    			$tableName_util = $this->plugin->prefixTableName('util');
        		$utc = current_time( 'mysql', true );
    		
    		    foreach($data['messages'] as $message) 
    		    {
    		        $botname = $this->getName();
    		        $to = $message['to'];
    		        //get last message for user
    		        
    		        
            			$wpdb->insert(
            					$tableName,
            					array(
            							'who' => $to,
            							'utc' => $utc,
            							'chatid' => $message['chatId'],
            							'isout' => 1,
            							'payload' => json_encode($message)
            					),
            					array(
            						
            							'%s',
            							'%s',
            							'%s',
            							'%d',
            							'%s'
            					)
            			);
    		    }
    		}
    		catch (Exception $e) {
    			$this->plugin->write_log($e->getMessage());
    		}
    		    
		    do_action('kik_out', $this, $data);
		}
		
		public function send($messages)
		{
		    $this->kik_out($messages);
			return $this->call('message',$messages);
		}
		
		/*
		public function getUserProfile($from)
		{
			return new objects\User($this->call('user/'.$from, [], self::TYPE_GET));
		}
		*/
		
		public function setConfiguration($webhook, $features = [
				'manuallySendReadReceipts' => false,
				'receiveReadReceipts' => false,
				'receiveIsTyping' => false,
				'receiveDeliveryReceipts' => false,
		])
		{
			$this->features = $features;
			return $this->call('config', [
					'webhook' => $webhook,
					'features' => $features
			]);
		}
		
		protected function call($url, $data)
		{
			$response = wp_remote_post($this->apiUrl.'/'.$url, array(
    			'body'    => json_encode($data),
    			'headers' => array(
        		'Authorization' => 'Basic ' . base64_encode($this->botname.":".$this->botkey),
        		'Content-Type' => 'application/json'
    		),
			));
		
			return  $response;
		}
		
		/////////////////////////////
		public function make_keyboard($to,$type,$hidden,...$choices) {
		    $ret = array (
		            "hidden" => $hidden,
		            "type"=>"suggested",
		            "responses"=>array()
		        );
		    if (!is_null($to)) {
		        $ret['to'] = $to;
		    }
		    
		    foreach($choices as $choice) {
		        if (is_array($choice)) {
		        array_push($ret['responses'],  array (
		            "type"=>"text",
		            "body"=>$choice[0],
		            "metadata"=>$choice[1])
		            );
		        }
		        else {
		            array_push($ret['responses'],  array (
		            "type"=>"text",
		            "body"=>$choice)
		            );
		        }
		        
            }
            return $ret;
		}
		
}

