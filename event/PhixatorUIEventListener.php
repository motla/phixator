<?php

class PhixatorUIEventListener extends PhabricatorEventListener {
  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
        break;
    }
  }

  private function handleActionEvent(PhabricatorEvent $event) {
    $viewer = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) return;
    if (!($object instanceof ManiphestTask)) return;
    if (!$this->canUseApplication($event->getUser())) return;

    $actionView = (new PhabricatorActionView())
    ->setName(pht('Log Work Time'))
    ->setIcon('fa-hourglass-half')
    ->setWorkflow(true)
    ->setHref('/phixator/log/' . $object->getPHID() . '/');
    
    $can_edit = PhabricatorPolicyFilter::hasCapability($viewer, $object, PhabricatorPolicyCapability::CAN_EDIT);
    if (!$viewer->isLoggedIn() || !$can_edit) {
      $actionView->setDisabled(true);
    }

    $this->addActionMenuItems($event, $actionView);
  }
}
