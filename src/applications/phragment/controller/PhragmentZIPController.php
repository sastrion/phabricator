<?php

final class PhragmentZIPController extends PhragmentController {

  private $dblob;

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, "dblob", "");
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $parents = $this->loadParentFragments($this->dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $fragment = idx($parents, count($parents) - 1, null);

    $temp = new TempFile();

    $zip = null;
    try {
      $zip = new ZipArchive();
    } catch (Exception $e) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($viewer);

      $inst = pht(
        'This system does not have the ZIP PHP extension installed. This '.
        'is required to download ZIPs from Phragment.');

      $dialog->setTitle(pht('ZIP Extension Not Installed'));
      $dialog->appendParagraph($inst);

      $dialog->addCancelButton('/phragment/browse/'.$this->dblob);
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if (!$zip->open((string)$temp, ZipArchive::CREATE)) {
      throw new Exception("Unable to create ZIP archive!");
    }

    $mappings = $this->getFragmentMappings($fragment, $fragment->getPath());

    $phids = array();
    foreach ($mappings as $path => $file_phid) {
      $phids[] = $file_phid;
    }

    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');
    foreach ($mappings as $path => $file_phid) {
      if (!isset($files[$file_phid])) {
        unset($mappings[$path]);
      }
      $mappings[$path] = $files[$file_phid];
    }

    foreach ($mappings as $path => $file) {
      $zip->addFromString($path, $file->loadFileData());
    }
    $zip->close();

    $zip_name = $fragment->getName();
    if (substr($zip_name, -4) !== '.zip') {
      $zip_name .= '.zip';
    }

    $data = Filesystem::readFile((string)$temp);
    $file = PhabricatorFile::buildFromFileDataOrHash(
      $data,
      array(
        'name' => $zip_name,
        'ttl' => time() + 60 * 60 * 24,
      ));
    return id(new AphrontRedirectResponse())
      ->setURI($file->getBestURI());
  }

  /**
   * Returns a list of mappings like array('some/path.txt' => 'file PHID');
   */
  private function getFragmentMappings(PhragmentFragment $current, $base_path) {
    $children = id(new PhragmentFragmentQuery())
      ->setViewer($this->getRequest()->getUser())
      ->needLatestVersion(true)
      ->withLeadingPath($current->getPath().'/')
      ->withDepths(array($current->getDepth() + 1))
      ->execute();

    if (count($children) === 0) {
      $path = substr($current->getPath(), strlen($base_path) + 1);
      if ($current->getLatestVersion() === null) {
        return array();
      }
      return array($path => $current->getLatestVersion()->getFilePHID());
    } else {
      $mappings = array();
      foreach ($children as $child) {
        $child_mappings = $this->getFragmentMappings($child, $base_path);
        foreach ($child_mappings as $key => $value) {
          $mappings[$key] = $value;
        }
      }
      return $mappings;
    }
  }

}
