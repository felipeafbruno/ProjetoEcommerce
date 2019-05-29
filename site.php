<?php

	use \Hcode\Page;
	use \Hcode\Model\Product;
	use \Hcode\Model\Category;
	use \Hcode\Model\Cart;
	use \Hcode\Model\Address;
	use \Hcode\Model\User;

	$app->get('/', function() {

		$products = Product::listAll();

		$page = new Page();

		$page->setTpl("index", [
			'products'=>Product::checkList($products)
		]);

	});

	$app->get("/categories/:idcategory", function($idcategory) {

		$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

		$category = new Category();
		
		$category->get((int)$idcategory);

		$pagination = $category->getProductsPage($page);

		$page = new Page();

		$pages = [];

		for($i=1; $i <= $pagination['pages']; $i++) {

			array_push($pages, [
				'link'=>'/categories/'.$category->getidcategory().'?page='.$i,
				'page'=>$i
			]);

		}

		$page->setTpl("category", [
			'category'=>$category->getValues(),
			'products'=>$pagination["data"],
			'pages'=>$pages
		]);

	});

	$app->get("/product/:desurl", function($desurl) {

		$product = new Product();

		$product->getFromURL($desurl);

		$page = new Page();

		$page->setTpl("product-detail", [
			'product'=>$product->getValues(),
			'categories'=>$product->getCategories()
		]);

	});

	$app->get("/cart", function() {

		//Recuperar o cart da Sessão.
		$cart = Cart::getFromSession();

		$page = new Page();

		$page->setTpl("cart", [
			'cart'=>$cart->getValues(),
			'products'=>$cart->getProductsCart(),
			'error'=>Cart::getCartError()
		]);

	});

	$app->get("/cart/:idproduct/add", function($idproduct) {

		$product = new Product();

		$product->get((int) $idproduct);

		$cart = new Cart();

		$cart = Cart::getFromSession();

		$qtd = (isset($_GET['qtd'])) ? (int) $_GET['qtd'] : 1;

		for($i = 0; $i < $qtd; $i++){

			$cart->addProductCart($product);

		}

		header("Location: /cart");
		exit;

	});

	$app->get("/cart/:idproduct/minus", function($idproduct) {

		$product = new Product();

		$product->get((int) $idproduct);

		$cart = new Cart();

		$cart = Cart::getFromSession();

		$cart->removeProductCart($product);

		header("Location: /cart");
		exit;

	});

	$app->get("/cart/:idproduct/remove", function($idproduct) {

		$product = new Product();

		$product->get((int) $idproduct);

		$cart = new Cart();

		$cart = Cart::getFromSession();

		$cart->removeProductCart($product, true);

		header("Location: /cart");
		exit;

	});

	$app->post("/cart/freight", function() {
		
		$cart = Cart::getFromSession();

		$cart->setFreight($_POST['zipcode']);

		header("Location: /cart");
		exit;

	});

	$app->get("/checkout", function() {

		User::VerifyLogin(false);

		$address = new Address();

		$cart = Cart::getFromSession();

		if(isset($_GET['deszipcode'])) {

			$_GEST['deszipcode'] = $cart->getzipcode();

		}

		if(isset($_GET['deszipcode'])) {

			$address->loadFromCEP($_GET['deszipcode']);

			$cart->setdeszipcode($_GET['deszipcode']);

			$cart->save();

			$cart->getCalculateTotal();

		}

		if(!$address->getdesaddress()) $address->setdesaddress('');
		if(!$address->getdescomplement()) $address->setdescomplement('');
		if(!$address->getdesdistrict()) $address->setdesdistrict('');
		if(!$address->getdescity()) $address->setdescity('');
		if(!$address->getdesstate()) $address->setdesstate('');
		if(!$address->getdescountry()) $address->setdescountry('');
		if(!$address->getdeszipcode()) $address->setdeszipcode('');

		$page = new Page();

		$page->setTpl("checkout", [
			'cart'=>$cart->getValues(),
			'address'=>$address->getValues(),
			'products'=>$cart->getProductsCart(),
			'error'=>$address->getAddressError()
		]);

	});

	$app->post("/checkout", function() {

		User::verifyLogin(false);

		if(!isset($_POST['deszipcode']) || $_POST['deszipcode'] === '') {

			Address::setAddressError("Informe o CEP!");
			header("Location: /checkout");
			exit;

		}

		if(!isset($_POST['desaddress']) || $_POST['desaddress'] === '') {

			Address::setAddressError("Informe o endereço!");
			header("Location: /checkout");
			exit;

		}

		if(!isset($_POST['desdistrict']) || $_POST['desdistrict'] === '') {

			Address::setAddressError("Informe o bairro!");
			header("Location: /checkout");
			exit;

		}

		if(!isset($_POST['descity']) || $_POST['descity'] === '') {

			Address::setAddressError("Informe a cidade!");
			header("Location: /checkout");
			exit;

		}

		if(!isset($_POST['desstate']) || $_POST['desstate'] === '') {

			Address::setAddressError("Informe o estado!");
			header("Location: /checkout");
			exit;

		}

		if(!isset($_POST['descountry']) || $_POST['descountry'] === '') {

			Address::setAddressError("Informe o país!");
			header("Location: /checkout");
			exit;

		}

		$user = User::getFromSession();

		$_POST['idperson'] = $user->getidperson();

		$address = new Address();

		$address->setData($_POST);

		$address->save();

		header("Location: /order");
		exit;

	});	

	$app->get("/login", function() {

		$page = new Page();

		$page->setTpl("login", [
			'error'=>User::getUserError(),
			'errorRegister'=>User::getUserErrorRegister(),
			'registerValues'=>(isset($_SESSION['registerValues'])) ? $_SESSION['registerValues'] : ['name' => '', 'email' => '', 'phone' => '']
		]);

	});	

	$app->post("/login", function() {

		try {

			User::login($_POST['login'], $_POST['password']);
		
		} catch(Exception $e) {

			User::setUserError($e->getMessage());

		}
		header('Location: /checkout');

		exit;

	});

	$app->get("/logout", function() {

		User::logout();

		header("Location: /checkout");
		exit;

	});

	$app->post("/register", function() {

		$_SESSION['registerValues'] = $_POST;

		if(!isset($_POST['name']) || $_POST['name'] == '') {

			User::setUserErrorRegister("Preencha o seu nome.");

			header("Location: /login");

			exit;

		}

		if(!isset($_POST['email']) || $_POST['email'] == '') {

			User::setUserErrorRegister("Preencha o seu email.");

			header("Location: /login");

			exit;

		}

		if(!isset($_POST['password']) || $_POST['password'] == '') {

			User::setUserErrorRegister("Preencha a sua senha.");

			header("Location: /login");
			exit;

		}

		if(User::checkLoginExist($_POST['email']) === true) {

			User::setUserErrorRegister("Esse endreço de email já está sendo usado por outro usuário.");

			header("Location: /login");
			exit;

		}

		$user = new User();

		$user->setData([
			'inadmin'=>0,
			'deslogin'=>$_POST['email'],
			'desperson'=>$_POST['name'],
			'desemail'=>$_POST['email'],
			'despassword'=>$_POST['password'],
			'nrphone'=>$_POST['phone']
		]);

		$user->save();

		User::login($_POST['email'], $_POST['password']);

		header('Location: /checkout');
		exit;

	});

	$app->get("/forgot", function() {

		$page = new Page();

		$page->setTpl("forgot");

	});

	$app->post("/forgot", function() {

		User::getForgot($_POST["email"], false);

		header("Location: /forgot/sent");
		exit;

	});

	$app->get("/forgot/sent", function() {

		$page = new Page();

		$page->setTpl("forgot-sent");

	});

	$app->get("/forgot/reset", function() {

	 	$user = User::validForgotDecrypt($_GET["code"]);

	 	$page = new Page();

	 	$page->setTpl("forgot-reset", array(
	 		"name"=>$user["desperson"],
	 		"code"=>$_GET["code"]
	 	));

	});

	$app->post("/forgot/reset", function() {

		$forgot = User::validForgotDecrypt($_POST["code"]);

		User::setForgotUsed($forgot["idrecovery"]);

		$user = new User();

		$user->get((int)$forgot["iduser"]);

		$password = $_POST["password"];

		$user->setPassword($password);

		$page = new Page();

	 	$page->setTpl("forgot-reset-success");

	});

	$app->get("/profile", function() {

		User::verifyLogin(false);

		$user = User::getFromSession();

		$page = new Page();

		$page->setTpl("profile", [
			'user'=>$user->getValues(),
			'profileMsg'=>User::getMsgSucess(),
			'profileError'=>User::getUserErrorRegister()
		]);

	}); 

	$app->post("/profile", function() {

		User::verifyLogin(false);

		$user = User::getFromSession();

		if(!isset($_POST['desperson']) || $_POST['desperson'] === '') {

			User::setUserErrorRegister("Preencha o seu nome");

			header("Location: /profile");
			exit;

		}

		if(!isset($_POST['desemail']) || $_POST['desemail'] === '') {

			User::setUserErrorRegister("Preencha o seu e-mail");

			header("Location: \profile");
			exit;

		}

		if($_POST['desemail'] !== $user->getdesemail()) {

			if(User::checkEmailExist($_POST['desemail'])) {
	
				User::setUserErrorRegister("Este endereço de e-mail já está cadastro");

				header("Location: \profile");
				exit;
				
			}

		}


		$_POST['inadmin'] = $user->getinadmin();
		$_POST['despassword'] = $user->getdespassword();

		$user->setData($_POST);

		$user->update();

		User::setMsgSucess("Dados alterados com sucesso");

		header('Location: /profile');
		exit;

	}) 

?>
