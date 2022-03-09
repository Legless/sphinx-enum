<?php
	if( !defined('SPHINX_ENUMERATOR' ) ) {
		die( "Please use sphinx-enum.php" );
	}

	/**
	 * @todo: make a nice class of it
	 */

	function sphinx_connect( $host ) {
		@ $conn = mysqli_connect( $host, '', '' );
		return $conn;
	}

	/**
	 * @param mysqli $conn
	 * @param string $sql
	 * @return bool|mysqli_result
	 */
	function sphinx_query( $conn, $sql ) {
		return mysqli_query( $conn, $sql );
	}

	/**
	 * @param mysqli $conn
	 * @param bool|mysqli_result $result
	 * @return array
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

	/**
	 * @param string $target
	 * @param array[][] $rows
	 * @return bool
	 */
	function save_csv( $target, $rows ) {
		if( !count( $rows ) ) {
			return true;
		}

		@$fp = fopen( $target, 'w+' );

		if( !$fp ) {
			return false;
		}

		$headers = array_keys( $rows[ 0 ] );
		fputcsv( $fp, $headers );

		foreach( $rows as $row ) {
			fputcsv( $fp, $row );
		}

		fclose( $fp );
		return true;
	}