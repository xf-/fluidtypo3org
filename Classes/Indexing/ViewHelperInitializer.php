<?php
namespace FluidTYPO3\Fluidtypo3org\Indexing;

use FluidTYPO3\Fluidtypo3org\Utility\MiscellaneousUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ViewHelperInitializer extends \Tx_Solr_IndexQueue_Initializer_Abstract {

	/**
	 * Initializes Index Queue items for a certain site and indexing
	 * configuration.
	 *
	 * @return boolean TRUE if initialization was successful, FALSE on error.
	 */
	public function initialize() {
		$directory = $this->indexingConfiguration['indexer.']['directory'];
		$path = GeneralUtility::getFileAbsFileName($directory);
		$files = glob($path . '*.xsd');
		if (FALSE === $files) {
			return TRUE;
		}
		$uids = array();
		foreach ($files as $file) {
			$filename = pathinfo($file, PATHINFO_FILENAME);
			list (, $uid) = explode('_', $filename);
			$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'tx_solr_indexqueue_item', "item_uid = '" . $uid . "'");
			$placeholder = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'viewhelper', "filename = '" . $file . "'");
			if (TRUE === empty($record)) {
				$record = array();
			}
			if (TRUE === empty($placeholder)) {
				$placeholder = array();
			}
			$placeholder['filename'] = $file;
			$placeholder['tstamp'] = filemtime($file);
			$placeholderUid = MiscellaneousUtility::insertOrUpdate('viewhelper', $placeholder);
			$record['item_uid'] = $placeholderUid;
			$record['item_type'] = $this->type;
			$record['indexing_configuration'] = $this->type;
			$record['indexing_priority'] = 0;
			$record['root'] = $this->site->getRootPageId();
			$record['changed'] = filemtime($file);
			MiscellaneousUtility::insertOrUpdate('tx_solr_indexqueue_item', $record);
			$uids[] = $placeholderUid;
		}
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_solr_indexqueue_item', "item_type = '" . $this->type . "' AND item_uid NOT IN (" . implode(',', $uids) . ')');
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('viewhelper', "uid NOT IN (" . implode(',', $uids) . ')');
		return TRUE;
	}

}
