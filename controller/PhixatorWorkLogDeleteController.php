<?php
/**
 * Displays and manage time log deletion
 */
final class PhixatorWorkLogDeleteController extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $transactionPHID = $request->getURIData('transactionPHID');
    $return_uri = $request->getStr('return_to') ?? '/phixator';

    // Get the transaction to delete, check that the viewer can edit it
    $transaction = (new PhixatorWorkLogQuery())
      ->setViewer($viewer)
      ->withPHIDs([$transactionPHID])
      ->requireCapabilities([PhabricatorPolicyCapability::CAN_EDIT])
      ->executeOne();
    if (!$transaction) return new Aphront404Response();

    // If the delete confirmation was submitted, "delete" the transaction (just rename its type)
    if ($request->isFormPost()) {
      $transaction->setTransactionType(ManiphestTaskWorkLogTransaction::TRANSACTIONTYPE.":deleted");
      $transaction->save();
      return (new AphrontRedirectResponse())->setURI($return_uri);
    }

    // Else display delete confirmation to the user
    $dialog = (new AphrontDialogView())
      ->setViewer($viewer)
      ->setTitle(pht('Delete Time Log?'))
      ->appendChild(pht('Are you sure to delete this time log?'))
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton($return_uri);
    return (new AphrontDialogResponse())->setDialog($dialog);
  }

}
