<?php
/*
 * @package    stopHitCounts
 * @subpackage Base
 * @author     Hans-Guenter Heiserholt [HGH] {@link moba-hgh/joomla}
 * @author     Created on 10-Oct-2017
 * @license    GNU/GPL
 */
 
defined('JPATH_BASE') or die();

class JFormFieldUserfiltering extends JFormFieldUser 
{
   public $type = 'userfiltering';
   protected function getGroups()
   {
      $groups = array();
      $groups[] = 2,8; // put here the list of the groups you want to filter
      return $groups;
   }
} 
