<?php
namespace FluidTYPO3\Fluidtypo3org\Indexing;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class IrcInitializer extends \Tx_Solr_IndexQueue_Initializer_Abstract {

	/**
	 * Initializes Index Queue items for a certain site and indexing
	 * configuration.
	 *
	 * @return boolean TRUE if initialization was successful, FALSE on error.
	 * @see Tx_Solr_IndexQueueInitializer::initialize()
	 */
	public function initialize() {
		$directory = $this->indexingConfiguration['indexer.']['directory'];
		$path = GeneralUtility::getFileAbsFileName($directory);
		$files = glob($path . '*.log');
		if (FALSE === $files) {
			return TRUE;
		}
		$uids = array();
		foreach ($files as $file) {
			$filename = pathinfo($file, PATHINFO_FILENAME);
			list (, $uid) = explode('_', $filename);
			$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'tx_solr_indexqueue_item', "item_uid = '" . $uid . "'");
			$placeholder = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'irc', "filename = '" . $file . "'");
			if (TRUE === empty($record)) {
				$record = array();
			}
			if (TRUE === empty($placeholder)) {
				$placeholder = array();
			}
			$placeholder['filename'] = $file;
			$placeholder['tstamp'] = filemtime($file);
			$placeholderUid = $this->insertOrUpdate('irc', $placeholder);
			$record['item_uid'] = $placeholderUid;
			$record['item_type'] = $this->type;
			$record['indexing_configuration'] = $this->type;
			$record['indexing_priority'] = 0;
			$record['root'] = $this->site->getRootPageId();
			$record['changed'] = filemtime($file);
			$this->insertOrUpdate('tx_solr_indexqueue_item', $record);
			$uids[] = $placeholderUid;
		}
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_solr_indexqueue_item', "item_type = '" . $this->type . "' AND item_uid NOT IN (" . implode(',', $uids) . ')');
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('irc', "uid NOT IN (" . implode(',', $uids) . ')');
		return TRUE;
	}

	/**
	 * @param string $table
	 * @param array $record
	 * @return integer
	 */
	protected function insertOrUpdate($table, $record) {
		if (0 < $record['uid']) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, "uid = '" . $record['uid'] . "'", $record);
			return $record['uid'];
		} else {
			$GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $record);
			return $GLOBALS['TYPO3_DB']->sql_insert_id();
		}

	}

}
