<?php

	class Summary
	{

		public static function getPilotSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('pilot', 'characterID', $data, $id, $parameters);
		}

		public static function getCorpSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('corp', 'corporationID', $data, $id, $parameters);
		}

		public static function getAlliSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('alli', 'allianceID', $data, $id, $parameters);
		}

		public static function getFactionSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('faction', 'factionID', $data, $id, $parameters);
		}

		public static function getShipSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('ship', 'shipTypeID', $data, $id, $parameters);
		}

		public static function getGroupSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('group', 'groupID', $data, $id, $parameters);
		}

		public static function getRegionSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('region', 'regionID', $data, $id, $parameters);
		}

		public static function getSystemSummary(&$data, $id, $parameters = array())
		{
			return Summary::getSummary('system', 'solarSystemID', $data, $id, $parameters);
		}

		private static function getSummary($type, $column, &$data, $id, $parameters = array())
		{
			$key = "summary:$type:$column:$id:" . json_encode($parameters);
			$mc = Memcached::get($key);
			if ($mc) return $mc;

			global $subDomain;
			$sub = strlen($subDomain) ? $subDomain : null;
			$rank = Db::queryRow("select * from zz_ranks where type = :type and typeID = :id", array(":type" => $type, ":id" => $id), 300);
			$recentRank = Db::queryField("select overallRank from zz_ranks_recent where type = :type and typeID = :id", "overallRank", array(":type" => $type, ":id" => $id), 300);
			if (false && sizeof($parameters) > 4) { //isset($parameters["year"]) && (isset($parameters["week"]) || isset($parameters["month"]))) {
				$rank = $recentRank = array();
				// Ensure that at least year/month or year/week are defined
				//if (!isset($parameters["year"])) throw new Exception("Year must be passed");
				//if (!isset($parameters["month"]) && !isset($parameters["week"])) throw new Exception("Week or Month must be passed.");

				/*$tables = array();
				$whereClauses = array();
				if (!isset($parameters["kills"]) && !isset($parameters["losses"])) $parameters["mixed"] = true;
				Filters::buildFilters($tables, $whereClauses, $whereClauses, $parameters, true);

				$whereStatement = implode(" and ", $whereClauses);

				$query = "select groupID, sum(if(isVictim, 0, 1)) destroyed, sum(if(isVictim, 0, total_price)) iskDestroyed, sum(if(isVictim, 0, points)) pointsDestroyed, sum(if(isVictim, 1, 0)) lost, sum(if(isVictim, total_price, 0)) iskLost, sum(if(isVictim, points, 0)) pointsLost from (select vGroupID groupID, isVictim, total_price, points from zz_participants p where $whereStatement group by killID) as foo group by groupID";
				$stats = Db::query($query);*/
				$stats = array();
			}
			else
			{
				if ($type == "system")
					$stats = Db::query("select groupID, lost destroyed, 0 lost, pointsLost pointsDestroyed, 0 pointsLost, iskLost iskDestroyed, 0 iskLost from zz_stats where type='$type' and typeID = $id",
														 array(":id" => $id), 300);
				else
					$stats = Db::query("select groupID, destroyed, lost, pointsDestroyed, pointsLost, iskDestroyed, iskLost from zz_stats where type='$type' and typeID = $id",
														 array(":id" => $id), 300);
			}

			$infoStats = array();

			$data["shipsDestroyed"] = 0;
			$data["shipsLost"] = 0;
			$data["pointsDestroyed"] = 0;
			$data["pointsLost"] = 0;
			$data["iskDestroyed"] = 0;
			$data["iskLost"] = 0;

			foreach ($stats as $stat) {
				$infoStat = Info::addInfo($stat);
				if ($infoStat["groupID"] == 0) $infoStat["groupName"] = "Unknown";
				$infoStats[$infoStat["groupName"]] = $infoStat;
				$data["shipsDestroyed"] += $infoStat["destroyed"];
				$data["shipsLost"] += $infoStat["lost"];
				$data["iskDestroyed"] += $infoStat["iskDestroyed"];
				$data["iskLost"] += $infoStat["iskLost"];
				$data["pointsDestroyed"] += $infoStat["pointsDestroyed"];
				$data["pointsLost"] += $infoStat["pointsLost"];
			}
			unset($stats);
			ksort($infoStats);
			$data["stats"] = $infoStats;
			if ($rank != null && $recentRank != null) $rank["recentRank"] = $recentRank;
			if ($rank != null) $data["ranks"] = $rank;
			Memcached::set($key, $data, 300);
			return $data;
		}

		public static function getMonthlyHistory($type, $typeID)
		{
			return Db::query("select year, month, sum(if(isVictim, 0, 1)) destroyed, sum(if(isVictim, 0, total_price)) iskDestroyed, sum(if(isVictim, 0, points)) pointsDestroyed, sum(if(isVictim, 1, 0)) lost, sum(if(isVictim, total_price, 0)) iskLost, sum(if(isVictim, points, 0)) pointsLost from (select year(dttm) year, month(dttm) month, isVictim, total_price, points from zz_participants p where $type = $typeID group by killID) as foo group by year, month", array(), 3600);
		}

		public static function buildSummary(&$kills, $parameters = array(), $key)
		{
			if ($kills == null || !is_array($kills) || sizeof($kills) == 0) return array();

			$key = "related:$key";
			$mc = Memcached::get($key);
			//if ($mc) return $mc;

			$teamAKills = array();
			$teamBKills = array();
			$teamAList = array();
			$teamBList = array();
			$killHash = array();

			Summary::determineSides($kills, $teamAKills, $teamBKills);
			foreach ($kills as $kill) {
				Summary::addRelatedPilots($killHash, $kill, $kill["victim"]);
			}
			$teamAEntities = Summary::getEntities($teamAKills);
			$teamBEntities = Summary::getEntities($teamBKills);
			//$killHash = array_keys($killHash);

			$teamATotals = Summary::getStatsKillList($teamAKills);
			$teamBTotals = Summary::getStatsKillList($teamBKills);

			Summary::hashPilots($teamBKills, $teamAKills, $teamBList, $teamAList);
			Summary::hashPilots($teamAKills, $teamBKills, $teamAList, $teamBList);

			$teamATotals["pilotCount"] = Summary::getUniquePilotCount($teamAList);
			$teamBTotals["pilotCount"] = Summary::getUniquePilotCount($teamBList);

			foreach ($teamAList as $a => $value) if (in_array($a, array_keys($teamBList))) unset($teamAList[$a]);
			foreach ($teamBList as $b => $value) if (in_array($b, array_keys($teamAList))) unset($teamBList[$b]);

			uksort($teamAList, "Summary::shipGroupSort");
			uksort($teamBList, "Summary::shipGroupSort");

			$teamAList = Summary::addInfo($teamAList, $killHash);
			$teamBList = Summary::addInfo($teamBList, $killHash);


			$retValue = array(
				"teamA" => array(
					"list" => $teamAList,
					"kills" => $teamAKills,
					"totals" => $teamATotals,
				),
				"teamB" => array(
					"list" => $teamBList,
					"kills" => $teamBKills,
					"totals" => $teamBTotals,
				),
			);

			$teamAScore = log($retValue["teamA"]["totals"]["total_price"] + 1) * (log($retValue["teamA"]["totals"]["total_points"]) + 1);
			$teamBScore = log($retValue["teamB"]["totals"]["total_price"] + 1) * (log($retValue["teamA"]["totals"]["total_points"]) + 1);
			/*if ($teamBScore > $teamAScore) {
					$temp = $retValue["teamB"];
					$retValue["teamB"] = $retValue["teamA"];
					$retValue["teamA"] = $temp;
					}*/

			Memcached::set($key, $retValue, 300);
			return $retValue;
		}

		private static function getEntities(&$kills)
		{
			$retValue = array();
			$retValue["chars"] = array();
			$retValue["corps"] = array();
			$retValue["allis"] = array();
			$retValue["factions"] = array();
			foreach ($kills as $kill) {
				$victim = $kill["victim"];
				$retValue["chars"][$victim["characterID"]] = true;
				$retValue["corps"][$victim["corporationID"]] = true;
				if ($victim["allianceID"] != 0) $retValue["allis"][$victim["allianceID"]] = true;
				if ($victim["factionID"] != 0) $retValue["factions"][$victim["factionID"]] = true;
			}
			$retValue["chars"] = array_keys($retValue["chars"]);
			$retValue["corps"] = array_keys($retValue["corps"]);
			$retValue["allis"] = array_keys($retValue["allis"]);
			$retValue["factions"] = array_keys($retValue["factions"]);
			return $retValue;
		}

		private static function getStatsKillList(&$kills)
		{
			$totalPrice = 0;
			$totalPoints = 0;
			$groupIDs = array();
			$totalShips = 0;
			foreach ($kills as $kill) {
				$info = $kill["info"];
				$victim = $kill["victim"];
				$totalPrice += $info["total_price"];
				$totalPoints += $info["points"];
				$groupID = $victim["groupID"];
				if (!isset($groupIDs[$groupID])) {
					$groupIDs[$groupID] = array();
					$groupIDs[$groupID]["count"] = 0;
					$groupIDs[$groupID]["isk"] = 0;
					$groupIDs[$groupID]["points"] = 0;
				}
				$groupIDs[$groupID]["groupID"] = $groupID;
				$groupIDs[$groupID]["count"]++;
				$groupIDs[$groupID]["isk"] += $info["total_price"];
				$groupIDs[$groupID]["points"] += $info["points"];
				$totalShips++;
			}
			Info::addInfo($groupIDs);
			return array(
				"total_price" => $totalPrice, "groupIDs" => $groupIDs, "totalShips" => $totalShips,
				"total_points" => $totalPoints
			);
		}

		private static function addInfo($array, $killHash)
		{
			$results = array();
			foreach ($array as $hash => $kill) {
				$pilot = $kill["pilot"];
				$killID = $pilot["killID"];
				$split = explode("|", $hash);
				$row = array();
				$row["shipTypeID"] = $split[0];
				$row["corporationID"] = $split[1];
				$row["characterID"] = $split[2];
				$row["allianceID"] = $split[3];
				$row["destroyed"] = in_array($hash, array_keys($killHash)) ? $killHash[$hash]["kill"]["victim"]["killID"] : 0;
				$podHash = "670|" . $row["corporationID"] . "|" . $row["characterID"] . "|" . $row["allianceID"];
				$row["podded"] = in_array($podHash, $killHash) ? 1 : 0;
				Info::addInfo($row);
				$results[] = $row;
			}
			return $results;
		}

		private static function getUniquePilotCount($array)
		{
			$uniquePilots = array();
			foreach ($array as $hash => $kill) {
				$split = explode("|", $hash);
				$characterID = $split[2];
				if ($characterID > 0) $uniquePilots["$characterID"] = true;
			}
			return sizeof($uniquePilots);
		}

		private static function determineSides(&$kills, &$teamA, &$teamB)
		{
			$chars = array();
			$corps = array();
			$allis = array();
			$factions = array();
			foreach ($kills as $kill) {
				$victim = $kill["victim"];
				$price = $kill["info"]["total_price"];
				$points = $kill["info"]["points"];
				Summary::increment($chars, $victim["characterID"], $price, $points);
				Summary::increment($corps, $victim["corporationID"], $price, $points);
				Summary::increment($allis, $victim["allianceID"], $price, $points);
				Summary::increment($factions, $victim["factionID"], $price, $points);
			}
			if (sizeof($factions) > 1) Summary::filterKills($kills, $teamB, $teamA, "factionID", $factions, "F");
			else if (sizeof($allis)) Summary::filterKills($kills, $teamB, $teamA, "allianceID", $allis, "A");
			else if (sizeof($corps)) Summary::filterKills($kills, $teamB, $teamA, "corporationID", $corps, "C");
			else if (sizeof($chars)) Summary::filterKills($kills, $teamB, $teamA, "characterID", $chars, "P");
		}

		private static function filterKills(&$kills, &$array1, &$array2, $key, $keyArray, $type)
		{
			$valueArray = array();
			foreach ($keyArray as $rowKey => $row) {
				$valueArray[$rowKey] = log($row["count"]) * $row["price"];
			}
			arsort($valueArray);
			$keys = array_keys($valueArray);
			$keyValue = $keys[0];
			foreach ($kills as $killID => $kill) {
				$victim = $kill["victim"];
				if ($victim[$key] == $keyValue) $array1[$killID] = $kill;
				else if ($victim[$key] == 0) $array1[$killID] = $kill;
				else $array2[$killID] = $kill;
			}
		}

		private static function increment(&$array, $key, $price, $points)
		{
			if ($key == 0) return;
			if (!isset($array["$key"])) {
				$array["$key"] = array();
				$array["$key"]["count"] = 0;
				$array["$key"]["price"] = 0;
				$array["$key"]["points"] = 0;
			}
			$array["$key"]["count"]++;
			$array["$key"]["price"] += $price;
			$array["$key"]["points"] += $points;
		}

		protected static function hashPilots($kills, $otherKills, &$teamBs, &$teamAs)
		{
			$pilotsAdded = array();
			ksort($kills);

			$validEntities = array();
			$validEntities["factions"] = array();
			$validEntities["allis"] = array();
			$validEntities["corps"] = array();
			// Preprocess the opposition
			foreach ($kills as $killID => $kill) {
				$victim = $kill['victim'];

				if ($victim["factionID"] != 0) $validEntities["factions"][] = $victim["factionID"];
				if ($victim["allianceID"] != 0) $validEntities["allis"][] = $victim["allianceID"];
				if ($victim["corporationID"] != 0) $validEntities["corps"][] = $victim["corporationID"];
			}

			foreach ($kills as $killID => $kill) {
				$detail = $kill['info'];
				$victim = $kill['victim'];
				$characterID = $victim["characterID"];
				$shipTypeID = $victim["shipTypeID"];
				if (($shipTypeID == 0 || $shipTypeID == 670) && in_array($characterID, $pilotsAdded)) continue;
				if ($shipTypeID != 670 && $shipTypeID != 0) $pilotsAdded[] = $characterID;
				Summary::addRelatedPilots($teamBs, $kill, $victim);

				$attackers = Db::query("select * from zz_participants where killID = :killID and isVictim = '0'",
															 array(":killID" => $killID), 3600);
				foreach ($attackers as $attacker) {
					$shipTypeID = $attacker["shipTypeID"];

					if ($attacker["factionID"] != 0 && in_array($attacker["factionID"], $validEntities["factions"])) continue;
					if ($attacker["allianceID"] != 0 && in_array($attacker["allianceID"], $validEntities["allis"])) continue;
					if (in_array($attacker["corporationID"], $validEntities["corps"])) continue;

					if ($shipTypeID != 0 && $shipTypeID != 670) Summary::addRelatedPilots($teamAs, $kill, $attacker);
					if (!in_array($attacker["characterID"], $pilotsAdded)) $pilotsAdded[] = $attacker["characterID"];
				}
				foreach ($attackers as $attacker) {
					//if (!in_array($attacker["characterID"], $pilotsAdded)) Summary::addRelatedPilots($teamAs, $kill, $attacker);
				}
			}
		}

		protected static function addRelatedPilots(&$array, &$kill, &$pilot)
		{
			$characterID = $pilot['characterID'];
			$corporationID = $pilot['corporationID'];
			$allianceID = $pilot["allianceID"];
			$shipTypeID = $pilot['shipTypeID'];

			if ($characterID == 0) return;

			if ($shipTypeID != 670 && $shipTypeID != 0) {
				$podHash = "670|$corporationID|$characterID|$allianceID";
				$s0Hash = "0|$corporationID|$characterID|$allianceID";
				unset($array[$podHash]);
				unset($array[$s0Hash]);
			}
			$hash = "$shipTypeID|$corporationID|$characterID|$allianceID";
			$array[$hash] = array("kill" => $kill, "pilot" => $pilot);
		}

		public static function shipGroupSort($a, $b)
		{
			$split1 = explode("|", $a);
			$split2 = explode("|", $b);
			$shipTypeID1 = $split1[0];
			$shipTypeID2 = $split2[0];

			$volumeID1 = Db::queryField("select volume from ccp_invTypes where typeID = :typeID", "volume",
																	array(":typeID" => $shipTypeID1));
			$volumeID2 = Db::queryField("select volume from ccp_invTypes where typeID = :typeID", "volume",
																	array(":typeID" => $shipTypeID2));

			if ($volumeID1 != $volumeID2) return $volumeID2 - $volumeID1;

			if ($shipTypeID1 == $shipTypeID2) {
				if ($split1[1] == $split2[1]) {
					return $split1[2] - $split2[2];
				}
				return $split1[1] - $split2[1];
			}

			$groupID1 = Db::queryField("select groupID from ccp_invTypes where typeID = :typeID", "groupID",
																 array(":typeID" => $shipTypeID1));
			$groupID2 = Db::queryField("select groupID from ccp_invTypes where typeID = :typeID", "groupID",
																 array(":typeID" => $shipTypeID2));
			if ($groupID1 == $groupID2) {
				return $shipTypeID2 - $shipTypeID1;
			}
			if ($groupID1 == $groupID2) return 0;
			return $groupID2 - $groupID1;
		}
	}
