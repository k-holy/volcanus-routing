#Volcanus_Routing

ページコントローラ(PageController)パターンで「きれいなURI」を実現するためのライブラリです。

フロントコントローラ(FrontController)パターンにおいてRouterと呼ばれるクラスは、
リクエストURIを解析して特定のクラスに振り分ける役目を担います。

ページコントローラパターンでの利用を想定したVolcanus_Routingでは、
リクエストURIを解析して特定のディレクトリにあるスクリプトファイルを読み込み、
カレントディレクトリを移動します。

また、パラメータディレクトリと呼ぶ特別な名前のディレクトリを設定することで、
リクエストURIのパスに含まれるパラメータを取得する機能を提供します。

##使用例

以下は Apache + mod_rewrite での使用例です。

###/.htaccess
	RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !\.(ico|gif|jpe?g|png|swf|pdf|css|js)$ [NC]
	RewriteRule ^(.*)$ __gateway.php [QSA,L]

存在しないディレクトリまたはファイルへのリクエストがあれば、
画像やPDF, CSS, JasvaScript等の静的ファイルへのリクエストを除き、
全てゲートウェイスクリプト(__gateway.php)に転送します。

###/__gateway.php
	<?php
	use Volcanus\Routing\Router;
	use Volcanus\Routing\Exception\NotFoundException;
	$router = Router::instance(array(
		'parameterDirectoryName' => '%VAR%', // パラメータディレクトリ名を %VAR% と設定する
		'searchExtensions'       => 'php',   // 読み込み対象スクリプトの拡張子を php と設定する
		'overwriteGlobals'       => true,    // ルーティング実行時、$_SERVERグローバル変数を上書きする
	));
	$router->importGlobals(); // $_SERVERグローバル変数から環境変数を取り込む
	try {
		$router->prepare();
	} catch (NotFoundException $e) {
		header(sprintf('%s 404 Not Found', $_SERVER['SERVER_PROTOCOL']));
		exit();
	}
	$router->execute();

"/categories/1/items/2/detail.json" というリクエストURIに対して上記の設定でルーティングを行った場合、
ドキュメントルート以下の "/categories/%VAR%/items/%VAR%/detail.php" スクリプトが読み込まれ、
カレントディレクトリを移動します。

ルーティング準備処理で読み込み対象のスクリプトが発見できなかった場合は、
Volcanus\Routing\Exception\NotFoundException 例外がスローされますので、
これをキャッチしてステータス 404 を返すことができます。

ルーティング実行時には、overwriteGlobals オプションを true に設定しているため、 
$_SERVERグローバル変数のうち PHP_SELF, SCRIPT_NAME, SCRIPT_FILENAME, PATH_INFO, PATH_TRANSLATED が、
ルーティング結果に従って書き換えられます。

###/categories/%VAR%/items/%VAR%/detail.php
	<?php
	use Volcanus\Routing\Router;
	$router = Router::instance();
	$categoryId = $router->parameter(0); // '1'
	$itemId     = $router->parameter(1); // '2'
	$extension  = $router->extension();  // 'json'

Router::instance()メソッドはSingletonとして実装されており、
読み込まれたスクリプトからルーティング結果を参照できます。

リクエストパスのうち、パラメータディレクトリ %VAR% に当たるセグメントを Router::parameter() メソッドによって取得したり、
本来のリクエストURIで指定された拡張子を Router::extension() メソッドによって取得できます。

読み込み先のスクリプトからこれらの機能を利用するためにSingleton機能を提供していますが、
コンストラクタの利用を禁止しているわけではないので、
たとえばルーティング実行後にRouterのインスタンスやparameters()メソッドの戻り値をグローバル変数や類似のオブジェクトにセットしておき、
読み込み先のスクリプトから参照するような使い方も可能です。
