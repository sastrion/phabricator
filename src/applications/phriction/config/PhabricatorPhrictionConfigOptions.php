<?php

final class PhabricatorPhrictionConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Phriction");
  }

  public function getDescription() {
    return pht("Options related to Phriction (wiki).");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'metamta.phriction.subject-prefix', 'string', '[Phriction]')
        ->setDescription(pht("Subject prefix for Phriction email.")),
      $this->newOption(
        'phriction.show-document-hierarchy-at-the-top', 'bool', false)
        ->setDescription(pht("Toggles document hierarchy position.")),
    );
  }

}
