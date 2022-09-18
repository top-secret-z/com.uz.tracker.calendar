<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace calendar\system\event\listener;

use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to Calendar date action.
 */
class TrackerCalendarDateListener implements IParameterizedEventListener
{
    /**
     * tracker and link
     */
    protected $tracker;

    protected $link = '';

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (!MODULE_TRACKER) {
            return;
        }

        // only if user is to be tracked
        $user = WCF::getUser();
        if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) {
            return;
        }

        // only if trackers
        $trackers = TrackerCacheBuilder::getInstance()->getData();
        if (!isset($trackers[$user->userID])) {
            return;
        }

        $this->tracker = $trackers[$user->userID];
        if (!$this->tracker->wlcalEvent) {
            return;
        }

        // actions / data
        $action = $eventObj->getActionName();

        if ($action == 'save') {
            $invitedUsers = $eventObj->invitedUsers;
            $objects = $eventObj->getObjects();
            $entry = $objects[0];
            $this->link = $entry->getLink();
            if (\count($invitedUsers)) {
                $this->store('wcf.uztracker.description.calendar.invited', 'wcf.uztracker.type.wlcal');
            } else {
                $params = $eventObj->getParameters();
                if (empty($params['username'])) {
                    $this->store('wcf.uztracker.description.calendar.invited.' . $params['decision'], 'wcf.uztracker.type.wlcal');
                } else {
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
    protected function store($description, $type, $name = '', $content = '')
    {
        $packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.calendar');
        TrackerLogEditor::create([
            'description' => $description,
            'link' => $this->link,
            'name' => $name,
            'trackerID' => $this->tracker->trackerID,
            'type' => $type,
            'packageID' => $packageID,
            'content' => $content,
        ]);
    }
}
