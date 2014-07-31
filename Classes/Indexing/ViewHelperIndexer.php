<?php
namespace FluidTYPO3\Fluidtypo3org\Indexing;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ViewHelperIndexer extends \Tx_Solr_IndexQueue_Indexer {

	/**
	 * Indexes an item from the indexing queue.
	 *
	 * @param \Tx_Solr_IndexQueue_Item $item
	 * @return \Apache_Solr_Response
	 */
	public function index(\Tx_Solr_IndexQueue_Item $item) {
		$item->setChanged(time());
		$placeholder = $item->getRecord();
		$filename = $placeholder['filename'];
		$solrConnections = $this->getSolrConnectionsByItem($item);
		foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
			$this->indexSchemaFile($solrConnection, $item, $filename);
		}
		return TRUE;
	}

	/**
	 * @param \Tx_Solr_SolrService $solrService
	 * @param \Tx_Solr_IndexQueue_Item $item
	 * @param string $filename
	 * @return boolean
	 */
	protected function indexSchemaFile(\Tx_Solr_SolrService $solrService, \Tx_Solr_IndexQueue_Item $item, $filename) {
		$basename = pathinfo($filename, PATHINFO_FILENAME);
		list ($extensionKey, $extensionVersion) = explode('-', $basename);
		$item->setType('viewhelper_schema');
		$document = $this->createSchemaLinkDocument($item, $extensionKey, $extensionVersion, $filename);
		$solrService->addDocument($document);
		$dom = new \DOMDocument();
		$dom->load($filename);
		$nodes = $dom->getElementsByTagName('element');
		foreach ($nodes as $viewHelperNode) {
			$description = $viewHelperNode->getElementsByTagName('annotation')->item(0)->getElementsByTagName('documentation')->item(0)->nodeValue;
			$name = $viewHelperNode->getAttribute('name');
			$item->setType('viewhelper');
			$document = $this->createViewHelperDescriptionDocument($item, $extensionKey, $extensionVersion, $name, $description);
			$solrService->addDocument($document);
			$arguments = $viewHelperNode->getElementsByTagName('attribute');
			foreach ($arguments as $argumentNode) {
				$argumentName = $argumentNode->getAttribute('name');
				$argumentType = $argumentNode->getAttribute('type');
				$argumentDescription = 'Type: ' . $argumentType . '. ' .
					$argumentNode->getElementsByTagName('annotation')->item(0)->getElementsByTagName('documentation')->item(0)->nodeValue;
				$item->setType('viewhelper_argument');
				$document = $this->createViewHelperArgumentDocument($item, $extensionKey, $extensionVersion, $name, $argumentName, $argumentDescription);
				$solrService->addDocument($document);
			}
		}
	}

	/**
	 * @param \Tx_Solr_IndexQueue_Item $item
	 * @param string $extensionKey
	 * @param string $extensionVersion
	 * @param string $filename
	 * @return \Apache_Solr_Document
	 */
	protected function createSchemaLinkDocument(\Tx_Solr_IndexQueue_Item $item, $extensionKey, $extensionVersion, $filename) {
		$record = $item->getRecord();
		$id = \Tx_Solr_Util::getDocumentId(
			$item->getType(),
			$record['pid'],
			$record['uid'] . '/' . $extensionKey . '/' . $extensionVersion
		);
		$document = $this->getBaseDocument($item, $record);
		$document->setField('title', 'ViewHelper schema for ' . $extensionKey . ' version ' . $extensionVersion);
		$document->setField('content', 'Download of XSD schema file for extension "' . $extensionKey . '" version ' . $extensionVersion);
		$document->setField('url', $filename);
		$document->setField('id', $id);
		$document->setField('extension', $extensionKey);
		$document->setField('version', $extensionVersion);
		return $document;
	}

	/**
	 * @param \Tx_Solr_IndexQueue_Item $item
	 * @param string $extensionKey
	 * @param string $extensionVersion
	 * @param string $name
	 * @param string $description
	 * @return \Apache_Solr_Document
	 */
	protected function createViewHelperDescriptionDocument(\Tx_Solr_IndexQueue_Item $item, $extensionKey, $extensionVersion, $name, $description) {
		$record = $item->getRecord();
		$pathSegments = explode('.', $name);
		$pathSegments = array_map('ucfirst', $pathSegments);
		$id = \Tx_Solr_Util::getDocumentId(
			$item->getType(),
			$record['pid'],
			$record['uid'] . '/' . $extensionKey . '/' . $extensionVersion . '/' . $name
		);
		$document = $this->getBaseDocument($item, $record);
		$document->setField('content', $description);
		$document->setField('title', 'ViewHelper: "' . $name . '" (' . $extensionKey . ' ' . $extensionVersion . ')');
		$document->setField('url', 'viewhelpers/' . $extensionKey . '/' . $extensionVersion . '/' . implode('/', $pathSegments) . 'ViewHelper.html');
		$document->setField('id', $id);
		$document->setField('extension', $extensionKey);
		$document->setField('version', $extensionVersion);
		return $document;
	}

	/**
	 * @param \Tx_Solr_IndexQueue_Item $item
	 * @param string $extensionKey
	 * @param string $extensionVersion
	 * @param string $viewHelperName
	 * @param string $argumentName
	 * @param string $description
	 * @return \Apache_Solr_Document
	 */
	protected function createViewHelperArgumentDocument(\Tx_Solr_IndexQueue_Item $item, $extensionKey, $extensionVersion, $viewHelperName, $argumentName, $description) {
		$record = $item->getRecord();
		$pathSegments = explode('.', $viewHelperName);
		$pathSegments = array_map('ucfirst', $pathSegments);
		$id = \Tx_Solr_Util::getDocumentId(
			$item->getType(),
			$record['pid'],
			$record['uid'] . '/' . $extensionKey . '/' . $extensionVersion . '/' . $viewHelperName . '/' . $argumentName
		);
		$document = $this->getBaseDocument($item, $item->getRecord());
		$document->setField('content', $description);
		$document->setField('title', 'ViewHelper argument: "' . $argumentName . '" on "' . $viewHelperName . '" (' . $extensionKey . ' ' . $extensionVersion . ')');
		$document->setField('url', 'viewhelpers/' . $extensionKey . '/' . $extensionVersion . '/' . implode('/', $pathSegments) . 'ViewHelper.html#argument-' . $argumentName);
		$document->setField('id', $id);
		$document->setField('extension', $extensionKey);
		$document->setField('version', $extensionVersion);
		return $document;
	}

}
