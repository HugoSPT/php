<?php
		
	require_once("config.php");

	class MySQLiDatabase{
	
		private $connection;
		public $last_query;
		private $magic_quotes_active;
		private $real_escape_string_exists;
	
	  	function __construct() {
	    	$this->open_connection();
			$this->magic_quotes_active = get_magic_quotes_gpc();
			$this->real_escape_string_exists = function_exists("mysql_real_escape_string");
	  	}

		public function open_connection() {
			$this->connection = new mysqli(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
			
			if (mysqli_connect_errno())
    			die("Database connection failed: " . mysqli_connect_error());

    		$this->connection->set_charset("utf8");
		}

		public function close_connection() {
			if(isset($this->connection)) {
				$this->connection->close();
				unset($this->connection);
			}
		}

		public function query($sql, $params, $types) {
			$this->last_query = $sql;
					
			if($params)
				array_unshift($params, $types);
								
			$result = $this->mysqli_prepared_query($this->connection, $sql, $params);

			$this->confirm_query($result);
			return $result;
		}
		
		public function escape_value($value) {
			if($this->real_escape_string_exists) {
				if($this->magic_quotes_active)
					$value = stripslashes($value);
				$value = mysql_real_escape_string($value);
			} else
				if(!$this->magic_quotes_active)
					$value = addslashes($value);
			return $value;
		}
  
	  	public function insert_id() {
	    	return mysqli_insert_id($this->connection);
	  	}
  
	  	public function affected_rows() {
	    	return mysqli_affected_rows($this->connection);
	  	}

		private function confirm_query($result) {
			if (!isset($result)) {
		    	/*$output = "Database query failed: " . mysql_error() . "<br /><br />";
		    	$output .= "Last SQL query: " . $this->last_query;*/
		    	$output = "Database Error!";
		    	die( $output );
			}
		}
		
		private function mysqli_prepared_query($link, $sql, $bindParams = false){
	
			if($stmt = mysqli_prepare($link, $sql)){
				if ($bindParams){
					//allows for call to mysqli_stmt->bind_param using variable argument list
					$bindParamsMethod = new ReflectionMethod('mysqli_stmt', 'bind_param');
					//will act as arguments list for mysqli_stmt->bind_param
					$bindParamsReferences = array();
					
					$typeDefinitionString = array_shift($bindParams);
					foreach($bindParams as $key => $value){
						$bindParamsReferences[$key] = &$bindParams[$key];
					}
				
					//returns typeDefinition as the first element of the string
					array_unshift($bindParamsReferences,$typeDefinitionString);
				
					//calls mysqli_stmt->bind_param suing $bindParamsRereferences as the argument list
					$bindParamsMethod->invokeArgs($stmt,$bindParamsReferences);
				}
				if(mysqli_stmt_execute($stmt)){
					$resultMetaData = mysqli_stmt_result_metadata($stmt);
					if($resultMetaData){
			
						//this will be a result row returned from mysqli_stmt_fetch($stmt)
						$stmtRow = array();
						//this will reference $stmtRow and be passed to mysqli_bind_results
						$rowReferences = array();
					
						while ($field = mysqli_fetch_field($resultMetaData)){
							$rowReferences[] = &$stmtRow[$field->name];
						}
					
						mysqli_free_result($resultMetaData);
						$bindResultMethod = new ReflectionMethod('mysqli_stmt', 'bind_result');
					
						//calls mysqli_stmt_bind_result($stmt,[$rowReferences]) using object-oriented style
						$bindResultMethod->invokeArgs($stmt, $rowReferences);
						$result = array();
						while(mysqli_stmt_fetch($stmt)){
							//variables must be assigned by value, so $result[] = $stmtRow does not work 
							//(not really sure why, something with referencing in $stmtRow)
							foreach($stmtRow as $key => $value){
								$row[$key] = $value;
							}
							$result[] = $row;
						}
					
						mysqli_stmt_free_result($stmt);
					} else {
						$result = mysqli_stmt_affected_rows($stmt);
					}
					mysqli_stmt_close($stmt);
				} else {
					$result = false;
				}
			} else {
				$result = false;
			}
	  	return $result;
		}
	
	} 

	$database = new MySQLiDatabase();
	$db =& $database;

?>