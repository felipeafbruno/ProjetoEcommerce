<?php

	namespace Hcode\Model;

	use \Hcode\DB\Sql;
	use \Hcode\Model;
	use \Hcode\Model\Cart;


	class Order extends Model {

		const SUCCESS = "Order-Success";
		const ORDER_ERROR = "Order-Error";

		public function save() {

			$sql = new Sql();

			$results = $sql->select("CALL sp_orders_save(:idorder, :idcart, :iduser, :idstatus, :idaddress, :vltotal)", [
				':idorder'=>$this->getidorder(),
				':idcart'=>$this->getidcart(),
				':iduser'=>$this->getiduser(),
				':idstatus'=>$this->getidstatus(),
				':idaddress'=>$this->getidaddress(),
				':vltotal'=>$this->getvltotal()
			]);

			if(count($results) > 0) {

				$this->setData($results[0]);

			}

		}

		public function get($idorder) {

			$sql = new Sql();

			$results = $sql->select("
				SELECT *
					FROM tb_orders a 
					INNER JOIN tb_ordersstatus b USING(idstatus)
					INNER JOIN tb_carts c USING(idcart)
					INNER JOIN tb_users d ON d.iduser = a.iduser
					INNER JOIN tb_addresses e USING(idaddress)
					INNER JOIN tb_persons f ON f.idperson = d.idperson
				WHERE a.idorder = :idorder
				", [

					':idorder'=>$idorder

				]);

			if(count($results) > 0) {

				$this->setData($results[0]);

			}

			return $results;

		}

		public static function listAll() {

			$sql = new Sql();

			$results = $sql->select("
				SELECT *
				FROM tb_orders a 
				INNER JOIN tb_ordersstatus b USING(idstatus)
				INNER JOIN tb_carts c USING(idcart)
				INNER JOIN tb_users d ON d.iduser = a.iduser
				INNER JOIN tb_addresses e USING(idaddress)
				INNER JOIN tb_persons f ON f.idperson = d.idperson
				ORDER BY a.dtregister DESC"
			);

			return $results;
		}

		public function delete() {

			$sql = new Sql();

			$sql->query("DELETE FROM tb_orders WHERE idorder = :idorder", [
				':idorder'=>$this->getidorder()
			]);

		}

		public function getCart():Cart {

			$cart = new Cart();

			$cart->get((int)$this->getidcart());

			return $cart;

		}

		public static function setOrderError($msg) {

			$_SESSION[Order::ORDER_ERROR] = $msg;

		}

		public static function getOrderError() {

			$msg = (isset($_SESSION[Order::ORDER_ERROR])) ? $_SESSION[Order::ORDER_ERROR] : "";

			Order::clearOrderError();

			return $msg;

		}

		public static function clearOrderError() {

			$_SESSION[Order::ORDER_ERROR] = NULL;

		}

		public static function setMsgSuccess($msg) {

			$_SESSION[Order::SUCCESS] = $msg;

		}

		public static function getMsgSuccess() {

			$msg = (isset($_SESSION[Order::SUCCESS])) ? $_SESSION[Order::SUCCESS] : "";

			Order::clearMsgSuccess();

			return $msg;

		}

		public static function clearMsgSuccess() {

			$_SESSION[Order::SUCCESS] = NULL;

		}

		public static function getPage($page = 1, $itemsPerPage = 10) {

			//calculo para saber em qual número de página esta os items.
			$start = ($page - 1) * $itemsPerPage;

			$sql = new Sql();

			$results = $sql->select("
				SELECT SQL_CALC_FOUND_ROWS * 
				FROM tb_orders a 
				INNER JOIN tb_ordersstatus b USING(idstatus)
				INNER JOIN tb_carts c USING(idcart)
				INNER JOIN tb_users d ON d.iduser = a.iduser
				INNER JOIN tb_addresses e USING(idaddress)
				INNER JOIN tb_persons f ON f.idperson = d.idperson
				ORDER BY a.dtregister DESC
				LIMIT $start, $itemsPerPage;
			");

			$resultTotal = $sql->select("
				SELECT FOUND_ROWS() AS nrtotal;
			");

			return [
				'data'=>$results,
				'total'=>(int)$resultTotal[0]["nrtotal"],
				'pages'=>ceil($resultTotal[0]["nrtotal"] / $itemsPerPage)
			];

		}

		public static function getPageSearch($search, $page = 1, $itemsPerPage = 10) {

			//calculo para saber em qual número de página esta os items.
			$start = ($page - 1) * $itemsPerPage;

			$sql = new Sql();

			$results = $sql->select("
				SELECT SQL_CALC_FOUND_ROWS * 
				FROM tb_orders a 
				INNER JOIN tb_ordersstatus b USING(idstatus)
				INNER JOIN tb_carts c USING(idcart)
				INNER JOIN tb_users d ON d.iduser = a.iduser
				INNER JOIN tb_addresses e USING(idaddress)
				INNER JOIN tb_persons f ON f.idperson = d.idperson
				WHERE a.idorder = :idorder OR f.desperson LIKE :search
				LIMIT $start, $itemsPerPage;
			", [
				':search'=>'%'.$search.'%',
				':id'=>$search
			]);

			$resultTotal = $sql->select("
				SELECT FOUND_ROWS() AS nrtotal;
			");

			return [
				'data'=>$results,
				'total'=>(int)$resultTotal[0]["nrtotal"],
				'pages'=>ceil($resultTotal[0]["nrtotal"] / $itemsPerPage)
			];

		}

	}

?>