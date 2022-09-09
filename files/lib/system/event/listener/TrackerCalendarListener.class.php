<?php
namespace calendar\system\event\listener;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\WCF;

/**
 * Listen to Calendar event action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.calendar
 */
class TrackerCalendarListener implements IParameterizedEventListener {
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
		if (!$this->tracker->wlcalEvent && !$this->tracker->otherModeration) return;
		
		// actions / data
		$action = $eventObj->getActionName();
		
		if ($this->tracker->wlcalEvent) {
			if ($action == 'create') {
				$returnValues = $eventObj->getReturnValues();
				$entry = $returnValues['returnValues'];
				$this->link = $entry->getLink();
				
				if ($entry->isDisabled) $this->store('wcf.uztracker.description.calendar.addDisabled', 'wcf.uztracker.type.wlcal');
				else $this->store('wcf.uztracker.description.calendar.add', 'wcf.uztracker.type.wlcal');
			}
		}
		
		if ($this->tracker->otherModeration) {
			if ($action == 'disable' || $action == 'enable') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $entry) {
					$this->link = $entry->getLink();
					if ($action == 'disable') {
						$this->store('wcf.uztracker.description.calendar.disable', 'wcf.uztracker.type.moderation');
					}
					else {
						$this->store('wcf.uztracker.description.calendar.enable', 'wcf.uztracker.type.moderation');
					}
				}
			}
			
			if ($action == 'delete') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $entry) {
					$this->link = '';
					$name = $entry->subject;
					$content = $entry->message;
					$this->store('wcf.uztracker.description.calendar.delete', 'wcf.uztracker.type.moderation', $name, $content);
				}
			}
			
			if ($action == 'setAsFeatured' || $action == 'unsetAsFeatured') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $entry) {
					$this->link = $entry->getLink();
					if ($action == 'setAsFeatured') {
						$this->store('wcf.uztracker.description.calendar.setAsFeatured', 'wcf.uztracker.type.moderation');
					}
					else {
						$this->store('wcf.uztracker.description.calendar.unsetAsFeatured', 'wcf.uztracker.type.moderation');
					}
				}
			}
		}
		
		if ($action == 'trash' || $action == 'restore') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $entry) {
				$this->link = $entry->getLink();
				if ($action == 'trash') {
					if ($entry->userID == $user->userID) {
						if ($this->tracker->wlcalEvent) $this->store('wcf.uztracker.description.calendar.trash', 'wcf.uztracker.type.wlcal');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.calendar.trash', 'wcf.uztracker.type.moderation');
					}
				}
				else {
					if ($entry->userID == $user->userID) {
						if ($this->tracker->wlcalEvent) $this->store('wcf.uztracker.description.calendar.restore', 'wcf.uztracker.type.wlcal');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.calendar.restore', 'wcf.uztracker.type.moderation');
					}
				}
			}
		}
		
		if ($action == 'update') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $entry) {
				$this->link = $entry->getLink();
				if ($entry->userID == $user->userID) {
					if ($this->tracker->wlcalEvent) $this->store('wcf.uztracker.description.calendar.update', 'wcf.uztracker.type.wlcal');
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.calendar.update', 'wcf.uztracker.type.moderation');
				}
			}
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
