<?php

require_once "../init.php";

Db::execute("truncate zz_prices");

$motherships = Db::query("select typeid from ccp_invTypes where groupid = 659");
foreach ($motherships as $mothership) {
	$typeID = $mothership['typeid'];
	Db::execute("replace into zz_prices (typeID, price) values (:typeID, 25000000000)", array(":typeID" => $typeID));
}

$titans = Db::query("select typeid from ccp_invTypes where groupid = 30");
foreach ($titans as $titan) {
	$typeID = $titan['typeid'];
	Db::execute("replace into zz_prices (typeID, price) values (:typeID, 70000000000)", array(":typeID" => $typeID));
}

$tourneyFrigates = array(
	2834, // Utu
	11375, // Freki
	32788, // Cambion
);
foreach($tourneyFrigates as $typeID) Db::execute("replace into zz_prices (typeID, price) values ($typeID, 25000000000)"); // 25b

$tourneyCruisers = array(
	2836, // Adrestia
	3516, // Malice
	3518, // Vangel
	32209, //Mimir
);
foreach($tourneyCruisers as $typeID) Db::execute("replace into zz_prices (typeID, price) values ($typeID, 40000000000)"); // 40b


$rareCruisers = array( // Ships we should never see get blown up!
	11940, // Gold Magnate
	11942, // Silver Magnate
	635, // Opux Luxury Yacht
	110111, // Guardian-Vexor
	25560, // Opux Dragoon Yacht
);
foreach($rareCruisers as $typeID) Db::execute("replace into zz_prices (typeID, price) values ($typeID, 55000000000)"); // 55b

$rareBattleships = array( // More ships we should never see get blown up!
	13202, // Megathron Federate Issue
	26840, // Raven State Issue
	11936, // Apocalypse Imperial Issue
	11938, // Armageddon Imperial Issue
	26842, // Tempest Tribal Issue
);
foreach($rareBattleships as $typeID) Db::execute("replace into zz_prices (typeID, price) values ($typeID, 750000000000)"); // 750b

die();
$prices = file_get_contents("http://eve.no-ip.de/prices/30d/prices-all.xml");
$xml = new SimpleXmlElement($prices);
foreach($xml->result->rowset->row as $row) {
	$typeID = $row['typeID'];
	$avgPrice = $row['avg'];
	Db::execute("replace into zz_prices (typeID, price) values (:typeID, :price)", array(":typeID" => $typeID, ":price" => $avgPrice));
}
