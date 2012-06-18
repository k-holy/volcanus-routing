<?php
/**
 * Volcanus libraries for PHP
 *
 * @copyright 2012 k-holy <k.holy74@gmail.com>
 * @license The MIT License (MIT)
 */
namespace Volcanus\Routing;

use Volcanus\Routing\Exception\NotFoundException;

/**
 * Router
 *
 * @package Volcanus\Routing
 * @author k.holy74@gmail.com
 */
class Router
{

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
	 * @var array 設定
	 */
	private $configurations;

	/**
	 * @var array 環境変数
	 */
	private $serverVariables;

	/**
	 * @var array パラメータ
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
	 * インスタンスのプロパティを初期化します。
	 *
	 * @param array デフォルトの設定
	 * @return $this
	 */
	public function initialize(array $configurations = array())
	{
		$this->configurations = array(
			'parameterDirectoryName' => '%VAR%',
			'searchExtensions'       => 'php',
			'overwriteGrobals'       => true,
		);
		$this->serverVariables = array();
		$this->parameters = array();
		$this->translateDirectory = null;
		$this->includeFile = null;
		$this->virtualUri = null;
		$this->extension = null;
		$this->prepared = false;
		if (!empty($configurations)) {
			$this->configurations($configurations);
		}
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
		} elseif (!empty($configurations)) {
			self::$instance->initialize($configurations);
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 * デフォルトの設定が指定されていればセットします。
	 *
	 * @param array デフォルトの設定
	 */
	public function __construct(array $configurations = array())
	{
		$this->initialize($configurations);
	}

	/**
	 * __clone()
	 */
	private final function __clone()
	{
		throw new \RuntimeException('could not clone instance.');
	}

	/**
	 * 引数なしの場合は全ての設定を配列で返します。
	 * 引数ありの場合は全ての設定を引数の配列からセットして$thisを返します。
	 *
	 * @param array 設定の配列
	 * @return mixed 設定の配列 または $this
	 * @throws \InvalidArgumentException
	 */
	public function configurations()
	{
		switch (func_num_args()) {
		case 0:
			return $this->configurations;
		case 1:
			$configurations = func_get_arg(0);
			if (!is_array($configurations)) {
				throw new \InvalidArgumentException(
					'The configurations is not Array.');
			}
			foreach ($configurations as $name => $value) {
				$this->configurations[$name] = $value;
			}
			return $this;
		}
		throw new \InvalidArgumentException('Invalid arguments count.');
	}

	/**
	 * 引数1つの場合は指定された設定の値を返します。
	 * 引数2つの場合は指定された設置の値をセットして$thisを返します。
	 *
	 * @param string 設定名
	 * @return mixed 設定値 または $this
	 * @throws \InvalidArgumentException
	 */
	public function config($name)
	{
		switch (func_num_args()) {
		case 1:
			return $this->getConfiguration($name);
		case 2:
			$this->setConfiguration($name, func_get_arg(1));
			return $this;
		}
		throw new \InvalidArgumentException('Invalid arguments count.');
	}

	/**
	 * 引数1つの場合は指定された環境変数の値を返します。
	 * 引数2つの場合は指定された環境変数の値をセットして$thisを返します。
	 *
	 * @param string 環境変数のキー
	 * @return mixed 環境変数の値 または $this
	 * @throws \InvalidArgumentException
	 */
	public function server($name)
	{
		switch (func_num_args()) {
		case 1:
			return $this->getServerVariable($name);
		case 2:
			$this->setServerVariable($name, func_get_arg(1));
			return $this;
		}
		throw new \InvalidArgumentException('Invalid arguments count.');
	}

	/**
	 * $_SERVERグローバル変数から環境変数を取り込みます。
	 *
	 * @return object Router
	 */
	public function importGrobals()
	{
		if (isset($_SERVER)) {
			foreach ($_SERVER as $name => $value) {
				if (in_array($name, self::$acceptServerVars)) {
					$this->setServerVariable($name, $value);
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
	 */
	public function prepare($requestUri = null)
	{
		if (isset($requestUri)) {
			$this->setServerVariable('REQUEST_URI', $requestUri);
		}

		$requestUri = $this->getServerVariable('REQUEST_URI');
		if (!isset($requestUri)) {
			throw new \RuntimeException('RequestUri is not set.');
		}

		$documentRoot = $this->getServerVariable('DOCUMENT_ROOT');
		if (!isset($documentRoot)) {
			throw new \RuntimeException('DocumentRoot is not set.');
		}

		preg_match(self::$requestUriPattern, $requestUri, $matches);

		$requestPath = (isset($matches[1])) ? $matches[1] : '';
		$queryString = (isset($matches[2])) ? $matches[2] : '';
		$fragment    = (isset($matches[3])) ? $matches[3] : '';

		$parameterDirectoryName = $this->getConfiguration('parameterDirectoryName');
		$searchExtensions = $this->getConfiguration('searchExtensions');
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
			$pos = strrpos($segment, '.');
			if ($pos !== false) {
				$filename = $this->findFile($documentRoot . $translateDirectory, $segment);
				if (isset($filename)) {
					$scriptName .= '/' . $filename;
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
						$scriptName .= '/' . $filename;
						$fileSegmentIndex = $index;
						$this->extension = $extension;
						break;
					}
				}
			}
			if (is_dir($documentRoot . $translateDirectory . '/' . $segment)) {
				$scriptName .= '/' . $segment;
				$translateDirectory .= '/' . $segment;
				continue;
			}
			$filename = $this->findFile($documentRoot . $translateDirectory,
				$segment, $searchExtensions);
			if (isset($filename)) {
				$scriptName .= '/' . $filename;
				$fileSegmentIndex = $index;
				break;
			}
			if (is_dir($documentRoot . $translateDirectory . '/' .
				$parameterDirectoryName)
			) {
				$translateDirectory .= '/' . $parameterDirectoryName;
				$scriptName .= '/' . $segment;
				$this->parameters[] = $segment;
				continue;
			}
			throw new NotFoundException(
				sprintf('The file that corresponds to the segment of Uri\'s path "%s" is not found in requestPath "%s".', $segment, $requestPath));
		}
		$translateDirectory = rtrim($translateDirectory, '/');

		if (!isset($filename)) {
			$filename = $this->findFile($documentRoot . $translateDirectory,
				'index', array('php', 'html'));
			if (isset($filename)) {
				$fileSegmentIndex = $segmentCount - 1;
				if (isset($segments[$fileSegmentIndex]) && strcmp($segments[$fileSegmentIndex], '') !== 0) {
					$scriptName .= '/' . $filename;
				} else {
					$scriptName .= $filename;
				}
			}
		}

		if (!isset($filename)) {
			throw new NotFoundException(
				sprintf('The file that corresponds to the Uri\'s path "%s" is not found.', $requestPath));
		}

		$includeFile = $translateDirectory . '/' . $filename;

		// @see RFC 3875 Section 4.1. Request Meta-Variables
		$pathInfo = '';
		for ($i = $fileSegmentIndex + 1; $i < $segmentCount; $i++) {
			$pathInfo .= '/' . $segments[$i];
		}
		if (strlen($pathInfo) >= 1) {
			$this->serverVariables['PATH_INFO'      ] = $pathInfo;
			$this->serverVariables['PATH_TRANSLATED'] = $documentRoot . $pathInfo;
		}

		if (strlen($scriptName) >= 1) {
			$this->serverVariables['SCRIPT_NAME'    ] = $scriptName;
			$this->serverVariables['PHP_SELF'       ] = $scriptName . $pathInfo;
			$this->serverVariables['SCRIPT_FILENAME'] = $documentRoot . $scriptName;
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
	 * カレントディレクトリを移動し、対象のスクリプトを読み込みます。
	 * overwriteGrobalsオプションが有効な場合、$_SERVERグローバル変数を
	 * serverVariablesプロパティの値で上書きします。
	 *
	 * @param string requestURI
	 * @return object Router
	 */
	public function execute($requestUri = null)
	{
		if (!$this->prepared) {
			$this->prepare($requestUri);
		}
		if ($this->getConfiguration('overwriteGrobals')) {
			$this->overwriteGrobals();
		}
		if (isset($this->translateDirectory)) {
			chdir($this->translateDirectory);
		}
		include $this->includeFile;
	}

	/**
	 * 指定された設定の値をセットします。
	 *
	 * @param string 設定名
	 * @param mixed 設定値
	 * @return object Router
	 * @throws \InvalidArgumentException
	 */
	private function setConfiguration($name, $value)
	{
		if (!array_key_exists($name, $this->configurations)) {
			throw new \InvalidArgumentException(
				sprintf('The configuration "%s" does not exists.', $name));
		}
		$this->configurations[$name] = $value;
		return $this;
	}

	/**
	 * 指定された設定の値を返します。
	 *
	 * @param string 設定名
	 * @return mixed 設定値
	 * @throws \InvalidArgumentException
	 */
	private function getConfiguration($name)
	{
		if (!array_key_exists($name, $this->configurations)) {
			throw new \InvalidArgumentException(
				sprintf('The configuration "%s" does not exists.', $name));
		}
		return $this->configurations[$name];
	}

	/**
	 * 指定された環境変数の値をセットします。
	 * REQUEST_URIが不正な場合は値を受け付けず例外をスローします。
	 * DOCUMENT_ROOTは値に含まれるディレクトリ区切り文字を / に統一します。
	 *
	 * @param string 環境変数のキー
	 * @param mixed 環境変数の値
	 * @return object Router
	 * @throws \InvalidArgumentException
	 */
	private function setServerVariable($name, $value)
	{
		if (!in_array($name, self::$acceptServerVars)) {
			throw new \InvalidArgumentException(
				sprintf('The serverVariables "%s" could not accept.', $name));
		}
		if (isset($value)) {
			if (!is_string($value)) {
				throw new \InvalidArgumentException(
					sprintf('The serverVariables "%s" is not string.', $name));
			}
			switch ($name) {
			case 'REQUEST_URI':
				if (!preg_match(self::$requestUriPattern, $value, $matches)) {
					throw new \InvalidArgumentException(
						sprintf('The serverVariables "%s" is not valid. "%s"', $name, $value));
				}
				break;
			case 'DOCUMENT_ROOT':
				if (DIRECTORY_SEPARATOR !== '/') {
					$value = str_replace(DIRECTORY_SEPARATOR, '/', $value);
				}
				break;
			}
		}
		$this->serverVariables[$name] = $value;
		return $this;
	}

	/**
	 * 指定された環境変数の値を返します。
	 *
	 * @param string 環境変数のキー
	 * @return mixed 環境変数の値
	 */
	private function getServerVariable($name)
	{
		if (array_key_exists($name, $this->serverVariables)) {
			return $this->serverVariables[$name];
		}
		return null;
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
		foreach (explode('/', $requestPath) as $segment) {
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
			foreach ($extensions as $ext) {
				$path = $dir . '/' . $filename . '.'. $ext;
				if (file_exists($path) && is_file($path)) {
					return $filename . '.' . $ext;
				}
			}
		}
		$path = $dir . '/' . $filename;
		return (file_exists($path) && is_file($path)) ? $filename : null;
	}

	/**
	 * 環境変数を$_SERVERグローバル変数に上書きします。
	 *
	 * @return object Router
	 */
	private function overwriteGrobals()
	{
		if (isset($_SERVER)) {
			foreach ($this->serverVariables as $name => $value) {
				$_SERVER[$name] = $value;
			}
		}
		return $this;
	}

}
