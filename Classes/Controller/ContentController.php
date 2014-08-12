<?php
namespace FluidTYPO3\Fluidtypo3org\Controller;

use FluidTYPO3\Flux\Controller\AbstractFluxController;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class ContentController extends AbstractFluxController {

	/**
	 * @return void
	 */
	public function contributorsAction() {
		$cachedContributorsFile = GeneralUtility::getFileAbsFileName('typo3temp/contributors.tmp');
		$row = $this->getRecord();
		$fieldName = $this->provider->getFieldName($row);
		$blacklist = GeneralUtility::trimExplode(',', $this->settings['blacklist']);
		$this->provider->setFieldName('pi_flexform');
		if (FALSE === file_exists($cachedContributorsFile) || time() - 86400 > filemtime($cachedContributorsFile)) {
			$maintainersSource = GeneralUtility::trimExplode(',', $this->settings['maintainers']);
			$contributors = array(
				'maintainers' => array(),
				'contributors' => array(),
			);
			foreach ($maintainersSource as $maintainerDefinition) {
				list ($usernameName, $email) = explode(' <', trim($maintainerDefinition, '>'));
				list ($username, $name) = explode(':', $usernameName);
				$contributors['maintainers'][$email] = $this->createUserArray($name, $email, $username);
			}
			$repositories = GeneralUtility::trimExplode(',', $this->settings['contributorRepositories']);
			$authors = array();
			foreach ($repositories as $repository) {
				$folder = GeneralUtility::getFileAbsFileName('typo3conf/ext/' . $repository);
				$command = CommandUtility::getCommand('git');
				$lines = array();
				CommandUtility::exec('cd ' . $folder . ' && ' . $command . ' log', $lines);
				foreach ($lines as $line) {
					$line = trim($line);
					if (0 === strpos($line, 'Author: ')) {
						list ($author, $email) = explode(' <', trim(substr($line, 8), '>'));
						if (TRUE === in_array($email, $blacklist)) {
							continue;
						}
						if (FALSE === isset($contributors['maintainers'][$email])) {
							$authors[$email] = $this->createUserArray($author, $email);
						}
					}
				}
			}
			ksort($authors);
			$contributors['contributors'] = $authors;
			GeneralUtility::writeFile($cachedContributorsFile, serialize($contributors));
		} else {
			$contributors = unserialize(file_get_contents($cachedContributorsFile));
		}
		$this->view->assign('people', $contributors);
		$this->view->assign('supportChatPageUid', $this->settings['supportChatPageUid']);
		$this->provider->setFieldName($fieldName);
	}

	/**
	 * @param string $email
	 * @param string $name
	 * @param string $username
	 * @return array
	 */
	protected function createUserArray($name, $email, $username = NULL) {
		$info = array(
			'username' => $username,
			'email' => strtolower(trim($email)),
			'gravatar' => md5($email)
		);
		if (NULL !== $name) {
			$info['name'] = $name;
		}
		return $info;
	}

	/**
	 * @param integer $date
	 * @return void
	 */
	public function ircLogsAction($date = NULL) {
		$prefix = $this->settings['logDirectory'] . $this->settings['filterPrefix'];
		$prefixLength = strlen($prefix);
		$files = glob($prefix . '*');
		$sorted = array();
		foreach ($files as $filePathAndFilename) {
			$baseName = substr($filePathAndFilename, $prefixLength);
			$dateStamp = substr($baseName, 0, -4);
			$year = substr($dateStamp, 0, 4);
			$month = substr($dateStamp, 4, 2);
			$sorted[$year][$month][$baseName] = $year . '/' . $month . '/' . substr($dateStamp, -2);
			$lastLog = $filePathAndFilename;
		}
		$sorted = array_reverse($sorted, TRUE);
		foreach ($sorted as &$set) {
			$set = array_reverse($set, TRUE);
		}
		if (NULL === $date) {
			$date = date('Ym');
		}
		$current = array();
		$url = GeneralUtility::getIndpEnv('REQUEST_URI');
		foreach (glob($prefix . $date . '*') as $file) {
			$baseName = substr($file, $prefixLength);
			$dateStamp = substr($baseName, 0, -4);
			$lines = file($file);
			foreach ($lines as $index => $line) {
				if (9 === strpos($line, '] *** ') || 9 === strpos($line, '] -') || FALSE !== strpos($line, '<FluidTYPO3>')) {
					unset($lines[$index]);
				} else {
					$mark = substr($line, 1, 8);
					$lines[$index] = '<a name="' . $mark . '" href="' . $url . '#' . $mark . '">' . $mark . '</a> ' . htmlentities(substr($line, 11));
				}
			}
			$lines = array_map('trim', $lines);
			$current[$dateStamp] = '<li>' . implode('</li><li>', $lines) . '</li>';
		}
		$this->view->assign('current', $current);
		$this->view->assign('last', implode(LF, $output));
		$this->view->assign('files', $sorted);
		$this->view->assign('selectedDateStamp', $date);

	}

	/**
	 * @return void
	 */
	public function githubmarkdownAction() {
		$file = $this->settings['basePath'] . $this->settings['markdownFile'];
		$filePathAndFilename = GeneralUtility::getFileAbsFileName($file);
		$convertedPathAndFilename = substr($filePathAndFilename, 0, -3) . '.html';
		if (FALSE === file_exists($convertedPathAndFilename) || filemtime($convertedPathAndFilename) < filemtime($filePathAndFilename)) {
			$command = CommandUtility::getCommand('grip');
			$output = NULL;
			$code = 0;
			CommandUtility::exec($command . ' --export ' . $filePathAndFilename, $output, $code);
		}
		$document = new \DOMDocument();
		$document->loadHTMLFile($convertedPathAndFilename);
		$this->decorateCodeBlocks($document);
		$this->decorateTableTags($document);
		$this->changeBlockquoteToInlineAlerts($document);
		$this->retargetRelativeMarkdownFileLinks($document);
		$this->retargetImages($document);
		$html = $document->saveXML($document->getElementsByTagName('article')->item(0));
		$this->view->assign('html', $html);
		$this->view->assign('edit', $this->settings['editBasePath'] . $this->settings['markdownFile'] . '?message=[DOC] Edited ' . basename($filePathAndFilename));
		$this->view->assign('parentPageUid', $GLOBALS['TSFE']->page['pid']);
	}

	/**
	 * @param \DOMDocument $document
	 * @return void
	 */
	protected function retargetImages(\DOMDocument $document) {
		$basePath = $this->settings['basePath'];
		$originalImagePath = $this->settings['originalImagePathMatch'];
		$newImagePath = $basePath . $originalImagePath;
		$originalImagePathLength = strlen($originalImagePath);
		foreach ($document->getElementsByTagName('img') as $imageNode) {
			$url = $imageNode->getAttribute('src');
			if (0 === strpos($url, '..') && FALSE !== strpos($url, $originalImagePath)) {
				$croppedPath = substr($url, strpos($url, $originalImagePath) + $originalImagePathLength);
				$newUrl = $this->settings['imagePathPrepend'] . $newImagePath . $croppedPath;
				$imageNode->setAttribute('src', $newUrl);
				if ('a' === $imageNode->parentNode->tagName) {
					$imageNode->parentNode->setAttribute('href', $newUrl);
				}
			}
		}
	}

	/**
	 * @param \DOMDocument $document
	 * @return void
	 */
	protected function decorateCodeBlocks(\DOMDocument $document) {
		foreach ($document->getElementsByTagName('pre') as $codeNode) {
			if ('div' === $codeNode->parentNode->tagName) {
				$classNames = explode(' ', $codeNode->parentNode->getAttribute('class'));
				if (TRUE === in_array('highlight', $classNames)) {
					$codeNode->setAttribute('class', 'prettyprint language-' . array_pop(explode('-', array_pop($classNames))));
				}
			}
		}
	}

	/**
	 * @param \DOMDocument $document
	 * @return void
	 */
	protected function changeBlockquoteToInlineAlerts(\DOMDocument $document) {
		while ($quoteNode = $document->getElementsByTagName('blockquote')->item(0)) {
			$divNode = $document->createElement('div', $quoteNode->textContent);
			foreach ($quoteNode->childNodes as $childNode) {
				$newChild = $document->importNode($childNode, TRUE);
				$divNode->appendChild($childNode);
			}
			$divNode->setAttribute('class', 'alert alert-info');
			$quoteNode->parentNode->replaceChild($divNode, $quoteNode);
		}
	}

	/**
	 * @param \DOMDocument $document
	 * @return void
	 */
	protected function decorateTableTags(\DOMDocument $document) {
		foreach ($document->getElementsByTagName('table') as $tableNode) {
			$wrapNode = $document->createElement('div');
			$wrapNode->setAttribute('class', 'table-responsive');
			$document->appendChild($wrapNode);
			$tableNode->setAttribute('class', 'table table-condensed');
			$tableNode->parentNode->replaceChild($wrapNode, $tableNode);
			$wrapNode->appendChild($tableNode);
		}
	}

	/**
	 * @param \DOMDocument $document
	 * @return void
	 */
	protected function retargetRelativeMarkdownFileLinks(\DOMDocument $document) {
		foreach ($document->getElementsByTagName('a') as $linkNode) {
			$url = $linkNode->getAttribute('href');
			$isRelativeMarkdownLink = ('md' === pathinfo($url, PATHINFO_EXTENSION) && FALSE === strpos($url, '://'));
			$isRelativeAnchorLink = 0 === strpos($url, '#');
			if (TRUE === $isRelativeAnchorLink) {
				$link = $this->uriBuilder->reset()->setTargetPageUid($GLOBALS['TSFE']->id)->build() . $url;
				$linkNode->setAttribute('href', $link);
				$linkNode->setAttribute('name', substr($linkNode->getAttribute('name'), 13));
			} elseif (TRUE === $isRelativeMarkdownLink) {
				$lookupClause = "pi_flexform LIKE '%" . trim($url, './') . "</value>%'";
				$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('pid', 'tt_content', $lookupClause);
				if (FALSE !== $record) {
					$link = $this->uriBuilder->reset()->setTargetPageUid($record['pid'])->build();
					$linkNode->setAttribute('href', $link);
				}
			}
		}
	}

}
