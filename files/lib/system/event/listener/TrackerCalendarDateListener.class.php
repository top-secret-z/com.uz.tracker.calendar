<?php
namespace calendar\system\event\listener;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\WCF;

/**
 * Listen to Calendar date action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.calendar
 */
class TrackerCalendarDateListener implements IParameterizedEventListener {
	/**
	 * tracker and link
	 */
	protected $tracker = null;
	protected $link = '';
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MODULE_TRACKER) return;
		
		// only if user is to be tracked
		$user = WCF::getUser();
		if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) return;
		
		// only if trackers
		$trackers = TrackerCacheBuilder::getInstance()->getData();
		if (!isset($trackers[$user->userID])) return;
		
		$this->tracker = $trackers[$user->userID];
		if (!$this->tracker->wlcalEvent) return;
		
		// actions / data
		$action = $eventObj->getActionName();
		
		if ($action == 'save') {
			$invitedUsers = $eventObj->invitedUsers;
			$objects = $eventObj->getObjects();
			$entry = $objects[0];
			$this->link = $entry->getLink();
			if (count($invitedUsers)) {
				$this->store('wcf.uztracker.description.calendar.invited', 'wcf.uztracker.type.wlcal');
			}
			else {
				$params = $eventObj->getParameters();
				if (empty($params['username'])) {
					$this->store('wcf.uztracker.description.calendar.invited.' . $params['decision'], 'wcf.uztracker.type.wlcal');
				}
				else {
					$this->store('wcf.uztracker.description.calendar.invited.other.' . $params['decision'], 'wcf.uztracker.type.wlcal');
				}
			}
		}
		
		// since WSC 3.1
		if ($action == 'cancel') {
			$objects = $eventObj->getObjects();
			$entry = $objects[0];
			$this->link = $entry->getLink();
			$this->store('wcf.uztracker.description.calendar.cancelled', 'wcf.uztracker.type.wlcal');
		}
	}
	
	/**
	 * store log entry
	 */
	protected function store ($description, $type, $name = '', $content = '') {
		$packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.calendar');
		TrackerLogEditor::create([
				'description' => $description,
				'link' => $this->link,
				'name' => $name,
				'trackerID' => $this->tracker->trackerID,
				'type' => $type,
				'packageID' => $packageID,
				'content' => $content
		]);
	}
}
