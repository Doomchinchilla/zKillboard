<?php
$info = "";
$message = "";
$info = User::getUserInfo();
if (!User::isModerator()) $app->notFound();

if($_POST)
{
	$status = NULL;
	$reply = NULL;
	$revokeaccess = NULL;
	$grantaccess = NULL;
	$reason = NULL;
	
	if(isset($_POST["status"]))
		$status = $_POST["status"];
	if(isset($_POST["reply"]))
		$reply = $_POST["reply"];
    if(isset($_POST["revokeaccess"]))
		$revokeaccess = $_POST["revokeaccess"];
    if(isset($_POST["grantaccess"]))
		$grantaccess = $_POST["grantaccess"];
	if(isset($_POST["reason"]))
		$reason = $_POST["reason"];
	if(isset($_POST["userID"]))
		$userID = $_POST["userID"];
	
	if(isset($status))
	{
		Db::execute("UPDATE zz_tickets SET status = :status WHERE id = :id", array(":status" => $status, ":id" => $id));
	}
	if(isset($reply))
	{
		$name = $info["username"];
		$moderator = $info["moderator"];
		$check = Db::query("SELECT * FROM zz_tickets_replies WHERE reply = :reply AND userid = :userid", array(":reply" => $reply, ":userid" => $info["id"]), 0);
		if(!$check)
		{
			Db::execute("INSERT INTO zz_tickets_replies (userid, belongsTo, name, reply, moderator) VALUES (:userid, :belongsTo, :name, :reply, :moderator)", array(":userid" => $info["id"], ":belongsTo" => $id, ":name" => $name, ":reply" => $reply, ":moderator" => $moderator));
			$tic = Db::query("SELECT name,email FROM zz_tickets WHERE id = :id", array(":id" => $id));
			$ticname = $tic[0]["name"];
			$ticmail = $tic[0]["email"];
			$subject = "zKillboard Ticket";
			global $baseAddr;
			$message = "$ticname, there is a new reply to your ticket from $name - https://$baseAddr/tickets/view/$id/";
			Email::send($ticmail, $subject, $message);
			$app->redirect("/moderator/tickets/$id/");
		}
	}
	if(isset($grantaccess) && isset($userID))
	{
		Db::execute("UPDATE zz_users SET revoked = :access WHERE id = :id", array(":id" => $userID, ":access" => 0));
		$message = "User has been granted access to the site";
	}
	if(isset($revokeaccess) && isset($userID) && isset($reason))
	{
		Db::execute("UPDATE zz_users SET revoked = :access WHERE id = :id", array(":id" => $userID, ":access" => 1));
		Db::execute("UPDATE zz_users SET revoked_reason = :reason WHERE id = :id", array(":id" => $userID, ":reason" => $reason));
		$message = "User has had access to the site revoked";
	}
}

if($req == "tickets" && $id)
{
	$info["ticket"] = Db::query("SELECT * FROM zz_tickets WHERE id = :id", array(":id" => $id), 0);
	$info["replies"] = Db::query("SELECT * FROM zz_tickets_replies WHERE belongsTo = :id", array(":id" => $id), 0);
}
elseif($req == "tickets")
{
	$info = Db::query("SELECT * FROM zz_tickets ORDER BY status DESC", array(),0);
	foreach($info as $key => $val)
	{
		if($val["tags"])
			$info[$key]["tags"] = explode(",", $val["tags"]);
	}
}
elseif($req == "users")
{
	$info = Db::query("SELECT * FROM zz_users order by username", array(), 0);
}
elseif($req == "revokes")
{
	$info = Db::query("SELECT id, username, email, revoked_reason FROM zz_users WHERE revoked = 1 ORDER BY id DESC", array(), 0);
}

$app->render("moderator/moderator.html", array("id" => $id, "info" => $info, "key" => $req, "message" => $message));
