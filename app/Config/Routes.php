<?php

use CodeIgniter\Router\RouteCollection;

require APPPATH . 'Config/Routes/cbs_radio.php';

/** @var RouteCollection $routes */

service('auth')->routes($routes);
$routes->get('/', 'Home::index');