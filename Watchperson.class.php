<?php
/*!
 * @file
 * @author  Matthew Howell <smashuu@gmail.com>
 * @version 1.0
 *
 * @section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @section DESCRIPTION
 *
 * This class provides simple tools for handling login and authentication
 */

/*!
 * This class provides simple tools for handling login and authentication.
 * 
 * @section Page Usage Example
 * 
 * require_once 'Watchperson.class.php';<br>
 * $sentry = new Watchperson('localhost', 'login', '*****', 'database', '/login.php', '/');<br>
 * $sentry->startSession();
 * 
 * 
 * if ($_POST['logout'])
 * 	$sentry->logout();
 * elseif (!empty($_POST['username']) && !empty($_POST['password']))
 * 	$sentry->login($_POST['username'], $_POST['password'], $_SERVER['REQUEST_URI']);
 * 
 * if ($sentry->getLevel())
 * 	echo "level: " . $sentry->getLevel() . "<hr>";
 */

require_once 'pbkdf2.php';

class Watchperson {
	private $db;					//!< The mysqli instance used for db access
	private $homepage;				//!< The path to the site's homepage from the web root
	private $loginPage;				//!< The path to the site's login page from the web root
	private $level = 0;				//!< The user's access level
	
	/**@{*/
	/*!
	 * User access levels, as integers, so you can easily test if a user's level < the required level
	 */
	const LVL_NONE  = 0;
	const LVL_USER  = 1;
	const LVL_ADMIN = 2;
	const LVL_OWNER = 3;
	/**@}*/
	
	/**@{*/
	/*!
	 * Error codes, as integers
	 */
	const LOGIN_SUCCESS      = 0;
	const LOGIN_REQUIRED     = 1;
	const LOGIN_EXPIRED      = 2;
	const LOGIN_UNAUTHORIZED = 3;
	const LOGIN_FAILED       = 4;
	/**@}*/
	
	/*!
	 * Creates a new instance of a Watchman object.
	 * 
	 * @param string $dbhost MySQL hostname
	 * @param string $dbuser MySQL username
	 * @param string $dbpass MySQL password
	 * @param string $database MySQL database name
	 * @param string $loginPage (optional) The path to redirect to when login is required. Defaults to the web root.
	 * @param string $homepage (optional) The path to the site's main page. Defaults to the web root.
	 */
	public function __construct($dbhost, $dbuser, $dbpass, $database, $loginPage='/', $homepage='/') {
		$this->homepage = $homepage;
		$this->db = new mysqli($dbhost, $dbuser, $dbpass, $database);
		if ($mysqli->connect_errno) {
			throw new Exception("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
		}
		$this->db->select_db($database);
	}
	
	/*!
	 * Runs a key (password) through the PBKDF2 algorithm and returns it as a hex string
	 * 
	 * @param string $password The password
	 * @param string $salt A salt that is unique to the password.
	 * @param int $iterations (optional) Iteration count. Higher is better, but slower. Recommended: At least 1024
	 * @param int $key_length (optional) The length of the derived key
	 * @return A $key_length sized hex string derived from the password and salt
	 */
	public function pbkdf2_hash($password, $salt, $iterations=1025, $key_length=256) {
		$result = bin2hex(pbkdf2($password, $salt, $iterations, $key_length, 'sha256'));
		return $result;
	}
	
	/*!
	 * Creates the users table
	 * 
	 * @return TRUE if the table creation succeeded, otherwise FALSE
	 */
	public function createUserTable() {
		$query = "CREATE TABLE `users` (
					  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					  `username` varchar(255) NOT NULL DEFAULT '',
					  `password` text NOT NULL,
					  `created` datetime DEFAULT NULL,
					  `last_login` datetime DEFAULT NULL,
					  `login_token` char(64) DEFAULT NULL,
					  `level` int(1) NOT NULL DEFAULT '0',
					  PRIMARY KEY (`id`),
					  UNIQUE KEY `UNIQUE_user` (`username`)
					) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;";
		return $this->db->query($query);
	}
	
	/*!
	 * Creates a user with the specified name, password, and optionally, access level
	 * 
	 * @param string $username The username
	 * @param string $password The raw password string
	 * @param int $level (optional) The user's access level, as defined at the top of the class. Defaults to the unprivileged LVL_USER
	 * @return TRUE if the insert succeeded, otherwise FALSE
	 */
	public function createUser($username, $password, $level=null) {
		$password = $this->db->real_escape_string($this->pbkdf2_hash($password, $username));
		$username = $this->db->real_escape_string($username);
		$level = $this->db->real_escape_string($level ?: self::LVL_USER);
		$query = "INSERT INTO `users` SET `username`='$username', `password`='$password', `level`='$level';";
		return $this->db->query($query);
	}
	
	/*!
	 * Attempt to log a user in
	 * 
	 * @param string $username The username
	 * @param string $password The raw password string
	 * @param string $path (optional) The path to redirect to, on successful login. Default value is the homepage defined on instanciation.
	 * @param bool $remember (optional) Currently unimplemented
	 */
	public function login($username, $password, $path='', $remember=false) {
		if (!$path)
			$path = $this->homepage;
		
		$password = $this->db->real_escape_string($this->pbkdf2_hash($password, $username));
		$username = $this->db->real_escape_string($username);
		$query = "SELECT `id`, `level` FROM `users` WHERE `username`='$username' AND `password`='$password';";
		$res = $this->db->query($query);
		if (!$res) {
			$this->redirect($this->loginPage, $path, self::LOGIN_FAILED);
		}
		else {
			//$this->startSession($path);
			$row = $res->fetch_assoc();
			if ($row) {
				$id = $row['id'];
				$loginToken = $this->db->real_escape_string(hash('sha256', $username.time()));
				$this->db->query("UPDATE `users` SET `last_login`=NOW(), `login_token`='$loginToken' WHERE `id`='$id';");
				$_SESSION['userid'] = $id;
				$_SESSION['token'] = $loginToken;
				$this->redirect($path);
			}
			else {
				$this->redirect($this->loginPage, $path, self::LOGIN_FAILED);
			}
		}
	}
	
	/*!
	 * Creates a user with the specified name, password, and optionally, access level
	 * 
	 * @param string $path (optional) The path to redirect to on logout. Default value is the homepage defined on instanciation.
	 */
	public function logout($path='') {
		if (!$path)
			$path = $this->homepage;
		
		$this->endSession();
		$this->redirect($path);
	}
	
	/*!
	 * Redirect browser to the specified path
	 * 
	 * @param string $path The destination path
	 * @param string $oldPath (optional) Optionally, the previous path the user was on; useful for preserving the page when logging in
	 * @param int $error (optional) The error code as defined at the beginning of the class. If the error is due to unauthorized access, user is sent to the homepage; otherwise the error is passed to the destination page.
	 */
	private function redirect($path, $oldPath='', $error=0) {
		$pathParsed = parse_url($path);
		$qParams = array();
		parse_str($pathParsed['query'], $qParams);
			
		switch($error) {
			case self::LOGIN_UNAUTHORIZED:
				$newPath = $this->homepage;
				break;
			case self::LOGIN_REQUIRED:
			case self::LOGIN_EXPIRED:
				$qParams['error'] = 'login';
				$newPath = $pathParsed['path'];
				break;
			case self::LOGIN_FAILED:
				$qParams['error'] = 'failed';
				$newPath = $pathParsed['path'];
			$sentry->startSession();	break;
			default:
				$newPath = $pathParsed['path'];
		}
		
		if ($oldPath) {
			$qParams['prevpath'] = $oldPath;
		}
		
		$newUrl = ($_SERVER['HTTPS']?'https':'http') . '://' . $_SERVER['SERVER_NAME'] . $newPath . ($qParams ? '?' : '') . http_build_query($qParams);
		
		header("Location: {$newUrl}");
		exit;
	}
	
	/*!
	 * Starts a session for a page. Optionally, setting the required level 
	 * Call this on page before any output, as it may send HTTP headers
	 * 
	 * @param int $requiredLevel (optional) Optionally, the previous path the user was on; useful for preserving the page when logging in
	 * @param string $path (optional) The path to use for redirects
	 */
	public function startSession($requiredLevel=0, $path='') {
		if (!$path)
			$path = $_SERVER['REQUEST_URI'];
		
		// Start out with no login/access
		$this->level = self::LVL_NONE;
		session_start();
		
		if ($_SESSION['userid']) {
			if ($_SESSION['token']) {
				$userid = $this->db->real_escape_string($_SESSION['userid']);
				$query = "SELECT `login_token`, `level` FROM `users` WHERE `id`='$userid';";
				$res = $this->db->query($query);
				if ($res) {
					$row = $res->fetch_assoc();
					$token = $row['login_token'];
					if ($token = $_SESSION['token']) {
						$this->level = $row['level'];
					}
					else
						$this->redirect($this->loginPage, $path, self::LOGIN_EXPIRED);
				}
				else {
					$this->redirect($this->loginPage, $path, self::LOGIN_FAILED);
				}
			}
			else
				$this->redirect($this->loginPage, $path, self::LOGIN_REQUIRED);
		}
				
		if ($this->level < $requiredLevel)
			$this->redirect($this->homepage, $path, self::LOGIN_UNAUTHORIZED);
	}
	
	/*!
	 * Ends a session and logs the user out, if logged in
	 */
	public function endSession() {
		// Initialize the session.
		// If you are using session_name("something"), don't forget it now!
		session_start();
		// Unset all of the session variables.
		$_SESSION = array();
		// If it's desired to kill the session, also delete the session cookie.
		// Note: This will destroy the session, and not just the session data!
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		// Finally, destroy the session.
		session_destroy();
	}
	
	/*!
	 * Returns the access level of the user.
	 * 
	 * @return 0 if no user is logged in, otherwise the user level as defined at the top of the class
	 */
	public function getLevel() {
		return $this->level;
	}
}
