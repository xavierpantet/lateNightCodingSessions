<?php

define("MAIL_TO", "youremail@yourdomain.com");
define("MAIL_FROM", "authenticator@yourdomain.com");

class Authenticator {
	private const DATA_SOURCE = "../data";
	private const TOKEN_SOURCE = "../token";
	private const SECRET_SOURCE = "../secret";
	private const INITIALIZATION_VECTOR = "9cd7950f424b23ab"; // generated with bin2hex(openssl_random_pseudo_bytes(8))
	private const ENCRYPTION_ALGORITHM = "AES256";
	private const HASHING_FUNCTION = "SHA256";
	
	public static function generateSecret(){
		$secret = openssl_encrypt(bin2hex(openssl_random_pseudo_bytes(16)), self::ENCRYPTION_ALGORITHM, "[secret]", OPENSSL_RAW_DATA, self::INITIALIZATION_VECTOR);
		file_put_contents(self::SECRET_SOURCE, $secret);
	}
	
	public static function authWithSecret($secret){
		if(file_exists(self::SECRET_SOURCE)){
			return openssl_decrypt(file_get_contents(self::SECRET_SOURCE), self::ENCRYPTION_ALGORITHM, $secret, OPENSSL_RAW_DATA, self::INITIALIZATION_VECTOR) !== false;
		}
		return false;
	}
	
	public static function encryptData(){
		$data = openssl_encrypt(file_get_contents("../data_raw"), self::ENCRYPTION_ALGORITHM, "[password]", OPENSSL_RAW_DATA, self::INITIALIZATION_VECTOR);
		file_put_contents(self::DATA_SOURCE, $data);
	}
	
	public static function decryptData($password){
		if(file_exists(self::DATA_SOURCE)){
			return openssl_decrypt(file_get_contents(self::DATA_SOURCE), self::ENCRYPTION_ALGORITHM, $password, OPENSSL_RAW_DATA, self::INITIALIZATION_VECTOR);
		}
		return false;
	}
	
	public static function generateNewToken(){
		if(file_exists(self::TOKEN_SOURCE)){
			unlink(self::TOKEN_SOURCE);
		}
		$token = bin2hex(openssl_random_pseudo_bytes(16));
		file_put_contents(self::TOKEN_SOURCE, hash(self::HASHING_FUNCTION, $token).PHP_EOL.time());
		return $token;
	}
	
	public static function authWithToken($token){
		if(file_exists(self::TOKEN_SOURCE)){
			$lines = explode(PHP_EOL, file_get_contents(self::TOKEN_SOURCE));
			$auth = $lines[0] == hash(self::HASHING_FUNCTION, $token) && $lines[1] > time()-60;
			unlink(self::TOKEN_SOURCE);
			return $auth;
		}
		return false;

	}
	
	public static function formatData($data){
		$lines = explode(PHP_EOL, $data);
		$formatted = '<table>
						<tr>
							<th>Account</th>
							<th>Password</th>
						</tr>';
		foreach($lines as $line){
			$exploded = explode(': ', $line);
			$formatted .= '<tr><td class="key">'.$exploded[0].'</td><td style="visibility: hidden;" class="revealable">'.$exploded[1].'</td></tr>';
		}
	
		$formatted .= '</table>';
		return $formatted;
	}
	
	public static function quickSecure(){
		if(file_exists(self::DATA_SOURCE)){
			unlink(self::DATA_SOURCE);
		}
	}

}

class Logger {
	private $database;
	
	public const STEP_SECRET = 0;
	public const STEP_REVEAL = 1;
	
	private const DB_NAME = "auth";
	private const DB_SERVER = "localhost";
	private const DB_PORT = "3306";
	private const DB_USER = "auth_db_user";
	private const DB_PASSWD = "Cr72pl!2";
	
	public function __construct() {
		try {
    		$this->database = new PDO('mysql:host='.self::DB_SERVER.':'.self::DB_PORT.';dbname='.self::DB_NAME.';charset=utf8', self::DB_USER, self::DB_PASSWD);
		}
		catch (PDOException $e) {}
	}
	
	public function logSuccess($step) {
		$request = $this->database->prepare('INSERT INTO logs(ip, datetime, step, status) VALUES(:ip, :datetime, :step, :status)');
		$request->execute(array(
			'ip' => $_SERVER['REMOTE_ADDR'],
			'datetime' => date('Y-m-d H:i:s'),
			'step' => $step,
			'status' => true
		));
		$request->closeCursor();
	}
	
	public function logFailure($step) {
		$request = $this->database->prepare('INSERT INTO logs(ip, datetime, step, status) VALUES(:ip, :datetime, :step, :status)');
		$request->execute(array(
			'ip' => $_SERVER['REMOTE_ADDR'],
			'datetime' => date('Y-m-d H:i:s'),
			'step' => $step,
			'status' => false
		));
		$request->closeCursor();
	}
	
	public function checkNumberOfAttempts() {
		$request = $this->database->prepare('SELECT * FROM logs WHERE ip = ? ORDER BY datetime DESC LIMIT 3');
		$request->execute(array($_SERVER['REMOTE_ADDR']));
		$entries = $request->fetchAll();
		$request->closeCursor();
		
		if(count($entries) < 3){ return true; }
		
		$first = new DateTime($entries[0]['datetime']);
		$last = new DateTime($entries[2]['datetime']);
		return !($entries[0]['status'] == false && $entries[1]['status'] == false && $entries[2]['status'] == false) || $last->getTimestamp() - $first->getTimestamp() > 24*60*60;
	}
}

$logger = new Logger();

if($logger->checkNumberOfAttempts()){
	// If we have a unique token + root password
	if(isset($_POST['token']) && isset($_POST['rootPassword'])){
		
		// We check that token and password are correct
		if(Authenticator::authWithToken($_POST['token']) && Authenticator::decryptData($_POST['rootPassword'])){
			$content = '<div class="main">'.
					Authenticator::formatData(Authenticator::decryptData($_POST['rootPassword'])).
					'<a href="." class="btn btn-primary btn-block btn-large">Logout</a>
				</div>';
			$logger->logSuccess(Logger::STEP_REVEAL);
		}
		else{
			$content = '<div class="login"><h1>Invalid authentication</h1></div>';
			$logger->logFailure(Logger::STEP_REVEAL);
		}
	}
	else{
		
		// If we only have the secret (no token yet)
		if(isset($_POST['rootPassword'])){
			sleep(3);
			
			// We check that the secret is valid
			if(Authenticator::authWithSecret($_POST['rootPassword'])){
				if(isset($_POST['quickSecure']) && $_POST['quickSecure'] == "now"){
					Authenticator::quickSecure();
					$content = '<div class="login"><h1>Secured</h1><a href="." class="btn btn-primary btn-block btn-large">Ok</a></div>';
				}
				else{
					mail(MAIL_TO, "[Ok] New authentication attempt", "https://auth.pantet.ch/?token=".Authenticator::generateNewToken(), 'From: '. MAIL_FROM . "\r\n" . 'X-Mailer: PHP/' . phpversion());
					$logger->logSuccess(Logger::STEP_SECRET);
				}
			}
			else{
				mail(MAIL_TO, "[Fail] New authentication attempt", "https://auth.pantet.ch?quickSecure=now", 'From: '. MAIL_FROM . "\r\n" . 'X-Mailer: PHP/' . phpversion());
				$logger->logFailure(Logger::STEP_SECRET);
			}
		}
		
		// Default content: login form
		if(!isset($content)){
		$content = '<div class="login">
						<h1>Login</h1>
						<form action="." method="POST">
        					<input type="password" name="rootPassword" placeholder="Password" required="required" />';
							
							if(isset($_GET['quickSecure']) && $_GET['quickSecure'] == "now"){
								$content .= '<input type="hidden" name="quickSecure" value="now" />';
							}
			
							if(isset($_GET['token'])){
								$content .= '<input type="hidden" name="token" value="'.$_GET['token'].'" />';
							}
        					
        					$content .= '<button type="submit" class="btn btn-primary btn-block btn-large">Login</button>
    					</form>
					</div>';}
	}
}
else{
	$content = '<div class="login"><h1>Too many attempts</h1></div>';
}
?>

<!DOCTYPE html>
<html lang="en" >
<head>
	<meta charset="UTF-8">
	<title>Authenticator</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.min.css">
	<link rel="stylesheet" href="style/main.css">
	<link rel="stylesheet" href="style/style.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
</head>

<body>
	<?= $content ?>
	<script src="scripts/main.js"></script>
</body>
</html>