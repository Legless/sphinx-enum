<?php
	define( 'SPHINX_ENUMERATOR', true );
	include_once "functions.php";

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
	echo "Usage: php sphinx-enum.php -target=(host or file) [-p=9306] [-edmi] [...] [-loot=dir] \n";
	echo "       php sphinx-enum.php -h for help \n";
	echo "\n";

	if( in_array( 'h', $flags ) ) {
		echo "\n";
		echo "HELP: \n";
		echo "          -h - this help \n";
		echo "    -target= - host, ip or file with host list \n";
		echo "          -p - port, default 9306 \n";
		echo "          -m - get server meta information \n";
		echo "          -e - enum tables/indexes \n";
		echo "          -d - describe index structure, requires -e \n";
		echo "          -i - get index meta information, requires -e \n";
		echo "      -loot= - directory to save index contents, dont save if not specified, \n";
		echo "               [!] files will be overwritten \n";
		echo "     -limit= - limit row count for looting for each index, default - 0 (loot all index) \n";
		echo "     -batch= - set batch size for looting, default=1000 \n";
		echo "   -timeout= - set connect/query wait timeout in seconds, default=5 \n";
		echo "\n";
		echo "\n";
		die( );
	}

	if( !isset( $args[ 'target' ] ) ) {
		die( "[!] Error: -target is required \n" );
	}

	if( $loot_dir = $args[ 'loot' ] ?? false ) {
		// lets clean and check looting directory
		if( is_file( $loot_dir ) ) {
			die( "[!] Error: -loot is not a directory \n" );
		}

		if( !is_dir( $loot_dir ) ) {
			mkdir( $loot_dir, 0777, true ) or die( "[!] Error: can't create $loot_dir \n" );
		}

		// add trailing slash
		if( substr( $loot_dir, -1 ) !== '/' ) {
			$loot_dir .= '/';
		}
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

	$all_indexes = [ ];
	$working_hosts = [ ];

	foreach( $hosts as $k => $host ) {
		if( strpos( $host, ':' ) === false ) {
			// add port if there's none specified in host addr
			$host = $host . ':' . ( $args[ 'p' ] ?? 9306 );
		}

		echo "\n";
		echo "[*] $host - $k of $total \n";

		$conn = sphinx_connect( $host );

		if( !$conn ) {
			echo "[!] connection error: " . mysqli_connect_error( ) . "\n";

			if( mysqli_connect_errno( ) === 2054 ) {
				// todo: there's for older sphinx versions, need fix
				echo "    probably a mysqli version mismatch, please try manual scan via mysql client \n";
			}

			continue ;
		}

		$working_hosts[ ] = $host;
		$version = mysqli_get_server_info( $conn );

		echo "[+] connected \n";
		echo "    version: $version \n";

		if( in_array( 'm', $flags ) ) {
			$res = sphinx_query( $conn, "SHOW STATUS" );
			$rows = sphinx_rows( $conn, $res );

			echo "    server status: \n";
			foreach( $rows as $stat ) {
				echo "        ";
				echo $stat[ 'Counter' ];
				echo " = ";
				echo $stat[ 'Value' ];
				echo "\n";
			}

			$res = sphinx_query( $conn, "SHOW AGENT STATUS" );
			$rows = sphinx_rows( $conn, $res );

			echo "    agent status: \n";
			foreach( $rows as $stat ) {
				echo "        ";
				echo $stat[ 'Key' ];
				echo " = ";
				echo $stat[ 'Value' ];
				echo "\n";
			}

			$res = sphinx_query( $conn, "SHOW THREADS" );
			$rows = sphinx_rows( $conn, $res );

			echo "    threads: \n";
			foreach( $rows as $stat ) {
				echo "        ";
				echo $stat[ 'Tid' ];
				echo " | ";
				echo $stat[ 'Proto' ];
				echo " | ";
				echo $stat[ 'State' ];
				echo " | ";
				echo $stat[ 'Time' ];
				echo " | ";
				echo $stat[ 'Info' ];
				echo "\n";
			}

			$res = sphinx_query( $conn, "SHOW VARIABLES" );
			$rows = sphinx_rows( $conn, $res );

			echo "    variables: \n";
			foreach( $rows as $stat ) {
				echo "        ";
				echo $stat[ 'Variable_name' ];
				echo " = ";
				echo $stat[ 'Value' ];
				echo "\n";
			}
		}

		if( !in_array( 'e', $flags ) ) {
			continue ;
		}

		// get and clean index list
		$res = sphinx_query( $conn, "SHOW TABLES" );
		$indexes = sphinx_rows( $conn, $res );

		echo "[+] found " . count( $indexes ) . " indexes: \n";
		$all_indexes[ $host ] = $indexes;

		foreach( $indexes as $index_info ) {
			$index = $index_info[ 'Index' ];
			echo "[+]     index " . $index_info[ 'Index' ] . " (". $index_info[ 'Type' ] .")\n";

			// get index meta if needed
			if( in_array( 'i', $flags ) ) {
				echo "[+]     " . $index . " status \n";

				$res = sphinx_query( $conn, "SHOW INDEX " . $index . " STATUS" );
				$status = sphinx_rows( $conn, $res );

				foreach( $status as $col_info ) {
					echo "          ";
					echo $col_info[ 'Variable_name' ];
					echo "\t(";
					echo $col_info[ 'Value' ];
					echo ")\n";
				}

				$res = sphinx_query( $conn, "SHOW INDEX " . $index . " SETTINGS" );
				$settings = sphinx_rows( $conn, $res );

				if( $settings_text = ( $settings[ 0 ][ 'Value' ] ?? null ) ) {
					echo "[+]     " . $index . " settings \n";

					// clean and display index settings block
					$settings_arr = explode( "\n", $settings_text );
					foreach( $settings_arr as $row ) {
						$row = trim( $row );
						$opt = explode( '=', $row );

						if( !isset( $opt[ 1 ] ) ) {
							// skip empty ?
							// @todo: check if sphinx index can has attr with no values?
							continue ;
						}

						echo "          ";
						echo trim( $opt[ 0 ] );
						echo "\t= ";
						echo trim( $opt[ 1 ] );
						echo "\n";
					}
				}
			}

			// get index structure if needed
			if( in_array( 'd', $flags ) ) {
				echo "[+]     " . $index . " structure \n";

				$res = sphinx_query( $conn, "DESCRIBE " . $index );
				$index_columns = sphinx_rows( $conn, $res );

				foreach( $index_columns as $col_info ) {
					echo "          ";

					if( isset( $col_info[ 'Agent' ] ) ) {
						echo "(agent) ". $col_info[ 'Agent' ];
					} else {
						echo $col_info[ 'Field' ];
					}

					echo "\t(";
					echo $col_info[ 'Type' ];
					echo ")\n";
				}
			}
		}

		mysqli_close( $conn );
	}

	if( $loot_dir ) {
		include_once "loot.php";
	}