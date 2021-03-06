<?php
class Registration
{
	public static function checkRegistration($email, $username)
	{
		$check = Db::query("SELECT username, email FROM zz_users WHERE email = :email OR username = :username", array(":email" => $email, ":username" => $username), 0);
		return $check;
	}

	public static function registerUser($username, $password, $email)
	{
		$check = Db::queryField("SELECT count(*) count FROM zz_users WHERE email = :email OR username = :username", "count", array(":email" => $email, ":username" => $username), 0);
		if ($check == 0) {
			$hashedpassword = Password::genPassword($password);
			Db::query("INSERT INTO zz_users (username, password, email) VALUES (:username, :password, :email)", array(":username" => $username, ":password" => $hashedpassword, ":email" => $email));
			$subject = "zKillboard Registration";
			$message = "Thank you, $username, for registering at zKillboard.com";
			Email::send($email, $subject, $message);
			$message = "You have been registered, you should recieve a confirmation email in a moment, in the mean time you can click login and login!";
			Log::ircAdmin("New registration: |g|$username |n|| |g|$email");
			return array("type" => "success", "message" => $message);
		}
		else
		{
			$message = "Username / email is already registered";
			return array("type" => "error", "message" => $message);
		}
	}
}
