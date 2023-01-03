<?php

final class PhixatorWorkLogSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Phixator Work Time');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhixatorApplication';
  }

  protected function getURI($path) {
    return '/phixator/'.$path;
  }

  public function newQuery() {
    return new PhixatorWorkLogQuery();
  }

  protected function getBuiltinQueryNames() {
    return array(
      'me' => pht('My Work Logs'),
      'all' => pht('All Work Logs'),
    );
  }
  
  protected function buildCustomSearchFields() {
    return array(
      (new PhabricatorUsersSearchField())->setLabel(pht('Authors'))->setKey('authorPHIDs')->setAliases(['author', 'authors', 'user', 'users']),
      (new PhabricatorProjectSearchField())->setLabel(pht('Projects'))->setKey('projectPHIDs')->setAliases(['project', 'projects']),
      (new PhabricatorSpacesSearchField())->setLabel(pht('Spaces'))->setKey('spacePHIDs')->setAliases(['space', 'spaces']),
      (new PhabricatorSearchTextField())->setLabel(pht('Tasks'))->setKey('taskIDs')->setAliases(['task', 'tasks']),
      (new PhabricatorSearchDateField())->setLabel(pht('After (included)'))->setKey('dateStart')->setAliases(['after']),
      (new PhabricatorSearchDateField())->setLabel(pht('Before (non included)'))->setKey('dateEnd')->setAliases(['before']),
      (new PhabricatorSearchCheckboxesField())->setLabel(pht('Display'))->setKey('hide')->setOptions(array(
        'space' => pht('Hide Space'),
        'monogram' => pht('Hide Task Monogram'),
        'description' => pht('Hide Description'),
        'author' => pht('Hide Author'),
        'projects' => pht('Hide Projects')
      )),
      (new PhabricatorSearchIntField())->setLabel(pht('Results per page'))->setKey('limit'),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    $query->setViewer($this->requireViewer());
    if($map['authorPHIDs']) $query->withAuthorPHIDs($map['authorPHIDs']);
    if($map['projectPHIDs']) $query->withProjectPHIDs($map['projectPHIDs']);
    if($map['spacePHIDs']) $query->withSpacePHIDs($map['spacePHIDs']);
    if($map['taskIDs']) $query->withTaskMonograms($map['taskIDs']);
    if($map['dateStart']) $query->withDateStart($map['dateStart']);
    if($map['dateEnd']) $query->withDateEnd($map['dateEnd']);
    return $query;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);
    $query->setParameter('limit', 30);
    switch ($query_key) {
      case 'me': return $query->setParameter('authorPHIDs', [$this->requireViewer()->getPHID()])->setParameter('hide', ['author']);
      case 'all': return $query;
    }
    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(array $transactions, PhabricatorSavedQuery $query, array $handles) {
    $viewer = $this->requireViewer();
    $request = $this->getRequest();
    $query_key = $request ? $request->getController()->getQueryKey() : null;
    $uri = $query_key ? $this->getQueryResultsPageURI($query_key) : $this->getQueryBaseURI();

    // Prepare the search view
    $view = new PHUIObjectItemListView();
    $view->setViewer($viewer);
    $result = new PhabricatorApplicationSearchResultView();
    $result->setNoDataString(pht('No Work Logs found for this query. Create a new Work Log by browsing a Task and selecting "%s" on its edition menu.', pht('Log Work Time')));
    $result->setObjectList($view);
    
    // Group transactions by date
    $transactions_by_date = [];
    foreach($transactions as $transaction) {
      $transactions_by_date[phabricator_date($transaction->getOldValue(), $viewer)][] = $transaction;
    }

    // List each date
    foreach($transactions_by_date as $date => $transactions_of_date) {
      $item = new PHUIObjectItemView();
      $item->setStyle('background: whitesmoke');
      $item->setObjectName($date);
      $view->addItem($item);

      // List every time log on this date
      foreach($transactions_of_date as $transaction) {
        // Query data from the transaction
        $newValue = $transaction->getNewValue();
        $can_edit = PhabricatorPolicyFilter::hasCapability($viewer, $transaction, PhabricatorPolicyCapability::CAN_EDIT);

        // Query task
        $task = head((new ManiphestTaskQuery())->withPHIDs([$transaction->getObjectPHID()])->needProjectPHIDs(true)->setViewer($viewer)->execute());

        // Process the time log item view display
        $item = new PHUIObjectItemView();
        $item->setStatusIcon('fa-hourglass-half');
        $space_view = (!in_array('space', (array)$query->getParameter('hide')) && $task) ? (new PHUISpacesNamespaceContextView())->setViewer($viewer)->setObject($task) : '';
        $monogram = (!in_array('monogram', (array)$query->getParameter('hide')) && $task) ? $task->getMonogram() : '';
        $item->setObjectName(hsprintf('<span style="font-size:120%%">%s</span> %s %s %s', PhixatorUtil::minutesToTimeString($newValue['minutes'] ?? 0), pht('at'), $space_view, $monogram));
        $item->setHeader($task ? $task->getTitle() : pht('Restricted Task'));
        if($task) $item->setHref($task->getURI());
        if(!in_array('description', (array)$query->getParameter('hide')) && $newValue['description']) {
          $item->setSubHead(new PHUIRemarkupView($viewer, $newValue['description']));
        }
        if(!in_array('author', (array)$query->getParameter('hide'))) {
          $authorPHID = $transaction->getAuthorPHID();
          $author = head((new PhabricatorPeopleQuery())->setViewer($viewer)->withPHIDs([$authorPHID])->execute());
          if($author) {
            $item->addAttribute((new PhabricatorObjectHandle())
              ->setType(phid_get_type($authorPHID))
              ->setPHID($authorPHID)
              ->setName($author->getFullName())
              ->setURI('/p/'.$author->getUsername().'/')
              ->setTagColor(PHUITagView::COLOR_GREY)
              ->renderTag()
            );
          }
        }
        if(!in_array('projects', (array)$query->getParameter('hide'))) {
          $task_handles = $task ? ManiphestTaskListView::loadTaskHandles($viewer, [$task]) : [];
          $project_handles = $task ? array_select_keys($task_handles, array_reverse($task->getProjectPHIDs())) : [];
          if($project_handles) $item->addAttribute((new PHUIHandleTagListView())->setLimit(6)->setHandles($project_handles));
        }
        $item->addAction((new PHUIListItemView())->setHref('/phixator/log/edit/'.$transaction->getPHID().'/?return_to='.urlencode($uri))->setIcon('fa-pencil')->setDisabled(!$can_edit)->setWorkflow(true));
        $item->addAction((new PHUIListItemView())->setHref('/phixator/log/delete/'.$transaction->getPHID().'/?return_to='.urlencode($uri))->setIcon('fa-times')->setDisabled(!$can_edit)->setWorkflow(true));
        $view->addItem($item);
      }
    }

    return $result;
  }

  protected function newExportFields() {
    return array(
      (new PhabricatorStringExportField())->setKey('space')->setLabel(pht('Space')),
      (new PhabricatorStringExportField())->setKey('date')->setLabel(pht('Date')),
      (new PhabricatorStringExportField())->setKey('projects')->setLabel(pht('Projects')),
      (new PhabricatorStringExportField())->setKey('taskMonogram')->setLabel(pht('Task')),
      (new PhabricatorStringExportField())->setKey('taskTitle')->setLabel(pht('Task Title')),
      (new PhabricatorURIExportField())->setKey('taskURI')->setLabel(pht('Task URI')),
      (new PhabricatorStringExportField())->setKey('author')->setLabel(pht('Author')),
      (new PhabricatorStringExportField())->setKey('description')->setLabel(pht('Description')),
      (new PhabricatorStringExportField())->setKey('spentTime')->setLabel(pht('Spent Time')),
      (new PhabricatorIntExportField())->setKey('spentMinutes')->setLabel(pht('Time (minutes)')),
    );
  }

  protected function newExportData(array $transactions) {
    $viewer = $this->requireViewer();
    $export = array();
    foreach ($transactions as $transaction) {
      $task = head((new ManiphestTaskQuery())->withPHIDs([$transaction->getObjectPHID()])->needProjectPHIDs(true)->setViewer($viewer)->execute());
      if(!$task) continue; // if viewer has no more access to the task
      $space = head((new PhabricatorSpacesNamespaceQuery())->withPHIDs([PhabricatorSpacesNamespaceQuery::getObjectSpacePHID($task)])->setViewer($viewer)->execute());
      $task_handles = ManiphestTaskListView::loadTaskHandles($viewer, [$task]);
      $project_handles = array_select_keys($task_handles, array_reverse($task->getProjectPHIDs()));
      $get_name = function($obj) { return $obj->getFullName(); };
      $author = head((new PhabricatorPeopleQuery())->setViewer($viewer)->withPHIDs([$transaction->getAuthorPHID()])->execute());
      $newValue = $transaction->getNewValue();
      $export[] = array(
        'space' => $space ? $space->getNamespaceName() : '',
        'date' => date("Y-m-d", $newValue['started']),
        'projects' => implode(" / ", array_map($get_name, $project_handles)),
        'taskMonogram' => $task->getMonogram(),
        'taskTitle' => $task->getTitle(),
        'taskURI' => PhabricatorEnv::getProductionURI($task->getURI()),
        'author' => $author ? $author->getFullName() : '',
        'description' => $newValue['description'],
        'spentTime' => PhixatorUtil::minutesToTimeString($newValue['minutes']),
        'spentMinutes' => $newValue['minutes']
      );
    }
    return $export;
  }
}