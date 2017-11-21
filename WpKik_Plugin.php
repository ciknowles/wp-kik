<?php


include_once('WpKik_LifeCycle.php');
include_once('WpKik_Bot.php');



class WpKik_Plugin extends WpKik_LifeCycle {
	
	public function write_log ( $log )  {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}
	
    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return array of option meta data.
     */
    public function getOptionMetaData() {
        //  http://plugin.michael-simpson.com/?page_id=31
        return array(
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            'BotName' => array(__('BotName', '')),
        	'BotAPIKey' => array(__('BotAPIKey', '')),        		
       		'BotPrune' => array(__('Save Message History for', ''),
					'Forever', '365 Days', '165 Days', '30 Days', '7 Days', '1 Day'),
        );
    }

//    protected function getOptionValueI18nString($optionValue) {
//        $i18nValue = parent::getOptionValueI18nString($optionValue);
//        return $i18nValue;
//    }

    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key, $arr[1]);
                }
            }
        }
    }

    public function getPluginDisplayName() {
        return 'WP Kik';
    }

    protected function getMainPluginFileName() {
        return 'wp-kik.php';
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Called by install() to create any database tables if needed.
     * Best Practice:
     * (1) Prefix all table names with $wpdb->prefix
     * (2) make table names lower case only
     * @return void
     */
    protected function installDatabaseTables() {
       	$this->write_log("installing db");        
	
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
	
		$table_name = $this->prefixTableName('msg');				
		$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		who tinytext NOT NULL,
		utc  datetime NOT NULL,
		chatid tinytext NOT NULL,
		isout tinyint NOT NULL,
		payload text NOT NULL,
		PRIMARY KEY (id),
		INDEX (who(20), utc)
		) $charset_collate;";
		
		$wpdb->query($sql);

		$table_name = $this->prefixTableName('util');				
		$sql = "CREATE TABLE $table_name (
		    id bigint(20) NOT NULL AUTO_INCREMENT,
		    keyname tinytext NOT NULL,
		    value text NOT NULL,
		PRIMARY KEY  (id),
		INDEX(keyname(100))
		) $charset_collate;";
		
		$wpdb->query($sql);
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    protected function unInstallDatabaseTables() {
       $this->write_log("Removing database...");
		
       global $wpdb;
       $tableName = $this->prefixTableName('msg');
       $wpdb->query("DROP TABLE IF EXISTS $tableName");
       
       $tableName = $this->prefixTableName('util');
       $wpdb->query("DROP TABLE IF EXISTS $tableName");
    }


    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
    }

    public function addActionsAndFilters() {

        // Add options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));
        

        // Example adding a script & style just for the options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        //        if (strpos($_SERVER['REQUEST_URI'], $this->getSettingsSlug()) !== false) {
        //            wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
        //            wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        }


        // Add Actions & Filters
        // http://plugin.michael-simpson.com/?page_id=37
        
        //do_action( "update_option_{$option}", mixed $old_value, mixed $value, string $option )
        
        add_action('botprune', array(&$this, 'do_prune'));

        
		$option = $this->prefix('BotName');
        add_action('update_option_'.$option, array(&$this, 'bot_configure'));
        $option = $this->prefix('BotAPIKey');
        add_action('update_option_'.$option, array(&$this, 'bot_configure'));

   
        // Adding scripts & styles to all pages
        // Examples:
        //        wp_enqueue_script('jquery');
        //        wp_enqueue_style('my-style', plugins_url('/css/my-style.css', __FILE__));
        //        wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));


        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39


        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41
         if (''!=$this->getOption('BotName')) {
            add_action('wp_ajax_'.$this->getOption('BotName'), array(&$this, 'ajaxACTION'));
            add_action('wp_ajax_nopriv_'.$this->getOption('BotName'), array(&$this, 'ajaxACTION')); 
            
         //   add_action('kik_in', array(&$this, 'kik_in'), 10, 2);
          //  add_action('kik_out', array(&$this, 'kik_out'), 10, 2);
         }
    }
    
    
    function do_prune() {
		global $wpdb;
		
		$dur = explode(' ', $this->getOption("BotPrune", "Forever"));
		
		if (count($dur)<2) {
			$dur = 99999;
		}
		else {
			$dur = intval($dur[0]);
		}
		
		/*
	    $table_name = $this->prefixTableName('data');

		$sql = "DELETE from $table_name WHERE DATEDIFF(NOW(),utc) > $dur;";
		$this->write_log($sql);			
		$wpdb->query($sql);										*/
	}    

   
    public function activate() {
    		$this->unInstallDatabaseTables();
    		$this->installDatabaseTables();
		$this->write_log("activate");
	
	    if (! wp_next_scheduled ( 'botprune' )) {
			wp_schedule_event(time(), 'daily', 'botprune');
	    }

    }
    
    public function deactivate() {
		wp_clear_scheduled_hook('botprune');
    }
    
    public function bot_configure() {
    	
    	$this->write_log('configuring');
    	 
    	if ((''==$this->getOption('BotName','')) || (''==$this->getOption('BotAPIKey',''))) {
    		return;
    	}
    	
    	$bot = new KikBot($this, $this->getOption('BotName'), $this->getOption('BotAPIKey'));
    	
    	$this->write_log($this->getAjaxUrl($this->getOption('BotName')));
    	
    	$response =  	$bot->setConfiguration($this->getAjaxUrl($this->getOption('BotName')), [
    			'manuallySendReadReceipts' => false,
    			'receiveReadReceipts' => false,
    			'receiveIsTyping' => false,
    			'receiveDeliveryReceipts' => false
    	]);
    	
    	$response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ($response_code==200) {
            $this->write_log('BotConfiguration: SUCCESS');
           
        }
        else {
             $this->write_log(var_export($response));
        }
    }
    
    public function ajaxACTION() {
        $inputJSON = file_get_contents('php://input');
        
        $bot = new KikBot($this, $this->getOption('BotName'), $this->getOption('BotAPIKey'));
        
        $this->write_log(var_export(json_decode($inputJSON), true));
        $bot->kik_in($inputJSON);
    
        wp_die();
    }
    
    
   
    
}
