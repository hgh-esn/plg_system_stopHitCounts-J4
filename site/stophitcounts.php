<?php
/*
 * @package    stopHitCounts
 * @subpackage Base
 * @author     Hans-Guenter Heiserholt [HGH] {@link moba-hgh/joomla}
 * @author     Created on 10-Oct-2017
 * @lastUpdate 21-Mai-2019
 * @version    1.3.0
 * @license    GNU/GPL
 */

//-- No direct access
defined('_JEXEC') || die('=;)');

/*
 * System Plugin.
 *
 * @package    stopHitCounts
 * @subpackage Plugin
 */
 
class plgSystemstopHitCounts extends JPlugin
{
    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array $config  An array that holds the plugin configuration
     */
    public function __construct(& $subject, $config)
    {
      parent::__construct($subject, $config);
      $this->loadLanguage();

      // load the plugin parameters
      
      if(!isset($this->params))
      {
         $plugin       = JPluginHelper::getPlugin('system', 'stophitcounts');
         $this->params = new JRegistry($plugin->params);
      }

    } // end-function-construct

    /**
     *  onContentBeforeDisplay
	 *
	 *  V1.2.1  190507  changed  $imitstart=0  because of errors in content->media
     */

	public  function onContentBeforeDisplay($context, &$article, &$params, $limitstart=0)
	{
		/***********************************
		 * get act. UserData as objekt
		 ***********************************/
		$user 	  	= JFactory::getUser();
 
		/**********************************************
		 * First of all, we check if it is a bot-access
		 * Then the counter is decremented because there 
		 * was already a hit
		 **********************************************/    
		if ( $this->params->get('disable_bots') )
		{
			$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
		 
		 // we call "checkBot" it returns true or false
			if ( $this->checkBot($user_agent) !== false )
			{        
				// the bot hasn't already set the hitCounter - we don't decrement
				$msg = '[Bot]user - noHitCount for artid/hits=' .$article->id .'/' .$article->hits;
			 
 				if ( $this->params->get('log_active') )
 				{
 					JLog::add($msg);
 				}
				return;
			}               
		}

		/***************************************
		 * check user(s) to ignore for counting
		 ***************************************/        
		if ( $this->params->get('disabled_users') )
		{
			if ( in_array($user->id, $this->params->get('disabled_users')) )
			{
				$msg = 'loggedIn-user= ' .$user->id .' is blocked from counting.';

				if ( $this->params->get('log_active') )
				{
					JLog::add($msg);
				}
				
				if ( $context == 'com_content.article' )
				{
 					$this->decrHitCounter($this->params->get('log_active'),$article->id,$article->hits);
				}
			//	else /* do nothing */
				
				return;
			}
		}     

		/***************************************
		 * Check group(s) to ignore for counting
		 ***************************************/		
		
		if ( $this->params->get('disabled_groups') )
		{
			foreach ( $this->params->get('disabled_groups') as $key => $group ) 
			{         
				if ( in_array( $group , $user->getAuthorisedGroups() ) )  
				{
					$msg ='loggedIn-user= ' .$user->id .' of noncounting - group.';

					if ( $this->params->get('log_active') )
					{
						JLog::add($msg);
					}
					
					if ( $context == 'com_content.article' )
					{
  						$this->decrHitCounter($this->params->get('log_active'),$article->id,$article->hits);
					}
				//	else /* do nothing */
					
					return; 
				}
			}
		}
		 
		/**************************************************
		 * Check if public / registrated-user updates article
		 **************************************************/       
		if 	( $context  == 'com_content.article' AND 
				( 		$user->groups[2] == 2   /* registrated */ 
					||	$user->id 	 	 == 0	/* public      */
				)
			)   
		{
			$sep  	= ',';							// separator  
			$name 	= 'HitCnt-Qookie';
			$pov 	= $this->params->get('qookie_pov');	// period of validity for qookie
			
			if ( isset($_COOKIE[$name]) )   // qookie exists ? 
			{
				$val = $_COOKIE[$name];     // get qookie-value 
				if ( strpos($val,$article->id) === false ) 
				{
 					$val .= $article->id .$sep;
					setcookie($name, $val, time()+$pov, $path='/');     			/* verfällt in x Stunden */
				}
				else 
				{					
					$msg = '[public/registrated]user - noHitcount because of reloading article[' .$article->id .'].';

					$this->decrHitCounter($this->params->get('log_active'),$article->id,$article->hits);
					
					if ( $this->params->get('log_active') )
					{
						JLog::add($msg);
					}          
				}
			}
			else
			{
				setcookie($name, $article->id .$sep, time()+$pov, $path='/');	/* verfällt in x Stunden */
			}

			return;
		}

		/********************************************************
		 * ignore counting in non-article area
		 ********************************************************/      
		if ( $context != 'com_content.article' )
		{
			/* no correction of hitCounter, because joomla hasn't already count itself.*/
			$msg = date('H:i:s', time()) .' - no counting in non-article areas for area/article/hits - ' .$context .'/' .$article->id .'/' .$article->hits;

			if ( $this->params->get('log_active') )
			{
				JLog::add($msg);
			}
			return;
		}

		/*******************************************************
		 * Check if loggedIn user matches a self-created article
		 *******************************************************/    
		if ( $this->params->get('disable_selfcreated_only') )
		{
			if ( $context  == 'com_content.article' && $user->id == $article->created_by )
			{
				$msg = 'loggedIn-user=' .$user->id .' matched >created_by< [' .$article->id .'] - no counting.';

				if ( $this->params->get('log_active') )
				{
                  JLog::add($msg);
				}
				
				if ( $context == 'com_content.article' )
				{
 					$this->decrHitCounter($this->params->get('log_active'),$article->id,$article->hits);
				}
			//	else /* do nothing */
				return;      
			}
		}     
	}// end-function onContentBeforeDisplay

	/**
	 * Method to decrement the Hitcounter
     * @access private
     * @param  -
     * @use    article->hits, article->id, 
     * @return true - when hit-counter before decrementation is > 0, false - when hit-counter = 0
     * @since 1.0.0
     */
    
	private function decrHitCounter($log_active,$id,$hits)
//	public function decrHitCounter($log_active,$id,$hits)
	{
		if ( $hits > 0 )
		{       
			/****************************************************************************************
			 * we decrement the article-hitconter because it is already incremented by joomla before
			 ****************************************************************************************/
			$db = JFactory::getDbo();
			$db->setQuery('UPDATE #__content SET hits = hits - 1 WHERE id = ' .$id);
			$db->execute();

//  depriciated in J4
//			if ( $db->getErrorNum() ) 
//			{
//				$msg = $db->getErrorMsg();
//
//				if ( $log_active )
//				{
//					JLog::add($msg);
//				} 
//				return false;
//			}
			
            $article->hits = $hits-1;
			
			$msg = 'decrHitCounter - [art-id/hits]=' .$id .'/' .$article->hits;
			
			if ( $log_active )
			{
				JLog::add($msg);
			}

			return true;
		}
		else
		{         
			$msg = 'decrHitCounter - no decm. hitCounter, because of ZERO hits in article/hits] =' .$id .'/'.$hits;

			if ( $log_active )
			{
				JLog::add($msg);
			}
			return false;
		}
	}
	
	private function logHitCounter($log_active,$id,$nr)
	{
		// for testing only 
		
		$db =JFactory::getDBO();
		$query 	= "SELECT hits FROM #__content WHERE id=" .$id;    
		$db->setQuery($query);
		$hits 	=  $db->loadResult();

//  depriciated in J4

//		if ( $db->getErrorNum() ) 
//		{
//			$msg = $db->getErrorMsg();
//
//			if ( $log_active )
//			{
//				JLog::add($msg);
//			} 
//			return false;
//		}
      		
		$msg = '- db-logHitCounter[id/hits] = ' .$id .'/' .$hits .'[ ' .$nr .']';

		if ( $log_active )
		{
			JLog::add($msg);
		} 
          
		return $hits;
	}
     
	/************************************************
     * Method to check if the user agent is a bot
     * @access private
     * @param $user_agent string The user agent data
     * @return bool 
	 * 		true  if 	match -> is  a bot 
	 * 		false if no match -> not a bot
     * @since 1.0.0
     ************************************************/

	private function CheckBotDetails($botarray, $user_agent, $seq, $logparm)
	{ 
		for($i=0; $i <= count($botarray); $i++)
		{
			if ( stristr($user_agent, $botarray[$i]) ) 
			{
				$msg = $seq .'-Bot-found=' .$botarray[$i];

				if ( $logparm )
				{
					JLog::add($user_agent);
					JLog::add($msg);
				}
				return true;        
			}
		} 
	 	return false;
	}

	private function checkBot($user_agent)
	{
	 	$ret = false;
		
		/****************************************************************************************** 
		 * for user_agent details see:
		 * https://github.com/monperrus/crawler-user-agents/blob/master/crawler-user-agents.json
		 * http://www.useragentstring.com/pages/useragentstring.php 
		 *
		 ******************************************************************************************/
		$checkbotsfirst = array('AhrefsBot','Bingbot','Googlebot','dotbot','DuckDuckBot','mj12bot',
								'obot','Yahoo! Slurp','Yahoo! Slurp China'
							   );
		$checkbotsfirst = array_map('strtolower',$checkbotsfirst);
		
		/**************************************************
		 * first check for most used bots - to get best performance
		 * if nothing found, check for other bots by details
		 ***************************************************/
		$ret = self::CheckBotDetails( $checkbotsfirst, $user_agent,'#',$this->params->get('log_active') );  // returns 'false' or 'true'

//		if ($ret !== false)   // could reset hitcounts
		if ($ret != false) 
		{
			return true;
		}
		
		/***********************************************************
		 * get the comma-seperated custom bots string from the 
		 * plugin configuration and put them into a table
         **********************************************************/
		$custom_bots = explode(',', $this->params->get('custom_bots'));

		/*************************************************************************
         * array of most common known robots
         * Note: The original array contains a bot named 'Web'.
         *       That interferres with the normal browser HTTP_USER_AGENT-string
         *       I deleted it in the array.
         *************************************************************************/
		 
		/* since: first time created */
		
		$bots = array('bingbot', 'msn', 'abacho', 'abcdatos', 'abcsearch', 'acoon', 'adsarobot', 'aesop', 'ah-ha',
         'alkalinebot', 'almaden', 'altavista', 'antibot', 'anzwerscrawl', 'aol', 'search', 'appie', 'arachnoidea',
         'araneo', 'architext', 'ariadne', 'arianna', 'ask', 'jeeves', 'aspseek', 'asterias', 'astraspider', 'atomz',
         'augurfind', 'backrub', 'baiduspider', 'bannana_bot', 'bbot', 'bdcindexer', 'blindekuh', 'boitho', 'boito',
         'borg-bot', 'bsdseek', 'christcrawler', 'computer_and_automation_research_institute_crawler', 'coolbot',
         'cosmos', 'crawler', 'crawler@fast', 'crawlerboy', 'cruiser', 'cusco', 'cyveillance', 'deepindex', 'denmex',
         'dittospyder', 'docomo', 'dogpile', 'dtsearch', 'elfinbot', 'entire', 'esism', 'artspider', 'exalead',
         'excite', 'ezresult', 'fast', 'fast-webcrawler', 'fdse', 'felix', 'fido', 'findwhat', 'finnish', 'firefly',
         'firstgov', 'fluffy', 'freecrawl', 'frooglebot', 'galaxy', 'gaisbot', 'geckobot', 'gencrawler', 'geobot',
         'gigabot', 'girafa', 'goclick', 'goliat', 'googlebot', 'griffon', 'gromit', 'grub-client', 'gulliver',
         'gulper', 'henrythemiragorobot', 'hometown', 'hotbot', 'htdig', 'hubater', 'ia_archiver', 'ibm_planetwide',
         'iitrovatore-setaccio', 'incywincy', 'incrawler', 'indy', 'infonavirobot', 'infoseek', 'ingrid', 'inspectorwww',
         'intelliseek', 'internetseer', 'ip3000.com-crawler', 'iron33', 'jcrawler', 'jeeves', 'jubii', 'kanoodle',
         'kapito', 'kit_fireball', 'kit-fireball', 'ko_yappo_robot', 'kototoi', 'lachesis', 'larbin', 'legs',
         'linkwalker', 'lnspiderguy', 'look.com', 'lycos', 'mantraagent', 'markwatch', 'maxbot', 'mercator', 'merzscope',
         'meshexplorer', 'metacrawler', 'mirago', 'mnogosearch', 'moget', 'motor', 'muscatferret', 'nameprotect',
         'nationaldirectory', 'naverrobot', 'nazilla', 'ncsa', 'beta', 'netnose', 'netresearchserver', 'ng/1.0',
         'northerlights', 'npbot', 'nttdirectory_robot', 'nutchorg', 'nzexplorer', 'odp', 'openbot', 'openfind',
         'osis-project', 'overture', 'perlcrawler', 'phpdig', 'pjspide', 'polybot', 'pompos', 'poppi', 'portalb',
         'psbot', 'quepasacreep', 'rabot', 'raven', 'rhcs', 'robi', 'robocrawl', 'robozilla', 'roverbot', 'scooter',
         'scrubby', 'search.ch', 'search.com.ua', 'searchfeed', 'searchspider', 'searchuk', 'seventwentyfour',
         'sidewinder', 'sightquestbot', 'skymob', 'sleek', 'slider_search', 'slurp', 'solbot', 'speedfind', 'speedy',
         'spida', 'spider_monkey', 'spiderku', 'stackrambler', 'steeler', 'suchbot', 'suchknecht.at-robot', 'suntek',
         'szukacz', 'surferf3', 'surfnomore', 'surveybot', 'suzuran', 'synobot', 'tarantula', 'teomaagent', 'teradex',
         't-h-u-n-d-e-r-s-t-o-n-e', 'tigersuche', 'topiclink', 'toutatis', 'tracerlock', 'turnitinbot', 'tutorgig',
         'uaportal', 'uasearch.kiev.ua', 'uksearcher', 'ultraseek', 'unitek', 'vagabondo', 'verygoodsearch', 'vivisimo',
         'voilabot', 'voyager', 'vscooter', 'w3index', 'w3c_validator', 'wapspider', 'wdg_validator', 'webcrawler',
         'webmasterresourcesdirectory', 'webmoose', 'websearchbench', 'webspinne', 'whatuseek', 'whizbanglab', 'winona',
         'wire', 'wotbox', 'wscbot', 'www.webwombat.com.au', 'xenu', 'link', 'sleuth', 'xyro', 'yahoobot', 'yahoo!',
		 'yandex', 'yellopet-spider', 'zao/0', 'zealbot', 'zippy', 'zyborg', 'mediapartners-google'
		);
		
		/* see: http://www.useragentstring.com/pages/useragentstring.php */
		/* since: 190505 */
		
		$bots1 = array('ABACHOBot','Accoona-AI-Agent','AddSugarSpiderBot','AnyApexBot','Arachmo','B-l-i-t-z-B-O-T',
		'Baiduspider','BecomeBot','BeslistBot','BillyBobBot','Bimbot','Bingbot','BlitzBOT','boitho.com-dc','boitho.com-robot',
		'btbot','CatchBot','Cerberian Drtrs','Charlotte','ConveraCrawler','cosmos','Covario IDS','DataparkSearch','DiamondBot',
		'Discobot','Dotbot','EARTHCOM.info','EmeraldShield.com WebBot','envolk[ITS]spider','EsperanzaBot','Exabot',
		'FAST Enterprise Crawler','FAST-WebCrawler','FDSE robot','FindLinks','FurlBot','FyberSpider','g2crawler',
		'Gaisbot','GalaxyBot','genieBot','Gigabot','Girafabot','Googlebot-Image','GurujiBot','HappyFunBot',
		'hl_ftien_spider','Holmes','htdig','iaskspider','ia_archiver','iCCrawler','ichiro','igdeSpyder','IRLbot',
		'IssueCrawler','Jaxified Bot','Jyxobot','KoepaBot','L.webis','LapozzBot','Larbin','LDSpider','LexxeBot',
		'Linguee Bot','LinkWalker','lmspider','lwp-trivial','mabontland','magpie-crawler','Mediapartners-Google',
		'MJ12bot','MLBot','Mnogosearch','mogimogi','MojeekBot','Moreoverbot','Morning Paper','msnbot','MSRBot',
		'MVAClient','mxbot','NetResearchServer','NetSeer Crawler','NewsGator','NG-Search','nicebot','noxtrumbot',
		'Nusearch Spider','NutchCVS','Nymesis','oegp','omgilibot','OmniExplorer_Bot','OOZBOT','Orbiter',
		'PageBitesHyperBot','Peew','polybot','Pompos','PostPost','Psbot','PycURL','Qseero','Radian6','RAMPyBot',
		'RufusBot','SandCrawler','SBIder','ScoutJet','Scrubby','SearchSight','Seekbot','semanticdiscovery',
		'Sensis Web Crawler','SeznamBot','Shim-Crawler','ShopWiki','Shoula robot','silk','Sitebot','Snappy',
		'sogou spider','Sosospider','Speedy Spider','Sqworm','StackRambler','suggybot','SurveyBot','SynooBot','Teoma',
		'TerrawizBot','TheSuBot','Thumbnail.CZ robot','TinEye','truwoGPS','TurnitinBot','TweetedTimes Bot','TwengaBot',
		'updated','Urlfilebot','Vagabondo','VoilaBot','Vortex','voyager','VYU2','webcollage','Websquash.com','wf84',
		'WoFindeIch Robot','WomlpeFactory','Xaldon_WebSpider','yacy','YahooSeeker',
		'YahooSeeker-Testing','YandexBot','YandexImages','YandexMetrika','Yasaklibot','Yeti','YodaoBot','yoogliFetchAgent',
		'YoudaoBot','Zao','Zealbot','zspider','ZyBorg');
		
		/*******************************************
		 * Merge the arrays bots, bots1 giving bots
		 *******************************************/
			$bots = array_map('strtolower', array_unique(array_merge($bots, $bots1)));
			natcasesort($bots);
			
		if( !empty($custom_bots) )
		{
			/**************************************************
			 * prepare the array and merge with any custom bots
			 ***************************************************/        
			$bots = array_map('strtolower', array_unique(array_merge($bots, $custom_bots)));
			natcasesort($bots);
		}
		
		// and now check for bot details
		
		$ret = self::CheckBotDetails( $bots, $user_agent,'##',$this->params->get('log_active') );  // returns 'false' or 'true'
	
		return $ret;
	} // end-function
} // end-class

/* 
 * Helper for logging
 * @package    Notes
 * @subpackage com_notes
 * see: https://docs.joomla.org/Using_JLog
 */

jimport('joomla.log.log');

    // Add the logger.
    // Set the name of the log file
    // (optional) you can change the directory 

//  bringen errors !!!!
//	echo '<br />' .'param-logfile= ' .$this->params->get('log_file');
//	echo '<br />' .'param-logpath= ' .$this->params->get('log_path');

    $options = array(
		'text_file'      => 'plg_stophitcounts.log.php',            
		'text_file_path' => 'administrator/logs'
	);

// Pass the array of configuration options    

JLog::addLogger($options, JLog::INFO);
?>
