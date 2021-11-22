<?php

class PhabricatorPhixatorApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phixator');
  }
  
  public function getBaseURI() {
    return '/phixator/';
  }

  public function getShortDescription() {
    return pht('Log work time on tasks');
  }

  public function getIcon() {
    return 'fa-hourglass-half';
  }

  public function getTitleGlyph() {
    return "\xE2\x8F\xB3";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function getApplicationOrder() {
    return 0.110;
  }

  public function getEventListeners() {
    return [new PhixatorUIEventListener()];
  }

  public function getRoutes() {
    return [
      '/phixator/' => [
        $this->getQueryRoutePattern() => 'PhixatorWorkLogListController',
        'log/' => [
          'edit/(?P<transactionPHID>[^/]+)/' => 'PhixatorWorkLogEditController',
          'delete/(?P<transactionPHID>[^/]+)/' => 'PhixatorWorkLogDeleteController',
          '(?P<taskPHID>[^/]+)/' => 'PhixatorWorkLogEditController',
        ]
      ],
    ];
  }
}
