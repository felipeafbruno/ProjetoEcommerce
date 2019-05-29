<?php

	namespace Hcode\Model;

	use \Hcode\DB\Sql;
	use \Hcode\Model;
	use \Hcode\Mailer;

	class User extends Model {

		const SESSION = "User";
		const SECRET = "HcodePhp7Secret";
		const CIPHER = "aes-256-cbc";
		const USER_ERROR = "UserEror";
		const USER_ERROR_REGISTER = "UserErrorRegister";
		const SUCESS = 'UserSucess';
		
		public function getFromSession() {

			$user = new User();

			if(isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0) {

				$user->setData($_SESSION[User::SESSION]);

			}

			return $user;

		}


		public static function login($login, $password) {

			$sql = new Sql();

			$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b ON a.idperson = b.idperson WHERE a.deslogin = :LOGIN", array(
				":LOGIN"=>$login
			));


			if(count($results) === 0) {

				throw new \Exception("Usuário inexistente ou senha inválida.");

			}

			$data = $results[0];

			if(password_verify($password, $data['despassword']) === true) {

				$user = new User();	

				$user->setData($data);
				
				$data['desperson'] = utf8_encode($data['desperson']);

				$_SESSION[User::SESSION] = $user->getValues();

				return $user;

			} else {

				throw new \Exception("Usuário inexistente ou senha inválida.");

			}

		}

		public static function checkLogin($inadmin = true) {

			if(
				!isset($_SESSION[User::SESSION])
				||
				!$_SESSION[User::SESSION]
				||
				!(int)$_SESSION[User::SESSION]["iduser"] > 0
			) {
				//Não esta logado.
				return false;

			} else {

				if($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true) {

					return true;

				} else if($inadmin === false) {

					return true;

				} else {

					return false;

				}

			}

		} 

		public static function verifyLogin($inadmin = true) {
			
			if (!User::checkLogin($inadmin)) {
				
				if ($inadmin) {

					header("Location: /admin/login");

				} else {

					header("Location: /login");
			
				}
			
			exit;
		
			}
	
		}

		public static function logout() {

			$_SESSION[User::SESSION] = NULL;
			$_SESSION[Cart::SESSION] = NULL;
		}

		public static function listAll() {

			$sql = new Sql();

			return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");

		}

		public function save() {
			
			$sql = new Sql();

			$results = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
				":desperson"=>utf8_decode($this->getdesperson()),
				":deslogin"=>$this->getdeslogin(),
				":despassword"=>User::getPasswordHash($this->getdespassword()),
				":desemail"=>$this->getdesemail(),
				":nrphone"=>$this->getnrphone(),
				":inadmin"=>$this->getinadmin()
			));

			$this->setData($results[0]);

		}

		public function get($iduser) {

			$sql = new Sql();

			$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) where a.iduser = :iduser", array(
				":iduser"=>$iduser
			));

			$data = $results[0];

			$data['desperson'] = utf8_decode($data['desperson']);

			$this->setData($data);

		}

		public function update() {

			$sql = new Sql();

			$results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
				":iduser"=>$this->getiduser(),
				":desperson"=>utf8_decode($this->getdesperson()),
				":deslogin"=>$this->getdeslogin(),
				":despassword"=>$this->getdespassword(),
				":desemail"=>$this->getdesemail(),
				":nrphone"=>$this->getnrphone(),
				":inadmin"=>$this->getinadmin()
			));

			$this->setData($results[0]);

		}

		public function delete() {

			$sql = new Sql();

			$sql->select("CALL sp_users_delete(:iduser)", array(
				":iduser"=>$this->getiduser()
			));

		}

		public static function getForgot($email, $inadmin = true) {

			$sql = new Sql();

			$results = $sql->select("
				SELECT * FROM tb_persons a 
				INNER JOIN tb_users b USING(idperson) 
				WHERE a.desemail = :email",
				array(
					":email"=>$email
				));

			if(count($results) === 0){

				echo "Algo de errado ocorreu";

			} else {

				
				if(count($results) === 0) {

					throw new \Exeception("Não foi possível recuperar a senha.");

				} else {


				$data = $results[0];

				$resultsRecovery = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
					":iduser"=>$data["iduser"],
					":desip"=>$_SERVER["REMOTE_ADDR"]
				));

					if(count($resultsRecovery) === 0) {

						throw new \Exception("Não foi possível recuperar a senha.");

					} else {

						$dataRecovery = $resultsRecovery[0];

						$key = hex2bin('5ae1b8a17bad4da4fdac796f64c16ecd');
						$iv = hex2bin('34857d973953e44afb49ea9d61104d8c');
						$code = base64_encode(openssl_encrypt($dataRecovery["idrecovery"], User::CIPHER, $key, OPENSSL_RAW_DATA, $iv));

						if($inadmin === true) {

							$link = "http://www.hcodecommerce.com.br:80/admin/forgot/reset?code=$code";

						} else {

							$link = "http://www.hcodecommerce.com.br:80/forgot/reset?code=$code";

						}

						$mailer = new Mailer($data["desemail"], $data["desperson"], "Redefiner Senha da Hcode Store", "forgot", array(
							"name"=>$data["desperson"],
							"link"=>$link
						));


						$mailer->send();

						return $data;

					}

				}

			}
 
		}

		public static function validForgotDecrypt($code) {

	     	$key = hex2bin('5ae1b8a17bad4da4fdac796f64c16ecd');
			$iv = hex2bin('34857d973953e44afb49ea9d61104d8c');
			$idrecovery = openssl_decrypt(base64_decode($code), User::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

			$sql = new Sql();

			$results = $sql->select("
				SELECT * FROM tb_userspasswordsrecoveries a
				INNER JOIN tb_users b USING(iduser)
				INNER JOIN tb_persons c USING(idperson)
				WHERE
					a.idrecovery = :idrecovery
					AND 
					a.dtrecovery IS NULL
					AND
					DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();
				", array(
					":idrecovery"=>$idrecovery
				));

			if(count($results) === 0) {

				throw new \Exception("Não foi possível recuperar a senha.");

			} else {

				return $results[0];

			}

		}

		public static function setForgotUsed($idrecovery) {

			$sql = new Sql();

			$sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
				":idrecovery"=>$idrecovery
			));

		}

		public function setPassword($password) {

			$sql = new Sql();

			$result = $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
				":password"=>User::getPasswordHash($password),
				":iduser"=>$this->getiduser()
			));

		}

		public static function setUserError($msg) {

			$_SESSION[User::USER_ERROR] = $msg;

		}

		public static function getUserError() {

			$msg = (isset($_SESSION[User::USER_ERROR])) ? $_SESSION[User::USER_ERROR] : "";

			User::clearUserError();

			return $msg;

		}

		public static function clearUserError() {

			$_SESSION[User::USER_ERROR] = NULL;

		}

		public static function setUserErrorRegister($msg) {

			$_SESSION[User::USER_ERROR_REGISTER] = $msg;

		}

		public static function getUserErrorRegister() {

			$msg = (isset($_SESSION[User::USER_ERROR_REGISTER]) && $_SESSION[User::USER_ERROR_REGISTER]) ? $_SESSION[User::USER_ERROR_REGISTER] : '';

			User::clearUserErrorRegister();

			return $msg;

		}

		public static function clearUserErrorRegister() {

			$_SESSION[User::USER_ERROR_REGISTER] = NULL;

		}

		public static function setMsgSucess($msg) {

			$_SESSION[User::SUCESS] = $msg;

		}

		public static function getMsgSucess() {

			$msg = (isset($_SESSION[User::SUCESS])) ? $_SESSION[User::SUCESS] : "";

			User::clearMsgSucess();

			return $msg;

		}

		public static function clearMsgSucess() {

			$_SESSION[User::SUCESS] = NULL;

		}

		public static function checkLoginExist($login) {

			$sql = new Sql();

			$results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin", [
				':deslogin'=>$login
			]);

			return (count($results) > 0);

		}

		public static function checkEmailExist($email) {

			$sql = new Sql();

			$results = $sql->select("SELECT * FROM tb_users WHERE desemail = :desemail", [
				':desemail'=>$email
			]);

			return (count($results) > 0);

		}

		public static function getPasswordHash($password) {

			return password_hash($password, PASSWORD_DEFAULT, ['cost'=>10]);

		}

	}

?>