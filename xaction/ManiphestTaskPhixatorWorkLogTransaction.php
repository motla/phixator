<?php
/**
 * Descriptor of the Time Log Transaction
 */
class ManiphestTaskWorkLogTransaction extends ManiphestTaskTransactionType {
  const TRANSACTIONTYPE = 'phixator:worklog';

  public function getIcon() {
    return 'fa-hourglass-half';
  }

  public function getTitle() {
    $values = $this->getNewValue();
    return pht(
      '%s logged <b>%s</b> of work on this task on <b>%s</b><i>%s</i>',
      $this->renderAuthor(),
      PhixatorUtil::minutesToTimeString($values['minutes'] ?? 0),
      phabricator_date($values['started'], $this->getViewer()),
      $values['description'] ? ' : '.$values['description'] : '.'
    );
  }

  public function generateOldValue($object) {
    // NOTE: as the oldValue field is unused, we use it to store the 'started' date for query ordering purpose
    return $this->getNewValue()['started'];
  }
}
