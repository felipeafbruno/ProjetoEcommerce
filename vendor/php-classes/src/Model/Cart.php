<?php

	namespace Hcode\Model;

	use \Hcode\DB\Sql;
	use \Hcode\Model;
	use \Hcode\Mailer;
	use  \Hcode\Model\User;

	class Cart extends Model {

		const SESSION = "Cart";
		const SESSION_ERROR = "CartError";

		public function save() {

			$sql = new Sql();

			$results = $sql->select("CALL sp_carts_save(:idcart, :dessessionid, :iduser, :deszipcode, :vlfreight, :nrdays)", [
				':idcart'=>$this->getidcart(),
				':dessessionid'=>$this->getdessessionid(),
				':iduser'=>$this->getiduser(),
				':deszipcode'=>$this->getdeszipcode(),
				':vlfreight'=>$this->getvlfreight(),
				':nrdays'=>$this->getnrdays()		
			]);

			$this->setData($results[0]);

		}

		public function get(int $idcart) {

			$sql = new Sql();

			$results = $sql->select("SELECT * FROM tb_carts WHERE idcart = :idcart", [
				':idcart'=>$idcart
			]);

			if(count($results) > 0) {

				$this->setData($results[0]);
				
			}

		}

		public static function getFromSession() {

			$cart = new Cart();
			
			//Condicional para pegar os dados do carrinho.
			/*
				-	Verifica se a sessão do carrinho esta armazenada na sessão do usuário
  					e se o idcart(id do carrinho) armazenado na sessão do carrinho não é menor
  					0. 
			*/
  
			if(isset($_SESSION[Cart::SESSION]) && $_SESSION[Cart::SESSION]['idcart'] > 0) {
			/*
				-	Dentro das condições o método get() retorna o carrinho -> método leva um
  					parâmetro $_SESSION[Cart::SESSION]['idcart']. 
			*/
				$cart->get((int) $_SESSION[Cart::SESSION]['idcart']);

			} else {
				/*
					Caso contrario getFromSessionID() da classe carrinho será utilizado para
					obter o carrinho por meio do id da Sessão.
				*/
				$cart->getFromSessionID();

				/*
					-	Com base no retornado é necessário agora saber se existe ou não um carrinho com o id da sessão.
					- 	A condicional abaixo faz justamente isso
						-	primeiro verifica se o id do carrinho é zero: caso seja então ele não existe e precisa ser criado.
				*/
				if(!(int)$cart->getidcart() > 0) {

					$data = [ 
						'dessessionid'=>session_id()
					];

					if(User::checkLogin(false)) {

						$user = User::getFromSession();

						$data['iduser'] = $user->getiduser();

					}
					/*
						Independente do resultado do código acissa o carrinho sempre será criado no fim da execução do método.
					*/

					$cart->setData($data);

					$cart->save();

					$cart->setToSession();

				}

			}

			return $cart;

		}

		public function getFromSessionID() {

			$sql = new Sql();

			$results = $sql->select("SELECT * FROM tb_carts WHERE dessessionid = :dessessionid", [
				':dessessionid'=>session_regenerate_id()
			]);

			if(count($results) > 0) {

				$this->setData($results[0]);

			}

		}

		public function setToSession() {

			$_SESSION[Cart::SESSION] = $this->getValues();

		}

		/*
			Métodos que seguem abaixo fazem parte da lógica de adição, 
			remoção e listagem de produtos contidos no carrinho.
		*/
		public function addProductCart(Product $product) {

			$sql = new Sql();

			$sql->query("INSERT INTO tb_cartsproducts (idcart, idproduct) VALUES(:idcart, :idproduct)", [
				':idcart'=>$this->getidcart(),
				':idproduct'=>$product->getidproduct()
			]);

			$this->getCalculateTotal();

		}

		/*
			Quando feita a remoção o método funciona da seguinte maneira
			removeProductCart() recebe dois parâmetros, o primeiro um objeto 
			Product para obter o id do produto e o segundo uma variável booleana, 
			por padrão foi inicializada com false, que diz se um ou mais produtos 
			retirados do carrinho. 
		*/
		public function removeProductCart($product, $all = false) {

			$sql = new Sql();
			
			if($all) {

				$sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND
					dtremoved IS NULL", [
					':idcart'=>$this->getidcart(),
					':idproduct'=>$product->getidproduct()
				]);

			} else {

				$sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND
					dtremoved IS NULL LIMIT 1", [
						':idcart'=>$this->getidcart(),
						':idproduct'=>$product->getidproduct()
				]);

			}

			$this->getCalculateTotal();
		}

		public function getProductsCart() {

			$sql = new Sql();

			$row = $sql->select("SELECT b.idproduct, b.desproduct, b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl, COUNT(*) AS nrqtd, SUM(b.vlprice) AS vltotal
				FROM tb_cartsproducts a 
				INNER JOIN tb_products b ON a.idproduct = b.idproduct 
				WHERE a.idcart = :idcart AND a.dtremoved IS NULL 
				GROUP BY b.idproduct, b.desproduct, b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl
				ORDER BY b.desproduct;", [
					':idcart'=>$this->getidcart()
				]);

			
			return Product::checkList($row);
		}

		public function getProductsCartTotals() {

			$sql = new Sql();

			$results = $sql->select("
				SELECT SUM(vlprice) AS vlprice, SUM(vlwidth) AS vlwidth, SUM(vlheight) AS vlheight, SUM(vllength) AS vllength, SUM(vlweight) AS vlweight, COUNT(*) AS nrqtd   
				FROM tb_products a
				INNER JOIN tb_cartsproducts b ON a.idproduct = b.idproduct
				WHERE b.idcart = :idcart AND dtremoved IS NULL;", [
					":idcart"=>$this->getidcart()
				]);

			if(count($results) > 0){

				return $results[0];

			} else {

				return [];

			}

		}

		public function setFreight($nrzipcode) {

			$nrzipcode = str_replace('-', '', $nrzipcode);

			$totals = $this->getProductsCartTotals();

			if($totals['nrqtd'] > 0) {

				if($totals['vlwidth'] < 11) $totals['vlwidth'] = 11;
				if($totals['vllength'] < 16) $totals['vllength'] = 16;

				$qs = http_build_query([
					'nCdEmpresa'=>'',
					'sDsSenha'=>'',
					'nCdServico'=>'40010',
					'sCepOrigem'=>'02312070',
					'sCepDestino'=>$nrzipcode,
					'nVlPeso'=>$totals['vlweight'],
					'nCdFormato'=>'1',
					'nVlComprimento'=>$totals['vllength'],
					'nVlAltura'=>$totals['vlheight'],
					'nVlLargura'=>$totals['vlwidth'],
					'nVlDiametro'=>'0',
					'sCdMaoPropria'=>'S',
					'nVlValorDeclarado'=>$totals['vlprice'],
					'sCdAvisoRecebimento'=>'S'
				]);

				$xml = simplexml_load_file("http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx/CalcPrecoPrazo?".$qs);

				$result = $xml->Servicos->cServico[0];
			
				if($result->MsgErro != '') {

					Cart::setCartError($result->MsgErro);

				} else {

					Cart::clearCartError();

				}

				$this->setnrdays($result->PrazoEntrega);
				$this->setvlfreight(Cart::formatValueToDecimal($result->Valor));
				$this->setdeszipcode($nrzipcode);

				$this->save();

				return $result;

			} else {


			}

		}

		public static function formatValueToDecimal($value):float {

			$value = str_replace('.', ' ', $value);

			return str_replace(',', '.', $value);

		}

		public static function setCartError($msg) {

			$_SESSION[Cart::SESSION_ERROR] = $msg;

		}

		public static function getCartError() {

			$msg = (isset($_SESSION[Cart::SESSION_ERROR])) ? $_SESSION[Cart::SESSION_ERROR] : "";

			Cart::clearCartError();

			return $msg;

		}

		public static function clearCartError() {

			$_SESSION[Cart::SESSION_ERROR] = NULL;

		}

		public function updateFreight() {

			if($this->getdeszipcode() != '') {

				$this->setFreight($this->getdeszipcode());
			}

		}

		public function getValues() {

			$this->getCalculateTotal();

			return parent::getValues();

		}

		public function getCalculateTotal() {

			$this->updateFreight();

			$totals = $this->getProductsCartTotals();

			$this->setvlsubtotal($totals['vlprice']);
			$this->setvltotal($totals['vlprice'] + $this->getvlfreight());
		}

	}

?>