<?php

$base = dirname(__FILE__);
require_once "$base/../init.php";

//Db::execute("insert ignore into ccp_invTypes (typeID, typeName) select distinct shipTypeID, concat('TypeID ', shipTypeID) from zz_participants");
//Db::execute("insert ignore into ccp_invTypes (typeID, typeName) select distinct typeID, concat('TypeID ', typeID) from zz_items");
$rows = Db::query("select typeID from ccp_invTypes order by typeID", array(), 0);
$ids = array();
foreach($rows as $row) {
	$ids[] = $row['typeID'];
}

$size = sizeof($ids);
$count = 0;

$buckets = array();
$bucketNumber = 0;
$bucketSize = 50;
do {
	$currentBucket = array();
	$start = $bucketNumber * $bucketSize;
	$end = $start + $bucketSize;
	for ($i = $start; $i < $end && $i < $size; $i++) {
		if ($ids[$i] != "") $currentBucket[] = $ids[$i];
	}

	$buckets[$bucketNumber] = $currentBucket;
	$bucketNumber++;
} while ($bucketNumber * $bucketSize < sizeof($ids));

foreach ($buckets as $bucket) {
	$exploded = implode(",", $bucket);
	$url = trim("http://api.eveonline.com/eve/typeName.xml.aspx?ids=$exploded");
	$raw = file_get_contents($url);
	try {
		$xml = new SimpleXmlElement($raw);
	} catch (Exception $ex) {
		print_r($ex);
		Log::log("There was a problem retrieving the XML from $url\nThis could be because of the network, local server settings, CCP, etc.");
		die();
	}
	foreach ($xml->result->rowset->row as $row) {
		$count++;
		$id = $row["typeID"];
		$currentName = trim(Db::queryField("select typeName from ccp_invTypes where typeID = :typeID", "typeName", array(":typeID" => $id), 0));
		$name = trim($row["typeName"]);
		if ($currentName === $name) continue;
		if (strlen($name) == 0) {
			continue;  // CCP removed an item and cleared the name, we'll keep the name around though
		}
		Db::execute("update ccp_invTypes set typeName = :name where typeID = :id", array(":name" => $name, ":id" => $id));
		if ($currentName != "") {
			Log::log("$count/$size $id $currentName -> $name");
			if (Util::startsWith($currentName, "TypeID")) Log::irc("New item: $name (typeID: $id)");
			//else Log::irc("Item renamed: '$currentName' -> '$name'");
		}
	}
}
