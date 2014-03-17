<?php

namespace GoogleOAuth\Slim;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Simple MiddleWare to start the PHP session.
 *
 * @author jhenning
 */
class SessionMiddleWare extends \Slim\Middleware {
  
  public function call() {
    $this->app->log->debug('Starting session ...');
    session_cache_limiter(false);
    session_start();
    $this->next->call();
  }
}
