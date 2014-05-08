<?php
/**
 * History Log
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


/**
 * History Log plugin.
 */
class HistoryLogPlugin extends Omeka_Plugin_AbstractPlugin
{
  private $_changedElements;

    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
			      'install',
			      'uninstall',
			      'after_save_item',
			      'before_save_item',
			      'after_delete_item',
			      'admin_items_show',
			      'initialize'
			      );
    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array();
    
    public function hookInstall()
    {

        $db = get_db();

        $sql = "
            CREATE TABLE IF NOT EXISTS `$db->ItemHistoryLog` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `itemID` int(10) NOT NULL,
                `userID` int(10) NOT NULL,
                `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `type` text,
                `value` text,
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        $db->query($sql);
    }

    public function hookUninstall()
    {
      $db = get_db();
      $sql = "DROP TABLE IF EXISTS `$db->ItemHistoryLog` ";
      $db->query($sql);

    }

    public function hookInitialize()
    {
      //get_view()->addHelperPath(dirname(__FILE__) . '/views/helpers', 'HistoryLog_View_Helper_');
    }

    public function hookBeforeSaveItem($args)
    {
      $item = $args['record'];
      //if it's not a new item, check for changes
      if( !isset($args['insert']) || !$args['insert'] )
	{
	  $changedElements = $this->_findChanges($item);

	  //log item update for each changed elements
	  $this->_logItemUpdate($item->id,$changedElements);
	}
    }

    public function hookAfterSaveItem($args)
    {
      $item = $args['record'];
      if( isset($args['insert']) && $args['insert'] )
	{
	  //log new item
	  $this->_logItemCreation($item->id);
	} 
    }

    public function hookAfterDeleteItem($args)
    {
      $item = $args['record'];
      $this->_logItemDeletion($item->id);
    }

    public function hookAdminItemsShow($args)
    {
      
      //echo get_view()->showlog();
      $item = $args['item'];
      $view = $args['view'];
      //check context (SOMEHOW - get output var?). Is this an export? if so, log it!
      //then add the log display! Include view helper?

      //echo('yo');

      echo($view->showlog($item->id,4));
    }
    
    private function _logItemCreation($itemID,$source="")
    {
      $this->_logItem($itemID,'created',$source);
    }

    private function _logItemUpdate($itemID,$elements)
    {
      $this->_logItem($itemID,'updated',serialize($elements));
    }

    private function _logItemDeletion($itemID)
    {
      $this->_logItem($itemID,'deleted',NULL);
    }

    private function _logItemExport($itemID,$context)
    {
      $this->_logItem($itemID,'exported',$context);
    }

    private function _logItem($itemID,$type,$value)
    {
      $currentUser = current_user();

      if(is_null($currentUser))
	die('ERROR');

      $values = array (
		       'itemID'=>$itemID,
		       'userID' => $currentUser->id,
		       'type' => $type,
		       'value' => $value
		       );
      $db = get_db();
      $db->insert('ItemHistoryLog',$values);
    }

    private function _findChanges($item)
    {
      $newElements = $item->Elements;
      $changedElements = array();
      $oldItem = get_record_by_id('Item',$item->id);

      foreach ($newElements as $newElementID => $newElementTexts)
	{
	  $flag=false;

	  $element = get_record_by_id('Element',$newElementID);
	  $oldElementTexts =  $oldItem->getElementTextsByRecord($element);
	  
	  $oldETextsArray = array();
	  foreach($oldElementTexts as $oldElementText)
	    {
	      $oldETextsArray[] = $oldElementText['text'];
	    }
	  
	  $i = 0;
	  foreach ($newElementTexts as $newElementText)
	    {
	      if($newElementText['text'] !== "")
		{
		  $i++;
		  
		  if(!in_array($newElementText['text'],$oldETextsArray))
		    $flag=true;
		}
	    }
	  if($i !== count($oldETextsArray))
	    $flag=true;

	  if($flag)
	    $changedElements[]=$newElementID;
	  
	}
      
      return $changedElements;
    }


}
