<?php

final class PhixatorWorkLogListController extends PhabricatorController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function getQueryKey() {
    return $this->queryKey;
  }

  public function processRequest() {
    $controller = (new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhixatorWorkLogSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildSideNavView() {
    $user = $this->getRequest()->getUser();
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    (new PhixatorWorkLogSearchEngine())->setViewer($user)->addNavigationItems($nav->getMenu());
    $nav->selectFilter(null);
    return $nav;
  }

}
