<?php
/**
 * Base class to display and manage the forms to create or edit work time logs
 */
class PhixatorWorkLogEditController extends PhabricatorController {
  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $taskPHID = $request->getURIData('taskPHID');
    $transactionPHID = $request->getURIData('transactionPHID');
    $is_edit = (bool)$transactionPHID;
    $return_uri = $request->getStr('return_to');
    if(!$return_uri) $return_uri = '/phixator';

    // Declare default parameters
    $params = array('minutes' => 0, 'description' => '', 'started' => time());
    
    // In case of an edit, check if the viewer can edit this time log, then get time log values
    if($is_edit) {
      $transaction = (new PhixatorWorkLogQuery())
        ->setViewer($viewer)
        ->withPHIDs([$transactionPHID])
        ->requireCapabilities([PhabricatorPolicyCapability::CAN_EDIT])
        ->executeOne();
      if(!$transaction) return new Aphront404Response();
      $taskPHID = $transaction->getObjectPHID();
      $params = $transaction->getNewValue();
    }
    
    // Get the task and check if the viewer can edit the task
    $task = (new ManiphestTaskQuery())->setViewer($viewer)->withPHIDs([$taskPHID])->needSubscriberPHIDs(true)->executeOne();
    if(!$task) return new Aphront404Response();
    PhabricatorPolicyFilter::requireCapability($viewer, $task, PhabricatorPolicyCapability::CAN_EDIT);
    
    // If the form was submitted and is correct: validate it and create/edit the transaction
    $formErrors = [];
    if($request->isDialogFormPost()) {
      // Get fields submitted by the user
      $work_time = strtolower(trim($request->getStr('work_time')));
      $params['minutes'] = PhixatorUtil::timeStringToMinutes($work_time);
      $params['description'] = trim($request->getStr('description'));
      $timestamp = AphrontFormDateControlValue::newFromRequest($request, 'started');
      if(!$timestamp->isValid()) $formErrors[] = pht('Please choose a valid date');
      $params['started'] = $timestamp->getEpoch();
      if(!PhixatorUtil::isTimeStringFormatCorrect($work_time)) $formErrors[] = pht('Work time is incorrect. Allowed format is: 1h 1m');
      if(!$formErrors) {
        // Edit time log
        if($is_edit) {
          $transaction->setNewValue($params)->setOldValue($params['started']); // set oldValue to 'started' date for query ordering purpose
          $transaction->save();
          return (new AphrontRedirectResponse())->setURI($return_uri);
        }
        // Or create new time log
        else {
          $transaction = (new ManiphestTransaction())->setTransactionType(ManiphestTaskWorkLogTransaction::TRANSACTIONTYPE)->setNewValue($params);
          $editor = (new ManiphestTransactionEditor())->setActor($viewer)->setContentSource(PhabricatorContentSource::newFromRequest($request))->setContinueOnNoEffect(true);
          $editor->applyTransactions($task, [$transaction]);
          return (new AphrontRedirectResponse())->setURI('/'.$task->getMonogram());
        }
      }
    }
    
    // If no submitted form or the submitted form was not correct, display the new/edit form
    $dialog = $this->newDialog()
      ->setTitle($is_edit ? pht('Edit Work Time Log') : pht('Log Work Time'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setErrors($formErrors);
    $form = new PHUIFormLayoutView();
    $form->appendChild((new AphrontFormStaticControl())
        ->setLabel(pht('Task'))
        ->setValue($task->getTitle()));
    $form->appendChild((new AphrontFormTextControl())
        ->setViewer($viewer)
        ->setName('work_time')
        ->setLabel(pht('Work Time'))
        ->setPlaceholder('1h 30m')
        ->setAutofocus(true)
        ->setValue(PhixatorUtil::minutesToTimeString($params['minutes'])));
    $form->appendChild((new AphrontFormTextAreaControl())
        ->setViewer($viewer)
        ->setName('description')
        ->setLabel(pht('Work Description'))
        ->setValue($params['description']));
    $form->appendChild((new AphrontFormDateControl())
        ->setViewer($viewer)
        ->setName('started')
        ->setLabel(pht('Work Date'))
        ->setIsTimeDisabled(true)
        ->setValue($params['started']));
    $dialog->appendChild($form);
    $dialog->addHiddenInput('return_to', $return_uri);
    $dialog->addCancelButton($return_uri, pht('Close'));
    $dialog->addSubmitButton($is_edit ? pht('Edit') : pht('Add'));
    return $dialog;
  }
}
