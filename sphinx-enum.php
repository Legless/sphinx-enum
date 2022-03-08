<?php
	$args = [ ];
	$flags = [ ];

	for( $i = 1 ; $i < count( $argv ) ; $i++ ) {
		$arg = $argv[ $i ];

		if( $pos = mb_strpos( $arg, '=' ) ) {
			$name = mb_substr( $arg, 1, $pos - 1 );
			$value = mb_substr( $arg, $pos + 1 );

			$args[ $name ] = $value;
		} else {
			$arg = mb_substr( $arg, 1 );

			$flags = array_merge( $flags, str_split( $arg ) );
			$flags = array_unique( $flags );
		}
	}

	echo "Sphinx enumerator \n";
	echo "Usage: php sphinx-enum.php -target=(host or file) [-p=9307] [-e] [-d] [-h] \n";
	echo "       php sphinx-enum.php -h for help \n";
	echo "\n";

	if( in_array( 'h', $flags ) ) {
		echo "\n";
		echo "HELP: \n";
		echo "     -h - this help \n";
		echo "     -target= - host, ip or file with host list \n";
		echo "     -p - port, default 9306 \n";
		echo "     -e - enum tables/indexes \n";
		echo "     -d - describe index structure \n";
		echo "\n";
		echo "\n";
		die();
	}

	if( !isset( $args[ 'target' ] ) ) {
		die( "[!] Error: -target is required \n" );
	}

	if( is_file( $args[ 'target' ] ) ) {
		$hosts = file( $args[ 'target' ] );
	} else {
		$hosts = [ $args[ 'target' ] ];
	}

	$hosts = array_map( function ( $host ) {
		return trim( $host );
	}, $hosts );

	$hosts = array_filter( $hosts );
	$hosts = array_unique( $hosts );
	$hosts = array_values( $hosts );
	$total = count( $hosts );

	/**
	 * @param mysqli $conn
	 * @param string $sql
	 * @return bool|mysqli_result
	 * @todo move to another file
	 */
	function sphinx_query( $conn, $sql ) {
		return mysqli_query( $conn, $sql );
	}

	/**
	 * @param mysqli $conn
	 * @param bool|mysqli_result $result
	 * @return array
	 * @todo move to another file
	 */
	function sphinx_rows( $conn, $result ) {
		// return empty array on query error
		if( !$result ) {
			return [ ];
		}

		// fetch & return
		$rows = [ ];

		while( $row = $result->fetch_assoc( ) ) {
			$rows[ ] = $row;
		}

		return $rows;
	}

	foreach( $hosts as $k => $host ) {
		$host = $host . ':' . ( $args[ 'p' ] ?? 9306 );

		echo "\n";
		echo "[*] $host - $k of $total \n";

		@ $conn = mysqli_connect( $host, '', '' );
		if( !$conn ) {
			echo "[!] connection error: " . mysqli_connect_error( ) . "\n";

			if( mysqli_connect_errno( ) === 2054 ) {
				// todo: there's for older sphinx versions, need fix
				echo "    probably a mysqli version mismatch, please try manual scan via mysql client \n";
			}

			continue ;
		}

		$version = mysqli_get_server_info( $conn );
		echo "[+] connected \n";
		echo "    version: " . $version . "\n";

		if( !in_array( 'e', $flags ) ) {
			continue ;
		}

		// get and clean index list
		$res = sphinx_query( $conn, "SHOW TABLES" );
		$rows = sphinx_rows( $conn, $res );
		$indexes = array_map( function ( $index ) {
			// we dont care about index type?
			return $index[ 'Index' ];
		}, $rows );

		echo "    found " . count( $indexes ) . " indexes: \n";

		foreach( $indexes as $index ) {
			echo "      - " . $index . "\n";

			// get index structure if needed
			if( !in_array( 'd', $flags ) ) {
				continue ;
			}

			$res = sphinx_query( $conn, "DESCRIBE " . $index );
			$index_columns = sphinx_rows( $conn, $res );

			foreach( $index_columns as $col_info ) {
				echo "          ";
				echo $col_info[ 'Field' ];
				echo "\t(";
				echo $col_info[ 'Type' ];
				echo ")\n";
			}
		}
	}

