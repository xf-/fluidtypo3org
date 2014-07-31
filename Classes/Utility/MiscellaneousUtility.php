<?php
namespace FluidTYPO3\Fluidtypo3org\Utility;

class MiscellaneousUtility {

	/**
	 * @param string $table
	 * @param array $record
	 * @return integer
	 */
	public static function insertOrUpdate($table, $record) {
		if (0 < $record['uid']) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, "uid = '" . $record['uid'] . "'", $record);
			return $record['uid'];
		} else {
			$GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $record);
			return $GLOBALS['TYPO3_DB']->sql_insert_id();
		}

	}

}
