<?php
/**
 * Displays the total work time summary in Maniphest tasks curtain
 */
class PhixatorCurtainExtension extends PHUICurtainExtension {
  const EXTENSIONKEY = 'phixator.log_history';

  public function shouldEnableForObject($object) {
    return ($object instanceof ManiphestTask);
  }

  public function getExtensionApplication() {
    return new PhabricatorPhixatorApplication();
  }

  public function buildCurtainPanel($object) {
    // Create the curtain panel and its status view
    $panel = $this->newPanel()->setHeaderText(pht('Work Time Summary'))->setOrder(40000);
    $status_view = new PHUIStatusListView();
    $panel->appendChild($status_view);

    // Get the transactions corresponding to the task currently displaying the curtain block
    $transactions = (new ManiphestTransactionQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs([$object->getPHID()])
      ->withTransactionTypes([ManiphestTaskWorkLogTransaction::TRANSACTIONTYPE])
      ->needComments(true)
      ->execute();
    if(!$transactions) return;
    $transactions = array_reverse($transactions);

    // Compute the contributing user list with their corresponding work time
    $summarySpendByUser = [];
    foreach ($transactions as $transaction) {
      $authorPHID = $transaction->getAuthorPHID();
      $minutes = intval($transaction->getNewValue()['minutes'] ?? 0);
      if (!isset($summarySpendByUser[$authorPHID])) $summarySpendByUser[$authorPHID] = 0;
      $summarySpendByUser[$authorPHID] += $minutes;
    }

    // Display all contributing users with their cumulated work times
    foreach ($summarySpendByUser as $authorPHID => $spendMinutes) {
      $author = head((new PhabricatorPeopleQuery())->setViewer($this->getViewer())->withPHIDs([$authorPHID])->execute());
      if(!$author) continue;
      $authorUrl = (new PhabricatorObjectHandle())
        ->setType(phid_get_type($authorPHID))
        ->setPHID($authorPHID)
        ->setName($author->getFullName())
        ->setURI('/p/'.$author->getUsername().'/')
        ->renderLink();
      $item = (new PHUIStatusItemView())
        ->setIcon('fa-hourglass-half')
        ->setTarget(pht('<b>%s</b> by %s', PhixatorUtil::minutesToTimeString($spendMinutes), $authorUrl));
      $status_view->addItem($item);
    }

    // Display link to get the work logs list for this task
    $status_view->addItem((new PHUIStatusItemView())->setTarget(
      (new PhabricatorObjectHandle())->setName(pht('Show details...'))->setURI('/phixator/query/advanced/?taskIDs='.$object->getMonogram())->renderLink())
    );

    return $panel;
  }
}
