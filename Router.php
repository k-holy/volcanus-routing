<?php
/**
 * Volcanus libraries for PHP
 *
 * @copyright 2011-2013 k-holy <k.holy74@gmail.com>
 * @license The MIT License (MIT)
 */
namespace Volcanus\Routing;

use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

/**
 * Router
 *
 * @package Volcanus\Routing
 * @author k.holy74@gmail.com
 */
class Router
{

	/**
	 * @const ディレクトリ区切り文字
	 */
	const DS = '/';

	/**
	 * @const URLパス区切り文字
	 */
	const PS = '/';

	/**
	 * @var Router Singletonインスタンス
	 */
	private static $instance = null;

	/**
	 * @var string RequestURI解析パターン
	 */
	private static $requestUriPattern = '~\A(?:[^:/?#]+:)*(?://(?:[^/?#]*))*([^?#]*)(?:\?([^#]*))?(?:#(.*))?\z~i';

	/**
	 * @var array 受け入れる環境変数
	 */
	private static $acceptServerVars = array(
		'DOCUMENT_ROOT',
		'PATH_INFO',
		'PATH_TRANSLATED',
		'PHP_SELF',
		'REQUEST_URI',
		'SCRIPT_FILENAME',
		'SCRIPT_NAME',
	);

	/**
	 * @var Configuration 設定値のコレクション
	 */
	private $config;

	/**
	 * @var Configuration 環境変数のコレクション
	 */
	private $server;

	/**
	 * @var array ディレクトリパラメータ
	 */
	private $parameters;

	/**
	 * @var string 転送先ディレクトリ
	 */
	private $translateDirectory;

	/**
	 * @var string 読込対象ファイル
	 */
	private $includeFile;

	/**
	 * @var string 仮想URI (読込対象ファイル + PathInfo + QueryString)
	 */
	private $virtualUri;

	/**
	 * @var string リクエストされた拡張子
	 */
	private $extension;

	/**
	 * @var boolean ルーティング準備処理済みかどうか
	 */
	private $prepared;

	/**
	 * constructor
	 *
	 * @param array 設定オプション
	 */
	public function __construct(array $configurations = array())
	{
		$this->initialize($configurations);
	}

	/**
	 * オブジェクトを初期化します。
	 *
	 * @param array 設定オプション
	 */
	public function initialize(array $configurations = array())
	{
		$this->config = new Configuration(array(
			'parameterDirectoryName'  => '%VAR%',
			'searchExtensions'        => 'php',
			'overwriteGlobals'        => true,
			'parameterLeftDelimiter'  => null,
			'parameterRightDelimiter' => null,
			'parameterFilters'        => array(),
		));
		if (!empty($configurations)) {
			foreach ($configurations as $name => $value) {
				$this->config->offsetSet($name, $value);
			}
		}
		$this->server = new Configuration(
			array_fill_keys(self::$acceptServerVars, null)
		);
		$this->parameters = array();
		$this->translateDirectory = null;
		$this->includeFile = null;
		$this->virtualUri = null;
		$this->extension = null;
		$this->prepared = false;
		return $this;
	}

	/**
	 * Singletonインスタンスを返します。
	 *
	 * @param array デフォルトの設定
	 * @return object Router
	 */
	public static function instance(array $configurations = array())
	{
		if (!isset(self::$instance)) {
			self::$instance = new self($configurations);
		}
		return self::$instance;
	}

	/**
	 * __clone()
	 */
	private final function __clone()
	{
		throw new \RuntimeException('could not clone instance.');
	}

	/**
	 * 引数1つの場合は指定された設定の値を返します。
	 * 引数2つの場合は指定された設置の値をセットして$thisを返します。
	 *
	 * string parameterDirectoryName : パラメータディレクトリ名
	 * string searchExtensions       : ルーティングによる読み込み対象ファイルの拡張子
	 * bool   overwriteGlobals       : $_SERVER グローバル変数をフィールドに含まれる囲み文字のエスケープ文字 ※1文字のみ対応
	 *
	 * @param string 設定名
	 * @return mixed 設定値 または $this
	 * @throws \InvalidArgumentException
	 */
	public function config($name)
	{
		switch (func_num_args()) {
		case 1:
			return $this->config->offsetGet($name);
		case 2:
			$value = func_get_arg(1);
			if (isset($value)) {
				switch ($name) {
				case 'parameterDirectoryName':
				case 'searchExtensions':
				case 'parameterLeftDelimiter':
				case 'parameterRightDelimiter':
					if (!is_string($value)) {
						throw new \InvalidArgumentException(
							sprintf('The config parameter "%s" only accepts string.', $name));
					}
					break;
				case 'overwriteGlobals':
					if (is_int($value) || ctype_digit($value)) {
						$value = (bool)$value;
					}
					if (!is_bool($value)) {
						throw new \InvalidArgumentException(
							sprintf('The config parameter "%s" only accepts boolean.', $name));
					}
					break;
				case 'parameterFilters':
					if (!is_array($value)) {
						throw new \InvalidArgumentException(
							sprintf('The config parameter "%s" only accepts array.', $name));
					}
					break;
				}
				$this->config->offsetSet($name, $value);
			}
			return $this;
		}
		throw new \InvalidArgumentException('Invalid arguments count.');
	}

	/**
	 * 引数1つの場合は指定された環境変数の値を返します。
	 * 引数2つの場合は指定された環境変数の値をセットして$thisを返します。
	 *
	 * REQUEST_URIが不正な場合は値を受け付けず例外をスローします。
	 * DOCUMENT_ROOTは値に含まれるディレクトリ区切り文字を / に統一します。
	 *
	 * @param string 環境変数名
	 * @return mixed 環境変数値 または $this
	 * @throws \InvalidArgumentException
	 */
	public function server($name)
	{
		switch (func_num_args()) {
		case 1:
			return $this->server->offsetGet($name);
		case 2:
			$value = func_get_arg(1);
			if (isset($value)) {
				if (!in_array($name, self::$acceptServerVars)) {
					throw new \InvalidArgumentException(
						sprintf('The server vars "%s" could not accept.', $name));
				}
				if (!is_string($value)) {
					throw new \InvalidArgumentException(
						sprintf('The server vars "%s" is not string.', $name));
				}
				switch ($name) {
				case 'REQUEST_URI':
					if (!preg_match(self::$requestUriPattern, $value, $matches)) {
						throw new \InvalidArgumentException(
							sprintf('The server vars "%s" is not valid. "%s"', $name, $value));
					}
					break;
				case 'DOCUMENT_ROOT':
					if (DIRECTORY_SEPARATOR !== self::DS) {
						$value = str_replace(DIRECTORY_SEPARATOR, self::DS, $value);
					}
					break;
				}
				$this->server->offsetSet($name, $value);
			}
			return $this;
		}
		throw new \InvalidArgumentException('Invalid arguments count.');
	}

	/**
	 * $_SERVERグローバル変数から環境変数を取り込みます。
	 *
	 * @return object Router
	 */
	public function importGlobals()
	{
		if (isset($_SERVER)) {
			foreach ($_SERVER as $name => $value) {
				if (in_array($name, self::$acceptServerVars)) {
					$this->server($name, $value);
				}
			}
		}
		return $this;
	}

	/**
	 * 全てのパラメータを配列で返します。
	 *
	 * @return array パラメータの配列
	 */
	public function parameters()
	{
		return $this->parameters;
	}

	/**
	 * 指定されたパスパラメータの値を返します。
	 *
	 * @param int パラメータのインデックス
	 * @param mixed デフォルト値
	 * @return mixed パラメータ値
	 */
	public function parameter($index, $defaultValue = null)
	{
		if (array_key_exists($index, $this->parameters)) {
			return $this->parameters[$index];
		}
		return $defaultValue;
	}

	/**
	 * 転送先ディレクトリを返します。
	 *
	 * @return string
	 */
	public function translateDirectory()
	{
		return $this->translateDirectory;
	}

	/**
	 * 読込対象スクリプトの物理パスを返します。
	 *
	 * @return string
	 */
	public function includeFile()
	{
		return $this->includeFile;
	}

	/**
	 * 仮想URI (読込対象スクリプトのドキュメントルートからのパス + PathInfo + QueryString) を返します。
	 *
	 * @return string
	 */
	public function virtualUri()
	{
		return $this->virtualUri;
	}

	/**
	 * リクエストされた拡張子を返します。
	 *
	 * @return string
	 */
	public function extension()
	{
		return $this->extension;
	}

	/**
	 * リクエストURIを解析し、ルーティングの実行を準備します。
	 *
	 * @param string requestURI
	 * @return object Router
	 * @throws \RuntimeException
	 * @throws Exception\NotFoundException
	 * @throws Exception\InvalidParameterException
	 */
	public function prepare($requestUri = null)
	{
		if (isset($requestUri)) {
			$this->server('REQUEST_URI', $requestUri);
		}

		$requestUri = $this->server('REQUEST_URI');
		if (!isset($requestUri)) {
			throw new \RuntimeException('RequestUri is not set.');
		}

		$documentRoot = $this->server('DOCUMENT_ROOT');
		if (!isset($documentRoot)) {
			throw new \RuntimeException('DocumentRoot is not set.');
		}

		preg_match(self::$requestUriPattern, $requestUri, $matches);

		$requestPath = (isset($matches[1])) ? $matches[1] : '';
		$queryString = (isset($matches[2])) ? $matches[2] : '';
		$fragment    = (isset($matches[3])) ? $matches[3] : '';

		$parameterDirectoryName = $this->config('parameterDirectoryName');

		// パラメータの左デリミタと右デリミタの両方が指定されている場合のみ検索
		$parameterLeftDelimiter = $this->config('parameterLeftDelimiter');
		$parameterRightDelimiter = $this->config('parameterRightDelimiter');
		$searchParameter = (isset($parameterLeftDelimiter) && isset($parameterRightDelimiter));

		$searchExtensions = $this->config('searchExtensions');
		if (is_string($searchExtensions)) {
			$searchExtensions = explode(',', $searchExtensions);
		}

		$translateDirectory = '';
		$scriptName = '';
		$filename = null;
		$fileSegmentIndex = -1;

		$segments = $this->parseRequestPath($requestPath);
		$segmentCount = count($segments);

		foreach ($segments as $index => $segment) {

			// セグメント内の . を展開
			$pos = strrpos($segment, '.');
			if ($pos !== false) {
				$filename = $this->findFile($documentRoot . $translateDirectory, $segment);
				if (isset($filename)) {
					$scriptName .= self::DS . $filename;
					$fileSegmentIndex = $index;
					break;
				}
				$basename = substr($segment, 0, $pos);
				$extension = substr($segment, $pos + 1);
				if (!empty($searchExtensions) &&
					!in_array($extension, $searchExtensions)
				) {
					$filename = $this->findFile($documentRoot . $translateDirectory,
						$basename, $searchExtensions);
					if (isset($filename)) {
						$scriptName .= self::DS . $filename;
						$fileSegmentIndex = $index;
						$this->extension = $extension;
						break;
					}
				}
			}

			// 実ディレクトリがあれば次のセグメントへ
			if (is_dir($documentRoot . $translateDirectory . self::DS . $segment)) {
				$scriptName .= self::DS . $segment;
				$translateDirectory .= self::DS . $segment;
				continue;
			}

			// 実ファイルがあれば終了
			$filename = $this->findFile($documentRoot . $translateDirectory,
				$segment, $searchExtensions);
			if (isset($filename)) {
				$scriptName .= self::DS . $filename;
				$fileSegmentIndex = $index;
				break;
			}

			// パラメータディレクトリがあれば次のセグメントへ
			if (is_dir($documentRoot . $translateDirectory . self::DS .
				$parameterDirectoryName)
			) {
				$translateDirectory .= self::DS . $parameterDirectoryName;
				$scriptName .= self::DS . $segment;
				$this->parameters[] = $segment;
				continue;
			}

			// デリミタでパラメータディレクトリを検索
			if ($searchParameter) {
				$pattern = $documentRoot . $translateDirectory . self::DS .
					sprintf('%s*%s', $parameterLeftDelimiter, $parameterRightDelimiter);
				$dirs = glob($pattern, GLOB_ONLYDIR);
				if (count($dirs) >= 1) {
					$parameterValue = null;
					foreach ($dirs as $dir) {
						$parameterSegment = substr($dir, strrpos($dir, self::DS) + 1);
						$parameterType = substr($parameterSegment, strlen($parameterLeftDelimiter),
							strlen($parameterSegment) - strlen($parameterLeftDelimiter) - strlen($parameterRightDelimiter)
						);
						$filters = $this->config->offsetGet('parameterFilters');
						// ユーザフィルタが定義されており、実行結果がNULL以外の場合は妥当なパラメータ値とする
						if (array_key_exists($parameterType, $filters)) {
							$parameterValue = $filters[$parameterType]($segment);
							if (isset($parameterValue)) {
								break;
							}
						// ユーザフィルタが未定義かつCtype関数に合致すれば妥当なパラメータ値とする
						} elseif (is_callable('ctype_' . $parameterType)) {
							$filter = 'ctype_' . $parameterType;
							if (call_user_func($filter, $segment)) {
								$parameterValue = $segment;
								break;
							}
						}
					}
					if (!isset($parameterValue)) {
						throw new InvalidParameterException(
							sprintf('The parameter of the segment in Uri\'s path "%s" is not valid in requestPath "%s".', $segment, $requestPath));
					}
					$translateDirectory .= self::DS . $parameterSegment;
					$scriptName .= self::DS . $segment;
					$this->parameters[] = $parameterValue;
					continue;
				}
			}
			throw new NotFoundException(
				sprintf('The file that corresponds to the segment of Uri\'s path "%s" is not found in requestPath "%s".', $segment, $requestPath));
		}
		$translateDirectory = rtrim($translateDirectory, self::DS);

		if (!isset($filename)) {
			$filename = $this->findFile($documentRoot . $translateDirectory,
				'index', array('php', 'html'));
			if (isset($filename)) {
				$fileSegmentIndex = $segmentCount - 1;
				if (isset($segments[$fileSegmentIndex]) && strcmp($segments[$fileSegmentIndex], '') !== 0) {
					$scriptName .= self::DS . $filename;
				} else {
					$scriptName .= $filename;
				}
			}
		}

		if (!isset($filename)) {
			throw new NotFoundException(
				sprintf('The file that corresponds to the Uri\'s path "%s" is not found.', $requestPath));
		}

		$includeFile = $translateDirectory . self::DS . $filename;

		// @see RFC 3875 Section 4.1. Request Meta-Variables
		$pathInfo = '';
		for ($i = $fileSegmentIndex + 1; $i < $segmentCount; $i++) {
			$pathInfo .= self::PS . $segments[$i];
		}
		if (strlen($pathInfo) >= 1) {
			$this->server('PATH_INFO'      , $pathInfo);
			$this->server('PATH_TRANSLATED', $documentRoot . $pathInfo);
		}

		if (strlen($scriptName) >= 1) {
			$this->server('SCRIPT_NAME'    , $scriptName);
			$this->server('PHP_SELF'       , $scriptName . $pathInfo);
			$this->server('SCRIPT_FILENAME', $documentRoot . $scriptName);
		}

		$this->translateDirectory = $documentRoot . $translateDirectory;

		$this->virtualUri = $includeFile;
		if (strlen($pathInfo) >= 1) {
			$this->virtualUri .= $pathInfo;
		}
		if (strlen($queryString) >= 1) {
			$this->virtualUri .= '?' . $queryString;
		}

		$this->includeFile = $documentRoot . $includeFile;

		$this->prepared = true;

		return $this;
	}

	/**
	 * ルーティングを実行します。
	 * カレントディレクトリを移動し、対象のスクリプトを返します。
	 * overwriteGlobalsオプションが有効な場合、$_SERVERグローバル変数を
	 * serverの値で上書きします。
	 *
	 * @param string requestURI
	 * @return string Router
	 */
	public function execute($requestUri = null)
	{
		if (!$this->prepared) {
			$this->prepare($requestUri);
		}
		if ($this->config('overwriteGlobals')) {
			$this->overwriteGlobals();
		}
		if (isset($this->translateDirectory)) {
			chdir($this->translateDirectory);
		}
		include $this->includeFile;
	}

	/**
	 * リクエストパスに含まれる.および..を展開し、ルートからのセグメントの配列を返します。
	 *
	 * @param string リクエストパス
	 * @return array セグメントの配列
	 */
	private function parseRequestPath($requestPath)
	{
		$segments = array();
		$count = 0;
		foreach (explode(self::PS, $requestPath) as $segment) {
			if (strcmp($segment, '.') === 0) {
				continue;
			}
			if (strcmp($segment, '..') === 0) {
				if ($count >= 2) {
					array_pop($segments);
					$count--;
				}
				continue;
			}
			$segments[] = $segment;
			$count++;
		}
		array_shift($segments);
		return $segments;
	}

	/**
	 * ディレクトリに、指定された名前および拡張子のファイルがあれば、そのファイル名を返します。
	 *
	 * @param string ディレクトリ
	 * @param string ファイル名
	 * @param array 検索する拡張子のリスト
	 * @return mixed ファイル名またはNULL
	 */
	private function findFile($dir, $filename, $extensions = array())
	{
		if (!empty($extensions)) {
			foreach ($extensions as $extension) {
				$path = $dir . self::DS . $filename . '.'. $extension;
				if (file_exists($path) && is_file($path)) {
					return $filename . '.' . $extension;
				}
			}
		}
		$path = $dir . self::DS . $filename;
		return (file_exists($path) && is_file($path)) ? $filename : null;
	}

	/**
	 * 環境変数を$_SERVERグローバル変数に上書きします。
	 *
	 * @return object Router
	 */
	private function overwriteGlobals()
	{
		if (isset($_SERVER)) {
			foreach ($this->server as $name => $value) {
				$_SERVER[$name] = $value;
			}
		}
		return $this;
	}

}
