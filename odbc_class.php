<?php 
require_once "/odbc_log_class.php";

class DB {

	private $server;
	private $Host;
	private $DBuser;
	private $DBpass;
	private $pdo;
	private $Query;
	private $Connected = false;
	private $log;
	private $QueryParameters;
	public $rowCount = 0;
	public $columnCount = 0;
	public $queryCount = 0;

	public function __construct() {
		$config = parse_ini_file("/credentials.ini");
		$this->Host = $config['host'];
		$this->DBuser = $config['username'];
		$this->DBpass = $config['password'];

		$this->log = new Log();
		$this->server = "odbc:Driver={Client Access ODBC Driver (32-bit)};System=".$this->Host.";Uid=user;Pwd=password";
		$this->connect();
	}

	public function connect() {
		try {
			$this->pdo = new PDO($this->server, $this->DBuser, $this->DBpass);
			$this->Connected = true;
		} catch (PDOException $e) {
			echo $this->ExceptionLog($e->getMessage());
			die();
		}
	}

	public function closeConnection() {
		$this->pdo = null;
	}

	public function init($query, $QueryParameters = '') {
		if (!$this->Connected) {
			$this->connect();
		}
		try {
			$this->QueryParameters = $QueryParameters;
			$this->Query = $this->pdo->prepare($query, $this->QueryParameters);

			if ($this->Query === false) {
				echo $this->ExceptionLog(implode(',', $this->pdo->errorInfo()));
				die();
			}

			if (!empty($this->QueryParameters)) {
				if (array_key_exists(0, $QueryParameters)) {
					$parametersType = true;
					array_unshift($this->QueryParameters, "");
					unset($this->QueryParameters[0]);
				} else {
					$parametersType = false;
				}

				foreach ($this->QueryParameters as $column => $value) {
					print_r_pre($this->QueryParameters);
					$this->Query->bindParam($parametersType ? intval($column) : ":" . $column, $this->QueryParameters[$column]);
				}
			}

			$this->succes = $this->Query->execute();
			$this->queryCount++;
		} catch (PDOException $e) {
			echo $this->ExceptionLog($e->getMessage(), $query);
			die();
		}

		$this->QueryParameters = array();
	}

	public function query($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
		$query = trim($query);
		$rawStatement = explode(" ", $query);
		$this->init($query, $params);
		$statement = strtolower($rawStatement[0]);
		if ($statement === 'select' || $statement === 'show') {
			return $this->Query->fetchAll($fetchmode);
		} elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
			return $this->Query->rowCount();
		} else {
			return NULL;
		}
	}

	public function column($query, $params = null) {
		$this->init($query, $params = null);
		$resultColumn = $this->Query->fetchAll(PDO::FETCH_COLUMN);
		$this->rowCount = $this->Query->rowCount();
		$this->columnCount = $this->Query->columnCount();
		$this->Query->closeCursor();
		return $resultColumn;
	}

	public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
		$this->init($query, $params);
		$resultRow = $this->Query->fetch($fetchmode);
		$this->rowCount = $this->Query->rowCount();
		$this->columnCount = $this->Query->columnCount();
		$this->Query->closeCursor();
		return $resultRow;
	}
	
	public function single($query, $params = null) {
		$this->init($query, $params);
		return $this->Query->fetchColumn();
	}
	
	private function ExceptionLog($message) {
		$exception = 'There is some unhandled Exceptions. <br />';
		$exception .= $message;
		$exception .= "<br /> Find all the errors in the log file.";
		
		$this->log->write($message, $this->Host . md5($this->DBpass));
		header("HTTP/1.1 500 Internal Server Error");
		header("Status: 500 Internal Server Error");
		return $exception;
	}

}
