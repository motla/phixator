<?php

final class PhixatorWorkLogQuery extends PhabricatorCursorPagedPolicyAwareQuery {
  private $IDs = array();
  private $PHIDs = array();
  private $authorPHIDs = array();
  private $projectPHIDs = array();
  private $spacePHIDs = array();
  private $taskIDs = array();
  private $dateStart = '';
  private $dateEnd = '';

  public function newResultObject(){
    return new ManiphestTransaction();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhixatorApplication';
  }

  public function withIDs(array $ids) { $this->IDs = $ids; return $this; }
  public function withPHIDs(array $phids) { $this->PHIDs = $phids; return $this; }
  public function withAuthorPHIDs(array $author_phids) { $this->authorPHIDs = $author_phids; return $this; }
  public function withProjectPHIDs(array $project_phids) { $this->projectPHIDs = $project_phids; return $this; }
  public function withSpacePHIDs(array $space_phids) { $this->spacePHIDs = $space_phids; return $this; }
  public function withTaskMonograms(string $monograms_list) {
    preg_match_all('/T(?P<id>\d+)/', $monograms_list, $matches);
    $this->taskIDs = $matches['id'];
    return $this;
  }
  public function withDateStart(string $date_start) { $this->dateStart = $date_start; return $this; }
  public function withDateEnd(string $date_end) { $this->dateEnd = $date_end; return $this; }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array('oldValue' => array('column' => 'oldValue', 'type' => 'int'));
  }

  public function getBuiltinOrders() {
    return array(
      'newest' => array('vector' => array('oldValue', 'id'), 'name' => pht('Newest Date First')),
      'oldest' => array('vector' => array('-oldValue', 'id'), 'name' => pht('Oldest Date First'))
    );
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'oldValue' => (int)$object->getOldValue()
    );
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $viewer = $this->getViewer();
    $where = parent::buildWhereClauseParts($conn);
    $where[] = qsprintf($conn, 'transactionType = %s', ManiphestTaskWorkLogTransaction::TRANSACTIONTYPE);
    if($this->IDs) $where[] = qsprintf($conn, 'id IN (%Ld)', $this->IDs);
    if($this->PHIDs) $where[] = qsprintf($conn, 'phid IN (%Ls)', $this->PHIDs);
    if($this->authorPHIDs) $where[] = qsprintf($conn, 'authorPHID IN (%Ls)', $this->authorPHIDs);

    // Filter transactions by tasks that the viewer can view and that match selection, if any
    $query = new ManiphestTaskQuery();
    if($this->projectPHIDs) $query->withEdgeLogicConstraints(PhabricatorProjectObjectHasProjectEdgeType::EDGECONST, $this->projectPHIDs);
    if($this->spacePHIDs) $query->withSpacePHIDs($this->spacePHIDs);
    if($this->taskIDs) $query->withIDs($this->taskIDs);
    if($tasks = $query->setViewer($viewer)->execute()) {
      $getPHID = function(ManiphestTask $task) { return $task->getPHID(); };
      $where[] = qsprintf($conn, 'objectPHID IN (%Ls)', array_map($getPHID, $tasks));
    }
    else return ['FALSE'];

    // NOTE: oldValue contains the 'started' date
    if($this->dateStart) $where[] = qsprintf($conn, 'oldValue >= %d', $this->dateStart);
    if($this->dateEnd) $where[] = qsprintf($conn, 'oldValue <= %d', $this->dateEnd);
    return $where;
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }
}
