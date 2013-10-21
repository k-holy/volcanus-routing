#Volcanus_Routing

[![Latest Stable Version](https://poser.pugx.org/volcanus/routing/v/stable.png)](https://packagist.org/packages/volcanus/routing)
[![Build Status](https://travis-ci.org/k-holy/volcanus-routing.png?branch=master)](https://travis-ci.org/k-holy/volcanus-routing)

ページコントローラ(PageController)パターンで「きれいなURI」を実現するためのライブラリです。

フロントコントローラ(FrontController)パターンにおいてRouterと呼ばれるクラスは、
リクエストURIを解析して特定のクラスに振り分ける役目を担います。

ページコントローラパターンでの利用を想定したVolcanus_Routingでは、
リクエストURIを解析して特定のディレクトリにあるスクリプトファイルを読み込み、
カレントディレクトリを移動します。

また、パラメータディレクトリと呼ぶ特別な名前のディレクトリを設定することで、
リクエストURIのパスに含まれるパラメータを取得する機能を提供します。


##対応環境

* PHP 5.3以降


##依存ライブラリ

なし

[Volcanus_Configuration](https://github.com/k-holy/Volcanus_Configuration) への依存は ver 0.2.3 よりなくなりました。


##簡単な使い方

以下は Apache + mod_rewrite での使用例です。

###/.htaccess
```
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ __gateway.php [QSA,L]
```

なお Apache 2.2.16以上の場合は [FallbackResource](http://httpd.apache.org/docs/trunk/en/mod/mod_dir.html#fallbackresource) ディレクティブが便利です。

###/.htaccess (Apache 2.2.16以上)
```
FallbackResource /__gateway.php
```

存在しないディレクトリまたはファイルへのリクエストがあれば、
以下のゲートウェイスクリプト(__gateway.php)に転送されます。

###/__gateway.php
```php
<?php
use Volcanus\Routing\Router;
use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

$router = Router::instance(array(
    'parameterDirectoryName' => '%VAR%', // パラメータディレクトリ名を %VAR% と設定する
    'searchExtensions'       => 'php',   // 読み込み対象スクリプトの拡張子を php と設定する
    'overwriteGlobals'       => true,    // ルーティング実行時、$_SERVERグローバル変数を上書きする
));

$router->importGlobals(); // $_SERVERグローバル変数から環境変数を取り込む

try {

    $router->prepare()->execute();

} catch (\Exception $e) {

    $text = '500 Internal Server Error';
    if ($e instanceof NotFoundException) {
        $text = '404 Not Found';
    }

    if (!headers_sent() && isset($_SERVER['SERVER_PROTOCOL'])) {
        header(sprintf('%s %s', $_SERVER['SERVER_PROTOCOL'], $text));
    }

    echo sprintf('<html><head><title>Error %s</title></head><body><h1>%s</h1></body></html>'
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
}
```

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
```php
<?php
use Volcanus\Routing\Router;

$router = Router::instance();
$categoryId = $router->parameter(0); // '1'
$itemId     = $router->parameter(1); // '2'
$extension  = $router->extension();  // 'json'
```

Router::instance()メソッドはSingletonとして実装されており、
読み込まれたスクリプトからルーティング結果を参照できます。

リクエストパスのうち、パラメータディレクトリ %VAR% に当たるセグメントを Router::parameter() メソッドによって取得したり、
本来のリクエストURIで指定された拡張子を Router::extension() メソッドによって取得できます。

読み込み先のスクリプトからこれらの機能を利用するためにSingleton機能を提供していますが、
コンストラクタの利用を禁止しているわけではないので、
たとえばルーティング実行後にRouterのインスタンスやparameters()メソッドの戻り値をグローバル変数や類似のオブジェクトにセットしておき、
読み込み先のスクリプトから参照するような使い方も可能です。


##デリミタ指定によるパラメータの型指定

ver 0.2.0より、左右のデリミタおよび型を指定して、リクエストパスのパラメータを取得できるようになりました。

標準ではパラメータのセグメントとして alpha, digit, alnum, graph といったCtype関数の各キーワードを含むディレクトリ名を利用できます。

###/__gateway.php
```php
<?php
use Volcanus\Routing\Router;
use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

$router = Router::instance(array(
    'parameterLeftDelimiter'  => '{%', // パラメータの左デリミタは {% とする
    'parameterRightDelimiter' => '%}', // パラメータの右デリミタは %} とする
    'searchExtensions' => 'php', // 読み込み対象スクリプトの拡張子を php と設定する
    'overwriteGlobals' => true,  // ルーティング実行時、$_SERVERグローバル変数を上書きする
));

$router->importGlobals(); // $_SERVERグローバル変数から環境変数を取り込む

try {

    $router->prepare()->execute();

} catch (\Exception $e) {

    $text = '500 Internal Server Error';
    if ($e instanceof NotFoundException) {
        $text = '404 Not Found';
    } elseif ($e instanceof InvalidParameterException) {
        $text = '400 Bad Request';
    }

    if (!headers_sent() && isset($_SERVER['SERVER_PROTOCOL'])) {
        header(sprintf('%s %s', $_SERVER['SERVER_PROTOCOL'], $text));
    }

    echo sprintf('<html><head><title>Error %s</title></head><body><h1>%s</h1></body></html>'
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
```

ドキュメントルート以下に "/users/{%digit%}/index.php" というスクリプトが存在するとして…

"/users/foo" というリクエストURIのルーティングでは、InvalidParameterException によりステータス400が返されます。

"/users/1" というリクエストURIのルーティングでは、該当スクリプトが読み込まれます。

###/users/{%digit%}/index.php
```php
<?php
use Volcanus\Routing\Router;

$router = Router::instance();
$$user_id = $router->parameter(0); // (string) '1'
```

##デリミタ指定および独自フィルタによるパラメータの検証と変換

parameterFilters オプションを利用して独自のフィルタを定義し、
Ctype関数に拠らないパラメータの検証を行ったり、パラメータの値を変換することもできます。

###/__gateway.php
```php
<?php
use Volcanus\Routing\Router;
use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

$router = Router::instance(array(
    'parameterLeftDelimiter'  => '{%', // パラメータの左デリミタは {% とする
    'parameterRightDelimiter' => '%}', // パラメータの右デリミタは %} とする
    'parameterFilters' => array(
        // 独自のフィルタ "profile_id" を設定する
        'profile_id' => function($value) {
            if (strspn($value, '0123456789abcdefghijklmnopqrstuvwxyz_-.') !== strlen($value)) {
                throw new InvalidParameterException('oh...');
            }
            return $value;
        },
        // 標準のフィルタ "digit" を上書き設定する
        'digit' => function($value) {
            if (!ctype_digit($value)) {
                throw new InvalidParameterException('oh...');
            }
            return intval($value);
        },
    ),
    'searchExtensions' => 'php', // 読み込み対象スクリプトの拡張子を php と設定する
    'overwriteGlobals' => true,  // ルーティング実行時、$_SERVERグローバル変数を上書きする
));

$router->importGlobals(); // $_SERVERグローバル変数から環境変数を取り込む

try {

    $router->prepare()->execute();

} catch (\Exception $e) {

    $text = '500 Internal Server Error';
    if ($e instanceof NotFoundException) {
        $text = '404 Not Found';
    } elseif ($e instanceof InvalidParameterException) {
        $text = '400 Bad Request';
    }

    if (!headers_sent() && isset($_SERVER['SERVER_PROTOCOL'])) {
        header(sprintf('%s %s', $_SERVER['SERVER_PROTOCOL'], $text));
    }

    echo sprintf('<html><head><title>Error %s</title></head><body><h1>%s</h1></body></html>'
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
}
```

ドキュメントルート以下に "/users/{%digit%}/profiles/{%profile_id%}/index.php" というスクリプトが存在するとして…

"/users/1/profiles/invalid@id" というリクエストURIのルーティングでは、InvalidParameterException によりステータス400が返されます。

"/users/1/profiles/k-holy" というリクエストURIのルーティングでは、該当スクリプトが読み込まれます。

###/users/{%digit%}/profiles/{%profile_id%}/index.php
```php
<?php
use Volcanus\Routing\Router;

$router = Router::instance();
$user_id = $router->parameter(0); // (int) 1
$profile_id = $router->parameter(1); // (string) 'k-holy'
```

##fallbackScriptオプションを指定して、スクリプトが見つからない場合に代替スクリプトを読み込む

ver 0.3.0より、スクリプトが見つからない場合にドキュメントルート以下の任意のパスに設置した代替スクリプトを読み込むための fallbackScript オプションを追加しました。

###/__gateway.php
```php
<?php
use Volcanus\Routing\Router;
use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

$router = Router::instance(array(
    'fallbackScript' => '/path/to/fallback.php', // スクリプトが見つからない場合は ドキュメントルート/path/to/fallback.php を読み込む
));

$router->importGlobals(); // $_SERVERグローバル変数から環境変数を取り込む

try {

    $router->prepare()->execute();

} catch (\Exception $e) {

    $text = '500 Internal Server Error';
    if ($e instanceof NotFoundException) {
        $text = '404 Not Found';
    }

    if (!headers_sent() && isset($_SERVER['SERVER_PROTOCOL'])) {
        header(sprintf('%s %s', $_SERVER['SERVER_PROTOCOL'], $text));
    }

    echo sprintf('<html><head><title>Error %s</title></head><body><h1>%s</h1></body></html>'
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
}
```

上記の設定で、たとえば存在しないパス /path/not/found がリクエストされた場合、カレントディレクトリを /path/to に移動して fallback.php を実行します。


fallbackScript オプションをファイル名で指定した場合、リクエストされたディレクトリ内にそのファイルがあれば読み込みます。

###/__gateway.php
```php
<?php
use Volcanus\Routing\Router;
use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

$router = Router::instance(array(
    'fallbackScript' => 'fallback.php', // スクリプトが見つからない場合は fallback.php があれば読み込む
));

$router->importGlobals(); // $_SERVERグローバル変数から環境変数を取り込む

try {

    $router->prepare()->execute();

} catch (\Exception $e) {

    $text = '500 Internal Server Error';
    if ($e instanceof NotFoundException) {
        $text = '404 Not Found';
    }

    if (!headers_sent() && isset($_SERVER['SERVER_PROTOCOL'])) {
        header(sprintf('%s %s', $_SERVER['SERVER_PROTOCOL'], $text));
    }

    echo sprintf('<html><head><title>Error %s</title></head><body><h1>%s</h1></body></html>'
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        , htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
    );
}
```

上記の設定で、たとえば存在しないパス /path/not/found がリクエストされ、/path/not/found.php が存在せず /path/not/fallback.php が存在する場合、カレントディレクトリを /path/not に移動して fallback.php を実行します。
